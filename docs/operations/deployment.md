# Deployment

## 1. Overview

Deployments are fully automated through independent per-target GitHub Actions pipelines:

| Pipeline | Trigger | Deploys to |
|---|---|---|
| `ci-backend.yml` → `cd-backend.yml` | push / PR on `main` | VPS via Ansible |
| `ci-frontend.yml` → `cd-frontend.yml` | push / PR on `main` | Vercel |
| `ci-security.yml` | push / PR on `main` | — (gate only) |

Frontend and backend pipelines are fully independent. A failing backend build never blocks a frontend deployment, and vice versa.

No manual steps are required for a normal release. Push to `main` is the only trigger.

## 2. CI Pipelines

### Backend (`ci-backend.yml`)

| Step | Tool |
|---|---|
| Install dependencies | `composer install --no-scripts` |
| Static analysis | PHPStan (result cache persisted via `actions/cache`) |
| Code style | PHP CS Fixer (dry-run) |
| Unit tests | PHPUnit |
| Dependency audit | `composer audit` |

PHP memory limit is set globally via `ini-values: memory_limit=1G` in the `shivammathur/setup-php` action.

### Frontend (`ci-frontend.yml`)

| Step | Tool |
|---|---|
| Install dependencies | `npm ci` |
| Type check | `tsc --noEmit` |
| Lint | ESLint |
| Format check | Prettier |
| Build | `next build` |
| Dependency audit | `npm audit --audit-level=high` |

`npm audit` runs at `--audit-level=high` — the known moderate-severity PostCSS transitive dependency inside Next.js is accepted risk (see [ADR 0010](../architecture/adrs/0010-security-hardening.md)).

`SULU_BASE_URL` is set to `http://localhost:8000` at build time in CI — a placeholder that allows the build to succeed. The real URL is configured in the Vercel environment.

### Security (`ci-security.yml`)

Runs on every push and pull request, independent of the other pipelines:

| Step | Tool |
|---|---|
| Filesystem CVE scan | Trivy (HIGH/CRITICAL, unfixed only) |
| SAST | Semgrep (OWASP Top 10, PHP, TypeScript rulesets) |

Trivy skips `backend/var`, `backend/vendor`, `frontend/node_modules`, and `.next`.

## 3. CD Pipelines

Each CD workflow triggers via `workflow_run` after its corresponding CI workflow completes successfully on `main`.

### Backend (`cd-backend.yml`)

1. **Build & push** — builds `backend/Dockerfile.prod`, tags with git SHA (priority 700) + `latest`, pushes to GHCR (`ghcr.io/<owner>/architecture-hub-backend`)
2. **Deploy** — runs `ansible/playbooks/deploy.yml` against the production inventory:
   - Prunes unused Docker images (frees disk before pulling)
   - Templates `.env`, pulls the new backend image
   - Runs pending `doctrine:migrations:migrate` against the new image in a
     one-off container *before* cutover, bounded by a 300s timeout (async/poll)
     — so schema/data changes land before the new code goes live, not after
   - Recreates containers with the new image
   - Health-checks `http://127.0.0.1:8000/admin/` (12 retries × 5 s)
   - Warms the Symfony cache inside the running container

### Frontend (`cd-frontend.yml`)

1. Installs Vercel CLI
2. Runs `vercel deploy --prod` from the `frontend/` directory

## 4. Required GitHub Secrets

Add these in **Settings → Secrets and variables → Actions**:

| Secret | Used by | Description |
|---|---|---|
| `SSH_PRIVATE_KEY` | `cd-backend` | Private key authorized on the VPS and the EC2 secondary (same keypair) |
| `PRODUCTION_HOST` | `cd-backend` | Mikrus VPS IP address or hostname (primary) |
| `PRODUCTION_HOST_EC2` | `cd-backend` | EC2 secondary's Elastic IP (Terraform output — see [failover-runbook.md](failover-runbook.md)) |
| `ANSIBLE_VAULT_PASSWORD` | `cd-backend` | Password for `ansible/group_vars/vault.yml` |
| `VERCEL_TOKEN` | `cd-frontend` | Personal access token from Vercel Account Settings → Tokens |
| `VERCEL_ORG_ID` | `cd-frontend` | Find under Vercel team Settings → General |
| `VERCEL_PROJECT_ID` | `cd-frontend` | Find under Vercel project Settings → General |

## 5. Vercel Environment Variables

Set via `vercel env add` or the Vercel dashboard under **Project → Settings → Environment Variables**:

| Variable | Environment | Value |
|---|---|---|
| `SULU_BASE_URL` | Production | `https://patrykapi.tojest.dev` |

## 6. One-Time Server Provisioning

Run once on a fresh VPS before the first deployment:

```bash
# 1. Fill in real values and encrypt secrets
cp ansible/group_vars/all/vault.yml.example ansible/group_vars/all/vault.yml
# edit vault.yml with real passwords/tokens, then:
ansible-vault encrypt ansible/group_vars/all/vault.yml

# 2. Update ansible/group_vars/all/main.yml with your domain names and GitHub username

# 3. Provision the server (installs Docker, nginx, renders the nginx vhost with correct server_names)
#    Pass the VPS IP via ansible_host — the inventory uses a stable alias, never a hardcoded IP.
cd ansible
ansible-playbook playbooks/provision.yml -i inventory/production.ini \
  -e "ansible_user=root ansible_host=YOUR_VPS_IP" --ask-vault-pass
```

> **Mikrus note:** SSH port is 10130 (Mikrus NAT forwards to internal port 22). Nginx listens on `[::]:80` (IPv6 only — no dedicated IPv4). See `docs/operations/mikrus-server.md` (gitignored) for full server details.

After provisioning, push to `main` to trigger the first automated deployment.

## 7. First-Deploy Database Initialisation (and content repair)

The automated deploy pipeline does **not** initialise the database. Run these once after the first container is up — schema via migrations (unchanged), content via the same Doctrine Fixtures used in local dev:

```bash
# Set these to match your server — see docs/operations/mikrus-server.md (gitignored)
SERVER="ssh -p YOUR_SSH_PORT deploy@YOUR_VPS_HOST"
CONTAINER="architecture-hub-backend-1"

# 1. Create the Sulu schema (all tables)
$SERVER "docker exec $CONTAINER php bin/console doctrine:schema:create --no-interaction"

# 2. Security roles, the admin user, and one homepage per webspace — IN THIS
#    ORDER, and BEFORE fixtures. ArticleFixture looks up the admin's own
#    "Adam Ministrator" contact as the default author (created as a side
#    effect of sulu:security:user:create) and several fixtures require the
#    homepage to exist as their parent page — run fixtures first and
#    everything that depends on either one silently no-ops instead of
#    erroring, which is worse: it looks like success.
$SERVER "docker exec $CONTAINER php bin/console sulu:security:init --no-interaction"
$SERVER "docker exec $CONTAINER php bin/console sulu:security:role:create --no-interaction User Sulu"
$SERVER "docker exec $CONTAINER php bin/console sulu:security:user:create --no-interaction admin Adam Ministrator admin@example.com en User admin"
$SERVER "docker exec $CONTAINER php bin/console sulu:page:initialize --no-interaction"

# 3. Load all content — the SAME fixture classes as local dev
#    (backend/src/DataFixtures/): tags, categories, contacts, articles,
#    author pages, exercises/QuestionSets, learning paths. Every fixture is
#    idempotent (upsert-by-slug/title), so this is also the correct way to
#    *repair* prod content later — just re-run it, safely, any time,
#    regardless of step 2 (that part only matters for a truly empty DB).
$SERVER "docker exec $CONTAINER php bin/console doctrine:fixtures:load --no-interaction --append"

# 4. Clear the backend's own HTTP cache (FOSHttpCacheBundle). Without this,
#    any URL that existed before this reinit (e.g. an exercise page at the
#    same slug, now backed by a different page UUID) keeps serving its
#    stale pre-reinit response indefinitely — the DB is correct, but the
#    live site silently isn't. Confirmed by curling an exercise page right
#    after fixtures loaded: it returned the *old* UUID until this ran.
$SERVER "docker exec $CONTAINER php bin/console fos:httpcache:clear --no-interaction"
```

Steps 2-3 are exactly what `sulu:build <env>`'s `user`/`security`/`homepage`/`fixtures`
targets already do internally (see `Sulu\Bundle\CoreBundle\Build\FixturesBuilder` —
it calls `doctrine:fixtures:load` with no `--group` filter, so it was never actually
dev-only) — this section just runs them individually, in the corrected order, to
keep schema management on migrations explicitly rather than also switching to
`sulu:build`'s `doctrine:schema:update`. (Sulu's own internal build order runs
fixtures *before* user/homepage and gets away with it only because local dev's
docs already document — and require — a second `doctrine:fixtures:load --append`
pass afterward; putting user/homepage first here avoids needing that second pass
at all. Verified end-to-end locally, from a fully torn-down database, before
running this against production.)

### Why this replaced the old seed-dump migration

Content used to be seeded from a `seed.sql.gz` production dump
(`Version20260629000000`) plus a hand-written data migration
(`Version20260704090000`) for QuestionSets. Both files stay in
`backend/migrations/` as historical, already-applied record — migrations are
forward-only, never edit or delete one that's already run — but they're no
longer the recommended path: the dump predates the exercise/quiz feature, so
a from-scratch reinit using only migrations silently produced a site with
**no exercise pages at all**, since page creation was never captured
anywhere except manual admin authoring and the dev-only fixture. Fixtures
are now the single place that content is defined for both dev and prod, so
it can't drift between the two again.

If doing a genuine ground-up reinit (fresh empty database), mark the
already-applied migrations first so `doctrine:migrations:migrate` doesn't
try to replay the old versions:

```bash
$SERVER "docker exec $CONTAINER php bin/console doctrine:migrations:sync-metadata-storage --no-interaction"
for v in 'Sulu\Article\Migrations\Version20260429120000' \
         'Sulu\Page\Migrations\Version20260429120000' \
         'Sulu\Snippet\Migrations\Version20260429120000' \
         'DoctrineMigrations\Version20260629000000' \
         'DoctrineMigrations\Version20260701184007' \
         'DoctrineMigrations\Version20260702144733' \
         'DoctrineMigrations\Version20260703000045' \
         'DoctrineMigrations\Version20260704090000'; do
  $SERVER "docker exec $CONTAINER php bin/console doctrine:migrations:version '$v' --add --no-interaction"
done
```

Migrations remain the correct mechanism for *schema* changes going forward
(new tables/columns) — this change only affects how *content* is seeded.

### Reset the admin password

The seed migration sets the admin password to `!!` (disabled). Reset it after the first deploy:

```bash
ssh -p YOUR_SSH_PORT deploy@YOUR_VPS_HOST \
  "docker exec -it architecture-hub-backend-1 php bin/console app:user:set-password admin"
# enters new password interactively (hidden input, confirmed twice)
```

The command uses Symfony's password hasher — the same algorithm Sulu uses — and never passes the password through the shell.

## 8. Manual Deploy (emergency)

Both `ansible_host` and `image_tag` are required — neither has a default.

```bash
ansible-playbook ansible/playbooks/deploy.yml \
  -i ansible/inventory/production.ini \
  --vault-password-file ~/.vault_pass \
  -e "ansible_host=YOUR_VPS_IP image_tag=<git-sha>"
```

### Rolling back a bad deploy

Re-run the same command with the previous known-good commit SHA as `image_tag`
(every CI-built image is tagged with its commit SHA — find one via `git log
--oneline` on `main`, or by browsing package versions in GHCR). The deploy
role runs migrations before cutting over to the new container (see §3), so
rolling back the image tag re-deploys old code against whatever schema is
currently in the database.

**This does not roll back the database.** Doctrine migrations are
forward-only in this pipeline — there is no automated `doctrine:migrations:
migrate down`. Two cases:

- **Bad code, no new migration involved:** rolling back the image tag is
  sufficient.
- **Bad migration:** prefer fixing forward with a new migration that corrects
  the problem, rather than manually running a migration's `down()` against
  production. If the migration must be reverted, do it manually and
  deliberately (`docker exec architecture-hub-backend-1 php bin/console
  doctrine:migrations:migrate <previous-version> --no-interaction`), verify
  the resulting schema, and only then roll back the image tag.

There is no automated pre-migration backup — see [GitHub issue
#3](https://github.com/PatrykOstrzolek/architecture-hub-showcase/issues/3).

## 9. Pre-commit Hooks

`husky` runs two sets of checks before every commit (`frontend/.husky/pre-commit`):

```
*.{ts,tsx}        → prettier --write → eslint --fix   (lint-staged, frontend only)
backend/**/*.php  → php-cs-fixer fix  (auto-fixed and re-staged)
                  → phpstan analyse   (full project, result cache keeps it fast)
```

PHP checks only run when PHP files are staged — pure frontend commits are unaffected.

Hooks run on manual commits. CI remains the authoritative gate for all checks.

## 10. Deployment Topology

```
                Internet
                   │
               Cloudflare
              /           \
    patrykarc.tojest.dev   patrykapi.tojest.dev
         (admin)                  (api)
              \                  /
           [ nginx / VPS :80 ]
           /admin/*  |  *.json, /api/*, /media/*
                     |
                [ Sulu :8000 ]
                     │
              [ PostgreSQL :5432 ]

   Next.js frontend → Vercel (arch-hub-tawny.vercel.app)
   fetches from patrykapi.tojest.dev at runtime
```

All backend services run as Docker containers managed by `docker-compose.prod.yml`. Nginx is installed directly on the host and proxies to containers bound to `127.0.0.1`.

### nginx security notes

- **`merge_slashes off`** on the admin block — prevents `//admin` from being normalised to `/admin` by nginx before forwarding. Without this, nginx normalises for routing but forwards the original URI to the backend, so PHP receives `//admin`, which does not match Sulu's `^\/admin` check and falls into website context.
- Both server blocks enforce strict path allowlists; unmatched paths return `404` directly from nginx without touching the backend.
- The Sulu Docker image adds `server-opts.d/a_headless.conf` (sorts before `security.conf` alphabetically) to allow `*.json` URL suffixes that the default dotfile-blocking rule in `security.conf` would otherwise deny (`location ~ /\.` blocks `/.json`).

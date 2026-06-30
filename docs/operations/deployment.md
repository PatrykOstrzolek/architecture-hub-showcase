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
| Static analysis | PHPStan |
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

1. **Build & push** — builds `backend/Dockerfile.prod`, tags with git SHA + `latest`, pushes to GHCR (`ghcr.io/<owner>/architecture-hub-backend`)
2. **Deploy** — runs `ansible/playbooks/deploy.yml` against the production inventory; Ansible pulls the new image, templates `.env`, restarts containers

### Frontend (`cd-frontend.yml`)

1. Installs Vercel CLI
2. Runs `vercel deploy --prod` from the `frontend/` directory

## 4. Required GitHub Secrets

Add these in **Settings → Secrets and variables → Actions**:

| Secret | Used by | Description |
|---|---|---|
| `SSH_PRIVATE_KEY` | `cd-backend` | Private key authorized on the VPS |
| `PRODUCTION_HOST` | `cd-backend` | VPS IP address or hostname |
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

## 7. First-Deploy Database Initialisation

The automated deploy pipeline does **not** initialise the database. Run these once after the first container is up:

```bash
# Set these to match your server — see docs/operations/mikrus-server.md (gitignored)
SERVER="ssh -p YOUR_SSH_PORT deploy@YOUR_VPS_HOST"
CONTAINER="architecture-hub-backend-1"

# 1. Create the Sulu schema (all tables)
$SERVER "docker exec $CONTAINER php bin/console doctrine:schema:create --no-interaction"

# 2. Initialise the Doctrine Migrations tracking table
$SERVER "docker exec $CONTAINER php bin/console doctrine:migrations:sync-metadata-storage --no-interaction"

# 3. Mark Sulu's internal data-migration versions as done
#    (they convert legacy tag-name data; irrelevant on a fresh install)
$SERVER "docker exec $CONTAINER php bin/console doctrine:migrations:version \
  'Sulu\Article\Migrations\Version20260429120000' --add --no-interaction"
$SERVER "docker exec $CONTAINER php bin/console doctrine:migrations:version \
  'Sulu\Page\Migrations\Version20260429120000' --add --no-interaction"
$SERVER "docker exec $CONTAINER php bin/console doctrine:migrations:version \
  'Sulu\Snippet\Migrations\Version20260429120000' --add --no-interaction"

# 4. Run the seed migration (loads demo content)
$SERVER "docker exec $CONTAINER php bin/console doctrine:migrations:migrate --no-interaction"
```

### Why not `sulu:build prod`?

`sulu:build prod` also runs `doctrine:fixtures:load`, which conflicts with the seed migration data. Use the manual sequence above instead.

### Reset the admin password

The seed migration sets the admin password to `!!` (disabled). Reset it after the first deploy:

```bash
# Hash the password and write it to the DB in one shot.
# Uses PHP stdin to avoid bcrypt's $ signs being expanded by the remote shell.
ssh -p YOUR_SSH_PORT deploy@YOUR_VPS_HOST 'docker exec -i architecture-hub-backend-1 php' << 'PHP'
<?php
$hash = password_hash('YOUR_PASSWORD_HERE', PASSWORD_BCRYPT, ['cost' => 13]);
$url = parse_url(getenv('DATABASE_URL'));
$dsn = "pgsql:host={$url['host']};port={$url['port']};dbname=" . ltrim($url['path'], '/');
$pdo = new PDO($dsn, $url['user'], $url['pass']);
$pdo->prepare("UPDATE se_users SET password = ? WHERE username = ?")->execute([$hash, 'admin']);
echo "Done: $hash\n";
PHP
```

> **Why not `doctrine:query:sql` with the hash?** Bcrypt hashes contain `$` signs that the remote shell expands as variable names, silently corrupting the hash. The PHP stdin approach bypasses the shell entirely.
>
> `sulu:security:user:change-password` does not exist in this Sulu version.

## 8. Manual Deploy (emergency)

Both `ansible_host` and `image_tag` are required — neither has a default.

```bash
ansible-playbook ansible/playbooks/deploy.yml \
  -i ansible/inventory/production.ini \
  --vault-password-file ~/.vault_pass \
  -e "ansible_host=YOUR_VPS_IP image_tag=<git-sha>"
```

## 9. Pre-commit Hooks

The frontend uses `husky` + `lint-staged` to enforce formatting and linting before every commit:

```
*.{ts,tsx} → prettier --write → eslint --fix
```

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

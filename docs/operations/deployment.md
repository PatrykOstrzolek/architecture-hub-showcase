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
2. **Deploy** — runs `ansible/playbooks/deploy.yml` against the production inventory; Ansible pulls the new image, templates `.env`, restarts containers, runs cache warmup

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
| `VERCEL_ORG_ID` | `cd-frontend` | `team_gWZc553cRPxf1ZKpFg2yRxNq` (archhub team) |
| `VERCEL_PROJECT_ID` | `cd-frontend` | `prj_yZr1N3ce8L5Z96CfOyJjP1om1bGw` (arch-hub project) |

## 5. Vercel Environment Variables

Set in the Vercel dashboard under **Project → Settings → Environment Variables**:

| Variable | Environment | Value |
|---|---|---|
| `SULU_BASE_URL` | Production | `https://api.yourdomain.com` |

## 6. One-Time Server Provisioning

Run once on a fresh VPS before the first deployment:

```bash
# 1. Fill in real values and encrypt secrets
cp ansible/group_vars/vault.yml.example ansible/group_vars/vault.yml
# edit vault.yml with real passwords, then:
ansible-vault encrypt ansible/group_vars/vault.yml

# 2. Update ansible/group_vars/all.yml with your real domain and GitHub username

# 3. Update ansible/inventory/production.ini with your VPS IP

# 4. Provision the server (installs Docker, nginx, UFW, fail2ban)
ansible-playbook ansible/playbooks/provision.yml -i ansible/inventory/production.ini
```

After provisioning, push to `main` to trigger the first automated deployment.

The seed migration (`Version20260629000000`) runs automatically as part of `doctrine:migrations:migrate` on the first deploy and loads the initial demo content. The admin password is intentionally disabled (`!!`) in the dump — reset it after the first deploy:

```bash
ssh deploy@your-server \
  "docker exec -it architecture-hub-backend-1 php bin/console sulu:security:user:change-password admin"
```

## 7. Manual Deploy (emergency)

```bash
ansible-playbook ansible/playbooks/deploy.yml \
  -i ansible/inventory/production.ini \
  --vault-password-file ~/.vault_pass
```

## 8. Pre-commit Hooks

The frontend uses `husky` + `lint-staged` to enforce formatting and linting before every commit:

```
*.{ts,tsx} → prettier --write → eslint --fix
```

Hooks run on manual commits. CI remains the authoritative gate for all checks.

## 9. Deployment Topology

```
                Internet
                   │
         ┌─────────┴──────────┐
    [ Vercel ]           [ nginx / VPS ]
   Next.js frontend       /           \
  arch-hub.vercel.app  admin.domain   api.domain
                              \           /
                           [ Sulu :8000 ]
                                  │
                           [ PostgreSQL :5432 ]
```

All backend services run as Docker containers managed by `docker-compose.prod.yml`. Nginx is installed directly on the host and proxies to containers bound to `127.0.0.1`.

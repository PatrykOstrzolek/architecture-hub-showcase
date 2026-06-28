# Deployment

## 1. Overview

Deployments are fully automated through a two-stage GitHub Actions pipeline:

- **CI** (`.github/workflows/ci.yml`) — quality gate on every push and pull request
- **CD** (`.github/workflows/cd.yml`) — triggered automatically after CI passes on `main`

No manual steps are required for a normal release. Push to `main` is the only trigger.

## 2. CI Pipeline

Runs in parallel across two jobs:

| Job | Steps |
|---|---|
| **Backend** | `composer install` → PHPStan → PHP CS Fixer (dry-run) → PHPUnit |
| **Frontend** | `npm ci` → typecheck → lint → format:check → `next build` |

PHP memory limit is set globally via `ini-values: memory_limit=1G` in the `shivammathur/setup-php` action — no per-command `-d` flags needed.

## 3. CD Pipeline

Triggers via `workflow_run` after CI completes successfully on `main`.

### Job 1 — Build & push Docker images

Builds both production images and pushes to GitHub Container Registry (GHCR):

| Image | Source | Tag |
|---|---|---|
| `ghcr.io/<owner>/architecture-hub-backend` | `backend/Dockerfile.prod` | `<git-sha>` + `latest` |
| `ghcr.io/<owner>/architecture-hub-frontend` | `frontend/Dockerfile` | `<git-sha>` + `latest` |

Authentication uses the built-in `GITHUB_TOKEN` — no additional credentials needed for GHCR.

### Job 2 — Deploy via Ansible

Runs after images are pushed. Installs Ansible on the runner, then executes `ansible/playbooks/deploy.yml` against the production inventory:

1. Logs in to GHCR on the server
2. Templates `.env` from vault variables
3. Pulls the new images
4. Restarts containers (`docker compose up -d --remove-orphans`)
5. Runs Symfony cache warmup inside the backend container

## 4. Required GitHub Secrets

Add these in **Settings → Secrets and variables → Actions**:

| Secret | Description |
|---|---|
| `SSH_PRIVATE_KEY` | Private key whose public key is authorized on the production server |
| `PRODUCTION_HOST` | VPS IP address or hostname |
| `ANSIBLE_VAULT_PASSWORD` | Password used to encrypt `ansible/group_vars/vault.yml` |

## 5. One-Time Server Provisioning

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

## 6. Manual Deploy (emergency)

To deploy outside of GitHub Actions:

```bash
ansible-playbook ansible/playbooks/deploy.yml \
  -i ansible/inventory/production.ini \
  --vault-password-file ~/.vault_pass
```

## 7. Deployment Topology

```
                Internet
                   │
              [ nginx ]
             /         \
    yourdomain.com   admin.yourdomain.com
         │                   │
   [ Next.js :3000 ]   [ Sulu :8000 ]
                \           /
              [ MySQL :3306 ]
```

All services run as Docker containers managed by `docker-compose.prod.yml`. Nginx is installed directly on the host and proxies to containers bound to `127.0.0.1` (not exposed publicly).

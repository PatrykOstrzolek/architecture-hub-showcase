# Infrastructure

## 1. Environments

| Environment | Purpose | Runtime |
|---|---|---|
| **Development** | Local development | Docker / Colima (macOS) |
| **Production** | Live application | VPS (Ubuntu) + Docker CE |

## 2. Local Development Stack

- **Container runtime**: Docker via [Colima](https://github.com/abiosoft/colima)
- **Backend image**: `serversideup/php:8.5-fpm-nginx` with `intl`, `gd`, `apcu` extensions
- **PHP version**: 8.5 (pinned in `backend/docker/Dockerfile`)
- **Node version**: 24

PHP memory limit for heavy operations (composer install, cache warmup):

```bash
php -d memory_limit=1G bin/console cache:clear
php -d memory_limit=1G $(which composer) install
```

## 3. Production Stack

### Server

Bare VPS running Ubuntu. Provisioned once via Ansible (`ansible/playbooks/provision.yml`).

Installed by the `common` and `docker` Ansible roles:
- Docker CE + Compose plugin
- nginx (host-level reverse proxy)
- UFW firewall (allow 22/80/443 only)
- fail2ban

### Application services

Defined in `docker-compose.prod.yml`, running as Docker containers:

| Service | Image | Internal port | Exposed to host |
|---|---|---|---|
| `db` | `mysql:8.0` | 3306 | No |
| `backend` | GHCR (built from `backend/Dockerfile.prod`) | 8080 | `127.0.0.1:8000` |
| `frontend` | GHCR (built from `frontend/Dockerfile`) | 3000 | `127.0.0.1:3000` |

Backend and frontend ports are bound to `127.0.0.1` — not publicly reachable. All public traffic goes through nginx.

### nginx (host-level)

Configured by the `nginx` Ansible role. Two virtual hosts:

- `yourdomain.com` → proxies to Next.js (`127.0.0.1:3000`)
- `admin.yourdomain.com` → proxies to Sulu (`127.0.0.1:8000`)

Template: `ansible/roles/nginx/templates/app.conf.j2`.

### Container registry

Docker images are built in CI and pushed to **GitHub Container Registry (GHCR)** under `ghcr.io/<owner>/architecture-hub-{backend,frontend}`. Each release is tagged with the git SHA and `latest`.

## 4. Infrastructure as Code

All server configuration is in `ansible/`:

```
ansible/
  ansible.cfg               # default inventory, roles path
  inventory/
    production.ini          # production host(s)
    staging.ini             # staging host(s)
  group_vars/
    all.yml                 # non-secret variables (domain, paths)
    vault.yml               # encrypted secrets (ansible-vault)
    vault.yml.example       # template — copy and encrypt before use
  roles/
    common/                 # system packages, deploy user, UFW
    docker/                 # Docker CE + Compose plugin
    nginx/                  # nginx install + virtual host config
    app/                    # docker compose pull + restart + cache warmup
  playbooks/
    provision.yml           # one-time server setup
    deploy.yml              # runs on every release
```

Secrets are stored in `ansible/group_vars/vault.yml`, encrypted with Ansible Vault. The vault password is kept in the `ANSIBLE_VAULT_PASSWORD` GitHub Secret and never committed.

## 5. Key Constraints

- **PHP memory**: 1 G required for composer install, PHPStan, and cache warmup. Set via `ini-values` in CI, via `-d` flag locally.
- **Headless architecture**: Next.js calls Sulu server-side via `SULU_BASE_URL`. Sulu does not need to be publicly accessible — only the admin subdomain is exposed.
- **Stateful volumes**: Sulu media uploads are persisted in a named Docker volume (`uploads`) and survive container restarts and image updates.

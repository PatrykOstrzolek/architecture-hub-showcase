# Infrastructure

## 1. Environments

| Environment | Purpose | Runtime |
|---|---|---|
| **Development** | Local development | Docker / Colima (macOS) |
| **Production** | Live application | VPS (Ubuntu) + Docker CE for backend; Vercel for frontend |

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

### Frontend — Vercel

The Next.js frontend is deployed to **Vercel**. Vercel owns the primary domain
(`yourdomain.com`) and handles TLS, CDN, and the Next.js build. The frontend
fetches content from the Sulu headless API over the public internet via
`SULU_BASE_URL`, which is set as a Vercel environment variable.

`SULU_BASE_URL` must point to `https://api.yourdomain.com` (see nginx below).

### Backend — VPS

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

Both ports are bound to `127.0.0.1` — not publicly reachable. All public traffic goes through nginx.

### nginx (host-level)

Configured by the `nginx` Ansible role. Two virtual hosts:

- `api.yourdomain.com` → proxies to Sulu (`127.0.0.1:8000`) — consumed by Vercel frontend
- `admin.yourdomain.com` → proxies to Sulu (`127.0.0.1:8000`) — Sulu admin UI

Both point to the same backend process. Template: `ansible/roles/nginx/templates/app.conf.j2`.

### Container registry

Docker images are built in CI and pushed to **GitHub Container Registry (GHCR)** under
`ghcr.io/<owner>/architecture-hub-backend`. Each release is tagged with the git SHA and `latest`.

## 4. Infrastructure as Code

All server configuration is in `ansible/`:

```
ansible/
  ansible.cfg               # default inventory, roles path
  inventory/
    production.ini          # production host(s)
    staging.ini             # staging host(s)
  group_vars/
    all.yml                 # non-secret variables (domain, api_domain, paths)
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

Secrets are stored in `ansible/group_vars/vault.yml`, encrypted with Ansible Vault. The vault
password is kept in the `ANSIBLE_VAULT_PASSWORD` GitHub Secret and never committed.

## 5. Vercel Setup (one-time)

Before the first deployment:

1. Create a Vercel project linked to the `frontend/` directory.
2. Set the following environment variables in the Vercel dashboard:
   - `SULU_BASE_URL` = `https://api.yourdomain.com`
3. Run `vercel link` locally to generate `frontend/.vercel/project.json`, then commit it.
4. Add three GitHub Secrets: `VERCEL_TOKEN`, `VERCEL_ORG_ID`, `VERCEL_PROJECT_ID`.

The `VERCEL_TOKEN` is a personal access token from the Vercel dashboard (Account Settings → Tokens).
`VERCEL_ORG_ID` and `VERCEL_PROJECT_ID` are found in `frontend/.vercel/project.json` after linking.

## 6. Security

### Secrets

`backend/.env` is **not committed** to version control (`.gitignore` blocks it). The file is templated at deploy time by Ansible from `ansible/group_vars/vault.yml` (Ansible Vault encrypted). Never commit real secrets to `backend/.env` — use `.env.example` for documentation.

### HTTP Security Headers

**Next.js frontend** — headers are set globally in `frontend/next.config.ts` via the `headers()` function:

| Header | Value |
|---|---|
| `X-Frame-Options` | `DENY` |
| `X-Content-Type-Options` | `nosniff` |
| `Strict-Transport-Security` | `max-age=63072000; includeSubDomains; preload` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` |
| `Content-Security-Policy` | baseline (see [ADR 0010](../adrs/0010-security-hardening.md)) |

**nginx backend** — headers are injected in `ansible/roles/nginx/templates/app.conf.j2`. The API vhost adds a strict `Content-Security-Policy: default-src 'none'`; the admin vhost uses `X-Frame-Options: SAMEORIGIN` to allow the Sulu admin iframe.

### Symfony Profiler

The Symfony profiler (`/_profiler`, `/_wdt`) is **disabled in production** via `when@prod` in `backend/config/packages/framework.yaml`. It remains enabled in the `dev` environment. Do not remove this block — the profiler exposes full request payloads, SQL queries, and logs to anyone with the URL.

### CMS HTML Sanitization

Rich-text HTML from Sulu is sanitized with `sanitize-html` before rendering via `dangerouslySetInnerHTML`. The allowlist and configuration live in `frontend/lib/sanitize.ts`. See [ADR 0010](../adrs/0010-security-hardening.md).

## 7. Key Constraints

- **PHP memory**: 1 G required for composer install, PHPStan, and cache warmup. Set via `ini-values` in CI, via `-d` flag locally.
- **Headless architecture**: Vercel calls Sulu over the public internet via `api.yourdomain.com`. The admin subdomain is separate and carries the Sulu admin UI.
- **Stateful volumes**: Sulu media uploads are persisted in a named Docker volume (`uploads`) and survive container restarts and image updates.
- **Content freshness**: Next.js caches Sulu responses for 60 seconds (Data Cache). See [ADR 0008](../architecture/adrs/0008-nextjs-caching-strategy.md).

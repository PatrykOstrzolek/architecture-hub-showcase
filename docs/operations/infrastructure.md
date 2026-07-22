# Infrastructure

## 1. Environments

| Environment | Purpose | Runtime |
|---|---|---|
| **Development** | Local development | Host PHP (mise) + Docker for Postgres/Mailpit |
| **Production** | Live application | VPS (Ubuntu) + Docker CE for backend; Vercel for frontend |

## 2. Local Development Stack

- **Container runtime**: any local Docker (Docker Desktop, Docker Engine, etc.) — used only for Postgres and Mailpit
- **PHP**: runs on the host, version-managed by [mise](https://mise.jdx.dev) (pinned in the repo's `mise.toml`)
- **Dev server**: [Symfony CLI](https://symfony.com/download) (`symfony serve`), installed separately via `brew install symfony-cli/tap/symfony-cli` — not mise-managed
- **Node version**: 24

See [ADR-0017](../architecture/adrs/0017-mise-managed-host-php-for-local-dev.md) for why local dev moved off the `serversideup/php:8.5-fpm-nginx` container (removed; prod builds from a separate `backend/Dockerfile.prod` and is unaffected).

PHP's memory limit and opcache are set via `backend/php-conf.d/local.ini`, loaded through the `PHP_INI_SCAN_DIR` env var declared in `mise.toml`'s `[env]` table — no inline `-d` flag needed:

```bash
bin/console cache:clear
composer install
```

## 3. Production Stack

### Frontend — Vercel

The Next.js frontend is deployed to **Vercel**. Vercel owns the primary domain
(`yourdomain.com`) and handles TLS, CDN, and the Next.js build. The frontend
fetches content from the Sulu headless API over the public internet via
`SULU_BASE_URL`, which is set as a Vercel environment variable.

`SULU_BASE_URL` must point to `https://api.yourdomain.com` (see nginx below).

### Backend — VPS (Mikrus)

[Mikrus](https://mikr.us) VPS running Ubuntu, plan 2.1 (1 GB RAM, 10 GB NVMe, Finland).
Provisioned once via Ansible (`ansible/playbooks/provision.yml`).

**Networking:** Mikrus provides no dedicated IPv4. Web traffic reaches the server via IPv6.
Mikrus subdomains (`patrykarc.tojest.dev`, `patrykapi.tojest.dev`) proxy HTTPS → nginx on port 80.
SSH uses port-forwarding: external port 10130 → internal port 22.

Installed by the `common` and `docker` Ansible roles:
- Docker CE + Compose plugin
- nginx (host-level reverse proxy, listens on `[::]:80`)
- UFW firewall (allows ports 22/80/443)
- fail2ban

### Application services

Defined in `docker-compose.prod.yml`, running as Docker containers:

| Service | Image | Internal port | Exposed to host |
|---|---|---|---|
| `db` | `postgres:16` | 5432 | No |
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

### EC2 secondary (manual failover drill)

A second backend instance runs on AWS EC2 (provisioned via Terraform, `terraform/`), as a DevOps training exercise in active-passive failover — not real HA. See [failover-runbook.md](failover-runbook.md) for what it does and doesn't cover, and [ADR-0016](../architecture/adrs/0016-ec2-secondary-failover-drill.md) for the full rationale and rejected alternatives.

Key differences from the primary:

- **No local Postgres.** The secondary's `backend` container connects to the *primary's* Postgres over a private **Tailscale** mesh (both hosts are tailnet members). The Postgres port is published only on the primary, bound to the primary's own Tailscale IP — never `0.0.0.0` — so it's unreachable from the public internet regardless of UFW (Docker's port publishing bypasses UFW's chains entirely; the bind address is the actual control).
- **No owned domain, but real TLS.** There's no domain to attach a Sulu/Mikrus-style `server_name` to, so the secondary's nginx vhost (`app-secondary.conf.j2`) is a single catch-all `server_name _;` block — but it does terminate TLS, using a **sslip.io** wildcard-DNS hostname for the Elastic IP (`52-57-29-121.sslip.io`, see `ansible/group_vars/secondary/main.yml: ec2_public_hostname`) as the cert subject. AWS's own free public DNS hostname for the same IP (`terraform/outputs.tf: public_dns`) was tried first and rejected outright by Let's Encrypt — its CA policy refuses to issue for any `*.amazonaws.com` name, confirmed against the live ACME server, not a config issue — so sslip.io is used instead. A `certbot` Ansible role (`when: not is_primary`) obtains the cert via Let's Encrypt's HTTP-01 **webroot** challenge — nginx keeps running the whole time (no port-80 conflict, no downtime), and certbot's own `certbot.timer` handles renewal. Since there's still no domain to validate a `Host` header against, it's fixed to that known hostname rather than passed through from the request — an unvalidated Host header forwarded to the backend is a real injection vector, not a hypothetical one (caught by `ci-security.yml`'s Semgrep gate). The primary's TLS, by contrast, is terminated by Mikrus's own platform proxy in front of nginx — this repo has never had to manage a cert for it, and still doesn't.
- **Migrations run on the primary only** (`is_primary` gate in the `app` Ansible role) — both hosts share one DB, so there's no need for both to race `doctrine:migrations:migrate`.
- **Media uploads are not shared.** The secondary's `uploads` volume is local and empty — see the failover runbook's limitations section.
- **IMDSv2 enforced.** `terraform/main.tf` sets `metadata_options.http_tokens = "required"`, blocking the older, SSRF-vulnerable IMDSv1 path to the instance metadata service.

Currently stopped and out of the routine release path — `cd-backend.yml` only deploys to the primary. App code reaches the secondary only via a manual `ansible-playbook playbooks/deploy.yml --limit secondary` run (see [deployment.md §8](deployment.md#8-manual-deploy-emergency)), typically right before a failover drill. Infra provisioning (`cd-infra.yml`) takes a required `target: primary|secondary` input since it's a manual, deliberate trigger either way.

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

### Sulu HTML Frontend — Must Remain Unreachable

Sulu's `HeadlessWebsiteController` serves HTML at the plain URL of every page (no `.json` suffix). This HTML rendering path is an internal Sulu detail and is **not part of the public API** — Next.js on Vercel is the only frontend.

Both nginx vhosts enforce this:

- **`admin.`** — proxies only `/admin`, `/build`, `/bundles`, `/token`. Everything else returns 404.
- **`api.`** — proxies only `*.json` requests, `/api/*`, and `/media/*`. Everything else returns 404.

**Do not remove these restrictions.** If the `location /` catch-all is changed to proxy to the backend, the Sulu HTML frontend becomes publicly accessible on those subdomains, contradicting the headless architecture decision ([ADR-0003](../architecture/adrs/0003-headless-content-delivery.md)).

### CMS HTML Sanitization

Rich-text HTML from Sulu is sanitized with `sanitize-html` before rendering via `dangerouslySetInnerHTML`. The allowlist and configuration live in `frontend/lib/sanitize.ts`. See [ADR 0010](../adrs/0010-security-hardening.md).

## 7. Key Constraints

- **PHP memory**: 1 G required for composer install, PHPStan, and cache warmup. Set via `ini-values` in CI, via the `PHP_MEMORY_LIMIT` container env var locally and in prod.
- **Headless architecture**: Vercel calls Sulu over the public internet via `api.yourdomain.com`. The admin subdomain is separate and carries the Sulu admin UI.
- **Stateful volumes**: Sulu media uploads are persisted in a named Docker volume (`uploads`) and survive container restarts and image updates. Neither `uploads` nor `db_data` has an automated backup — a host/disk failure loses both with no recovery path.
- **Search index is not persisted**: `SEAL_DSN` (`loupe:///var/www/html/var/indexes`) writes inside the container's own filesystem, not a mounted volume. It's rebuilt from nothing on every deploy (`recreate: always`), and there is no reindex step anywhere in the CD pipeline — search results may be stale or empty until Sulu's own indexing catches up on content access/save.
- **Content freshness**: Next.js caches Sulu responses for 60 seconds (Data Cache). See [ADR 0008](../architecture/adrs/0008-nextjs-caching-strategy.md).

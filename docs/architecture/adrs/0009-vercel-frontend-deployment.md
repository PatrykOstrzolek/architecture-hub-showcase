# ADR 0009: Deploy Next.js Frontend to Vercel

*   **Status**: Accepted
*   **Date**: 2026-06-28
*   **Deciders**: Patryk O
*   **Supersedes**: [ADR 0006](0006-ci-cd-and-ansible-deployment.md) (frontend deployment section only)
*   **Context Docs**: [Infrastructure](../../operations/infrastructure.md), [Caching Strategy](0008-nextjs-caching-strategy.md)

## Context

The original deployment topology ran the Next.js frontend as a Docker container on the same VPS
as Sulu, exposed via nginx at `127.0.0.1:3000`. This works but carries costs:

- The frontend Node.js process consumes ~300–500 MB RAM on the VPS, competing with PHP-FPM and MySQL.
- Building and pushing a frontend Docker image in CI adds build time and GHCR storage.
- Scaling the frontend independently of the backend is not possible.
- No CDN — all frontend traffic hits the VPS directly.

The headless architecture (Sulu as JSON API, Next.js as presentation layer) maps naturally to
a split deployment where the frontend lives at the edge and the backend stays on the VPS.

## Decision

Deploy the **Next.js frontend to Vercel**. The Sulu backend remains on the VPS.

### What changes

| Concern | Before | After |
|---------|--------|-------|
| Frontend host | Docker on VPS (`127.0.0.1:3000`) | Vercel (edge network) |
| Frontend build | Docker image → GHCR → VPS | Vercel CLI in GitHub Actions |
| `yourdomain.com` DNS | nginx on VPS → `127.0.0.1:3000` | Vercel (DNS points to Vercel) |
| `SULU_BASE_URL` | `http://backend:8080` (Docker internal) | `https://api.yourdomain.com` (public) |
| Sulu public exposure | Admin subdomain only | Admin + API subdomain |

### API subdomain

A dedicated `api.yourdomain.com` nginx vhost is added, proxying to the same Sulu backend
(`127.0.0.1:8000`) as the admin. This keeps the admin and headless delivery concerns separate
in nginx config (different `client_max_body_size`, rate limiting, etc.) while sharing one process.

`SULU_BASE_URL=https://api.yourdomain.com` is set as a Vercel environment variable — not in
the workflow file, so it can differ per Vercel environment (preview vs production) without
touching the repository.

### CI/CD changes

- `cd.yml`: frontend Docker build/push step removed; `deploy-frontend` job added using the
  Vercel CLI (`vercel deploy --prod`). Backend deploy job unchanged.
- `deploy-backend` and `deploy-frontend` run in parallel after CI passes — neither blocks the other.
- Three new GitHub Secrets required: `VERCEL_TOKEN`, `VERCEL_ORG_ID`, `VERCEL_PROJECT_ID`.

### VPS changes

- `docker-compose.prod.yml`: `frontend` service removed.
- nginx: `yourdomain.com` frontend vhost removed; `api.yourdomain.com` vhost added.
- Ansible `all.yml`: `api_domain` variable added.

## Consequences

### Positive

- ~400 MB RAM freed on the VPS — allows a smaller Hetzner tier (CX22 instead of CX32).
- Frontend served from Vercel's global edge CDN — lower latency for readers worldwide.
- Zero-downtime frontend deploys; rollback is instant via Vercel dashboard.
- Next.js features (ISR, Edge Runtime, Image Optimization) are natively supported.
- Frontend and backend deploy independently — a frontend-only change does not touch the VPS.

### Negative / Risks

- **Sulu must be publicly reachable** for content delivery. The headless API is now on the
  public internet (`api.yourdomain.com`). Ensure nginx and UFW allow 80/443 inbound.
- **`SULU_BASE_URL` is a public URL**: latency between Vercel edge and the VPS adds ~10–50 ms
  per RSC request depending on geography. The 60 s Data Cache (ADR 0008) makes this negligible
  under normal traffic.
- **Vercel free tier limits**: 100 GB bandwidth/month, 6000 build minutes/month. Sufficient for
  a showcase; revisit if traffic grows.
- One-time setup required: `vercel link`, three GitHub Secrets, DNS change, Vercel env vars.

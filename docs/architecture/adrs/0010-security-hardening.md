# ADR-0010: Security Hardening

- **Status**: Accepted
- **Date**: 2026-06-29
- **Deciders**: Patryk O

## Context

A full security audit was performed using semgrep (OWASP/PHP/TypeScript rulesets), trivy (dependency CVEs + secret scanning), and OWASP ZAP (dynamic baseline scan against the running backend). The audit identified several issues requiring architectural decisions — not just one-line fixes.

Key findings driving this ADR:

1. `backend/.env` was committed to git with a real `APP_SECRET` and default database credentials.
2. Three `dangerouslySetInnerHTML` sites rendered raw Sulu CMS HTML with no sanitization.
3. The `/api/articles` Next.js proxy forwarded all query parameters verbatim to the upstream Sulu API.
4. No HTTP security headers were set on either the Next.js frontend or the nginx-proxied backend.
5. The Symfony profiler (`/_profiler`) was publicly accessible in production, exposing full request data, SQL queries, logs, and internal IP addresses.
6. The taxonomy filter in `ArticlesByTaxonomyController` loaded all matching articles into memory without a result cap.

## Decisions

### 1. Secrets out of version control

`backend/.env` was purged from the entire git history using `git filter-branch`. `/.env` was added to `backend/.gitignore`. Going forward, `.env` is never committed; only `.env.example` (no real values) may be tracked. The `APP_SECRET` was rotated as part of the remediation.

### 2. HTML sanitization for CMS-sourced content (defense in depth)

`dangerouslySetInnerHTML` is used in three places to render rich-text HTML authored in Sulu. While Sulu's editor limits what authors can input, defense in depth requires sanitization on the rendering side too — a compromised admin account or backend vulnerability would otherwise result in stored XSS for all visitors.

`sanitize-html` was chosen over DOMPurify because it runs on the server (Next.js Server Components) without requiring a DOM. A shared `lib/sanitize.ts` utility defines an allowlist of safe tags, attributes, and URL schemes, and forces `rel="noopener noreferrer"` on all links.

**Affected files**: `components/content/block-renderer.tsx`, `components/content/page-view.tsx`.

### 3. API proxy parameter allowlist

`app/api/articles/route.ts` previously forwarded `req.nextUrl.searchParams.toString()` directly to the Sulu API, allowing clients to inject arbitrary query parameters. An explicit allowlist (`page`, `limit`, `category`, `tag`) is now constructed before the upstream call.

### 4. HTTP security headers

**Next.js frontend** (Vercel): headers added via `next.config.ts` `headers()` function, applied to all routes:
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Strict-Transport-Security: max-age=63072000; includeSubDomains; preload`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- `Content-Security-Policy` (baseline; `unsafe-inline` required by Next.js hydration — upgrade to nonces if stricter CSP is needed). `script-src` additionally allows `unsafe-eval` in `development` only (Next.js dev/HMR requires it); production is unaffected.

**nginx backend** (`ansible/roles/nginx/templates/app.conf.j2`):
- API vhost (`api.*`): `nosniff`, Referrer-Policy, Permissions-Policy, `Cross-Origin-Resource-Policy: same-origin`, and a strict `CSP: default-src 'none'` (pure JSON API).
- Admin vhost (`admin.*`): same set except `X-Frame-Options: SAMEORIGIN` (the Sulu admin UI uses iframes internally).

The `Host` header was also changed from `$host` (client-controlled) to `$server_name` (configured value) in both proxy blocks.

### 5. Symfony profiler disabled in production

ZAP found the Symfony profiler publicly accessible at `/_profiler`, spidered 240 URLs, and collected full request payloads, SQL queries, serializer output, logs, and internal Docker network IPs. The `security.yaml` firewall intentionally excludes `/_profiler` from authentication, so the fix is to disable the profiler at the framework level rather than relying on access control.

Added to `config/packages/framework.yaml`:

```yaml
when@prod:
    framework:
        profiler:
            enabled: false
            collect: false
```

This has no effect on the `dev` environment, where the profiler remains available.

### 6. Security audits in CI

All three audit tools are wired into `.github/workflows/ci.yml` and run on every push and pull request:

- `composer audit` — added as the final step of the **Backend** job; fails on any known PHP CVE.
- `npm audit --audit-level=high` — added as the final step of the **Frontend** job; high threshold intentionally excludes the accepted PostCSS moderate risk.
- **Security scan** job (parallel to Backend and Frontend): Trivy filesystem scan (HIGH/CRITICAL, secrets) and Semgrep (OWASP Top 10 / PHP / TypeScript). Trivy is installed via its official install script rather than a pinned action to avoid version-tag resolution issues.

### 7. Result cap on taxonomy filter

`ArticlesByTaxonomyController::taxonomyFilter()` fetched all published articles before filtering in PHP. A `setMaxResults(200)` guard was added to bound memory usage under adversarial or high-volume conditions.

## Alternatives Considered

**Trust Sulu's sanitization entirely** — rejected for `dangerouslySetInnerHTML`. Sulu does sanitize editor output, but defense in depth at the render layer costs little and protects against backend compromise.

**Nonce-based CSP** — not implemented yet. Requires Next.js middleware to inject a per-request nonce. The current `unsafe-inline` CSP is a starting point; nonces are the right next step if a stricter CSP is required.

**NelmioSecurityBundle for backend headers** — rejected in favor of nginx-level headers. Nginx is already the TLS termination and reverse proxy point; adding headers there covers all responses including static assets, without coupling security policy to the PHP layer.

## Consequences

### Positive

- The old `APP_SECRET` is no longer in git history; sessions and CSRF tokens signed with it are invalid after rotation.
- Stored XSS via CMS is blocked by the sanitizer allowlist even if the Sulu admin is compromised.
- The profiler can no longer be used to harvest SQL queries, request bodies, or logs from production.
- Security headers satisfy OWASP baseline recommendations for both the API and frontend layers.
- The articles proxy can no longer be used to probe internal Sulu API parameters.

### Negative

- `sanitize-html` adds a dependency and a small per-render CPU cost (negligible for SSR at this scale, but worth noting).
- The `unsafe-inline` CSP on the frontend does not protect against inline XSS injected at the CDN or infrastructure layer; nonces are needed for that guarantee.
- `setMaxResults(200)` on the taxonomy filter is an arbitrary cap; if the dataset grows past 200 articles in a category, results will be silently truncated. A proper paginated response should replace this endpoint if that threshold is approached.

### Known accepted risk — PostCSS CVE (GHSA-qx2v-qp2m-jg93)

`npm audit` reports `postcss <8.5.10` (moderate) via `node_modules/next/node_modules/postcss` — a copy bundled inside Next.js 16.2.6 itself, not a direct dependency. The CVE describes XSS via unescaped `</style>` in CSS stringify output.

**Exploitability is low in this project**: the affected postcss instance processes Tailwind CSS at build time only. It never processes attacker-controlled input at runtime. Forcing the version via `npm overrides` risks breaking Next.js internals.

**Resolution**: monitor Next.js patch releases for an update that bumps its bundled postcss to ≥8.5.10 and upgrade when available. Do not run `npm audit fix --force` — it would downgrade Next.js to 9.3.3.

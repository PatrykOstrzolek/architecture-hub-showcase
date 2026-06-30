# ADR 0008: Next.js Caching Strategy — Time-Based Revalidation

*   **Status**: Accepted
*   **Date**: 2026-06-28
*   **Deciders**: Patryk O
*   **Context Docs**: [Headless Content Delivery](0003-headless-content-delivery.md), [Infrastructure](../../operations/infrastructure.md)

## Context

The Next.js frontend fetches all content from the Sulu backend at request time (RSC). Without
caching, every page load would hit the Sulu PHP backend — adding latency and putting unnecessary
load on the VPS. Sulu has no native mechanism to push cache invalidation events, and the
deployment topology keeps both services on the same VPS behind nginx.

### Next.js caching layers relevant here

Next.js (App Router) has four caching layers:

1.  **Request Memoization** — deduplicates identical `fetch()` calls within a single render. Free,
    automatic, no configuration needed.
2.  **Data Cache** — persists `fetch()` responses across requests. Controlled by
    `next: { revalidate }` per call.
3.  **Full Route Cache** — caches rendered HTML + RSC payload at build time for static routes.
4.  **Router Cache** — client-side cache of RSC payloads in the browser session.

The key knob for this project is the **Data Cache** `revalidate` interval.

### Options considered

| Strategy | Freshness | Complexity | Fit |
|----------|-----------|------------|-----|
| `cache: 'no-store'` | Always fresh | Zero | Defeats caching; all requests hit Sulu |
| `revalidate: N` (time-based) | Up to N seconds stale | Zero | Simple, safe, no wiring |
| On-demand revalidation via webhook | Near-instant after publish | Webhook + secret setup | Practical now that frontend is on Vercel |

### Webhook design

On-demand revalidation requires a publicly reachable Next.js endpoint that Sulu calls on every
publish/unpublish event. Hazards and mitigations:

| Hazard | Mitigation |
|--------|------------|
| Unauthenticated abuse | `Authorization: Bearer <secret>` header; constant-time comparison; 401 on mismatch |
| Path injection | Tag-based invalidation (`revalidateTag('content')`) — no path from payload |
| Silent delivery failure | 60 s time-based TTL remains as safety-net fallback |
| Thundering-herd | Sulu publishes one document at a time; not a concern in practice |

With the frontend on Vercel (ADR 0009), the `POST /api/revalidate` endpoint is publicly reachable
and `NEXT_REVALIDATE_URL` / `REVALIDATE_SECRET` can be managed alongside the existing Vercel
and GitHub secrets already in use.

## Decision

Use **on-demand revalidation** as the primary mechanism, with **time-based revalidation (60 s)**
as a fallback TTL. All cached `suluFetch()` calls are tagged `'content'`; a single
`revalidateTag('content')` call purges the entire Data Cache on demand.

Live search (`/api/search`) is excluded from caching entirely — it is user-driven and must always
return current results.

```
REVALIDATE_SECONDS = 60   (fallback TTL defined in frontend/lib/sulu.ts)
NEXT_REVALIDATE_URL      (backend env var — public Vercel URL of the frontend)
REVALIDATE_SECRET        (shared Bearer token — same name in Vercel, GitHub Secrets, and backend .env)
```

### Cached endpoints

| Function | Endpoint | Revalidate | Tags |
|----------|----------|------------|------|
| `getContent()` | `{path}.json` | 60 s | `content` |
| `getNavigation()` | `/api/navigations/{context}` | 60 s | `content` |
| `getArticles()` | `/api/articles?page=&limit=` | 60 s | `content` |
| `searchByTaxonomy()` | `/api/articles?category=` / `?tag=` | 60 s | `content` |
| `search()` | `/api/search?q=` | 0 (no cache) | — |

### How invalidation is triggered

Two complementary triggers both POST to `{NEXT_REVALIDATE_URL}/api/revalidate`:

1. **Sulu publish event (Phase 2)** — `NextjsCacheInvalidationSubscriber` subscribes to
   `PageWorkflowTransitionAppliedEvent` and `ArticleWorkflowTransitionAppliedEvent`. Fires
   synchronously; bounded by `connect_timeout`, `timeout`, and `max_duration` (all 5 s).
   Failures are logged and do not abort the publish action. The 60 s TTL acts as a silent
   fallback if the call fails.

2. **Backend CI/CD deploy (Phase 1)** — the `deploy-backend` GitHub Actions job fires the same
   endpoint via `curl` after Ansible completes. Ensures the cache is fresh after any backend
   code or config change, even outside of editorial publish actions. Marked
   `continue-on-error: true` so a revalidation failure does not block the deploy.

### Required secrets / variables

| Store | Key | Value |
|-------|-----|-------|
| Vercel env var | `REVALIDATE_SECRET` | Shared Bearer token |
| GitHub Secret | `REVALIDATE_SECRET` | Same token |
| GitHub Variable | `NEXT_PUBLIC_URL` | e.g. `https://arch-hub.vercel.app` |
| Backend `.env` / Ansible vault | `NEXT_REVALIDATE_URL` | Same as above |
| Backend `.env` / Ansible vault | `REVALIDATE_SECRET` | Same token |

## Communication contract (Sulu → Next.js)

Next.js calls Sulu server-side via `SULU_BASE_URL` (public `api.yourdomain.com`). Sulu calls
Next.js via `NEXT_REVALIDATE_URL` on publish/unpublish. The shared secret travels only in
`Authorization` headers; it is never embedded in URLs or response bodies.

## Consequences

### Positive

*   Content published in Sulu is live on the frontend within seconds, not up to 60 s.
*   Tag-based invalidation purges all affected endpoints in one call with no path enumeration.
*   The 60 s TTL fallback makes the system self-healing — a missed webhook does not cause
    permanent staleness.
*   No new infrastructure required beyond secrets already managed in the project.

### Negative / Risks

*   The Sulu publish action now makes an outbound HTTP call. Latency is bounded by the 5 s
    `max_duration` hard cap; a slow or unreachable Vercel endpoint adds at most 5 s to a publish.
*   If `REVALIDATE_SECRET` is rotated, it must be updated in three places (Vercel, GitHub Secrets,
    Ansible vault) simultaneously or the webhook will return 401 until all are in sync.
*   `symfony/http-client` is now a direct backend dependency (was previously only transitive).

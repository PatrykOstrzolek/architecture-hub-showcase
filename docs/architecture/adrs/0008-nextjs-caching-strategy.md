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
| On-demand revalidation via Sulu webhook | Near-instant after publish | Webhook + secret setup | Overkill for showcase |

### Webhook hazards (why it was not chosen)

On-demand revalidation requires a publicly reachable Next.js endpoint that Sulu calls on every
publish event. Hazards include: unauthenticated endpoint abuse, path injection if the payload
path is passed to `revalidatePath()` directly, silent delivery failure if Next.js is unreachable,
and thundering-herd on bulk publishes. Mitigations exist (shared secret, tag-based revalidation,
fallback TTL) but add operational complexity not justified for a showcase.

## Decision

Use **time-based revalidation with a 60-second window** for all content and listing fetches.
Live search (`/api/search`) is excluded — it is user-driven and must always return current results.

```
REVALIDATE_SECONDS = 60   (defined in frontend/lib/sulu.ts)
```

Applied via the `suluFetch` helper's default `revalidate` parameter:

| Function | Endpoint | Revalidate |
|----------|----------|------------|
| `getContent()` | `{path}.json` | 60 s |
| `getNavigation()` | `/api/navigations/{context}` | 60 s |
| `getArticles()` | `/api/articles?page=&limit=` | 60 s |
| `searchByTaxonomy()` | `/api/articles?category=` / `?tag=` | 60 s |
| `search()` | `/api/search?q=` | 0 (no cache) |

## Communication contract (Sulu → Next.js)

In the current single-VPS topology, Next.js calls Sulu **server-side only**, via the internal
`SULU_BASE_URL` (`http://127.0.0.1:8000`). Sulu does not call Next.js. There is no webhook,
no push mechanism, and no WebSocket between the two services.

If the topology changes (e.g. Next.js deployed to Vercel), the revalidation strategy should be
revisited — on-demand revalidation via a secret-authenticated webhook becomes practical and the
time-based fallback should remain as a safety net.

## Consequences

### Positive

*   Zero infrastructure change — no webhook endpoint, no Sulu configuration.
*   Content staleness is bounded and predictable (≤ 60 s).
*   Reduces Sulu backend load significantly under concurrent traffic.

### Negative / Risks

*   An editor publishing a change will not see it live for up to 60 seconds. Acceptable for a
    showcase; would need on-demand revalidation for a time-sensitive production site.
*   If `REVALIDATE_SECONDS` is changed, the behaviour of all cached endpoints changes together.
    Per-endpoint tuning is possible by passing an explicit second argument to `suluFetch()`.

# ADR 0013: Next.js Draft Mode for the Real-Frontend Preview

*   **Status**: Accepted
*   **Date**: 2026-07-01
*   **Deciders**: Patryk O

## Context

The Sulu admin's inline content preview renders the backend's fallback Twig
view (`backend/templates/pages/*.html.twig`), not the actual Next.js
frontend — those Twig views exist only to satisfy Sulu's own
`ContentController::renderSuluView()`, which throws a `NotAcceptableHttpException`
("Page does not exist in 'html' format") if no view exists for the requested
format. They were never meant to represent the real design.

We want editors to be able to see a page rendered by the real frontend before
publishing.

## Decision

Use Next.js **Draft Mode**, reading draft-stage content from a new,
secret-protected backend endpoint — not a custom Sulu `PreviewRendererInterface`.

*   **Backend**: `GET /admin/api/preview/pages?path=...&locale=...`
    (`App\Controller\Website\PagePreviewController`), guarded by a
    `PREVIEW_SECRET` bearer token. It resolves the path to a page via
    `RouteRepositoryInterface`, loads the page via `PageRepositoryInterface`,
    then resolves its **draft**-stage `DimensionContentInterface` via
    `ContentManagerInterface::resolve()` (`stage: DimensionContentInterface::STAGE_DRAFT`)
    and serializes it with the same `sulu_headless.structure_resolver` service
    `HeadlessWebsiteController` uses for the public `.json` route — so draft
    and live responses are shape-identical. Lives under `/admin/api/...`, not
    `/api/...` — see "Production incident" below for why.
*   **Frontend**: `GET /api/preview?secret=...&path=...` enables Draft Mode
    (`draftMode().enable()`) and redirects to `path`. `GET /api/preview/disable`
    turns it back off. `lib/sulu.ts`'s `getContent()` checks
    `draftMode().isEnabled` and, when true, calls the draft endpoint instead of
    the public `{path}.json` route, with `cache: "no-store"`. A
    `DraftModeBanner` server component shows an "Exit preview" link site-wide
    while active.
*   **One-click trigger, no JS build required**: Sulu's admin toolbar already
    has a working, already-registered "Share > Generate link" action for
    pages — it's a *separate* feature from the inline live iframe, backed by
    `PreviewLinkController`/`PreviewLinkRepositoryInterface` (a DB-persisted
    `PreviewLink` token, unrelated to the session-based `Preview` service the
    iframe and the toolbar's "open in new tab" button use). Its public URL,
    `GET /admin/p/{token}` (route `sulu_preview.public_preview`), normally
    wraps Sulu's Twig renderer. `App\Controller\Website\PreviewLinkRedirectController`
    re-registers that exact route name — a standard Symfony technique for
    overriding bundle routing — and for page resources, redirects straight
    into `/api/preview` instead. Anything else (a non-page resource type)
    falls back to Sulu's own `PublicPreviewController`, injected and called
    directly, so its behavior for non-pages is completely unchanged.

This reuses the exact page components, layouts, and rendering path real
visitors get — no HTML is faked or duplicated — at the cost of the preview
only reflecting the last **Save**, not every keystroke.

## Consequences

### Positive

*   Editors see the actual production UI, not an approximation.
*   No duplicated rendering logic — the same `page.tsx` catch-all route and
    the same `SuluContent` shape serve both live and draft content.
*   `PagePreviewController` is generic over any page template (not
    exercise-specific) — the same mechanism that fixed the earlier missing-Twig-view
    bug for `learning-paths`/`authors` also gives every current and future page
    template a real-frontend preview for free.
*   Editors get a real one-click "open in the real frontend" action today, via
    the existing Share > Generate link button — no `npm`/webpack step needed.

### Production incident: `/api/preview/pages` 500'd, worked fine locally

First deploy of `PagePreviewController` used `/api/preview/pages`. It worked
in every local test (curl, browser, full end-to-end) and passed CI, but
500'd in production on every request, before the controller's own logic ever
ran (same 500 regardless of headers/query params). Root cause, found by
reading `public/index.php`:

1.  Sulu context is decided purely by request path —
    `preg_match('/^\/admin(\/|$)/', REQUEST_URI)` picks `admin` context,
    anything else gets `website` context. **Not** by hostname, despite
    `admin_domain`/`api_domain` being two separate Ansible-templated nginx
    vhosts proxying to the same upstream.
2.  For `website` context, `public/index.php` wraps the kernel in
    `$kernel->getHttpCache()` (`SuluHttpCache`, FOSHttpCacheBundle) whenever
    `APP_ENV !== 'dev'`. Local dev never hits this — `APP_ENV=dev` skips the
    wrap unconditionally — so the bug was invisible in every local test no
    matter how thorough.
3.  `/api/preview/pages` isn't a webspace/portal-matched Sulu content route
    (unlike `.json`, which goes through `HeadlessWebsiteController`) — it's a
    plain `#[Route]` controller. Something in that combination (custom route
    + website context + the reverse-proxy cache kernel wrapper) throws.
    Without production log/SSH access this session, the *exact* line wasn't
    isolated — but the fix sidesteps needing to know it.

**Fix**: moved the route to `/admin/api/preview/pages`. Any `/admin`-prefixed
path always gets `admin` context, which is never wrapped in `SuluHttpCache` —
matches why `PreviewLinkRedirectController` (`/admin/p/{token}`) worked
correctly from the start, and mirrors Sulu's own convention (its
preview-link REST API already lives at `/admin/api/preview-links/*` for
what's presumably the same reason). This required two more changes to
actually reach the controller:
*   `security.yaml`'s `access_control` requires `ROLE_USER` for `^/admin` by
    default — added a `PUBLIC_ACCESS` carve-out for
    `^/admin/api/preview/`, the same pattern already used for `^/admin/p/`.
    The controller still guards itself with the bearer secret; this just lets
    that check run instead of the request dying at the firewall first.
*   The `api_domain` nginx vhost's allowlist (`\.json$|^/(api|media)`) didn't
    include `/admin/*` at all (by design — `admin_domain` is a separate
    vhost). Added a narrow `^/admin/api/preview/` carve-out to `api_domain`
    specifically, rather than routing this one call through `admin_domain`
    instead, so the frontend keeps using a single `SULU_BASE_URL`.

**Lesson**: `APP_ENV=dev` disables a whole kernel-wrapping layer that's
always active in prod. Local testing — however thorough — cannot exercise
that layer at all; only a real deploy (or intentionally reproducing
`APP_ENV=prod` locally) can.

### Negative / Risks

*   **Not live-as-you-type.** Sulu's own inline admin iframe re-renders on
    every keystroke via an in-process `PreviewKernel`; Next.js has no access to
    that unsaved in-memory state, so this only reflects the last Save. Judged
    an acceptable trade for getting the real design instead of the Twig
    fallback.
*   **Still not live-as-you-type even via the one-click link** — same
    limitation as above; "Generate link" opens the last-saved draft, not
    unsaved keystrokes.
*   **Route-name-override coupling.** `PreviewLinkRedirectController`
    re-registers `sulu_preview.public_preview` by name — an internal Sulu
    route (the controllers it touches, `PublicPreviewController` and
    `PreviewController`, are explicitly marked `@internal No BC promises`).
    If a Sulu upgrade renames or restructures this route, the override
    silently stops taking effect (traffic reverts to Sulu's own controller,
    which just means the Twig fallback returns — not a hard failure, but
    worth re-checking after any Sulu upgrade via
    `bin/console debug:router sulu_preview.public_preview`).
*   **Split DI containers.** Sulu compiles separate `website` and `admin`
    service containers (`RemoveForeignContextServicesPass`, filtering on a
    `sulu.context` tag) — `sulu_preview.public_preview_controller` only
    exists in the `admin` container, so `PreviewLinkRedirectController` has
    to carry the same `sulu.context: admin` tag itself, or the container
    fails to compile. Easy to miss when adding future controllers that
    reference other Sulu-internal admin services.
*   A custom Sulu Admin JS toolbar action (`backend/assets/admin/app.js`,
    needing `npm install` + a webpack build this session couldn't run) is
    still the only way to add a *new* kind of button — but turned out to be
    unnecessary for this particular feature once the existing "Generate
    link" action was found and its target route overridden instead.
*   **New shared secret.** `PREVIEW_SECRET` grants read access to
    *unpublished* content, so it's a distinct credential from
    `REVALIDATE_SECRET` (that one only lets the backend trigger a frontend
    cache flush — no data exposure either direction). Wired through
    `backend/.env`, `frontend/.env.example`, `ansible/group_vars/vault.yml.example`,
    `ansible/roles/app/templates/.env.j2`, and `docker-compose.prod.yml`,
    mirroring the existing `REVALIDATE_SECRET` plumbing. The real value still
    needs to be set in the Ansible vault and the Vercel project's environment
    variables — not something this change can do on its own.
*   **Pages only.** `PagePreviewController` resolves `PageInterface::RESOURCE_KEY`
    routes; articles aren't covered. Extendable later by branching on
    `Route::getResourceKey()` if needed — not built now since nothing
    currently asks for an article draft preview.

## Alternatives Considered

1.  **Implement a custom `PreviewRendererInterface`** to make Sulu's existing
    inline admin iframe itself proxy to Next.js. Rejected: that interface is
    explicitly `@internal` (no BC promise), and Sulu's live-preview flow
    fundamentally depends on re-rendering an unsaved in-memory draft via a
    `PreviewKernel` sub-request — something Next.js, fetching over real HTTP
    from the database, has no way to observe. Would have meant either losing
    live-as-you-type anyway or building a much larger bridge (streaming
    unsaved form state to Next.js) for the same end result Draft Mode gives
    more simply.
2.  **Fully reimplement Sulu's shareable "Preview Link" feature** (its own
    token generation/storage/UI) from scratch instead of overriding its
    existing route. Rejected: `PreviewLinkController`, `PreviewLinkManager`,
    and the "Share > Generate link" admin button already work correctly —
    token creation, persistence, visit counting, revocation. Only the
    *rendering* step (`PublicPreviewController::previewAction`, which is
    Twig-only) needed to change for pages, so overriding just that one route
    keeps everything else (including the admin UI) untouched and still
    correct for non-page resources.

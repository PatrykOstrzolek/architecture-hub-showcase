# ADR 0005: Use Sulu Navigation API for Site Header

*   **Status**: Accepted
*   **Date**: 2026-06-27
*   **Deciders**: Patryk O

## Context

The site header originally used a hardcoded `navItems` array in `site-header.tsx`. This meant
any change to the top-level navigation (adding a section, renaming a page) required a frontend
code change and redeployment rather than a content manager updating it in the Sulu admin.

The `sulu/headless-bundle` exposes a navigation delivery endpoint:

```
GET /api/navigations/{context}?depth=1
```

Pages appear in a navigation context when a content manager assigns them to it in the Sulu admin
(Settings → Navigation for each page), or when the fixture sets `navigationContexts: ['main']` in
the `CreatePageMessage`/`ModifyPageMessage` data.

The `architecture-hub` webspace already declares a `main` navigation context in
`config/webspaces/architecture-hub.xml`.

## Decision

`SiteHeader` is an **async React Server Component** that calls `getNavigation("main")` at
render time. If the API returns items they are used directly; if it returns an empty list or
the backend is unavailable the component falls back to the static `FALLBACK_NAV` array, so
the header is always visible.

Fixtures (`AuthorPageFixture`, `LearningPathFixture`) set `navigationContexts: ['main']` on
the `/authors` and `/learning-paths` listing pages. `AuthorPageFixture` also inserts the
homepage (`/`) into the navigation context table via DBAL when it does not already have an
entry there (the homepage is created by `sulu:build dev`, not by a fixture).

## Consequences

*   **Content managers can reorder or extend the nav** without touching frontend code.
*   **ISR-cached** at the same 60-second window as other content fetches — changes propagate
    within one revalidation cycle.
*   **Fallback** guarantees the header never goes blank if the backend is slow or down during
    a cold start.
*   Pages must be explicitly assigned to the `main` context in Sulu admin (or via fixtures)
    to appear; this is intentional — not every published page should be a top-level nav item.

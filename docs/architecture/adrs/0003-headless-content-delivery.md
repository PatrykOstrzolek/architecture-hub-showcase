# ADR 0003: Headless Content Delivery via the Official SuluHeadlessBundle

*   **Status**: Accepted
*   **Date**: 2026-06-25
*   **Deciders**: Patryk O
*   **Context Docs**: [Product Spec](../../product/product-spec.md), [Architectural Principles](../principles.md)

## Context

The platform is a **headless** knowledge site: Sulu CMS is the source of truth for content
(articles, authors, taxonomy), and a separate Next.js (App Router, React Server Components)
application renders the public experience. Sulu is not API-first out of the box — its default
delivery path renders Twig templates server-side. We therefore need a **content delivery layer**
that exposes resolved, locale-aware content (including the category/tag taxonomy required by the
MVP) as JSON the Next.js frontend can consume.

Two relevant principles constrain the choice:

*   **Built-in Capabilities First** — leverage built-in Sulu capabilities before custom solutions.
*   **Minimal Customization** — avoid custom APIs unless explicitly required by unique business needs.

### Backend baseline (verified)

*   `sulu/sulu ~3.0.7`, PHP 8.5 (constraint `^8.2`), Symfony `^7.4`.
*   New Sulu 3.0 content architecture installed: `SuluContentBundle`, `SuluArticleBundle`,
    `SuluPageBundle`, `SuluSnippetBundle`, plus `SuluCategoryBundle` and `SuluTagBundle`.
*   `cmsig/seal` search is available for full-text and faceting.

## Decision

Adopt the **official `sulu/SuluHeadlessBundle` (>= 3.0)** as the headless content delivery layer.
Do **not** hand-roll custom delivery controllers or serializers.

### Why this fits

*   It is a **first-party Sulu bundle**, so it satisfies "Built-in Capabilities First" and means
    the headless delivery API is not custom code we own and maintain.
*   Compatibility is exact: it requires `php ^8.2`, `sulu/sulu ^3.0.6`,
    `symfony/* ^6.4 || ^7.0` — all satisfied by our backend.
*   It targets the **new Sulu 3.0 content/article packages**, not the legacy 2.x stack
    (e.g. `ArticleSelectionResolver` depends on `Sulu\Article\Domain\Repository\ArticleRepositoryInterface`
    and `Sulu\Content\Application\ContentAggregator`).

### How content (incl. taxonomy) is resolved

*   **Pages / Articles → JSON**: setting a template's `<controller>` to
    `Sulu\Bundle\HeadlessBundle\Controller\HeadlessWebsiteController::indexAction` makes that
    content available as a JSON object when requested with the `.json` format suffix on the
    content's URL (e.g. `/.json`, `/articles/cap-theorem.json`). At the plain URL the same
    controller still renders the Twig HTML view; only the `.json` suffix yields JSON. The payload
    includes `content`, `view`, `template`, author/changer/creator timestamps, `localizations`,
    and an `extension` bag carrying `excerpt` and `seo`.
*   **Taxonomy (excerpt tab)**: a page/article's own taxonomy is exposed under
    `extension.excerpt.categories` and `extension.excerpt.tags`. Per the installed
    `StructureResolver` (taxonomy path: `getExcerptCategoryIds()` / `getExcerptTagNames()`),
    these resolve to **bare identifiers — category IDs (`number[]`) and tag name strings
    (`string[]`)**, *not* resolved objects. Rendering category names/links therefore requires
    resolving those IDs separately. (Verified empirically — see below.)
*   **Selections in body content**: dedicated `ContentTypeResolver`s flatten content types to
    JSON (`category_selection`, `tag`, `single/article_selection`, media, snippets, blocks,
    smart content, etc.). Note the contrast: the **`category_selection` content type** *does*
    return fully resolved category objects (`partialCategory` group: id, key, locale-resolved
    name) — a different path from the excerpt taxonomy above. See
    [Content Types Reference](../content-types-reference.md) for every shape.
*   **Discovery APIs** (portal context, e.g. `/api/...`):
    `navigations/{contextKey}`, `search?q=`, `snippet-areas/{area}`, `analytics`.

### Verified delivery shape (2026-06-26)

Confirmed empirically against the running instance (webspace `architecture-hub`):

*   The webspace maps its single default locale `en` to the host root
    (`<url language="en">{host}</url>`), so **the locale is not present in delivery URLs**.
    Content is at `{path}.json` (homepage → `/.json`) and discovery APIs at `/api/...`
    (an `/en/...`-prefixed variant returns 404). Multi-locale webspaces that map locales to a
    path prefix would reintroduce that prefix.
*   `excerpt` and `seo` are nested under a top-level **`extension`** object — not top-level keys.
*   The excerpt object exposes `categories` and `tags` alongside `icon`, `image`,
    `audience_targeting_groups`, and `segments`. After authoring a category + tag on the
    homepage and publishing, `/.json` returned **`"categories": [1]`** (category **ID**) and
    **`"tags": ["cap-theorem"]`** (tag **name string**) — confirming the excerpt taxonomy is
    bare identifiers, not resolved objects. (All are `[]`/empty until content is authored.)
*   Search hits (`/api/search?q=`) carry `resourceKey`, `resourceId`, `locale`, `webspaces`,
    `title`, `url`, and `_formatted` highlight fragments under `_embedded.hits`.

The typed client `frontend/lib/sulu.ts` mirrors these verified shapes.

### Frontend contract

The Next.js app fetches in RSC against (for the host-root, single-locale webspace):

*   `{path}.json` — page/article detail (content + `extension.excerpt.categories` / `.tags`).
*   `/api/navigations/{contextKey}` — primary navigation / category-driven menus.
*   `/api/search?q=` — search (can be backed by `cmsig/seal`).

Listing/filtering by category or tag (an MVP acceptance criterion) is served via a SmartContent
data provider on a listing template (rendered through the headless controller) and/or the search
index, rather than ad-hoc database queries.

## Consequences

### Positive

*   No custom delivery API to own; upgrades track Sulu releases.
*   Taxonomy, SEO, excerpt, navigation, and search are available immediately and consistently.
*   Clean separation: Sulu stays the content authority; Next.js owns presentation.

### Negative / Risks

*   The bundle is explicitly "still under development"; future minor versions may introduce
    breaking changes — pin the version and review `UPGRADE.md` on bumps.
*   The JSON shape is defined by the bundle's resolvers. Bespoke payload needs are addressed by
    adding/overriding a `ContentTypeResolver` (the supported extension point), not by forking.
*   Listing/filter ergonomics depend on SmartContent and/or the search index being configured.

## Alternatives Considered

1.  **Custom thin delivery controllers** using `Sulu\Content` `ContentResolver` directly.
    Rejected: duplicates what the official bundle already provides and violates
    "Minimal Customization".
2.  **`handcraftedinthealps/sulu-headless-bundle` (2.x community fork)**.
    Rejected: not aligned with Sulu 3.0; superseded by the official bundle.
3.  **api-platform / GraphQL layer over Doctrine**.
    Rejected: bypasses Sulu's content resolution and dimension/locale handling; large surface
    area for no MVP benefit.

# Feature Spec: Search

## 1. Overview
Provide users with the ability to quickly locate relevant technical articles, authors, categories, and tags based on keywords.

## 2. Goals
*   Enable efficient content discovery.
*   Surface content from titles, body text, and tags.
*   Guide users toward related taxonomy (categories, tags) and authors directly from the search input.

## 3. Scope (MVP)

### 3.1 Full-text search results page
*   Full-text search across all published articles (title + body content).
*   Search results page at `/search?q=` displaying article title, summary excerpt, and link.
*   Basic keyword matching via Loupe (SQLite-backed, no external service required — `SEAL_DSN=loupe://`).
*   "No results found" message when query returns nothing.
*   "Enter a keyword" prompt when no query is present.
*   Also handles `?category={key}&label={name}` and `?tag={name}&label={name}` params — shows filtered article list with a "Category" / "Tag" subheading.

### 3.2 Autocomplete suggestions (inline dropdown)
*   Suggestions appear after the user types ≥ 2 characters, debounced at 300 ms.
*   Two parallel requests per keystroke:
    *   `/api/search?q=` (Next.js proxy → Sulu `SearchController`) — up to 4 article title suggestions.
    *   `/api/taxonomy?q=` (Next.js proxy → `TaxonomyController`) — up to 3 category + 3 tag suggestions.
*   Dropdown is grouped into three labelled sections: **Articles**, **Categories**, **Tags**.
*   Clicking an article suggestion navigates directly to that article's URL.
*   Clicking a category/tag suggestion navigates to `/search?category={key}&label={name}` or `/search?tag={name}&label={name}`.
*   "See all results →" at the bottom navigates to the full `/search?q=` results page.
*   Dropdown closes on outside click or navigation.

### 3.3 Taxonomy-filtered results
*   Backend: `TaxonomyController` (`GET /api/taxonomy?q=`) — Doctrine DQL searching `Category.translations.translation` and `Tag.name` with `LIKE`. Returns `{ categories: [{id, key, name}], tags: [{id, name}] }`.
*   Backend: `ArticlesByTaxonomyController` (`GET /api/articles?category={key}` or `?tag={name}`) — fetches all live articles, filters in PHP against `templateData['categories']`/`['tags']` IDs. Returns `{ _embedded: { hits: [...] } }` in the same shape as SEAL hits.
*   Frontend proxies: `app/api/taxonomy/route.ts` and `app/api/articles/route.ts` forward to the Sulu backend (needed because `SULU_BASE_URL` is server-only).

> **Note on filtering approach**: Categories and tags on articles are stored in the main content template (`category_selection` / `tag_selection` fields in `templateData`), not in the Sulu excerpt taxonomy. This means filtering uses PHP-side array intersection rather than a Doctrine join. Acceptable for the current dataset size; if content scales, migrate to SEAL-indexed category/tag fields or the excerpt taxonomy (which has proper Doctrine relations).

### 3.4 Authors (planned)
*   Author profile pages are indexed in the SEAL `website` index. Surfacing them in the autocomplete dropdown (filtered by `resourceKey = 'pages'`) requires differentiating them from article hits — a `resourceKey` check in `SearchForm` is sufficient once verified against live data.

## 4. Acceptance Criteria
*   User can enter a keyword into a search field.
*   System returns a list of matching articles on the full results page.
*   Search results display the article title and a short excerpt.
*   Searching with no results displays a "No results found" message.
*   As the user types (≥ 2 chars), an autocomplete dropdown appears grouped into Articles, Categories, and Tags sections.
*   Clicking an article suggestion navigates directly to that article.
*   Clicking a category or tag suggestion navigates to a filtered results page showing all articles with that taxonomy.
*   "See all results →" navigates to the full `/search` results page.
*   *(Planned)* Author profile pages appear as a fourth section in the autocomplete dropdown.

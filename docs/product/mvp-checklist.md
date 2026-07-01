# MVP Acceptance Checklist

Cross-reference of every acceptance criterion from `product-spec.md` and the feature specs.
Update this file whenever a criterion is met or a new one is added.

**Status: MVP complete (2026-07-01).** All `product-spec.md` §4 scope items and §7 acceptance
criteria are checked off below, and every entry in the Open Gaps table is resolved. Interactive
Exercises (`features/exercises.md`) was pulled forward from §5 Future Enhancements and is also
done. Remaining Future Enhancements (Personalization, Community Contributions, Advanced
Visualization) are explicitly out of MVP scope.

Legend: ✅ done · ❌ not started · ⚠️ partial / planned

---

## Articles (`features/articles.md`)

- [x] Content managers can create, update, and publish articles in Sulu
- [x] Articles displayed with clear typography, including support for technical diagrams (`image` block type)
- [x] Articles correctly display the assigned author, category, and tags
- [x] Frontend retrieves and renders article content via the headless API

## Authors (`features/authors.md`)

- [x] Content managers can create, update, and publish author profiles in Sulu (`author` template)
- [x] Article pages display the author's name and link to their profile page
- [x] Author profile page displays name, bio, image, and list of published articles
- [x] Frontend retrieves author data via the headless API (`/authors/{slug}.json`)
- [x] Author pages for Adam Ministrator and Jane Kowalski published via `AuthorPageFixture` (`/authors/adam-ministrator`, `/authors/jane-kowalski`)
- [x] `/authors` listing page — `authors` template with `page_selection` field; fixture auto-populates it

## Learning Paths (`features/learning-paths.md`)

- [x] User can view a list of available learning paths (`/learning-paths` — `learning-paths` template with `page_selection`)
- [x] User can navigate into a specific learning path
- [x] Learning path page displays title, description, and ordered article list
- [x] User can navigate sequentially through articles (prev / next)
- [x] Progress indication ("Article X of Y in this path")

## Exercises (`features/exercises.md`)

- [x] Content manager can create an exercise page under a learning path in Sulu and link it via the `exercise` field
- [x] Exercise page displays title, optional intro, and all questions
- [x] Each question shows four answer options; only one can be selected
- [x] Submitting reveals which answers are correct and shows any explanations
- [x] "Test yourself →" link appears on the learning path page only when an exercise is linked
- [x] Score summary is shown after submission
- [x] Refreshing the page resets the quiz

## Search (`features/search.md`)

- [x] Full-text search results page at `/search?q=`
- [x] Results display article title and short excerpt
- [x] "No results found" message when query returns nothing
- [x] "Enter a keyword" prompt when no query is present
- [x] `?category={key}` / `?tag={name}` filter params handled on results page
- [x] Autocomplete suggestions after ≥ 2 characters, debounced at 300 ms
- [x] Suggestions grouped: **Articles**, **Categories**, **Tags**
- [x] Clicking article suggestion navigates to that article
- [x] Clicking category/tag suggestion navigates to filtered results page
- [x] "See all results →" navigates to full `/search` page
- [x] Dropdown closes on outside click or navigation
- [x] Author profile pages surfaced in autocomplete as a fourth "Authors" section (filtered by `resourceKey = 'pages'` + `/authors/` URL prefix)

## Product Spec top-level criteria (`product-spec.md §7`)

- [x] Users can navigate to and read articles by category or tag
- [x] Users can find articles based on keywords in titles or content
- [x] Users can view a list of learning paths and follow progression
- [x] Diagrams correctly rendered within article content
- [x] Each article clearly identifies its author, linking to an author profile

---

## Open gaps (items blocking MVP completion)

| # | Gap | File / feature |
|---|-----|----------------|
| 1 | ~~Learning paths index~~ — done via `LearningPathFixture` + `learning-paths` template | ~~`features/learning-paths.md`~~ |
| 2 | ~~Author pages + listing~~ — done via `AuthorPageFixture` + `authors` template | ~~content task~~ |
| 3 | ~~Authors in autocomplete~~ — done, deduped by URL | ~~`features/search.md §3.4`~~ |
| 4 | ~~Nav driven by Sulu navigation API~~ — done; `SiteHeader` is async RSC calling `getNavigation("main")` with static fallback; fixtures seed nav contexts; ADR 0005 | ~~`site-header.tsx`~~ |
| 5 | ~~Interactive Exercises~~ — template, fixture with real quiz content, frontend view, and Sulu-admin authoring flow all built and verified (ADR 0011) | ~~`features/exercises.md`~~ |

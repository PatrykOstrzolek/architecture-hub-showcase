# ADR 0004: Content Modeling for Articles, Authors and Taxonomy

*   **Status**: Accepted
*   **Date**: 2026-06-26
*   **Deciders**: Patryk O
*   **Context Docs**: [Articles](../../product/features/articles.md),
    [Authors](../../product/features/authors.md),
    [Learning Paths](../../product/features/learning-paths.md),
    [Content Types Reference](../content-types-reference.md), [ADR 0003](0003-headless-content-delivery.md)

## Context

The MVP requires Articles (title, summary, rich body with diagram support, publication
date, a linked author, taxonomy), Authors (name, bio, avatar, a profile page listing
their articles) and Learning Paths (ordered article sequences). We must model these in
Sulu 3.0 consistently with our principles — **Built-in Capabilities First**, **Minimal
Customization**, **simplicity over premature abstraction** — and in a way the headless
delivery layer (ADR 0003) exposes cleanly to the Next.js frontend.

Sulu already provides, with no custom code: the Article content type (route via
`page_tree_route`, built-in **Authored date**, **Excerpt** taxonomy, **SEO**), the
Category and Tag taxonomies, Pages with routes, Snippets, and the Media library.

## Decision

Model the **Article** as a Sulu Article template, leaning on built-ins and adding only
the fields the spec needs:

| Requirement | Modeled as | Built-in? |
| :-- | :-- | :-- |
| Title | `title` (`text_line`, `sulu.rlp.part`, search `title`) | type |
| URL | `url` (`page_tree_route`) | type |
| Summary | `summary` (`text_area`, search `description`) | field |
| Publication date | built-in **Authored** date (Settings tab → `authored` in JSON) | ✅ built-in |
| Body (rich, diagrams) | `body` **block** with types `text`, `code`, `image`, `callout` | type |
| Author link | `author` (`single_contact_selection`, mandatory) → a built-in Contact | ✅ built-in entity |
| Categories / Tags | built-in **Excerpt** tab | ✅ built-in |
| SEO | built-in **SEO** tab | ✅ built-in |

### Why these specific choices

*   **Block-based body** (not a single `text_editor`). A knowledge article mixes prose,
    code samples and diagrams; the acceptance criteria explicitly call for "technical
    diagrams". A `block` with `text` / `code` / `image` / `callout` types is the
    idiomatic Sulu way to express that, maps cleanly to one frontend component per block
    type (see Content Types Reference), and keeps each concern typed. The set is kept
    deliberately small to avoid premature abstraction.
*   **Authors are built-in Contacts, with a thin routed profile page** (a hybrid).
    *   **Author identity = a Contact.** The built-in Contact already models everything an
        author needs and the headless `ContactSerializer` exposes it: name, `avatar`
        (resolved media), `position`/`title` (resolved to text), and `note` (used as the
        **bio**). Built-in **Organizations/Accounts** can add affiliation (an Authors
        goal: credibility). The article references the Contact via
        **`single_contact_selection`**, so the author **byline is resolved inline** in the
        article JSON — name, avatar, position — with no extra fetch.
    *   **Profile page = a thin `author` page** under `/authors/<slug>`. The Authors spec
        requires a *fetchable* profile page that *lists the author's articles*, but the
        headless bundle exposes **no standalone contact endpoint** (only navigations,
        analytics, search, snippet-areas) — a Contact has no public URL of its own. A
        small page (`single_contact_selection` for the author + `article_selection` for
        their articles) gives that route for free: `/authors/jane.json` returns the
        resolved Contact plus the ordered article list.

    This keeps author *data* fully built-in (no duplicated name/bio/avatar fields) while
    still meeting the profile-page + article-list requirement.
*   **Publication date = built-in Authored**, not a custom field. Sulu already stores and
    exposes an authored date; adding a field would duplicate it.
*   **Taxonomy = built-in Excerpt.** Verified working (ADR 0003): `extension.excerpt`
    carries `categories` (IDs) and `tags` (names). No custom taxonomy.

### Author → articles (reverse relation)

The author profile page lists its articles via a **manual `article_selection`** on the
author page, rather than an automatic query. Rationale: the headless `smart_content`
data providers filter by category/tag/datasource, **not** by author (Contact), so an
automatic author filter would require custom code (violating Minimal Customization). A
manual ordered selection is simple, explicit, and consistent with how **Learning Paths**
order their articles. Trade-off: the relationship is maintained on both sides (the
article's `author` Contact, and the author page's article list). Accepted for the MVP;
can be revisited with a custom SmartContent provider if it becomes a burden.

Linking an article to the author's *profile page* is likewise a frontend concern: the
article carries the resolved Contact (for the byline), and the frontend maps it to the
matching `/authors` page (e.g. by listing author pages via `smart_content` provider
`pages`). No back-reference field is stored on the article for the MVP.

## Consequences

### Positive

*   Almost entirely built-in: route, authored date, taxonomy, SEO, media, selections, and
    **author identity (Contacts + Organizations)** — no custom entities, no custom delivery.
*   The block body gives rich, typed, frontend-friendly content and meets the diagram
    requirement.
*   Author data lives once (the Contact); the byline resolves inline in the article JSON,
    and the thin author page gives a real, SEO-friendly profile URL + article list.

### Negative / Risks

*   The author↔article relationship is maintained on both sides (see above), and the
    article→profile-page link is resolved frontend-side, not stored.
*   `single_contact_selection` lets editors pick any Contact; convention (authors are the
    Contacts that have a profile page) plus review keeps this clean for the MVP.
*   Article body items are resolved per block type; bespoke needs are handled by adding a
    block type, not by forking resolvers.

## Follow-ups

*   Author page template (`pages/author`) — `single_contact_selection` + `article_selection`. **(done)**
*   An `/authors` parent page to host author profiles, and at least one Contact per author.
*   Learning Path template — title, description, ordered `article_selection`.

## Alternatives Considered

1.  **Single `text_editor` body.** Rejected: weak for code/diagrams; misses an
    acceptance criterion; not showcase-worthy.
2.  **Author as Snippet.** Rejected: no route → no profile page.
3.  **Author as a custom page with its own name/bio/avatar fields** (no Contacts).
    Rejected: duplicates what the built-in Contact already models and ignores the CRM /
    Organizations capabilities. (Superseded by the hybrid above.)
4.  **Author as a Contact only, no Sulu page.** Rejected: there is no standalone public
    contact endpoint, so the profile page would not be directly fetchable and the
    "articles by author" list would have no built-in home. The hybrid adds a thin page to
    close exactly that gap.
5.  **Article links the author *page* (`single_page_selection`) instead of the Contact.**
    Rejected for the byline: a page selection is slim (`title`/`url`) and would need a
    `properties` param to surface the author's name/avatar; linking the Contact resolves
    the full byline inline. (The built-in `authored` *date* is still used for the
    publication date regardless.)
6.  **Custom Author/Article Doctrine entities + API.** Rejected: violates Built-in
    Capabilities First and Minimal Customization for no MVP benefit.

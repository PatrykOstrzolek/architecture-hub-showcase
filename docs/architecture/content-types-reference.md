# Sulu Content Types — Headless Delivery Reference

How each Sulu content type is serialized to JSON by the **SuluHeadlessBundle**, so
the Next.js frontend knows exactly what shape to expect per template property.

> **Source of truth:** the resolvers installed in
> `backend/vendor/sulu/headless-bundle/Content/`. This document mirrors that code
> (verified 2026-06-26, `sulu/headless-bundle` 3.0.x). When in doubt, the resolver
> wins — re-read it rather than trusting prose.

## How resolution works (the mental model)

A page/article/snippet template is a list of **properties**, each with a **content
type** (`text_editor`, `media_selection`, `block`, `smart_content`, …). At delivery
time the `StructureResolver` walks the template's fields and asks the matching
`ContentTypeResolver` to turn the raw stored value into JSON. Every resolver returns
a `ContentView` with two halves:

* **`content`** — the resolved, frontend-ready value (lands in the payload's `content[propertyName]`).
* **`view`** — metadata *about* the resolution (raw ids, pagination, filter echo). Lands in `view[propertyName]`. Usually ignored by rendering; useful for debugging, "load more", and re-querying.

So the page payload is:

```jsonc
{
  "id": "...", "type": "page", "template": "homepage",
  "content": { "title": "...", "body": [ /* resolved per content type */ ] },
  "view":    { "title": [],   "body": [ /* per-property view metadata */ ] },
  "extension": { "excerpt": { ... }, "seo": { ... } },
  // author/changer/creator + timestamps + localizations (see ADR 0003)
}
```

`content[prop]` is keyed by the **template property name** you author in the XML, not
by the content type. The content type only decides the *shape* of the value.

---

## Authoring side: templates & properties (the semantic layer)

> Source: [Sulu docs — Templates](https://docs.sulu.io/3.x/book/templates.html),
> cross-checked against this project's own templates in
> `backend/config/templates/`.

Before a content type produces JSON, an **editor authors it** through a template. A
template is the contract between three audiences: the **editor** (what fields they
see), the **delivery layer** (what JSON comes out), and the **frontend** (what it
renders). Understanding the authoring side is what lets us design good "knowledge
article" templates.

### Template anatomy

Each page/article/snippet type is one XML file under
`backend/config/templates/{pages,articles,snippets}/`:

```xml
<template xmlns="http://schemas.sulu.io/template/template">
  <key>article</key>                 <!-- unique id, == filename -->
  <view>articles/article</view>      <!-- Twig view (unused in pure-headless, but required) -->
  <controller>Sulu\Bundle\HeadlessBundle\Controller\HeadlessWebsiteController::indexAction</controller>
  <cacheLifetime>604800</cacheLifetime>   <!-- HTTP max-age (s) or cron expression -->
  <meta><title lang="en">Article</title></meta>   <!-- label in admin -->
  <properties>
    <property name="title" type="text_line" mandatory="true">
      <meta><title lang="en">Title</title></meta>
      <tag name="sulu.rlp.part"/>          <!-- contributes to the auto-generated slug -->
    </property>
    <!-- … -->
  </properties>
</template>
```

The `<controller>` line is what makes a template headless-deliverable (it's set on all
three of this project's templates). `<cacheLifetime>` flows through to the
`Cache-Control` header the frontend's `fetch` sees.

### Property attributes (authoring controls)

| Attribute | Meaning |
| :-- | :-- |
| `name` | property key — becomes the key in `content` / `view` |
| `type` | content type (drives both the admin widget and the JSON shape) |
| `mandatory="true"` | server-side required (JSON-Schema validated on save) |
| `multilingual` | default `true`; per-language value. `false` = one value shared across locales |
| `colspan="1..12"` | admin grid width only — no effect on output |
| `visibleCondition` / `disabledCondition` | JEXL expressions to show/lock a field based on others (great for block types) |
| `<meta><title>` / `<info_text>` | editor-facing label and help text (per `lang`) |
| `<params>` | type-specific config (select options, smart_content provider, etc.) |

### Structural tags (semantic roles)

Tags assign meaning beyond plain data — this is how Sulu knows which field is the
"title", which builds the URL, and what to index for search:

| Tag | Role |
| :-- | :-- |
| `sulu.rlp` | **the** resource-locator property (the page's URL). Type `route` (top-level) or `page_tree_route` (nested under a parent, as articles use here). |
| `sulu.rlp.part` | this field feeds the **auto-generated slug** (title → `cap-theorem`). Put on `title`. |
| `sulu.search.field` with `role="title|description|image"` | marks the field for the search index and what role it plays in a hit. Drives `/api/search` result quality. |

> In this project's templates: `title` carries `sulu.rlp.part`, the `url` property
> carries `sulu.rlp` (pages) or is a `page_tree_route` (articles). Knowledge-article
> templates should additionally tag the lead/body for search (`sulu.search.field`)
> so articles surface well in `/api/search`.

### Authoring field-type catalog (semantic meaning)

What each type *means to the editor* — pair this with the delivery-shape tables below.

| Type | Editor experience | Delivers as |
| :-- | :-- | :-- |
| `text_line` | single-line text | string |
| `text_area` | multi-line plain text | string |
| `text_editor` | rich WYSIWYG (HTML, internal links) | HTML string |
| `checkbox` | boolean toggle | bool |
| `single_select` | dropdown, one of `<params><param name="values">` | selected value |
| `multiple_select` | dropdown, many | value array |
| `color` | color picker | hex string |
| `date` / `time` | date / time picker | ISO-ish string |
| `url` / `email` / `phone` | validated text inputs | string |
| `route` | the page URL editor | slug string (tag `sulu.rlp`) |
| `page_tree_route` | URL nested under a parent page | `{ page:{uuid,path}, suffix }` |
| `single_media_selection` / `media_selection` | pick one / many from media library | media object / array |
| `single_page_selection` / `page_selection` | pick page(s) | page object / array (slim) |
| `smart_content` | configure a **query** (source, filters, sort, limit) | resolved item array |
| `teaser_selection` | pick mixed pages/articles/media as teasers | teaser array |
| `snippet_selection` / `single_*` | pick reusable snippet(s) | full snippet content array / object |
| `category_selection` | assign taxonomy categories | category object array |
| `tag_selection` | free-text tags (autocomplete) | string array |
| `single_icon_selection` | icon picker | icon ref |
| `contact_selection` / `account_selection` (+ `single_*`) | CRM pickers | contact/account objects |
| `block` | **repeatable, reorderable, typed** content rows | array of typed blocks |
| `image_map` | image + positioned hotspots | image + hotspots |

### Blocks & sections (authoring)

* **`block`** — `<block name="body" default-type="text" minOccurs="0" maxOccurs="20">`
  with `<types>`, each `<type>` having its own `<properties>`. Editors add, remove and
  reorder instances and pick a type per row. This is how a knowledge article gets a
  flexible body (text / quote / code / image / callout blocks).
* **Global blocks** — define a block type once in
  `config/templates/blocks/{name}.xml`, reference it via `<type ref="name"/>` to reuse
  across templates (e.g. a shared "code sample" block for all articles).
* **`section`** — purely visual grouping in the admin (`<section>` with nested
  `<properties>`); **no effect on the data model or JSON**.

### What this means for "knowledge articles"

The current `article.xml` is minimal (`title`, `url`, `article` text_editor). A
richer knowledge-article template would likely add: a `block` body (text / code /
callout / media block types — ideally global blocks for reuse), a
`single_media_selection` hero image, `category_selection` + `tag_selection` for the
taxonomy the MVP needs, an `author`/`single_contact_selection` or a dedicated author
snippet, and `sulu.search.field` tags on the lead + body. When we design that
template, each field's row in the catalog above tells us the exact JSON the frontend
component will receive.

---

## The compositional types (the important ones)

### `block` — repeatable, typed, nestable

The workhorse for page bodies. A block property is an **ordered array**; each element
is one block of a chosen type, and every block type has its own fields — which are
**themselves recursively resolved** by their own content types. Blocks can contain
blocks.

Resolved shape (`content[propName]`):

```jsonc
[
  {
    "type": "text_block",          // the block type key from the template
    "settings": { /* block settings, e.g. visibility */ },
    "heading": "About",            // each field resolved by ITS content type:
    "text": "<p>resolved html</p>" //   text_editor → html string, etc.
  },
  {
    "type": "media_block",
    "settings": {},
    "images": [ /* media_selection → array of media objects (below) */ ]
  }
]
```

`view[propName]` is a parallel array holding each block's per-field view metadata.

**Frontend pattern:** render a block list by `switch (block.type)` → a component per
block type. This is the primary composition mechanism; design React components to map
1:1 to block types.

### `smart_content` — a stored *query*, resolved to results

This is the one to understand carefully. The author does **not** pick items; they
configure a query (data source subtree, categories, tags, sorting, limit), and the
backend **executes it at delivery time** and returns the matching items already
resolved.

Resolved shape (`content[propName]`): **an array of resolved items**, where item
shape depends on the configured `provider` (`pages`, `articles`, `snippets`,
`media`, `contacts`, `accounts`).

> **Critical gotcha — items are a slim projection, not full content.**
> For `pages`/`articles` providers, each item resolves only `title` and `url` **by
> default**. To get more fields you must declare them via the `properties` param in
> the template's `<param>` config. Without that, do **not** expect body/excerpt/media
> on smart_content items.

```jsonc
// content[propName] — pages provider, default projection
[
  { "id": "...", "type": "page", "template": "default",
    "content": { "title": "CAP Theorem", "url": "/articles/cap-theorem" },
    "view": { "title": [], "url": [] } }
]
```

`view[propName]` carries the **query echo + pagination** — useful for "load more":

```jsonc
{
  "tags": [], "categories": [],
  "websiteTags": [], "websiteCategories": [],
  "page": 1, "hasNextPage": true, "paginated": true
}
```

Pagination is driven by a URL query param (default `p`) and category/tag filters by
URL params (default `categories`, `tags`) — so smart_content listings are
**filterable/paginated from the frontend URL** without custom endpoints. This is how
the MVP's "list articles by category/tag" requirement is met (see ADR 0003).

**Providers available** (from `DataProviderResolver/`): `pages`, `articles`,
`articles_page_tree`, `snippets`, `media`, `contacts`, `accounts`.

### `image_map` — one image + typed hotspots

```jsonc
{
  "image": { /* full media object */ },
  "hotspots": [
    { "type": "link_hotspot", "hotspot": { /* x/y/shape geometry */ },
      /* + that hotspot type's fields, each recursively resolved */ }
  ]
}
```

Same recursive-field idea as `block`, but anchored to an image with hotspot geometry.

---

## Selections (author hand-picks specific items)

Two flavours throughout: the **multi** (`*_selection` → **array**) and the **single**
(`single_*_selection` → **one object or `null`**). Single resolvers just delegate to
the multi resolver and unwrap `[0]`.

| Content type | `content` shape | Notes |
| :-- | :-- | :-- |
| `media_selection` | `Media[]` | full media objects (see below) |
| `single_media_selection` | `Media \| null` | |
| `page_selection` | `Page[]` (slim) | `title`+`url` by default; extend via `properties` param |
| `single_page_selection` | `Page \| null` | |
| `article_selection` | `Article[]` (slim) | as pages |
| `single_article_selection` | `Article \| null` | |
| `snippet_selection` | `Snippet[]` | **full** resolved snippet content; excerpt only if `loadExcerpt` param; supports a `default` area fallback |
| `single_snippet_selection` | `Snippet \| null` | |
| `category_selection` | `Category[]` | serialized with `partialCategory` group → `{ id, key, name, … }` |
| `single_category_selection` | `Category \| null` | |
| `teaser_selection` | `Teaser[]` | mixed pages/articles/media as uniform teasers (below) |
| `collection_selection` / `single_*` | media collection object(s) | |
| `contact_selection`, `account_selection`, `contact_account_selection` + `single_*` | contact/account object(s) | CRM types; not expected in this project's MVP |

`view` for selections is typically `{ "ids": [...] }` (or `{ "id": "..." }` for
singles) — the raw selection echoed back.

> ✅ **Verified (2026-06-26) — page/article selection item shape.** Each item in a
> `page_selection` / `article_selection` is itself a slim resolved structure that
> **nests its fields under `content`** (plus top-level `id`, `type`, `template`,
> `author`, `authored`, timestamps). Observed for an `article_selection`:
> ```json
> { "id": "019f04af…", "type": "article", "template": "article",
>   "content": { "title": "Understanding the CAP Theorem", "url": null },
>   "view": { "url": { "page": {"uuid":"019f03fd…","path":"/"},
>                      "suffix": "understanding-the-cap-theorem" } },
>   "author": 2, "authored": "2026-06-26T16:07:49+00:00", … }
> ```
> Note the **`page_tree_route` quirk**: the article's URL is **not** in
> `content.url` (which is `null`) — it resolves into **`view.url`** as
> `{ page: { path }, suffix }`. The frontend builds the href as `path + suffix`
> (here `/` + `understanding-the-cap-theorem` → `/understanding-the-cap-theorem`).
> Declare a `properties` param on the selection to surface more than `title`/`url`.

### Media object shape

Produced by `MediaSerializer` (full Media API minus `formats`, `thumbnails`,
`versions`, `storageOptions`, `downloadCounter`, `adminUrl`). Frontend-relevant keys:

```jsonc
{
  "id": 12, "title": "...", "description": "...", "name": "file.jpg",
  "mimeType": "image/jpeg", "isImage": true,
  "url": "/media/12/download/file.jpg?v=1",
  "formatUri": "/media/12/{format}/file.{extension}",  // responsive: swap {format}/{extension}
  "formatPreferredExtension": "webp"
}
```

`formatUri` is the key to responsive images: substitute a Sulu image format name for
`{format}` and `formatPreferredExtension` for `{extension}`.

### Teaser object shape

`teaser_selection` normalizes heterogeneous targets (pages, articles, media) into one
shape — ideal for "featured cards":

```jsonc
{ "id": "...", "type": "pages", "locale": "en",
  "title": "...", "description": "...", "moreText": "...",
  "url": "/...", "media": { /* media object | null */ } }
```

---

## Scalars & links

| Content type | `content` shape | Notes |
| :-- | :-- | :-- |
| `text_editor` | `string` (HTML) | internal `sulu:link` markup is resolved to real URLs server-side |
| `text_line` / `text_area` / `color` / `checkbox` / `single_select` etc. | passthrough scalar | no dedicated resolver → value passes through as stored (string/bool/number) |
| `link` | `string \| null` (resolved href) | `view` carries `{ provider, target, title }`; anchor appended as `#anchor` |
| `page_tree_route` | route object | `{ page: { uuid, path }, suffix }` |

> Any content type **without** a dedicated resolver passes its stored value through
> unchanged. So custom/simple fields (text lines, selects, toggles) appear in
> `content` as plain JSON primitives — no special handling needed.

---

## Extensions: excerpt, SEO, taxonomy (`extension.*`)

Every page/article carries an `extension` bag alongside `content` (see ADR 0003 for
the verified top-level shape). Two parts matter for the frontend:

* **`extension.seo`** — `{ title, description, keywords, canonicalUrl, noIndex,
  noFollow, hideInSitemap }`. Map straight into Next.js `generateMetadata`.
* **`extension.excerpt`** — `{ title, description, more, icon, image, categories,
  tags, audience_targeting_groups, segments }`.

> ✅ **Verified (2026-06-26).** A page/article's own excerpt taxonomy resolves to
> **bare identifiers, not objects.** After authoring a category + tag on the homepage
> and publishing, `/.json` returned:
> ```json
> "categories": [1],          // category IDs (number[])
> "tags": ["cap-theorem"]      // tag name strings (string[])
> ```
> This matches the installed `StructureResolver::resolveTaxonomyData()`
> (`getExcerptCategoryIds()` / `getExcerptTagNames()`), which merges IDs + names over
> any resolved objects. It is **different** from the `category_selection` *content
> type* (a template body field), which DOES return full `{ id, key, name }` objects:
> * Page/article **excerpt** taxonomy → raw category IDs + tag-name strings.
> * A `category_selection` **property in the body** → resolved category objects.
>
> To render category names/links from an excerpt, resolve the IDs separately.
> `frontend/lib/sulu.ts` types reflect this (`SuluExcerpt.categories: number[]`).

---

## Discovery APIs (not template properties)

Served at portal `/api/...` (no locale prefix for this single-locale, host-root
webspace):

* `GET /api/navigations/{context}?depth=&flat=` → `_embedded.items` navigation tree.
* `GET /api/search?q=` → `_embedded.hits` (shape documented in ADR 0003).
* `GET /api/snippet-areas/{area}` → a globally-assigned snippet by area.

---

## Frontend takeaways

1. **Model components around block types and the seven smart_content providers** —
   those two cover the bulk of real rendering.
2. **smart_content & page/article selections are slim by default** — declare a
   `properties` param backend-side for any field you need beyond `title`/`url`.
3. **Selections come in multi (array) and single (object|null) variants** — type both.
4. **Media:** use `formatUri` + `formatPreferredExtension` for responsive `<img>`/`next/image`.
5. **`view` metadata** (esp. smart_content `hasNextPage`/`page`) powers pagination &
   filtering driven by URL query params — no custom API needed.
6. **Excerpt taxonomy is IDs + tag-name strings** (verified) — resolve category IDs
   separately for names; don't expect resolved objects there.

## Open items to verify against a live payload

* [x] Excerpt `categories`/`tags` concrete shape — **verified**: `categories: number[]`
  (IDs), `tags: string[]` (names). See the ✅ note above.
* [ ] Exact `properties`-param syntax for enriching smart_content / selection items.
* [ ] Snippet-area (`/api/snippet-areas/{area}`) response shape.

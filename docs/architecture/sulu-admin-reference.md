# Sulu Admin — Navigation & Platform Behavior Reference

General Sulu 3.x admin-UI knowledge gathered by walking every nav item in this
project's instance (`http://localhost:8000/admin`, verified 2026-07-05). Where
[content-types-reference.md](content-types-reference.md) covers *headless
delivery* (how a template property becomes JSON), this doc covers the *admin
UI/platform* side: what each nav item is backed by, and Sulu behaviors that
aren't specific to any one template.

## Navigation map

| Nav item | Route | Backed by |
| :-- | :-- | :-- |
| Search | `#/` (root) | Global search index — see [scope](#global-search-index-scope-not-everything-is-indexed) below |
| Webspaces → Pages | `#/webspaces/{key}/pages/{locale}` | Page tree (this project: single webspace `architecture-hub`) |
| Webspaces → Analytics | `#/webspaces/{key}/analytics` | Per-domain tracking-code config (Google Analytics etc.); unused here |
| Snippets | `#/snippets/{locale}` | Reusable cross-page content; this project only has the `default` snippet type |
| Articles | `#/articles/{locale}/default` | Sulu ArticleBundle list (route segment `default` is a list-view key, not a template name) |
| Media | `#/collections/{locale}` | DAM, collection-based — see [System collection](#sulu-auto-manages-a-system-media-collection) below |
| Assessment → Questions/Question Sets/Attempts | `#/questions`, `#/question-sets`, `#/attempts` | **Not** Sulu content — a custom Doctrine-backed Admin extension, see [ADR-0014](adrs/0014-question-set-entities.md) |
| Contacts → People | `#/contacts` | `Contact` entity |
| Contacts → Organizations | `#/accounts` | **`Account`** entity — see [terminology note](#contacts-people-vs-organizations-is-contact-vs-account) below |
| Settings → User roles | `#/roles` | Role + permission-context matrix |
| Settings → Categories | `#/categories/{locale}` | Hierarchical taxonomy |
| Settings → Tags | `#/tags` | Flat taxonomy |
| Settings → Activities | `#/activities` | Audit log — see [scope note](#the-activity-log-only-covers-sulu-native-content) below |
| Settings → Trash | `#/trash/{locale}` | Soft-delete recovery |

## Contacts: "People" vs "Organizations" is `Contact` vs `Account`

The nav label "Organizations" routes to `#/accounts`, not `#/organizations` —
Sulu's ContactBundle calls a company/organization an **`Account`** internally
(`AccountController`, `account_selection` content type, etc.). "People" maps
1:1 to `Contact`. Worth knowing before grepping vendor code for
"organization" and finding nothing.

## Every page/article edit form has 4 tabs, not just "Content"

Opening a page or article (not just its list row) shows a tab strip:
**Content** / **SEO** / **Excerpt & Taxonomies** / **Settings**, plus a live
preview iframe alongside the form. The nav walkthrough above only covered
list-level views, so it's worth calling out explicitly:

* **Content** — the template's own properties (what's in `content.*`).
* **SEO** — meta title/description/keywords, canonical URL, no-index/
  no-follow/hide-in-sitemap checkboxes. Maps directly to `extension.seo`
  (see [content-types-reference.md](content-types-reference.md)).
* **Excerpt & Taxonomies** — title/more-link/description (rich text), two
  media slots (icon + image), category checkboxes (flat list, not the
  hierarchical tree shown in Settings → Categories), and a free-text tags
  field. Maps to `extension.excerpt`. `audience_targeting_groups`/`segments`
  (present in the JSON shape doc) weren't visible as form fields here —
  likely Sulu Enterprise-only, not exercised by this Community-edition setup.
* **Settings** — page-type toggles (**Enable Link**, **Enable Shadow page** —
  the latter is what the pages-tree "Show ghost and shadow pages" checkbox
  surfaces), last-modified (read-only) / authored date, an author contact
  picker, and a **navigation-context assignment dropdown** (e.g. "Main
  Navigation", unchecked by default). This dropdown is literally where an
  editor opts a page into the navigation tree that
  [ADR-0005](adrs/0005-sulu-navigation-api-for-site-header.md)'s
  `/api/navigations/{context}` endpoint serves — worth knowing when
  debugging "why isn't this page showing up in the header nav".

## Articles vs. Pages: the same 4 tabs, different Content shape

An Article's edit form has the identical tab strip (Content / SEO / Excerpt &
Taxonomies / Settings) but isn't just a re-skinned Page:

* **Content** — Articles put `Author`, `Categories`, and `Tags` directly as
  first-class template properties on the Content tab itself (alongside
  Title/Resourcelocator/Summary/Body-as-blocks). A Page's Content tab has
  none of that — categories/tags/author only exist via the separate Excerpt
  tab. This matches the JSON-shape distinction already noted in
  [content-types-reference.md](content-types-reference.md#selections-author-hand-picks-specific-items):
  a `category_selection` **property** resolves to full category objects,
  while **excerpt** taxonomy resolves to bare ids/name-strings.
* **Excerpt & Taxonomies** — present but genuinely unused on this project's
  articles (empty title/description/no categories authored there) — the
  tab exists because every Sulu content type gets it for free, not because
  this project uses it twice.
* **Settings** — no navigation-context dropdown (articles aren't part of the
  webspace nav tree the way pages are); instead has a "Customize" checkbox
  that unlocks a webspace/type override, plus the same Enable Shadow page
  toggle and author/date fields as a Page.

## Contacts (People/Organizations) have their own 3-tab shape

Neither `Contact` nor `Account` uses the Content/SEO/Excerpt/Settings tabs at
all — they get **Details / Documents / Permissions**:

* **Details** — the entity's own fields (name, org, birthday/UID, avatar or
  logo upload, notes, multiple email/phone rows via "Add", free-text Tags)
  **plus the same flat Categories checkbox list** used on pages/articles —
  taxonomy isn't just a content-authoring concept in Sulu, it's attachable to
  contacts/accounts too.
* **Documents** — a list of media/files attached to this contact/account
  (e.g. signed agreements) — empty by default, its own Add/Delete toolbar.
* **Permissions** — this is where a `Contact` becomes a logged-in `User`:
  Username, Email, Password (+confirm), System language, and a role/webspace
  permission matrix identical in shape to Settings → User roles. Confirms
  `Contact` and `User` are separate entities in Sulu — every `User` has an
  underlying `Contact` (this project's "Adam Ministrator" login), but a
  `Contact` (like "Jane Kowalski", an author-only record) doesn't need one.

## Toolbar actions, by view shape

List views split into two toolbar patterns:

* **Flat lists** (Snippets, Articles, People, Organizations, Categories,
  User roles) → **Add / Delete / Export** (Export sometimes disabled until a
  row is selected, depending on the resource).
* **The page tree** has no such toolbar at all — Add/Delete are per-row
  (inline `su-plus-circle`/edit-pencil icons in tree-list view) since a tree
  needs a parent context per action; the tree's own toolbar only has "Show
  ghost and shadow pages" + "Clear website cache" + locale.
* **Media** → Upload file / Delete selected / Move selected (no Export/Add —
  "Add" is "Upload").
* **Read-only/system views** taper down further: Tags still gets the full
  Add/Delete/Export triad, but **Trash** is Delete-only (permanent purge; row
  restore is presumably a per-row action, not visible with an empty trash),
  and **Activities** has no toolbar buttons at all — pure audit log.
* **Assessment** (Questions/Question Sets) → Add/Delete only, no Export, no
  locale selector — confirms these are plain non-localized Doctrine
  entities, not Sulu content. **Attempts** → Delete only (see
  [ADR-0014](adrs/0014-question-set-entities.md): "written once by the
  grading flow, never authored in admin").

## Sulu auto-manages a "System" media collection

Under Media → All media there's always a `System` collection (2 auto-managed
subfolders observed here):

* **`System / Sulu contacts`** — holds contact avatar uploads (this project:
  2 objects, matching the 2 seeded `Contact` records, Adam Ministrator and
  Jane Kowalski).
* **`System / Sulu media / Preview images`** — reserved for
  Sulu-generated preview/thumbnail images (empty in this project).

Don't delete or repurpose `System` — it's framework-managed, not
author-created content.

## Global search index scope (not everything is indexed)

The Search dashboard's "Everything" dropdown lists the indexed entity types:
**Pages, Snippets, Articles, Categories, Media, Collection, Organization,
People**. Notably absent: `Question`/`QuestionSet`/`Attempt` — the custom
Assessment entities (ADR-0014) aren't wired into Sulu's search index at all,
consistent with them bypassing Sulu's content-management machinery generally
(see next section).

## The Activity log only covers Sulu-native content

Settings → Activities records events like "Someone has published the page
...", but only for Sulu-native content types (pages, articles, snippets,
media, contacts). Creating/editing a `Question` or `QuestionSet` produces no
Activities entry — another instance of the Assessment bounded context
deliberately living outside Sulu's cross-cutting systems, alongside the
missing search-index entry above and the missing permission-context gating
noted in ADR-0014's "Sulu Admin extension" section.

## Permission contexts (Settings → User roles)

A role's permission matrix is grouped by these contexts (this project: one
role, "User", with everything granted). Useful as a checklist of what Sulu
manages access to out of the box:

```
Webspaces
  {webspace-key}              (Pages)
  {webspace-key}.analytics
  {webspace-key}.custom-urls
Activities  → activities
Article     → articles
Contacts    → people, organizations
Media       → collections, system_collections
References  → references
Security    → roles, users
Settings    → categories, tags
Snippet     → snippets
Trash       → trash
```

Each Sulu-native module contributes its own row(s) here automatically. A
custom Admin extension (like `AssessmentAdmin`) does **not** get a row unless
it explicitly registers one via Sulu's `SecurityChecker`/permission-context
API — this project's Assessment module deliberately skips that (single-role
project, see ADR-0014), which is why "Assessment" never appears in this list.

## Custom non-CMS entities can still reuse Sulu's admin widgets

Confirmed hands-on (backing ADR-0014's account): a Doctrine entity with zero
relationship to Sulu's content model can still get a full list/form admin
screen, including Sulu's generic **block** editor (`config/forms/question_details.xml`'s
`options` block) and generic **selection picker** (`question_selection` /
`single_question_set_selection`, wired purely via `sulu_admin.yaml`'s
`field_type_options` + `resources.<resourceKey>.routes` — no custom
JS/webpack build required). This is a reusable pattern for any future
internal CRUD screen: define `config/lists/*.xml` + `config/forms/*.xml`,
register views in a custom `Admin` class, and if a field needs to reference
another custom resource, configure it as a `selection`/`single_selection`
field type rather than writing a bespoke picker.

/**
 * Sulu headless content client (server-side / RSC).
 *
 * Talks to the SuluHeadlessBundle delivery API on the Sulu backend. Shapes
 * below were verified empirically against a running sulu/sulu 3.0.7 +
 * sulu/headless-bundle 3.0.1 instance (webspace `architecture-hub`):
 *
 *   - Page / article bodies:  GET {base}{path}.json        (e.g. "/.json", "/articles/cap-theorem.json")
 *   - Navigation:             GET {base}/api/navigations/{context}
 *   - Search:                 GET {base}/api/search?q=...
 *
 * Locale is NOT carried in the URL: the `architecture-hub` webspace maps its
 * single default locale (`en`) to the host root (`<url language="en">{host}</url>`),
 * so the locale is resolved server-side by the matched portal URL. To support a
 * multi-locale webspace that prefixes the locale (e.g. `{host}/{localization}`),
 * prepend that prefix to `path` here.
 *
 * The HeadlessWebsiteController returns the Twig HTML view at the plain URL and
 * this JSON only when the `.json` format suffix is present.
 *
 * See docs/architecture/adrs/0003-headless-content-delivery.md
 */

import { draftMode } from "next/headers"

const BASE_URL = process.env.SULU_BASE_URL ?? "http://localhost:8000"
const PREVIEW_SECRET = process.env.PREVIEW_SECRET

/** Revalidation window (seconds) for cached content fetches. */
const REVALIDATE_SECONDS = 60

// --- Taxonomy -------------------------------------------------------------

/**
 * Resolved category object as returned by the `category_selection` *content type*
 * (a template body field). The headless CategorySelectionResolver serializes with
 * the `partialCategory` group: id, key, locale-resolved name.
 *
 * NOTE: this is NOT the shape of a page/article's own excerpt taxonomy — see
 * `SuluExcerpt.categories`, which carries raw category IDs, not these objects.
 */
export interface SuluCategory {
  id: number
  key: string | null
  name: string
}

/** Resolved tag value. */
export type SuluTag = string

/**
 * Built-in excerpt extension — where page/article taxonomy lives.
 * Served under `extension.excerpt` in the content payload.
 *
 * Verified empirically (2026-06-26) against the running instance: the excerpt's
 * own taxonomy resolves to bare identifiers, NOT resolved objects —
 * `categories` is an array of category **IDs** and `tags` an array of tag
 * **name strings** (observed: `"categories": [1], "tags": ["cap-theorem"]`).
 * This is the StructureResolver taxonomy path (`getExcerptCategoryIds()` /
 * `getExcerptTagNames()`), distinct from the `category_selection` content type.
 * To render category names/links, resolve these IDs separately.
 */
export interface SuluExcerpt {
  title: string
  description: string
  more: string
  /** Category IDs (resolve separately for names) — not resolved objects. */
  categories: number[]
  /** Tag name strings. */
  tags: SuluTag[]
  icon: unknown[]
  image: unknown[]
  audience_targeting_groups: unknown[]
  segments: unknown[]
}

/** SEO extension — served under `extension.seo`. */
export interface SuluSeo {
  title: string
  description: string
  keywords: string
  canonicalUrl: string
  noIndex: boolean
  noFollow: boolean
  hideInSitemap: boolean
}

/** Built-in extensions bag attached to every page/article. */
export interface SuluExtension {
  excerpt: SuluExcerpt
  seo: SuluSeo
}

// --- Media & Contacts -----------------------------------------------------

/**
 * Resolved media object (MediaSerializer). URLs are relative to the Sulu host —
 * use {@link mediaUrl} to absolutize. `formatUri` carries `{format}`/`{extension}`
 * placeholders for responsive variants.
 */
export interface SuluMedia {
  id: number
  title: string | null
  description: string | null
  name: string
  mimeType: string
  isImage: boolean
  url: string
  formatUri?: string
  formatPreferredExtension?: string
}

/**
 * Resolved Contact (ContactSerializer) — how an author surfaces inline via a
 * `single_contact_selection`. `note` carries the bio; `position`/`title` resolve
 * to text; `avatar` is a full media object.
 */
export interface SuluContact {
  id: number
  fullName?: string
  firstName?: string
  lastName?: string
  position?: string | null
  title?: string | null
  note?: string | null
  avatar?: SuluMedia | null
}

/** Absolutize a Sulu media URL against the backend host. */
export function mediaUrl(path: string): string {
  return path.startsWith("http") ? path : `${BASE_URL}${path}`
}

// --- Content --------------------------------------------------------------

/** A locale alternate as advertised in `localizations`. */
export interface SuluLocalization {
  url: string
  locale: string
  alternate: boolean
}

/**
 * A resolved page or article. `content` is the template's resolved properties
 * (title, body, media, blocks, …); its keys depend on the template, so it is
 * left open and narrowed per template at call sites. `view` carries the
 * per-property view metadata (same keys as `content`).
 */
export interface SuluContent<TContent = Record<string, unknown>> {
  id: string
  type: "page" | "article" | string
  linkType: string | null
  template: string
  content: TContent
  view: Record<string, unknown>
  extension: SuluExtension
  author: string | null
  authored: string | null
  changer: string | null
  changed: string | null
  creator: string | null
  created: string | null
  localizations: Record<string, SuluLocalization>
}

export interface SuluNavigationItem {
  id: string
  title: string
  url: string
  children?: SuluNavigationItem[]
}

/** A search hit from the cmsig/seal-backed website index. */
export interface SuluSearchHit {
  id?: string
  resourceKey?: string
  resourceId?: string
  locale?: string
  webspaces?: string[]
  title: string
  url: string
  content: unknown[]
  authoredAt: string | null
  _formatted?: Record<string, string | null>
  media?: unknown
}

/** A category suggestion returned by /api/taxonomy. */
export interface SuluTaxonomyCategory {
  id: number
  key: string
  name: string
}

/** A tag suggestion returned by /api/taxonomy. */
export interface SuluTaxonomyTag {
  id: number
  name: string
}

/** Autocomplete suggestions grouped by type. */
export interface SuluTaxonomySuggestions {
  categories: SuluTaxonomyCategory[]
  tags: SuluTaxonomyTag[]
}

// --- Fetch helpers --------------------------------------------------------

class SuluNotFoundError extends Error {}
class SuluUnavailableError extends Error {}

async function suluFetch<T>(
  path: string,
  revalidate = REVALIDATE_SECONDS
): Promise<T> {
  let res: Response
  try {
    res = await fetch(`${BASE_URL}${path}`, {
      headers: { Accept: "application/json" },
      next:
        revalidate > 0 ? { revalidate, tags: ["content"] } : { revalidate: 0 },
    })
  } catch {
    throw new SuluUnavailableError(`Sulu backend unreachable: ${BASE_URL}`)
  }

  if (res.status === 404) {
    throw new SuluNotFoundError(`Sulu content not found: ${path}`)
  }
  if (!res.ok) {
    throw new Error(`Sulu request failed (${res.status}): ${path}`)
  }
  return res.json() as Promise<T>
}

// --- Public API -----------------------------------------------------------

/**
 * Fetch a page or article rendered as JSON by the HeadlessWebsiteController.
 * `path` is the content's resource locator, e.g. "/" or "/articles/cap-theorem".
 * Returns null when no content is published at that path.
 *
 * When Next.js Draft Mode is enabled (via /api/preview, see ADR-0013), this
 * instead reads draft-stage content from the backend's preview endpoint —
 * bypassing the publish requirement and any response caching.
 */
export async function getContent<TContent = Record<string, unknown>>(
  path: string
): Promise<SuluContent<TContent> | null> {
  const { isEnabled: isPreview } = await draftMode()
  if (isPreview) {
    return getDraftContent<TContent>(path)
  }

  try {
    return await suluFetch<SuluContent<TContent>>(`${path}.json`)
  } catch (err) {
    if (err instanceof SuluNotFoundError) return null
    if (err instanceof SuluUnavailableError) return null
    throw err
  }
}

async function getDraftContent<TContent>(
  path: string
): Promise<SuluContent<TContent> | null> {
  if (!PREVIEW_SECRET) return null

  const params = new URLSearchParams({ path, locale: "en" })
  let res: Response
  try {
    res = await fetch(`${BASE_URL}/api/preview/pages?${params}`, {
      headers: { Authorization: `Bearer ${PREVIEW_SECRET}` },
      cache: "no-store",
    })
  } catch {
    return null
  }

  if (!res.ok) return null
  return res.json() as Promise<SuluContent<TContent>>
}

/** Fetch a navigation tree by its Sulu navigation context key. */
export async function getNavigation(
  context: string,
  { depth = 1, flat = false }: { depth?: number; flat?: boolean } = {}
): Promise<SuluNavigationItem[]> {
  const params = new URLSearchParams({
    depth: String(depth),
    flat: String(flat),
  })
  try {
    const data = await suluFetch<{
      _embedded: { items: SuluNavigationItem[] }
    }>(`/api/navigations/${context}?${params}`)
    return data._embedded.items
  } catch (err) {
    if (err instanceof SuluUnavailableError) return []
    throw err
  }
}

/** Full-text search across the website index. */
export async function search(query: string): Promise<SuluSearchHit[]> {
  const params = new URLSearchParams({ q: query })
  const data = await suluFetch<{ _embedded: { hits: SuluSearchHit[] } }>(
    `/api/search?${params}`,
    0
  )
  // Sulu/seal can return duplicate hits when multiple indices are configured;
  // deduplicate by URL before returning.
  const seen = new Set<string>()
  return data._embedded.hits.filter((hit) => {
    if (seen.has(hit.url)) return false
    seen.add(hit.url)
    return true
  })
}

/** Articles filtered by category key or tag name. */
export async function searchByTaxonomy(
  filter: { category: string } | { tag: string }
): Promise<SuluSearchHit[]> {
  const params = new URLSearchParams(filter)
  const data = await suluFetch<{ _embedded: { hits: SuluSearchHit[] } }>(
    `/api/articles?${params}`
  )
  return data._embedded.hits
}

export interface ArticlesPage {
  _embedded: { hits: SuluSearchHit[] }
  total: number
  page: number
  limit: number
  pages: number
}

const EMPTY_ARTICLES: ArticlesPage = {
  _embedded: { hits: [] },
  total: 0,
  page: 1,
  limit: 6,
  pages: 0,
}

/** Paginated article listing (newest first). */
export async function getArticles(page = 1, limit = 6): Promise<ArticlesPage> {
  const params = new URLSearchParams({
    page: String(page),
    limit: String(limit),
  })
  try {
    return await suluFetch<ArticlesPage>(`/api/articles?${params}`)
  } catch (err) {
    if (err instanceof SuluUnavailableError) return EMPTY_ARTICLES
    throw err
  }
}

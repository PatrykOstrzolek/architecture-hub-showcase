/**
 * Content shapes and helpers shared by more than one feature. Keep this file
 * limited to genuinely cross-feature items — anything used by exactly one
 * feature belongs in that feature's own `types.ts`.
 */

/**
 * One resolved article item as returned by either the `article_selection`
 * content type (author page) or the `articles` smart_content provider (homepage).
 * Both resolve through the same StructureResolver path, so the shape is identical;
 * `summary` is only present when the field requests it as an extra property.
 */
export interface ArticleSelectionItem {
  id: string
  type: string
  template: string
  content: { title: string; url: string | null; summary?: string | null }
  view: {
    url?: { page?: { uuid: string; path: string }; suffix?: string }
  }
}

/**
 * Build an article's href. A `page_tree_route` URL is NOT in `content.url`
 * (it's null) — it resolves into `view.url` as `{ page.path, suffix }`
 * (verified, see content-types-reference.md).
 */
export function articleHref(item: ArticleSelectionItem): string {
  if (item.content.url) return item.content.url
  const page = item.view?.url?.page
  const suffix = item.view?.url?.suffix
  if (page && suffix) return `${page.path.replace(/\/$/, "")}/${suffix}`
  return "#"
}

/** Contextual navigation passed to ArticleView when reading inside a learning path. */
export interface LearningPathContext {
  title: string
  slug: string
  current: number
  total: number
  prev: string | null
  next: string | null
}

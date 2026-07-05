import type { MetadataRoute } from "next"

import { getArticles, getContent, type ArticlesPage } from "@/lib/sulu"
import type {
  AuthorsListingContent,
  LearningPathsListingContent,
} from "@/components/content/types"
import { SITE_URL } from "@/lib/site"

/**
 * Built entirely from the existing headless delivery client — no new backend
 * endpoint. See ADR-0015 for why this isn't Sulu's own built-in sitemap.
 */
const PAGE_LIMIT = 50

function toEntries({ _embedded, pages }: ArticlesPage, expectedPages: number) {
  // getArticles() swallows backend-unavailable errors and returns an empty,
  // zero-page result instead of throwing (by design, for page-rendering
  // callers) — but for the sitemap that would silently truncate the article
  // list with no signal. Treat a page-count mismatch as a hard failure
  // instead, so a broken crawl surfaces as a build/route error rather than a
  // quietly incomplete sitemap.
  if (pages !== expectedPages) {
    throw new Error(
      `Sulu article listing unavailable while building the sitemap (expected ${expectedPages} pages, got ${pages})`
    )
  }
  return _embedded.hits
    .filter((hit) => hit.url)
    .map((hit) => ({
      url: `${SITE_URL}${hit.url}`,
      lastModified: hit.authoredAt ?? undefined,
    }))
}

async function articleEntries(): Promise<MetadataRoute.Sitemap> {
  const first = await getArticles(1, PAGE_LIMIT)
  const totalPages = first.pages

  const rest = await Promise.all(
    Array.from({ length: Math.max(0, totalPages - 1) }, (_, i) =>
      getArticles(i + 2, PAGE_LIMIT)
    )
  )

  return [first, ...rest].flatMap((result) => toEntries(result, totalPages))
}

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const [authors, learningPaths, articles] = await Promise.all([
    getContent<AuthorsListingContent>("/authors"),
    getContent<LearningPathsListingContent>("/learning-paths"),
    articleEntries(),
  ])

  const authorEntries: MetadataRoute.Sitemap = (authors?.content.authors ?? [])
    .filter((author) => author.content.url)
    .map((author) => ({ url: `${SITE_URL}${author.content.url}` }))

  const learningPathEntries: MetadataRoute.Sitemap = (
    learningPaths?.content.paths ?? []
  )
    .filter((path) => path.content.url)
    .map((path) => ({ url: `${SITE_URL}${path.content.url}` }))

  return [
    { url: SITE_URL },
    { url: `${SITE_URL}/authors` },
    { url: `${SITE_URL}/learning-paths` },
    ...authorEntries,
    ...learningPathEntries,
    ...articles,
  ]
}

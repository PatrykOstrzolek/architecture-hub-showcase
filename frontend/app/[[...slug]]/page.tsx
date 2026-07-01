import type { Metadata } from "next"
import { notFound, redirect } from "next/navigation"

import { getContent, getArticles } from "@/lib/sulu"
import { ArticleView } from "@/components/content/article-view"
import { AuthorView } from "@/components/content/author-view"
import { AuthorsListingView } from "@/components/content/authors-listing-view"
import { ExerciseView } from "@/components/content/exercise-view"
import { HomeView } from "@/components/content/home-view"
import { LearningPathsListingView } from "@/components/content/learning-paths-listing-view"
import { LearningPathView } from "@/components/content/learning-path-view"
import { PageView } from "@/components/content/page-view"
import type {
  ArticleContent,
  AuthorContent,
  AuthorsListingContent,
  ExerciseContent,
  HomeContent,
  LearningPathContent,
  LearningPathContext,
  LearningPathsListingContent,
  PageContent,
} from "@/components/content/types"
import { articleHref } from "@/components/content/types"

/**
 * Headless catch-all. Every public URL maps 1:1 to a Sulu resource locator;
 * `{path}.json` is fetched and dispatched to a view by its `template`. The
 * optional catch-all also matches `/` (the homepage).
 */
type RouteParams = { slug?: string[] }

function toPath(slug?: string[]): string {
  return slug && slug.length > 0 ? `/${slug.join("/")}` : "/"
}

export async function generateMetadata({
  params,
}: {
  params: Promise<RouteParams>
}): Promise<Metadata> {
  const { slug } = await params
  const data = await getContent(toPath(slug))
  if (!data) return {}

  const seo = data.extension?.seo
  const title = seo?.title || (data.content.title as string | undefined)
  const description = seo?.description || undefined
  return {
    title,
    description,
    keywords: seo?.keywords || undefined,
    alternates: seo?.canonicalUrl ? { canonical: seo.canonicalUrl } : undefined,
    robots:
      seo?.noIndex || seo?.noFollow
        ? { index: !seo.noIndex, follow: !seo.noFollow }
        : undefined,
    openGraph: { title: title ?? undefined, description },
  }
}

export default async function Page({
  params,
  searchParams,
}: {
  params: Promise<RouteParams>
  searchParams: Promise<Record<string, string | string[] | undefined>>
}) {
  const [{ slug }, sp] = await Promise.all([params, searchParams])
  const currentPath = toPath(slug)

  const data = await getContent(currentPath)
  if (!data) notFound()

  switch (data.template) {
    case "article": {
      const pathSlug = typeof sp.path === "string" ? sp.path : undefined

      const [lpData, allArticles] = await Promise.all([
        pathSlug
          ? getContent<LearningPathContent>(`/learning-paths/${pathSlug}`)
          : Promise.resolve(null),
        getArticles(1, 4),
      ])

      let learningPath: LearningPathContext | undefined
      if (pathSlug && lpData) {
        const articles = lpData.content.articles ?? []
        const index = articles.findIndex((a) => articleHref(a) === currentPath)
        if (index >= 0) {
          learningPath = {
            title: lpData.content.title,
            slug: pathSlug,
            current: index + 1,
            total: articles.length,
            prev:
              index > 0
                ? `${articleHref(articles[index - 1])}?path=${encodeURIComponent(pathSlug)}`
                : null,
            next:
              index < articles.length - 1
                ? `${articleHref(articles[index + 1])}?path=${encodeURIComponent(pathSlug)}`
                : null,
          }
        }
      }

      const relatedArticles = allArticles._embedded.hits
        .filter((h) => h.url !== currentPath)
        .slice(0, 3)

      return (
        <ArticleView
          content={data.content as unknown as ArticleContent}
          authored={data.authored}
          learningPath={learningPath}
          relatedArticles={relatedArticles}
        />
      )
    }
    case "authors":
      return (
        <AuthorsListingView
          content={data.content as unknown as AuthorsListingContent}
        />
      )
    case "author":
      return <AuthorView content={data.content as unknown as AuthorContent} />
    case "homepage": {
      const page = Math.max(
        1,
        Number(typeof sp.page === "string" ? sp.page : "1") || 1
      )
      const articlesData = await getArticles(page, 6)
      if (page > articlesData.pages && articlesData.pages > 0) {
        redirect(`/?page=${articlesData.pages}`)
      }
      return (
        <HomeView
          content={data.content as unknown as HomeContent}
          articles={articlesData._embedded.hits}
          page={page}
          pages={articlesData.pages}
          total={articlesData.total}
        />
      )
    }
    case "learning-paths":
      return (
        <LearningPathsListingView
          content={data.content as unknown as LearningPathsListingContent}
        />
      )
    case "learning-path": {
      const pathParts = slug ?? []
      const lpSlug = pathParts[pathParts.length - 1] ?? ""
      return (
        <LearningPathView
          content={data.content as unknown as LearningPathContent}
          slug={lpSlug}
        />
      )
    }
    case "exercise": {
      const pathParts = slug ?? []
      const pathSlug =
        typeof sp.path === "string" ? sp.path : pathParts[pathParts.length - 2]
      return (
        <ExerciseView
          content={data.content as unknown as ExerciseContent}
          pathSlug={pathSlug}
        />
      )
    }
    default:
      // `default` page template (simple rich-text body)
      return <PageView content={data.content as unknown as PageContent} />
  }
}

import Link from "next/link"
import { mediaUrl, type SuluSearchHit } from "@/lib/sulu"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { BlockRenderer } from "./block-renderer"
import type { ArticleContent, LearningPathContext } from "./types"
import { SITE_URL } from "@/lib/site"

function formatDate(iso: string | null): string | null {
  if (!iso) return null
  return new Date(iso).toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  })
}

/**
 * Article JSON-LD — see ADR-0015. No image: not part of ArticleContent today.
 * `path` is the resolved page path (from the caller's own routing, not
 * `content.url` — the article template's `page_tree_route` URL never
 * populates `content.url`, only `view.url`, which isn't passed down here).
 */
function articleJsonLd(
  content: ArticleContent,
  authored: string | null,
  path: string
) {
  return {
    "@context": "https://schema.org",
    "@type": "Article",
    headline: content.title,
    description: content.summary || undefined,
    url: `${SITE_URL}${path}`,
    datePublished: authored || undefined,
    keywords: content.tags?.length ? content.tags.join(", ") : undefined,
    author: content.author?.fullName
      ? { "@type": "Person", name: content.author.fullName }
      : undefined,
  }
}

export function ArticleView({
  content,
  path,
  authored,
  learningPath,
  relatedArticles,
}: {
  content: ArticleContent
  path: string
  authored: string | null
  learningPath?: LearningPathContext
  relatedArticles?: SuluSearchHit[]
}) {
  const author = content.author
  const date = formatDate(authored)

  return (
    <article className="mx-auto max-w-3xl px-4 py-10">
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{
          __html: JSON.stringify(
            articleJsonLd(content, authored, path)
          ).replace(/</g, "\\u003c"),
        }}
      />

      {/* Breadcrumb */}
      <div className="mb-10">
        {learningPath ? (
          <div className="space-y-1">
            <Link
              href={`/learning-paths/${learningPath.slug}`}
              className="font-mono text-xs text-muted-foreground transition-colors hover:text-foreground"
            >
              ← {learningPath.title}
            </Link>
            <p className="font-mono text-[10px] text-muted-foreground/60">
              {learningPath.current} of {learningPath.total}
            </p>
          </div>
        ) : (
          <Link
            href="/"
            className="font-mono text-xs text-muted-foreground transition-colors hover:text-foreground"
          >
            ← all articles
          </Link>
        )}
      </div>

      {/* Article header */}
      <header className="mb-10">
        {content.categories?.length > 0 && (
          <p className="mb-3 font-mono text-[10px] tracking-widest text-primary uppercase">
            {content.categories[0].name}
          </p>
        )}

        <h1 className="text-4xl leading-tight font-bold tracking-tight">
          {content.title}
        </h1>

        {content.summary ? (
          <p className="mt-4 text-lg leading-7 text-muted-foreground">
            {content.summary}
          </p>
        ) : null}

        {/* Meta strip */}
        <div className="mt-6 flex flex-wrap items-center gap-x-5 gap-y-3">
          {author ? (
            <div className="flex items-center gap-2">
              <Avatar className="size-6 shrink-0">
                {author.avatar ? (
                  <AvatarImage
                    src={mediaUrl(author.avatar.url)}
                    alt={author.fullName ?? ""}
                  />
                ) : null}
                <AvatarFallback className="text-[10px]">
                  {(author.fullName ?? "?").charAt(0)}
                </AvatarFallback>
              </Avatar>
              <Link
                href={`/authors/${author.firstName}-${author.lastName}`.toLowerCase()}
                className="font-mono text-xs font-medium transition-colors hover:text-primary"
              >
                {author.fullName}
              </Link>
              {author.position ? (
                <span className="font-mono text-xs text-muted-foreground">
                  {author.position}
                </span>
              ) : null}
            </div>
          ) : null}

          {date ? (
            <span className="font-mono text-xs text-muted-foreground">
              {date}
            </span>
          ) : null}

          {content.tags?.length > 0 ? (
            <div className="flex flex-wrap gap-1.5">
              {content.tags.map((tag) => (
                <span
                  key={tag}
                  className="rounded border px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground"
                >
                  #{tag}
                </span>
              ))}
            </div>
          ) : null}
        </div>

        <div className="mt-8 border-b" />
      </header>

      {/* Article body */}
      <BlockRenderer blocks={content.body ?? []} />

      {/* Author bio — only when there is actual bio content to show */}
      {author?.note ? (
        <div className="mt-14 border-t pt-10">
          <p className="mb-5 font-mono text-[10px] tracking-widest text-muted-foreground uppercase">
            Written by
          </p>
          <div className="flex items-start gap-4">
            <Avatar className="size-12 shrink-0">
              {author.avatar ? (
                <AvatarImage
                  src={mediaUrl(author.avatar.url)}
                  alt={author.fullName ?? ""}
                />
              ) : null}
              <AvatarFallback className="text-base">
                {(author.fullName ?? "?").charAt(0)}
              </AvatarFallback>
            </Avatar>
            <div className="space-y-1">
              <Link
                href={`/authors/${author.firstName}-${author.lastName}`.toLowerCase()}
                className="font-medium transition-colors hover:text-primary"
              >
                {author.fullName}
              </Link>
              {author.position ? (
                <p className="font-mono text-xs text-muted-foreground">
                  {author.position}
                </p>
              ) : null}
              {author.note ? (
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                  {author.note}
                </p>
              ) : null}
            </div>
          </div>
        </div>
      ) : null}

      {/* Related articles */}
      {relatedArticles && relatedArticles.length > 0 ? (
        <section className="mt-14 border-t pt-10">
          <p className="mb-2 font-mono text-[10px] tracking-widest text-muted-foreground uppercase">
            Continue reading
          </p>
          <ul className="divide-y">
            {relatedArticles.map((item, i) => (
              <li key={item.url ?? i}>
                <Link
                  href={item.url}
                  className="group flex items-start justify-between gap-6 py-4"
                >
                  <div className="min-w-0">
                    <h3 className="leading-snug font-medium transition-colors group-hover:text-primary">
                      {item.title}
                    </h3>
                    {Array.isArray(item.content) && item.content.length > 0 ? (
                      <p className="mt-1 line-clamp-1 text-sm text-muted-foreground">
                        {String(item.content[0])}
                      </p>
                    ) : null}
                  </div>
                  <span className="mt-0.5 shrink-0 font-mono text-sm text-muted-foreground transition-colors group-hover:text-primary">
                    →
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {/* Learning path prev/next navigation */}
      {learningPath ? (
        <nav className="mt-12 flex items-center justify-between gap-4 border-t pt-8">
          {learningPath.prev ? (
            <Link
              href={learningPath.prev}
              className="font-mono text-xs text-muted-foreground transition-colors hover:text-foreground"
            >
              ← Previous
            </Link>
          ) : (
            <span />
          )}
          <span className="font-mono text-xs text-muted-foreground">
            {learningPath.current} / {learningPath.total}
          </span>
          {learningPath.next ? (
            <Link
              href={learningPath.next}
              className="font-mono text-xs text-muted-foreground transition-colors hover:text-foreground"
            >
              Next →
            </Link>
          ) : (
            <span />
          )}
        </nav>
      ) : null}
    </article>
  )
}

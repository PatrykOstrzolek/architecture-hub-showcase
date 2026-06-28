import Link from "next/link"
import type { LearningPathContent } from "./types"
import { articleHref } from "./types"

export function LearningPathView({
  content,
  slug,
}: {
  content: LearningPathContent
  slug: string
}) {
  const articles = content.articles ?? []

  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <header className="mb-10 space-y-3">
        <p className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
          Learning Path
        </p>
        <h1 className="text-4xl font-bold tracking-tight">{content.title}</h1>
        {content.description ? (
          <p className="text-lg text-muted-foreground">{content.description}</p>
        ) : null}
        <p className="text-sm text-muted-foreground">
          {articles.length} articles
        </p>
      </header>

      <ol className="space-y-4">
        {articles.map((article, i) => {
          const href = `${articleHref(article)}?path=${encodeURIComponent(slug)}`
          const title = article.content.title
          const summary = article.content.summary

          return (
            <li key={article.id} className="flex gap-4">
              <span className="mt-1 w-6 shrink-0 text-right font-mono text-sm text-muted-foreground">
                {i + 1}.
              </span>
              <Link
                href={href}
                className="group flex-1 rounded-lg border p-4 transition-colors hover:bg-accent"
              >
                <p className="font-semibold group-hover:underline">{title}</p>
                {summary ? (
                  <p className="mt-1 text-sm text-muted-foreground">
                    {summary}
                  </p>
                ) : null}
              </Link>
            </li>
          )
        })}
      </ol>
    </div>
  )
}

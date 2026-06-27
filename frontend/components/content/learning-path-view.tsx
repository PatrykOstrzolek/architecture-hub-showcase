import Link from "next/link";
import type { LearningPathContent } from "./types";
import { articleHref } from "./types";

export function LearningPathView({ content, slug }: { content: LearningPathContent; slug: string }) {
  const articles = content.articles ?? [];

  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <header className="mb-10 space-y-3">
        <p className="text-muted-foreground text-sm font-medium uppercase tracking-wide">
          Learning Path
        </p>
        <h1 className="text-4xl font-bold tracking-tight">{content.title}</h1>
        {content.description ? (
          <p className="text-muted-foreground text-lg">{content.description}</p>
        ) : null}
        <p className="text-muted-foreground text-sm">{articles.length} articles</p>
      </header>

      <ol className="space-y-4">
        {articles.map((article, i) => {
          const href = `${articleHref(article)}?path=${encodeURIComponent(slug)}`;
          const title = article.content.title;
          const summary = article.content.summary;

          return (
            <li key={article.id} className="flex gap-4">
              <span className="text-muted-foreground mt-1 w-6 shrink-0 text-right text-sm font-mono">
                {i + 1}.
              </span>
              <Link
                href={href}
                className="group flex-1 rounded-lg border p-4 transition-colors hover:bg-accent"
              >
                <p className="font-semibold group-hover:underline">{title}</p>
                {summary ? (
                  <p className="text-muted-foreground mt-1 text-sm">{summary}</p>
                ) : null}
              </Link>
            </li>
          );
        })}
      </ol>
    </div>
  );
}

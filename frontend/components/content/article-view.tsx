import Link from "next/link";
import { mediaUrl, type SuluSearchHit } from "@/lib/sulu";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { BlockRenderer } from "./block-renderer";
import type { ArticleContent, LearningPathContext } from "./types";

function formatDate(iso: string | null): string | null {
  if (!iso) return null;
  return new Date(iso).toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

export function ArticleView({
  content,
  authored,
  learningPath,
  relatedArticles,
}: {
  content: ArticleContent;
  authored: string | null;
  learningPath?: LearningPathContext;
  relatedArticles?: SuluSearchHit[];
}) {
  const author = content.author;
  const date = formatDate(authored);

  return (
    <article className="mx-auto max-w-3xl px-4 py-12">
      {/* Breadcrumb / back navigation */}
      <div className="mb-8">
        {learningPath ? (
          <>
            <Link
              href={`/learning-paths/${learningPath.slug}`}
              className="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
            >
              ← {learningPath.title}
            </Link>
            <p className="mt-1 text-xs text-muted-foreground">
              Article {learningPath.current} of {learningPath.total}
            </p>
          </>
        ) : (
          <Link
            href="/"
            className="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
          >
            ← All articles
          </Link>
        )}
      </div>

      {/* Article header */}
      <header className="mb-10 space-y-5">
        <h1 className="text-4xl font-bold leading-tight tracking-tight">{content.title}</h1>

        {content.summary ? (
          <p className="text-xl leading-relaxed text-muted-foreground">{content.summary}</p>
        ) : null}

        {(content.categories?.length > 0 || content.tags?.length > 0) ? (
          <div className="flex flex-wrap gap-2">
            {content.categories?.map((cat) => (
              <Badge key={cat.id} variant="default">{cat.name}</Badge>
            ))}
            {content.tags?.map((tag) => (
              <Badge key={tag} variant="outline">{tag}</Badge>
            ))}
          </div>
        ) : null}

        {author ? (
          <div className="flex items-center gap-3">
            <Avatar className="size-9">
              {author.avatar ? (
                <AvatarImage src={mediaUrl(author.avatar.url)} alt={author.fullName ?? ""} />
              ) : null}
              <AvatarFallback className="text-xs">{(author.fullName ?? "?").charAt(0)}</AvatarFallback>
            </Avatar>
            <div className="text-sm">
              <Link
                href={`/authors/${author.firstName}-${author.lastName}`.toLowerCase()}
                className="font-medium hover:underline"
              >
                {author.fullName}
              </Link>
              <div className="text-muted-foreground">
                {[author.position, date].filter(Boolean).join(" · ")}
              </div>
            </div>
          </div>
        ) : date ? (
          <p className="text-sm text-muted-foreground">{date}</p>
        ) : null}

        <div className="border-b" />
      </header>

      {/* Article body */}
      <BlockRenderer blocks={content.body ?? []} />

      {/* Author bio */}
      {author ? (
        <div className="mt-14 border-t pt-10">
          <div className="flex items-start gap-4">
            <Avatar className="size-14 shrink-0">
              {author.avatar ? (
                <AvatarImage src={mediaUrl(author.avatar.url)} alt={author.fullName ?? ""} />
              ) : null}
              <AvatarFallback className="text-lg">{(author.fullName ?? "?").charAt(0)}</AvatarFallback>
            </Avatar>
            <div className="space-y-1">
              <div className="font-semibold">{author.fullName}</div>
              {author.position ? (
                <div className="text-sm text-muted-foreground">{author.position}</div>
              ) : null}
              {author.note ? (
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{author.note}</p>
              ) : null}
            </div>
          </div>
        </div>
      ) : null}

      {/* Related articles */}
      {relatedArticles && relatedArticles.length > 0 ? (
        <section className="mt-14 border-t pt-10">
          <h2 className="mb-6 text-xl font-semibold">Keep reading</h2>
          <ul className="space-y-4">
            {relatedArticles.map((item, i) => (
              <li key={item.url ?? i}>
                <Link href={item.url} className="group block">
                  <Card className="transition-shadow group-hover:shadow-md group-hover:ring-1 group-hover:ring-foreground/20">
                    <CardContent className="py-4">
                      <h3 className="font-semibold group-hover:underline">{item.title}</h3>
                      {Array.isArray(item.content) && item.content.length > 0 ? (
                        <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                          {String(item.content[0])}
                        </p>
                      ) : null}
                    </CardContent>
                  </Card>
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
              className="flex items-center gap-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
              ← Previous
            </Link>
          ) : (
            <span />
          )}
          <Link
            href={`/learning-paths/${learningPath.slug}`}
            className="text-sm text-muted-foreground transition-colors hover:text-foreground"
          >
            {learningPath.current} / {learningPath.total}
          </Link>
          {learningPath.next ? (
            <Link
              href={learningPath.next}
              className="flex items-center gap-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
              Next →
            </Link>
          ) : (
            <span />
          )}
        </nav>
      ) : null}
    </article>
  );
}

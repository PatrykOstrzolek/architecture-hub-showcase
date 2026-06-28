import Link from "next/link"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import type { SuluSearchHit } from "@/lib/sulu"
import type { HomeContent } from "./types"

interface HomeViewProps {
  content: HomeContent
  articles: SuluSearchHit[]
  page: number
  pages: number
  total: number
}

export function HomeView({
  content,
  articles,
  page,
  pages,
  total,
}: HomeViewProps) {
  return (
    <div className="px-4 py-12">
      <header className="mb-10">
        <h1 className="text-4xl font-bold tracking-tight">{content.title}</h1>
        <p className="mt-3 text-lg text-muted-foreground">
          Curated deep-dives on software architecture, distributed systems and
          scalable backend design.
        </p>
      </header>

      <section>
        <div className="mb-5 flex items-baseline justify-between">
          <h2 className="text-xl font-semibold">Latest articles</h2>
          <span className="text-sm text-muted-foreground">
            {total} articles
          </span>
        </div>

        {articles.length === 0 ? (
          <p className="text-sm text-muted-foreground">No articles yet.</p>
        ) : (
          <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {articles.map((item, i) => (
              <li key={item.url ?? i}>
                <Link href={item.url} className="group block h-full">
                  <Card className="h-full transition-shadow group-hover:shadow-md group-hover:ring-foreground/20">
                    <CardHeader>
                      <CardTitle className="text-base/snug group-hover:underline">
                        {item.title}
                      </CardTitle>
                    </CardHeader>
                    {item.content.length > 0 ? (
                      <CardContent className="line-clamp-3 text-muted-foreground">
                        {String(item.content[0])}
                      </CardContent>
                    ) : null}
                  </Card>
                </Link>
              </li>
            ))}
          </ul>
        )}

        {pages > 1 && (
          <nav
            aria-label="Pagination"
            className="mt-10 flex items-center justify-center gap-1"
          >
            <Button
              variant="outline"
              size="sm"
              disabled={page <= 1}
              asChild={page > 1}
            >
              {page > 1 ? (
                <Link href={`/?page=${page - 1}`}>← Prev</Link>
              ) : (
                <span>← Prev</span>
              )}
            </Button>

            {Array.from({ length: pages }, (_, i) => i + 1).map((p) => (
              <Button
                key={p}
                variant={p === page ? "default" : "outline"}
                size="sm"
                asChild={p !== page}
              >
                {p !== page ? (
                  <Link href={`/?page=${p}`}>{p}</Link>
                ) : (
                  <span>{p}</span>
                )}
              </Button>
            ))}

            <Button
              variant="outline"
              size="sm"
              disabled={page >= pages}
              asChild={page < pages}
            >
              {page < pages ? (
                <Link href={`/?page=${page + 1}`}>Next →</Link>
              ) : (
                <span>Next →</span>
              )}
            </Button>
          </nav>
        )}
      </section>
    </div>
  )
}

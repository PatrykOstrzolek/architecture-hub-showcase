import Link from "next/link"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import type { SuluSearchHit } from "@/lib/sulu"
import type { HomeContent } from "./types"

function ArchDiagram() {
  return (
    <svg
      viewBox="0 0 280 130"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
      className="w-full"
    >
      <rect
        x="1"
        y="10"
        width="72"
        height="28"
        rx="1.5"
        stroke="currentColor"
        strokeWidth="0.75"
        opacity="0.28"
      />
      <text
        x="37"
        y="27"
        textAnchor="middle"
        fontSize="7.5"
        fill="currentColor"
        opacity="0.4"
        fontFamily="monospace"
      >
        Client
      </text>

      <rect
        x="101"
        y="10"
        width="78"
        height="28"
        rx="1.5"
        stroke="currentColor"
        strokeWidth="0.75"
        opacity="0.28"
      />
      <text
        x="140"
        y="27"
        textAnchor="middle"
        fontSize="7.5"
        fill="currentColor"
        opacity="0.4"
        fontFamily="monospace"
      >
        API Gateway
      </text>

      <rect
        x="203"
        y="10"
        width="76"
        height="28"
        rx="1.5"
        stroke="currentColor"
        strokeWidth="0.75"
        opacity="0.28"
      />
      <text
        x="241"
        y="27"
        textAnchor="middle"
        fontSize="7.5"
        fill="currentColor"
        opacity="0.4"
        fontFamily="monospace"
      >
        Service
      </text>

      <rect
        x="101"
        y="90"
        width="78"
        height="28"
        rx="1.5"
        stroke="currentColor"
        strokeWidth="0.75"
        opacity="0.28"
      />
      <text
        x="140"
        y="107"
        textAnchor="middle"
        fontSize="7.5"
        fill="currentColor"
        opacity="0.4"
        fontFamily="monospace"
      >
        Database
      </text>

      <line
        x1="73"
        y1="24"
        x2="97"
        y2="24"
        stroke="currentColor"
        strokeWidth="0.75"
        opacity="0.35"
      />
      <polygon points="95,21 101,24 95,27" fill="currentColor" opacity="0.35" />

      <line
        x1="179"
        y1="24"
        x2="199"
        y2="24"
        stroke="currentColor"
        strokeWidth="0.75"
        opacity="0.35"
      />
      <polygon
        points="197,21 203,24 197,27"
        fill="currentColor"
        opacity="0.35"
      />

      <line
        x1="140"
        y1="38"
        x2="140"
        y2="87"
        stroke="currentColor"
        strokeWidth="0.75"
        opacity="0.35"
      />
      <polygon
        points="137,85 140,91 143,85"
        fill="currentColor"
        opacity="0.35"
      />
    </svg>
  )
}

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
  const featured = articles[0]
  const sidebar = articles.slice(1, 4)
  const overflow = articles.slice(4)

  return (
    <div className="px-4 py-10">
      <header className="mb-12 flex items-start justify-between gap-8">
        <div className="min-w-0">
          <p className="mb-2 font-mono text-[11px] tracking-widest text-primary uppercase">
            Software Architecture
          </p>
          <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">
            {content.title}
          </h1>
          <p className="mt-3 font-mono text-sm leading-relaxed text-muted-foreground">
            Curated deep-dives on distributed systems,
            <br className="hidden sm:block" />
            scalable backend design and architecture patterns.
          </p>
        </div>
        <div className="hidden w-52 shrink-0 text-foreground md:block">
          <ArchDiagram />
        </div>
      </header>

      <section>
        <div className="mb-5 flex items-baseline justify-between">
          <h2 className="font-mono text-xs tracking-widest text-muted-foreground uppercase">
            Latest articles
          </h2>
          <span className="font-mono text-xs text-muted-foreground">
            {total} total
          </span>
        </div>

        {articles.length === 0 ? (
          <p className="font-mono text-sm text-muted-foreground">
            No articles published yet.
          </p>
        ) : (
          <div className="space-y-4">
            <div className="grid gap-4 lg:grid-cols-3">
              <Link href={featured.url} className="group lg:col-span-2">
                <Card className="h-full transition-all duration-150 group-hover:shadow-md group-hover:ring-1 group-hover:ring-primary/20">
                  <CardHeader className="pb-3">
                    <p className="font-mono text-[10px] tracking-widest text-primary uppercase">
                      Featured
                    </p>
                    <CardTitle className="mt-1 text-lg/snug transition-colors group-hover:text-primary">
                      {featured.title}
                    </CardTitle>
                  </CardHeader>
                  {featured.content.length > 0 && (
                    <CardContent className="line-clamp-4 text-sm text-muted-foreground">
                      {String(featured.content[0])}
                    </CardContent>
                  )}
                </Card>
              </Link>

              {sidebar.length > 0 && (
                <div className="flex flex-col gap-3">
                  {sidebar.map((item, i) => (
                    <Link
                      key={item.url ?? i}
                      href={item.url}
                      className="group flex-1"
                    >
                      <Card className="h-full transition-all duration-150 group-hover:shadow-sm group-hover:ring-1 group-hover:ring-primary/20">
                        <CardHeader className="py-3">
                          <CardTitle className="text-sm/snug transition-colors group-hover:text-primary">
                            {item.title}
                          </CardTitle>
                        </CardHeader>
                      </Card>
                    </Link>
                  ))}
                </div>
              )}
            </div>

            {overflow.length > 0 && (
              <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {overflow.map((item, i) => (
                  <li key={item.url ?? i}>
                    <Link href={item.url} className="group block h-full">
                      <Card className="h-full transition-all duration-150 group-hover:shadow-sm group-hover:ring-1 group-hover:ring-primary/20">
                        <CardHeader className="pb-2">
                          <CardTitle className="text-sm/snug transition-colors group-hover:text-primary">
                            {item.title}
                          </CardTitle>
                        </CardHeader>
                        {item.content.length > 0 && (
                          <CardContent className="line-clamp-2 text-xs text-muted-foreground">
                            {String(item.content[0])}
                          </CardContent>
                        )}
                      </Card>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </div>
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

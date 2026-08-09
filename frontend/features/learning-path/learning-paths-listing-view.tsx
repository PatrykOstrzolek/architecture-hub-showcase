import Link from "next/link"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent } from "@/components/ui/card"
import type { LearningPathsListingContent } from "./types"

export function LearningPathsListingView({
  content,
}: {
  content: LearningPathsListingContent
}) {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <h1 className="mb-10 text-3xl font-bold tracking-tight">
        {content.title}
      </h1>

      {content.paths.length === 0 ? (
        <p className="text-sm text-muted-foreground">No learning paths yet.</p>
      ) : (
        <ul className="space-y-4">
          {content.paths.map((path) => {
            const count = path.content.articles?.length ?? 0
            return (
              <li key={path.id}>
                <Link href={path.content.url ?? "#"} className="group block">
                  <Card className="transition-shadow group-hover:shadow-md group-hover:ring-1 group-hover:ring-foreground/20">
                    <CardContent className="py-5">
                      <div className="flex items-start justify-between gap-4">
                        <h2 className="font-semibold group-hover:underline">
                          {path.content.title}
                        </h2>
                        {count > 0 ? (
                          <Badge variant="secondary" className="shrink-0">
                            {count} {count === 1 ? "article" : "articles"}
                          </Badge>
                        ) : null}
                      </div>
                      {path.content.description ? (
                        <p className="mt-2 line-clamp-2 text-sm text-muted-foreground">
                          {path.content.description}
                        </p>
                      ) : null}
                    </CardContent>
                  </Card>
                </Link>
              </li>
            )
          })}
        </ul>
      )}
    </div>
  )
}

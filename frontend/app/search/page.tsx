import type { Metadata } from "next"
import Link from "next/link"

import { search, searchByTaxonomy, type SuluSearchHit } from "@/lib/sulu"

export async function generateMetadata({
  searchParams,
}: {
  searchParams: Promise<{ q?: string; category?: string; tag?: string; label?: string }>
}): Promise<Metadata> {
  const { q, category, tag, label } = await searchParams
  if (category) return { title: label ?? category }
  if (tag) return { title: label ?? tag }
  if (q?.trim()) return { title: `Results for "${q.trim()}"` }
  return { title: "Search" }
}

export default async function SearchPage({
  searchParams,
}: {
  searchParams: Promise<{
    q?: string
    category?: string
    tag?: string
    label?: string
  }>
}) {
  const { q, category, tag, label } = await searchParams

  let hits: SuluSearchHit[] = []
  let heading = "Search"
  let subheading: string | null = null

  if (category) {
    hits = await searchByTaxonomy({ category })
    heading = label ?? category
    subheading = "Category"
  } else if (tag) {
    hits = await searchByTaxonomy({ tag })
    heading = label ?? tag
    subheading = "Tag"
  } else if (q?.trim()) {
    hits = await search(q.trim())
    heading = `Results for "${q.trim()}"`
  }

  const hasQuery = !!(q?.trim() || category || tag)

  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <header className="mb-8">
        {subheading && (
          <p className="mb-1 text-sm font-medium tracking-wide text-muted-foreground uppercase">
            {subheading}
          </p>
        )}
        <h1 className="text-3xl font-bold tracking-tight">{heading}</h1>
      </header>

      {hasQuery && hits.length === 0 && (
        <p className="text-muted-foreground">No articles found.</p>
      )}

      {!hasQuery && (
        <p className="text-muted-foreground">
          Enter a keyword in the search box above.
        </p>
      )}

      <ul className="space-y-8">
        {hits.map((hit, i) => (
          <li key={hit.url ?? i}>
            <Link href={hit.url} className="group block space-y-1">
              <h2 className="text-xl font-semibold transition-colors group-hover:text-primary">
                {hit.title}
              </h2>
              {Array.isArray(hit.content) && hit.content.length > 0 && (
                <p className="line-clamp-2 text-sm text-muted-foreground">
                  {String(hit.content[0])}
                </p>
              )}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  )
}

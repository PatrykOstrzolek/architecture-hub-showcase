"use client"

import Link from "next/link"
import { useRouter, useSearchParams } from "next/navigation"
import { useEffect, useRef, useState } from "react"

import { Input } from "@/components/ui/input"
import type { SuluSearchHit, SuluTaxonomySuggestions } from "@/lib/sulu"

const MIN_CHARS = 2
const DEBOUNCE_MS = 300

export function SearchForm() {
  const router = useRouter()
  const params = useSearchParams()

  const [value, setValue] = useState(params.get("q") ?? "")
  const [articles, setArticles] = useState<SuluSearchHit[]>([])
  const [authors, setAuthors] = useState<SuluSearchHit[]>([])
  const [taxonomy, setTaxonomy] = useState<SuluTaxonomySuggestions>({
    categories: [],
    tags: [],
  })
  const [open, setOpen] = useState(false)
  const containerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const trimmed = value.trim()
    if (trimmed.length < MIN_CHARS) return

    const timer = setTimeout(async () => {
      const q = encodeURIComponent(trimmed)
      const [searchRes, taxonomyRes] = await Promise.all([
        fetch(`/api/search?q=${q}`)
          .then((r) => r.json())
          .catch(() => ({ _embedded: { hits: [] } })),
        fetch(`/api/taxonomy?q=${q}`)
          .then((r) => r.json())
          .catch(() => ({ categories: [], tags: [] })),
      ])

      const allHits: SuluSearchHit[] = searchRes._embedded?.hits ?? []
      const seen = new Set<string>()
      const deduped = allHits.filter(
        (h) => h.url && !seen.has(h.url) && seen.add(h.url)
      )
      const articleHits = deduped
        .filter((h) => h.resourceKey === "articles")
        .slice(0, 4)
      const authorHits = deduped
        .filter(
          (h) => h.resourceKey === "pages" && h.url?.startsWith("/authors/")
        )
        .slice(0, 3)
      const suggestions: SuluTaxonomySuggestions = {
        categories: taxonomyRes.categories?.slice(0, 3) ?? [],
        tags: taxonomyRes.tags?.slice(0, 3) ?? [],
      }

      setArticles(articleHits)
      setAuthors(authorHits)
      setTaxonomy(suggestions)
      setOpen(
        articleHits.length > 0 ||
          authorHits.length > 0 ||
          suggestions.categories.length > 0 ||
          suggestions.tags.length > 0
      )
    }, DEBOUNCE_MS)

    return () => clearTimeout(timer)
  }, [value])

  useEffect(() => {
    function onMouseDown(e: MouseEvent) {
      if (
        containerRef.current &&
        !containerRef.current.contains(e.target as Node)
      ) {
        setOpen(false)
      }
    }
    document.addEventListener("mousedown", onMouseDown)
    return () => document.removeEventListener("mousedown", onMouseDown)
  }, [])

  function close() {
    setValue("")
    setOpen(false)
  }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const q = value.trim()
    if (!q) return
    setOpen(false)
    router.push(`/search?q=${encodeURIComponent(q)}`)
  }

  const hasResults =
    articles.length > 0 ||
    authors.length > 0 ||
    taxonomy.categories.length > 0 ||
    taxonomy.tags.length > 0

  return (
    <div ref={containerRef} className="relative">
      <form onSubmit={handleSubmit}>
        <Input
          name="q"
          type="search"
          placeholder="Search…"
          value={value}
          onChange={(e) => {
            const next = e.target.value
            setValue(next)
            if (next.trim().length < MIN_CHARS) {
              setArticles([])
              setAuthors([])
              setTaxonomy({ categories: [], tags: [] })
              setOpen(false)
            }
          }}
          onFocus={() => hasResults && setOpen(true)}
          className="h-8 w-44 text-sm"
          aria-label="Search"
          autoComplete="off"
        />
      </form>

      {open && hasResults && (
        <div className="absolute top-full left-0 z-50 mt-1 w-80 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md">
          {articles.length > 0 && (
            <section>
              <p className="px-3 py-1.5 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                Articles
              </p>
              {articles.map((hit) => (
                <Link
                  key={hit.url}
                  href={hit.url}
                  onClick={close}
                  className="flex flex-col px-3 py-2 text-sm transition-colors hover:bg-accent"
                >
                  <span className="leading-snug font-medium">{hit.title}</span>
                </Link>
              ))}
            </section>
          )}

          {authors.length > 0 && (
            <section className={articles.length > 0 ? "border-t" : ""}>
              <p className="px-3 py-1.5 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                Authors
              </p>
              {authors.map((hit) => (
                <Link
                  key={hit.url}
                  href={hit.url}
                  onClick={close}
                  className="flex items-center gap-2 px-3 py-2 text-sm transition-colors hover:bg-accent"
                >
                  <span className="rounded-full bg-muted px-1.5 py-0.5 text-xs">
                    @
                  </span>
                  <span className="leading-snug font-medium">{hit.title}</span>
                </Link>
              ))}
            </section>
          )}

          {taxonomy.categories.length > 0 && (
            <section
              className={
                articles.length > 0 || authors.length > 0 ? "border-t" : ""
              }
            >
              <p className="px-3 py-1.5 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                Categories
              </p>
              {taxonomy.categories.map((cat) => (
                <Link
                  key={cat.id}
                  href={`/search?category=${cat.key}&label=${encodeURIComponent(cat.name)}`}
                  onClick={close}
                  className="flex items-center gap-2 px-3 py-2 text-sm transition-colors hover:bg-accent"
                >
                  <span className="rounded bg-primary/10 px-1.5 py-0.5 text-xs text-primary">
                    cat
                  </span>
                  <span>{cat.name}</span>
                </Link>
              ))}
            </section>
          )}

          {taxonomy.tags.length > 0 && (
            <section
              className={
                articles.length > 0 ||
                authors.length > 0 ||
                taxonomy.categories.length > 0
                  ? "border-t"
                  : ""
              }
            >
              <p className="px-3 py-1.5 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                Tags
              </p>
              {taxonomy.tags.map((tag) => (
                <Link
                  key={tag.id}
                  href={`/search?tag=${encodeURIComponent(tag.name)}&label=${encodeURIComponent(tag.name)}`}
                  onClick={close}
                  className="flex items-center gap-2 px-3 py-2 text-sm transition-colors hover:bg-accent"
                >
                  <span className="rounded border px-1.5 py-0.5 text-xs text-muted-foreground">
                    #
                  </span>
                  <span>{tag.name}</span>
                </Link>
              ))}
            </section>
          )}

          <div className="border-t">
            <button
              type="button"
              onClick={() => {
                setOpen(false)
                router.push(`/search?q=${encodeURIComponent(value.trim())}`)
              }}
              className="w-full px-3 py-2 text-left text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            >
              See all results for &ldquo;{value.trim()}&rdquo; →
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

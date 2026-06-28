import Link from "next/link"
import { Suspense } from "react"

import { ModeToggle } from "@/components/mode-toggle"
import { SearchForm } from "@/components/search-form"
import { getNavigation } from "@/lib/sulu"

const FALLBACK_NAV = [
  { href: "/", label: "Home" },
  { href: "/learning-paths", label: "Learning Paths" },
  { href: "/authors", label: "Authors" },
]

export async function SiteHeader() {
  let navItems = FALLBACK_NAV
  try {
    const items = await getNavigation("main", { depth: 2, flat: true })
    if (items.length > 0) {
      navItems = items.map((item) => ({ href: item.url, label: item.title }))
    }
  } catch {
    // backend unavailable — fall back to static nav
  }

  return (
    <header className="sticky top-0 z-40 border-b bg-background/90 backdrop-blur supports-[backdrop-filter]:bg-background/75">
      <div className="mx-auto flex h-12 max-w-5xl items-center justify-between px-4">
        <Link
          href="/"
          className="font-mono text-sm font-bold tracking-tight transition-colors hover:text-primary"
        >
          arch<span className="text-primary">.</span>hub
        </Link>

        <div className="flex items-center gap-2">
          <Suspense>
            <SearchForm />
          </Suspense>

          <nav className="flex items-center gap-0.5">
            {navItems.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className="rounded px-2.5 py-1.5 font-mono text-xs text-muted-foreground transition-colors hover:text-foreground"
              >
                {item.label}
              </Link>
            ))}
            <ModeToggle />
          </nav>
        </div>
      </div>
    </header>
  )
}

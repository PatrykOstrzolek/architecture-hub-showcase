import { SiteNav } from "@/components/site-nav"
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
      <SiteNav navItems={navItems} />
    </header>
  )
}

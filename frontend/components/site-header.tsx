import Link from "next/link";
import { Suspense } from "react";

import { ModeToggle } from "@/components/mode-toggle";
import { SearchForm } from "@/components/search-form";
import { getNavigation } from "@/lib/sulu";

const FALLBACK_NAV = [
  { href: "/", label: "Home" },
  { href: "/learning-paths", label: "Learning Paths" },
  { href: "/authors", label: "Authors" },
];

export async function SiteHeader() {
  let navItems = FALLBACK_NAV;
  try {
    const items = await getNavigation("main", { depth: 2, flat: true });
    if (items.length > 0) {
      navItems = items.map((item) => ({ href: item.url, label: item.title }));
    }
  } catch {
    // backend unavailable — fall back to static nav
  }

  return (
    <header className="bg-background/80 supports-[backdrop-filter]:bg-background/60 sticky top-0 z-40 border-b backdrop-blur">
      <div className="mx-auto flex h-14 max-w-5xl items-center justify-between px-4">
        <Link href="/" className="font-heading text-base font-semibold tracking-tight">
          Architecture<span className="text-primary">Hub</span>
        </Link>

        <div className="flex items-center gap-2">
          <Suspense>
            <SearchForm />
          </Suspense>

          <nav className="flex items-center gap-1">
            {navItems.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className="text-muted-foreground hover:text-foreground rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
              >
                {item.label}
              </Link>
            ))}
            <ModeToggle />
          </nav>
        </div>
      </div>
    </header>
  );
}

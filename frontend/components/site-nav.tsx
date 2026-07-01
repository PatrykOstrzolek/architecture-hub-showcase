"use client"

import { List, X } from "@phosphor-icons/react"
import Link from "next/link"
import { Suspense, useEffect, useState } from "react"

import { ModeToggle } from "@/components/mode-toggle"
import { SearchForm } from "@/components/search-form"
import { Button } from "@/components/ui/button"

type NavItem = { href: string; label: string }

const MOBILE_PANEL_ID = "mobile-nav-panel"

export function SiteNav({ navItems }: { navItems: NavItem[] }) {
  const [open, setOpen] = useState(false)

  useEffect(() => {
    if (!open) return
    function onKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape") setOpen(false)
    }
    document.addEventListener("keydown", onKeyDown)
    return () => document.removeEventListener("keydown", onKeyDown)
  }, [open])

  return (
    <div className="mx-auto max-w-5xl px-4">
      <div className="flex h-12 items-center justify-between gap-2">
        <Link
          href="/"
          onClick={() => setOpen(false)}
          className="font-mono text-sm font-bold tracking-tight transition-colors hover:text-primary"
        >
          arch<span className="text-primary">.</span>hub
        </Link>

        <div className="flex flex-1 items-center justify-end gap-2">
          <div className="hidden flex-1 items-center justify-end gap-2 md:flex">
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
            </nav>
          </div>

          <ModeToggle />

          <Button
            variant="ghost"
            size="icon"
            className="md:hidden"
            aria-label={open ? "Close menu" : "Open menu"}
            aria-expanded={open}
            aria-controls={MOBILE_PANEL_ID}
            onClick={() => setOpen((v) => !v)}
          >
            {open ? (
              <X weight="bold" className="size-4" />
            ) : (
              <List weight="bold" className="size-4" />
            )}
          </Button>
        </div>
      </div>

      {open && (
        <div id={MOBILE_PANEL_ID} className="space-y-3 border-t py-4 md:hidden">
          <Suspense>
            <SearchForm />
          </Suspense>

          <nav className="flex flex-col">
            {navItems.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                onClick={() => setOpen(false)}
                className="rounded px-2 py-2 font-mono text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
              >
                {item.label}
              </Link>
            ))}
          </nav>
        </div>
      )}
    </div>
  )
}

export function SiteFooter() {
  return (
    <footer className="border-t">
      <div className="mx-auto flex max-w-5xl flex-col items-center justify-between gap-2 px-4 py-5 sm:flex-row">
        <p className="font-mono text-xs text-muted-foreground">
          © {new Date().getFullYear()} arch.hub
        </p>
        <p className="font-mono text-xs text-muted-foreground">
          Sulu CMS · Next.js
        </p>
      </div>
    </footer>
  )
}

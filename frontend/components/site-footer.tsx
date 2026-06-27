export function SiteFooter() {
  return (
    <footer className="border-t">
      <div className="text-muted-foreground mx-auto flex max-w-5xl flex-col items-center justify-between gap-2 px-4 py-6 text-sm sm:flex-row">
        <p>© {new Date().getFullYear()} Architecture Hub</p>
        <p className="font-heading text-xs">Sulu (headless) · Next.js</p>
      </div>
    </footer>
  );
}

import Link from "next/link"

export default function NotFound() {
  return (
    <div className="flex flex-col items-center justify-center py-32 text-center">
      <p className="text-sm font-semibold tracking-widest text-muted-foreground uppercase">
        404
      </p>
      <p className="mt-4 text-4xl font-bold tracking-tight">Page not found</p>
      <p className="mt-4 max-w-md text-muted-foreground">
        This page doesn&apos;t exist or the content is not yet published.
      </p>
      <Link
        href="/"
        className="mt-8 rounded-md bg-primary px-5 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
      >
        Back to home
      </Link>
    </div>
  )
}

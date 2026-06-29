"use client"

export default function Error({
  reset,
}: {
  error: Error & { digest?: string }
  reset: () => void
}) {
  return (
    <div className="flex flex-col items-center justify-center py-32 text-center">
      <p className="text-4xl font-bold tracking-tight">Something went wrong</p>
      <p className="mt-4 max-w-md text-muted-foreground">
        We couldn&apos;t load this page. The content service may be temporarily
        unavailable.
      </p>
      <button
        onClick={reset}
        className="mt-8 rounded-md bg-primary px-5 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
      >
        Try again
      </button>
    </div>
  )
}

import { draftMode } from "next/headers"

/** Shown site-wide while Next.js Draft Mode is active — see ADR-0013. */
export async function DraftModeBanner() {
  const { isEnabled } = await draftMode()
  if (!isEnabled) return null

  return (
    <div className="bg-amber-500 px-4 py-2 text-center font-mono text-xs text-black">
      Draft preview — showing unpublished content.{" "}
      {/* Hard navigation on purpose: /api/preview/disable is a Route Handler, not
          a page — next/link's soft client-side navigation can serve a cached RSC
          payload from while draft mode was still on instead of actually leaving it. */}
      {/* eslint-disable-next-line @next/next/no-html-link-for-pages */}
      <a href="/api/preview/disable" className="underline underline-offset-2">
        Exit preview
      </a>
    </div>
  )
}

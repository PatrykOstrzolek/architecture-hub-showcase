import Link from "next/link"
import { draftMode } from "next/headers"

/** Shown site-wide while Next.js Draft Mode is active — see ADR-0013. */
export async function DraftModeBanner() {
  const { isEnabled } = await draftMode()
  if (!isEnabled) return null

  return (
    <div className="bg-amber-500 px-4 py-2 text-center font-mono text-xs text-black">
      Draft preview — showing unpublished content.{" "}
      <Link href="/api/preview/disable" className="underline underline-offset-2">
        Exit preview
      </Link>
    </div>
  )
}

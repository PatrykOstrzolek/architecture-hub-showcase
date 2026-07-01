import { timingSafeEqual } from "crypto"
import { draftMode } from "next/headers"
import { NextRequest, NextResponse } from "next/server"

const PREVIEW_SECRET = process.env.PREVIEW_SECRET

function safeCompare(a: string, b: string): boolean {
  if (a.length !== b.length) return false
  return timingSafeEqual(Buffer.from(a), Buffer.from(b))
}

/**
 * Draft Mode entry point — opened from Sulu admin with the page's resourcelocator,
 * e.g. /api/preview?secret=...&path=/learning-paths/domain-driven-design/exercise.
 * See ADR-0013.
 */
export async function GET(request: NextRequest) {
  const secret = request.nextUrl.searchParams.get("secret") ?? ""
  const path = request.nextUrl.searchParams.get("path")

  if (!PREVIEW_SECRET || !safeCompare(secret, PREVIEW_SECRET)) {
    return NextResponse.json(
      { error: "Invalid preview token" },
      { status: 401 }
    )
  }
  if (!path || !path.startsWith("/")) {
    return NextResponse.json(
      { error: 'Missing or invalid "path" query parameter.' },
      { status: 400 }
    )
  }

  const draft = await draftMode()
  draft.enable()

  return NextResponse.redirect(new URL(path, request.url))
}

import { timingSafeEqual } from "crypto"
import { revalidateTag } from "next/cache"
import { NextRequest, NextResponse } from "next/server"

const REVALIDATE_SECRET = process.env.REVALIDATE_SECRET

function safeCompare(a: string, b: string): boolean {
  if (a.length !== b.length) return false
  return timingSafeEqual(Buffer.from(a), Buffer.from(b))
}

export function POST(request: NextRequest): NextResponse {
  const token = request.headers.get("authorization")?.replace("Bearer ", "")

  if (!REVALIDATE_SECRET || !token || !safeCompare(token, REVALIDATE_SECRET)) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 })
  }

  revalidateTag("content", {})

  return NextResponse.json({ revalidated: true, timestamp: Date.now() })
}

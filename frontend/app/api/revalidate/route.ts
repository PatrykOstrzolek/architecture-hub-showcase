import { revalidateTag } from "next/cache"
import { NextRequest, NextResponse } from "next/server"

const REVALIDATE_SECRET = process.env.REVALIDATE_SECRET

export async function POST(request: NextRequest): Promise<NextResponse> {
  const token = request.headers.get("authorization")?.replace("Bearer ", "")

  if (!REVALIDATE_SECRET || token !== REVALIDATE_SECRET) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 })
  }

  revalidateTag("content", {})

  return NextResponse.json({ revalidated: true, timestamp: Date.now() })
}

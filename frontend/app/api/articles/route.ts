import { NextRequest, NextResponse } from "next/server"

const SULU_BASE_URL = process.env.SULU_BASE_URL ?? "http://localhost:8000"

export async function GET(req: NextRequest) {
  const src = req.nextUrl.searchParams
  const params = new URLSearchParams()
  for (const key of ["page", "limit", "category", "tag"] as const) {
    const val = src.get(key)
    if (val !== null) params.set(key, val)
  }
  const upstream = await fetch(`${SULU_BASE_URL}/api/articles?${params}`, {
    headers: { Accept: "application/json" },
  })

  return NextResponse.json(await upstream.json())
}

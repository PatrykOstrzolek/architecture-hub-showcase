import { NextRequest, NextResponse } from "next/server"

const SULU_BASE_URL = process.env.SULU_BASE_URL ?? "http://localhost:8000"

export async function GET(req: NextRequest) {
  const params = req.nextUrl.searchParams.toString()
  const upstream = await fetch(`${SULU_BASE_URL}/api/articles?${params}`, {
    headers: { Accept: "application/json" },
  })

  return NextResponse.json(await upstream.json())
}

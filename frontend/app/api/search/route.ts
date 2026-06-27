import { NextRequest, NextResponse } from "next/server";

const SULU_BASE_URL = process.env.SULU_BASE_URL ?? "http://localhost:8000";

export async function GET(req: NextRequest) {
  const q = req.nextUrl.searchParams.get("q") ?? "";
  if (!q.trim()) {
    return NextResponse.json({ _embedded: { hits: [] } });
  }

  const upstream = await fetch(
    `${SULU_BASE_URL}/api/search?q=${encodeURIComponent(q)}`,
    { headers: { Accept: "application/json" } },
  );

  const data = await upstream.json();
  return NextResponse.json(data);
}

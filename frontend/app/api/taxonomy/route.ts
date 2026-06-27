import { NextRequest, NextResponse } from "next/server";

const SULU_BASE_URL = process.env.SULU_BASE_URL ?? "http://localhost:8000";

export async function GET(req: NextRequest) {
  const q = req.nextUrl.searchParams.get("q") ?? "";
  if (q.trim().length < 2) {
    return NextResponse.json({ categories: [], tags: [] });
  }

  const upstream = await fetch(
    `${SULU_BASE_URL}/api/taxonomy?q=${encodeURIComponent(q)}`,
    { headers: { Accept: "application/json" } },
  );

  return NextResponse.json(await upstream.json());
}

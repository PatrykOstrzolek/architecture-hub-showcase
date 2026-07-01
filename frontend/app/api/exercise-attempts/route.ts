import { NextRequest, NextResponse } from "next/server"

const SULU_BASE_URL = process.env.SULU_BASE_URL ?? "http://localhost:8000"

export async function POST(req: NextRequest) {
  const body = await req.json().catch(() => null)
  if (!body || typeof body !== "object") {
    return NextResponse.json({ error: "Invalid JSON body." }, { status: 400 })
  }

  const { exerciseUuid, sessionId, answers } = body as Record<string, unknown>

  const upstream = await fetch(`${SULU_BASE_URL}/api/exercise-attempts`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ exerciseUuid, sessionId, answers }),
  })

  return NextResponse.json(await upstream.json(), { status: upstream.status })
}

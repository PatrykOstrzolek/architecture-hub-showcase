/**
 * Anonymous, locally-generated identity for tracking exercise attempts without
 * accounts or cookies. See ADR-0012 (Assessment bounded context) — avoids any
 * cross-site cookie/CORS complexity between the Vercel frontend and the Sulu
 * backend, since the id never needs to travel outside a same-origin fetch to
 * our own `/api/exercise-attempts` proxy route.
 */
const STORAGE_KEY = "ahub_anon_id"

export function getAnonymousSessionId(): string {
  if (typeof window === "undefined") {
    throw new Error("getAnonymousSessionId() must only be called client-side")
  }

  const existing = window.localStorage.getItem(STORAGE_KEY)
  if (existing) return existing

  const id = window.crypto.randomUUID()
  window.localStorage.setItem(STORAGE_KEY, id)
  return id
}

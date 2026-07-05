/**
 * Public URL of this Next.js site. Used for metadataBase, sitemap.xml,
 * robots.txt, and JSON-LD — see ADR-0015. Centralized here (mirroring how
 * lib/sulu.ts centralizes BASE_URL) so every consumer stays in sync and a
 * trailing slash can't sneak in and double up against a leading-slash path.
 */
export const SITE_URL = (process.env.SITE_URL ?? "http://localhost:3000").replace(
  /\/+$/,
  ""
)

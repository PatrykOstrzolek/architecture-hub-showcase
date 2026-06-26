# Context Summary — architecture-hub-showcase

Handoff note for continuing work in a new chat/session.

## Project

**architecture-hub-showcase** — a Sulu CMS (headless) + Next.js demo for a
software-architecture content hub.

- **Backend:** `backend/` — Sulu CMS 3.0.7 + `sulu/headless-bundle` 3.0.1.
  Webspace `architecture-hub`, single locale `en` at host root. Admin login
  `admin` / `admin`.
- **Frontend:** `frontend/` — Next.js App Router (RSC), TypeScript, Tailwind,
  shadcn/ui. ISR `next:{revalidate:60}` in `lib/sulu.ts`.
- Branch: `main`. **Nothing committed yet** — all work is in the working tree.

## Standing rules (from CLAUDE.md + memory)

- User runs ALL `sulu` / `composer` / `php` / `npm` / `npx` commands — assistant
  gets the logs. Heavy console/composer ops need `php -d memory_limit=1G`.
- Always ask before `rm -rf`, `git reset --hard`, `git clean -fd`,
  `docker system prune`, `docker volume rm`. Never delete project files after a
  failed install. Don't guess paths/versions/config.
- Prefer built-in Sulu capabilities; avoid custom APIs and new dependencies.
- Run shell from project root with relative paths; never touch system dirs.
  Reply in English even if user writes Polish. Always close/kill the Chrome
  session after Playwright work.
- **Pre-authorized (no need to ask):** read-only shell commands; calling/reading
  `localhost:8000` and `localhost:3000`.
- Backend template field changes require the user to run
  `php -d memory_limit=1G backend/bin/console cache:clear`.

## Key architecture facts

- Headless delivery: `{path}.json` → nested headless shape
  `{id,type,template,content:{title,url,summary},view:{url:{page,suffix}}}`.
  Plain HTML/Twig path (used by the **admin live preview**) exposes standard Sulu
  `content` with **flat** `.title`/`.url` accessors. **Keep the Twig fallback
  view valid when changing template fields**, or the admin preview 500s.
- smart_content article items share the exact shape as `article_selection` items
  → frontend reuses `ArticleSelectionItem` + `articleHref`
  (`frontend/components/content/types.ts`).
- smart_content `sortBy`/`sortMethod`/limit are **stored field data set in the
  admin** (filter button → Configure → Sort by), NOT in XML. Articles provider
  sort columns: `published, authored, created, changed, title`.
- Catch-all dispatch in `frontend/app/[[...slug]]/page.tsx` switches on
  `data.template` → `article` / `author` / `homepage` / default (`PageView`).

## Backlog status

| # | Item | Status |
|---|------|--------|
| 1 | 2nd article on Jane Kowalski's author page | ✅ Done |
| 2 | Homepage article listing (smart_content, auto-latest, Published ↓) | ✅ Done & verified |
| 3 | Categories/Tags as badges | ⬜ **Next** (needs shadcn `badge`) |
| 4 | Article reading-layout polish | ⬜ |
| 5 | Learning Path content type | ⬜ (spec: `docs/product/features/learning-paths.md`) |

## Last verified state (#2)

`localhost:8000/.json` lists "Eventual Consistency in Practice" then
"Understanding the CAP Theorem" (newest first, with summaries). Admin preview
HTML 200. `localhost:3000` renders the card grid correctly after the ISR window.
Browser closed, 0 chrome processes.

## Open decisions

- Whether to commit #1–#2 before starting #3, or keep going.

## Relevant memory files

- `sulu-template-twig-fallback-and-smartcontent.md` — the Twig/JSON/sort gotchas above.
- `sulu-admin-playwright-navigation.md` — driving the admin via Playwright
  (login, save split-button → "Save and publish", JS-click for intercepted
  elements, custom dropdown = `button[class*=option--]`).
- `working-directory-preference.md`, `language-preference.md`,
  `playwright-close-when-done.md`.

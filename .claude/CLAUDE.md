# Project Guidelines

Architecture-hub-showcase: Sulu CMS headless backend + Next.js frontend demo.

## Development Principles

- Prefer simplicity over complexity.
- Avoid premature abstractions; don't design for hypothetical future requirements.
- Avoid new dependencies unless justified. Keep code explicit.
- Prefer existing patterns. Follow ADR decisions.
- Ask for clarification when requirements are ambiguous.

## Safety Rules

Before executing any command containing:

```
rm -rf  /  git reset --hard  /  git clean -fd  /  docker system prune  /  docker volume rm
```

always ask for confirmation. Never delete project files after a failed install — show the error and propose a fix first.

## Environment

Verify tool availability before starting:

```bash
which mise && which symfony && which docker && which colima && which npx && which npm
```

If a tool is missing, ask the user — do not guess paths or install automatically.

### Backend: PHP runs on the host via mise, Docker is only for Postgres + Mailpit

PHP is managed by `mise` (version pinned in `mise.toml` at the repo root). `php`, `composer`, and `bin/console` all run directly on the host — no container involved:

```bash
cd backend
composer install
php bin/console cache:clear
symfony serve   # dev server, from backend/
```

Docker (`docker compose up -d` from `backend/`) only starts `database` (Postgres) and `mailer` (Mailpit) — there is no `php` service anymore. See `docs/architecture/adrs/0017-mise-managed-host-php-for-local-dev.md`.

`symfony` (Symfony CLI) is a separate host prerequisite, not mise-managed (mise's registry has no `symfony-cli` entry) — install via `brew install symfony-cli/tap/symfony-cli`.

### PHP Memory Limit

Set via `backend/php-conf.d/local.ini` (`memory_limit = 1G`, opcache enabled), loaded through `PHP_INI_SCAN_DIR` in `mise.toml`'s `[env]` table — applies to every `php`/`composer` invocation on the host automatically. Do not reintroduce inline `-d memory_limit=1G` flags on individual commands. (Prod's `docker-compose.prod.yml` still sets `PHP_MEMORY_LIMIT` as a container env var — that path is unaffected by this.)

### Docker

Only needed for Postgres + Mailpit in dev now. May be configured through Colima. Check context before use:

```bash
which docker && which colima && docker context ls
```

If the active context is `colima`, verify it is running:

```bash
colima status
```

If Colima is unavailable, ask the user — do not modify Docker configuration automatically.

## Frontend

- Use TypeScript.
- Prefer React Server Components; Client Components only when interactivity is required.
- Use Tailwind CSS and shadcn/ui.
- Prefer feature-based organization.

## Backend

- Sulu CMS is the source of truth for content.
- Prefer built-in Sulu capabilities before custom solutions.
- Do not introduce custom APIs unless explicitly required.

## Documentation

- Specifications are the source of truth. Read `docs/` before implementing.
- Major decisions must be documented as ADRs.
- Implementation must remain consistent with existing specifications.

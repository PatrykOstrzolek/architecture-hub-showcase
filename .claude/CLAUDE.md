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

This project uses **mise** for tool management. Verify availability before starting:

```bash
which php && which composer && which docker && which colima && which npx && which npm
```

If a tool is missing, ask the user — do not guess paths or install automatically.

### PHP Memory Limit

Default is 128M — insufficient for Symfony/Sulu heavy operations. Always use 1G for:

```bash
php -d memory_limit=1G bin/console cache:clear
php -d memory_limit=1G bin/console cache:warmup
php -d memory_limit=1G $(which composer) install
```

### Docker

May be configured through Colima. Check context before use:

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

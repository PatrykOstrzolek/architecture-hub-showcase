# Project Guidelines

Before implementing changes:

* Read relevant documentation from the docs directory.
* Prefer existing patterns over introducing new ones.
* Follow the project architecture and ADR decisions.
* Ask for clarification when requirements are ambiguous.

## Development Principles

* Prefer simplicity over complexity.
* Avoid premature abstractions.
* Avoid introducing new dependencies unless justified.
* Keep code explicit and easy to understand.

## Command Execution Rule

Every `sulu`, `composer`, `php`, `npm`, and `npx` command will be run by the user. The assistant will receive the available log.

## Frontend

* Use TypeScript.
* Prefer React Server Components.
* Use Client Components only when interactivity is required.
* Use Tailwind CSS and shadcn/ui.
* Prefer feature-based organization.

## Backend

* Sulu CMS is the source of truth for content.
* Prefer built-in Sulu capabilities before creating custom solutions.
* Do not introduce custom APIs unless explicitly required.

## Documentation

* Specifications are the source of truth.
* Major decisions should be documented through ADRs.
* Implementation should remain consistent with existing specifications.

## Environment Bootstrap

This project uses mise.

## Verify tool availability:

```bash
which php
which composer
which docker
which colima
which npx
which npm
```

## Memory Limit

This environment uses PHP with a default memory limit of 128M.

For Symfony, Sulu, cache warmup, cache clear, composer post-install scripts, and similar heavy operations, use:

```bash
php -d memory_limit=1G
```

Examples:

```bash
php -d memory_limit=1G bin/console cache:clear
php -d memory_limit=1G bin/console cache:warmup
php -d memory_limit=1G $(which composer) install
```

Do not assume the default PHP memory limit is sufficient.

## Docker

Docker may be configured through Colima.

Before using Docker:

```bash
which docker
which colima
docker context ls
```

If the active context is `colima`, verify:

```bash
colima status
```

If Colima is unavailable or not running, ask for guidance instead of modifying Docker configuration automatically.
```

## Safety Rules

Before executing any command containing:

```bash
rm -rf
git reset --hard
git clean -fd
docker system prune
docker volume rm
```

always ask for confirmation.

Never delete project files automatically after a failed installation.

Always show the error and propose a fix before performing cleanup.

## Uncertainty

If the environment configuration is unclear, ask first.

Do not guess paths, PHP versions, Composer configuration, Docker configuration, or deployment details.

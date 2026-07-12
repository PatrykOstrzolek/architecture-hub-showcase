# ADR-0017: Mise-Managed Host PHP for Local Development

- **Status**: Accepted
- **Date**: 2026-07-12
- **Deciders**: Patryk O

## Context

Local backend development ran entirely inside a `serversideup/php:8.5-fpm-nginx` Docker container (`backend/docker/Dockerfile`, wired up as the `php` service in `backend/compose.override.yaml`). Every `php`/`composer`/`bin/console` invocation had to go through `docker compose exec php ...`.

A repo-wide audit before this change surfaced two things that undercut the case for keeping that container:

- **CI already runs on host PHP, not Docker.** `.github/workflows/ci-backend.yml` uses `shivammathur/setup-php@...` with `php-version: '8.5'` directly on the `ubuntu-latest` runner. The Docker-based local dev workflow was the odd one out, not CI.
- **Production is fully decoupled from the dev container.** Prod builds from a separate `backend/Dockerfile.prod` and runs behind a host-level nginx + certbot on the Mikrus VPS (and the EC2 secondary, [ADR-0016](0016-ec2-secondary-failover-drill.md)). Nothing in the prod path references `backend/docker/` or `compose.override.yaml`.

Separately, [mise](https://mise.jdx.dev) (a host tool version manager, already used for Node) gained a PHP plugin (`verzly/mise-php`) capable of managing a PHP install including FPM. A smoke test confirmed host PHP 8.5.7 via mise + `composer install` + a dev server served the app correctly against the same Postgres the Docker setup used (`/`, `/admin/`, and Sulu's `/.json` homepage-content route all returned 200, no errors) — with no nginx in the loop at all.

That last point mattered because `backend/docker/nginx/server-opts.d/sulu-homepage-json.conf` existed solely to carve an exception into the serversideup image's own nginx hardening rule, which otherwise blocks dotfile paths and breaks Sulu's `/.json` route. Without nginx in the picture, that workaround has nothing to work around.

## Decision

Local backend dev now runs PHP directly on the host:

- **PHP** is version-managed by mise (`mise.toml` at the repo root pins `php = "8.5.7"`, matching CI's `8.5`).
- **PHP memory limit / opcache** are set via `backend/php-conf.d/local.ini`, loaded through `PHP_INI_SCAN_DIR` in `mise.toml`'s `[env]` table (a leading `:` appends this directory after mise-php's own `conf.d`, so it overrides mise-php's default `memory_limit = 512M` without touching mise's install directory). This keeps memory-limit configuration centralized, matching the project's existing rule against inline `-d memory_limit` flags per command.
- **Dev server** is [Symfony CLI](https://symfony.com/download) (`symfony serve`), installed via `brew install symfony-cli/tap/symfony-cli` — a separate host prerequisite, since mise's registry has no `symfony-cli` entry.
- **Docker is retained only for stateful services**: `docker compose up -d` (from `backend/`) now starts just `database` (Postgres) and `mailer` (Mailpit).
- `backend/docker/` (the dev `Dockerfile` and the nginx dotfile workaround) is deleted — nothing depends on it anymore.

Env files were adjusted so the fix isn't tied to one developer's machine: `backend/.env.dev` (committed, `APP_ENV=dev`-scoped per Symfony convention) now carries `DATABASE_URL` pointed at `127.0.0.1:5432` instead of the Docker-network hostname `database`. `backend/.env.test` got the same host-relative fix for local `phpunit` runs against the real Postgres (CI's own PHPUnit run doesn't touch the DB, so this was latent, not currently broken there).

## Alternatives Considered

**Keep the Docker container as an optional, non-default fallback for pre-deploy nginx-parity checks** — considered, rejected in favor of a clean removal. Nothing in CI or prod exercises this container, so keeping it around would be unused config with no automated check keeping it correct; if nginx-specific behavior ever needs verifying pre-deploy, prod's own image (`Dockerfile.prod`) is the more accurate thing to test against anyway.

**`php -S` built-in server instead of Symfony CLI** — proven to work in the smoke test with zero new host dependencies, but rejected in favor of Symfony CLI: it's single-threaded (blocks on concurrent requests), whereas Symfony CLI wraps a Caddy-based binary that handles concurrency and is the standard tool in the Sulu/Symfony community for local dev.

**`PHPRC` pointing at a single php.ini** — tried first, rejected: mise-php's own `conf.d/php.ini` (setting `memory_limit = 512M`) loads *after* the file `PHPRC` points at, silently overriding it. `PHP_INI_SCAN_DIR` with a leading `:` (append, not replace) was needed instead so our override loads last.

## Consequences

### Positive

- Local dev now matches how CI runs the backend (host PHP), rather than diverging from it.
- One fewer Docker image to build/maintain for contributors; `docker compose up -d` is faster (two lightweight services instead of a custom PHP+nginx build).
- Removes a workaround (`sulu-homepage-json.conf`) that existed only because of the container's own nginx hardening — one less piece of dev-only config to keep in sync with anything.
- Fixes a real bug (`DATABASE_URL` pointing at a Docker-network hostname unreachable from the host) for every future developer, not just the machine it was first noticed on.

### Negative

- Two new host prerequisites: `mise` and `symfony-cli` (the latter not mise-managed, installed via Homebrew, and must be kept in mind as a manual step in onboarding).
- `apcu` (available in the old container image) isn't installed on host PHP. Currently harmless — it's only a commented-out option in `config/packages/cache.yaml` — but would need a manual `pie`/`pecl` install if ever adopted for real.
- Local dev no longer exercises nginx at all; an nginx-specific misconfiguration (routing, headers, dotfile rules) won't surface locally, only against prod's own image/deploy.

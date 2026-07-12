#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/backend"

echo "==> Postgres + Mailpit"
docker compose up -d

echo "==> Symfony dev server (Ctrl+C to stop)"
symfony serve --no-tls

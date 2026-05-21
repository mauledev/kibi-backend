#!/usr/bin/env sh
set -e

if command -v docker >/dev/null 2>&1 && docker compose ps app 2>/dev/null | grep -qE 'running|Up'; then
    docker compose exec -T app composer quality
else
    composer quality
fi

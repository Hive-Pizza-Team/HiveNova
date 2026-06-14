#!/usr/bin/env bash
# Dev-time batch render of static planet JPGs into styles/theme/hive/planeten/
# Parallel workers: --jobs N or PLANET_RENDER_JOBS=8 (default ~6)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

PHP_PORT="${PLANET_RENDER_PORT:-8765}"
export PLANET_RENDER_PORT="$PHP_PORT"

if ! command -v node >/dev/null 2>&1; then
	echo "node is required" >&2
	exit 1
fi

if ! node -e "require('puppeteer')" 2>/dev/null; then
	echo "Installing puppeteer (dev dependency)…"
	npm install
fi

php -S "127.0.0.1:${PHP_PORT}" -t "$ROOT" >/dev/null 2>&1 &
PHP_PID=$!
cleanup() {
	kill "$PHP_PID" 2>/dev/null || true
}
trap cleanup EXIT

sleep 1

if ! curl -sf "http://127.0.0.1:${PHP_PORT}/scripts/dev/planet-viz-export.html" >/dev/null; then
	echo "PHP dev server failed to start on port ${PHP_PORT}" >&2
	exit 1
fi

node scripts/dev/render-planet-images.mjs --port "$PHP_PORT" "$@"

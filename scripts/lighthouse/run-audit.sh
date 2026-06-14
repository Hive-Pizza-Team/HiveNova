#!/usr/bin/env bash
# Batch Lighthouse audits for all ingame pages (Puppeteer + CDP session reuse).
#
# Usage:
#   ./scripts/lighthouse/run-audit.sh
#   ./scripts/lighthouse/run-audit.sh --pages overview,buildings --html
#
# Prerequisites:
#   - node + npm install (puppeteer, lighthouse)
#   - PHP dev server on :8000 (php -S localhost:8000)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

BASE_URL="${SMOKE_BASE_URL:-http://localhost:8000}"

if ! command -v node >/dev/null 2>&1; then
	echo "node is required" >&2
	exit 1
fi

if ! node -e "require('puppeteer'); require('lighthouse')" 2>/dev/null; then
	echo "Installing dev dependencies (puppeteer, lighthouse)…"
	npm install
fi

if ! curl -sf "${BASE_URL%/}/index.php" >/dev/null; then
	echo "Dev server not reachable at ${BASE_URL}" >&2
	echo "Start with: php -S localhost:8000" >&2
	exit 1
fi

node scripts/lighthouse/audit.mjs "$@"
node scripts/lighthouse/summarize.mjs latest

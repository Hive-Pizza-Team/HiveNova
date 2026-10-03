#!/usr/bin/env bash
# Syntax-check production deploy scripts. No SSH, no network.
#
# Usage:
#   ./tests/check-deploy-scripts.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

bash -n scripts/deploy.sh
bash -n scripts/deploy-spa.sh

help_out="$("$ROOT/scripts/deploy.sh" --help)"
printf '%s\n' "$help_out" | grep -q 'HN_DEPLOY_ROOT' || {
	echo "FAIL: scripts/deploy.sh --help did not mention HN_DEPLOY_ROOT" >&2
	exit 1
}

if "$ROOT/scripts/deploy.sh" --bogus >/dev/null 2>&1; then
	echo "FAIL: scripts/deploy.sh --bogus should exit non-zero" >&2
	exit 1
fi

echo "OK: deploy scripts"

#!/usr/bin/env bash
# run-ci-local.sh — mirrors the GitHub Actions CI pipeline locally.
#
# Usage:
#   ./tests/run-ci-local.sh               # unit tests + language check + smoke + bottom-nav check
#   ./tests/run-ci-local.sh --integration # also run integration tests (requires MySQL)
#
# Prerequisites:
#   - composer install
#   - Local PHP dev server running on :8000  (php -S localhost:8000)
#   - For --integration: MySQL with game installed (php tests/ci-install.php)

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# When stdout is a pipe (IDE/agents), block-buffering can hide output until the buffer
# fills; stderr is typically unbuffered. Emit progress there first.
if [[ -t 1 ]]; then
  _say() { echo "$@"; }
else
  _say() { echo "$@" >&2; }
fi

_say "=== HiveNova local CI ==="

INTEGRATION=0
for arg in "$@"; do
  [[ "$arg" == "--integration" ]] && INTEGRATION=1
done

# Clear error.log so we only see errors from this run. Skip FIFOs/special files — truncating
# a named pipe can block forever waiting for a reader.
if [[ -e includes/error.log ]] && [[ -p includes/error.log ]]; then
  _say "WARN: includes/error.log is a FIFO; skipping truncate"
elif [[ -e includes/error.log ]] && [[ ! -f includes/error.log ]]; then
  _say "WARN: includes/error.log is not a regular file; skipping truncate"
else
  : > includes/error.log 2>/dev/null || true
fi

PASS=0
FAIL=0

run() {
  local label="$1"
  shift
  _say ""
  _say "=== $label ==="
  if "$@"; then
    _say "--- PASS: $label"
    ((PASS++)) || true
  else
    _say "--- FAIL: $label"
    ((FAIL++)) || true
  fi
}

check_error_log() {
  # Ignore deprecations from vendor libraries (third-party code we don't control)
  local errors
  errors=$(grep -v '/vendor/' includes/error.log 2>/dev/null || true)
  if [[ -n "$errors" ]]; then
    _say "=== includes/error.log has non-vendor errors ==="
    echo "$errors"
    return 1
  fi
  return 0
}

run "Language check"   php .github/scripts/check-language-files.php
run "CSS check"        bash tests/check-css.sh
run "JS tests"         npm run test:js
run "Unit tests"       php vendor/bin/phpunit --configuration phpunit.xml
run "Smoke test"       php tests/smoke.php
run "Bottom nav check" php tests/check-bottom-nav.php
run "Error log empty"  check_error_log

if [[ $INTEGRATION -eq 1 ]]; then
  run "Integration tests" php vendor/bin/phpunit --configuration phpunit-integration.xml
  run "Error log empty (post-integration)" check_error_log
fi

_say ""
_say "=== Results: $PASS passed, $FAIL failed ==="
[[ $FAIL -eq 0 ]]

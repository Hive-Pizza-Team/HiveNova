#!/usr/bin/env bash
# run-ci-local.sh — mirrors the GitHub Actions CI pipeline locally.
#
# Usage:
#   ./tests/run-ci-local.sh               # unit tests + language check + smoke test
#   ./tests/run-ci-local.sh --integration # also run integration tests (requires MySQL)
#
# Prerequisites:
#   - composer install
#   - Local PHP dev server running on :8000  (php -S localhost:8000)
#   - For --integration: MySQL with game installed (php tests/ci-install.php)

set -euo pipefail
cd "$(dirname "$0")/.."

INTEGRATION=0
for arg in "$@"; do
  [[ "$arg" == "--integration" ]] && INTEGRATION=1
done

# Clear error.log so we only see errors from this run
> includes/error.log 2>/dev/null || true

PASS=0
FAIL=0

run() {
  local label="$1"; shift
  echo ""
  echo "=== $label ==="
  if "$@"; then
    echo "--- PASS: $label"
    ((PASS++)) || true
  else
    echo "--- FAIL: $label"
    ((FAIL++)) || true
  fi
}

check_error_log() {
  # Ignore deprecations from vendor libraries (third-party code we don't control)
  local errors
  errors=$(grep -v '/vendor/' includes/error.log 2>/dev/null || true)
  if [ -n "$errors" ]; then
    echo "=== includes/error.log has non-vendor errors ==="
    echo "$errors"
    return 1
  fi
  return 0
}

run "Language check"   php .github/scripts/check-language-files.php
run "Unit tests"       php vendor/bin/phpunit --configuration phpunit.xml
run "Smoke test"       php tests/smoke.php
run "Error log empty"  check_error_log

if [[ $INTEGRATION -eq 1 ]]; then
  run "Integration tests" php vendor/bin/phpunit --configuration phpunit-integration.xml
  run "Error log empty (post-integration)" check_error_log
fi

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="
[[ $FAIL -eq 0 ]]

#!/usr/bin/env bash
# check-diff-coverage.sh — fail if changed lines under includes/classes/ lack test coverage.
#
# Usage:
#   ./tests/check-diff-coverage.sh                    # run unit (+ integration if RUN_INTEGRATION=1)
#   ./tests/check-diff-coverage.sh --integration      # also run integration tests (needs MySQL)
#   ./tests/check-diff-coverage.sh --from-artifacts   # only diff-cover (CI: clover files already present)
#
# Environment:
#   DIFF_COVER_FAIL_UNDER=80          minimum % of changed lines covered (default 80)
#   DIFF_COVER_COMPARE_BRANCH=...   git ref for PR base (default origin/develop)
#   RUN_INTEGRATION=1               same as --integration
#
# Requires: pip install diff-cover  (https://github.com/Bachmann1234/diff_cover)

set -euo pipefail
cd "$(dirname "$0")/.."

FAIL_UNDER="${DIFF_COVER_FAIL_UNDER:-80}"
COMPARE_BRANCH="${DIFF_COVER_COMPARE_BRANCH:-origin/develop}"
FROM_ARTIFACTS=0
RUN_INTEGRATION="${RUN_INTEGRATION:-0}"

for arg in "$@"; do
  case "$arg" in
    --from-artifacts) FROM_ARTIFACTS=1 ;;
    --integration)    RUN_INTEGRATION=1 ;;
    -h|--help)
      sed -n '2,14p' "$0"
      exit 0
      ;;
    *)
      echo "Unknown argument: $arg" >&2
      exit 2
      ;;
  esac
done

mkdir -p coverage

if [[ "$FROM_ARTIFACTS" -eq 0 ]]; then
  echo "=== Unit tests (with coverage) ==="
  XDEBUG_MODE=coverage php vendor/bin/phpunit \
    --configuration phpunit.xml \
    --coverage-clover coverage/clover.xml

  if [[ "$RUN_INTEGRATION" -eq 1 ]]; then
    echo "=== Integration tests (with coverage) ==="
    XDEBUG_MODE=coverage php vendor/bin/phpunit \
      --configuration phpunit-integration.xml \
      --coverage-clover coverage/integration-clover.xml
  fi
fi

# GitHub artifact layout may flatten paths (clover.xml at coverage/ root).
if [[ ! -f coverage/clover.xml ]] && [[ -f coverage/coverage/clover.xml ]]; then
  mv coverage/coverage/clover.xml coverage/clover.xml
  rmdir coverage/coverage 2>/dev/null || true
fi
if [[ ! -f coverage/integration-clover.xml ]] && [[ -f coverage/coverage/integration-clover.xml ]]; then
  mv coverage/coverage/integration-clover.xml coverage/integration-clover.xml
fi

if [[ ! -f coverage/clover.xml ]]; then
  echo "=== coverage/ contents ==="
  find coverage -type f 2>/dev/null || echo "(no coverage directory)"
  echo "Missing coverage/clover.xml — run unit tests with XDEBUG_MODE=coverage first." >&2
  exit 1
fi

if ! command -v diff-cover >/dev/null 2>&1; then
  echo "diff-cover is not installed. Install with: pip install diff-cover" >&2
  exit 1
fi

# Ensure compare ref exists (best-effort for local runs).
if [[ "$COMPARE_BRANCH" == origin/* ]]; then
  remote="${COMPARE_BRANCH#origin/}"
  git fetch origin "$remote" --depth=1 2>/dev/null || true
fi

COV_FILES=(coverage/clover.xml)
if [[ -f coverage/integration-clover.xml ]]; then
  COV_FILES+=(coverage/integration-clover.xml)
fi

# PHPUnit Clover uses <file name="..."> (absolute paths). diff-cover's Clover parser
# expects <file path="..."> and a clover attribute on <coverage>; without those it
# uses the Cobertura parser and reports "No lines with coverage information".
normalize_clover_for_diff_cover() {
  local src="$1"
  local dest="$2"
  perl -pe '
    if (!/clover=/) { s/<coverage /<coverage clover="4.5.2" /; }
    s{<file name="(?:[^"]*/)?(includes/classes/[^"]+)"}{<file path="$1" name="$1"}g;
  ' "$src" > "$dest"
}

NORMALIZED=()
for f in "${COV_FILES[@]}"; do
  norm="coverage/.diff-cover-$(basename "$f")"
  normalize_clover_for_diff_cover "$f" "$norm"
  NORMALIZED+=("$norm")
done

echo "=== Diff coverage (includes/classes/, fail under ${FAIL_UNDER}%) ==="
echo "Compare branch: ${COMPARE_BRANCH}"
echo "Reports: ${COV_FILES[*]} (normalized for diff-cover)"

DIFF_COVER_LOG="$(mktemp)"
trap 'rm -f "$DIFF_COVER_LOG"' EXIT
set +e
diff-cover "${NORMALIZED[@]}" \
  --compare-branch="$COMPARE_BRANCH" \
  --fail-under="$FAIL_UNDER" \
  --include='includes/classes/*' \
  --show-uncovered 2>&1 | tee "$DIFF_COVER_LOG"
DIFF_COVER_RC=${PIPESTATUS[0]}
set -e

if grep -Fq 'No lines with coverage information in this diff.' "$DIFF_COVER_LOG"; then
  echo "diff-cover could not match Clover paths to the git diff (vacuous pass)." >&2
  echo "Ensure coverage reports include includes/classes/ with path attributes." >&2
  exit 1
fi

exit "$DIFF_COVER_RC"

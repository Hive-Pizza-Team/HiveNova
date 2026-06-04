#!/usr/bin/env bash
# check-tree-coverage.sh — fail if full-tree coverage on includes/classes/ is below threshold.
#
# Usage:
#   ./tests/check-tree-coverage.sh
#   TREE_COVER_FAIL_UNDER=80 ./tests/check-tree-coverage.sh
#
# Reads project metrics from coverage/clover.xml (PHPUnit unit suite scope per phpunit.xml).
# Integration clover is not merged here; use a merged report if you need both (future).

set -euo pipefail
cd "$(dirname "$0")/.."

CLOVER="${CLOVER:-coverage/clover.xml}"
FAIL_UNDER="${TREE_COVER_FAIL_UNDER:-0}"

if [[ ! -f "$CLOVER" ]]; then
  echo "Missing $CLOVER — run unit tests with XDEBUG_MODE=coverage first." >&2
  echo "  XDEBUG_MODE=coverage php vendor/bin/phpunit --configuration phpunit.xml --coverage-clover $CLOVER" >&2
  exit 1
fi

read -r TOTAL COVERED <<< "$(perl -ne '
  if (/<metrics files=/) {
    my ($s) = /statements="(\d+)"/;
    my ($c) = /coveredstatements="(\d+)"/;
    print "$s $c\n" if defined $s && defined $c;
  }
' "$CLOVER" | tail -1)"

if [[ -z "${TOTAL:-}" || "$TOTAL" -eq 0 ]]; then
  echo "Could not parse statement metrics from $CLOVER" >&2
  exit 1
fi

export TOTAL COVERED FAIL_UNDER
PCT=$(perl -e 'printf "%.2f", ($ENV{COVERED} / $ENV{TOTAL}) * 100')

TARGET=$(( (TOTAL * FAIL_UNDER + 99) / 100 ))
NEED=$(( TARGET - COVERED ))
[[ "$NEED" -lt 0 ]] && NEED=0

echo "Tree coverage (includes/classes/, unit clover): ${PCT}% (${COVERED}/${TOTAL} statements)"
echo "Threshold: ${FAIL_UNDER}% (need ${TARGET} covered; gap ${NEED})"

if perl -e 'exit(($ENV{COVERED} / $ENV{TOTAL}) * 100 < $ENV{FAIL_UNDER} ? 1 : 0)'; then
  echo "PASS: tree coverage meets ${FAIL_UNDER}%"
  exit 0
fi

echo "FAIL: tree coverage ${PCT}% is below ${FAIL_UNDER}%" >&2
exit 1

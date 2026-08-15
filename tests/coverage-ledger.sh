#!/usr/bin/env bash
# coverage-ledger.sh — list includes/classes files by uncovered statements (plan phases).
#
# Usage:
#   ./tests/coverage-ledger.sh
#   ./tests/coverage-ledger.sh --top 30

set -euo pipefail
cd "$(dirname "$0")/.."

CLOVER="${CLOVER:-coverage/clover.xml}"
TOP=20

while [[ $# -gt 0 ]]; do
  case "$1" in
    --top)
      TOP="${2:-20}"
      shift 2
      ;;
    -h|--help)
      echo "Usage: $0 [--top N]"
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 2
      ;;
  esac
done

if [[ ! -f "$CLOVER" ]]; then
  echo "Missing $CLOVER" >&2
  exit 1
fi

assign_phase() {
  local rel="$1"
  case "$rel" in
    Database.php) echo "1" ;;
    FleetFunctions.php) echo "2" ;;
    missions/MissionCaseAttack.php|missions/MissionCaseExpedition.php|missions/MissionCaseDestruction.php)
      echo "3a" ;;
    missions/functions/OPBE.php) echo "3a/5" ;;
    PlayerUtil.php|ResourceUpdate.php|Session.php) echo "4" ;;
    missions/MissionCase*.php|missions/functions/*) echo "5" ;;
    *) echo "6" ;;
  esac
}

export -f assign_phase

perl -ne '
  if (/<file name="[^"]*includes\/classes\/([^"]+)"/) {
    $f = $1;
  }
  if (defined $f && /<metrics loc=/) {
    my ($s, $c) = /statements="(\d+)"[^>]*coveredstatements="(\d+)"/;
    if (defined $s) {
      print "$f\t$s\t$c\t" . ($s - $c) . "\n";
    }
    undef $f;
  }
' "$CLOVER" | sort -t$'\t' -k4 -nr | head -n "$TOP" | while IFS=$'\t' read -r file stmts cov uncov; do
  [[ -z "$file" || "$stmts" -eq 0 ]] && continue
  phase=$(assign_phase "$file")
  export S="$stmts" C="$cov"
  pct=$(perl -e 'printf "%.1f", ($ENV{C}/$ENV{S})*100')
  printf "%4s  %5s/%5s (%5s%%)  %-45s\n" "$phase" "$cov" "$stmts" "$pct" "$file"
done

read -r TOTAL COVERED <<< "$(perl -ne '
  if (/<metrics files=/) {
    my ($s) = /statements="(\d+)"/;
    my ($c) = /coveredstatements="(\d+)"/;
    print "$s $c\n" if defined $s && defined $c;
  }
' "$CLOVER" | tail -1)"

TARGET_80=$(( (TOTAL * 80 + 99) / 100 ))
GAP=$(( TARGET_80 - COVERED ))
echo ""
export TOTAL COVERED
echo "Project: ${COVERED}/${TOTAL} ($(perl -e 'printf "%.2f", ($ENV{COVERED}/$ENV{TOTAL})*100')%)"
echo "To 80%: need ${TARGET_80} covered (${GAP} more statements)"

#!/usr/bin/env bash
# Extract pre-batch legacy hive planet JPGs for scripts/dev/planet-static-preview.html
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="$ROOT/scripts/dev/legacy-samples"
LEGACY_REF="${1:-396021e7^}"

cd "$ROOT"
mkdir -p "$OUT"

if ! git cat-file -e "${LEGACY_REF}^{commit}" 2>/dev/null; then
	echo "Legacy ref not found: ${LEGACY_REF}" >&2
	exit 1
fi

echo "Extracting legacy hive planet JPGs from ${LEGACY_REF} → ${OUT#"$ROOT"/}"

while IFS= read -r texture; do
	src="styles/theme/hive/planeten/${texture}.jpg"
	if git cat-file -e "${LEGACY_REF}:${src}" 2>/dev/null; then
		git show "${LEGACY_REF}:${src}" > "$OUT/${texture}.jpg"
		echo "  ${texture}.jpg"
	fi
done <<'EOF'
trockenplanet01
trockenplanet05
wuestenplanet02
dschjungelplanet03
dschjungelplanet08
normaltempplanet03
normaltempplanet07
wasserplanet04
wasserplanet09
eisplanet02
eisplanet10
mond
unknown
destroyed
EOF

echo "Done. Open scripts/dev/planet-static-preview.html via php -S."

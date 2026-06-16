#!/usr/bin/env bash
# Populate scripts/dev/legacy-samples/ with painted JPGs from legacy themes (nova primary).
# Used by planet-static-preview.html "Legacy" column.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="$ROOT/scripts/dev/legacy-samples"
NOVA="$ROOT/styles/theme/nova/planeten"
GOW="$ROOT/styles/theme/gow/planeten"
LEGACY_REF="${1:-396021e7^}"

cd "$ROOT"
mkdir -p "$OUT"

copy_if_exists() {
	local src="$1"
	local dest="$2"
	if [[ -f "$src" ]]; then
		cp "$src" "$dest"
		echo "  $(basename "$dest")"
		return 0
	fi
	return 1
}

resolve_source() {
	local texture="$1"
	if copy_if_exists "$NOVA/${texture}.jpg" /dev/null 2>/dev/null; then
		echo "$NOVA/${texture}.jpg"
		return
	fi
	if copy_if_exists "$GOW/${texture}.jpg" /dev/null 2>/dev/null; then
		echo "$GOW/${texture}.jpg"
		return
	fi
	if git cat-file -e "${LEGACY_REF}:styles/theme/hive/planeten/${texture}.jpg" 2>/dev/null; then
		git show "${LEGACY_REF}:styles/theme/hive/planeten/${texture}.jpg"
		return
	fi
	return 1
}

copy_texture() {
	local texture="$1"
	local dest="$OUT/${texture}.jpg"
	if [[ -f "$NOVA/${texture}.jpg" ]]; then
		cp "$NOVA/${texture}.jpg" "$dest"
		echo "  ${texture}.jpg (nova)"
		return 0
	fi
	if [[ -f "$GOW/${texture}.jpg" ]]; then
		cp "$GOW/${texture}.jpg" "$dest"
		echo "  ${texture}.jpg (gow)"
		return 0
	fi
	if git cat-file -e "${LEGACY_REF}:styles/theme/hive/planeten/${texture}.jpg" 2>/dev/null; then
		git show "${LEGACY_REF}:styles/theme/hive/planeten/${texture}.jpg" > "$dest"
		echo "  ${texture}.jpg (pre-batch hive)"
		return 0
	fi
	return 1
}

echo "Extracting legacy planet JPGs → ${OUT#"$ROOT"/}"

for prefix in trockenplanet wuestenplanet dschjungelplanet normaltempplanet wasserplanet eisplanet; do
	for v in 01 02 03 04 05; do
		texture="${prefix}${v}"
		copy_texture "$texture" || true
	done
done

# Old themes had four wüsten variants; use 04 for 05.
if [[ ! -f "$OUT/wuestenplanet05.jpg" && -f "$OUT/wuestenplanet04.jpg" ]]; then
	cp "$OUT/wuestenplanet04.jpg" "$OUT/wuestenplanet05.jpg"
	echo "  wuestenplanet05.jpg (alias of 04)"
fi

for texture in mond unknown debris; do
	copy_texture "$texture" || true
done

# Moon size/base variants did not exist in legacy themes — single mond.jpg everywhere.
if [[ -f "$OUT/mond.jpg" ]]; then
	for texture in moon-small moon-large moon-base-2 moon-base-5; do
		cp "$OUT/mond.jpg" "$OUT/${texture}.jpg"
		echo "  ${texture}.jpg (alias of mond)"
	done
fi

# Destroyed state asset is post-viz; approximate with a dark hot-world painted icon.
if [[ ! -f "$OUT/destroyed.jpg" ]]; then
	if [[ -f "$NOVA/trockenplanet01.jpg" ]]; then
		cp "$NOVA/trockenplanet01.jpg" "$OUT/destroyed.jpg"
		echo "  destroyed.jpg (nova trockenplanet01 stand-in)"
	elif [[ -f "$OUT/trockenplanet01.jpg" ]]; then
		cp "$OUT/trockenplanet01.jpg" "$OUT/destroyed.jpg"
		echo "  destroyed.jpg (trockenplanet01 stand-in)"
	fi
fi

count=$(find "$OUT" -name '*.jpg' | wc -l | tr -d ' ')
echo "Done: ${count} legacy JPGs in legacy-samples/"

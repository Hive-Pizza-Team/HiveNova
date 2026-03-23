#!/usr/bin/env bash
# CSS design token checker — no Node/npm required.
#
# Enforces: no CSS named colors in ingame/main.css and all theme formate.css files.
# Named colors must be replaced with hex equivalents or var(--color-*) tokens.
#
# Usage:
#   ./tests/check-css.sh          # exits 0 on pass, 1 on failure

set -euo pipefail

ERRORS=0
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

INGAME="$ROOT/styles/resource/css/ingame/main.css"
THEME_FILES=$(find "$ROOT/styles/theme" -name "formate.css")

# Named colors that are valid CSS color keywords (excludes property names like white-space).
# Pattern requires the color word to appear as a standalone value token after `:`.
# We skip comment lines.
NAMED_COLORS_PATTERN=":\s*(red|green|blue|white|black|lime|yellow|orange|pink|purple|cyan|magenta|skyblue|gray|grey|silver|maroon|navy|teal|olive|aqua|fuchsia)\s*([;,!/{]|$)"

check_named_colors() {
    local file="$1"
    local rel="${file#$ROOT/}"
    # Strip single-line comments before matching, then search
    local hits
    hits=$(grep -inE "$NAMED_COLORS_PATTERN" "$file" | grep -v "^\s*/\*\|^\s*\*\|^\s*//" || true)
    if [[ -n "$hits" ]]; then
        echo "FAIL: named colors found in $rel:"
        echo "$hits" | while IFS= read -r line; do echo "  $line"; done
        ERRORS=$((ERRORS + 1))
    fi
}

check_named_colors "$INGAME"
for f in $THEME_FILES; do
    check_named_colors "$f"
done

if [[ $ERRORS -eq 0 ]]; then
    echo "CSS check passed."
    exit 0
else
    echo ""
    echo "CSS check FAILED ($ERRORS file(s) with violations)."
    echo "Use hex values or var(--color-*) tokens instead of named colors."
    exit 1
fi

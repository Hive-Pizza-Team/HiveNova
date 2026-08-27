#!/usr/bin/env bash
# Symlink identical gebaeude assets from the canonical nova theme into other themes.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CANONICAL="${ROOT}/styles/theme/nova/gebaeude"

if [[ ! -d "${CANONICAL}" ]]; then
	echo "Canonical gebaeude directory not found: ${CANONICAL}" >&2
	exit 1
fi

link_identical_theme() {
	local theme="$1"
	local target="${ROOT}/styles/theme/${theme}/gebaeude"

	if [[ ! -d "${target}" ]]; then
		echo "Skipping ${theme}: no gebaeude directory"
		return 0
	fi

	if diff -rq "${CANONICAL}" "${target}" >/dev/null 2>&1; then
		echo "Replacing ${theme}/gebaeude with symlink to nova/gebaeude"
		rm -rf "${target}"
		ln -s ../nova/gebaeude "${target}"
		return 0
	fi

	echo "Linking identical files in ${theme}/gebaeude"
	find "${CANONICAL}" -type f | while read -r src; do
		rel="${src#${CANONICAL}/}"
		dest="${target}/${rel}"
		if [[ -f "${dest}" ]] && cmp -s "${src}" "${dest}"; then
			rm -f "${dest}"
			mkdir -p "$(dirname "${dest}")"
			ln -s "../../nova/gebaeude/${rel}" "${dest}"
		fi
	done
}

link_identical_theme hive
link_identical_theme gow
link_identical_theme EpicBlueXIII

echo "Done."

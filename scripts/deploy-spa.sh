#!/usr/bin/env bash
# Build (unless skipped) and rsync the minified React SPA onto the game host.
#
# The Vite app is served at /react/, so files land in <game-root>/react/ — not
# the PHP document root. Dumping hashed assets into /var/www/NextMoon/ with
# --delete would wipe the game.
#
# Usage:
#   ./scripts/deploy-spa.sh user@host
#   HN_DEPLOY_HOST=user@moon.example ./scripts/deploy-spa.sh
#   ./scripts/deploy-spa.sh user@host --dry-run
#   ./scripts/deploy-spa.sh user@host --skip-build
#
# Env:
#   HN_DEPLOY_HOST         SSH target (required unless passed as argv)
#   HN_DEPLOY_ROOT         Remote game root (default: /var/www/NextMoon)
#   HN_DEPLOY_PATH         Remote SPA directory (default: $HN_DEPLOY_ROOT/react)
#   HN_DEPLOY_SSH          Extra ssh options (e.g. '-i ~/.ssh/id_deploy')
#   HN_DEPLOY_RSYNC_PATH   Remote rsync binary (default: rsync; use 'sudo rsync' if needed)
#   SKIP_BUILD=1           Same as --skip-build
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

DEFAULT_GAME_ROOT="/var/www/NextMoon"

usage() {
	cat <<'EOF'
Usage: ./scripts/deploy-spa.sh [user@]host [options]

Build the React SPA (Docker) and rsync ./react/ to the remote game tree.

Options:
  --dry-run          Show rsync actions; do not write on the server
  --skip-build       Use the existing ./react/ tree (must contain index.html)
  -h, --help         Show this help

Environment:
  HN_DEPLOY_HOST         SSH target if not given as the first argument
  HN_DEPLOY_ROOT         Game root (default: /var/www/NextMoon)
  HN_DEPLOY_PATH         SPA directory (default: $HN_DEPLOY_ROOT/react)
  HN_DEPLOY_SSH          Extra ssh options
  HN_DEPLOY_RSYNC_PATH   Remote rsync command (default: rsync)
  SKIP_BUILD=1           Skip ./scripts/build-spa.sh
EOF
}

HOST="${HN_DEPLOY_HOST:-}"
GAME_ROOT="${HN_DEPLOY_ROOT:-$DEFAULT_GAME_ROOT}"
DEST="${HN_DEPLOY_PATH:-}"
SSH_OPTS="${HN_DEPLOY_SSH:-}"
REMOTE_RSYNC="${HN_DEPLOY_RSYNC_PATH:-rsync}"
DRY_RUN=0
SKIP_BUILD="${SKIP_BUILD:-0}"

while [[ $# -gt 0 ]]; do
	case "$1" in
		-h|--help)
			usage
			exit 0
			;;
		--dry-run)
			DRY_RUN=1
			shift
			;;
		--skip-build)
			SKIP_BUILD=1
			shift
			;;
		-*)
			echo "error: unknown option: $1" >&2
			usage >&2
			exit 1
			;;
		*)
			if [[ -n "$HOST" ]]; then
				echo "error: unexpected argument: $1" >&2
				usage >&2
				exit 1
			fi
			HOST="$1"
			shift
			;;
	esac
done

if [[ -z "$HOST" ]]; then
	echo "error: SSH target required (pass user@host or set HN_DEPLOY_HOST)" >&2
	usage >&2
	exit 1
fi

if [[ -z "$DEST" ]]; then
	DEST="${GAME_ROOT%/}/react"
fi
DEST="${DEST%/}"

# Refuse --delete into the PHP tree if someone points HN_DEPLOY_PATH at the game root.
root_norm="${GAME_ROOT%/}"
if [[ "$DEST" == "$root_norm" ]]; then
	echo "error: refusing to rsync --delete into $DEST (that is the PHP game root)." >&2
	echo "       SPA files belong in ${root_norm}/react  (override with HN_DEPLOY_PATH)" >&2
	exit 1
fi

SRC="$ROOT/react"
if [[ "$SKIP_BUILD" != "1" ]]; then
	"$ROOT/scripts/build-spa.sh"
fi

if [[ ! -f "$SRC/index.html" ]]; then
	echo "error: $SRC/index.html is missing. Run ./scripts/build-spa.sh first." >&2
	exit 1
fi

if ! command -v rsync >/dev/null 2>&1; then
	echo "error: rsync is required on this machine." >&2
	exit 1
fi

RSYNC_ARGS=(-a -z --delete --human-readable --itemize-changes)
RSYNC_ARGS+=(--exclude '.DS_Store' --exclude '*.map')
if [[ "$DRY_RUN" == "1" ]]; then
	RSYNC_ARGS+=(-n)
fi

SSH_RSH="ssh"
if [[ -n "$SSH_OPTS" ]]; then
	SSH_RSH="ssh ${SSH_OPTS}"
fi

echo "Deploying SPA → ${HOST}:${DEST}/"
rsync "${RSYNC_ARGS[@]}" \
	-e "$SSH_RSH" \
	--rsync-path "$REMOTE_RSYNC" \
	"${SRC}/" \
	"${HOST}:${DEST}/"

if [[ "$DRY_RUN" == "1" ]]; then
	echo "Dry run only; nothing written on ${HOST}."
	exit 0
fi

echo "SPA deployed. Confirm ${HOST}:${DEST}/index.html then open /react/"

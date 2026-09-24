#!/usr/bin/env bash
# Apply origin/master on the game host: git pull, composer, migrate, Smarty cache.
#
# GitHub Actions scp's this file to the host and runs it as the deploy user so
# the first merge works before the checkout contains the script. Operators
# can also run it in the game tree.
#
# Usage (on the game host):
#   ./scripts/deploy.sh
#   HN_DEPLOY_ROOT=/var/www/HiveNova ./scripts/deploy.sh
#   ./scripts/deploy.sh --dry-run
#
# Usage (from a laptop / Actions runner):
#   scp scripts/deploy.sh deploy@host:/tmp/hivenova-deploy.sh
#   ssh deploy@host "HN_DEPLOY_ROOT=/var/www/NextMoon bash /tmp/hivenova-deploy.sh"
#
# Env:
#   HN_DEPLOY_ROOT      Game checkout (default: script's repo, else /var/www/NextMoon)
#   HN_DEPLOY_BRANCH    Git branch to fast-forward (default: master)
#   HN_DEPLOY_POST_CMD  Optional shell run after a successful deploy (e.g. php-fpm reload)
set -euo pipefail

DEFAULT_GAME_ROOT="/var/www/NextMoon"

usage() {
	cat <<'EOF'
Usage: ./scripts/deploy.sh [options]

Fast-forward the game checkout, install PHP deps, apply pending migrations,
and drop compiled Smarty files. Safe to re-run; migrate.php is a no-op when
the DB is current.

Options:
  --dry-run          Print actions; git fetch only; migrate --dry-run
  --skip-git        Do not fetch/merge (use after a pull in the same session)
  -h, --help        Show this help

Environment:
  HN_DEPLOY_ROOT      Game checkout path
  HN_DEPLOY_BRANCH    Branch to deploy (default: master)
  HN_DEPLOY_POST_CMD  Command after success (not run in --dry-run)
EOF
}

DRY_RUN=0
SKIP_GIT=0

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
		--skip-git)
			SKIP_GIT=1
			shift
			;;
		-*)
			echo "error: unknown option: $1" >&2
			usage >&2
			exit 1
			;;
		*)
			echo "error: unexpected argument: $1" >&2
			usage >&2
			exit 1
			;;
	esac
done

src="${BASH_SOURCE[0]:-}"
if [[ -n "${HN_DEPLOY_ROOT:-}" ]]; then
	ROOT="${HN_DEPLOY_ROOT}"
elif [[ -n "$src" && "$src" != /dev/stdin && "$src" != stdin && -f "$src" ]]; then
	ROOT="$(cd "$(dirname "$src")/.." && pwd)"
else
	ROOT="$DEFAULT_GAME_ROOT"
fi
ROOT="${ROOT%/}"

BRANCH="${HN_DEPLOY_BRANCH:-master}"

run() {
	if [[ "$DRY_RUN" == 1 ]]; then
		printf '(dry-run)'
		printf ' %q' "$@"
		printf '\n'
		return 0
	fi
	"$@"
}

if [[ ! -d "$ROOT" ]]; then
	echo "error: game root does not exist: $ROOT" >&2
	exit 1
fi

cd "$ROOT"

if [[ ! -e .git ]]; then
	echo "error: $ROOT is not a git checkout" >&2
	exit 1
fi

if command -v flock >/dev/null 2>&1; then
	exec 9>/tmp/hivenova-deploy.lock
	if ! flock -n 9; then
		echo "error: another deploy holds /tmp/hivenova-deploy.lock" >&2
		exit 1
	fi
fi

echo "Deploy root : $ROOT"
echo "Branch      : $BRANCH"
echo "HEAD before : $(git rev-parse --short HEAD) $(git log -1 --format=%s)"

if [[ "$SKIP_GIT" != 1 ]]; then
	if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
		echo "error: tracked files have local changes; refusing to deploy." >&2
		git status --short --untracked-files=no >&2
		echo "         Discard them on the host with: git reset --hard origin/${BRANCH}" >&2
		exit 1
	fi
	if ! git remote get-url origin >/dev/null 2>&1; then
		echo "error: no git remote named origin in $ROOT" >&2
		exit 1
	fi
	GIT_TERMINAL_PROMPT=0 run git fetch origin "$BRANCH" </dev/null
	if [[ "$DRY_RUN" == 1 ]]; then
		echo "(dry-run) would: git checkout $BRANCH && git merge --ff-only origin/$BRANCH"
		echo "origin/${BRANCH} : $(git rev-parse --short "origin/${BRANCH}" 2>/dev/null || echo unknown)"
	else
		if git show-ref --verify --quiet "refs/heads/${BRANCH}"; then
			git checkout "$BRANCH" </dev/null
		else
			git checkout -b "$BRANCH" --track "origin/${BRANCH}" </dev/null
		fi
		git merge --ff-only "origin/${BRANCH}" </dev/null
	fi
fi

echo "HEAD after  : $(git rev-parse --short HEAD) $(git log -1 --format=%s)"

if ! command -v php >/dev/null 2>&1; then
	echo "error: php is not on PATH" >&2
	exit 1
fi

COMPOSER=()
if command -v composer >/dev/null 2>&1; then
	COMPOSER=(composer)
elif [[ -f "$ROOT/composer.phar" ]]; then
	COMPOSER=(php "$ROOT/composer.phar")
else
	echo "error: composer not found (install composer or place composer.phar in $ROOT)" >&2
	exit 1
fi

echo "==> composer install --no-dev"
run "${COMPOSER[@]}" install --no-dev --no-interaction --prefer-dist --optimize-autoloader </dev/null

if [[ ! -f includes/config.php || ! -s includes/config.php ]]; then
	echo "error: includes/config.php is missing; cannot migrate." >&2
	exit 1
fi

echo "==> php migrate.php status"
php migrate.php status

echo "==> php migrate.php run"
if [[ "$DRY_RUN" == 1 ]]; then
	php migrate.php run --dry-run
else
	php migrate.php run
fi

echo "==> clear compiled Smarty (keep cache/sessions)"
if [[ "$DRY_RUN" == 1 ]]; then
	echo "(dry-run) find cache -maxdepth 1 -type f ! -name '.htaccess' -delete"
	echo "(dry-run) find cache/templates -type f ! -name '.htaccess' -delete"
else
	if [[ -d cache ]]; then
		find cache -maxdepth 1 -type f ! -name '.htaccess' -delete
	fi
	if [[ -d cache/templates ]]; then
		find cache/templates -type f ! -name '.htaccess' -delete
	fi
fi

if [[ -n "${HN_DEPLOY_POST_CMD:-}" ]]; then
	echo "==> HN_DEPLOY_POST_CMD"
	run bash -c "${HN_DEPLOY_POST_CMD}"
fi

echo "Deploy finished."

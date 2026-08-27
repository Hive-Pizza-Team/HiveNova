#!/usr/bin/env bash
# release.sh — tag origin/master and create a GitHub Release.
#
# Usage:
#   ./scripts/release.sh v1.2.3
#   ./scripts/release.sh v1.2.3 --dry-run
#   ./scripts/release.sh v1.2.3 --message "v1.2.3: short summary"
#
# See docs/releasing.md for process and SemVer rules.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

usage() {
  cat <<'EOF'
Usage: ./scripts/release.sh vX.Y.Z [--dry-run] [--message "tag message"]

Creates an annotated tag on the current master tip, pushes it, and opens a
GitHub Release with auto-generated notes.

Options:
  --dry-run              Validate only; do not tag, push, or create a release
  --message <text>       Annotated tag message (default: "<version>: release")
  -h, --help             Show this help
EOF
}

VERSION=""
DRY_RUN=0
TAG_MESSAGE=""

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
    --message)
      [[ $# -ge 2 ]] || { echo "error: --message requires a value" >&2; exit 1; }
      TAG_MESSAGE="$2"
      shift 2
      ;;
    -*)
      echo "error: unknown option: $1" >&2
      usage >&2
      exit 1
      ;;
    *)
      if [[ -n "$VERSION" ]]; then
        echo "error: unexpected argument: $1" >&2
        usage >&2
        exit 1
      fi
      VERSION="$1"
      shift
      ;;
  esac
done

if [[ -z "$VERSION" ]]; then
  echo "error: version required (e.g. v1.2.3)" >&2
  usage >&2
  exit 1
fi

if [[ ! "$VERSION" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "error: version must look like v1.2.3 (got: $VERSION)" >&2
  exit 1
fi

if [[ -z "$TAG_MESSAGE" ]]; then
  TAG_MESSAGE="${VERSION}: release"
fi

if ! command -v gh >/dev/null 2>&1; then
  echo "error: gh CLI is required" >&2
  exit 1
fi

BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$BRANCH" != "master" ]]; then
  echo "error: must run on master (currently on $BRANCH)" >&2
  exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
  echo "error: working tree is not clean; commit or stash first" >&2
  git status --short >&2
  exit 1
fi

git fetch origin master --tags

LOCAL="$(git rev-parse HEAD)"
REMOTE="$(git rev-parse origin/master)"
if [[ "$LOCAL" != "$REMOTE" ]]; then
  echo "error: local master ($LOCAL) differs from origin/master ($REMOTE)" >&2
  echo "pull or reset to origin/master before releasing" >&2
  exit 1
fi

if git rev-parse -q --verify "refs/tags/$VERSION" >/dev/null; then
  echo "error: tag $VERSION already exists locally" >&2
  exit 1
fi

if git ls-remote --tags origin "refs/tags/$VERSION" | grep -q .; then
  echo "error: tag $VERSION already exists on origin" >&2
  exit 1
fi

if gh release view "$VERSION" >/dev/null 2>&1; then
  echo "error: GitHub Release $VERSION already exists" >&2
  exit 1
fi

SHORT="$(git rev-parse --short HEAD)"
SUBJECT="$(git log -1 --format='%s')"

echo "Release plan"
echo "  version:  $VERSION"
echo "  commit:   $SHORT ($SUBJECT)"
echo "  message:  $TAG_MESSAGE"
echo "  dry-run:  $DRY_RUN"

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "dry-run complete; no changes made"
  exit 0
fi

git tag -a "$VERSION" -m "$TAG_MESSAGE"
git push origin "$VERSION"
gh release create "$VERSION" --generate-notes --title "$VERSION"

echo "Created tag and release $VERSION"
echo "Edit notes: gh release view $VERSION --web"

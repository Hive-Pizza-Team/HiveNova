#!/usr/bin/env bash
# Build the React SPA inside Docker and write static files to ./react/
# Does not install Node or npm on the host.
#
# Usage:
#   ./scripts/build-spa.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if ! command -v docker >/dev/null 2>&1; then
  echo "docker is required to build the SPA (Node/npm stay inside the container)." >&2
  exit 1
fi

export DOCKER_BUILDKIT=1

DEST="$ROOT/react"
mkdir -p "$DEST"

echo "Building HiveNova SPA in Docker → $DEST"
docker build \
  --output "type=local,dest=$DEST" \
  -f frontend/Dockerfile \
  frontend

if [[ ! -f "$DEST/index.html" ]]; then
  echo "SPA build failed: $DEST/index.html is missing." >&2
  exit 1
fi

echo "SPA ready at /react/ (open after: php -S localhost:8000 router.php)"

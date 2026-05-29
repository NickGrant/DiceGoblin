#!/bin/sh
set -eu

LOCK_HASH_FILE="node_modules/.package-lock.sha256"
CURRENT_LOCK_HASH="$(sha256sum package-lock.json | awk '{print $1}')"
NEEDS_INSTALL=0

if [ ! -d node_modules ]; then
  NEEDS_INSTALL=1
elif [ ! -x node_modules/.bin/ng ]; then
  NEEDS_INSTALL=1
elif [ ! -f "$LOCK_HASH_FILE" ]; then
  NEEDS_INSTALL=1
elif [ "$(cat "$LOCK_HASH_FILE")" != "$CURRENT_LOCK_HASH" ]; then
  NEEDS_INSTALL=1
fi

if [ "$NEEDS_INSTALL" -eq 1 ]; then
  echo "Installing frontend dependencies for the current workspace..."
  npm ci --no-audit --no-fund
  printf '%s' "$CURRENT_LOCK_HASH" > "$LOCK_HASH_FILE"
else
  echo "Frontend dependencies already match package-lock.json."
fi

exec ./node_modules/.bin/ng serve --host 0.0.0.0 --port 5173

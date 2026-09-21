#!/usr/bin/env bash
# Import moodle-ifsc-mooc data into this repository.
# Run on Daniel's Mac where ~/Sites/moodle-ifsc-mooc exists.
set -euo pipefail

SOURCE="${MOODLE_SOURCE:-$HOME/Sites/moodle-ifsc-mooc}"
TARGET="${MOODLE_TARGET:-$(git rev-parse --show-toplevel)}"
BRANCH="${MOODLE_BRANCH:-cursor/import-moodle-ifsc-mooc-846a}"

if [[ ! -d "$SOURCE" ]]; then
  echo "error: source not found: $SOURCE" >&2
  exit 1
fi

cd "$TARGET"
git fetch origin "$BRANCH" 2>/dev/null || true
git checkout "$BRANCH" 2>/dev/null || git checkout -b "$BRANCH"

echo "Importing from $SOURCE into $TARGET (preserving layout)..."
rsync -av --delete --exclude='.git' --exclude='.DS_Store' "$SOURCE/" "$TARGET/"

# Restore repo-specific files if rsync overwrote them
git checkout HEAD -- README.md scripts/import-moodle-ifsc-mooc.sh 2>/dev/null || true

git add -A
if git diff --cached --quiet; then
  echo "No changes to commit."
  exit 0
fi

git commit -m "Import moodle-ifsc-mooc course data

Copy from ~/Sites/moodle-ifsc-mooc preserving original directory layout."
git push -u origin "$BRANCH"

echo "Import complete. Branch: $BRANCH"

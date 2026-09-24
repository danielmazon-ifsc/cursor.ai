#!/usr/bin/env bash
# Import moodle-pdi data into this repository.
# Run on Daniel's Mac where ~/Sites/moodle-pdi exists (self-hosted worker
# f1fefa9c-22d0-5be4-951a-c3fcccc2e075). Cloud VMs cannot see that filesystem.
set -euo pipefail

SOURCE="${MOODLE_SOURCE:-$HOME/Sites/moodle-pdi}"
TARGET="${MOODLE_TARGET:-$(git rev-parse --show-toplevel 2>/dev/null || true)}"
BRANCH="${MOODLE_BRANCH:-cursor/import-moodle-pdi-b6f0}"
REMOTE="${MOODLE_REMOTE:-origin}"

if [[ ! -d "$SOURCE" ]]; then
  echo "error: source not found: $SOURCE" >&2
  echo "This script must run on the Mac that has ~/Sites/moodle-pdi." >&2
  exit 1
fi

if [[ -z "$TARGET" || ! -d "$TARGET/.git" ]]; then
  # If invoked from the Moodle tree rather than cursor.ai, clone/fetch the project repo.
  TARGET="${MOODLE_TARGET:-$HOME/Sites/cursor.ai}"
  if [[ ! -d "$TARGET/.git" ]]; then
    echo "Cloning danielmazon-ifsc/cursor.ai into $TARGET ..."
    git clone "https://github.com/danielmazon-ifsc/cursor.ai.git" "$TARGET"
  fi
fi

cd "$TARGET"
git fetch "$REMOTE" "$BRANCH" 2>/dev/null || git fetch "$REMOTE" main
if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
  git checkout "$BRANCH"
  git pull --ff-only "$REMOTE" "$BRANCH" 2>/dev/null || true
elif git show-ref --verify --quiet "refs/remotes/$REMOTE/$BRANCH"; then
  git checkout -B "$BRANCH" "$REMOTE/$BRANCH"
else
  git checkout -b "$BRANCH" "$REMOTE/main"
fi

echo "Importing from $SOURCE into $TARGET (preserving layout)..."
rsync -av --delete \
  --exclude='.git' \
  --exclude='.DS_Store' \
  --exclude='moodledata/' \
  "$SOURCE/" "$TARGET/"

# Keep repo-specific files if rsync overwrote them from a Moodle-only tree.
git checkout HEAD -- README.md scripts/import-moodle-pdi.sh 2>/dev/null || true

# Redact DB password if config.php was copied.
if [[ -f "$TARGET/config.php" ]]; then
  perl -pi -e 's/(\$CFG->dbpass\s*=\s*)'\''[^'\'']*'\''/$1'\'''\''/' "$TARGET/config.php"
fi

git add -A
if git diff --cached --quiet; then
  echo "No changes to commit."
  exit 0
fi

git commit -m "Import moodle-pdi Moodle installation

Copy from ~/Sites/moodle-pdi preserving original directory layout.
DB password in config.php redacted when present."
git push -u "$REMOTE" "$BRANCH"

echo "Import complete. Branch: $BRANCH"

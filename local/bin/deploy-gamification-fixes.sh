#!/usr/bin/env bash
# Deploy PDI gamification stack (local plugins + mod/video + course formats).
#
# Usage:
#   ./local/bin/deploy-gamification-fixes.sh /path/to/production/moodle
#   MOODLE_ROOT=/var/www/moodle ./local/bin/deploy-gamification-fixes.sh
#
set -euo pipefail

LOCAL_PLUGINS=(studypace coin autobadge dashboard notifications profile)

# Paths relative to Moodle root (source = same tree as this script's grandparent).
MOODLE_COMPONENTS=(
    mod/video
    course/format/specialization
    course/format/saladeconferencias
)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
SOURCE_LOCAL="${SOURCE_ROOT}/local"
TARGET_ROOT="${1:-${MOODLE_ROOT:-}}"

if [[ -z "${TARGET_ROOT}" || ! -d "${TARGET_ROOT}" ]]; then
    echo "Usage: $0 /path/to/target/moodle" >&2
    echo "   or: MOODLE_ROOT=/path/to/moodle $0" >&2
    exit 1
fi

echo "Source root: ${SOURCE_ROOT}"
echo "Target root: ${TARGET_ROOT}"
echo

for name in "${LOCAL_PLUGINS[@]}"; do
    src="${SOURCE_LOCAL}/${name}"
    dst="${TARGET_ROOT}/local/${name}"
    if [[ ! -d "${src}" ]]; then
        echo "Missing source plugin: ${src}" >&2
        exit 1
    fi
    echo "Syncing local_${name}..."
    rsync -a --delete \
        --exclude '.DS_Store' \
        "${src}/" "${dst}/"
done

for relpath in "${MOODLE_COMPONENTS[@]}"; do
    src="${SOURCE_ROOT}/${relpath}"
    dst="${TARGET_ROOT}/${relpath}"
    if [[ ! -d "${src}" ]]; then
        echo "Missing source component: ${src}" >&2
        exit 1
    fi
    echo "Syncing ${relpath}..."
    rsync -a --delete \
        --exclude '.DS_Store' \
        "${src}/" "${dst}/"
done

echo
echo "Running Moodle CLI upgrade and cache purge on target..."
(
    cd "${TARGET_ROOT}"
    php admin/cli/upgrade.php --non-interactive
    php admin/cli/purge_caches.php
)

echo
echo "Done. Verify:"
echo "  - Site administration → Notifications (no pending upgrades)"
echo "  - Capability local/coin:viewledger for managers (extrato)"
echo "  - Admin studypace → lacunas / repair (dry-run first if needed)"
echo "  - Spot-check coin_stack vs coin_ledger sums after upgrade 2026052639"

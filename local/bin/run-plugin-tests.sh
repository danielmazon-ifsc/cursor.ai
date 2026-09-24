#!/usr/bin/env bash
# Run PHPUnit for PDI local plugins changed in the gamification fix rollout.
#
# Requires in config.php:
#   $CFG->phpunit_prefix = 'phpu_';
#   $CFG->phpunit_dataroot = '/path/to/phpunit_moodledata';
#
set -euo pipefail

MOODLE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${MOODLE_ROOT}"

if ! grep -q 'phpunit_dataroot' config.php; then
    echo "config.php must define \$CFG->phpunit_dataroot and \$CFG->phpunit_prefix" >&2
    exit 1
fi

DATAROOT="$(grep 'phpunit_dataroot' config.php | sed -E "s/.*= *'([^']+)'.*/\1/")"
mkdir -p "${DATAROOT}"

echo "Initializing PHPUnit environment (once)..."
php admin/tool/phpunit/cli/init.php

TESTS=(
    local/coin/tests/coin_stack_test.php
    local/coin/tests/observer_test.php
    local/autobadge/tests/autobadge_test.php
    local/profile/tests/enrol_helper_test.php
)

echo
echo "Running tests..."
vendor/bin/phpunit "${TESTS[@]}"

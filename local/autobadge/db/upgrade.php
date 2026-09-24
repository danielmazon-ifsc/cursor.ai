<?php

defined('MOODLE_INTERNAL') || die;

function xmldb_local_autobadge_upgrade($oldversion) {
    if ($oldversion < 2026052637) {
        upgrade_plugin_savepoint(true, 2026052637, 'local', 'autobadge');
    }

    if ($oldversion < 2026052638) {
        upgrade_plugin_savepoint(true, 2026052638, 'local', 'autobadge');
    }

    return true;
}

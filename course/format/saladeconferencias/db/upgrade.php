<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upgrade steps for format_saladeconferencias.
 *
 * @package    format_saladeconferencias
 * @copyright  2025 Viddia (http://viddia.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_format_saladeconferencias_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026052625) {
        require_once(__DIR__ . '/../lib.php');

        $courseids = $DB->get_fieldset_sql(
            "SELECT id FROM {course} WHERE format = ? AND id > ?",
            ['saladeconferencias', SITEID]
        );

        foreach ($courseids as $courseid) {
            format_saladeconferencias_ensure_settings_block((int) $courseid);
        }

        upgrade_plugin_savepoint(true, 2026052625, 'format', 'saladeconferencias');
    }

    if ($oldversion < 2026052646) {
        require_once(__DIR__ . '/../lib.php');
        $added = format_saladeconferencias_backfill_video_completion_criteria();
        if ($added > 0) {
            mtrace('  ... format_saladeconferencias: added ' . $added . ' video(s) to course completion criteria');
        }
        upgrade_plugin_savepoint(true, 2026052646, 'format', 'saladeconferencias');
    }

    if ($oldversion < 2026052647) {
        upgrade_plugin_savepoint(true, 2026052647, 'format', 'saladeconferencias');
    }

    if ($oldversion < 2026052648) {
        upgrade_plugin_savepoint(true, 2026052648, 'format', 'saladeconferencias');
    }

    return true;
}

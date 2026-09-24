<?php

/**
 * Plugin upgrade code
 *
 * @package   local_profile
 * @copyright 2024 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die;

function xmldb_local_profile_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025031703) {

        // Define table local_profile to be created.
        $table = new xmldb_table('local_profile');

        // Adding fields to table local_profile.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('updatetime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table local_profile.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table local_profile.
        $table->add_index('userid', XMLDB_INDEX_UNIQUE, array('userid'));

        // Conditionally launch create table for local_profile.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Profile savepoint reached.
        upgrade_plugin_savepoint(true, 2025031703, 'local', 'profile');
    }

    if ($oldversion < 2025031801) {

        // Define field nummonths to be added to local_profile.
        $table = new xmldb_table('local_profile');
        $field = new xmldb_field('nummonths', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null, 'updatetime');

        // Conditionally launch add field nummonths.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Profile savepoint reached.
        upgrade_plugin_savepoint(true, 2025031801, 'local', 'profile');
    }

    if ($oldversion < 2025051601) {

        // Define field numupdates to be added to local_profile.
        $table = new xmldb_table('local_profile');
        $field = new xmldb_field('numupdates', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'nummonths');

        // Conditionally launch add field numupdates.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Profile savepoint reached.
        upgrade_plugin_savepoint(true, 2025051601, 'local', 'profile');
    }

    if ($oldversion < 2025060901) {

        // Define field hiredate to be added to local_profile.
        $table = new xmldb_table('local_profile');
        $field = new xmldb_field('hiredate', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'numupdates');

        // Conditionally launch add field hiredate.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Profile savepoint reached.
        upgrade_plugin_savepoint(true, 2025060901, 'local', 'profile');
    }

    if ($oldversion < 2026052604) {
        // One-shot backfill: re-apply the Eixo auto-progression for users
        // who completed earlier Eixos BEFORE the observer that hooks
        // \local_studypace\event\course_completed into the enrolment
        // helper shipped (2026052602). studypace::mark_course_completed()
        // only fires that event once — the first time it records the
        // completion — so any user whose actualcompletion was already
        // written before the observer existed is stuck without auto-enrol
        // into the next Eixo. Re-firing the event isn't safe (would also
        // re-emit planned_completion and re-send badges/messages), so we
        // skip the event and call the enrolment helper directly. It's
        // idempotent — already-enrolled users and non-Eixo completions
        // are no-ops — so the backfill is safe to ship and safe to re-run
        // on partial upgrades.
        global $CFG;
        require_once($CFG->dirroot . '/local/profile/lib.php');
        $created = local_profile_backfill_eixo_progression();
        // Logged via mtrace so the CLI upgrade and the web upgrade log
        // both show how much catch-up the site needed. Skipped for
        // 0 to keep silent upgrades quiet.
        if ($created > 0) {
            mtrace('  ... local_profile: backfilled ' . $created . ' Eixo auto-enrolment(s)');
        }
        upgrade_plugin_savepoint(true, 2026052604, 'local', 'profile');
    }

    if ($oldversion < 2026052605) {
        // Re-run the Eixo backfill now that the enrolment helper uses the
        // permissive plugin search (manual → self → first other enabled).
        // The 2026052604 backfill called local_profile_enrol_in_next_eixo
        // which still went through the strict manual-only path, so on
        // production sites whose Eixos use enrol_self every retroactive
        // enrolment was silently skipped. Re-running here picks them up
        // without re-firing the studypace events (mark_course_completed
        // already short-circuits on actualcompletion, and the backfill
        // helper bypasses mark_course_completed entirely).
        //
        // Idempotent: local_profile_enrol_in_next_eixo() checks
        // is_enrolled() before each insert, so users already moved by the
        // first backfill pass (the lucky ones whose Eixos had manual on)
        // are no-ops on this pass.
        global $CFG;
        require_once($CFG->dirroot . '/local/profile/lib.php');
        $created = local_profile_backfill_eixo_progression();
        if ($created > 0) {
            mtrace('  ... local_profile: re-backfilled ' . $created
                    . ' Eixo auto-enrolment(s) via permissive enrol helper');
        }
        upgrade_plugin_savepoint(true, 2026052605, 'local', 'profile');
    }

    return true;
}

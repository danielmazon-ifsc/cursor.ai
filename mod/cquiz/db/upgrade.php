<?php

/**
 * Upgrade script for the cquiz module.
 *
 * @package    mod_cquiz
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Cquiz module upgrade function.
 * @param string $oldversion the version we are upgrading from.
 */
function xmldb_cquiz_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2014052800) {

        // Define field completionattemptsexhausted to be added to cquiz.
        $table = new xmldb_table('cquiz');
        $field = new xmldb_field('completionattemptsexhausted', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'showblocks');

        // Conditionally launch add field completionattemptsexhausted.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Cquiz savepoint reached.
        upgrade_mod_savepoint(true, 2014052800, 'cquiz');
    }

    if ($oldversion < 2014052801) {
        // Define field completionpass to be added to cquiz.
        $table = new xmldb_table('cquiz');
        $field = new xmldb_field('completionpass', XMLDB_TYPE_INTEGER, '1', null, null, null, 0, 'completionattemptsexhausted');

        // Conditionally launch add field completionpass.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Cquiz savepoint reached.
        upgrade_mod_savepoint(true, 2014052801, 'cquiz');
    }

    // Moodle v2.8.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2015030500) {
        // Define field requireprevious to be added to cquiz_slots.
        $table = new xmldb_table('cquiz_slots');
        $field = new xmldb_field('requireprevious', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, 0, 'page');

        // Conditionally launch add field page.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Cquiz savepoint reached.
        upgrade_mod_savepoint(true, 2015030500, 'cquiz');
    }

    if ($oldversion < 2015030900) {
        // Define field canredoquestions to be added to cquiz.
        $table = new xmldb_table('cquiz');
        $field = new xmldb_field('canredoquestions', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, 0, 'preferredbehaviour');

        // Conditionally launch add field completionpass.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Cquiz savepoint reached.
        upgrade_mod_savepoint(true, 2015030900, 'cquiz');
    }

    if ($oldversion < 2015032300) {

        // Define table cquiz_sections to be created.
        $table = new xmldb_table('cquiz_sections');

        // Adding fields to table cquiz_sections.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cquizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('firstslot', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('heading', XMLDB_TYPE_CHAR, '1333', null, null, null, null);
        $table->add_field('shufflequestions', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table cquiz_sections.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('cquizid', XMLDB_KEY_FOREIGN, array('cquizid'), 'cquiz', array('id'));

        // Adding indexes to table cquiz_sections.
        $table->add_index('cquizid-firstslot', XMLDB_INDEX_UNIQUE, array('cquizid', 'firstslot'));

        // Conditionally launch create table for cquiz_sections.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Cquiz savepoint reached.
        upgrade_mod_savepoint(true, 2015032300, 'cquiz');
    }

    if ($oldversion < 2015032301) {

        // Create a section for each cquiz.
        $DB->execute("
                INSERT INTO {cquiz_sections}
                            (cquizid, firstslot, heading, shufflequestions)
                     SELECT  id,     1,         ?,       shufflequestions
                       FROM {cquiz}
                ", array(''));

        // Cquiz savepoint reached.
        upgrade_mod_savepoint(true, 2015032301, 'cquiz');
    }

    if ($oldversion < 2015032302) {

        // Define field shufflequestions to be dropped from cquiz.
        $table = new xmldb_table('cquiz');
        $field = new xmldb_field('shufflequestions');

        // Conditionally launch drop field shufflequestions.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Cquiz savepoint reached.
        upgrade_mod_savepoint(true, 2015032302, 'cquiz');
    }

    if ($oldversion < 2015032303) {

        // Drop corresponding admin settings.
        unset_config('shufflequestions', 'cquiz');
        unset_config('shufflequestions_adv', 'cquiz');

        // Cquiz savepoint reached.
        upgrade_mod_savepoint(true, 2015032303, 'cquiz');
    }

    // Moodle v2.9.0 release upgrade line.
    // Put any upgrade step following this.
    // Moodle v3.0.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2016032600) {
        // Update cquiz_sections to repair cquizzes what were broken by MDL-53507.
        $problemcquizzes = $DB->get_records_sql("
                SELECT cquizid, MIN(firstslot) AS firstsectionfirstslot
                FROM {cquiz_sections}
                GROUP BY cquizid
                HAVING MIN(firstslot) > 1");

        if ($problemcquizzes) {
            $pbar = new progress_bar('upgradecquizfirstsection', 500, true);
            $total = count($problemcquizzes);
            $done = 0;
            foreach ($problemcquizzes as $problemcquiz) {
                $DB->set_field('cquiz_sections', 'firstslot', 1, array('cquizid' => $problemcquiz->cquizid,
                    'firstslot' => $problemcquiz->firstsectionfirstslot));
                $done += 1;
                $pbar->update($done, $total, "Fixing cquiz layouts - {$done}/{$total}.");
            }
        }

        // Cquiz savepoint reached.
        upgrade_mod_savepoint(true, 2016032600, 'cquiz');
    }

    return true;
}

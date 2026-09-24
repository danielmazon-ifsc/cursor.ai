<?php

defined('MOODLE_INTERNAL') || die;

function xmldb_format_specialization_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2018102502) {

        // Define table format_specialization to be created.
        $table = new xmldb_table('format_specialization');

        // Adding fields to table format_specialization.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table format_specialization.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table format_specialization.
        $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, array('courseid'));

        // Conditionally launch create table for format_specialization.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table format_specialization_course to be created.
        $table = new xmldb_table('format_specialization_course');

        // Adding fields to table format_specialization_course.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('specializationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sequence', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table format_specialization_course.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table format_specialization_course.
        $table->add_index('specializationid', XMLDB_INDEX_NOTUNIQUE, array('specializationid'));
        $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, array('courseid'));
        $table->add_index('sequence', XMLDB_INDEX_NOTUNIQUE, array('sequence'));

        // Conditionally launch create table for format_specialization_course.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Specialization savepoint reached.
        upgrade_plugin_savepoint(true, 2018102502, 'format', 'specialization');
    }

    if ($oldversion < 2025112501) {
        // Backfill format_specialization rows for every course that is in this
        // format but doesn't yet have its companion row. The old constructor
        // used to create the row lazily on every instantiation; now it only
        // happens via observers, so we need a one-time sweep for existing data.
        // Batched to keep memory bounded on large sites - get_fieldset_sql
        // would otherwise load the whole list.
        $batchsize = 500;
        do {
            $sql = "SELECT c.id FROM {course} c "
                    . "WHERE c.format = :format "
                    . "AND NOT EXISTS ("
                    . "    SELECT 1 FROM {format_specialization} fs WHERE fs.courseid = c.id"
                    . ")";
            $missing = $DB->get_fieldset_sql($sql, ['format' => 'specialization'], 0, $batchsize);
            foreach ($missing as $courseid) {
                $row = new \stdClass();
                $row->courseid = (int) $courseid;
                $DB->insert_record('format_specialization', $row);
            }
        } while (count($missing) === $batchsize);

        // Also remove orphan rows for courses that no longer have the format
        // (or were deleted), since the constructor used to create them
        // erroneously for every read of course_get_format().
        $orphansql = "DELETE FROM {format_specialization} "
                . "WHERE courseid NOT IN ("
                . "    SELECT id FROM {course} WHERE format = :format"
                . ")";
        $DB->execute($orphansql, ['format' => 'specialization']);

        upgrade_plugin_savepoint(true, 2025112501, 'format', 'specialization');
    }

    if ($oldversion < 2026052502) {
        // Convert the non-unique courseid index into a UNIQUE one, after
        // deduping. The TOCTOU in create_specialization (read then insert
        // without a lock) plus the bulk-import codepath could produce
        // duplicate rows; the unique index defends against that going
        // forward.
        $dedupesql = "DELETE FROM {format_specialization} "
                . "WHERE id NOT IN ("
                . "    SELECT min_id FROM ("
                . "        SELECT MIN(id) AS min_id FROM {format_specialization} GROUP BY courseid"
                . "    ) AS keep"
                . ")";
        $DB->execute($dedupesql);

        $table = new xmldb_table('format_specialization');
        $oldindex = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, array('courseid'));
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }
        $newindex = new xmldb_index('courseid', XMLDB_INDEX_UNIQUE, array('courseid'));
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        upgrade_plugin_savepoint(true, 2026052502, 'format', 'specialization');
    }

    return true;
}

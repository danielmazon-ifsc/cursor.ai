<?php

/**
 * This file keeps track of upgrades to
 * the socialforum module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

function xmldb_socialforum_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2017062001) {

        $dbman = $DB->get_manager();

        // Define table socialforum_improper to be created.
        $table = new xmldb_table('socialforum_improper');

        // Adding fields to table socialforum_improper.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table socialforum_improper.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table socialforum_improper.
        $table->add_index('postid', XMLDB_INDEX_NOTUNIQUE, array('postid'));
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));

        // Conditionally launch create table for socialforum_improper.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Socialforum savepoint reached.
        upgrade_mod_savepoint(true, 2017062001, 'socialforum');
    }

    return true;
}

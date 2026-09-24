<?php

/**
 * Multiple choice question type upgrade code.
 *
 * @package    qtype
 * @subpackage cmultichoice
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade code for the competency multiple choice question type.
 * @param int $oldversion the version we are upgrading from.
 */
function xmldb_qtype_cmultichoice_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2017062801) {

        // Add new 'answerid' column.
        $table = new xmldb_table('qtype_cmultichoice_compts');
        $field = new xmldb_field('answerid', XMLDB_TYPE_INTEGER, '10', null, false, null, null, 'questionid');

        // Conditionally launch add field.
        $dbman = $DB->get_manager();
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2017062801, 'qtype', 'cmultichoice');
    }

    if ($oldversion < 2017062802) {

        $table = new xmldb_table('qtype_cmultichoice_compts');
        $index = new xmldb_index('answerid', XMLDB_INDEX_NOTUNIQUE, array('answerid'));
        $dbman = $DB->get_manager();
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        $dbman->add_index($table, $index);

        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2017062802, 'qtype', 'cmultichoice');
    }

    return true;
}

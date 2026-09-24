<?php

/**
 * Post-install script for the cquiz statistics report.
 * @package   cquiz_statistics
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Post-install script
 */
function xmldb_cquiz_statistics_install() {
    global $DB;

    $dbman = $DB->get_manager();

    $record = new stdClass();
    $record->name = 'statistics';
    $record->displayorder = 8000;
    $record->capability = 'cquiz/statistics:view';

    if ($dbman->table_exists('cquiz_reports')) {
        $DB->insert_record('cquiz_reports', $record);
    } else {
        $DB->insert_record('cquiz_report', $record);
    }
}

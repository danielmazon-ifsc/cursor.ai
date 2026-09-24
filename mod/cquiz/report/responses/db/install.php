<?php

/**
 * Post-install script for the cquiz responses report.
 * @package   cquiz_responses
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Post-install script
 */
function xmldb_cquiz_responses_install() {
    global $DB;

    $record = new stdClass();
    $record->name = 'responses';
    $record->displayorder = '9000';

    $DB->insert_record('cquiz_reports', $record);
}

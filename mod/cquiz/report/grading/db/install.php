<?php

/**
 * Post-install script for the cquiz manual grading report.
 * @package   cquiz_grading
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Post-install script
 */
function xmldb_cquiz_grading_install() {
    global $DB;

    $record = new stdClass();
    $record->name = 'grading';
    $record->displayorder = '6000';
    $record->capability = 'mod/cquiz:grade';

    $DB->insert_record('cquiz_reports', $record);
}

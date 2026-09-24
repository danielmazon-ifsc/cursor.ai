<?php

/**
 * Post-install script for the cquiz grades report.
 * @package   cquiz_overview
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Post-install script
 */
function xmldb_cquiz_overview_install() {
    global $DB;

    $record = new stdClass();
    $record->name = 'overview';
    $record->displayorder = '10000';

    $DB->insert_record('cquiz_reports', $record);
}

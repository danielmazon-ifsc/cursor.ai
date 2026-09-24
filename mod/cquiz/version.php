<?php

/**
 * Cquiz activity version information.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

$plugin->version = 2025090803;
$plugin->requires = 2017051500;
$plugin->component = 'mod_cquiz';
$plugin->cron = 60;
$plugin->dependencies = array(
    'qtype_cmultichoice' => 2025032801,
);

<?php

/**
 * Version conferencerooms
 *
 * @package    format_conferenceroom
 * @copyright  2025 Viddia (http://viddia.com.br)
 */
defined('MOODLE_INTERNAL') || die();

$plugin->version = 2026052501;        // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires = 2017051500;       // Requires this Moodle version.
$plugin->component = 'format_conferenceroom'; // Full name of the plugin (used for diagnostics).
$plugin->dependencies = array(
    'mod_video' => 2026052501,
);

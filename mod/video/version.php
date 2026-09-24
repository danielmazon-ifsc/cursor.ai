<?php

/**
 * Video module version information
 *
 * @package   mod_video
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

$plugin->version = 2026052649;   // The current module version (Date: YYYYMMDDXX)
$plugin->requires = 2017051500;   // Requires this Moodle version
$plugin->component = 'mod_video';  // Full name of the plugin (used for diagnostics)
$plugin->cron = 0;
$plugin->dependencies = array(
    'mod_socialforum' => 2025032201
);

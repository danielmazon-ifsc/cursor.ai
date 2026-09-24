<?php

/**
 * Version format videogallery
 *
 * @package    format_videogallery
 * @copyright  2024 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

$plugin->version = 2026052501;        // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires = 2017051500;       // Requires this Moodle version.
$plugin->component = 'format_videogallery'; // Full name of the plugin (used for diagnostics).
$plugin->dependencies = array(
    'mod_video' => 2026052501,
);

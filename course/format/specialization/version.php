<?php

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2026091503;                  // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires = 2022041900;                 // Requires Moodle 4.0+ (uses core_completion, format_base APIs).
$plugin->component = 'format_specialization';  // Full name of the plugin (used for diagnostics).
$plugin->dependencies = array(
    'local_dashboard' => 2025042901,
);

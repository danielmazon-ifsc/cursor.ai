<?php

/**
 * Version info
 *
 * @package   local_profile
 */
defined('MOODLE_INTERNAL') || die();

$plugin->version = 2026091301;
$plugin->requires = 2022041900;
$plugin->component = 'local_profile';
$plugin->dependencies = array(
    'format_specialization' => 2026052609,
    'local_studypace' => 2026052601,
);

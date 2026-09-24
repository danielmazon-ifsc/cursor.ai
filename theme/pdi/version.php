<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Version metadata for theme_pdi.
 *
 * @package   theme_pdi
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2026091603;
$plugin->requires = 2022041900;
$plugin->component = 'theme_pdi';
$plugin->dependencies = [
    'theme_boost' => 2022112800,
    'format_conferenceroom' => 2025040101,
    'format_videogallery' => 2025040101,
    'mod_cquiz' => 2025042902,
    'mod_scorm' => 2025042701,
    'block_onboarding' => 2026052618,
    'local_profile' => 2026052616,
    'local_notifications' => 2026052637,
    'local_studypace' => 2026052616,
    'local_autobadge' => 2026052616,
    'local_dashboard' => 2026052616,
    'local_coin' => 2025061403,
    'mod_video' => 2026052616,
    'format_specialization' => 2026091503,
    'format_saladeconferencias' => 2026052625,
];

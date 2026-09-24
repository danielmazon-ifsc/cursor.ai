<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English strings for local_salagrade.
 *
 * @package   local_salagrade
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sala de Conferências auto-grade';
$string['privacy:metadata'] = 'The plugin writes grades into the core gradebook; it does not store personal data in its own tables.';
$string['gradeitemname'] = 'Video completion';
$string['admincategory'] = 'Sala auto-grade';
$string['syncpageheading'] = 'Sync Sala grades';
$string['syncintro'] = 'Assigns grade 100 in the Sala de Conferências gradebook when a student has completed every required video (the same videos used for course completion). Students who already finished are included. The operation is idempotent: existing 100s are left unchanged.';
$string['syncqueued'] = 'Grade sync queued. Cron will process it; you can close this page.';
$string['syncalreadyqueued'] = 'A full grade sync is already queued or running. Wait for it to finish before queueing another.';
$string['syncnow'] = 'Queue grade sync';
$string['syncconfirm'] = 'Queue a background job to assign grade 100 to every enrolled student who has completed all required Sala videos?';
$string['synccourses'] = 'Sala de Conferências courses: {$a}';
$string['task_sync_grades'] = 'Sync Sala de Conferências completion grades';
$string['task_sync_grades_adhoc'] = 'Sync Sala de Conferências completion grades (queued)';

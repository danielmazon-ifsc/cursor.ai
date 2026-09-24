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
 * Queue a full backfill of Sala de Conferências completion grades.
 *
 * @package   local_salagrade
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_salagrade\grader;
use local_salagrade\task\sync_grades_adhoc;

admin_externalpage_setup('local_salagrade_sync');

$confirm = optional_param('confirm', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/salagrade/sync.php'));
$PAGE->set_title(get_string('syncpageheading', 'local_salagrade'));
$PAGE->set_heading(get_string('syncpageheading', 'local_salagrade'));

if ($confirm && confirm_sesskey() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (sync_grades_adhoc::has_pending()) {
        redirect(
            $PAGE->url,
            get_string('syncalreadyqueued', 'local_salagrade'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
    $task = new sync_grades_adhoc();
    \core\task\manager::queue_adhoc_task($task, true);
    redirect(
        $PAGE->url,
        get_string('syncqueued', 'local_salagrade'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('syncpageheading', 'local_salagrade'));
echo $OUTPUT->notification(get_string('syncintro', 'local_salagrade'), 'info');

$coursecount = count(grader::get_sala_course_ids());
echo html_writer::tag('p', get_string('synccourses', 'local_salagrade', $coursecount));

if (sync_grades_adhoc::has_pending()) {
    echo $OUTPUT->notification(get_string('syncalreadyqueued', 'local_salagrade'), 'info');
}

echo $OUTPUT->confirm(
    get_string('syncconfirm', 'local_salagrade'),
    new single_button(
        new moodle_url('/local/salagrade/sync.php', ['confirm' => 1, 'sesskey' => sesskey()]),
        get_string('syncnow', 'local_salagrade'),
        'post'
    ),
    $PAGE->url
);

echo $OUTPUT->footer();

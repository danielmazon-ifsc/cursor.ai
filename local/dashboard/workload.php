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
 * Admin page: configure workload hours per tracked course for progress weighting.
 *
 * @package   local_dashboard
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_dashboard\course_workload;
use local_dashboard\course_workload_form;

admin_externalpage_setup('local_dashboard_workload');

$PAGE->set_url(new moodle_url('/local/dashboard/workload.php'));
$PAGE->set_title(get_string('workloadpageheading', 'local_dashboard'));
$PAGE->set_heading(get_string('workloadpageheading', 'local_dashboard'));

$courses = course_workload::get_tracked_courses_grouped();
$workloads = course_workload::get_all();

$form = new course_workload_form(null, [
    'courses' => $courses,
    'workloads' => $workloads,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/category.php', ['category' => 'dashboard']));
}

if ($form->get_data()) {
    course_workload::save_all($form->get_submitted_workloads());
    redirect(
        $PAGE->url,
        get_string('workloadsaved', 'local_dashboard'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('workloadpageheading', 'local_dashboard'));

$configured = array_filter($workloads, static function ($hours): bool {
    return (float) $hours > 0;
});
if (empty($configured)) {
    echo $OUTPUT->notification(get_string('workloadfallbacknotice', 'local_dashboard'), 'info');
} else {
    $totalhours = array_sum($configured);
    echo $OUTPUT->notification(
        get_string('workloadsummary', 'local_dashboard', [
            'courses' => count($configured),
            'hours' => format_float($totalhours, 2, true),
        ]),
        'success'
    );
}

$form->display();
echo $OUTPUT->footer();

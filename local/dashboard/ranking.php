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
 * Admin report: full student coin ranking, with CSV download.
 *
 * Access is restricted to site administrators via admin_externalpage_setup()
 * with the 'moodle/site:config' capability.
 *
 * @package   local_dashboard
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/dashboard/lib.php');

admin_externalpage_setup('local_dashboard_ranking');

$download = optional_param('download', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$perpage = max(10, min(500, $perpage));

$baseurl = new moodle_url('/local/dashboard/ranking.php', array('perpage' => $perpage));

// CSV download. Require a valid sesskey so the export can't be triggered via a
// forged cross-site GET, then stream the whole ranking and stop.
if ($download === 'csv') {
    require_sesskey();
    @set_time_limit(0);
    raise_memory_limit(MEMORY_HUGE);
    \core\session\manager::write_close();
    local_dashboard_send_ranking_csv();
    exit;
}

$PAGE->set_url($baseurl);
$PAGE->set_title(get_string('rankingpageheading', 'local_dashboard'));
$PAGE->set_heading(get_string('rankingpageheading', 'local_dashboard'));

// The final-grade tie-break is a PHP-side sum, so the full set must be sorted
// in PHP (a paginated SQL ORDER BY can't express it). Admin-only / on-demand,
// so raise the limits and load everything, then slice the requested page.
@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

$eixos = local_dashboard_get_ranking_eixos();
$ordered = local_dashboard_get_sorted_ranking($eixos);
$totalstudents = count($ordered);
$rows = array_slice($ordered, $page * $perpage, $perpage);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('rankingpageheading', 'local_dashboard'));
echo html_writer::tag('p', get_string('rankingintro', 'local_dashboard'));
echo html_writer::tag('p', html_writer::tag('em', get_string('rankinggradenote', 'local_dashboard')), array('class' => 'text-muted small'));

// Summary + download button.
echo $OUTPUT->box_start('generalbox p-3 mb-3');
echo html_writer::tag('p', get_string('rankingtotalstudents', 'local_dashboard', $totalstudents));
$downloadurl = new moodle_url('/local/dashboard/ranking.php', array(
    'download' => 'csv',
    'sesskey' => sesskey(),
));
echo html_writer::link(
    $downloadurl,
    get_string('rankingdownloadcsv', 'local_dashboard'),
    array('class' => 'btn btn-primary')
);
echo $OUTPUT->box_end();

if (empty($rows)) {
    echo $OUTPUT->notification(get_string('rankingnostudents', 'local_dashboard'), 'info');
} else {
    $table = new html_table();
    $head = array(
        get_string('rankingcolposition', 'local_dashboard'),
        get_string('rankingcolname', 'local_dashboard'),
        get_string('rankingcolusername', 'local_dashboard'),
        get_string('rankingcolemail', 'local_dashboard'),
        get_string('rankingcolcoins', 'local_dashboard'),
    );
    foreach ($eixos as $eixo) {
        $coursecontext = context_course::instance($eixo->id);
        $head[] = html_writer::tag('span', s($eixo->shortname), array(
            'title' => format_string($eixo->fullname, true, array('context' => $coursecontext)),
        ));
    }
    $head[] = get_string('rankingcolfinalgrade', 'local_dashboard');
    $table->head = $head;
    $table->attributes['class'] = 'generaltable';

    $position = $page * $perpage;
    foreach ($rows as $row) {
        $position++;
        $profileurl = new moodle_url('/user/profile.php', array('id' => (int) $row->id));
        $cells = array(
            $position . 'º',
            html_writer::link($profileurl, s(fullname($row))),
            s($row->username),
            s($row->email),
            (int) $row->coins,
        );
        foreach ($eixos as $eixo) {
            $grade = $row->pereixo[(int) $eixo->id];
            // Dim a zero so admins can spot "not completed" at a glance.
            $cells[] = ($grade > 0)
                ? format_float($grade, 1)
                : html_writer::tag('span', format_float(0, 1), array('class' => 'text-muted'));
        }
        $cells[] = html_writer::tag('strong', format_float($row->finalgrade, 1));
        $table->data[] = $cells;
    }
    echo html_writer::table($table);

    echo $OUTPUT->paging_bar($totalstudents, $page, $perpage, $baseurl);
}

echo $OUTPUT->footer();

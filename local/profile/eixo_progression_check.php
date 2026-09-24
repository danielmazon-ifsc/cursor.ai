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
 * Admin diagnostic: detect (and repair) failures in the auto-enrolment into
 * the next Eixo. Lists students who completed an Eixo but were never enrolled
 * into the next visible Eixo.
 *
 * Access is restricted to site administrators via admin_externalpage_setup()
 * with the 'moodle/site:config' capability.
 *
 * @package   local_profile
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/profile/lib.php');

admin_externalpage_setup('local_profile_eixo_progression_check');

$run = optional_param('run', 0, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$repair = optional_param('repair', 0, PARAM_INT);       // userid to repair (single).
$repairall = optional_param('repairall', 0, PARAM_INT); // 1 = repair everyone in the report.
$userid = optional_param('userid', 0, PARAM_INT);       // optional single-user filter.

$baseurl = new moodle_url('/local/profile/eixo_progression_check.php');

// CSV download. Require a valid sesskey so the export can't be triggered via a
// forged cross-site GET, then stream the whole report and stop.
if ($download === 'csv') {
    require_sesskey();
    @set_time_limit(0);
    raise_memory_limit(MEMORY_HUGE);
    \core\session\manager::write_close();
    local_profile_send_progression_gaps_csv($userid);
    exit;
}

$PAGE->set_url($baseurl);
$PAGE->set_title(get_string('progcheckpageheading', 'local_profile'));
$PAGE->set_heading(get_string('progcheckpageheading', 'local_profile'));

$repairmsg = null;
$repairerror = null;

// Repair actions (POST + sesskey). Both reuse the idempotent backfill, which
// replays every completed Eixo for the target user(s) through
// local_profile_enrol_in_next_eixo() — it only ever CREATES the missing
// next-Eixo enrolment and never touches anything an admin removed on purpose.
if (($repair || $repairall) && confirm_sesskey()) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidrequest');
    }
    @set_time_limit(0);
    raise_memory_limit(MEMORY_HUGE);
    \core\session\manager::write_close();

    try {
        if ($repair > 0) {
            $created = local_profile_backfill_eixo_progression($repair);
            $repairmsg = get_string('progcheckrepairedone', 'local_profile', (object) [
                'userid' => $repair,
                'created' => $created,
            ]);
        } else if ($repairall) {
            $created = local_profile_backfill_eixo_progression(0);
            $repairmsg = get_string('progcheckrepairedall', 'local_profile', $created);
        }
    } catch (\Throwable $e) {
        $repairerror = $e->getMessage();
    }
    // After repairing, re-run the check so the table reflects the new state.
    $run = 1;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('progcheckpageheading', 'local_profile'));
echo html_writer::tag('p', get_string('progcheckintro', 'local_profile'));

if ($repairerror !== null) {
    echo $OUTPUT->notification($repairerror, 'error');
}
if ($repairmsg !== null) {
    echo $OUTPUT->notification($repairmsg, 'success');
}

// Filter / run form.
echo $OUTPUT->box_start('generalbox p-3 mb-3');
echo html_writer::start_tag('form', array(
    'method' => 'get',
    'action' => $baseurl->out(false),
    'class' => 'form-inline',
));
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'run', 'value' => '1'));
echo html_writer::tag('label', get_string('progcheckuseridlabel', 'local_profile'), array(
    'class' => 'mr-2',
    'for' => 'progcheck_userid',
));
echo html_writer::empty_tag('input', array(
    'type' => 'number',
    'min' => '0',
    'id' => 'progcheck_userid',
    'name' => 'userid',
    'class' => 'form-control mr-2',
    'style' => 'max-width: 10rem;',
    'value' => $userid > 0 ? $userid : '',
    'placeholder' => get_string('progcheckuseridplaceholder', 'local_profile'),
));
echo html_writer::empty_tag('input', array(
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => get_string('progcheckrunbutton', 'local_profile'),
));
echo html_writer::end_tag('form');
echo $OUTPUT->box_end();

if (!$run) {
    echo $OUTPUT->footer();
    exit;
}

@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

$gaps = local_profile_find_progression_gaps($userid);

echo $OUTPUT->box_start('generalbox p-3 mb-3');
if (empty($gaps)) {
    echo $OUTPUT->notification(get_string('progchecknone', 'local_profile'), 'success');
} else {
    echo html_writer::tag('p', get_string('progcheckfound', 'local_profile', count($gaps)));

    // Download CSV.
    $downloadurl = new moodle_url('/local/profile/eixo_progression_check.php', array(
        'download' => 'csv',
        'userid' => $userid,
        'sesskey' => sesskey(),
    ));
    echo html_writer::link(
        $downloadurl,
        get_string('progcheckdownloadcsv', 'local_profile'),
        array('class' => 'btn btn-secondary mr-2')
    );

    // Repair all.
    echo html_writer::start_tag('form', array(
        'method' => 'post',
        'action' => $baseurl->out(false),
        'class' => 'd-inline',
        'onsubmit' => 'return confirm(' . json_encode(get_string('progcheckrepairallconfirm', 'local_profile')) . ');',
    ));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'repairall', 'value' => '1'));
    echo html_writer::tag('button', get_string('progcheckrepairall', 'local_profile'), array(
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ));
    echo html_writer::end_tag('form');
}
echo $OUTPUT->box_end();

if (!empty($gaps)) {
    $table = new html_table();
    $table->head = array(
        get_string('progcheckcoluser', 'local_profile'),
        get_string('progcheckcolcompleted', 'local_profile'),
        get_string('progcheckcolcompletedon', 'local_profile'),
        get_string('progcheckcolnext', 'local_profile'),
        get_string('progcheckcolretry', 'local_profile'),
        get_string('progcheckcolaction', 'local_profile'),
    );
    $table->attributes['class'] = 'generaltable';

    foreach ($gaps as $gap) {
        $profileurl = new moodle_url('/user/profile.php', array('id' => $gap->userid));
        $usercell = html_writer::link($profileurl, s(fullname($gap->user)))
            . html_writer::empty_tag('br')
            . html_writer::tag('span', 'id=' . $gap->userid . ' · ' . s($gap->user->username), array('class' => 'text-muted small'));

        if ($gap->pending) {
            $retrycell = get_string('progcheckretryqueued', 'local_profile', (object) array(
                'time' => userdate($gap->pending->nextruntime),
                'attempt' => $gap->pending->attempt,
            ));
        } else {
            $retrycell = html_writer::tag('span', get_string('progcheckretrynone', 'local_profile'), array('class' => 'text-danger'));
        }

        // Per-row repair button.
        $repairform = html_writer::start_tag('form', array(
            'method' => 'post',
            'action' => $baseurl->out(false),
            'class' => 'd-inline',
        ));
        $repairform .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        $repairform .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'repair', 'value' => $gap->userid));
        $repairform .= html_writer::tag('button', get_string('progcheckrepair', 'local_profile'), array(
            'type' => 'submit',
            'class' => 'btn btn-sm btn-outline-primary',
        ));
        $repairform .= html_writer::end_tag('form');

        $table->data[] = array(
            $usercell,
            s($gap->completedcoursename),
            $gap->completedtime ? userdate($gap->completedtime) : '—',
            s($gap->nexteixoname),
            $retrycell,
            $repairform,
        );
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();

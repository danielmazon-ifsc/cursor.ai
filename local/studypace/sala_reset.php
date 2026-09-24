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
 * Admin tool: reset Sala de Conferências progress and remove its keys.
 *
 * @package   local_studypace
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_studypace\studypace;

admin_externalpage_setup('local_studypace_sala_reset');

$preview = optional_param('preview', 0, PARAM_INT);
$execute = optional_param('execute', 0, PARAM_INT);
$liveconfirm = optional_param('liveconfirm', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$revalidpreview = optional_param('revalidpreview', 0, PARAM_INT);
$revalidexecute = optional_param('revalidexecute', 0, PARAM_INT);
$revalidconfirm = optional_param('revalidconfirm', 0, PARAM_INT);
$revaliduserid = optional_param('revaliduserid', 0, PARAM_INT);

$salacourseids = studypace::get_tracked_course_ids_for_format('saladeconferencias', false);

if ($revalidpreview || $revalidexecute) {
    studypace::ensure_sala_video_completion_criteria();
}

$baseurl = new moodle_url('/local/studypace/sala_reset.php');
$PAGE->set_url($baseurl);
$PAGE->set_title(get_string('salaresetpageheading', 'local_studypace'));
$PAGE->set_heading(get_string('salaresetpageheading', 'local_studypace'));

/**
 * Render preview/result counts.
 *
 * @param \stdClass $data
 * @return void
 */
function local_studypace_render_sala_reset_stats(\stdClass $data): void {
    global $OUTPUT;

    if (!empty($data->error) && $data->error === 'user_not_found') {
        echo $OUTPUT->notification(get_string('salaresetusernotfound', 'local_studypace'), 'error');
        return;
    }

    if (!$data->valid) {
        echo $OUTPUT->notification(get_string('salaresetnocourse', 'local_studypace'), 'warning');
        return;
    }

    if (!empty($data->targetuser)) {
        echo html_writer::tag('p', get_string('salaresetpreviewuserheading', 'local_studypace',
            fullname($data->targetuser) . ' (id=' . (int) $data->targetuser->id . ')'));
    } else if (!empty($data->userid)) {
        echo $OUTPUT->notification(get_string('salaresetusernotfound', 'local_studypace'), 'error');
        return;
    } else {
        echo html_writer::tag('p', get_string('salaresetpreviewallusers', 'local_studypace'),
            ['class' => 'font-weight-bold']);
    }

    $table = new html_table();
    $table->head = [
        get_string('salaresetheadercourse', 'local_studypace'),
        get_string('salaresetheadercoinsrows', 'local_studypace'),
        get_string('salaresetheadercoinstotal', 'local_studypace'),
        get_string('salaresetheadercoinsusers', 'local_studypace'),
        get_string('salaresetheaderpacecourse', 'local_studypace'),
        get_string('salaresetheaderpacecmkept', 'local_studypace'),
        get_string('salaresetheadermoodlecourse', 'local_studypace'),
        get_string('salaresetheadermoodlecmkept', 'local_studypace'),
    ];

    foreach ($data->courses as $course) {
        $table->data[] = [
            s($course->fullname) . ' (id=' . (int) $course->id . ')',
            (int) ($data->coins_ledger_rows ?? 0),
            (int) ($data->coins_total ?? 0),
            (int) ($data->coins_users ?? 0),
            (int) ($data->pace_course_completions ?? 0),
            (int) ($data->pace_cm_completions ?? 0),
            (int) ($data->moodle_course_completions ?? 0),
            (int) ($data->moodle_cm_completions ?? 0),
        ];
        break;
    }

    if (count($data->courses) > 1) {
        echo $OUTPUT->notification(get_string('salaresetmultiplecourses', 'local_studypace'), 'info');
    }

    echo html_writer::table($table);
    echo html_writer::tag('p', get_string('salaresetkeepvideosnote', 'local_studypace'), [
        'class' => 'text-muted small mb-0',
    ]);
}

/**
 * User id field shared by preview and execute forms.
 *
 * @param int $userid
 * @return void
 */
function local_studypace_sala_reset_userid_field(int $userid): void {
    echo html_writer::start_div('form-group row align-items-center mb-3');
    echo html_writer::tag('label', get_string('salaresetuseridlabel', 'local_studypace'), [
        'class' => 'col-form-label col-md-3',
        'for' => 'studypace_sala_reset_userid',
    ]);
    echo html_writer::start_div('col-md-9');
    echo html_writer::empty_tag('input', [
        'type' => 'number',
        'min' => '0',
        'id' => 'studypace_sala_reset_userid',
        'name' => 'userid',
        'class' => 'form-control',
        'style' => 'max-width: 10rem;',
        'value' => $userid > 0 ? $userid : '',
        'placeholder' => get_string('salaresetuseridplaceholder', 'local_studypace'),
    ]);
    echo html_writer::tag('small', get_string('salaresetuseridhelp', 'local_studypace'),
        ['class' => 'form-text text-muted']);
    echo html_writer::end_div();
    echo html_writer::end_div();
}

/**
 * Render stale Sala completion preview rows.
 *
 * @param \stdClass $data
 * @return void
 */
function local_studypace_render_sala_revalidate_preview(\stdClass $data): void {
    global $OUTPUT;

    if (!empty($data->error) && $data->error === 'user_not_found') {
        echo $OUTPUT->notification(get_string('salaresetusernotfound', 'local_studypace'), 'error');
        return;
    }

    if (!$data->valid) {
        echo $OUTPUT->notification(get_string('salaresetnocourse', 'local_studypace'), 'warning');
        return;
    }

    if (!empty($data->targetuser)) {
        echo html_writer::tag('p', get_string('salaresetpreviewuserheading', 'local_studypace',
            fullname($data->targetuser) . ' (id=' . (int) $data->targetuser->id . ')'));
    } else if (!empty($data->userid)) {
        echo $OUTPUT->notification(get_string('salaresetusernotfound', 'local_studypace'), 'error');
        return;
    } else {
        echo html_writer::tag('p', get_string('salaresetpreviewallusers', 'local_studypace'),
            ['class' => 'font-weight-bold']);
    }

    if (($data->stale_count ?? 0) === 0) {
        echo $OUTPUT->notification(get_string('salarevalidatenoop', 'local_studypace'), 'info');
        if (!empty($data->targetuser) && !empty($data->courseids)) {
            local_studypace_render_sala_diagnostic((int) $data->targetuser->id, $data->courseids);
        }
        return;
    }

    echo html_writer::tag('p', get_string('salarevalidatepreviewheading', 'local_studypace') . ': '
        . (int) $data->stale_count . ' (' . (int) $data->stale_users . ' '
        . get_string('salaresetheadercoinsusers', 'local_studypace') . ')');

    if (!empty($data->details)) {
        $table = new html_table();
        $table->head = [
            get_string('salarevalidateheaderuser', 'local_studypace'),
            get_string('salarevalidateheadercourse', 'local_studypace'),
            get_string('salarevalidateheadermarked', 'local_studypace'),
        ];
        foreach ($data->details as $row) {
            $table->data[] = [
                s($row->userfullname) . ' (id=' . (int) $row->userid . ')',
                s($row->courseshortname) . ' (id=' . (int) $row->courseid . ')',
                userdate($row->markedon),
            ];
        }
        echo html_writer::table($table);
        if ($data->stale_count > count($data->details)) {
            echo html_writer::tag('p', '…', ['class' => 'text-muted small']);
        }
    }
}

/**
 * Optional user id field for the revalidate form.
 *
 * @param int $revaliduserid
 * @return void
 */
function local_studypace_sala_revalidate_userid_field(int $revaliduserid): void {
    echo html_writer::start_div('form-group row align-items-center mb-3');
    echo html_writer::tag('label', get_string('salaresetuseridlabel', 'local_studypace'), [
        'class' => 'col-form-label col-md-3',
        'for' => 'studypace_sala_revalidate_userid',
    ]);
    echo html_writer::start_div('col-md-9');
    echo html_writer::empty_tag('input', [
        'type' => 'number',
        'min' => '0',
        'id' => 'studypace_sala_revalidate_userid',
        'name' => 'revaliduserid',
        'class' => 'form-control',
        'style' => 'max-width: 10rem;',
        'value' => $revaliduserid > 0 ? $revaliduserid : '',
        'placeholder' => get_string('salaresetuseridplaceholder', 'local_studypace'),
    ]);
    echo html_writer::tag('small', get_string('salaresetuseridhelp', 'local_studypace'),
        ['class' => 'form-text text-muted']);
    echo html_writer::end_div();
    echo html_writer::end_div();
}

/**
 * Per-video diagnostic explaining why the Sala is/ isn't complete for a user.
 *
 * @param int $userid
 * @param int[] $courseids
 * @return void
 */
function local_studypace_render_sala_diagnostic(int $userid, array $courseids): void {
    global $OUTPUT, $DB;

    foreach ($courseids as $courseid) {
        $courseid = (int) $courseid;
        $course = $DB->get_record('course', ['id' => $courseid], 'id, shortname, fullname', IGNORE_MISSING);
        if (!$course) {
            continue;
        }
        $rows = studypace::get_sala_completion_breakdown($userid, $courseid);
        if (empty($rows)) {
            continue;
        }

        echo html_writer::tag('h5', get_string('saladiagheading', 'local_studypace', s($course->shortname)),
            ['class' => 'mt-3']);

        $countedtotal = 0;
        $counteddone = 0;
        $table = new html_table();
        $table->head = [
            get_string('saladiagactivity', 'local_studypace'),
            get_string('saladiagtype', 'local_studypace'),
            get_string('saladiagvisible', 'local_studypace'),
            get_string('saladiagtracking', 'local_studypace'),
            get_string('saladiagcounted', 'local_studypace'),
            get_string('saladiagcompleted', 'local_studypace'),
        ];
        $yes = get_string('yes');
        $no = get_string('no');
        foreach ($rows as $r) {
            if ($r->counted) {
                $countedtotal++;
                if ($r->completed) {
                    $counteddone++;
                }
            }
            $table->data[] = [
                s($r->name) . ' (cmid=' . $r->cmid . ')',
                s($r->modname),
                $r->uservisible ? $yes : $no,
                $r->completiontracking ? $yes : $no,
                $r->counted ? $yes : $no,
                $r->counted ? ($r->completed ? $yes : $no) : '—',
            ];
        }
        echo html_writer::table($table);
        echo html_writer::tag('p', get_string('saladiagsummary', 'local_studypace', (object) [
            'done' => $counteddone,
            'total' => $countedtotal,
        ]), ['class' => 'text-muted']);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('salaresetpageheading', 'local_studypace'));
echo html_writer::tag('p', get_string('salaresetintro', 'local_studypace'));
echo $OUTPUT->notification(get_string('salaresetwarning', 'local_studypace'), 'warning');

if ($execute && $liveconfirm && confirm_sesskey()) {
    @set_time_limit(0);
    raise_memory_limit(MEMORY_HUGE);
    \core\session\manager::write_close();

    $result = studypace::reset_sala_conferencias_progress(false, $userid);

    if (!empty($result->errors)) {
        foreach ($result->errors as $error) {
            echo $OUTPUT->notification($error, 'error');
        }
    } else if (!empty($result->executed)) {
        if (!empty($result->targetuser)) {
            echo $OUTPUT->notification(get_string('salaresetdoneuser', 'local_studypace', (object) [
                'user' => fullname($result->targetuser),
                'coins' => (int) $result->coins_removed,
                'pace' => (int) $result->pace_rows_cleared,
                'moodlecourse' => (int) $result->moodle_course_rows_reset,
            ]), 'success');
        } else {
            echo $OUTPUT->notification(get_string('salaresetdone', 'local_studypace', (object) [
                'coins' => (int) $result->coins_removed,
                'pace' => (int) $result->pace_rows_cleared,
                'moodlecourse' => (int) $result->moodle_course_rows_reset,
            ]), 'success');
        }
    }

    echo $OUTPUT->box_start('generalbox p-3 mt-3');
    echo html_writer::tag('h4', get_string('salaresetresultheading', 'local_studypace'));
    local_studypace_render_sala_reset_stats(studypace::get_sala_conferencias_reset_preview($userid));
    echo $OUTPUT->box_end();
} else if ($execute && confirm_sesskey()) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidrequest');
    }

    $confirmtext = get_string('salaresetliveconfirm', 'local_studypace');
    if ($userid > 0) {
        $targetuser = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id, firstname, lastname');
        if ($targetuser) {
            $confirmtext = get_string('salaresetliveconfirmuser', 'local_studypace', fullname($targetuser));
        } else {
            echo $OUTPUT->notification(get_string('salaresetusernotfound', 'local_studypace'), 'error');
            echo $OUTPUT->footer();
            exit;
        }
    }

    $confirmurl = new moodle_url($baseurl, [
        'execute' => 1,
        'liveconfirm' => 1,
        'sesskey' => sesskey(),
    ]);
    if ($userid > 0) {
        $confirmurl->param('userid', $userid);
    }

    echo $OUTPUT->confirm($confirmtext, $confirmurl, $baseurl);
} else if ($revalidexecute && $revalidconfirm && confirm_sesskey()) {
    @set_time_limit(0);
    raise_memory_limit(MEMORY_HUGE);
    \core\session\manager::write_close();

    $result = studypace::revalidate_stale_course_completions(false, $revaliduserid, $salacourseids);

    if (!empty($result->targetuser)) {
        echo $OUTPUT->notification(get_string('salarevalidatedoneuser', 'local_studypace', (object) [
            'user' => fullname($result->targetuser),
            'cleared' => (int) ($result->cleared ?? 0),
        ]), 'success');
    } else if (($result->cleared ?? 0) > 0) {
        echo $OUTPUT->notification(get_string('salarevalidatedone', 'local_studypace', (object) [
            'cleared' => (int) $result->cleared,
            'users' => (int) ($result->stale_users ?? 0),
        ]), 'success');
    } else {
        echo $OUTPUT->notification(get_string('salarevalidatenoop', 'local_studypace'), 'info');
    }

    echo $OUTPUT->box_start('generalbox p-3 mt-3');
    local_studypace_render_sala_revalidate_preview(
        studypace::get_stale_course_completions_preview($revaliduserid, $salacourseids)
    );
    echo $OUTPUT->box_end();
} else if ($revalidexecute && confirm_sesskey()) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidrequest');
    }

    $previewdata = studypace::get_stale_course_completions_preview($revaliduserid, $salacourseids);
    if (!empty($previewdata->error) && $previewdata->error === 'user_not_found') {
        echo $OUTPUT->notification(get_string('salaresetusernotfound', 'local_studypace'), 'error');
    } else if (($previewdata->stale_count ?? 0) === 0) {
        echo $OUTPUT->notification(get_string('salarevalidatenoop', 'local_studypace'), 'info');
    } else {
        $confirmtext = get_string('salarevalidateliveconfirm', 'local_studypace', (object) [
            'count' => (int) $previewdata->stale_count,
            'users' => (int) $previewdata->stale_users,
        ]);
        if (!empty($previewdata->targetuser)) {
            $confirmtext = get_string('salarevalidateliveconfirmuser', 'local_studypace', (object) [
                'count' => (int) $previewdata->stale_count,
                'user' => fullname($previewdata->targetuser),
            ]);
        }

        $confirmurl = new moodle_url($baseurl, [
            'revalidexecute' => 1,
            'revalidconfirm' => 1,
            'sesskey' => sesskey(),
        ]);
        if ($revaliduserid > 0) {
            $confirmurl->param('revaliduserid', $revaliduserid);
        }

        echo $OUTPUT->confirm($confirmtext, $confirmurl, $baseurl);
    }
} else if (!$revalidexecute) {
    $previewdata = $preview ? studypace::get_sala_conferencias_reset_preview($userid) : null;

    echo $OUTPUT->box_start('generalbox p-3 mb-3');
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $baseurl->out(false),
        'class' => 'mform',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    local_studypace_sala_reset_userid_field($userid);

    if (!$preview) {
        echo html_writer::tag('button', get_string('salaresetpreviewbutton', 'local_studypace'), [
            'type' => 'submit',
            'name' => 'preview',
            'value' => '1',
            'class' => 'btn btn-secondary',
        ]);
    } else {
        echo html_writer::tag('h4', get_string('salaresetpreviewheading', 'local_studypace'));
        if ($previewdata) {
            local_studypace_render_sala_reset_stats($previewdata);
        }

        $haswork = $previewdata && !empty($previewdata->valid) && empty($previewdata->error) && (
            ($previewdata->coins_ledger_rows ?? 0) > 0
            || ($previewdata->pace_course_completions ?? 0) > 0
            || ($previewdata->moodle_course_completions ?? 0) > 0
        );

        if ($haswork) {
            if ($userid > 0) {
                echo html_writer::empty_tag('input', [
                    'type' => 'hidden',
                    'name' => 'userid',
                    'value' => $userid,
                ]);
            }
            echo html_writer::start_div('mt-3');
            echo html_writer::tag('button', get_string('salaresetexecutebutton', 'local_studypace'), [
                'type' => 'submit',
                'name' => 'execute',
                'value' => '1',
                'class' => 'btn btn-danger',
            ]);
            echo html_writer::end_div();
        } else if ($previewdata && !empty($previewdata->error)) {
            // user_not_found already rendered in stats helper.
        } else {
            echo $OUTPUT->notification(get_string('salaresetnoop', 'local_studypace'), 'info');
        }
    }

    echo html_writer::end_tag('form');
    echo $OUTPUT->box_end();
}

if (!$execute && !($revalidexecute && !$revalidconfirm)) {
    $revalidpreviewdata = $revalidpreview
        ? studypace::get_stale_course_completions_preview($revaliduserid, $salacourseids)
        : null;

    echo $OUTPUT->box_start('generalbox p-3 mb-3 mt-4');
    echo html_writer::tag('h4', get_string('salarevalidateheading', 'local_studypace'));
    echo html_writer::tag('p', get_string('salarevalidateintro', 'local_studypace'));
    echo $OUTPUT->notification(get_string('salarevalidatewarning', 'local_studypace'), 'info');

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $baseurl->out(false),
        'class' => 'mform',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    local_studypace_sala_revalidate_userid_field($revaliduserid);

    if (!$revalidpreview) {
        echo html_writer::tag('button', get_string('salarevalidatepreviewbutton', 'local_studypace'), [
            'type' => 'submit',
            'name' => 'revalidpreview',
            'value' => '1',
            'class' => 'btn btn-secondary',
        ]);
    } else {
        if ($revalidpreviewdata) {
            local_studypace_render_sala_revalidate_preview($revalidpreviewdata);
        }

        $hasstale = $revalidpreviewdata && !empty($revalidpreviewdata->valid)
            && empty($revalidpreviewdata->error) && ($revalidpreviewdata->stale_count ?? 0) > 0;

        if ($hasstale) {
            if ($revaliduserid > 0) {
                echo html_writer::empty_tag('input', [
                    'type' => 'hidden',
                    'name' => 'revaliduserid',
                    'value' => $revaliduserid,
                ]);
            }
            echo html_writer::start_div('mt-3');
            echo html_writer::tag('button', get_string('salarevalidateexecutebutton', 'local_studypace'), [
                'type' => 'submit',
                'name' => 'revalidexecute',
                'value' => '1',
                'class' => 'btn btn-primary',
            ]);
            echo html_writer::end_div();
        }
    }

    echo html_writer::end_tag('form');
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();

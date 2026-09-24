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
 * Admin tool: recalculate studypace and restore missing keys and/or badges per user.
 *
 * @package   local_studypace
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_studypace\gamification_gaps;
use local_studypace\studypace;

admin_externalpage_setup('local_studypace_repair_coins');

$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$notify = optional_param('notify', 0, PARAM_INT);
$repairdryrun = optional_param('repairdryrun', '', PARAM_RAW) !== '';
$repairlive = optional_param('repairlive', '', PARAM_RAW) !== '';
if ($repairlive || $repairdryrun) {
    $action = 'repair';
}
$dryrun = $repairdryrun;
$recalc = optional_param('recalc', 0, PARAM_INT);
$repairwhat = optional_param('repairwhat', gamification_gaps::REPAIR_COINS, PARAM_ALPHA);
$formsubmitted = ($action === 'preview' || $action === 'repair' || $repairdryrun || $repairlive);
if ($formsubmitted && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new moodle_exception('invalidrequest');
}
$sesskeyerror = $formsubmitted && !confirm_sesskey();
$liveconfirm = optional_param('liveconfirm', 0, PARAM_INT);
$repairattempted = ($action === 'repair' && $confirm && ($repairdryrun || $liveconfirm));
$repairlivepending = ($repairlive && !$repairdryrun && !$liveconfirm && !$sesskeyerror && $userid > 0);

$revokeuserid = optional_param('revokeuserid', 0, PARAM_INT);
$revokecourseid = optional_param('revokecourseid', 0, PARAM_INT);
$revokepreview = optional_param('revokepreview', '', PARAM_RAW) !== '';
$revokelive = optional_param('revokelive', '', PARAM_RAW) !== '';
$revokeliveconfirm = optional_param('revokeliveconfirm', 0, PARAM_INT);
$revokeformsubmitted = ($revokepreview || $revokelive);
if ($revokeformsubmitted && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new moodle_exception('invalidrequest');
}
$revokesesskeyerror = $revokeformsubmitted && !confirm_sesskey();
$revokelivepending = ($revokelive && !$revokeliveconfirm && !$revokesesskeyerror
    && $revokeuserid > 0 && $revokecourseid > 0);
$revokeattempted = ($revokepreview || ($revokelive && $revokeliveconfirm))
    && !$revokesesskeyerror && $revokeuserid > 0 && $revokecourseid > 0;

$forcebadgeuserid = optional_param('forcebadgeuserid', 0, PARAM_INT);
$forcebadgecourseid = optional_param('forcebadgecourseid', 0, PARAM_INT);
$forcebadgenotify = optional_param('forcebadgenotify', 0, PARAM_INT);
$forcebadgepreview = optional_param('forcebadgepreview', '', PARAM_RAW) !== '';
$forcebadgelive = optional_param('forcebadgelive', '', PARAM_RAW) !== '';
$forcebadgeliveconfirm = optional_param('forcebadgeliveconfirm', 0, PARAM_INT);
$forcebadgeformsubmitted = ($forcebadgepreview || $forcebadgelive);
if ($forcebadgeformsubmitted && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new moodle_exception('invalidrequest');
}
$forcebadgesesskeyerror = $forcebadgeformsubmitted && !confirm_sesskey();
$forcebadgelivepending = ($forcebadgelive && !$forcebadgeliveconfirm && !$forcebadgesesskeyerror
    && $forcebadgeuserid > 0 && $forcebadgecourseid > 0);
$forcebadgeattempted = ($forcebadgepreview || ($forcebadgelive && $forcebadgeliveconfirm))
    && !$forcebadgesesskeyerror && $forcebadgeuserid > 0 && $forcebadgecourseid > 0;

if (!in_array($repairwhat, gamification_gaps::allowed_repair_scopes(), true)) {
    $repairwhat = gamification_gaps::REPAIR_COINS;
}

/**
 * Shared form fields for repair page.
 *
 * @param int $userid
 * @param string $repairwhat
 * @param int $recalc
 * @param int $notify
 * @return void
 */
function local_studypace_repair_form_fields(
    int $userid,
    string $repairwhat,
    int $recalc,
    int $notify
): void {
    echo html_writer::start_div('form-group row align-items-center mb-2');
    echo html_writer::tag('label', get_string('recalculateuseridlabel', 'local_studypace'), [
        'class' => 'col-form-label col-md-3',
        'for' => 'studypace_repair_userid',
    ]);
    echo html_writer::start_div('col-md-9');
    echo html_writer::empty_tag('input', [
        'type' => 'number',
        'min' => '1',
        'required' => 'required',
        'id' => 'studypace_repair_userid',
        'name' => 'userid',
        'class' => 'form-control',
        'style' => 'max-width: 10rem;',
        'value' => $userid > 0 ? $userid : '',
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('form-group row align-items-center mb-2');
    echo html_writer::tag('label', get_string('repairwhatlabel', 'local_studypace'), [
        'class' => 'col-form-label col-md-3',
        'for' => 'studypace_repair_what',
    ]);
    echo html_writer::start_div('col-md-9');
    echo html_writer::select([
        gamification_gaps::REPAIR_BOTH => get_string('repairwhatboth', 'local_studypace'),
        gamification_gaps::REPAIR_COINS => get_string('repairwhatcoins', 'local_studypace'),
        gamification_gaps::REPAIR_BADGES => get_string('repairwhatbadges', 'local_studypace'),
    ], 'repairwhat', $repairwhat, false, [
        'id' => 'studypace_repair_what',
        'class' => 'custom-select',
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('form-group row align-items-center mb-2');
    echo html_writer::start_div('col-md-9 offset-md-3');
    echo html_writer::start_div('form-check');
    echo html_writer::empty_tag('input', [
        'type' => 'checkbox',
        'class' => 'form-check-input',
        'name' => 'recalc',
        'value' => '1',
        'id' => 'studypace_repair_recalc',
    ] + ($recalc ? ['checked' => 'checked'] : []));
    echo html_writer::tag('label', get_string('repairrecalclabel', 'local_studypace'), [
        'class' => 'form-check-label',
        'for' => 'studypace_repair_recalc',
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('form-group row align-items-center mb-2');
    echo html_writer::start_div('col-md-9 offset-md-3');
    echo html_writer::start_div('form-check');
    echo html_writer::empty_tag('input', [
        'type' => 'checkbox',
        'class' => 'form-check-input',
        'name' => 'notify',
        'value' => '1',
        'id' => 'studypace_repair_notify',
    ] + ($notify ? ['checked' => 'checked'] : []));
    echo html_writer::tag('label', get_string('repairnotifylabel', 'local_studypace'), [
        'class' => 'form-check-label',
        'for' => 'studypace_repair_notify',
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}

$baseurl = new moodle_url('/local/studypace/repair_coins.php');
$PAGE->set_url($baseurl);
$PAGE->set_title(get_string('repairrewardspageheading', 'local_studypace'));
$PAGE->set_heading(get_string('repairrewardspageheading', 'local_studypace'));

/**
 * @param \stdClass $result
 * @param bool $wasdryrun
 * @return void
 */
function local_studypace_render_repair_result(\stdClass $result, bool $wasdryrun): void {
    global $OUTPUT;

    $label = $wasdryrun
        ? get_string('repairdryrunsummary', 'local_studypace')
        : get_string('repairlivesummary', 'local_studypace');

    echo html_writer::tag('h5', $label);

    if (!empty($result->progress)) {
        echo $OUTPUT->notification(
            get_string('recalculaterepairsummary', 'local_studypace', (object) $result->progress),
            'info'
        );
    }

    echo $OUTPUT->notification(get_string('repaircoinssummary', 'local_studypace', (object) [
        'credited' => (int) ($result->coins->credited ?? 0),
        'skipped' => (int) ($result->coins->skipped ?? 0),
    ]), ($result->coins->credited ?? 0) > 0 ? 'success' : 'info');

    echo $OUTPUT->notification(get_string('repairbadgessummary', 'local_studypace', (object) [
        'awarded' => (int) ($result->badges->awarded ?? 0),
        'skipped' => (int) ($result->badges->skipped ?? 0),
    ]), ($result->badges->awarded ?? 0) > 0 ? 'success' : 'info');

    foreach (array_merge($result->coins->errors ?? [], $result->badges->errors ?? []) as $err) {
        if ($err === 'triggers_not_configured') {
            echo $OUTPUT->notification(get_string('gapstriggerswarning', 'local_studypace'), 'error');
        } else if ($err === 'autobadge_missing') {
            echo $OUTPUT->notification(get_string('repairautobadgemissing', 'local_studypace'), 'error');
        } else {
            echo $OUTPUT->notification($err, 'error');
        }
    }

    if (!empty($result->coins->details)) {
        $t = new html_table();
        $t->head = [
            get_string('recalculatediagheadername', 'local_studypace'),
            get_string('repaircoinscreditedcol', 'local_studypace'),
        ];
        foreach ($result->coins->details as $d) {
            $prefix = !empty($d->dryrun) ? get_string('repairwouldlabel', 'local_studypace') . ' ' : '';
            $t->data[] = [
                s($d->shortname) . ' (id=' . (int) $d->courseid . ')',
                $prefix . '+' . (int) $d->amount,
            ];
        }
        echo html_writer::table($t);
    }

    if (!empty($result->badges->details)) {
        $t = new html_table();
        $t->head = [
            get_string('recalculatediagheadername', 'local_studypace'),
            get_string('repairbadgesawardedcol', 'local_studypace'),
        ];
        foreach ($result->badges->details as $d) {
            $prefix = !empty($d->dryrun) ? get_string('repairwouldlabel', 'local_studypace') . ' ' : '';
            $t->data[] = [
                s($d->shortname) . ' (id=' . (int) $d->courseid . ')',
                $prefix . s($d->badge),
            ];
        }
        echo html_writer::table($t);
    }
}

$targetuser = null;
if ($userid > 0) {
    $targetuser = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id, firstname, lastname, username');
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('repairrewardspageheading', 'local_studypace'));

echo html_writer::tag('p', get_string('repairrewardsintro', 'local_studypace'));
echo html_writer::tag('p', html_writer::tag('em', get_string('repaircoinswarning', 'local_studypace')));

if (!gamification_gaps::triggers_configured()) {
    echo $OUTPUT->notification(get_string('gapstriggerswarning', 'local_studypace'), 'warning');
}

echo $OUTPUT->box_start('generalbox p-3 mb-4');
echo html_writer::tag('h4', get_string('repaircoinsformheading', 'local_studypace'));

// Um único formulário POST: evita userid vazio ao analisar num form e reparar no outro.
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $baseurl->out(false),
    'class' => 'mform',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
local_studypace_repair_form_fields($userid, $repairwhat, $recalc, $notify);
echo html_writer::start_div('form-group row');
echo html_writer::start_div('col-md-9 offset-md-3 d-flex flex-wrap');
echo html_writer::tag('button', get_string('repaircoinspreviewbutton', 'local_studypace'), [
    'type' => 'submit',
    'name' => 'action',
    'value' => 'preview',
    'class' => 'btn btn-secondary mr-2 mb-2',
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'repairdryrun',
    'value' => get_string('repairdryrunbutton', 'local_studypace'),
    'class' => 'btn btn-outline-primary mr-2 mb-2',
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'repairlive',
    'value' => get_string('repairrewardsbutton', 'local_studypace'),
    'class' => 'btn btn-primary mb-2',
]);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo $OUTPUT->box_end();

$showpreview = ($action === 'preview' && !$sesskeyerror && $userid > 0);
$showrepair = ($repairattempted && !$sesskeyerror && $userid > 0);

if ($sesskeyerror) {
    echo $OUTPUT->notification(get_string('gapsinvalidsesskey', 'local_studypace'), 'error');
} else if ($repairattempted && $userid <= 0) {
    echo $OUTPUT->notification(get_string('repaircoinsuseridrequired', 'local_studypace'), 'error');
} else if ($repairlivepending) {
    if (!$targetuser) {
        echo $OUTPUT->notification(get_string('recalculateusernotfound', 'local_studypace'), 'error');
    } else {
        echo $OUTPUT->confirm(
            get_string('repaircoinsliveconfirm', 'local_studypace', s(fullname($targetuser))),
            new moodle_url($baseurl, [
                'action' => 'repair',
                'confirm' => 1,
                'liveconfirm' => 1,
                'userid' => $userid,
                'repairwhat' => $repairwhat,
                'recalc' => $recalc,
                'notify' => $notify,
                'sesskey' => sesskey(),
            ]),
            $baseurl
        );
    }
}

if ($showpreview || $showrepair) {
    if (!$targetuser) {
        echo $OUTPUT->notification(get_string('recalculateusernotfound', 'local_studypace'), 'error');
    } else {
        echo html_writer::tag('h4', get_string('repaircoinsuserheading', 'local_studypace', s(fullname($targetuser))));

        if ($showrepair) {
            @set_time_limit(0);
            raise_memory_limit(MEMORY_HUGE);
            \core\session\manager::write_close();

            $options = (object) [
                'repairwhat' => $repairwhat,
                'dryrun' => !empty($dryrun),
                'quiet' => !$notify,
                'recalcstudypace' => !empty($recalc),
            ];
            $repairresult = gamification_gaps::repair_user((int) $targetuser->id, $options);

            echo $OUTPUT->notification(
                get_string($dryrun ? 'repairdryrundone' : 'repaircoinsdone', 'local_studypace', s(fullname($targetuser))),
                $dryrun ? 'info' : 'success'
            );

            echo $OUTPUT->box_start('generalbox p-3 mt-3');
            local_studypace_render_repair_result($repairresult, !empty($dryrun));
            echo $OUTPUT->box_end();
        }

        $gaps = gamification_gaps::gaps_for_user((int) $targetuser->id);
        $summary = gamification_gaps::summarize($gaps);

        echo $OUTPUT->box_start('generalbox p-3 mt-3');
        echo html_writer::tag('h4', get_string('repaircoinspreviewheading', 'local_studypace'));

        if ($summary->totalrows === 0) {
            echo $OUTPUT->notification(get_string('repaircoinsnoop', 'local_studypace'), 'info');
        } else {
            echo html_writer::tag('p', get_string('gapssummarytotal', 'local_studypace', (object) [
                'rows' => $summary->totalrows,
                'users' => 1,
            ]));

            $table = new html_table();
            $table->head = [
                get_string('recalculatediagheadername', 'local_studypace'),
                get_string('recalculatediagheadercompleted', 'local_studypace'),
                get_string('recalculatediaggamificationgrade', 'local_studypace'),
                get_string('recalculatediaggamificationbadge', 'local_studypace'),
                get_string('recalculatediaggamificationcoins', 'local_studypace'),
                get_string('gapsheadergaptype', 'local_studypace'),
            ];
            foreach ($gaps as $gap) {
                $badgetxt = $gap->has_badge
                    ? get_string('recalculatediagyes', 'local_studypace') . ' (' . s($gap->badge_issued_names) . ')'
                    : get_string('recalculatediagno', 'local_studypace') . ' (' . s($gap->badge_expected ?? '—') . ')';
                $coinstxt = get_string('gapscoinsformat', 'local_studypace', (object) [
                    'credited' => (int) $gap->coins_credited,
                    'expected' => $gap->coins_expected ?? '?',
                ]);
                $table->data[] = [
                    s($gap->courseshortname),
                    userdate($gap->actualcompletion),
                    ($gap->grade ?? '—') . '%',
                    $badgetxt,
                    $coinstxt,
                    get_string(gamification_gaps::gap_type_string_id($gap->gap_type), 'local_studypace'),
                ];
            }
            echo html_writer::table($table);
        }

        echo html_writer::tag('p', get_string('recalculatediaggamificationfootnote', 'local_studypace'), [
            'class' => 'text-muted small mb-0',
        ]);
        echo $OUTPUT->box_end();

        if ($showpreview && $summary->totalrows > 0) {
            echo html_writer::tag('p', get_string('repaircoinsconfirmhint', 'local_studypace'), ['class' => 'mt-3']);
        }
    }
}

echo $OUTPUT->box_start('generalbox p-3 mb-4 mt-4');
echo html_writer::tag('h4', get_string('revokecoinsformheading', 'local_studypace'));
echo html_writer::tag('p', get_string('revokecoinsintro', 'local_studypace'));
echo html_writer::tag('p', html_writer::tag('em', get_string('revokecoinswarning', 'local_studypace')));

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $baseurl->out(false),
    'class' => 'mform',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('form-group row align-items-center mb-2');
echo html_writer::tag('label', get_string('revokecoinsuseridlabel', 'local_studypace'), [
    'class' => 'col-form-label col-md-3',
    'for' => 'studypace_revoke_userid',
]);
echo html_writer::start_div('col-md-9');
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'min' => '1',
    'required' => 'required',
    'id' => 'studypace_revoke_userid',
    'name' => 'revokeuserid',
    'class' => 'form-control',
    'style' => 'max-width: 10rem;',
    'value' => $revokeuserid > 0 ? $revokeuserid : '',
]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('form-group row align-items-center mb-2');
echo html_writer::tag('label', get_string('revokecoinscourseidlabel', 'local_studypace'), [
    'class' => 'col-form-label col-md-3',
    'for' => 'studypace_revoke_courseid',
]);
echo html_writer::start_div('col-md-9');
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'min' => '1',
    'required' => 'required',
    'id' => 'studypace_revoke_courseid',
    'name' => 'revokecourseid',
    'class' => 'form-control',
    'style' => 'max-width: 10rem;',
    'value' => $revokecourseid > 0 ? $revokecourseid : '',
]);
echo html_writer::tag('p', get_string('revokecoinscourseidhelp', 'local_studypace'), [
    'class' => 'form-text text-muted mb-0',
]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('form-group row');
echo html_writer::start_div('col-md-9 offset-md-3 d-flex flex-wrap');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'revokepreview',
    'value' => get_string('revokecoinspreviewbutton', 'local_studypace'),
    'class' => 'btn btn-secondary mr-2 mb-2',
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'revokelive',
    'value' => get_string('revokecoinsexecutebutton', 'local_studypace'),
    'class' => 'btn btn-danger mb-2',
]);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo $OUTPUT->box_end();

if ($revokesesskeyerror) {
    echo $OUTPUT->notification(get_string('gapsinvalidsesskey', 'local_studypace'), 'error');
} else if ($revokeformsubmitted && ($revokeuserid <= 0 || $revokecourseid <= 0)) {
    echo $OUTPUT->notification(get_string('revokecoinsmissingids', 'local_studypace'), 'error');
} else if ($revokelivepending) {
    $revokeuser = $DB->get_record('user', ['id' => $revokeuserid, 'deleted' => 0], 'id, firstname, lastname');
    $revokecourse = $DB->get_record('course', ['id' => $revokecourseid], 'id, shortname, fullname');
    if (!$revokeuser || !$revokecourse) {
        echo $OUTPUT->notification(get_string('revokecoinsnotfound', 'local_studypace'), 'error');
    } else {
        $preview = studypace::revoke_course_planned_completion_coins($revokeuserid, $revokecourseid, true);
        echo $OUTPUT->confirm(
            get_string('revokecoinsliveconfirm', 'local_studypace', (object) [
                'user' => s(fullname($revokeuser)),
                'course' => s($revokecourse->shortname),
                'rows' => (int) $preview->ledger_rows,
                'coins' => (int) $preview->coins_total,
            ]),
            new moodle_url($baseurl, [
                'revokeuserid' => $revokeuserid,
                'revokecourseid' => $revokecourseid,
                'revokelive' => 1,
                'revokeliveconfirm' => 1,
                'sesskey' => sesskey(),
            ]),
            $baseurl
        );
    }
}

if ($revokeattempted) {
    $revokeresult = studypace::revoke_course_planned_completion_coins(
        $revokeuserid,
        $revokecourseid,
        $revokepreview || !$revokeliveconfirm
    );
    if (!$revokeresult->valid) {
        echo $OUTPUT->notification(get_string('revokecoinsnotfound', 'local_studypace'), 'error');
    } else if ($revokeresult->ledger_rows === 0) {
        echo $OUTPUT->notification(get_string('revokecoinsnoop', 'local_studypace'), 'info');
    } else {
        $dry = !empty($revokeresult->dryrun);
        echo $OUTPUT->notification(
            get_string($dry ? 'revokecoinspreviewdone' : 'revokecoinsdone', 'local_studypace', (object) [
                'user' => s(fullname($revokeresult->user)),
                'course' => s($revokeresult->course->shortname),
                'rows' => (int) $revokeresult->ledger_rows,
                'coins' => (int) $revokeresult->coins_total,
            ]),
            $dry ? 'info' : 'success'
        );
        if (!empty($revokeresult->errors)) {
            foreach ($revokeresult->errors as $err) {
                echo $OUTPUT->notification($err, 'warning');
            }
        }
    }
}

echo $OUTPUT->box_start('generalbox p-3 mb-4 mt-4');
echo html_writer::tag('h4', get_string('forcebadgeformheading', 'local_studypace'));
echo html_writer::tag('p', get_string('forcebadgeintro', 'local_studypace'));
echo html_writer::tag('p', html_writer::tag('em', get_string('forcebadgewarning', 'local_studypace')));

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $baseurl->out(false),
    'class' => 'mform',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('form-group row align-items-center mb-2');
echo html_writer::tag('label', get_string('forcebadgeuseridlabel', 'local_studypace'), [
    'class' => 'col-form-label col-md-3',
    'for' => 'studypace_forcebadge_userid',
]);
echo html_writer::start_div('col-md-9');
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'min' => '1',
    'required' => 'required',
    'id' => 'studypace_forcebadge_userid',
    'name' => 'forcebadgeuserid',
    'class' => 'form-control',
    'style' => 'max-width: 10rem;',
    'value' => $forcebadgeuserid > 0 ? $forcebadgeuserid : '',
]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('form-group row align-items-center mb-2');
echo html_writer::tag('label', get_string('forcebadgecourseidlabel', 'local_studypace'), [
    'class' => 'col-form-label col-md-3',
    'for' => 'studypace_forcebadge_courseid',
]);
echo html_writer::start_div('col-md-9');
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'min' => '1',
    'required' => 'required',
    'id' => 'studypace_forcebadge_courseid',
    'name' => 'forcebadgecourseid',
    'class' => 'form-control',
    'style' => 'max-width: 10rem;',
    'value' => $forcebadgecourseid > 0 ? $forcebadgecourseid : '',
]);
echo html_writer::tag('p', get_string('forcebadgecourseidhelp', 'local_studypace'), [
    'class' => 'form-text text-muted mb-0',
]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('form-group row align-items-center mb-2');
echo html_writer::start_div('col-md-9 offset-md-3');
echo html_writer::start_div('form-check');
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'class' => 'form-check-input',
    'name' => 'forcebadgenotify',
    'value' => '1',
    'id' => 'studypace_forcebadge_notify',
] + ($forcebadgenotify ? ['checked' => 'checked'] : []));
echo html_writer::tag('label', get_string('forcebadgenotifylabel', 'local_studypace'), [
    'class' => 'form-check-label',
    'for' => 'studypace_forcebadge_notify',
]);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('form-group row');
echo html_writer::start_div('col-md-9 offset-md-3 d-flex flex-wrap');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'forcebadgepreview',
    'value' => get_string('forcebadgepreviewbutton', 'local_studypace'),
    'class' => 'btn btn-secondary mr-2 mb-2',
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'forcebadgelive',
    'value' => get_string('forcebadgeexecutebutton', 'local_studypace'),
    'class' => 'btn btn-warning mb-2',
]);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo $OUTPUT->box_end();

if ($forcebadgesesskeyerror) {
    echo $OUTPUT->notification(get_string('gapsinvalidsesskey', 'local_studypace'), 'error');
} else if ($forcebadgeformsubmitted && ($forcebadgeuserid <= 0 || $forcebadgecourseid <= 0)) {
    echo $OUTPUT->notification(get_string('forcebadgemissingids', 'local_studypace'), 'error');
} else if ($forcebadgelivepending) {
    $preview = gamification_gaps::force_award_course_badge($forcebadgeuserid, $forcebadgecourseid, true, false);
    if (!$preview->valid) {
        echo $OUTPUT->notification(get_string('forcebadgenotfound', 'local_studypace'), 'error');
    } else if (!empty($preview->already_issued)) {
        echo $OUTPUT->notification(get_string('forcebadgealready', 'local_studypace', (object) [
            'user' => s(fullname($preview->user)),
            'course' => s($preview->course->shortname),
            'badge' => s($preview->badge_name),
        ]), 'info');
    } else if (!$preview->badge_exists) {
        echo $OUTPUT->notification(get_string('forcebadgemissingdef', 'local_studypace', s($preview->badge_name ?: '?')), 'error');
    } else {
        $confirmurl = new moodle_url($baseurl, [
            'forcebadgeuserid' => $forcebadgeuserid,
            'forcebadgecourseid' => $forcebadgecourseid,
            'forcebadgenotify' => $forcebadgenotify ? 1 : 0,
            'forcebadgelive' => 1,
            'forcebadgeliveconfirm' => 1,
            'sesskey' => sesskey(),
        ]);
        echo $OUTPUT->confirm(
            get_string('forcebadgeliveconfirm', 'local_studypace', (object) [
                'user' => s(fullname($preview->user)),
                'course' => s($preview->course->shortname),
                'badge' => s($preview->badge_name),
                'grade' => isset($preview->grade) ? round((float) $preview->grade, 1) : '—',
            ]),
            $confirmurl,
            $baseurl
        );
    }
}

if ($forcebadgeattempted) {
    $forceresult = gamification_gaps::force_award_course_badge(
        $forcebadgeuserid,
        $forcebadgecourseid,
        $forcebadgepreview || !$forcebadgeliveconfirm,
        !empty($forcebadgenotify)
    );
    if (!$forceresult->valid) {
        if (in_array('autobadge_missing', $forceresult->errors ?? [], true)) {
            echo $OUTPUT->notification(get_string('repairautobadgemissing', 'local_studypace'), 'error');
        } else {
            echo $OUTPUT->notification(get_string('forcebadgenotfound', 'local_studypace'), 'error');
        }
    } else if (!empty($forceresult->already_issued) && empty($forceresult->awarded) && empty($forceresult->executed)) {
        echo $OUTPUT->notification(get_string('forcebadgealready', 'local_studypace', (object) [
            'user' => s(fullname($forceresult->user)),
            'course' => s($forceresult->course->shortname),
            'badge' => s($forceresult->badge_name),
        ]), 'info');
    } else if (!$forceresult->badge_exists) {
        echo $OUTPUT->notification(get_string('forcebadgemissingdef', 'local_studypace', s($forceresult->badge_name ?: '?')), 'error');
    } else if (!empty($forceresult->dryrun)) {
        echo $OUTPUT->notification(get_string('forcebadgepreviewdone', 'local_studypace', (object) [
            'user' => s(fullname($forceresult->user)),
            'course' => s($forceresult->course->shortname),
            'badge' => s($forceresult->badge_name),
            'grade' => isset($forceresult->grade) ? round((float) $forceresult->grade, 1) : '—',
        ]), 'info');
        if ($forceresult->ontime === false) {
            echo $OUTPUT->notification(get_string('forcebadgelatenote', 'local_studypace'), 'warning');
        }
    } else if (!empty($forceresult->awarded)) {
        echo $OUTPUT->notification(get_string('forcebadgedone', 'local_studypace', (object) [
            'user' => s(fullname($forceresult->user)),
            'course' => s($forceresult->course->shortname),
            'badge' => s($forceresult->badge_name),
        ]), 'success');
    } else {
        echo $OUTPUT->notification(get_string('forcebadgefailed', 'local_studypace'), 'error');
        foreach ($forceresult->errors ?? [] as $err) {
            echo $OUTPUT->notification($err, 'warning');
        }
    }
}

echo $OUTPUT->footer();

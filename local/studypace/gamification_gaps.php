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
 * Admin report: users with on-time Eixo completions but missing keys/badges.
 *
 * @package   local_studypace
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_studypace\gamification_gaps;

admin_externalpage_setup('local_studypace_gamification_gaps');

$run = optional_param('run', 0, PARAM_INT);
$export = optional_param('export', 0, PARAM_INT);
$queuebatch = optional_param('queuebatch', 0, PARAM_INT);
$only = optional_param('only', gamification_gaps::FILTER_ANY, PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$repairwhat = optional_param('repairwhat', gamification_gaps::REPAIR_COINS, PARAM_ALPHA);
$batchdryrun = optional_param('queuedryrun', '', PARAM_RAW) !== '';
$queuelive = optional_param('queuelive', '', PARAM_RAW) !== '';
$batchconfirm = optional_param('batchconfirm', 0, PARAM_INT);
$batchnotify = optional_param('batchnotify', 0, PARAM_INT);
$batchrecalc = optional_param('batchrecalc', 0, PARAM_INT);
$batchsize = optional_param('batchsize', 50, PARAM_INT);

if (!in_array($repairwhat, gamification_gaps::allowed_repair_scopes(), true)) {
    $repairwhat = gamification_gaps::REPAIR_COINS;
}

if (!in_array($only, gamification_gaps::allowed_filters(), true)) {
    $only = gamification_gaps::FILTER_ANY;
}
$perpage = max(10, min(200, $perpage));

/**
 * Build the report URL preserving filters and sesskey (required for paging).
 *
 * @param string $only
 * @param int $userid
 * @param int $perpage
 * @return moodle_url
 */
function local_studypace_gaps_report_url(string $only, int $userid, int $perpage): moodle_url {
    $params = [
        'run' => 1,
        'only' => $only,
        'perpage' => $perpage,
        'sesskey' => sesskey(),
    ];
    if ($userid > 0) {
        $params['userid'] = $userid;
    }
    return new moodle_url('/local/studypace/gamification_gaps.php', $params);
}

$gaps = [];
$reportready = false;
$sesskeyerror = false;
$queuedcount = null;
$queueerror = null;
$batchawaitconfirm = false;
$confirmusercount = 0;

if ($queuebatch && confirm_sesskey()) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidrequest');
    }
    @set_time_limit(0);
    raise_memory_limit(MEMORY_HUGE);
    \core\session\manager::write_close();

    if ($batchdryrun || ($queuelive && $batchconfirm)) {
        try {
            $queuedcount = gamification_gaps::queue_batch_repair(
                $only,
                $repairwhat,
                $batchdryrun,
                !empty($batchnotify),
                !empty($batchrecalc),
                $batchsize
            );
        } catch (\moodle_exception $e) {
            $queueerror = $e->getMessage();
        }
    } else if ($queuelive && !$batchconfirm) {
        $confirmusercount = count(gamification_gaps::get_repair_userids($only));
        $batchawaitconfirm = true;
    }
}

if ($run || $export) {
    if (!confirm_sesskey()) {
        $sesskeyerror = true;
    } else {
        @set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);
        \core\session\manager::write_close();

        $gaps = gamification_gaps::find($userid, $only);
        $reportready = true;

        if ($export) {
            gamification_gaps::enrich($gaps);
            gamification_gaps::send_csv($gaps);
            exit;
        }
    }
}

$reporturl = local_studypace_gaps_report_url($only, $userid, $perpage);
$PAGE->set_url($reporturl);
if ($reportready && $page > 0) {
    $PAGE->set_url(new moodle_url($reporturl, ['page' => $page]));
}
$PAGE->set_title(get_string('gapspageheading', 'local_studypace'));
$PAGE->set_heading(get_string('gapspageheading', 'local_studypace'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gapspageheading', 'local_studypace'));

if ($sesskeyerror) {
    echo $OUTPUT->notification(get_string('gapsinvalidsesskey', 'local_studypace'), 'error');
}

echo html_writer::tag('p', get_string('gapsintro', 'local_studypace'));
echo html_writer::tag('p', html_writer::tag('em', get_string('gapwarning', 'local_studypace')));

if (!gamification_gaps::triggers_configured()) {
    echo $OUTPUT->notification(get_string('gapstriggerswarning', 'local_studypace'), 'warning');
}

echo $OUTPUT->box_start('generalbox p-3 mb-4');
echo html_writer::tag('h4', get_string('gapsfilterheading', 'local_studypace'));
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/studypace/gamification_gaps.php'))->out(false),
    'class' => 'mform',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'run', 'value' => '1']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('form-group row align-items-center mb-2');
echo html_writer::tag('label', get_string('gapsfilteronlylabel', 'local_studypace'), [
    'class' => 'col-form-label col-md-3',
    'for' => 'studypace_gaps_only',
]);
echo html_writer::start_div('col-md-9');
echo html_writer::select([
    gamification_gaps::FILTER_ANY => get_string('gapsfilterany', 'local_studypace'),
    gamification_gaps::FILTER_BOTH => get_string('gapsfilterboth', 'local_studypace'),
    gamification_gaps::FILTER_BADGE => get_string('gapsfilterbadge', 'local_studypace'),
    gamification_gaps::FILTER_COINS => get_string('gapsfiltercoins', 'local_studypace'),
], 'only', $only, false, ['id' => 'studypace_gaps_only', 'class' => 'custom-select']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('form-group row align-items-center mb-2');
echo html_writer::tag('label', get_string('recalculateuseridlabel', 'local_studypace'), [
    'class' => 'col-form-label col-md-3',
    'for' => 'studypace_gaps_userid',
]);
echo html_writer::start_div('col-md-9');
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'min' => '0',
    'id' => 'studypace_gaps_userid',
    'name' => 'userid',
    'class' => 'form-control',
    'style' => 'max-width: 10rem;',
    'value' => $userid > 0 ? $userid : '',
    'placeholder' => get_string('gapsfilteruseridplaceholder', 'local_studypace'),
]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('form-group row align-items-center mb-2');
echo html_writer::tag('label', get_string('gapsfilterperpagelabel', 'local_studypace'), [
    'class' => 'col-form-label col-md-3',
    'for' => 'studypace_gaps_perpage',
]);
echo html_writer::start_div('col-md-9');
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'min' => '10',
    'max' => '200',
    'id' => 'studypace_gaps_perpage',
    'name' => 'perpage',
    'class' => 'form-control',
    'style' => 'max-width: 10rem;',
    'value' => $perpage,
]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('form-group row');
echo html_writer::start_div('col-md-9 offset-md-3');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => get_string('gapsrunbutton', 'local_studypace'),
]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_tag('form');
echo $OUTPUT->box_end();

if ($reportready) {
    $summary = gamification_gaps::summarize($gaps);

    echo $OUTPUT->box_start('generalbox p-3 mb-4');
    echo html_writer::tag('h4', get_string('gapssummaryheading', 'local_studypace'));

    if ($summary->totalrows === 0) {
        echo $OUTPUT->notification(get_string('gapsnonfound', 'local_studypace'), 'info');
    } else {
        echo html_writer::tag('p', get_string('gapssummarytotal', 'local_studypace', (object) [
            'rows' => $summary->totalrows,
            'users' => $summary->usercount,
        ]));
        echo html_writer::start_tag('ul');
        echo html_writer::tag('li', get_string('gapssummaryboth', 'local_studypace', $summary->bytype[gamification_gaps::FILTER_BOTH]));
        echo html_writer::tag('li', get_string('gapssummarybadge', 'local_studypace', $summary->bytype[gamification_gaps::FILTER_BADGE]));
        echo html_writer::tag('li', get_string('gapssummarycoins', 'local_studypace', $summary->bytype[gamification_gaps::FILTER_COINS]));
        echo html_writer::end_tag('ul');

        if (!empty($summary->bycourse)) {
            $coursetable = new html_table();
            $coursetable->head = [
                get_string('recalculatediagheadername', 'local_studypace'),
                get_string('gapssummarycourserows', 'local_studypace'),
                get_string('gapssummarycourseusers', 'local_studypace'),
            ];
            foreach ($summary->bycourse as $courseid => $info) {
                $coursecontext = context_course::instance((int) $courseid);
                $coursetable->data[] = [
                    s($info->shortname) . ' — ' . format_string($info->name, true, ['context' => $coursecontext]),
                    $info->rows,
                    count($info->users),
                ];
            }
            echo html_writer::table($coursetable);
        }

        $exporturl = new moodle_url('/local/studypace/gamification_gaps.php', [
            'export' => 1,
            'only' => $only,
            'userid' => $userid,
            'sesskey' => sesskey(),
        ]);
        echo html_writer::div(
            html_writer::link($exporturl, get_string('gapsexportcsv', 'local_studypace'), ['class' => 'btn btn-secondary']),
            'mt-3'
        );
    }
    echo $OUTPUT->box_end();

    if ($summary->totalrows > 0) {
        $gapslist = array_values($gaps);
        $totalpages = (int) ceil($summary->totalrows / $perpage);
        $page = max(0, min($page, max(0, $totalpages - 1)));
        $pagegaps = array_slice($gapslist, $page * $perpage, $perpage, true);
        gamification_gaps::enrich($pagegaps);

        echo $OUTPUT->box_start('generalbox p-3');
        echo html_writer::tag('h4', get_string('gapsdetailheading', 'local_studypace'));

        $detailtable = new html_table();
        $detailtable->head = [
            get_string('gapsheaderuser', 'local_studypace'),
            get_string('recalculatediagheadername', 'local_studypace'),
            get_string('recalculatediagheadercompleted', 'local_studypace'),
            get_string('recalculatediaggamificationgrade', 'local_studypace'),
            get_string('recalculatediaggamificationbadge', 'local_studypace'),
            get_string('recalculatediaggamificationcoins', 'local_studypace'),
            get_string('gapsheadergaptype', 'local_studypace'),
        ];

        foreach ($pagegaps as $gap) {
            $usercell = s(fullname($gap)) . html_writer::empty_tag('br')
                . html_writer::tag('span', 'id=' . (int) $gap->userid . ' · ' . s($gap->username), ['class' => 'text-muted small']);
            $badgetxt = $gap->has_badge
                ? get_string('recalculatediagyes', 'local_studypace') . ' (' . s($gap->badge_issued_names) . ')'
                : get_string('recalculatediagno', 'local_studypace') . ' (' . s($gap->badge_expected ?? '—') . ')';
            $coinstxt = get_string('gapscoinsformat', 'local_studypace', (object) [
                'credited' => (int) $gap->coins_credited,
                'expected' => $gap->coins_expected ?? '?',
            ]);
            $recalcaction = new moodle_url('/local/studypace/recalculate.php');
            $recalcform = html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $recalcaction->out(false),
                'class' => 'd-inline',
            ]);
            $recalcform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $recalcform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
            $recalcform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'mode', 'value' => 'user']);
            $recalcform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => (int) $gap->userid]);
            $recalcform .= html_writer::tag('button', get_string('gapslinkrecalculate', 'local_studypace'), [
                'type' => 'submit',
                'class' => 'btn btn-link btn-sm p-0 align-baseline',
            ]);
            $recalcform .= html_writer::end_tag('form');
            $repairurl = new moodle_url('/local/studypace/repair_coins.php', [
                'userid' => (int) $gap->userid,
                'repairwhat' => gamification_gaps::REPAIR_COINS,
            ]);

            $detailtable->data[] = [
                $usercell . ' ' . $recalcform
                . ' · '
                . html_writer::link($repairurl, get_string('repaircoinslink', 'local_studypace'), ['class' => 'small']),
                s($gap->courseshortname),
                userdate($gap->actualcompletion),
                ($gap->grade ?? '—') . '%',
                $badgetxt,
                $coinstxt,
                get_string(gamification_gaps::gap_type_string_id($gap->gap_type), 'local_studypace'),
            ];
        }

        echo html_writer::table($detailtable);

        if ($totalpages > 1) {
            echo $OUTPUT->paging_bar(
                $summary->totalrows,
                $page,
                $perpage,
                $reporturl
            );
        }

        echo $OUTPUT->box_end();
    }
}

echo $OUTPUT->box_start('generalbox p-3 mt-4');
echo html_writer::tag('h4', get_string('repairbatchheading', 'local_studypace'));
echo html_writer::tag('p', get_string('repairbatchintro', 'local_studypace'));
echo html_writer::tag('p', html_writer::tag('em', get_string('repairbatchwarning', 'local_studypace')));

if (gamification_gaps::has_pending_batch_repair()) {
    echo $OUTPUT->notification(get_string('repairbatchrunning', 'local_studypace'), 'info');
}

if ($queueerror !== null) {
    echo $OUTPUT->notification($queueerror, 'error');
}

if ($batchawaitconfirm) {
    $repairwhatlabels = [
        gamification_gaps::REPAIR_BOTH => get_string('repairwhatboth', 'local_studypace'),
        gamification_gaps::REPAIR_COINS => get_string('repairwhatcoins', 'local_studypace'),
        gamification_gaps::REPAIR_BADGES => get_string('repairwhatbadges', 'local_studypace'),
    ];
    $yesno = function(bool $flag): string {
        return $flag
            ? get_string('recalculatediagyes', 'local_studypace')
            : get_string('recalculatediagno', 'local_studypace');
    };

    echo $OUTPUT->box_start('generalbox p-3 mb-3 border-warning');
    echo html_writer::tag('h5', get_string('repairbatchconfirmheading', 'local_studypace'));
    if ($confirmusercount === 0) {
        echo $OUTPUT->notification(get_string('gapsnonfound', 'local_studypace'), 'info');
    } else {
        echo html_writer::tag('p', get_string('repairbatchconfirminfo', 'local_studypace', (object) [
            'users' => $confirmusercount,
            'scope' => $repairwhatlabels[$repairwhat] ?? $repairwhat,
            'recalc' => $yesno(!empty($batchrecalc)),
            'notify' => $yesno(!empty($batchnotify)),
            'batchsize' => max(10, min(200, $batchsize)),
        ]));
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => (new moodle_url('/local/studypace/gamification_gaps.php'))->out(false),
            'class' => 'mform mt-2',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'queuebatch', 'value' => '1']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'batchconfirm', 'value' => '1']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'queuelive', 'value' => '1']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'only', 'value' => $only]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'repairwhat', 'value' => $repairwhat]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'batchsize', 'value' => max(10, min(200, $batchsize))]);
        if (!empty($batchrecalc)) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'batchrecalc', 'value' => '1']);
        }
        if (!empty($batchnotify)) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'batchnotify', 'value' => '1']);
        }
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-danger',
            'value' => get_string('repairbatchconfirmbutton', 'local_studypace'),
        ]);
        echo html_writer::end_tag('form');
    }
    echo $OUTPUT->box_end();
}

if ($queuedcount !== null) {
    if ($queuedcount > 0) {
        echo $OUTPUT->notification(get_string('repairbatchqueued', 'local_studypace', (object) [
            'users' => $queuedcount,
            'dryrun' => $batchdryrun ? get_string('recalculatediagyes', 'local_studypace') : get_string('recalculatediagno', 'local_studypace'),
        ]), 'success');
        echo html_writer::tag('p', get_string('repairbatchcronhint', 'local_studypace'), ['class' => 'text-muted small']);
    } else {
        echo $OUTPUT->notification(get_string('gapsnonfound', 'local_studypace'), 'info');
    }
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => (new moodle_url('/local/studypace/gamification_gaps.php'))->out(false),
    'class' => 'mform',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'queuebatch', 'value' => '1']);

echo html_writer::start_div('form-group row align-items-center mb-2');
echo html_writer::tag('label', get_string('gapsfilteronlylabel', 'local_studypace'), [
    'class' => 'col-form-label col-md-3',
    'for' => 'studypace_batch_only',
]);
echo html_writer::start_div('col-md-9');
echo html_writer::select([
    gamification_gaps::FILTER_ANY => get_string('gapsfilterany', 'local_studypace'),
    gamification_gaps::FILTER_BOTH => get_string('gapsfilterboth', 'local_studypace'),
    gamification_gaps::FILTER_BADGE => get_string('gapsfilterbadge', 'local_studypace'),
    gamification_gaps::FILTER_COINS => get_string('gapsfiltercoins', 'local_studypace'),
], 'only', $only, false, ['id' => 'studypace_batch_only', 'class' => 'custom-select']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('form-group row align-items-center mb-2');
echo html_writer::tag('label', get_string('repairwhatlabel', 'local_studypace'), [
    'class' => 'col-form-label col-md-3',
    'for' => 'studypace_batch_repairwhat',
]);
echo html_writer::start_div('col-md-9');
echo html_writer::select([
    gamification_gaps::REPAIR_BOTH => get_string('repairwhatboth', 'local_studypace'),
    gamification_gaps::REPAIR_COINS => get_string('repairwhatcoins', 'local_studypace'),
    gamification_gaps::REPAIR_BADGES => get_string('repairwhatbadges', 'local_studypace'),
], 'repairwhat', $repairwhat, false, ['id' => 'studypace_batch_repairwhat', 'class' => 'custom-select']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('form-group row align-items-center mb-2');
echo html_writer::tag('label', get_string('repairbatchsizelabel', 'local_studypace'), [
    'class' => 'col-form-label col-md-3',
    'for' => 'studypace_batch_size',
]);
echo html_writer::start_div('col-md-9');
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'min' => '10',
    'max' => '200',
    'id' => 'studypace_batch_size',
    'name' => 'batchsize',
    'class' => 'form-control',
    'style' => 'max-width: 10rem;',
    'value' => max(10, min(200, $batchsize)),
]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('form-group row align-items-center mb-2');
echo html_writer::start_div('col-md-9 offset-md-3');
echo html_writer::start_div('form-check mb-2');
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'class' => 'form-check-input',
    'name' => 'batchrecalc',
    'value' => '1',
    'id' => 'studypace_batch_recalc',
] + ($batchrecalc ? ['checked' => 'checked'] : []));
echo html_writer::tag('label', get_string('repairrecalclabel', 'local_studypace'), [
    'class' => 'form-check-label',
    'for' => 'studypace_batch_recalc',
]);
echo html_writer::end_div();
echo html_writer::start_div('form-check');
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'class' => 'form-check-input',
    'name' => 'batchnotify',
    'value' => '1',
    'id' => 'studypace_batch_notify',
] + ($batchnotify ? ['checked' => 'checked'] : []));
echo html_writer::tag('label', get_string('repairnotifylabel', 'local_studypace'), [
    'class' => 'form-check-label',
    'for' => 'studypace_batch_notify',
]);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('form-group row');
echo html_writer::start_div('col-md-9 offset-md-3 d-flex flex-wrap');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'queuedryrun',
    'value' => get_string('repairbatchqueuedryrun', 'local_studypace'),
    'class' => 'btn btn-outline-primary mr-2 mb-2',
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'queuelive',
    'class' => 'btn btn-primary mb-2',
    'value' => get_string('repairbatchqueuelive', 'local_studypace'),
]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_tag('form');
echo $OUTPUT->box_end();

echo $OUTPUT->footer();

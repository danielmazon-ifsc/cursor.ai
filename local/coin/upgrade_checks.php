<?php
// This file is part of Moodle - http://moodle.org/
//
// @package   local_coin
// @copyright 2026 Viddia (http://viddia.com.br)
// @license   http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later

/**
 * Admin: pre/post upgrade health checks for coin ledger and stacks.
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_coin\coin_health;

admin_externalpage_setup('local_coin_upgrade_checks');

$rebuild = optional_param('rebuild', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/coin/upgrade_checks.php'));
$PAGE->set_title(get_string('upgradecheckspageheading', 'local_coin'));
$PAGE->set_heading(get_string('upgradecheckspageheading', 'local_coin'));

$rebuildstats = null;
if ($rebuild && $confirm && confirm_sesskey()) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidrequest');
    }
    $rebuildstats = coin_health::rebuild_stacks_from_ledger();
}

$duplicates = coin_health::get_duplicate_ledger_report();
$mismatches = coin_health::get_stack_ledger_mismatch_report();
$orphans = coin_health::get_ledger_without_stack_report();

$coinversion = get_config('local_coin', 'version');
$studypaceversion = get_config('local_studypace', 'version');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('upgradecheckspageheading', 'local_coin'));

echo html_writer::tag('p', get_string('upgradechecksintro', 'local_coin'));

// --- Installed versions ---
echo $OUTPUT->box_start('generalbox p-3 mb-4');
echo html_writer::tag('h4', get_string('upgradechecksversionsheading', 'local_coin'));
$versiontable = new html_table();
$versiontable->head = [
    get_string('upgradechecksplugincol', 'local_coin'),
    get_string('upgradechecksversioncol', 'local_coin'),
];
$versiontable->data = [
    ['local_coin', $coinversion ?: '—'],
    ['local_studypace', $studypaceversion ?: '—'],
];
echo html_writer::table($versiontable);
echo html_writer::tag('p', get_string('upgradechecksversionshint', 'local_coin'), ['class' => 'text-muted small']);
echo $OUTPUT->box_end();

// --- Before upgrade ---
echo $OUTPUT->box_start('generalbox p-3 mb-4');
echo html_writer::tag('h4', get_string('upgradechecksbeforeheading', 'local_coin'));
echo html_writer::tag('p', get_string('upgradechecksbeforeintro', 'local_coin'));

if ($duplicates->totalgroups === 0) {
    echo $OUTPUT->notification(get_string('upgradechecksnoduplicates', 'local_coin'), 'success');
} else {
    echo $OUTPUT->notification(get_string('upgradechecksduplicatesfound', 'local_coin', (object) [
        'groups' => $duplicates->totalgroups,
        'extras' => $duplicates->extrasrows,
    ]), 'warning');
    echo html_writer::tag('p', get_string('upgradechecksduplicatehint', 'local_coin'));

    if (!empty($duplicates->samples)) {
        $dtable = new html_table();
        $dtable->head = [
            get_string('upgradechecksusercol', 'local_coin'),
            get_string('upgradecheckscoursecol', 'local_coin'),
            get_string('upgradechecksactioncol', 'local_coin'),
            get_string('upgradechecksduplicatecountcol', 'local_coin'),
        ];
        foreach ($duplicates->samples as $row) {
            $usercell = html_writer::link(
                new moodle_url('/user/view.php', ['id' => $row->userid]),
                $row->userid
            );
            $dtable->data[] = [
                $usercell,
                (int) $row->courseid,
                s(\core_text::substr($row->action, 0, 80)),
                (int) $row->duplicatecount,
            ];
        }
        echo html_writer::table($dtable);
        if ($duplicates->totalgroups > count($duplicates->samples)) {
            echo html_writer::tag('p', get_string('upgradecheckssampletruncated', 'local_coin'), ['class' => 'text-muted small']);
        }
    }
}
echo $OUTPUT->box_end();

// --- After upgrade ---
echo $OUTPUT->box_start('generalbox p-3 mb-4');
echo html_writer::tag('h4', get_string('upgradechecksafterheading', 'local_coin'));
echo html_writer::tag('p', get_string('upgradechecksafterintro', 'local_coin'));

if ($rebuildstats !== null) {
    echo $OUTPUT->notification(get_string('upgradechecksrebuilddone', 'local_coin', $rebuildstats), 'success');
    $mismatches = coin_health::get_stack_ledger_mismatch_report();
    $orphans = coin_health::get_ledger_without_stack_report();
}

$allok = ($mismatches->totalmismatches === 0 && $orphans->totalorphans === 0);
if ($allok) {
    echo $OUTPUT->notification(get_string('upgradechecksbalancesok', 'local_coin'), 'success');
} else {
    if ($mismatches->totalmismatches > 0) {
        echo $OUTPUT->notification(get_string('upgradechecksmismatchfound', 'local_coin', (object) [
            'count' => $mismatches->totalmismatches,
        ]), 'warning');
        $mtable = new html_table();
        $mtable->head = [
            get_string('upgradechecksusercol', 'local_coin'),
            get_string('upgradecheckscoursecol', 'local_coin'),
            get_string('upgradechecksstackcol', 'local_coin'),
            get_string('upgradechecksledgercol', 'local_coin'),
        ];
        foreach ($mismatches->samples as $row) {
            $usercell = html_writer::link(
                new moodle_url('/user/view.php', ['id' => $row->userid]),
                $row->userid
            );
            $mtable->data[] = [
                $usercell,
                (int) $row->courseid,
                (int) $row->stackcoins,
                (int) $row->ledgersum,
            ];
        }
        echo html_writer::table($mtable);
    }
    if ($orphans->totalorphans > 0) {
        echo $OUTPUT->notification(get_string('upgradechecksorphansfound', 'local_coin', (object) [
            'count' => $orphans->totalorphans,
        ]), 'warning');
    }
}

$rebuildurl = new moodle_url('/local/coin/upgrade_checks.php', [
    'rebuild' => 1,
    'confirm' => 1,
    'sesskey' => sesskey(),
]);
echo html_writer::tag('p', get_string('upgradechecksrebuildhint', 'local_coin'));
echo $OUTPUT->single_button($rebuildurl, get_string('upgradechecksrebuildbutton', 'local_coin'), 'post');
echo $OUTPUT->box_end();

// --- Next steps (studypace) ---
echo $OUTPUT->box_start('generalbox p-3 mb-4');
echo html_writer::tag('h4', get_string('upgradechecksnextheading', 'local_coin'));
echo html_writer::tag('p', get_string('upgradechecksnextintro', 'local_coin'));
$links = html_writer::alist([
    html_writer::link(
        new moodle_url('/admin/index.php'),
        get_string('upgradecheckslinknotifications', 'local_coin')
    ),
    html_writer::link(
        new moodle_url('/local/studypace/gamification_gaps.php'),
        get_string('upgradecheckslinkgaps', 'local_coin')
    ),
    html_writer::link(
        new moodle_url('/local/studypace/repair_coins.php'),
        get_string('upgradecheckslinkrepair', 'local_coin')
    ),
    html_writer::link(
        new moodle_url('/admin/purgecaches.php'),
        get_string('upgradecheckslinkpurge', 'local_coin')
    ),
]);
echo $links;
echo $OUTPUT->box_end();

echo $OUTPUT->footer();

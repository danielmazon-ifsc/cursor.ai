<?php

/**
 * Coin ledger report
 *
 * @package   local_coin
 * @copyright 2021 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require_once '../../../../config.php';
require_once './locallib.php';
require_once './coinreportledger_form.php';

use local_coin\coin_report_ledger;

require_login();

$userid = optional_param('userid', $USER->id, PARAM_INT);
if ($userid <= 0) {
    $userid = (int) $USER->id;
}
$startdate = optional_param_array('startdate', [], PARAM_INT);
$finishdate = optional_param_array('finishdate', [], PARAM_INT);
$start = 0;
$finish = 0;
$pagenum = optional_param('pagenum', 0, PARAM_INT);

$mform = new coinreportledger_form(null, []);
if ($fromform = $mform->get_data()) {
    $userid = (int) $fromform->userid;
    if (!empty($fromform->startdate)) {
        $startdate = (array) $fromform->startdate;
    }
    if (!empty($fromform->finishdate)) {
        $finishdate = (array) $fromform->finishdate;
    }
}

if (!empty($startdate['year'])) {
    $start = make_timestamp(
        (int) $startdate['year'],
        (int) ($startdate['month'] ?? 1),
        (int) ($startdate['day'] ?? 1)
    );
}
if (!empty($finishdate['year'])) {
    $finish = make_timestamp(
        (int) $finishdate['year'],
        (int) ($finishdate['month'] ?? 1),
        (int) ($finishdate['day'] ?? 1)
    );
}

$targetcontext = context_user::instance($userid);
if ($userid === (int) $USER->id) {
    $user = $USER;
} else {
    $systemcontext = context_system::instance();
    if (!has_capability('local/coin:viewledger', $systemcontext)
            && !has_capability('moodle/user:viewdetails', $targetcontext)) {
        require_capability('local/coin:viewledger', $targetcontext);
    }
    $user = core_user::get_user($userid, '*', MUST_EXIST);
}

$PAGE->set_context($targetcontext);

$defaults = [
    'userid' => $user->id,
    'startdate' => $startdate,
    'finishdate' => $finishdate,
];
$mform->set_data($defaults);

$reporturl = new moodle_url('/local/coin/report/ledger/index.php', ['userid' => $user->id]);
$PAGE->set_url($reporturl);
$PAGE->set_pagelayout('standard');
$header = get_string('coinsledger', 'local_coin') . ' - ' . fullname($user);
$PAGE->set_title($header);
$PAGE->set_heading($header);

echo $OUTPUT->header();
echo $OUTPUT->heading($header);
echo html_writer::start_div('dateform', array('style' => 'padding-bottom: 2rem;'));
$mform->display();
echo html_writer::end_div();
$report = new coin_report_ledger($user);
echo $report->print_report($start, $finish, $pagenum);
echo $OUTPUT->footer();

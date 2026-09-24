<?php

/**
 * This page deals with processing responses during an attempt at a cquiz.
 *
 * People will normally arrive here from a form submission on attempt.php or
 * summary.php, and once the responses are processed, they will be redirected to
 * attempt.php or summary.php.
 *
 * This code used to be near the top of attempt.php, if you are looking for CVS history.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/cquiz/locallib.php');

// Remember the current time as the time any responses were submitted
// (so as to make sure students don't get penalized for slow processing on this page).
$timenow = time();

// Get submitted parameters.
$attemptid = required_param('attempt', PARAM_INT);
$thispage = optional_param('thispage', 0, PARAM_INT);
$nextpage = optional_param('nextpage', 0, PARAM_INT);
$previous = optional_param('previous', false, PARAM_BOOL);
$next = optional_param('next', false, PARAM_BOOL);
$finishattempt = optional_param('finishattempt', false, PARAM_BOOL);
$timeup = optional_param('timeup', 0, PARAM_BOOL); // True if form was submitted by timer.
$scrollpos = optional_param('scrollpos', '', PARAM_RAW);

$attemptobj = cquiz_attempt::create($attemptid);

// Set $nexturl now.
if ($next) {
    $page = $nextpage;
} else if ($previous && $thispage > 0) {
    $page = $thispage - 1;
} else {
    $page = $thispage;
}
if ($page == -1) {
    $nexturl = $attemptobj->summary_url();
} else {
    $nexturl = $attemptobj->attempt_url(null, $page);
    if ($scrollpos !== '') {
        $nexturl->param('scrollpos', $scrollpos);
    }
}

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());
require_sesskey();

$pageurl = new moodle_url('/mod/cquiz/processattempt.php', array(
    'attempt' => $attemptid
        ));
$PAGE->set_url($pageurl);

// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
    throw new moodle_cquiz_exception($attemptobj->get_cquizobj(), 'notyourattempt');
}

// Check capabilities.
if (!$attemptobj->is_preview_user()) {
    $attemptobj->require_capability('mod/cquiz:attempt');
}

// If the attempt is already closed, send them to the review page.
if ($attemptobj->is_finished()) {
    throw new moodle_cquiz_exception($attemptobj->get_cquizobj(), 'attemptalreadyclosed', null, $attemptobj->review_url());
}

// Process the attempt, getting the new status for the attempt.
$status = $attemptobj->process_attempt($timenow, $finishattempt, $timeup, $thispage);

if ($status == cquiz_attempt::OVERDUE) {
    redirect($attemptobj->summary_url());
} else if ($status == cquiz_attempt::IN_PROGRESS) {
    redirect($nexturl);
} else {
    // Attempt abandoned or finished.
    redirect($attemptobj->review_url());
}

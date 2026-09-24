<?php

/**
 * This page prints a review of a particular question attempt.
 * This page is expected to only be used in a popup window.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_once('locallib.php');

$attemptid = required_param('attempt', PARAM_INT);
$slot = required_param('slot', PARAM_INT);
$seq = optional_param('step', null, PARAM_INT);

$baseurl = new moodle_url('/mod/cquiz/reviewquestion.php', array('attempt' => $attemptid, 'slot' => $slot));
$currenturl = new moodle_url($baseurl);
if (!is_null($seq)) {
    $currenturl->param('step', $seq);
}
$PAGE->set_url($currenturl);

$attemptobj = cquiz_attempt::create($attemptid);

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());
$attemptobj->check_review_capability();
$student = $DB->get_record('user', array('id' => $attemptobj->get_userid()));

$accessmanager = $attemptobj->get_access_manager(time());
$options = $attemptobj->get_display_options(true);

$PAGE->set_pagelayout('popup');
$PAGE->set_title(get_string('reviewofquestion', 'cquiz', array(
    'question' => format_string($attemptobj->get_question_name($slot)),
    'cquiz' => format_string($attemptobj->get_cquiz_name()), 'user' => fullname($student))));
$PAGE->set_heading($attemptobj->get_course()->fullname);
$output = $PAGE->get_renderer('mod_cquiz');

// Check permissions - warning there is similar code in review.php and
// cquiz_attempt::check_file_access. If you change on, change them all.
if ($attemptobj->is_own_attempt()) {
    if (!$attemptobj->is_finished()) {
        echo $output->review_question_not_allowed($attemptobj, get_string('cannotreviewopen', 'cquiz'));
        die();
    } else if (!$options->attempt) {
        echo $output->review_question_not_allowed($attemptobj, $attemptobj->cannot_review_message());
        die();
    }
} else if (!$attemptobj->is_review_allowed()) {
    throw new moodle_cquiz_exception($attemptobj->get_cquizobj(), 'noreviewattempt');
}

// Prepare summary informat about this question attempt.
$summarydata = array();

// Student name.
$userpicture = new user_picture($student);
$userpicture->courseid = $attemptobj->get_courseid();
$summarydata['user'] = array(
    'title' => $userpicture,
    'content' => new action_link(new moodle_url('/user/view.php', array(
        'id' => $student->id, 'course' => $attemptobj->get_courseid())), fullname($student, true)),
);

// Cquiz name.
$summarydata['cquizname'] = array(
    'title' => get_string('modulename', 'cquiz'),
    'content' => format_string($attemptobj->get_cquiz_name()),
);

// Question name.
$summarydata['questionname'] = array(
    'title' => get_string('question', 'cquiz'),
    'content' => $attemptobj->get_question_name($slot),
);

// Other attempts at the cquiz.
if ($attemptobj->has_capability('mod/cquiz:viewreports')) {
    $attemptlist = $attemptobj->links_to_other_attempts($baseurl);
    if ($attemptlist) {
        $summarydata['attemptlist'] = array(
            'title' => get_string('attempts', 'cquiz'),
            'content' => $attemptlist,
        );
    }
}

// Timestamp of this action.
$timestamp = $attemptobj->get_question_action_time($slot);
if ($timestamp) {
    $summarydata['timestamp'] = array(
        'title' => get_string('completedon', 'cquiz'),
        'content' => userdate($timestamp),
    );
}

echo $output->review_question_page($attemptobj, $slot, $seq, $attemptobj->get_display_options(true), $summarydata);

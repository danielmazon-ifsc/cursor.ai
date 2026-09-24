<?php

/**
 * This script deals with starting a new attempt at a cquiz.
 *
 * Normally, it will end up redirecting to attempt.php - unless a password form is displayed.
 *
 * This code used to be at the top of attempt.php, if you are looking for CVS history.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/cquiz/locallib.php');

// Get submitted parameters.
$id = required_param('cmid', PARAM_INT); // Course module id
$forcenew = optional_param('forcenew', false, PARAM_BOOL); // Used to force a new preview
$page = optional_param('page', -1, PARAM_INT); // Page to jump to in the attempt.
$preview = optional_param('preview', false, PARAM_BOOL); // Start as a preview, not a real attempt

if (!$cm = get_coursemodule_from_id('cquiz', $id)) {
    print_error('invalidcoursemodule');
}
if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error("coursemisconf");
}

$cquizobj = cquiz::create($cm->instance, $USER->id);
// This script should only ever be posted to, so set page URL to the view page.
$PAGE->set_url($cquizobj->view_url());

// Check login and sesskey.
require_login($cquizobj->get_course(), false, $cquizobj->get_cm());
require_sesskey();
$PAGE->set_heading($cquizobj->get_course()->fullname);

// If no questions have been set up yet redirect to edit.php or display an error.
if (!$cquizobj->has_questions()) {
    if ($cquizobj->has_capability('mod/cquiz:manage')) {
        redirect($cquizobj->edit_url());
    } else {
        print_error('cannotstartnoquestions', 'cquiz', $cquizobj->view_url());
    }
}

// Create an object to manage all the other (non-roles) access rules.
$timenow = time();
$accessmanager = $cquizobj->get_access_manager($timenow);

// Validate permissions for creating a new attempt and start a new preview attempt if required.
list($currentattemptid, $attemptnumber, $lastattempt, $messages, $page) = cquiz_validate_new_attempt($cquizobj, $accessmanager, $forcenew, $page, true);

// Check access.
if (!$cquizobj->is_preview_user() && $messages) {
    $output = $PAGE->get_renderer('mod_cquiz');
    print_error('attempterror', 'cquiz', $cquizobj->view_url(), $output->access_messages($messages));
}

if ($accessmanager->is_preflight_check_required($currentattemptid)) {
    // Need to do some checks before allowing the user to continue.
    $mform = $accessmanager->get_preflight_check_form(
            $cquizobj->start_attempt_url($page, $preview), $currentattemptid);

    if ($mform->is_cancelled()) {
        $accessmanager->back_to_view_page($PAGE->get_renderer('mod_cquiz'));
    } else if (!$mform->get_data()) {

        // Form not submitted successfully, re-display it and stop.
        $PAGE->set_url($cquizobj->start_attempt_url($page, $preview));
        $PAGE->set_title($cquizobj->get_cquiz_name());
        $accessmanager->setup_attempt_page($PAGE);
        $output = $PAGE->get_renderer('mod_cquiz');
        if (empty($cquizobj->get_cquiz()->showblocks)) {
            $PAGE->blocks->show_only_fake_blocks();
        }

        echo $output->start_attempt_page($cquizobj, $mform);
        die();
    }

    // Pre-flight check passed.
    $accessmanager->notify_preflight_check_passed($currentattemptid);
}
if ($currentattemptid) {
    if ($lastattempt->state == cquiz_attempt::OVERDUE) {
        redirect($cquizobj->summary_url($lastattempt->id));
    } else {
        cquiz_reset_attempt_timer($currentattemptid);
        redirect($cquizobj->attempt_url($currentattemptid, $page));
    }
}

$attempt = cquiz_prepare_and_start_new_attempt($cquizobj, $attemptnumber, $lastattempt, $preview);

// Redirect to the attempt page.
redirect($cquizobj->attempt_url($attempt->id, $page));

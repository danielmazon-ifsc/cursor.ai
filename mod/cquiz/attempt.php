<?php

/**
 * This script displays a particular page of a cquiz attempt that is in progress.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/cquiz/locallib.php');

$preview = optional_param('preview', false, PARAM_BOOL); // Start as a preview, not a real attempt
// Look for old-style URLs, such as may be in the logs, and redirect them to startattemtp.php.
if ($id = optional_param('id', 0, PARAM_INT)) {
    redirect($CFG->wwwroot . '/mod/cquiz/startattempt.php?cmid=' . $id . '&sesskey=' . sesskey() . '&preview=' . $preview);
} else if ($qid = optional_param('q', 0, PARAM_INT)) {
    if (!$cm = get_coursemodule_from_instance('cquiz', $qid)) {
        print_error('invalidcquizid', 'cquiz');
    }
    redirect(new moodle_url('/mod/cquiz/startattempt.php', array('cmid' => $cm->id, 'sesskey' => sesskey(), 'preview' => $preview)));
}

// Get submitted parameters.
$attemptid = required_param('attempt', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

$attemptobj = cquiz_attempt::create($attemptid);
$page = $attemptobj->force_page_number_into_range($page);
$PAGE->set_pagelayout('base');
$PAGE->set_url($attemptobj->attempt_url(null, $page));

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
    if ($attemptobj->has_capability('mod/cquiz:viewreports')) {
        redirect($attemptobj->review_url(null, $page));
    } else {
        throw new moodle_cquiz_exception($attemptobj->get_cquizobj(), 'notyourattempt');
    }
}

// Check capabilities and block settings.
if (!$attemptobj->is_preview_user()) {
    $attemptobj->require_capability('mod/cquiz:attempt');
    if (empty($attemptobj->get_cquiz()->showblocks)) {
        $PAGE->blocks->show_only_fake_blocks();
    }
} else {
    navigation_node::override_active_url($attemptobj->start_attempt_url(null, $page, $preview));
}

// If the attempt is already closed, send them to the review page.
if ($attemptobj->is_finished()) {
    redirect($attemptobj->review_url(null, $page));
} else if ($attemptobj->get_state() == cquiz_attempt::OVERDUE) {
    redirect($attemptobj->summary_url());
}

// Check the access rules.
$accessmanager = $attemptobj->get_access_manager(time());
$accessmanager->setup_attempt_page($PAGE);
$output = $PAGE->get_renderer('mod_cquiz');
$messages = $accessmanager->prevent_access();
if (!$attemptobj->is_preview_user() && $messages) {
    print_error('attempterror', 'cquiz', $attemptobj->view_url(), $output->access_messages($messages));
}
if ($accessmanager->is_preflight_check_required($attemptobj->get_attemptid())) {
    redirect($attemptobj->start_attempt_url(null, $page, $preview));
}

// Set up auto-save if required.
$autosaveperiod = get_config('cquiz', 'autosaveperiod');
if ($autosaveperiod) {
    $PAGE->requires->yui_module('moodle-mod_cquiz-autosave', 'M.mod_cquiz.autosave.init', array($autosaveperiod));
}

// Log this page view.
$attemptobj->fire_attempt_viewed_event();

// Get the list of questions needed by this page.
$slots = $attemptobj->get_slots($page);

// Check.
if (empty($slots)) {
    throw new moodle_cquiz_exception($attemptobj->get_cquizobj(), 'noquestionsfound');
}

// Update attempt page, redirecting the user if $page is not valid.
if (!$attemptobj->set_currentpage($page)) {
    redirect($attemptobj->start_attempt_url(null, $attemptobj->get_currentpage(), $preview));
}

// Initialise the JavaScript.
$PAGE->requires->js_init_call('M.mod_cquiz.init_attempt_form', null, false, cquiz_get_js_module());

// Arrange for the navigation to be displayed in the first region on the page.
$navbc = $attemptobj->get_navigation_panel($output, 'cquiz_attempt_nav_panel', $page);
$regions = $PAGE->blocks->get_regions();
//$PAGE->blocks->add_fake_block($navbc, reset($regions));

$title = get_string('attempt', 'cquiz', $attemptobj->get_attempt_number());
$headtags = $attemptobj->get_html_head_contributions($page);
$PAGE->set_title($attemptobj->get_cquiz_name());
$PAGE->set_heading($attemptobj->get_course()->fullname);

if ($attemptobj->is_last_page($page)) {
    $nextpage = -1;
} else {
    $nextpage = $page + 1;
}

echo $output->attempt_page($attemptobj, $page, $accessmanager, $messages, $slots, $id, $nextpage);

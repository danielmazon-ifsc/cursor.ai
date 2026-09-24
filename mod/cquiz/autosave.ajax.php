<?php

/**
 * Thisscript processes ajax auto-save requests during the cquiz.
 *
 * @package    mod_cquiz
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/cquiz/locallib.php');

// Remember the current time as the time any responses were submitted
// (so as to make sure students don't get penalized for slow processing on this page).
$timenow = time();
require_sesskey();

// Get submitted parameters.
$attemptid = required_param('attempt', PARAM_INT);
$thispage = optional_param('thispage', 0, PARAM_INT);

$transaction = $DB->start_delegated_transaction();
$attemptobj = cquiz_attempt::create($attemptid);

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

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

$attemptobj->process_auto_save($timenow);
$transaction->allow_commit();
echo 'OK';

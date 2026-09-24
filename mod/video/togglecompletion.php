<?php

/**
 * Toggles video activity completion for the current user.
 *
 * Course-level self-completion and role-based completion belong in
 * /course/togglecompletion.php only. This endpoint accepts video modules
 * (id=cmid) so AJAX completion from the player cannot be repointed at
 * other activity types or at another user's course completion.
 *
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require_once("../../config.php");
require_once($CFG->libdir . '/completionlib.php');
require_once('locallib.php');

$cmid = required_param('id', PARAM_INT);
$targetstate = required_param('completionstate', PARAM_INT);
$fromajax = optional_param('fromajax', 0, PARAM_INT);

$PAGE->set_url('/mod/video/togglecompletion.php', array('id' => $cmid, 'completionstate' => $targetstate));

switch ($targetstate) {
    case COMPLETION_COMPLETE:
    case COMPLETION_INCOMPLETE:
        break;
    default:
        print_error('unsupportedstate');
}

// Get course-modules entry with up-to-date completion settings.
$cm = get_coursemodule_from_id(null, $cmid, null, true, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$modinfo = get_fast_modinfo($course);
$cm = $modinfo->get_cm($cmid);

require_login($course, false, $cm);

if (isguestuser() or !confirm_sesskey()) {
    print_error('error');
}

if ($cm->modname !== 'video') {
    throw new moodle_exception('invalidcoursemodule');
}

$completion = new completion_info($course);
if (!$completion->is_enabled($cm)) {
    throw new moodle_exception('completionnotenabled', 'completion');
}

$modcontext = context_module::instance($cmid);
if ($cm->completion == COMPLETION_TRACKING_MANUAL) {
    require_capability('moodle/course:togglecompletion', $modcontext);
}

if ($targetstate == COMPLETION_COMPLETE) {
    if (!video_set_completed($course, $cm)) {
        error_or_ajax('error', $fromajax);
    }
} else if ($cm->completion == COMPLETION_TRACKING_MANUAL) {
    $completion->update_state($cm, COMPLETION_INCOMPLETE);
}

if ($fromajax) {
    print 'OK';
} else {
    if ($backto = optional_param('backto', null, PARAM_LOCALURL)) {
        $backto = clean_param($backto, PARAM_LOCALURL);
        if ($backto !== '' && validate_local_url($backto)) {
            redirect(new moodle_url($backto));
        }
    } else {
        redirect(course_get_url($course, $cm->sectionnum));
    }
}

/**
 * @param string $message
 * @param int $fromajax
 */
function error_or_ajax($message, $fromajax) {
    if ($fromajax) {
        print get_string($message, 'error');
        exit;
    }
    print_error($message);
}

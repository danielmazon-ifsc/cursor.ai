<?php

/**
 * This page handles deleting cquiz overrides
 *
 * @package    mod_cquiz
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/cquiz/lib.php');
require_once($CFG->dirroot . '/mod/cquiz/locallib.php');
require_once($CFG->dirroot . '/mod/cquiz/override_form.php');

$overrideid = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', false, PARAM_BOOL);

if (!$override = $DB->get_record('cquiz_overrides', array('id' => $overrideid))) {
    print_error('invalidoverrideid', 'cquiz');
}
if (!$cquiz = $DB->get_record('cquiz', array('id' => $override->cquiz))) {
    print_error('invalidcoursemodule');
}
if (!$cm = get_coursemodule_from_instance("cquiz", $cquiz->id, $cquiz->course)) {
    print_error('invalidcoursemodule');
}
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

$context = context_module::instance($cm->id);

require_login($course, false, $cm);

// Check the user has the required capabilities to modify an override.
require_capability('mod/cquiz:manageoverrides', $context);

$url = new moodle_url('/mod/cquiz/overridedelete.php', array('id' => $override->id));
$confirmurl = new moodle_url($url, array('id' => $override->id, 'confirm' => 1));
$cancelurl = new moodle_url('/mod/cquiz/overrides.php', array('cmid' => $cm->id));

if (!empty($override->userid)) {
    $cancelurl->param('mode', 'user');
}

// If confirm is set (PARAM_BOOL) then we have confirmation of intention to delete.
if ($confirm) {
    require_sesskey();

    // Set the course module id before calling cquiz_delete_override().
    $cquiz->cmid = $cm->id;
    cquiz_delete_override($cquiz, $override->id);

    redirect($cancelurl);
}

// Prepare the page to show the confirmation form.
$stroverride = get_string('override', 'cquiz');
$title = get_string('deletecheck', null, $stroverride);

$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->navbar->add($title);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($cquiz->name, true, array('context' => $context)));

if ($override->groupid) {
    $group = $DB->get_record('groups', array('id' => $override->groupid), 'id, name');
    $confirmstr = get_string("overridedeletegroupsure", "cquiz", $group->name);
} else {
    $namefields = get_all_user_name_fields(true);
    $user = $DB->get_record('user', array('id' => $override->userid), 'id, ' . $namefields);
    $confirmstr = get_string("overridedeleteusersure", "cquiz", fullname($user));
}

echo $OUTPUT->confirm($confirmstr, $confirmurl, $cancelurl);

echo $OUTPUT->footer();

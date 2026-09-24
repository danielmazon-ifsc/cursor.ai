<?php

/**
 * Allows you to edit a users profile
 *
 * @package   local_profile
 * @copyright 2024 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require_once('../../config.php');
require_once('./editlib.php');
require_once('./edit_form.php');
require_once($CFG->dirroot . '/user/lib.php');

$userid = optional_param('id', $USER->id, PARAM_INT);    // User id.
// Use PARAM_LOCALURL so an external "returnto" can't open-redirect after save.
$returnto = optional_param('returnto', '', PARAM_LOCALURL);
$cancelemailchange = optional_param('cancelemailchange', 0, PARAM_BOOL);

require_login();

// The user profile we are editing.
if (!$user = $DB->get_record('user', array('id' => $userid))) {
    throw new moodle_exception('invaliduserid');
}

$PAGE->set_url($CFG->wwwroot . '/local/profile/edit.php', array(
    'id' => $user->id
));

$systemcontext = context_system::instance();
$personalcontext = context_user::instance($user->id);

// Make sure the current user is actually allowed to edit THIS profile. Editing
// own profile requires moodle/user:editownprofile (system) and editing someone
// else requires moodle/user:editprofile on the target user context. Guests and
// admins-of-admins are blocked the same way the core profile editor does it.
if (isguestuser($user)) {
    throw new moodle_exception('guestnoeditprofile');
}
if ($user->id == $USER->id) {
    require_capability('moodle/user:editownprofile', $systemcontext);
} else {
    require_capability('moodle/user:editprofile', $personalcontext);
    if (is_siteadmin($user) && !is_siteadmin($USER)) {
        throw new moodle_exception('useradmineditadmin');
    }
}

if ($user->deleted) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('userdeleted'));
    echo $OUTPUT->footer();
    die;
}

// Handle the "Cancel email change" link from the form (editlib.php builds it).
// We require sesskey to stop a cross-site forced cancel.
if ($cancelemailchange) {
    require_sesskey();
    cancel_email_update($user->id);
    redirect(new moodle_url('/local/profile/edit.php', array('id' => $user->id)),
            get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_pagelayout('admin');
$PAGE->set_context($personalcontext);

// Prepare the editor and create form.
$editoroptions = array(
    'maxfiles' => EDITOR_UNLIMITED_FILES,
    'maxbytes' => $CFG->maxbytes,
    'trusttext' => false,
    'forcehttps' => false,
    'context' => $personalcontext
);

$user = file_prepare_standard_editor($user, 'description', $editoroptions, $personalcontext, 'user', 'profile', 0);
// Prepare filemanager draft area.
$draftitemid = 0;
$filemanagercontext = $editoroptions['context'];
$filemanageroptions = array('maxbytes' => $CFG->maxbytes,
    'subdirs' => 0,
    'maxfiles' => 1,
    'accepted_types' => 'web_image');
file_prepare_draft_area($draftitemid, $filemanagercontext->id, 'user', 'newicon', 0, $filemanageroptions);
$user->imagefile = $draftitemid;
// Create form.
$userform = new user_edit_form(new moodle_url($PAGE->url, array('returnto' => $returnto)), array(
    'editoroptions' => $editoroptions,
    'filemanageroptions' => $filemanageroptions,
    'user' => $user));

$redirecturl = $returnto !== '' ? new moodle_url($returnto) : new moodle_url('/');
$emailchanged = false;
if ($userform->is_cancelled()) {
    redirect($redirecturl);
} else if ($usernew = $userform->get_data()) {
    $usernew->timemodified = time();
    if (isset($usernew->description_editor) && isset($usernew->description_editor['format'])) {
        $usernew = file_postupdate_standard_editor($usernew, 'description', $editoroptions, $personalcontext, 'user', 'profile', 0);
    }
    if (!empty($usernew->password)) {
        // Mirror the AJAX path (updateprofile.php): the form already enforces
        // length + repeat, but Moodle's site-wide $CFG->passwordpolicy can
        // demand more (digits, symbols, dictionary, etc.). Without this check
        // an admin or self-edit can save a password that violates policy.
        $errmsg = '';
        if (!check_password_policy($usernew->password, $errmsg, $user)) {
            redirect($PAGE->url, strip_tags($errmsg), null, \core\output\notification::NOTIFY_ERROR);
        }
        user_update_user($usernew, true, false);
    } else {
        $user = get_complete_user_data('id', $user->id);
        $usernew->password = $user->password;
        user_update_user($usernew, false, false);
    }
    if (empty($CFG->disableuserimages)) {
        core_user::update_picture($usernew, $filemanageroptions);
    }
    \core\event\user_updated::create_from_userid($user->id)->trigger();
    // Re-fetch the user fresh from DB so the in-memory $USER reflects the
    // values we just persisted (the old loop copied stale data).
    if ($user->id == $USER->id) {
        $freshuser = get_complete_user_data('id', $user->id);
        if ($freshuser) {
            foreach ((array) $freshuser as $variable => $value) {
                $USER->$variable = $value;
            }
        }
    }
    redirect($redirecturl);
}

$streditmyprofile = get_string('editprofile', 'local_profile');
$userfullname = fullname($user, true);
$PAGE->set_title($streditmyprofile);
$PAGE->set_heading($userfullname);

echo $OUTPUT->header();
echo local_profile_print_edit_header();
if ($CFG->theme != 'pdi') {
    echo $OUTPUT->heading($userfullname);
}
echo html_writer::start_div('narrow-page pb-6', array(
    'id' => 'editform'
));
if ($CFG->theme == 'pdi') {
    echo local_profile_print_edit_subheader();
}
echo html_writer::start_div('row py-32pt');
if ($CFG->theme == 'pdi') {
    echo html_writer::start_div('col-md-6', array(
        'id' => 'timedata'
    ));
    echo local_profile_print_time_selector();
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('col-md-6', array(
        'id' => 'userdata'
    ));
    $userform->display();
    echo html_writer::end_div();
}
echo html_writer::start_div('col-md-6', array(
    'id' => 'useravatar'
));
if ($CFG->theme == 'pdi') {
    echo local_profile_print_avatar_chooser();
} else {
    echo local_profile_print_avatar_selector($user, $returnto);
}
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

$sesskey = sesskey();
echo html_writer::empty_tag('input', array(
    'type' => 'hidden',
    'id' => 'sesskey',
    'value' => $sesskey
));
echo html_writer::empty_tag('input', array(
    'type' => 'hidden',
    'id' => 'returnto',
    'value' => $returnto
));

$PAGE->requires->strings_for_js(['profileupdated', 'profileupdatefailed', 'nothingtoupdate'], 'local_profile');
$PAGE->requires->js('/local/profile/formcontrol.js');

echo $OUTPUT->footer();

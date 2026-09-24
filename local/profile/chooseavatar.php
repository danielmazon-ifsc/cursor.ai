<?php

/**
 * Allows you to choose an avatar for users profile
 *
 * @package   local_profile
 * @copyright 2024 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require_once('../../config.php');
require_once('./editlib.php');
require_once($CFG->dirroot . '/user/lib.php');

$userid   = optional_param('id', $USER->id, PARAM_INT);
$returnto = optional_param('returnto', '', PARAM_LOCALURL);
$avatarid = optional_param('avatar', 'avatar-00', PARAM_ALPHANUM);
$return   = optional_param('return', 0, PARAM_INT);

require_login();

$PAGE->set_url('/local/profile/chooseavatar.php', array(
    'id' => $userid
));

if (!$user = $DB->get_record('user', array('id' => $userid))) {
    throw new moodle_exception('invaliduserid');
}
if (isguestuser($user)) {
    throw new moodle_exception('guestnoeditprofile');
}

$systemcontext = context_system::instance();
$personalcontext = context_user::instance($user->id);

if ($user->id == $USER->id) {
    require_capability('moodle/user:editownprofile', $systemcontext);
} else {
    require_capability('moodle/user:editprofile', $personalcontext);
    if (isguestuser($user->id)) {
        throw new moodle_exception('guestnoeditprofileother');
    }
    if (is_siteadmin($user) && !is_siteadmin($USER)) {
        throw new moodle_exception('useradmineditadmin');
    }
}

$urloptions = array();
if ($returnto !== '') {
    $urloptions['returnto'] = $returnto;
}
$returntoediturl = new moodle_url('/local/profile/edit.php', $urloptions);

if ($return != 0) {
    redirect($returntoediturl);
} else if ($avatarid != 'avatar-00') {
    // Mutating action: require sesskey and validate the avatar id against the
    // bundled file list before letting it reach the filesystem.
    require_sesskey();

    $avatarfile = $CFG->dirroot . '/local/profile/pix/avatars/' . $avatarid . '.png';
    $realavatar = realpath($avatarfile);
    $expectedbase = realpath($CFG->dirroot . '/local/profile/pix/avatars');
    if ($realavatar
        && $expectedbase
        && strpos($realavatar, $expectedbase) === 0
        && is_file($realavatar)
    ) {
        // Pass the target $user->id explicitly: the helper otherwise writes
        // the picture to the editor's own account, which is wrong when an
        // admin is editing someone else's profile.
        local_profile_set_avatar_by_id($avatarid, $user->id);
    }
    redirect($returntoediturl);
}

$PAGE->set_pagelayout('admin');
$PAGE->set_context($personalcontext);

$streditmyprofile = get_string('chooseyouravatar', 'local_profile');
$userfullname = fullname($user, true);
$PAGE->set_title($streditmyprofile);
$PAGE->set_heading($userfullname);

echo local_profile_print_chooseavatar_selector_css();
echo $OUTPUT->header();
echo local_profile_print_chooseavatar_header();
echo local_profile_print_chooseavatar_form($user, $returnto);
echo $OUTPUT->footer();
echo local_profile_print_chooseavatar_selector_js();

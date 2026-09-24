<?php

/**
 * This file contains function used when editing a users profile and preferences.
 *
 * @package   local_profile
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/profile/lib.php');

/**
 * Cancels the requirement for a user to update their email address.
 *
 * @param int $userid
 */
function cancel_email_update($userid) {
    unset_user_preference('newemail', $userid);
    unset_user_preference('newemailkey', $userid);
    unset_user_preference('newemailattemptsleft', $userid);
}

/**
 * Performs the common access checks and page setup for all
 * user preference pages.
 *
 * @param int $userid The user id to edit taken from the page params.
 * @param int $courseid The optional course id if we came from a course context.
 * @return array containing the user and course records.
 */
function useredit_setup_preference_page($userid, $courseid) {
    global $PAGE, $SESSION, $DB, $CFG, $OUTPUT, $USER;

    // Guest can not edit. print_error() is deprecated since Moodle 3.x; use
    // moodle_exception which is the documented replacement.
    if (isguestuser()) {
        throw new moodle_exception('guestnoeditprofile');
    }

    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        throw new moodle_exception('invalidcourseid');
    }

    if ($course->id != SITEID) {
        require_login($course);
    } else if (!isloggedin()) {
        if (empty($SESSION->wantsurl)) {
            $SESSION->wantsurl = $CFG->wwwroot . '/user/preferences.php';
        }
        redirect(get_login_url());
    } else {
        $PAGE->set_context(context_system::instance());
    }

    // The user profile we are editing.
    if (!$user = $DB->get_record('user', array('id' => $userid))) {
        throw new moodle_exception('invaliduserid');
    }

    // Guest can not be edited.
    if (isguestuser($user)) {
        throw new moodle_exception('guestnoeditprofile');
    }

    // Remote users cannot be edited.
    if (is_mnet_remote_user($user)) {
        if (user_not_fully_set_up($user, false)) {
            $hostwwwroot = $DB->get_field('mnet_host', 'wwwroot', array('id' => $user->mnethostid));
            throw new moodle_exception('usernotfullysetup', 'mnet', '', $hostwwwroot);
        }
        redirect($CFG->wwwroot . "/user/view.php?course={$course->id}");
    }

    $systemcontext = context_system::instance();
    $personalcontext = context_user::instance($user->id);

    // Check access control.
    if ($user->id == $USER->id) {
        // Editing own profile - require_login() MUST NOT be used here, it would result in infinite loop!
        if (!has_capability('moodle/user:editownprofile', $systemcontext)) {
            throw new moodle_exception('cannotedityourprofile');
        }
    } else {
        // Teachers, parents, etc.
        require_capability('moodle/user:editprofile', $personalcontext);

        // No editing of primary admin!
        if (is_siteadmin($user) and ! is_siteadmin($USER)) {  // Only admins may edit other admins.
            throw new moodle_exception('useradmineditadmin');
        }
    }

    if ($user->deleted) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('userdeleted'));
        echo $OUTPUT->footer();
        die;
    }

    $PAGE->set_pagelayout('admin');
    $PAGE->set_context($personalcontext);
    if ($USER->id != $user->id) {
        $PAGE->navigation->extend_for_user($user);
    } else {
        if ($node = $PAGE->navigation->find('myprofile', navigation_node::TYPE_ROOTNODE)) {
            $node->force_open();
        }
    }

    return array($user, $course);
}

/**
 * Loads the given users preferences into the given user object.
 *
 * @param stdClass $user The user object, modified by reference.
 * @param bool $reload
 */
function useredit_load_preferences(&$user, $reload = true) {
    global $USER;

    if (!empty($user->id)) {
        if ($reload and $USER->id == $user->id) {
            // Reload preferences in case it was changed in other session.
            unset($USER->preference);
        }

        if ($preferences = get_user_preferences(null, null, $user->id)) {
            foreach ($preferences as $name => $value) {
                $user->{'preference_' . $name} = $value;
            }
        }
    }
}

/**
 * Updates the user preferences for the given user
 *
 * Only preference that can be updated directly will be updated here. This method is called from various WS
 * updating users and should be used when updating user details. Plugins may whitelist preferences that can
 * be updated by defining 'user_preferences' callback, {@see core_user::fill_preferences_cache()}
 *
 * Some parts of code may use user preference table to store internal data, in these cases it is acceptable
 * to call set_user_preference()
 *
 * @param stdClass|array $usernew object or array that has user preferences as attributes with keys starting with preference_
 */
function useredit_update_user_preference($usernew) {
    global $USER;
    $ua = (array) $usernew;
    if (is_object($usernew) && isset($usernew->id) && isset($usernew->deleted) && isset($usernew->confirmed)) {
        // This is already a full user object, maybe not completely full but these fields are enough.
        $user = $usernew;
    } else if (empty($ua['id']) || $ua['id'] == $USER->id) {
        // We are updating current user.
        $user = $USER;
    } else {
        // Retrieve user object.
        $user = core_user::get_user($ua['id'], '*', MUST_EXIST);
    }

    foreach ($ua as $key => $value) {
        if (strpos($key, 'preference_') === 0) {
            $name = substr($key, strlen('preference_'));
            if (core_user::can_edit_preference($name, $user)) {
                $value = core_user::clean_preference($value, $name);
                set_user_preference($name, $value, $user->id);
            }
        }
    }
}

/**
 * Updates the provided users profile picture based upon the expected fields returned from the edit or edit_advanced forms.
 *
 * @deprecated since Moodle 3.2 MDL-51789 - please use core_user::update_picture() instead.
 * @todo MDL-54858 This will be deleted in Moodle 3.6.
 * @see core_user::update_picture()
 *
 * @global moodle_database $DB
 * @param stdClass $usernew An object that contains some information about the user being updated
 * @param moodleform $userform The form that was submitted to edit the form (unused)
 * @param array $filemanageroptions
 * @return bool True if the user was updated, false if it stayed the same.
 */
function useredit_update_picture(stdClass $usernew, moodleform $userform, $filemanageroptions = array()) {
    debugging('useredit_update_picture() is deprecated. Please use core_user::update_picture() instead.', DEBUG_DEVELOPER);
    return core_user::update_picture($usernew, $filemanageroptions);
}

/**
 * Updates the user email bounce + send counts when the user is edited.
 *
 * @param stdClass $user The current user object.
 * @param stdClass $usernew The updated user object.
 */
function useredit_update_bounces($user, $usernew) {
    if (!isset($usernew->email)) {
        // Locked field.
        return;
    }
    if (!isset($user->email) || $user->email !== $usernew->email) {
        set_bounce_count($usernew, true);
        set_send_count($usernew, true);
    }
}

/**
 * Updates the forums a user is tracking when the user is edited.
 *
 * @param stdClass $user The original user object.
 * @param stdClass $usernew The updated user object.
 */
function useredit_update_trackforums($user, $usernew) {
    global $CFG;
    if (!isset($usernew->trackforums)) {
        // Locked field.
        return;
    }
    if ((!isset($user->trackforums) || ($usernew->trackforums != $user->trackforums)) and ! $usernew->trackforums) {
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        forum_tp_delete_read_records($usernew->id);
    }
}

/**
 * Updates a users interests.
 *
 * @param stdClass $user
 * @param array $interests
 */
function useredit_update_interests($user, $interests) {
    core_tag_tag::set_item_tags('core', 'user', $user->id, context_user::instance($user->id), $interests);
}

/**
 * Powerful function that is used by edit and editadvanced to add common form elements/rules/etc.
 *
 * @param moodleform $mform
 * @param array $editoroptions
 * @param array $filemanageroptions
 * @param stdClass $user
 */
function useredit_shared_definition(&$mform, $editoroptions, $filemanageroptions, $user) {
    global $CFG;

    if ($user->id > 0) {
        useredit_load_preferences($user, false);
    }

    $strrequired = get_string('required');
    $stringman = get_string_manager();

    // Add the necessary names.
    foreach (useredit_get_required_name_fields() as $fullname) {
        $mform->addElement('text', $fullname, get_string($fullname), 'maxlength="100" size="23"');
        if ($stringman->string_exists('missing' . $fullname, 'core')) {
            $strmissingfield = get_string('missing' . $fullname, 'core');
        } else {
            $strmissingfield = $strrequired;
        }
        $mform->addRule($fullname, $strmissingfield, 'required', null, 'client');
        $mform->setType($fullname, PARAM_NOTAGS);
    }

    $enabledusernamefields = useredit_get_enabled_name_fields();
    // Add the enabled additional name fields.
    foreach ($enabledusernamefields as $addname) {
        $mform->addElement('text', $addname, get_string($addname), 'maxlength="100" size="30"');
        $mform->setType($addname, PARAM_NOTAGS);
    }

    // Do not show email field if change confirmation is pending.
    if ($user->id > 0 and ! empty($CFG->emailchangeconfirmation) and ! empty($user->preference_newemail)) {
        $notice = get_string('emailchangepending', 'auth', $user);
        // sesskey is required by edit.php's cancelemailchange handler so the
        // link can't be triggered cross-site.
        $cancelurl = new moodle_url('/local/profile/edit.php', array(
            'id' => $user->id,
            'cancelemailchange' => 1,
            'sesskey' => sesskey(),
        ));
        $notice .= '<br /><a href="' . $cancelurl->out(false) . '">'
                . get_string('emailchangecancel', 'auth') . '</a>';
        $mform->addElement('static', 'emailpending', get_string('email', 'local_profile'), $notice);
    } else {
        $mform->addElement('text', 'email', get_string('email', 'local_profile'), 'maxlength="100" size="52"');
        $mform->addRule('email', $strrequired, 'required', null, 'client');
        $mform->setType('email', PARAM_RAW_TRIMMED);
    }

    $mform->addElement('text', 'password', get_string('newpassword', 'local_profile'), 'maxlength="50" size="23" style="-webkit-text-security: circle;"');
    $mform->setType('password', PARAM_RAW);

    $mform->addElement('text', 'repeatpassword', get_string('repeatpassword', 'local_profile'), 'maxlength="50" size="23" style="-webkit-text-security: circle;"');
    $mform->setType('repeatpassword', PARAM_RAW);

    $mform->addElement('text', 'city', get_string('city', 'local_profile'), 'maxlength="120" size="23"');
    $mform->setType('city', PARAM_TEXT);
    if (!empty($CFG->defaultcity)) {
        $mform->setDefault('city', $CFG->defaultcity);
    }

    $choices = get_string_manager()->get_list_of_countries();
    $choices = array('' => get_string('selectacountry') . '...') + $choices;
    $mform->addElement('select', 'country', get_string('country', 'local_profile'), $choices, array('style' => 'width: 230px;'));
    if (!empty($CFG->country)) {
        $mform->setDefault('country', core_user::get_property_default('country'));
    }

    $mform->addElement('editor', 'description_editor', get_string('userdescription'), array('rows' => 7), $editoroptions);
    $mform->setType('description_editor', PARAM_CLEANHTML);
}

/**
 * Return required user name fields for forms.
 *
 * @return array required user name fields in order according to settings.
 */
function useredit_get_required_name_fields() {
    global $CFG;

    // Get the name display format.
    $nameformat = $CFG->fullnamedisplay;

    // Names that are required fields on user forms.
    $necessarynames = array('firstname', 'lastname');
    $languageformat = get_string('fullnamedisplay');

    // Check that the language string and the $nameformat contain the necessary names.
    foreach ($necessarynames as $necessaryname) {
        $pattern = "/$necessaryname\b/";
        if (!preg_match($pattern, $languageformat)) {
            // If the language string has been altered then fall back on the below order.
            $languageformat = 'firstname lastname';
        }
        if (!preg_match($pattern, $nameformat)) {
            // If the nameformat doesn't contain the necessary name fields then use the languageformat.
            $nameformat = $languageformat;
        }
    }

    // Order all of the name fields in the postion they are written in the fullnamedisplay setting.
    $necessarynames = order_in_string($necessarynames, $nameformat);
    return $necessarynames;
}

/**
 * Gets enabled (from fullnameformate setting) user name fields in appropriate order.
 *
 * @return array Enabled user name fields.
 */
function useredit_get_enabled_name_fields() {
    global $CFG;

    // Get all of the other name fields which are not ranked as necessary.
    $additionalusernamefields = array_diff(get_all_user_name_fields(), array('firstname', 'lastname'));
    // Find out which additional name fields are actually being used from the fullnamedisplay setting.
    $enabledadditionalusernames = array();
    foreach ($additionalusernamefields as $enabledname) {
        if (strpos($CFG->fullnamedisplay, $enabledname) !== false) {
            $enabledadditionalusernames[] = $enabledname;
        }
    }

    // Order all of the name fields in the postion they are written in the fullnamedisplay setting.
    $enabledadditionalusernames = order_in_string($enabledadditionalusernames, $CFG->fullnamedisplay);
    return $enabledadditionalusernames;
}

/**
 * Gets user name fields not enabled from the setting fullnamedisplay.
 *
 * @param array $enabledadditionalusernames Current enabled additional user name fields.
 * @return array Disabled user name fields.
 */
function useredit_get_disabled_name_fields($enabledadditionalusernames = null) {
    // If we don't have enabled additional user name information then go and fetch it (try to avoid).
    if (!isset($enabledadditionalusernames)) {
        $enabledadditionalusernames = useredit_get_enabled_name_fields();
    }

    // These are the additional fields that are not currently enabled.
    $nonusednamefields = array_diff(get_all_user_name_fields(), array_merge(array('firstname', 'lastname'), $enabledadditionalusernames));
    return $nonusednamefields;
}

function local_profile_print_edit_header() {
    global $CFG;

    $html = '';
    if ($CFG->theme == 'pdi') {
        $html .= '<!-- Banner BEGIN -->';
        $html .= html_writer::start_div('d-flex page-section bg-primary800 border-bottom-2', array(
                    'style' => 'height: 220px; background-color: #052B2D !important; border: none !important; color: rgb(245, 254, 255); padding-bottom: 0;'
        ));
        $html .= html_writer::start_div('d-flex narrow-page container page__container h-100', array(
                    'style' => 'flex-flow: row; justify-content: space-between;'
        ));
        $html .= html_writer::start_div('d-flex flex-md-row align-items-center ');
        $html .= html_writer::start_div('avatar avatar-xl rounded-circle bg-primary400 avatar-title mb-16pt mb-md-0 mr-md-16pt', array(
                    'style' => 'background-color: rgb(55, 182, 181); width: 5.125rem; height: 5.125rem; margin-right: 1rem;'
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-gear fa-fw navicon mr-1',
                    'style' => 'font-size: 48px; margin-left: 1.3rem; margin-top: 1rem;'
        ));
        $html .= html_writer::end_tag('i');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('mr-auto w-75');
        $html .= html_writer::start_tag('h1', array(
                    'class' => 'p-0 mb-16pt pt-3 text-primary100',
                    'style' => 'color: white;'
        ));
        $html .= get_string('configurations', 'local_profile');
        $html .= html_writer::end_tag('h1');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('d-flex');
        $html .= html_writer::start_div('d-flex flex-column flex-sm-row align-items-center justify-content-start');
        $html .= html_writer::start_tag('button', array(
                    'onclick' => 'updateProfile();',
                    'class' => 'btn btn-outline-white btn-rounded mb-16pt mb-sm-0 mr-sm-16pt'
        ));
        $html .= get_string('savechanges', 'local_profile');
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-save fa-fw navicon mr-1',
                    'style' => 'margin-left: .5rem; margin-right: 0 !important;'
        ));
        $html .= html_writer::end_tag('i');
        $html .= html_writer::end_tag('button');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Banner END -->';
    } else {
        $html .= html_writer::start_div('mdk-box mb-0', array(
                    'id' => 'banner',
                    'data-effects' => 'parallax-background blend-background',
                    'data-domfactory-upgraded' => 'mdk-box',
                    'style' => 'padding-top: 4rem; height: 232px;'
        ));
        $html .= html_writer::start_div('mdk-box__bg', array(
                    'style' => 'visibility: visible;'
        ));
        $html .= html_writer::start_div('mdk-box__bg-front', array(
                    'style' => 'background-image: url(' . $CFG->wwwroot . '/local/profile/pix/header_background.png); transform: translate3d(0px, 0px, 0px); will-change: opacity; opacity: 1; margin-top: -31.2624px;'
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('mdk-box__bg-rear', array(
                    'style' => 'transform: translate3d(0px, 0px, 0px); will-change: opacity; opacity: 0; margin-top: -31.2624px;'
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('narrow-page mdk-box__content justify-content-center');
        $html .= html_writer::start_div('hero container page__container text-center');
        $html .= html_writer::start_div('page-section');
        $html .= html_writer::start_div('container page__container d-flex flex-column flex-md-row align-items-center text-center text-md-left');
        $html .= html_writer::tag('img', '', array(
                    'src' => $CFG->wwwroot . '/local/profile/pix/edit_icon.png',
                    'width' => '104',
                    'class' => 'mr-md-32pt mb-32pt mb-md-0',
                    'alt' => 'profile'
        ));
        $html .= html_writer::start_div('flex mb-32pt mb-md-0');
        $html .= html_writer::tag('h2', get_string('editprofile', 'local_profile'), array(
                    'class' => 'text-white mb-0'
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
    }
    return $html;
}

function local_profile_print_avatar_selector($user, $returnto) {
    global $PAGE;

    $html = '';
    $html .= html_writer::start_div('card card-group-row__card p-1 pb-4 mr-3 ml-3 bg-roxo-espacial-30');
    $html .= html_writer::start_div('card-header d-flex align-items-center border-0');
    $html .= html_writer::start_div('mb-0 mr-3');
    $html .= html_writer::tag('i', 'face', array(
                'class' => 'material-icons text-muted ml-2'
    ));
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('flex');
    $html .= html_writer::tag('small', get_string('profilepicture', 'local_profile'), array(
                'class' => 'strong text-uppercase text-70 form-label'
    ));
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('card-body flex text-center d-flex flex-column align-items-center justify-content-center py-32pt');
    $urloptions = array();
    $urloptions['id'] = $user->id;
    if ($returnto) {
        $urloptions['returnto'] = $returnto;
    }
    $chooseavatarurl = new moodle_url('/local/profile/chooseavatar.php', $urloptions);
    $html .= html_writer::start_tag('a', array(
                'href' => $chooseavatarurl,
                'class' => 'avatar avatar-xxl overlay rounded-circle p-relative o-hidden mb-16pt',
                'data-trigger' => 'hover',
                'data-domfactory-upgraded' => 'overlay'
    ));
    $userpicture = new user_picture($user);
    $userpicture->size = 140;
    $html .= html_writer::tag('img', '', array(
                'src' => $userpicture->get_url($PAGE),
                'alt' => fullname($user),
                'class' => 'avatar-img',
                'width' => '140px'
    ));
    $html .= html_writer::start_tag('span', array(
                'class' => 'overlay__content'
    ));
    $html .= html_writer::tag('i', 'edit', array(
                'class' => 'overlay__action material-icons icon-40pt'
    ));
    $html .= html_writer::end_tag('span');
    $html .= html_writer::end_tag('a');
    $html .= html_writer::start_div('flex');
    $html .= html_writer::tag('a', get_string('chooseotheravatar', 'local_profile'), array(
                'href' => $chooseavatarurl,
                'class' => 'chip chip-primary mt-3'
    ));
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

function local_profile_print_chooseavatar_header() {
    global $CFG;

    $html = '';
    $html .= html_writer::start_div('mdk-box mb-0', array(
                'data-effects' => 'parallax-background blend-background',
                'data-domfactory-upgraded' => 'mdk-box'
    ));
    $html .= html_writer::start_div('mdk-box__bg', array(
                'style' => 'visibility: visible;'
    ));
    $html .= html_writer::start_div('mdk-box__bg-front', array(
                'style' => 'background-image: url(' . $CFG->wwwroot . '/local/profile/pix/header_background.png); transform: translate3d(0px, 0px, 0px); will-change: opacity; opacity: 1; margin-top: -3.14019px;'
    ));
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('mdk-box__bg-rear', array(
                'style' => 'transform: translate3d(0px, 0px, 0px); will-change: opacity; opacity: 0; margin-top: -3.14019px;'
    ));
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('narrow-page mdk-box__content justify-content-center', array(
                'style' => 'height: auto;'
    ));
    $html .= html_writer::start_div('hero container page__container text-center');
    $html .= html_writer::start_div('page-section', array(
                'style' => 'padding-top: 2rem !important;'
    ));
    $html .= html_writer::start_div('container page__container d-flex flex-column flex-md-row align-items-center text-center text-md-left');
    $html .= html_writer::tag('img', '', array(
                'src' => $CFG->wwwroot . '/local/profile/pix/chooseavatar_icon.png',
                'width' => '104',
                'class' => 'mr-md-32pt mb-32pt mb-md-0',
                'alt' => 'profile'
    ));
    $html .= html_writer::start_div('flex mb-32pt mb-md-0');
    $html .= html_writer::tag('h2', get_string('chooseyouravatar', 'local_profile'), array(
                'class' => 'text-white mb-0'
    ));
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

function local_profile_print_avatar($avatarid, $user, $returnto) {
    global $CFG;

    $html = '';
    $urloptions = array();
    $urloptions['avatar']  = 'avatar' . $avatarid;
    $urloptions['id']      = $user->id;
    $urloptions['sesskey'] = sesskey();
    if ($returnto) {
        $urloptions['returnto'] = $returnto;
    }
    $chooseavatarurl = new moodle_url('', $urloptions);
    $html .= html_writer::start_tag('a', array(
                'class' => 'avatar-item overlay js-overlay',
                'style' => 'background-image: url(' . $CFG->wwwroot . '/local/profile/pix/avatars/avatar' . $avatarid . '.png);',
                'href' => $chooseavatarurl,
    ));
    $html .= html_writer::end_tag('a');
    return $html;
}

function local_profile_print_chooseavatar_form($user, $returnto) {
    $html = '';
    $html .= html_writer::start_div('choose-avatar bg-secondary-purple3');
    $html .= html_writer::start_div('avatars-container');
    $html .= html_writer::tag('span', '', array(
                'class' => 'left'
    ));
    $html .= html_writer::start_div('avatars', array(
                'style' => 'transform: translateX(-480px);'
    ));
    for ($i = 1; $i <= 30; ++$i) {
        $html .= local_profile_print_avatar(sprintf('%02d', $i), $user, $returnto);
    }
    $html .= '';
    $html .= html_writer::end_div();
    $html .= html_writer::tag('span', '', array(
                'class' => 'right'
    ));
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('');
    $urloptions = array();
    $urloptions['return'] = 1;
    if ($returnto) {
        $urloptions['returnto'] = $returnto;
    }
    $chooseavatarurl = new moodle_url('', $urloptions);
    $html .= html_writer::tag('a', get_string('back', 'local_profile'), array(
                'href' => $chooseavatarurl,
                'class' => 'btn btn-outline-secondary ml-3'
    ));
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

function local_profile_print_chooseavatar_selector_js() {
    global $PAGE;
    $PAGE->requires->js('/local/profile/js/select-avatar.js');
    return '';
}

function local_profile_print_chooseavatar_selector_css() {
    global $PAGE;
    $PAGE->requires->css('/local/profile/css/select-avatar.css');
    return '';
}

function local_profile_set_avatar_by_image_file($imagefile, $targetuserid = null) {
    global $CFG, $USER, $DB;
    require_once($CFG->libdir . '/gdlib.php');

    // Target user defaults to $USER for backwards compat, but callers that
    // handle other people's profiles (chooseavatar.php, updateprofile.php
    // when editing someone else) MUST pass the actual target id - otherwise
    // the picture is silently written to the editor's own account.
    $targetuserid = $targetuserid ?: $USER->id;
    $usercontext = context_user::instance($targetuserid);
    $fs = get_file_storage();
    $fs->delete_area_files($usercontext->id, 'user', 'icon');
    $newpicture = process_new_icon($usercontext, 'user', 'icon', 0 /* $itemid */, $imagefile, true /* $preferpng */);
    if ($newpicture) {
        $DB->set_field('user', 'picture', $newpicture, array('id' => $targetuserid));
        if ($targetuserid == $USER->id) {
            $USER->picture = $newpicture;
        }
    } else {
        error_log('Cannot update user image to content of file ' . $imagefile);
    }
}

function local_profile_set_avatar_by_id($avatarid, $targetuserid = null) {
    global $CFG;

    $imagefile = $CFG->dirroot . '/local/profile/pix/avatars/' . $avatarid . '.png';
    local_profile_set_avatar_by_image_file($imagefile, $targetuserid);
}

function local_profile_set_avatar_by_url($avatarurl, $targetuserid = null) {
    global $CFG;

    // Defense in depth: even though callers validate the path, this resolver
    // walks the path and confirms the absolute file still lives inside the
    // bundled avatars directory before handing it to process_new_icon().
    $imagefile = $CFG->dirroot . $avatarurl;
    $expectedbase = realpath($CFG->dirroot . '/local/profile/pix/avatars');
    $real = realpath($imagefile);
    if (!$real || !$expectedbase || strpos($real, $expectedbase) !== 0 || !is_file($real)) {
        return;
    }
    local_profile_set_avatar_by_image_file($real, $targetuserid);
}

function local_profile_print_edit_subheader() {
    $html = '';
    $html .= html_writer::start_div('mt-lg-6 ml-3 mb-6', array(
                'id' => 'subheader',
                'style' => 'color: rgb(48, 56, 64);'
    ));
    $html .= html_writer::start_div('pt-32pt');
    $html .= html_writer::start_tag('h2', array(
                'class' => 'mb-0'
    ));
    $html .= get_string('configurations', 'local_profile');
    $html .= html_writer::end_tag('h2');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('mt-4');
    $html .= html_writer::start_tag('p');
    $html .= get_string('configurationstxt', 'local_profile');
    $html .= html_writer::end_tag('p');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

function local_profile_print_time_selector() {
    global $USER;

    $html = '';
    $html .= html_writer::start_div('card card-group-row__card p-1 pb-4 mr-3 ml-3', array(
                'style' => 'color: #0E4242 !important;'
    ));
    $html .= html_writer::start_div('card-header d-flex align-items-center border-0', array(
                'style' => 'padding-bottom: 0 !important;'
    ));
    $html .= html_writer::start_div('mb-0 mr-3');
    $html .= html_writer::start_tag('i', array(
                'class' => 'icon fa fa-calendar fa-fw navicon mr-1',
                'style' => 'margin-left: .5rem; margin-right: 0 !important;'
    ));
    $html .= html_writer::end_tag('i');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('flex');
    $html .= html_writer::start_tag('small', array(
                'class' => 'strong text-uppercase text-primary700 form-label'
    ));
    $html .= get_string('studyplan', 'local_profile');
    $html .= html_writer::end_tag('small');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('card-body');
    $html .= html_writer::start_div('alert alert-soft-warning mb-24pt', array(
                'style' => 'background-color: rgba(228, 169, 60, .05); padding: .5rem; margin-bottom: 1rem; border: 1px solid #e4a93c; border-radius: .25rem; color: #e4a93c;'
    ));
    $html .= html_writer::start_div('d-flex align-items-center');
    $html .= html_writer::start_div('mr-3');
    $html .= html_writer::start_tag('i', array(
                'class' => 'icon fa fa-info-circle fa-fw navicon mr-1',
                'style' => 'margin-left: .5rem; margin-right: 0 !important;'
    ));
    $html .= html_writer::end_tag('i');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('text-body');
    $lastprofileupdate = local_profile_get_last_profile_update($USER);
    $html .= get_string('changeanytime', 'local_profile');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('form-group');
    $plans = local_profile_get_study_plans();
    $html .= html_writer::start_tag('label', array(
                'class' => 'form-label'
    ));
    $planmonths = 0;
    if ($lastprofileupdate) {
        $html .= get_string('yourcurrentplan', 'local_profile');
        $html .= html_writer::start_tag('strong');
        $html .= get_string('plan', 'local_profile') . ' ';
        $planmonths = (int) $lastprofileupdate->nummonths;
        $planlabel = array_key_exists($planmonths, $plans)
            ? $plans[$planmonths]
            : get_string('plannotselected', 'local_profile');
        $html .= html_writer::tag('span', $planlabel, array(
                    'id' => 'planname'
        ));
        $html .= html_writer::end_tag('strong');
    }
    $html .= html_writer::start_tag('select', array(
                'class' => 'form-control',
                'id' => 'planSelect'
    ));
    $placeholderattrs = array(
                'value' => '',
                'disabled' => '',
    );
    if ($planmonths <= 0 || !array_key_exists($planmonths, $plans)) {
        $placeholderattrs['selected'] = '';
    }
    $html .= html_writer::start_tag('option', $placeholderattrs);
    $html .= get_string('selectnewplan', 'local_profile');
    $html .= html_writer::end_tag('option');
    foreach ($plans as $months => $name) {
        $optionattrs = array(
                    'value' => $months,
                    'planname' => $name
        );
        if ($planmonths === (int) $months) {
            $optionattrs['selected'] = '';
        }
        $html .= html_writer::start_tag('option', $optionattrs);
        $html .= get_string('plantxt', 'local_profile', array(
            'name' => $name,
            'months' => $months
        ));
        $html .= html_writer::end_tag('option');
    }
    $html .= html_writer::end_tag('select');
    $html .= html_writer::start_tag('small', array(
                'class' => 'form-text text-muted',
                'style' => 'text-transform: none; letter-spacing: normal;'
    ));
    $html .= get_string('choosethebest', 'local_profile');
    $html .= html_writer::end_tag('small');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

function local_profile_print_avatar_chooser() {
    global $USER, $PAGE;

    $html = '';
    $html .= html_writer::start_div('card card-group-row__card p-1 pb-4 mr-3 ml-3 bg-purple100', array(
                'style' => 'color: #0E4242 !important;'
    ));
    $html .= html_writer::start_div('card-header d-flex align-items-center border-0 bg-purple100');
    $html .= html_writer::start_div('mb-0 mr-3');
    $html .= html_writer::start_tag('i', array(
                'class' => 'icon fa fa-camera fa-fw navicon mr-1',
                'style' => 'margin-left: .5rem; margin-right: 0 !important;'
    ));
    $html .= html_writer::end_tag('i');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('flex');
    $html .= html_writer::start_tag('small', array(
                'class' => 'strong text-uppercase text-primary700 form-label'
    ));
    $html .= get_string('profilepicture', 'local_profile');
    $html .= html_writer::end_tag('small');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('card-body flex text-center d-flex flex-column align-items-center justify-content-center', array(
                'style' => 'background-color: transparent;'
    ));
    $html .= html_writer::start_tag('a', array(
                'href' => '#',
                'onclick' => 'toggleAvatarSelector(event);',
                'class' => 'avatar avatar-xxl overlay js-overlay rounded-circle p-relative o-hidden mb-16pt',
                'style' => 'width: 12rem; height: 12rem;',
                'data-trigger' => 'hover',
                'data-domfactory-upgraded' => 'overlay'
    ));
    $userpicture = new user_picture($USER);
    $userpicture->size = 140;
    $userpictureurl = $userpicture->get_url($PAGE);
    $html .= html_writer::tag('img', '', array(
                'src' => $userpictureurl,
                'alt' => 'avatar',
                'class' => 'avatar-img',
                'width' => '180px',
                'id' => 'avatarImg'
    ));
    $html .= html_writer::start_tag('span', array(
                'class' => 'overlay__content'
    ));
    $html .= html_writer::start_tag('i', array(
                'class' => 'icon fa fa-edit fa-fw navicon mr-1',
                'style' => 'margin-left: .5rem; margin-right: 0 !important;'
    ));
    $html .= html_writer::end_tag('i');
    $html .= html_writer::end_tag('span');
    $html .= html_writer::end_tag('a');
    $html .= html_writer::start_div('flex mt-3');
    $html .= html_writer::start_tag('h2');
    $html .= fullname($USER);
    $html .= html_writer::end_tag('h2');
    $html .= html_writer::start_div('text-70 pl-16pt pr-16pt');
    // strftime() is deprecated in PHP 8.1 and removed in PHP 9.0. userdate()
    // is the Moodle-native equivalent and respects the user's locale/timezone.
    $usercreationdate = str_replace("De", "de", ucwords(userdate($USER->timecreated, get_string('dateformat', 'local_profile'))));
    $html .= get_string('membersince', 'local_profile', $usercreationdate);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('flex');
    $html .= html_writer::start_tag('a', array(
                'href' => '#',
                'onclick' => 'toggleAvatarSelector(event);',
                'class' => 'btn btn-rounded btn-accent mt-3'
    ));
    $html .= get_string('chooseanotheravatar', 'local_profile');
    $html .= html_writer::start_tag('i', array(
                'class' => 'icon fa fa-edit fa-fw navicon mr-1',
                'style' => 'margin-left: .5rem; margin-right: 0 !important;'
    ));
    $html .= html_writer::end_tag('i');
    $html .= html_writer::end_tag('a');
    $html .= html_writer::start_div('card mt-5', array(
                'id' => 'avatarSelector',
                'style' => 'display: none;'
    ));
    $html .= html_writer::start_div('card-header bg-purple400', array(
                'style' => 'background-color: rgb(153, 48, 255);'
    ));
    $html .= html_writer::start_tag('h5', array(
                'class' => 'card-title mb-0 text-white'
    ));
    $html .= get_string('chooseyouravatar', 'local_profile');
    $html .= html_writer::end_tag('h5');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('card-body justify-content-center align-items-center');
    $html .= html_writer::start_div('d-flex flex-wrap justify-content-center align-items-center');
    global $CFG;
    $avatars = local_profile_get_avatars('pdi');
    foreach ($avatars as $avatarurl) {
        // The img src needs wwwroot so the browser doesn't strip a subdirectory
        // install (e.g. http://localhost/moodle-pdi/...). The selectAvatar
        // argument keeps the relative path because that's what updateprofile.php
        // validates - sending the full URL would survive PARAM_PATH only by
        // losing its colons and then fail the strict path-prefix check.
        $html .= html_writer::start_div('avatar-item m-2', array(
                    'onclick' => 'selectAvatar("' . $avatarurl . '", this);'
        ));
        $html .= html_writer::tag('img', '', array(
                    'src' => $CFG->wwwroot . $avatarurl,
                    'alt' => 'avatar',
                    'class' => 'rounded-circle',
                    'width' => '60',
                    'height' => '60'
        ));
        $html .= html_writer::end_div();
    }
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('mt-3');
    $html .= html_writer::start_tag('button', array(
                'class' => 'btn btn-accent btn-rounded',
                'onclick' => 'saveAvatar();'
    ));
    $html .= get_string('confirm');
    $html .= html_writer::end_tag('button');
    $html .= html_writer::start_tag('button', array(
                'class' => 'btn btn-outline-secondary ml-2 btn-rounded',
                'onclick' => 'toggleAvatarSelector(event);'
    ));
    $html .= get_string('cancel');
    $html .= html_writer::end_tag('button');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

<?php

/**
 * Form to edit a users profile
 *
 * @package   local_profile
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    //  It must be included from a Moodle page.
}

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Class user_edit_form.
 *
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_edit_form extends moodleform {

    /**
     * Define the form.
     */
    public function definition() {
        global $CFG, $COURSE, $USER;

        $mform = $this->_form;
        $editoroptions = null;
        $filemanageroptions = null;
        $usernotfullysetup = user_not_fully_set_up($USER);

        if (!is_array($this->_customdata)) {
            throw new coding_exception('invalid custom data for user_edit_form');
        }
        $editoroptions = $this->_customdata['editoroptions'];
        $filemanageroptions = $this->_customdata['filemanageroptions'];
        $user = $this->_customdata['user'];
        $user->repeatpassword = $user->password = '';
        $userid = $user->id;

        if (empty($user->country)) {
            // We must unset the value here so $CFG->country can be used as default one.
            unset($user->country);
        }

        // Accessibility: "Required" is bad legend text.
        $strgeneral = get_string('general');
        $strrequired = get_string('required');

        // Add some extra hidden fields.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'course', $COURSE->id);
        $mform->setType('course', PARAM_INT);

        // Shared fields.
        useredit_shared_definition($mform, $editoroptions, $filemanageroptions, $user);

        // Extra settigs.
        if (!empty($CFG->disableuserimages) || $usernotfullysetup) {
            $mform->removeElement('deletepicture');
            $mform->removeElement('imagefile');
            $mform->removeElement('imagealt');
        }

        // If the user isn't fully set up, let them know that they will be able to change
        // their profile picture once their profile is complete.
        if ($usernotfullysetup) {
            $userpicturewarning = $mform->createElement('warning', 'userpicturewarning', 'notifymessage', get_string('newpictureusernotsetup'));
            $enabledusernamefields = useredit_get_enabled_name_fields();
            if ($mform->elementExists('moodle_additional_names')) {
                $mform->insertElementBefore($userpicturewarning, 'moodle_additional_names');
            } else if ($mform->elementExists('moodle_interests')) {
                $mform->insertElementBefore($userpicturewarning, 'moodle_interests');
            } else {
                $mform->insertElementBefore($userpicturewarning, 'moodle_optional');
            }
        }

        // This is expected to exist when the form is submitted.
        $imagefile = $mform->createElement('hidden', 'imagefile');
        $mform->addElement($imagefile);
        // imagefile holds a draft area itemid (integer). PARAM_RAW would let
        // non-numeric/garbage values reach core_user::update_picture().
        $mform->setType('imagefile', PARAM_INT);

        $this->add_action_buttons(true, get_string('updatemyprofile'));

        $this->set_data($user);
    }

    /**
     * Extend the form definition after the data has been parsed.
     */
    public function definition_after_data() {
        global $CFG, $DB, $OUTPUT;

        $mform = $this->_form;
        $userid = $mform->getElementValue('id');

        // Trim required name fields.
        foreach (useredit_get_required_name_fields() as $field) {
            $mform->applyFilter($field, 'trim');
        }

        if ($user = $DB->get_record('user', array('id' => $userid))) {

            // Remove description.
            if (empty($user->description) && !empty($CFG->profilesforenrolledusersonly) && !$DB->record_exists('role_assignments', array('userid' => $userid))) {
                $mform->removeElement('description_editor');
            }

            // Disable fields that are locked by auth plugins.
            $fields = get_user_fieldnames();
            $authplugin = get_auth_plugin($user->auth);
            $customfields = $authplugin->get_custom_user_profile_fields();
            $customfieldsdata = profile_user_record($userid, false);
            $fields = array_merge($fields, $customfields);
            foreach ($fields as $field) {
                if ($field === 'description') {
                    // Hard coded hack for description field. See MDL-37704 for details.
                    $formfield = 'description_editor';
                } else {
                    $formfield = $field;
                }
                if (!$mform->elementExists($formfield)) {
                    continue;
                }

                // Get the original value for the field.
                if (in_array($field, $customfields)) {
                    $key = str_replace('profile_field_', '', $field);
                    $value = isset($customfieldsdata->{$key}) ? $customfieldsdata->{$key} : '';
                } else {
                    $value = $user->{$field};
                }

                $configvariable = 'field_lock_' . $field;
                if (isset($authplugin->config->{$configvariable})) {
                    if ($authplugin->config->{$configvariable} === 'locked') {
                        $mform->hardFreeze($formfield);
                        $mform->setConstant($formfield, $value);
                    } else if ($authplugin->config->{$configvariable} === 'unlockedifempty' and $value != '') {
                        $mform->hardFreeze($formfield);
                        $mform->setConstant($formfield, $value);
                    }
                }
            }

            // Next the customisable profile fields.
            profile_definition_after_data($mform, $user->id);
        } else {
            profile_definition_after_data($mform, 0);
        }
    }

    /**
     * Validate incoming form data.
     * @param array $usernew
     * @param array $files
     * @return array
     */
    public function validation($usernew, $files) {
        global $CFG, $DB;

        $errors = parent::validation($usernew, $files);

        $usernew = (object) $usernew;
        $user = $DB->get_record('user', array('id' => $usernew->id));

        // Validate email.
        if (!isset($usernew->email)) {
            // Mail not confirmed yet.
        } else if (!validate_email($usernew->email)) {
            $errors['email'] = get_string('invalidemail');
        } else if (($usernew->email !== $user->email)
                and empty($CFG->allowaccountssameemail)
                and $DB->record_exists('user', array('email' => $usernew->email, 'mnethostid' => $CFG->mnet_localhost_id))) {
            $errors['email'] = get_string('emailexists');
        }

        if (isset($usernew->email) and $usernew->email === $user->email and over_bounce_threshold($user)) {
            $errors['email'] = get_string('toomanybounces');
        }

        if (isset($usernew->email) and ! empty($CFG->verifychangedemail) and ! isset($errors['email']) and ! has_capability('moodle/user:update', context_system::instance())) {
            $errorstr = email_is_not_allowed($usernew->email);
            if ($errorstr !== false) {
                $errors['email'] = $errorstr;
            }
        }

        // Validate password
        if ($usernew->password) {
            if (strlen($usernew->password) < 4) {
                $errors['password'] = get_string('minpasswordlength', 'local_profile');
            }
            if ($usernew->password != $usernew->repeatpassword) {
                $errors['repeatpassword'] = get_string('repeatpasswordmustmatch', 'local_profile');
            }
        }

        // Next the customisable profile fields.
        $errors += profile_validation($usernew, $files);

        return $errors;
    }

}

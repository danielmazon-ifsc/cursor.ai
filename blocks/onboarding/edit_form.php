<?php

/**
 * Defines the form for editing onboarding block instances.
 *
 * @package   block_onboarding
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/onboarding/locallib.php');

/**
 * Form for editing onboarding block instances.
 *
 * @package   block_onboarding
 */
class block_onboarding_edit_form extends block_edit_form {

    /**
     * The definition of the fields to use.
     *
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {

        // Fields for editing onboarding block title and contents.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Video content 
        $videotype_options = array();
        $videotype_options[BLOCK_FORMAT_NONE] = get_string('none', 'block_onboarding');
        $videotype_options[BLOCK_FORMAT_VIMEO] = get_string('vimeo', 'block_onboarding');
        $videotype_options[BLOCK_FORMAT_YOUTUBE] = get_string('youtube', 'block_onboarding');
        $videotype_options[BLOCK_FORMAT_EMBEDED] = get_string('embeded', 'block_onboarding');
        $mform->addElement('select', 'config_contentformat', get_string('type', 'block_onboarding'), $videotype_options);
        $mform->addRule('config_contentformat', null, 'required', null, 'client');
        $mform->setDefault('config_contentformat', BLOCK_FORMAT_NONE);
        $mform->addElement('text', 'config_vimeoid', get_string('vimeoid', 'block_onboarding'), array('size' => '20'));
        $mform->setType('config_vimeoid', PARAM_NUMBER);
        $mform->addElement('text', 'config_youtubeid', get_string('youtubeid', 'block_onboarding'), array('size' => '20'));
        $mform->setType('config_youtubeid', PARAM_ALPHANUMEXT);
        $mform->addElement('text', 'config_embededurl', get_string('embededurl', 'block_onboarding'), array('size' => '60'));
        $mform->setType('config_embededurl', PARAM_URL);
    }

    function definition() {
        global $PAGE;

        parent::definition();
        $PAGE->requires->js('/blocks/onboarding/formcontrol.js');
    }

}

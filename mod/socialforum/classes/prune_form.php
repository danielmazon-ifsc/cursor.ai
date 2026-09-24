<?php

/**
 * This file provides form for splitting discussions
 *
 * @package    mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}
require_once("$CFG->libdir/formslib.php");

/**
 * Form which displays fields for splitting socialforum post to a separate threads.
 *
 * @package    mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class mod_socialforum_prune_form extends moodleform {

    /**
     * Form constructor.
     *
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('discussionname', 'mod_socialforum'), array('size' => '60', 'maxlength' => '255'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->add_action_buttons(true, get_string('prune', 'mod_socialforum'));

        $mform->addElement('hidden', 'prune');
        $mform->setType('prune', PARAM_INT);
        $mform->setConstant('prune', $this->_customdata['prune']);

        $mform->addElement('hidden', 'confirm');
        $mform->setType('confirm', PARAM_INT);
        $mform->setConstant('confirm', $this->_customdata['confirm']);
    }

}

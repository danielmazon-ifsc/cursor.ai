<?php

/**
 * Cquiz statistics settings form definition.
 *
 * @package   cquiz_statistics
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * This is the settings form for the cquiz statistics report.
 *
 * @package   cquiz_statistics
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquiz_statistics_settings_form extends moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'preferencespage', get_string('reportsettings', 'cquiz_statistics'));

        $options = array();
        foreach (array_keys(cquiz_get_grading_options()) as $which) {
            $options[$which] = \cquiz_statistics\calculator::using_attempts_lang_string($which);
        }

        $mform->addElement('select', 'whichattempts', get_string('calculatefrom', 'cquiz_statistics'), $options);

        if (cquiz_allows_multiple_tries($this->_customdata['cquiz'])) {
            $mform->addElement('select', 'whichtries', get_string('whichtries', 'cquiz_statistics'), array(
                question_attempt::FIRST_TRY => get_string('firsttry', 'question'),
                question_attempt::LAST_TRY => get_string('lasttry', 'question'),
                question_attempt::ALL_TRIES => get_string('alltries', 'question'))
            );
            $mform->setDefault('whichtries', question_attempt::LAST_TRY);
        }
        $mform->addElement('submit', 'submitbutton', get_string('preferencessave', 'cquiz_overview'));
    }

}

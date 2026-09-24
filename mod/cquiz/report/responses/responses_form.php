<?php

/**
 * This file defines the setting form for the cquiz responses report.
 *
 * @package   cquiz_responses
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/cquiz/report/attemptsreport_form.php');

/**
 * Cquiz responses report settings form.
 *
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquiz_responses_settings_form extends mod_cquiz_attempts_report_form {

    protected function other_preference_fields(MoodleQuickForm $mform) {
        $mform->addGroup(array(
            $mform->createElement('advcheckbox', 'qtext', '', get_string('questiontext', 'cquiz_responses')),
            $mform->createElement('advcheckbox', 'resp', '', get_string('response', 'cquiz_responses')),
            $mform->createElement('advcheckbox', 'right', '', get_string('rightanswer', 'cquiz_responses')),
                ), 'coloptions', get_string('showthe', 'cquiz_responses'), array(' '), false);
        $mform->disabledIf('qtext', 'attempts', 'eq', cquiz_attempts_report::ENROLLED_WITHOUT);
        $mform->disabledIf('resp', 'attempts', 'eq', cquiz_attempts_report::ENROLLED_WITHOUT);
        $mform->disabledIf('right', 'attempts', 'eq', cquiz_attempts_report::ENROLLED_WITHOUT);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['attempts'] != cquiz_attempts_report::ENROLLED_WITHOUT && !(
                $data['qtext'] || $data['resp'] || $data['right'])) {
            $errors['coloptions'] = get_string('reportmustselectstate', 'cquiz');
        }

        return $errors;
    }

    protected function other_attempt_fields(MoodleQuickForm $mform) {
        parent::other_attempt_fields($mform);
        if (cquiz_allows_multiple_tries($this->_customdata['cquiz'])) {
            $mform->addElement('select', 'whichtries', get_string('whichtries', 'question'), array(
                question_attempt::FIRST_TRY => get_string('firsttry', 'question'),
                question_attempt::LAST_TRY => get_string('lasttry', 'question'),
                question_attempt::ALL_TRIES => get_string('alltries', 'question'))
            );
            $mform->setDefault('whichtries', question_attempt::LAST_TRY);
        }
    }

}

<?php

/**
 * This file defines the setting form for the cquiz overview report.
 *
 * @package   cquiz_overview
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/cquiz/report/attemptsreport_form.php');

/**
 * Cquiz overview report settings form.
 *
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquiz_overview_settings_form extends mod_cquiz_attempts_report_form {

    protected function other_attempt_fields(MoodleQuickForm $mform) {
        if (has_capability('mod/cquiz:regrade', $this->_customdata['context'])) {
            $mform->addElement('advcheckbox', 'onlyregraded', get_string('reportshowonly', 'cquiz'), get_string('optonlyregradedattempts', 'cquiz_overview'));
            $mform->disabledIf('onlyregraded', 'attempts', 'eq', cquiz_attempts_report::ENROLLED_WITHOUT);
        }
    }

    protected function other_preference_fields(MoodleQuickForm $mform) {
        if (cquiz_has_grades($this->_customdata['cquiz'])) {
            $mform->addElement('selectyesno', 'slotmarks', get_string('showdetailedmarks', 'cquiz_overview'));
        } else {
            $mform->addElement('hidden', 'slotmarks', 0);
            $mform->setType('slotmarks', PARAM_INT);
        }
    }

}

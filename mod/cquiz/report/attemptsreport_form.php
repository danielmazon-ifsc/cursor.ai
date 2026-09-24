<?php

/**
 * Base class for the settings form for {@link cquiz_attempts_report}s.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Base class for the settings form for {@link cquiz_attempts_report}s.
 *
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
abstract class mod_cquiz_attempts_report_form extends moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'preferencespage', get_string('reportwhattoinclude', 'cquiz'));

        $this->standard_attempt_fields($mform);
        $this->other_attempt_fields($mform);

        $mform->addElement('header', 'preferencesuser', get_string('reportdisplayoptions', 'cquiz'));

        $this->standard_preference_fields($mform);
        $this->other_preference_fields($mform);

        $mform->addElement('submit', 'submitbutton', get_string('showreport', 'cquiz'));
    }

    protected function standard_attempt_fields(MoodleQuickForm $mform) {

        $mform->addElement('select', 'attempts', get_string('reportattemptsfrom', 'cquiz'), array(
            cquiz_attempts_report::ENROLLED_WITH => get_string('reportuserswith', 'cquiz'),
            cquiz_attempts_report::ENROLLED_WITHOUT => get_string('reportuserswithout', 'cquiz'),
            cquiz_attempts_report::ENROLLED_ALL => get_string('reportuserswithorwithout', 'cquiz'),
            cquiz_attempts_report::ALL_WITH => get_string('reportusersall', 'cquiz'),
        ));

        $stategroup = array(
            $mform->createElement('advcheckbox', 'stateinprogress', '', get_string('stateinprogress', 'cquiz')),
            $mform->createElement('advcheckbox', 'stateoverdue', '', get_string('stateoverdue', 'cquiz')),
            $mform->createElement('advcheckbox', 'statefinished', '', get_string('statefinished', 'cquiz')),
            $mform->createElement('advcheckbox', 'stateabandoned', '', get_string('stateabandoned', 'cquiz')),
        );
        $mform->addGroup($stategroup, 'stateoptions', get_string('reportattemptsthatare', 'cquiz'), array(' '), false);
        $mform->setDefault('stateinprogress', 1);
        $mform->setDefault('stateoverdue', 1);
        $mform->setDefault('statefinished', 1);
        $mform->setDefault('stateabandoned', 1);
        $mform->disabledIf('stateinprogress', 'attempts', 'eq', cquiz_attempts_report::ENROLLED_WITHOUT);
        $mform->disabledIf('stateoverdue', 'attempts', 'eq', cquiz_attempts_report::ENROLLED_WITHOUT);
        $mform->disabledIf('statefinished', 'attempts', 'eq', cquiz_attempts_report::ENROLLED_WITHOUT);
        $mform->disabledIf('stateabandoned', 'attempts', 'eq', cquiz_attempts_report::ENROLLED_WITHOUT);

        if (cquiz_report_can_filter_only_graded($this->_customdata['cquiz'])) {
            $gm = html_writer::tag('span', cquiz_get_grading_option_name($this->_customdata['cquiz']->grademethod), array('class' => 'highlight'));
            $mform->addElement('advcheckbox', 'onlygraded', '', get_string('reportshowonlyfinished', 'cquiz', $gm));
            $mform->disabledIf('onlygraded', 'attempts', 'eq', cquiz_attempts_report::ENROLLED_WITHOUT);
            $mform->disabledIf('onlygraded', 'statefinished', 'notchecked');
        }
    }

    protected function other_attempt_fields(MoodleQuickForm $mform) {
        
    }

    protected function standard_preference_fields(MoodleQuickForm $mform) {
        $mform->addElement('text', 'pagesize', get_string('pagesize', 'cquiz'));
        $mform->setType('pagesize', PARAM_INT);
    }

    protected function other_preference_fields(MoodleQuickForm $mform) {
        
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['attempts'] != cquiz_attempts_report::ENROLLED_WITHOUT && !(
                $data['stateinprogress'] || $data['stateoverdue'] || $data['statefinished'] || $data['stateabandoned'])) {
            $errors['stateoptions'] = get_string('reportmustselectstate', 'cquiz');
        }

        return $errors;
    }

}

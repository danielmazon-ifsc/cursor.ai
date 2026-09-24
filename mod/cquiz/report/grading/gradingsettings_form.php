<?php

/**
 * This file defines the setting form for the cquiz grading report.
 *
 * @package   cquiz_grading
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Cquiz grading report settings form.
 *
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquiz_grading_settings_form extends moodleform {

    protected $includeauto;
    protected $hidden = array();
    protected $counts;
    protected $shownames;
    protected $showidnumbers;

    public function __construct($hidden, $counts, $shownames, $showidnumbers) {
        global $CFG;
        $this->includeauto = !empty($hidden['includeauto']);
        $this->hidden = $hidden;
        $this->counts = $counts;
        $this->shownames = $shownames;
        $this->showidnumbers = $showidnumbers;
        parent::__construct($CFG->wwwroot . '/mod/cquiz/report.php', null, 'get');
    }

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'options', get_string('options', 'cquiz_grading'));

        $gradeoptions = array();
        foreach (array('needsgrading', 'manuallygraded', 'autograded', 'all') as $type) {
            if (empty($this->counts->$type)) {
                continue;
            }
            if ($type == 'autograded' && !$this->includeauto) {
                continue;
            }
            $gradeoptions[$type] = get_string('gradeattempts' . $type, 'cquiz_grading', $this->counts->$type);
        }
        $mform->addElement('select', 'grade', get_string('attemptstograde', 'cquiz_grading'), $gradeoptions);

        $mform->addElement('text', 'pagesize', get_string('questionsperpage', 'cquiz_grading'), array('size' => 3));
        $mform->setType('pagesize', PARAM_INT);

        $orderoptions = array(
            'random' => get_string('randomly', 'cquiz_grading'),
            'date' => get_string('bydate', 'cquiz_grading'),
        );
        if ($this->shownames) {
            $orderoptions['studentfirstname'] = get_string('bystudentfirstname', 'cquiz_grading');
            $orderoptions['studentlastname'] = get_string('bystudentlastname', 'cquiz_grading');
        }
        if ($this->showidnumbers) {
            $orderoptions['idnumber'] = get_string('bystudentidnumber', 'cquiz_grading');
        }
        $mform->addElement('select', 'order', get_string('orderattempts', 'cquiz_grading'), $orderoptions);

        foreach ($this->hidden as $name => $value) {
            $mform->addElement('hidden', $name, $value);
            if ($name == 'mode') {
                $mform->setType($name, PARAM_ALPHA);
            } else {
                $mform->setType($name, PARAM_INT);
            }
        }

        $mform->addElement('submit', 'submitbutton', get_string('changeoptions', 'cquiz_grading'));
    }

}

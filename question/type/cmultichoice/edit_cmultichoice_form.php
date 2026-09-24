<?php

/**
 * Defines the editing form for the multiple choice question type.
 *
 * @package    qtype
 * @subpackage cmultichoice
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

use core_competency\course_competency;

/**
 * Multiple choice editing form definition.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class qtype_cmultichoice_edit_form extends question_edit_form {

    /**
     * Add question-type specific form fields.
     *
     * @param object $mform the form being built.
     */
    protected function definition_inner($mform) {
        global $CFG, $COURSE;

        $menu = array(
            get_string('answersingleno', 'qtype_cmultichoice'),
            get_string('answersingleyes', 'qtype_cmultichoice'),
        );
        $mform->addElement('select', 'single', get_string('answerhowmany', 'qtype_cmultichoice'), $menu);
        $mform->setDefault('single', 1);

        $mform->addElement('advcheckbox', 'shuffleanswers', get_string('shuffleanswers', 'qtype_cmultichoice'), null, null, array(0, 1));
        $mform->addHelpButton('shuffleanswers', 'shuffleanswers', 'qtype_cmultichoice');
        $mform->setDefault('shuffleanswers', 1);

        $mform->addElement('select', 'answernumbering', get_string('answernumbering', 'qtype_cmultichoice'), qtype_cmultichoice::get_numbering_styles());
        $mform->setDefault('answernumbering', 'abc');

        MoodleQuickForm::registerElementType('question_competencies', "$CFG->dirroot/admin/tool/lp/classes/course_competencies_form_element.php", 'tool_lp_course_competencies_form_element');

        $this->add_per_answer_fields($mform, get_string('choiceno', 'qtype_cmultichoice', '{no}'), question_bank::fraction_options_full(), max(5, QUESTION_NUMANS_START));

        $this->add_combined_feedback_fields(true);
        $mform->disabledIf('shownumcorrect', 'single', 'eq', 1);

        $this->add_interactive_settings(true, true);

        // Competency selection
        $mform->addElement('header', 'competenciessection', get_string('competencies', 'core_competency'));
        $mform->addElement('question_competencies', 'competencies', get_string('questioncompetencies', 'qtype_cmultichoice'), array('courseid' => $COURSE->id));
        $mform->addHelpButton('competencies', 'questioncompetencies', 'qtype_cmultichoice');
        MoodleQuickForm::registerElementType('course_competency_rule', "$CFG->dirroot/admin/tool/lp/classes/course_competency_rule_form_element.php", 'tool_lp_course_competency_rule_form_element');
        $mform->setExpanded('competenciessection');
    }

    /**
     * Get the list of form elements to repeat, one for each answer.
     * @param object $mform the form being built.
     * @param $label the label to use for each option.
     * @param $gradeoptions the possible grades for each answer.
     * @param $repeatedoptions reference to array of repeated options to fill
     * @param $answersoption reference to return the name of $question->options
     *      field holding an array of answers
     * @return array of form fields.
     */
    protected function get_per_answer_fields($mform, $label, $gradeoptions, &$repeatedoptions, &$answersoption) {
        global $COURSE;

        $repeated = array();
        $repeated[] = $mform->createElement('editor', 'answer', $label, array('rows' => 1), $this->editoroptions);
        $repeated[] = $mform->createElement('select', 'fraction', get_string('grade'), $gradeoptions);
        $repeated[] = $mform->createElement('editor', 'feedback', get_string('feedback', 'question'), array('rows' => 1), $this->editoroptions);
        $competencies = $this->get_course_competencies($COURSE->id);
        $compoptions = array(null => '');
        foreach ($competencies as $competency) {
            $compoptions[$competency->id] = $competency->shortname;
        }
        $repeated[] = $mform->createElement('select', 'competencyid', get_string('answercompetency', 'qtype_cmultichoice'), $compoptions);
        $repeated[] = $mform->createElement('hidden', 'questionid');
        $mform->setType('questionid', PARAM_INT);
        $repeatedoptions['answer']['type'] = PARAM_RAW;
        $repeatedoptions['fraction']['default'] = 0;
        $answersoption = 'answers';
        return $repeated;
    }

    private function get_course_competencies($courseid) {
        global $DB;

        $sql = 'SELECT c.*
                  FROM {competency} c
                  JOIN {competency_coursecomp} cc
                    ON cc.competencyid = c.id
                 WHERE cc.courseid = :courseid';
        return $DB->get_records_sql($sql, array('courseid' => $courseid));
    }

    protected function get_hint_fields($withclearwrong = false, $withshownumpartscorrect = false) {
        list($repeated, $repeatedoptions) = parent::get_hint_fields($withclearwrong, $withshownumpartscorrect);
        $repeatedoptions['hintclearwrong']['disabledif'] = array('single', 'eq', 1);
        $repeatedoptions['hintshownumcorrect']['disabledif'] = array('single', 'eq', 1);
        return array($repeated, $repeatedoptions);
    }

    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        $question = $this->data_preprocessing_answers($question, true);
        $question = $this->data_preprocessing_combined_feedback($question, true);
        $question = $this->data_preprocessing_hints($question, true, true);

        if (!empty($question->options)) {
            $question->single = $question->options->single;
            $question->shuffleanswers = $question->options->shuffleanswers;
            $question->answernumbering = $question->options->answernumbering;
        }

        return $question;
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $answers = $data['answer'];
        $answercount = 0;

        $totalfraction = 0;
        $maxfraction = -1;

        foreach ($answers as $key => $answer) {
            // Check no of choices.
            $trimmedanswer = trim($answer['text']);
            $fraction = (float) $data['fraction'][$key];
            if ($trimmedanswer === '' && empty($fraction)) {
                continue;
            }
            if ($trimmedanswer === '') {
                $errors['fraction[' . $key . ']'] = get_string('errgradesetanswerblank', 'qtype_cmultichoice');
            }

            $answercount++;

            // Check grades.
            if ($data['fraction'][$key] > 0) {
                $totalfraction += $data['fraction'][$key];
            }
            if ($data['fraction'][$key] > $maxfraction) {
                $maxfraction = $data['fraction'][$key];
            }
        }

        if ($answercount == 0) {
            $errors['answer[0]'] = get_string('notenoughanswers', 'qtype_cmultichoice', 2);
            $errors['answer[1]'] = get_string('notenoughanswers', 'qtype_cmultichoice', 2);
        } else if ($answercount == 1) {
            $errors['answer[1]'] = get_string('notenoughanswers', 'qtype_cmultichoice', 2);
        }

        // Perform sanity checks on fractional grades.
        if ($data['single']) {
            if ($maxfraction != 1) {
                $errors['fraction[0]'] = get_string('errfractionsnomax', 'qtype_cmultichoice', $maxfraction * 100);
            }
        } else {
            $totalfraction = round($totalfraction, 2);
            if ($totalfraction != 1) {
                $errors['fraction[0]'] = get_string('errfractionsaddwrong', 'qtype_cmultichoice', $totalfraction * 100);
            }
        }
        return $errors;
    }

    public function qtype() {
        return 'cmultichoice';
    }

    protected function add_interactive_settings($withclearwrong = false, $withshownumpartscorrect = false) {
        parent::add_interactive_settings($withclearwrong, $withshownumpartscorrect);
        $this->_form->setDefault('penalty', 0.5000000);
    }

}

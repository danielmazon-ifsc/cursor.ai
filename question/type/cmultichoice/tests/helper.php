<?php

/**
 * Test helper code for the multiple choice question type.
 *
 * @package    qtype_cmultichoice
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Test helper class for the multiple choice question type.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class qtype_cmultichoice_test_helper extends question_test_helper {

    const STANDARD_OVERALL_CORRECT_FEEDBACK = 'Well done!';
    const STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK = 'Parts, but only parts, of your response are correct.';
    const STANDARD_OVERALL_INCORRECT_FEEDBACK = 'That is not right at all.';

    /**
     * Initialise the common fields of a question of any type.
     */
    public function get_test_questions() {
        return array('two_of_four', 'one_of_four');
    }

    public static function initialise_a_question($q) {
        global $USER;

        $q->id = 0;
        $q->category = 0;
        $q->parent = 0;
        $q->questiontextformat = FORMAT_HTML;
        $q->generalfeedbackformat = FORMAT_HTML;
        $q->defaultmark = 1;
        $q->penalty = 0.3333333;
        $q->length = 1;
        $q->stamp = make_unique_id_code();
        $q->version = make_unique_id_code();
        $q->hidden = 0;
        $q->timecreated = time();
        $q->timemodified = time();
        $q->createdby = $USER->id;
        $q->modifiedby = $USER->id;
    }

    /**
     * Makes a multichoice question with choices 'A', 'B' and 'C' shuffled. 'A'
     * is correct, defaultmark 1.
     * @return qtype_multichoice_single_question
     */
    public static function make_a_cmultichoice_single_question() {
        question_bank::load_question_definition_classes('cmultichoice');
        $mc = new qtype_cmultichoice_single_question();
        qtype_cmultichoice_test_helper::initialise_a_question($mc);
        $mc->name = 'Multi-choice question, single response';
        $mc->questiontext = 'The answer is A.';
        $mc->generalfeedback = 'You should have selected A.';
        $mc->qtype = question_bank::get_qtype('multichoice');

        $mc->shuffleanswers = 1;
        $mc->answernumbering = 'abc';

        $mc->answers = array(
            13 => new question_answer(13, 'A', 1, 'A is right', FORMAT_HTML),
            14 => new question_answer(14, 'B', -0.3333333, 'B is wrong', FORMAT_HTML),
            15 => new question_answer(15, 'C', -0.3333333, 'C is wrong', FORMAT_HTML),
        );

        return $mc;
    }

    /**
     * Makes a multichoice question with choices 'A', 'B', 'C' and 'D' shuffled.
     * 'A' and 'C' is correct, defaultmark 1.
     * @return qtype_multichoice_multi_question
     */
    public static function make_a_cmultichoice_multi_question() {
        question_bank::load_question_definition_classes('cmultichoice');
        $mc = new qtype_cmultichoice_multi_question();
        qtype_cmultichoice_test_helper::initialise_a_question($mc);
        $mc->name = 'Competency multichoice question, multiple response';
        $mc->questiontext = 'The answer is A and C.';
        $mc->generalfeedback = 'You should have selected A and C.';
        $mc->qtype = question_bank::get_qtype('cmultichoice');

        $mc->shuffleanswers = 1;
        $mc->answernumbering = 'abc';

        qtype_cmultichoice_test_helper::set_standard_combined_feedback_fields($mc);

        $mc->answers = array(
            13 => new question_answer(13, 'A', 0.5, 'A is part of the right answer', FORMAT_HTML),
            14 => new question_answer(14, 'B', -1, 'B is wrong', FORMAT_HTML),
            15 => new question_answer(15, 'C', 0.5, 'C is part of the right answer', FORMAT_HTML),
            16 => new question_answer(16, 'D', -1, 'D is wrong', FORMAT_HTML),
        );

        return $mc;
    }

    /**
     * Add some standard overall feedback to a question. You need to use these
     * specific feedback strings for the corresponding contains_..._feedback
     * methods in {@link qbehaviour_walkthrough_test_base} to works.
     * @param question_definition $q the question to add the feedback to.
     */
    public static function set_standard_combined_feedback_fields($q) {
        $q->correctfeedback = self::STANDARD_OVERALL_CORRECT_FEEDBACK;
        $q->correctfeedbackformat = FORMAT_HTML;
        $q->partiallycorrectfeedback = self::STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK;
        $q->partiallycorrectfeedbackformat = FORMAT_HTML;
        $q->shownumcorrect = true;
        $q->incorrectfeedback = self::STANDARD_OVERALL_INCORRECT_FEEDBACK;
        $q->incorrectfeedbackformat = FORMAT_HTML;
    }

    /**
     * Get the question data, as it would be loaded by get_question_options.
     * @return object
     */
    public static function get_cmultichoice_question_data_two_of_four() {
        global $USER;

        $qdata = new stdClass();

        $qdata->createdby = $USER->id;
        $qdata->modifiedby = $USER->id;
        $qdata->qtype = 'cmultichoice';
        $qdata->name = 'Multiple choice question';
        $qdata->questiontext = 'Which are the odd numbers?';
        $qdata->questiontextformat = FORMAT_HTML;
        $qdata->generalfeedback = 'The odd numbers are One and Three.';
        $qdata->generalfeedbackformat = FORMAT_HTML;
        $qdata->defaultmark = 1;
        $qdata->length = 1;
        $qdata->penalty = 0.3333333;
        $qdata->hidden = 0;

        $qdata->options = new stdClass();
        $qdata->options->shuffleanswers = 1;
        $qdata->options->answernumbering = '123';
        $qdata->options->layout = 0;
        $qdata->options->single = 0;
        $qdata->options->correctfeedback = test_question_maker::STANDARD_OVERALL_CORRECT_FEEDBACK;
        $qdata->options->correctfeedbackformat = FORMAT_HTML;
        $qdata->options->partiallycorrectfeedback = test_question_maker::STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK;
        $qdata->options->partiallycorrectfeedbackformat = FORMAT_HTML;
        $qdata->options->shownumcorrect = 1;
        $qdata->options->incorrectfeedback = test_question_maker::STANDARD_OVERALL_INCORRECT_FEEDBACK;
        $qdata->options->incorrectfeedbackformat = FORMAT_HTML;

        $qdata->options->answers = array(
            13 => (object) array(
                'id' => 13,
                'answer' => 'One',
                'answerformat' => FORMAT_PLAIN,
                'fraction' => '0.5',
                'feedback' => 'One is odd.',
                'feedbackformat' => FORMAT_HTML,
            ),
            14 => (object) array(
                'id' => 14,
                'answer' => 'Two',
                'answerformat' => FORMAT_PLAIN,
                'fraction' => '0.0',
                'feedback' => 'Two is even.',
                'feedbackformat' => FORMAT_HTML,
            ),
            15 => (object) array(
                'id' => 15,
                'answer' => 'Three',
                'answerformat' => FORMAT_PLAIN,
                'fraction' => '0.5',
                'feedback' => 'Three is odd.',
                'feedbackformat' => FORMAT_HTML,
            ),
            16 => (object) array(
                'id' => 16,
                'answer' => 'Four',
                'answerformat' => FORMAT_PLAIN,
                'fraction' => '0.0',
                'feedback' => 'Four is even.',
                'feedbackformat' => FORMAT_HTML,
            ),
        );

        $qdata->hints = array(
            1 => (object) array(
                'hint' => 'Hint 1.',
                'hintformat' => FORMAT_HTML,
                'shownumcorrect' => 1,
                'clearwrong' => 0,
                'options' => 0,
            ),
            2 => (object) array(
                'hint' => 'Hint 2.',
                'hintformat' => FORMAT_HTML,
                'shownumcorrect' => 1,
                'clearwrong' => 1,
                'options' => 1,
            ),
        );

        return $qdata;
    }

    /**
     * Get the question data, as it would be loaded by get_question_options.
     * @return object
     */
    public static function get_cmultichoice_question_form_data_two_of_four() {
        $qdata = new stdClass();

        $qdata->name = 'multiple choice question';
        $qdata->questiontext = array('text' => 'Which are the odd numbers?', 'format' => FORMAT_HTML);
        $qdata->generalfeedback = array('text' => 'The odd numbers are One and Three.', 'format' => FORMAT_HTML);
        $qdata->defaultmark = 1;
        $qdata->noanswers = 5;
        $qdata->numhints = 2;
        $qdata->penalty = 0.3333333;

        $qdata->shuffleanswers = 1;
        $qdata->answernumbering = '123';
        $qdata->single = '0';
        $qdata->correctfeedback = array('text' => test_question_maker::STANDARD_OVERALL_CORRECT_FEEDBACK,
            'format' => FORMAT_HTML);
        $qdata->partiallycorrectfeedback = array('text' => test_question_maker::STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK,
            'format' => FORMAT_HTML);
        $qdata->shownumcorrect = 1;
        $qdata->incorrectfeedback = array('text' => test_question_maker::STANDARD_OVERALL_INCORRECT_FEEDBACK,
            'format' => FORMAT_HTML);
        $qdata->fraction = array('0.5', '0.0', '0.5', '0.0', '0.0');
        $qdata->answer = array(
            0 => array(
                'text' => 'One',
                'format' => FORMAT_PLAIN
            ),
            1 => array(
                'text' => 'Two',
                'format' => FORMAT_PLAIN
            ),
            2 => array(
                'text' => 'Three',
                'format' => FORMAT_PLAIN
            ),
            3 => array(
                'text' => 'Four',
                'format' => FORMAT_PLAIN
            ),
            4 => array(
                'text' => '',
                'format' => FORMAT_PLAIN
            )
        );

        $qdata->feedback = array(
            0 => array(
                'text' => 'One is odd.',
                'format' => FORMAT_HTML
            ),
            1 => array(
                'text' => 'Two is even.',
                'format' => FORMAT_HTML
            ),
            2 => array(
                'text' => 'Three is odd.',
                'format' => FORMAT_HTML
            ),
            3 => array(
                'text' => 'Four is even.',
                'format' => FORMAT_HTML
            ),
            4 => array(
                'text' => '',
                'format' => FORMAT_HTML
            )
        );

        $qdata->hint = array(
            0 => array(
                'text' => 'Hint 1.',
                'format' => FORMAT_HTML
            ),
            1 => array(
                'text' => 'Hint 2.',
                'format' => FORMAT_HTML
            )
        );
        $qdata->hintclearwrong = array(0, 1);
        $qdata->hintshownumcorrect = array(1, 1);

        return $qdata;
    }

    /**
     * Get the question data, as it would be loaded by get_question_options.
     * @return object
     */
    public static function get_cmultichoice_question_data_one_of_four() {
        global $USER;

        $qdata = new stdClass();

        $qdata->createdby = $USER->id;
        $qdata->modifiedby = $USER->id;
        $qdata->qtype = 'cmultichoice';
        $qdata->name = 'Multiple choice question';
        $qdata->questiontext = 'Which is the oddest number?';
        $qdata->questiontextformat = FORMAT_HTML;
        $qdata->generalfeedback = 'The oddest number is One.'; // Arguable possibly but it is a quick way to make a variation on
        //this question with one correct answer.
        $qdata->generalfeedbackformat = FORMAT_HTML;
        $qdata->defaultmark = 1;
        $qdata->length = 1;
        $qdata->penalty = 0.3333333;
        $qdata->hidden = 0;

        $qdata->options = new stdClass();
        $qdata->options->shuffleanswers = 1;
        $qdata->options->answernumbering = '123';
        $qdata->options->layout = 0;
        $qdata->options->single = 1;
        $qdata->options->correctfeedback = test_question_maker::STANDARD_OVERALL_CORRECT_FEEDBACK;
        $qdata->options->correctfeedbackformat = FORMAT_HTML;
        $qdata->options->partiallycorrectfeedback = test_question_maker::STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK;
        $qdata->options->partiallycorrectfeedbackformat = FORMAT_HTML;
        $qdata->options->shownumcorrect = 1;
        $qdata->options->incorrectfeedback = test_question_maker::STANDARD_OVERALL_INCORRECT_FEEDBACK;
        $qdata->options->incorrectfeedbackformat = FORMAT_HTML;

        $qdata->options->answers = array(
            13 => (object) array(
                'id' => 13,
                'answer' => 'One',
                'answerformat' => FORMAT_PLAIN,
                'fraction' => '1',
                'feedback' => 'One is the oddest.',
                'feedbackformat' => FORMAT_HTML,
            ),
            14 => (object) array(
                'id' => 14,
                'answer' => 'Two',
                'answerformat' => FORMAT_PLAIN,
                'fraction' => '0.0',
                'feedback' => 'Two is even.',
                'feedbackformat' => FORMAT_HTML,
            ),
            15 => (object) array(
                'id' => 15,
                'answer' => 'Three',
                'answerformat' => FORMAT_PLAIN,
                'fraction' => '0',
                'feedback' => 'Three is odd.',
                'feedbackformat' => FORMAT_HTML,
            ),
            16 => (object) array(
                'id' => 16,
                'answer' => 'Four',
                'answerformat' => FORMAT_PLAIN,
                'fraction' => '0.0',
                'feedback' => 'Four is even.',
                'feedbackformat' => FORMAT_HTML,
            ),
        );

        $qdata->hints = array(
            1 => (object) array(
                'hint' => 'Hint 1.',
                'hintformat' => FORMAT_HTML,
                'shownumcorrect' => 1,
                'clearwrong' => 0,
                'options' => 0,
            ),
            2 => (object) array(
                'hint' => 'Hint 2.',
                'hintformat' => FORMAT_HTML,
                'shownumcorrect' => 1,
                'clearwrong' => 1,
                'options' => 1,
            ),
        );

        return $qdata;
    }

    /**
     * Get the question data, as it would be loaded by get_question_options.
     * @return object
     */
    public static function get_cmultichoice_question_form_data_one_of_four() {
        $qdata = new stdClass();

        $qdata->name = 'multiple choice question';
        $qdata->questiontext = array('text' => 'Which is the oddest number?', 'format' => FORMAT_HTML);
        $qdata->generalfeedback = array('text' => 'The oddest number is One.', 'format' => FORMAT_HTML);
        $qdata->defaultmark = 1;
        $qdata->noanswers = 5;
        $qdata->numhints = 2;
        $qdata->penalty = 0.3333333;

        $qdata->shuffleanswers = 1;
        $qdata->answernumbering = '123';
        $qdata->single = '1';
        $qdata->correctfeedback = array('text' => test_question_maker::STANDARD_OVERALL_CORRECT_FEEDBACK,
            'format' => FORMAT_HTML);
        $qdata->partiallycorrectfeedback = array('text' => test_question_maker::STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK,
            'format' => FORMAT_HTML);
        $qdata->shownumcorrect = 1;
        $qdata->incorrectfeedback = array('text' => test_question_maker::STANDARD_OVERALL_INCORRECT_FEEDBACK,
            'format' => FORMAT_HTML);
        $qdata->fraction = array('1.0', '0.0', '0.0', '0.0', '0.0');
        $qdata->answer = array(
            0 => array(
                'text' => 'One',
                'format' => FORMAT_PLAIN
            ),
            1 => array(
                'text' => 'Two',
                'format' => FORMAT_PLAIN
            ),
            2 => array(
                'text' => 'Three',
                'format' => FORMAT_PLAIN
            ),
            3 => array(
                'text' => 'Four',
                'format' => FORMAT_PLAIN
            ),
            4 => array(
                'text' => '',
                'format' => FORMAT_PLAIN
            )
        );

        $qdata->feedback = array(
            0 => array(
                'text' => 'One is the oddest.',
                'format' => FORMAT_HTML
            ),
            1 => array(
                'text' => 'Two is even.',
                'format' => FORMAT_HTML
            ),
            2 => array(
                'text' => 'Three is odd.',
                'format' => FORMAT_HTML
            ),
            3 => array(
                'text' => 'Four is even.',
                'format' => FORMAT_HTML
            ),
            4 => array(
                'text' => '',
                'format' => FORMAT_HTML
            )
        );

        $qdata->hint = array(
            0 => array(
                'text' => 'Hint 1.',
                'format' => FORMAT_HTML
            ),
            1 => array(
                'text' => 'Hint 2.',
                'format' => FORMAT_HTML
            )
        );
        $qdata->hintclearwrong = array(0, 1);
        $qdata->hintshownumcorrect = array(1, 1);

        return $qdata;
    }

}

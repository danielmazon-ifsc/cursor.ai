<?php

/**
 * Multiple choice question renderer classes.
 *
 * @package    qtype
 * @subpackage cmultichoice
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Base class for generating the bits of output common to multiple choice
 * single and multiple questions.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
abstract class qtype_cmultichoice_renderer_base extends qtype_with_combined_feedback_renderer {

    protected abstract function get_input_type();

    protected abstract function get_input_name(question_attempt $qa, $value);

    protected abstract function get_input_value($value);

    protected abstract function get_input_id(question_attempt $qa, $value);

    /**
     * Whether a choice should be considered right, wrong or partially right.
     * @param question_answer $ans representing one of the choices.
     * @return fload 1.0, 0.0 or something in between, respectively.
     */
    protected abstract function is_right(question_answer $ans);

    protected abstract function prompt();

    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {

        $question = $qa->get_question();
        $response = $question->get_response($qa);

        $inputname = $qa->get_qt_field_name('answer');
        $inputattributes = array(
            'type' => $this->get_input_type(),
            'name' => $inputname,
        );

        if ($options->readonly) {
            $inputattributes['disabled'] = 'disabled';
        }

        $radiobuttons = array();
        $feedbackimg = array();
        $feedback = array();
        $classes = array();
        foreach ($question->get_order($qa) as $value => $ansid) {
            $ans = $question->answers[$ansid];
            $inputattributes['name'] = $this->get_input_name($qa, $value);
            $inputattributes['value'] = $this->get_input_value($value);
            $inputattributes['id'] = $this->get_input_id($qa, $value);
            $isselected = $question->is_choice_selected($response, $value);
            if ($isselected) {
                $inputattributes['checked'] = 'checked';
            } else {
                unset($inputattributes['checked']);
            }
            $hidden = '';
            if (!$options->readonly && $this->get_input_type() == 'checkbox') {
                $hidden = html_writer::empty_tag('input', array(
                            'type' => 'hidden',
                            'name' => $inputattributes['name'],
                            'value' => 0,
                ));
            }
            $radiobuttons[] = $hidden . html_writer::empty_tag('input', $inputattributes) .
                    html_writer::tag('label', $this->number_in_style($value, $question->answernumbering) .
                            $question->make_html_inline($question->format_text(
                                            $ans->answer, $ans->answerformat, $qa, 'question', 'answer', $ansid)), array('for' => $inputattributes['id']));

            // Param $options->suppresschoicefeedback is a hack specific to the
            // oumultiresponse question type. It would be good to refactor to
            // avoid refering to it here.
            if ($options->feedback && empty($options->suppresschoicefeedback) &&
                    $isselected && trim($ans->feedback)) {
                $feedback[] = html_writer::tag('div', $question->make_html_inline($question->format_text(
                                                $ans->feedback, $ans->feedbackformat, $qa, 'question', 'answerfeedback', $ansid)), array('class' => 'specificfeedback'));
            } else {
                $feedback[] = '';
            }
            $class = 'r' . ($value % 2);
            if ($options->correctness && $isselected) {
                $feedbackimg[] = $this->feedback_image($this->is_right($ans));
                $class .= ' ' . $this->feedback_class($this->is_right($ans));
            } else {
                $feedbackimg[] = '';
            }
            $classes[] = $class;
        }

        $result = '';
        $result .= html_writer::tag('div', $question->format_questiontext($qa), array('class' => 'qtext'));

        $result .= html_writer::start_tag('div', array('class' => 'ablock'));
        $result .= html_writer::tag('div', $this->prompt(), array('class' => 'prompt'));

        $result .= html_writer::start_tag('div', array('class' => 'answer'));
        foreach ($radiobuttons as $key => $radio) {
            $result .= html_writer::tag('div', $radio . ' ' . $feedbackimg[$key] . $feedback[$key], array('class' => $classes[$key])) . "\n";
        }
        $result .= html_writer::end_tag('div'); // Answer.

        $result .= html_writer::end_tag('div'); // Ablock.

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div', $question->get_validation_error($qa->get_last_qt_data()), array('class' => 'validationerror'));
        }

        return $result;
    }

    protected function number_html($qnum) {
        return $qnum . '. ';
    }

    /**
     * @param int $num The number, starting at 0.
     * @param string $style The style to render the number in. One of the
     * options returned by {@link qtype_cmultichoice:;get_numbering_styles()}.
     * @return string the number $num in the requested style.
     */
    protected function number_in_style($num, $style) {
        switch ($style) {
            case 'abc':
                $number = chr(ord('a') + $num);
                break;
            case 'ABCD':
                $number = chr(ord('A') + $num);
                break;
            case '123':
                $number = $num + 1;
                break;
            case 'iii':
                $number = question_utils::int_to_roman($num + 1);
                break;
            case 'IIII':
                $number = strtoupper(question_utils::int_to_roman($num + 1));
                break;
            case 'none':
                return '';
            default:
                return 'ERR';
        }
        return $this->number_html($number);
    }

    public function specific_feedback(question_attempt $qa) {
        return $this->combined_feedback($qa);
    }

    /**
     * Generate the display of the outcome part of the question. This is the
     * area that contains the various forms of feedback. This function generates
     * the content of this area belonging to the question type.
     *
     * Subclasses will normally want to override the more specific methods
     * {specific_feedback()}, {general_feedback()} and {correct_response()}
     * that this method calls.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function feedback(question_attempt $qa, question_display_options $options) {
        $html = '';
        $state = $qa->get_state();
        $correct = (($state->is_incorrect() || $state->is_gave_up()) ? 0 : 1);
        $html .= html_writer::start_div('ml-sm-32pt card p-3', array(
                    'style' => 'background-color: rgba(119, 193, 58, .05);">'
        ));
        $html .= html_writer::start_div('d-flex');
        if ($correct) {
            $colorclass = 'text-success';
            $bordercolorclass = 'border-success';
        } else {
            $colorclass = 'text-danger';
            $bordercolorclass = 'border-danger';
        }
        $html .= html_writer::start_tag('span', array(
                    'class' => $colorclass . ' ' . $bordercolorclass . ' icon-holder icon-holder--outline-secondary rounded-circle d-inline-flex mb-8pt mr-12pt',
                    'style' => 'width: 42px; height: 42px;'
        ));
        if ($correct) {
            $icon = 'fa-check';
        } else {
            $icon = 'fa-close';
        }

        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa ' . $icon,
                    'style' => 'margin-left: 10px'
        ));
        $html .= html_writer::end_tag('i');

        $html .= html_writer::end_tag('span');
        $html .= html_writer::start_div('flex');
        $html .= html_writer::start_div('d-flex align-items-center');
        $html .= html_writer::start_tag('span', array(
                    'class' => 'text-body'
        ));
        if ($correct) {
            $headertext = get_string('rightanswer', 'qtype_cmultichoice');
        } else {
            $headertext = get_string('wronganswer', 'qtype_cmultichoice');
        }
        $html .= html_writer::tag('strong', $headertext);
        $html .= html_writer::end_tag('span');
        $html .= html_writer::end_div();
        if ($options->numpartscorrect) {
            $html .= html_writer::nonempty_tag('div', strip_tags($this->num_parts_correct($qa)), array('class' => 'numpartscorrect'));
        }
        $hint = $qa->get_applicable_hint();
        if ($hint) {
            $html .= strip_tags($this->hint($qa, $hint));
        }
        if ($options->generalfeedback) {
            $html .= html_writer::nonempty_tag('div', strip_tags($this->general_feedback($qa)), array('class' => 'generalfeedback'));
        }
        $html .= html_writer::nonempty_tag('div', strip_tags($this->correct_response($qa)), array('class' => 'rightanswer'));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        return $html;



        $output = '';
        $hint = null;

        if ($options->feedback) {
            // Set class accordingly
            $state = $qa->get_state();
            $class = 'specificfeedback' . (($state->is_incorrect() || $state->is_gave_up()) ? ' incorrect' : ' correct');
            $feedback = $this->specific_feedback($qa);
            if ($qa->get_fraction() > 0 && $qa->get_fraction() < 1) {
                $feedback .= get_string('downgraded', 'qtype_cmultichoice');
            }
            $output .= html_writer::nonempty_tag('div', $feedback, array('class' => $class));
            $hint = $qa->get_applicable_hint();
        }

        if ($options->numpartscorrect) {
            $output .= html_writer::nonempty_tag('div', $this->num_parts_correct($qa), array('class' => 'numpartscorrect'));
        }

        if ($hint) {
            $output .= $this->hint($qa, $hint);
        }

        if ($options->generalfeedback) {
            $output .= html_writer::nonempty_tag('div', $this->general_feedback($qa), array('class' => 'generalfeedback'));
        }

        if ($options->rightanswer) {
            $output .= html_writer::nonempty_tag('div', $this->correct_response($qa), array('class' => 'rightanswer'));
        }

        return $output;
    }

}

/**
 * Subclass for generating the bits of output specific to multiple choice
 * single questions.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class qtype_cmultichoice_single_renderer extends qtype_cmultichoice_renderer_base {

    protected function get_input_type() {
        return 'radio';
    }

    protected function get_input_name(question_attempt $qa, $value) {
        return $qa->get_qt_field_name('answer');
    }

    protected function get_input_value($value) {
        return $value;
    }

    protected function get_input_id(question_attempt $qa, $value) {
        return $qa->get_qt_field_name('answer' . $value);
    }

    protected function is_right(question_answer $ans) {
        return $ans->fraction;
    }

    protected function prompt() {
        return get_string('selectone', 'qtype_cmultichoice');
    }

    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();

        foreach ($question->answers as $ansid => $ans) {
            if (question_state::graded_state_for_fraction($ans->fraction) == question_state::$gradedright) {
                return get_string('correctansweris', 'qtype_cmultichoice', $question->make_html_inline($question->format_text($ans->answer, $ans->answerformat, $qa, 'question', 'answer', $ansid)));
            }
        }

        return '';
    }

}

/**
 * Subclass for generating the bits of output specific to multiple choice
 * multi=select questions.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class qtype_cmultichoice_multi_renderer extends qtype_cmultichoice_renderer_base {

    protected function get_input_type() {
        return 'checkbox';
    }

    protected function get_input_name(question_attempt $qa, $value) {
        return $qa->get_qt_field_name('choice' . $value);
    }

    protected function get_input_value($value) {
        return 1;
    }

    protected function get_input_id(question_attempt $qa, $value) {
        return $this->get_input_name($qa, $value);
    }

    protected function is_right(question_answer $ans) {
        if ($ans->fraction > 0) {
            return 1;
        } else {
            return 0;
        }
    }

    protected function prompt() {
        return get_string('selectmulti', 'qtype_cmultichoice');
    }

    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();

        $right = array();
        foreach ($question->answers as $ansid => $ans) {
            if ($ans->fraction > 0) {
                $right[] = $question->make_html_inline($question->format_text($ans->answer, $ans->answerformat, $qa, 'question', 'answer', $ansid));
            }
        }

        if (!empty($right)) {
            return get_string('correctansweris', 'qtype_cmultichoice', implode(', ', $right));
        }
        return '';
    }

    protected function num_parts_correct(question_attempt $qa) {
        if ($qa->get_question()->get_num_selected_choices($qa->get_last_qt_data()) >
                $qa->get_question()->get_num_correct_choices()) {
            return get_string('toomanyselected', 'qtype_cmultichoice');
        }

        return parent::num_parts_correct($qa);
    }

}

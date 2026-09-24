<?php

/**
 * The questiontype class for the multiple choice question type.
 *
 * @package    qtype
 * @subpackage cmultichoice
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');

/**
 * The multiple choice question type.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class qtype_cmultichoice extends question_type {

    public function get_question_options($question) {
        global $DB;

        $question->options = $DB->get_record('qtype_cmultichoice_options', array('questionid' => $question->id), '*', MUST_EXIST);

        // Retrieve question competencies and set $question->competencies array
        $competencies = $DB->get_records('qtype_cmultichoice_compts', array('questionid' => $question->id, 'answerid' => null));
        if ($competencies) {
            $question->competencies = array();
            foreach ($competencies as $competency) {
                $question->competencies[] = $competency->competencyid;
            }
        }

        parent::get_question_options($question);
    }

    public function save_question_options($question) {
        global $DB;
        $context = $question->context;
        $result = new stdClass();

        $oldanswers = $DB->get_records('question_answers', array('question' => $question->id), 'id ASC');

        // We need separate arrays for answers and extra answer data, so no JOINS there.
        $extraanswerfields = $this->extra_answer_fields();
        $isextraanswerfields = is_array($extraanswerfields);
        $extraanswertable = '';
        $oldanswerextras = array();
        if ($isextraanswerfields) {
            $extraanswertable = array_shift($extraanswerfields);
            if (!empty($oldanswers)) {
                $oldanswerextras = $DB->get_records_sql("SELECT * FROM {{$extraanswertable}} WHERE " .
                        'answerid IN (SELECT id FROM {question_answers} WHERE question = ' . $question->id . ')');
            }
        }

        // Following hack to check at least two answers exist.
        $answercount = 0;
        foreach ($question->answer as $key => $answer) {
            if ($answer != '') {
                $answercount++;
            }
        }
        if ($answercount < 2) { // Check there are at lest 2 answers for multiple choice.
            $result->notice = get_string('notenoughanswers', 'qtype_cmultichoice', '2');
            return $result;
        }

        // Insert all the new answers.
        $totalfraction = 0;
        $maxfraction = -1;
        foreach ($question->answer as $key => $answerdata) {
            if (trim($answerdata['text']) == '') {
                continue;
            }

            // Update an existing answer if possible.
            $answer = array_shift($oldanswers);
            if (!$answer) {
                $answer = new stdClass();
                $answer->question = $question->id;
                $answer->answer = '';
                $answer->feedback = '';
                $answer->id = $DB->insert_record('question_answers', $answer);
            }

            // Doing an import.
            $answer->answer = $this->import_or_save_files($answerdata, $context, 'question', 'answer', $answer->id);
            $answer->answerformat = $answerdata['format'];
            $answer->fraction = $question->fraction[$key];
            $answer->feedback = $this->import_or_save_files($question->feedback[$key], $context, 'question', 'answerfeedback', $answer->id);
            $answer->feedbackformat = $question->feedback[$key]['format'];

            $DB->update_record('question_answers', $answer);

            if ($question->fraction[$key] > 0) {
                $totalfraction += $question->fraction[$key];
            }
            if ($question->fraction[$key] > $maxfraction) {
                $maxfraction = $question->fraction[$key];
            }

            if ($isextraanswerfields) {
                // Check, if this answer contains some extra field data.
                if ($this->is_extra_answer_fields_empty($question, $key)) {
                    continue;
                }
                $answerextra = array_shift($oldanswerextras);
                if (!$answerextra) {
                    $answerextra = new stdClass();
                    $answerextra->answerid = $answer->id;
                    // Avoid looking for correct default for any possible DB field type
                    // by setting real values.
                    $answerextra = $this->fill_extra_answer_fields($answerextra, $question, $key, $context, $extraanswerfields);
                    $answerextra->questionid = $question->id;
                    $answerextra->id = $DB->insert_record($extraanswertable, $answerextra);
                } else {
                    // Update answerid, as record may be reused from another answer.
                    $answerextra->answerid = $answer->id;
                    $answerextra = $this->fill_extra_answer_fields($answerextra, $question, $key, $context, $extraanswerfields);
                    $DB->update_record($extraanswertable, $answerextra);
                }
            }
        }

        // Delete any left over old answer records.
        $fs = get_file_storage();
        foreach ($oldanswers as $oldanswer) {
            $fs->delete_area_files($context->id, 'question', 'answerfeedback', $oldanswer->id);
            $DB->delete_records('question_answers', array('id' => $oldanswer->id));
        }

        $options = $DB->get_record('qtype_cmultichoice_options', array('questionid' => $question->id));
        if (!$options) {
            $options = new stdClass();
            $options->questionid = $question->id;
            $options->correctfeedback = '';
            $options->partiallycorrectfeedback = '';
            $options->incorrectfeedback = '';
            $options->id = $DB->insert_record('qtype_cmultichoice_options', $options);
        }

        $options->single = $question->single;
        if (isset($question->layout)) {
            $options->layout = $question->layout;
        }
        $options->answernumbering = $question->answernumbering;
        $options->shuffleanswers = $question->shuffleanswers;
        $options = $this->save_combined_feedback_helper($options, $question, $context, true);
        $DB->update_record('qtype_cmultichoice_options', $options);

        // Save competencies associated with this question
        // Remove existing records first
        $DB->delete_records('qtype_cmultichoice_compts', array('questionid' => $question->id, 'answerid' => null));
        if (isset($question->competencies)) {
            foreach ($question->competencies as $competency) {
                $record = new stdClass();
                $record->questionid = $question->id;
                $record->competencyid = $competency;
                $record->id = $DB->insert_record('qtype_cmultichoice_compts', $record);
            }
        }

        $this->save_hints($question, true);

        // Perform sanity checks on fractional grades.
        if ($options->single) {
            if ($maxfraction != 1) {
                $result->noticeyesno = get_string('fractionsnomax', 'qtype_cmultichoice', $maxfraction * 100);
                return $result;
            }
        } else {
            $totalfraction = round($totalfraction, 2);
            if ($totalfraction != 1) {
                $result->noticeyesno = get_string('fractionsaddwrong', 'qtype_cmultichoice', $totalfraction * 100);
                return $result;
            }
        }
    }

    protected function make_question_instance($questiondata) {
        question_bank::load_question_definition_classes($this->name());
        if ($questiondata->options->single) {
            $class = 'qtype_cmultichoice_single_question';
        } else {
            $class = 'qtype_cmultichoice_multi_question';
        }
        return new $class();
    }

    protected function make_hint($hint) {
        return question_hint_with_parts::load_from_record($hint);
    }

    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $question->shuffleanswers = $questiondata->options->shuffleanswers;
        $question->answernumbering = $questiondata->options->answernumbering;
        if (!empty($questiondata->options->layout)) {
            $question->layout = $questiondata->options->layout;
        } else {
            $question->layout = qtype_cmultichoice_single_question::LAYOUT_VERTICAL;
        }
        $this->initialise_combined_feedback($question, $questiondata, true);

        $this->initialise_question_answers($question, $questiondata, false);
    }

    public function make_answer($answer) {
        // Overridden just so we can make it public for use by question.php.
        return parent::make_answer($answer);
    }

    public function delete_question($questionid, $contextid) {
        global $DB;

        $DB->delete_records('qtype_cmultichoice_options', array('questionid' => $questionid));

        // Delete question associated competencies
        $DB->delete_records('qtype_cmultichoice_compts', array('questionid' => $questionid));

        parent::delete_question($questionid, $contextid);
    }

    public function get_random_guess_score($questiondata) {
        if (!$questiondata->options->single) {
            // Pretty much impossible to compute for _multi questions. Don't try.
            return null;
        }

        // Single choice questions - average choice fraction.
        $totalfraction = 0;
        foreach ($questiondata->options->answers as $answer) {
            $totalfraction += $answer->fraction;
        }
        return $totalfraction / count($questiondata->options->answers);
    }

    public function get_possible_responses($questiondata) {
        if ($questiondata->options->single) {
            $responses = array();

            foreach ($questiondata->options->answers as $aid => $answer) {
                $responses[$aid] = new question_possible_response(
                        question_utils::to_plain_text($answer->answer, $answer->answerformat), $answer->fraction);
            }

            $responses[null] = question_possible_response::no_response();
            return array($questiondata->id => $responses);
        } else {
            $parts = array();

            foreach ($questiondata->options->answers as $aid => $answer) {
                $parts[$aid] = array($aid => new question_possible_response(
                            question_utils::to_plain_text($answer->answer, $answer->answerformat), $answer->fraction));
            }

            return $parts;
        }
    }

    /**
     * @return array of the numbering styles supported. For each one, there
     *      should be a lang string answernumberingxxx in teh qtype_cmultichoice
     *      language file, and a case in the switch statement in number_in_style,
     *      and it should be listed in the definition of this column in install.xml.
     */
    public static function get_numbering_styles() {
        $styles = array();
        foreach (array('abc', 'ABCD', '123', 'iii', 'IIII', 'none') as $numberingoption) {
            $styles[$numberingoption] = get_string('answernumbering' . $numberingoption, 'qtype_cmultichoice');
        }
        return $styles;
    }

    public function move_files($questionid, $oldcontextid, $newcontextid) {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_answers($questionid, $oldcontextid, $newcontextid, true);
        $this->move_files_in_combined_feedback($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    protected function delete_files($questionid, $contextid) {
        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_answers($questionid, $contextid, true);
        $this->delete_files_in_combined_feedback($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);
    }

    /**
     * Export question to the Moodle XML format
     *
     * Export question using information from extra_question_fields function
     * If some of you fields contains id's you'll need to reimplement this
     */
    public function export_to_xml($question, qformat_xml $format, $extra = null) {
        $expout = '';
        $expout .= "    <single>" . $this->get_single($question->options->single) .
                "</single>\n";
        $expout .= "    <shuffleanswers>" .
                $this->get_single($question->options->shuffleanswers) .
                "</shuffleanswers>\n";
        $expout .= "    <answernumbering>" . $question->options->answernumbering .
                "</answernumbering>\n";
        $expout .= $this->write_combined_feedback($question->options, $question->id, $question->contextid);
        $expout .= $this->write_answers($question->options->answers);
        $expout .= $this->write_competencies($question->competencies);
        return $expout;
    }

    /**
     * Write question competencies in XML format:
     *       <competency>Competency 1</competency>
     *       <competency>Competency 2</competency>
     *
     * @param array $competencies Question competencies
     * @return string Question competencies in XML format
     */
    protected function write_competencies($competencies) {
        $output = "";
        foreach ($competencies as $competency) {
            $record = $this->get_competency($competency);
            if ($record) {
                $output .= "      <competency>";
                $output .= trim($record->shortname);
                $output .= "</competency>\n";
            }
        }
        return $output;
    }

    /**
     * Retrieve a competency
     * 
     * @param int $competencyid Competency id
     * @return object Competency or null if competency cannot be found
     */
    protected function get_competency($competencyid) {
        global $DB;

        $competency = $DB->get_record('competency', array('id' => $competencyid));
        return $competency ? $competency : null;
    }

    /**
     * Convert internal single question code into
     * human readable form
     * @param int id single question code
     * @return string single question string
     */
    protected function get_single($id) {
        switch ($id) {
            case 0:
                return 'false';
            case 1:
                return 'true';
            default:
                return 'unknown';
        }
    }

    /**
     * Output the combined feedback fields.
     * @param object $questionoptions the question definition data.
     * @param int $questionid the question id.
     * @param int $contextid the question context id.
     * @return string XML to output.
     */
    protected function write_combined_feedback($questionoptions, $questionid, $contextid) {
        $fs = get_file_storage();
        $output = '';

        $fields = array('correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback');
        foreach ($fields as $field) {
            $formatfield = $field . 'format';
            $files = $fs->get_area_files($contextid, 'question', $field, $questionid);

            $output .= "    <{$field} {$this->format($questionoptions->$formatfield)}>\n";
            $output .= '      ' . $this->writetext($questionoptions->$field);
            $output .= $this->write_files($files);
            $output .= "    </{$field}>\n";
        }

        if (!empty($questionoptions->shownumcorrect)) {
            $output .= "    <shownumcorrect/>\n";
        }
        return $output;
    }

    /**
     * @param int $format a FORMAT_... constant.
     * @return string the attribute to add to an XML tag.
     */
    protected function format($format) {
        return 'format="' . $this->get_format($format) . '"';
    }

    protected function write_answers($answers) {
        if (empty($answers)) {
            return;
        }
        $output = '';
        foreach ($answers as $answer) {
            $output .= $this->write_answer($answer);
        }
        return $output;
    }

    protected function write_answer($answer, $extra = '') {
        $percent = $answer->fraction * 100;
        $output = '';
        $output .= "    <answer fraction=\"{$percent}\" {$this->format($answer->answerformat)}>\n";
        $output .= $this->writetext($answer->answer, 3);
        $output .= $this->write_files($answer->answerfiles);
        $output .= "      <feedback {$this->format($answer->feedbackformat)}>\n";
        $output .= $this->writetext($answer->feedback, 4);
        $output .= $this->write_files($answer->feedbackfiles);
        $output .= "      </feedback>\n";
        $output .= $extra;
        $output .= "    </answer>\n";
        return $output;
    }

    /**
     * Convert internal Moodle text format code into
     * human readable form
     * @param int id internal code
     * @return string format text
     */
    protected function get_format($id) {
        switch ($id) {
            case FORMAT_MOODLE:
                return 'moodle_auto_format';
            case FORMAT_HTML:
                return 'html';
            case FORMAT_PLAIN:
                return 'plain_text';
            case FORMAT_WIKI:
                return 'wiki_like';
            case FORMAT_MARKDOWN:
                return 'markdown';
            default:
                return 'unknown';
        }
    }

    /**
     * Generates <text></text> tags, processing raw text therein
     * @param string $raw the content to output.
     * @param int $indent the current indent level.
     * @param bool $short stick it on one line.
     * @return string formatted text.
     */
    protected function writetext($raw, $indent = 0, $short = true) {
        $indent = str_repeat('  ', $indent);
        $raw = $this->xml_escape($raw);

        if ($short) {
            $xml = "{$indent}<text>{$raw}</text>\n";
        } else {
            $xml = "{$indent}<text>\n{$raw}\n{$indent}</text>\n";
        }

        return $xml;
    }

    /**
     * Take a string, and wrap it in a CDATA secion, if that is required to make
     * the output XML valid.
     * @param string $string a string
     * @return string the string, wrapped in CDATA if necessary.
     */
    protected function xml_escape($string) {
        if (!empty($string) && htmlspecialchars($string) != $string) {
            // If the string contains something that looks like the end
            // of a CDATA section, then we need to avoid errors by splitting
            // the string between two CDATA sections.
            $string = str_replace(']]>', ']]]]><![CDATA[>', $string);
            return "<![CDATA[{$string}]]>";
        } else {
            return $string;
        }
    }

    /**
     * Generte the XML to represent some files.
     * @param array of store array of stored_file objects.
     * @return string $string the XML.
     */
    protected function write_files($files) {
        if (empty($files)) {
            return '';
        }
        $string = '';
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $string .= '<file name="' . $file->get_filename() . '" path="' . $file->get_filepath() . '" encoding="base64">';
            $string .= base64_encode($file->get_content());
            $string .= "</file>\n";
        }
        return $string;
    }

    /**
     * Imports question from the Moodle XML format
     *
     * Imports question using information from extra_question_fields function
     * If some of you fields contains id's you'll need to reimplement this
     */
    public function import_from_xml($data, $question, qformat_xml $format, $extra = null) {
        // Get common parts.
        $qo = $this->import_headers($data);

        // Header parts particular to multichoice.
        $qo->qtype = 'cmultichoice';
        $single = $this->getpath($data, array('#', 'single', 0, '#'), 'true');
        $qo->single = $this->trans_single($single);
        $shuffleanswers = $this->getpath($data, array('#', 'shuffleanswers', 0, '#'), 'false');
        $qo->answernumbering = $this->getpath($data, array('#', 'answernumbering', 0, '#'), 'abc');
        $qo->shuffleanswers = $this->trans_single($shuffleanswers);

        // There was a time on the 1.8 branch when it could output an empty
        // answernumbering tag, so fix up any found.
        if (empty($qo->answernumbering)) {
            $qo->answernumbering = 'abc';
        }

        // Run through the answers.
        $answers = $data['#']['answer'];
        $acount = 0;
        foreach ($answers as $answer) {
            $ans = $this->import_answer($answer, true, $this->get_format($qo->questiontextformat));
            $qo->answer[$acount] = $ans->answer;
            $qo->fraction[$acount] = $ans->fraction;
            $qo->feedback[$acount] = $ans->feedback;
            ++$acount;
        }

        $this->import_combined_feedback($qo, $data, true);
        $this->import_hints($qo, $data, true, false, $this->get_format($qo->questiontextformat));

        // Run through the competencies.
        if (isset($data['#']['competency'])) {
            $competencies = $data['#']['competency'];
            foreach ($competencies as $competency) {
                $cmpt = $this->import_competency($competency['#']);
                if ($cmpt) {
                    $qo->competencies[] = $cmpt->id;
                }
            }
        }

        return $qo;
    }

    /**
     * Finds a competency by its shortname
     * 
     * @param string $competencyshortname Competency short name
     * @return object Competency or null if no competency with that shortname could be found
     */
    protected function import_competency($competencyshortname) {
        global $DB;

        $competency = $DB->get_record('competency', array('shortname' => $competencyshortname));
        return $competency ? $competency : null;
    }

    /**
     * import parts of question common to all types
     * @param $question array question question array from xml tree
     * @return object question object
     */
    public function import_headers($question) {
        global $USER;

        // This routine initialises the question object.
        $qo = $this->defaultquestion();

        // Question name.
        $qo->name = $this->clean_question_name($this->getpath($question, array('#', 'name', 0, '#', 'text', 0, '#'), '', true, get_string('xmlimportnoname', 'qformat_xml')));
        $questiontext = $this->import_text_with_files($question, array('#', 'questiontext', 0));
        $qo->questiontext = $questiontext['text'];
        $qo->questiontextformat = $questiontext['format'];
        if (!empty($questiontext['itemid'])) {
            $qo->questiontextitemid = $questiontext['itemid'];
        }
        // Backwards compatibility, deal with the old image tag.
        $filedata = $this->getpath($question, array('#', 'image_base64', '0', '#'), null, false);
        $filename = $this->getpath($question, array('#', 'image', '0', '#'), null, false);
        if ($filedata && $filename) {
            $fs = get_file_storage();
            if (empty($qo->questiontextitemid)) {
                $qo->questiontextitemid = file_get_unused_draft_itemid();
            }
            $filename = clean_param(str_replace('/', '_', $filename), PARAM_FILE);
            $filerecord = array(
                'contextid' => context_user::instance($USER->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $qo->questiontextitemid,
                'filepath' => '/',
                'filename' => $filename,
            );
            $fs->create_file_from_string($filerecord, base64_decode($filedata));
            $qo->questiontext .= ' <img src="@@PLUGINFILE@@/' . $filename . '" />';
        }

        // Restore files in generalfeedback.
        $generalfeedback = $this->import_text_with_files($question, array('#', 'generalfeedback', 0), $qo->generalfeedback, $this->get_format($qo->questiontextformat));
        $qo->generalfeedback = $generalfeedback['text'];
        $qo->generalfeedbackformat = $generalfeedback['format'];
        if (!empty($generalfeedback['itemid'])) {
            $qo->generalfeedbackitemid = $generalfeedback['itemid'];
        }

        $qo->defaultmark = $this->getpath($question, array('#', 'defaultgrade', 0, '#'), $qo->defaultmark);
        $qo->penalty = $this->getpath($question, array('#', 'penalty', 0, '#'), $qo->penalty);

        // Fix problematic rounding from old files.
        if (abs($qo->penalty - 0.3333333) < 0.005) {
            $qo->penalty = 0.3333333;
        }

        // Read the question tags.
        $this->import_question_tags($qo, $question);

        return $qo;
    }

    /**
     * Import the common parts of a single answer
     * @param array answer xml tree for single answer
     * @param bool $withanswerfiles if true, the answers are HTML (or $defaultformat)
     *      and so may contain files, otherwise the answers are plain text.
     * @param array Default text format for the feedback, and the answers if $withanswerfiles
     *      is true.
     * @return object answer object
     */
    public function import_answer($answer, $withanswerfiles = false, $defaultformat = 'html') {
        $ans = new stdClass();

        if ($withanswerfiles) {
            $ans->answer = $this->import_text_with_files($answer, array(), '', $defaultformat);
        } else {
            $ans->answer = array();
            $ans->answer['text'] = $this->getpath($answer, array('#', 'text', 0, '#'), '', true);
            $ans->answer['format'] = FORMAT_PLAIN;
        }

        $ans->feedback = $this->import_text_with_files($answer, array('#', 'feedback', 0), '', $defaultformat);

        $ans->fraction = $this->getpath($answer, array('@', 'fraction'), 0) / 100;

        return $ans;
    }

    /**
     * Import the common overall feedback fields.
     * @param object $question the part of the XML relating to this question.
     * @param object $qo the question data to add the fields to.
     * @param bool $withshownumpartscorrect include the shownumcorrect field.
     */
    public function import_combined_feedback($qo, $questionxml, $withshownumpartscorrect = false) {
        $fields = array('correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback');
        foreach ($fields as $field) {
            $qo->$field = $this->import_text_with_files($questionxml, array('#', $field, 0), '', $this->get_format($qo->questiontextformat));
        }

        if ($withshownumpartscorrect) {
            $qo->shownumcorrect = array_key_exists('shownumcorrect', $questionxml['#']);

            // Backwards compatibility.
            if (array_key_exists('correctresponsesfeedback', $questionxml['#'])) {
                $qo->shownumcorrect = $this->trans_single($this->getpath($questionxml, array('#', 'correctresponsesfeedback', 0, '#'), 1));
            }
        }
    }

    /**
     * Import a question hint
     * @param array $hintxml hint xml fragment.
     * @param string $defaultformat the text format to assume for hints that do not specify.
     * @return object hint for storing in the database.
     */
    public function import_hint($hintxml, $defaultformat) {
        $hint = new stdClass();
        if (array_key_exists('hintcontent', $hintxml['#'])) {
            // Backwards compatibility.

            $hint->hint = $this->import_text_with_files($hintxml, array('#', 'hintcontent', 0), '', $defaultformat);

            $hint->shownumcorrect = $this->getpath($hintxml, array('#', 'statenumberofcorrectresponses', 0, '#'), 0);
            $hint->clearwrong = $this->getpath($hintxml, array('#', 'clearincorrectresponses', 0, '#'), 0);
            $hint->options = $this->getpath($hintxml, array('#', 'showfeedbacktoresponses', 0, '#'), 0);

            return $hint;
        }
        $hint->hint = $this->import_text_with_files($hintxml, array(), '', $defaultformat);
        $hint->shownumcorrect = array_key_exists('shownumcorrect', $hintxml['#']);
        $hint->clearwrong = array_key_exists('clearwrong', $hintxml['#']);
        $hint->options = $this->getpath($hintxml, array('#', 'options', 0, '#'), '', true);

        return $hint;
    }

    /**
     * Import all the question hints
     *
     * @param object $qo the question data that is being constructed.
     * @param array $questionxml The xml representing the question.
     * @param bool $withparts whether the extra fields relating to parts should be imported.
     * @param bool $withoptions whether the extra options field should be imported.
     * @param string $defaultformat the text format to assume for hints that do not specify.
     * @return array of objects representing the hints in the file.
     */
    public function import_hints($qo, $questionxml, $withparts = false, $withoptions = false, $defaultformat = 'html') {
        if (!isset($questionxml['#']['hint'])) {
            return;
        }

        foreach ($questionxml['#']['hint'] as $hintxml) {
            $hint = $this->import_hint($hintxml, $defaultformat);
            $qo->hint[] = $hint->hint;

            if ($withparts) {
                $qo->hintshownumcorrect[] = $hint->shownumcorrect;
                $qo->hintclearwrong[] = $hint->clearwrong;
            }

            if ($withoptions) {
                $qo->hintoptions[] = $hint->options;
            }
        }
    }

    /**
     * return an "empty" question
     * Somewhere to specify question parameters that are not handled
     * by import but are required db fields.
     * This should not be overridden.
     * @return object default question
     */
    protected function defaultquestion() {
        global $CFG;
        static $defaultshuffleanswers = null;
        if (is_null($defaultshuffleanswers)) {
            $defaultshuffleanswers = get_config('quiz', 'shuffleanswers');
        }

        $question = new stdClass();
        $question->shuffleanswers = $defaultshuffleanswers;
        $question->defaultmark = 1;
        $question->image = "";
        $question->usecase = 0;
        $question->multiplier = array();
        $question->questiontextformat = FORMAT_MOODLE;
        $question->generalfeedback = '';
        $question->generalfeedbackformat = FORMAT_MOODLE;
        $question->correctfeedback = '';
        $question->partiallycorrectfeedback = '';
        $question->incorrectfeedback = '';
        $question->answernumbering = 'abc';
        $question->penalty = 0.3333333;
        $question->length = 1;

        // this option in case the questiontypes class wants
        // to know where the data came from
        $question->export_process = true;
        $question->import_process = true;

        return $question;
    }

    /**
     * Ensure that a question name does not contain anything nasty, and will fit in the DB field.
     * @param string $name the raw question name.
     * @return string a safe question name.
     */
    public function clean_question_name($name) {
        $name = clean_param($name, PARAM_TEXT); // Matches what the question editing form does.
        $name = trim($name);
        $trimlength = 251;
        while (core_text::strlen($name) > 255 && $trimlength > 0) {
            $name = shorten_text($name, $trimlength);
            $trimlength -= 10;
        }
        return $name;
    }

    /**
     * return the value of a node, given a path to the node
     * if it doesn't exist return the default value
     * @param array xml data to read
     * @param array path path to node expressed as array
     * @param mixed default
     * @param bool istext process as text
     * @param string error if set value must exist, return false and issue message if not
     * @return mixed value
     */
    public function getpath($xml, $path, $default, $istext = false, $error = '') {
        foreach ($path as $index) {
            if (!isset($xml[$index])) {
                if (!empty($error)) {
                    $this->error($error);
                    return false;
                } else {
                    return $default;
                }
            }

            $xml = $xml[$index];
        }

        if ($istext) {
            if (!is_string($xml)) {
                $this->error(get_string('invalidxml', 'qformat_xml'));
            }
            $xml = trim($xml);
        }

        return $xml;
    }

    /**
     * Handle parsing error
     *
     * @param string $message information about error
     * @param string $text imported text that triggered the error
     * @param string $questionname imported question name
     */
    protected function error($message, $text = '', $questionname = '') {
        $importerrorquestion = get_string('importerrorquestion', 'question');

        echo "<div class=\"importerror\">\n";
        echo "<strong>$importerrorquestion $questionname</strong>";
        if (!empty($text)) {
            $text = s($text);
            echo "<blockquote>$text</blockquote>\n";
        }
        echo "<strong>$message</strong>\n";
        echo "</div>";
    }

    public function import_text_with_files($data, $path, $defaultvalue = '', $defaultformat = 'html') {
        $field = array();
        $field['text'] = $this->getpath($data, array_merge($path, array('#', 'text', 0, '#')), $defaultvalue, true);
        $field['format'] = $this->trans_format($this->getpath($data, array_merge($path, array('@', 'format')), $defaultformat));
        $itemid = $this->import_files_as_draft($this->getpath($data, array_merge($path, array('#', 'file')), array(), false));
        if (!empty($itemid)) {
            $field['itemid'] = $itemid;
        }
        return $field;
    }

    /**
     * Translate human readable format name
     * into internal Moodle code number
     * @param string name format name from xml file
     * @return int Moodle format code
     */
    public function trans_format($name) {
        $name = trim($name);

        if ($name == 'moodle_auto_format') {
            return FORMAT_MOODLE;
        } else if ($name == 'html') {
            return FORMAT_HTML;
        } else if ($name == 'plain_text') {
            return FORMAT_PLAIN;
        } else if ($name == 'wiki_like') {
            return FORMAT_WIKI;
        } else if ($name == 'markdown') {
            return FORMAT_MARKDOWN;
        } else {
            debugging("Unrecognised text format '{$name}' in the import file. Assuming 'html'.");
            return FORMAT_HTML;
        }
    }

    public function import_files_as_draft($xml) {
        global $USER;
        if (empty($xml)) {
            return null;
        }
        $fs = get_file_storage();
        $itemid = file_get_unused_draft_itemid();
        $filepaths = array();
        foreach ($xml as $file) {
            $filename = $this->getpath($file, array('@', 'name'), '', true);
            $filepath = $this->getpath($file, array('@', 'path'), '/', true);
            $fullpath = $filepath . $filename;
            if (in_array($fullpath, $filepaths)) {
                debugging('Duplicate file in XML: ' . $fullpath, DEBUG_DEVELOPER);
                continue;
            }
            $filerecord = array(
                'contextid' => context_user::instance($USER->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $itemid,
                'filepath' => $filepath,
                'filename' => $filename,
            );
            $fs->create_file_from_string($filerecord, base64_decode($file['#']));
            $filepaths[] = $fullpath;
        }
        return $itemid;
    }

    /**
     * Import all the question tags
     *
     * @param object $qo the question data that is being constructed.
     * @param array $questionxml The xml representing the question.
     * @return array of objects representing the tags in the file.
     */
    public function import_question_tags($qo, $questionxml) {
        global $CFG;

        if (core_tag_tag::is_enabled('core_question', 'question') && array_key_exists('tags', $questionxml['#']) && !empty($questionxml['#']['tags'][0]['#']['tag'])) {
            $qo->tags = array();
            foreach ($questionxml['#']['tags'][0]['#']['tag'] as $tagdata) {
                $qo->tags[] = $this->getpath($tagdata, array('#', 'text', 0, '#'), '', true);
            }
        }
    }

    /**
     * Translate human readable single answer option
     * to internal code number
     * @param string name true/false
     * @return int internal code number
     */
    public function trans_single($name) {
        $name = trim($name);
        if ($name == "false" || !$name) {
            return 0;
        } else {
            return 1;
        }
    }

    /**
     * If your question type has a table that extends the question_answers table,
     * make this method return an array wherer the first element is the table name,
     * and the subsequent entries are the column names (apart from id and answerid).
     *
     * @return mixed array as above, or null to tell the base class to do nothing.
     */
    public function extra_answer_fields() {
        return array('qtype_cmultichoice_compts', 'questionid', 'competencyid');
    }

    /**
     * Returns true if extra answer fields for answer with the $key is empty
     * in the question data and should not be saved in DB.
     *
     * Questions where extra answer fields are optional will want to overload this.
     * @param object $questiondata This holds the information from the question editing form or import.
     * @param int $key A key of the answer in question.
     * @return bool True if extra answer data shouldn't be saved in DB.
     */
    protected function is_extra_answer_fields_empty($questiondata, $key) {
        return empty($questiondata->competencyid[$key]);
    }

}

<?php

/**
 * Cquiz statistics report class.
 *
 * @package   cquiz_statistics
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    James Pratt <me@jamiep.org>
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/cquiz/report/statistics/statistics_form.php');
require_once($CFG->dirroot . '/mod/cquiz/report/statistics/statistics_table.php');
require_once($CFG->dirroot . '/mod/cquiz/report/statistics/statistics_question_table.php');
require_once($CFG->dirroot . '/mod/cquiz/report/statistics/statisticslib.php');

/**
 * The cquiz statistics report provides summary information about each question in
 * a cquiz, compared to the whole cquiz. It also provides a drill-down to more
 * detailed information about each question.
 *
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquiz_statistics_report extends cquiz_default_report {

    /** @var context_module context of this cquiz. */
    protected $context;

    /** @var cquiz_statistics_table instance of table class used for main questions stats table. */
    protected $table;

    /** @var \core\progress\base|null $progress Handles progress reporting or not. */
    protected $progress = null;

    /**
     * Display the report.
     */
    public function display($cquiz, $cm, $course) {
        global $OUTPUT;

        raise_memory_limit(MEMORY_HUGE);

        $this->context = context_module::instance($cm->id);

        if (!cquiz_has_questions($cquiz->id)) {
            $this->print_header_and_tabs($cm, $course, $cquiz, 'statistics');
            echo cquiz_no_questions_message($cquiz, $cm, $this->context);
            return true;
        }

        // Work out the display options.
        $download = optional_param('download', '', PARAM_ALPHA);
        $everything = optional_param('everything', 0, PARAM_BOOL);
        $recalculate = optional_param('recalculate', 0, PARAM_BOOL);
        // A qid paramter indicates we should display the detailed analysis of a sub question.
        $qid = optional_param('qid', 0, PARAM_INT);
        $slot = optional_param('slot', 0, PARAM_INT);
        $variantno = optional_param('variant', null, PARAM_INT);
        $whichattempts = optional_param('whichattempts', $cquiz->grademethod, PARAM_INT);
        $whichtries = optional_param('whichtries', question_attempt::LAST_TRY, PARAM_ALPHA);

        $pageoptions = array();
        $pageoptions['id'] = $cm->id;
        $pageoptions['mode'] = 'statistics';

        $reporturl = new moodle_url('/mod/cquiz/report.php', $pageoptions);

        $mform = new cquiz_statistics_settings_form($reporturl, compact('cquiz'));

        $mform->set_data(array('whichattempts' => $whichattempts, 'whichtries' => $whichtries));

        if ($whichattempts != $cquiz->grademethod) {
            $reporturl->param('whichattempts', $whichattempts);
        }

        if ($whichtries != question_attempt::LAST_TRY) {
            $reporturl->param('whichtries', $whichtries);
        }

        // Find out current groups mode.
        $currentgroup = $this->get_current_group($cm, $course, $this->context);
        $nostudentsingroup = false; // True if a group is selected and there is no one in it.
        if (empty($currentgroup)) {
            $currentgroup = 0;
            $groupstudents = array();
        } else if ($currentgroup == self::NO_GROUPS_ALLOWED) {
            $groupstudents = array();
            $nostudentsingroup = true;
        } else {
            // All users who can attempt cquizzes and who are in the currently selected group.
            $groupstudents = get_users_by_capability($this->context, array('mod/cquiz:reviewmyattempts', 'mod/cquiz:attempt'), '', '', '', '', $currentgroup, '', false);
            if (!$groupstudents) {
                $nostudentsingroup = true;
            }
        }

        $qubaids = cquiz_statistics_qubaids_condition($cquiz->id, $groupstudents, $whichattempts);

        // If recalculate was requested, handle that.
        if ($recalculate && confirm_sesskey()) {
            $this->clear_cached_data($qubaids);
            redirect($reporturl);
        }

        // Set up the main table.
        $this->table = new cquiz_statistics_table();
        if ($everything) {
            $report = get_string('completestatsfilename', 'cquiz_statistics');
        } else {
            $report = get_string('questionstatsfilename', 'cquiz_statistics');
        }
        $courseshortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
        $filename = cquiz_report_download_filename($report, $courseshortname, $cquiz->name);
        $this->table->is_downloading($download, $filename, get_string('cquizstructureanalysis', 'cquiz_statistics'));
        $questions = $this->load_and_initialise_questions_for_calculations($cquiz);

        // Print the page header stuff (if not downloading.
        if (!$this->table->is_downloading()) {
            $this->print_header_and_tabs($cm, $course, $cquiz, 'statistics');
        }

        if (!$nostudentsingroup) {
            // Get the data to be displayed.
            $progress = $this->get_progress_trace_instance();
            list($cquizstats, $questionstats) = $this->get_all_stats_and_analysis($cquiz, $whichattempts, $whichtries, $groupstudents, $questions, $progress);
        } else {
            // Or create empty stats containers.
            $cquizstats = new \cquiz_statistics\calculated($whichattempts);
            $questionstats = new \core_question\statistics\questions\all_calculated_for_qubaid_condition();
        }

        // Set up the table, if there is data.
        if ($cquizstats->s()) {
            $this->table->statistics_setup($cquiz, $cm->id, $reporturl, $cquizstats->s());
        }

        // Print the rest of the page header stuff (if not downloading.
        if (!$this->table->is_downloading()) {

            if (groups_get_activity_groupmode($cm)) {
                groups_print_activity_menu($cm, $reporturl->out());
                if ($currentgroup && !$groupstudents) {
                    $OUTPUT->notification(get_string('nostudentsingroup', 'cquiz_statistics'));
                }
            }

            if (!$this->table->is_downloading() && $cquizstats->s() == 0) {
                echo $OUTPUT->notification(get_string('noattempts', 'cquiz'));
            }

            foreach ($questionstats->any_error_messages() as $errormessage) {
                echo $OUTPUT->notification($errormessage);
            }

            // Print display options form.
            $mform->display();
        }

        if ($everything) { // Implies is downloading.
            // Overall report, then the analysis of each question.
            $cquizinfo = $cquizstats->get_formatted_cquiz_info_data($course, $cm, $cquiz);
            $this->download_cquiz_info_table($cquizinfo);

            if ($cquizstats->s()) {
                $this->output_cquiz_structure_analysis_table($questionstats);

                if ($this->table->is_downloading() == 'xhtml' && $cquizstats->s() != 0) {
                    $this->output_statistics_graph($cquiz->id, $currentgroup, $whichattempts);
                }

                $this->output_all_question_response_analysis($qubaids, $questions, $questionstats, $reporturl, $whichtries);
            }

            $this->table->export_class_instance()->finish_document();
        } else if ($qid) {
            // Report on an individual sub-question indexed questionid.
            if (is_null($questionstats->for_subq($qid, $variantno))) {
                print_error('questiondoesnotexist', 'question');
            }

            $this->output_individual_question_data($cquiz, $questionstats->for_subq($qid, $variantno));
            $this->output_individual_question_response_analysis($questionstats->for_subq($qid, $variantno)->question, $variantno, $questionstats->for_subq($qid, $variantno)->s, $reporturl, $qubaids, $whichtries);
            // Back to overview link.
            echo $OUTPUT->box('<a href="' . $reporturl->out() . '">' .
                    get_string('backtocquizreport', 'cquiz_statistics') . '</a>', 'boxaligncenter generalbox boxwidthnormal mdl-align');
        } else if ($slot) {
            // Report on an individual question indexed by position.
            if (!isset($questions[$slot])) {
                print_error('questiondoesnotexist', 'question');
            }

            if ($variantno === null &&
                    ($questionstats->for_slot($slot)->get_sub_question_ids() || $questionstats->for_slot($slot)->get_variants())) {
                if (!$this->table->is_downloading()) {
                    $number = $questionstats->for_slot($slot)->question->number;
                    echo $OUTPUT->heading(get_string('slotstructureanalysis', 'cquiz_statistics', $number), 3);
                }
                $this->table->define_baseurl(new moodle_url($reporturl, array('slot' => $slot)));
                $this->table->format_and_add_array_of_rows($questionstats->structure_analysis_for_one_slot($slot));
            } else {
                $this->output_individual_question_data($cquiz, $questionstats->for_slot($slot, $variantno));
                $this->output_individual_question_response_analysis($questions[$slot], $variantno, $questionstats->for_slot($slot, $variantno)->s, $reporturl, $qubaids, $whichtries);
            }
            if (!$this->table->is_downloading()) {
                // Back to overview link.
                echo $OUTPUT->box('<a href="' . $reporturl->out() . '">' .
                        get_string('backtocquizreport', 'cquiz_statistics') . '</a>', 'backtomainstats boxaligncenter generalbox boxwidthnormal mdl-align');
            } else {
                $this->table->finish_output();
            }
        } else if ($this->table->is_downloading()) {
            // Downloading overview report.
            $cquizinfo = $cquizstats->get_formatted_cquiz_info_data($course, $cm, $cquiz);
            $this->download_cquiz_info_table($cquizinfo);
            if ($cquizstats->s()) {
                $this->output_cquiz_structure_analysis_table($questionstats);
            }
            $this->table->finish_output();
        } else {
            // On-screen display of overview report.
            echo $OUTPUT->heading(get_string('cquizinformation', 'cquiz_statistics'), 3);
            echo $this->output_caching_info($cquizstats->timemodified, $cquiz->id, $groupstudents, $whichattempts, $reporturl);
            echo $this->everything_download_options();
            $cquizinfo = $cquizstats->get_formatted_cquiz_info_data($course, $cm, $cquiz);
            echo $this->output_cquiz_info_table($cquizinfo);
            if ($cquizstats->s()) {
                echo $OUTPUT->heading(get_string('cquizstructureanalysis', 'cquiz_statistics'), 3);
                $this->output_cquiz_structure_analysis_table($questionstats);
                $this->output_statistics_graph($cquiz->id, $currentgroup, $whichattempts);
            }
        }

        return true;
    }

    /**
     * Display the statistical and introductory information about a question.
     * Only called when not downloading.
     *
     * @param object                                         $cquiz         the cquiz settings.
     * @param \core_question\statistics\questions\calculated $questionstat the question to report on.
     */
    protected function output_individual_question_data($cquiz, $questionstat) {
        global $OUTPUT;

        // On-screen display. Show a summary of the question's place in the cquiz,
        // and the question statistics.
        $datumfromtable = $this->table->format_row($questionstat);

        // Set up the question info table.
        $questioninfotable = new html_table();
        $questioninfotable->align = array('center', 'center');
        $questioninfotable->width = '60%';
        $questioninfotable->attributes['class'] = 'generaltable titlesleft';

        $questioninfotable->data = array();
        $questioninfotable->data[] = array(get_string('modulename', 'cquiz'), $cquiz->name);
        $questioninfotable->data[] = array(get_string('questionname', 'cquiz_statistics'),
            $questionstat->question->name . '&nbsp;' . $datumfromtable['actions']);

        if ($questionstat->variant !== null) {
            $questioninfotable->data[] = array(get_string('variant', 'cquiz_statistics'), $questionstat->variant);
        }
        $questioninfotable->data[] = array(get_string('questiontype', 'cquiz_statistics'),
            $datumfromtable['icon'] . '&nbsp;' .
            question_bank::get_qtype($questionstat->question->qtype, false)->menu_name() . '&nbsp;' .
            $datumfromtable['icon']);
        $questioninfotable->data[] = array(get_string('positions', 'cquiz_statistics'),
            $questionstat->positions);

        // Set up the question statistics table.
        $questionstatstable = new html_table();
        $questionstatstable->align = array('center', 'center');
        $questionstatstable->width = '60%';
        $questionstatstable->attributes['class'] = 'generaltable titlesleft';

        unset($datumfromtable['number']);
        unset($datumfromtable['icon']);
        $actions = $datumfromtable['actions'];
        unset($datumfromtable['actions']);
        unset($datumfromtable['name']);
        $labels = array(
            's' => get_string('attempts', 'cquiz_statistics'),
            'facility' => get_string('facility', 'cquiz_statistics'),
            'sd' => get_string('standarddeviationq', 'cquiz_statistics'),
            'random_guess_score' => get_string('random_guess_score', 'cquiz_statistics'),
            'intended_weight' => get_string('intended_weight', 'cquiz_statistics'),
            'effective_weight' => get_string('effective_weight', 'cquiz_statistics'),
            'discrimination_index' => get_string('discrimination_index', 'cquiz_statistics'),
            'discriminative_efficiency' =>
            get_string('discriminative_efficiency', 'cquiz_statistics')
        );
        foreach ($datumfromtable as $item => $value) {
            $questionstatstable->data[] = array($labels[$item], $value);
        }

        // Display the various bits.
        echo $OUTPUT->heading(get_string('questioninformation', 'cquiz_statistics'), 3);
        echo html_writer::table($questioninfotable);
        echo $this->render_question_text($questionstat->question);
        echo $OUTPUT->heading(get_string('questionstatistics', 'cquiz_statistics'), 3);
        echo html_writer::table($questionstatstable);
    }

    /**
     * Output question text in a box with urls appropriate for a preview of the question.
     *
     * @param object $question question data.
     * @return string HTML of question text, ready for display.
     */
    protected function render_question_text($question) {
        global $OUTPUT;

        $text = question_rewrite_question_preview_urls($question->questiontext, $question->id, $question->contextid, 'question', 'questiontext', $question->id, $this->context->id, 'cquiz_statistics');

        return $OUTPUT->box(format_text($text, $question->questiontextformat, array('noclean' => true, 'para' => false, 'overflowdiv' => true)), 'questiontext boxaligncenter generalbox boxwidthnormal mdl-align');
    }

    /**
     * Display the response analysis for a question.
     *
     * @param object           $question  the question to report on.
     * @param int|null         $variantno the variant
     * @param int              $s
     * @param moodle_url       $reporturl the URL to redisplay this report.
     * @param qubaid_condition $qubaids
     * @param string           $whichtries
     */
    protected function output_individual_question_response_analysis($question, $variantno, $s, $reporturl, $qubaids, $whichtries = question_attempt::LAST_TRY) {
        global $OUTPUT;

        if (!question_bank::get_qtype($question->qtype, false)->can_analyse_responses()) {
            return;
        }

        $qtable = new cquiz_statistics_question_table($question->id);
        $exportclass = $this->table->export_class_instance();
        $qtable->export_class_instance($exportclass);
        if (!$this->table->is_downloading()) {
            // Output an appropriate title.
            echo $OUTPUT->heading(get_string('analysisofresponses', 'cquiz_statistics'), 3);
        } else {
            // Work out an appropriate title.
            $a = clone($question);
            $a->variant = $variantno;

            if (!empty($question->number) && !is_null($variantno)) {
                $questiontabletitle = get_string('analysisnovariant', 'cquiz_statistics', $a);
            } else if (!empty($question->number)) {
                $questiontabletitle = get_string('analysisno', 'cquiz_statistics', $a);
            } else if (!is_null($variantno)) {
                $questiontabletitle = get_string('analysisvariant', 'cquiz_statistics', $a);
            } else {
                $questiontabletitle = get_string('analysisnameonly', 'cquiz_statistics', $a);
            }

            if ($this->table->is_downloading() == 'xhtml') {
                $questiontabletitle = get_string('analysisofresponsesfor', 'cquiz_statistics', $questiontabletitle);
            }

            // Set up the table.
            $exportclass->start_table($questiontabletitle);

            if ($this->table->is_downloading() == 'xhtml') {
                echo $this->render_question_text($question);
            }
        }

        $responesanalyser = new \core_question\statistics\responses\analyser($question, $whichtries);
        $responseanalysis = $responesanalyser->load_cached($qubaids, $whichtries);

        $qtable->question_setup($reporturl, $question, $s, $responseanalysis);
        if ($this->table->is_downloading()) {
            $exportclass->output_headers($qtable->headers);
        }

        // Where no variant no is specified the variant no is actually one.
        if ($variantno === null) {
            $variantno = 1;
        }
        foreach ($responseanalysis->get_subpart_ids($variantno) as $partid) {
            $subpart = $responseanalysis->get_analysis_for_subpart($variantno, $partid);
            foreach ($subpart->get_response_class_ids() as $responseclassid) {
                $responseclass = $subpart->get_response_class($responseclassid);
                $tabledata = $responseclass->data_for_question_response_table($subpart->has_multiple_response_classes(), $partid);
                foreach ($tabledata as $row) {
                    $qtable->add_data_keyed($qtable->format_row($row));
                }
            }
        }

        $qtable->finish_output(!$this->table->is_downloading());
    }

    /**
     * Output the table that lists all the questions in the cquiz with their statistics.
     *
     * @param \core_question\statistics\questions\all_calculated_for_qubaid_condition $questionstats the stats for all questions in
     *                                                                                               the cquiz including subqs and
     *                                                                                               variants.
     */
    protected function output_cquiz_structure_analysis_table($questionstats) {
        $tooutput = array();
        $limitvariants = !$this->table->is_downloading();
        foreach ($questionstats->get_all_slots() as $slot) {
            // Output the data for these question statistics.
            $tooutput = array_merge($tooutput, $questionstats->structure_analysis_for_one_slot($slot, $limitvariants));
        }
        $this->table->format_and_add_array_of_rows($tooutput);
    }

    /**
     * Return HTML for table of overall cquiz statistics.
     *
     * @param array $cquizinfo as returned by {@link get_formatted_cquiz_info_data()}.
     * @return string the HTML.
     */
    protected function output_cquiz_info_table($cquizinfo) {

        $cquizinfotable = new html_table();
        $cquizinfotable->align = array('center', 'center');
        $cquizinfotable->width = '60%';
        $cquizinfotable->attributes['class'] = 'generaltable titlesleft';
        $cquizinfotable->data = array();

        foreach ($cquizinfo as $heading => $value) {
            $cquizinfotable->data[] = array($heading, $value);
        }

        return html_writer::table($cquizinfotable);
    }

    /**
     * Download the table of overall cquiz statistics.
     *
     * @param array $cquizinfo as returned by {@link get_formatted_cquiz_info_data()}.
     */
    protected function download_cquiz_info_table($cquizinfo) {
        global $OUTPUT;

        // XHTML download is a special case.
        if ($this->table->is_downloading() == 'xhtml') {
            echo $OUTPUT->heading(get_string('cquizinformation', 'cquiz_statistics'), 3);
            echo $this->output_cquiz_info_table($cquizinfo);
            return;
        }

        // Reformat the data ready for output.
        $headers = array();
        $row = array();
        foreach ($cquizinfo as $heading => $value) {
            $headers[] = $heading;
            $row[] = $value;
        }

        // Do the output.
        $exportclass = $this->table->export_class_instance();
        $exportclass->start_table(get_string('cquizinformation', 'cquiz_statistics'));
        $exportclass->output_headers($headers);
        $exportclass->add_data($row);
        $exportclass->finish_table();
    }

    /**
     * Output the HTML needed to show the statistics graph.
     *
     * @param $cquizid
     * @param $currentgroup
     * @param $whichattempts
     */
    protected function output_statistics_graph($cquizid, $currentgroup, $whichattempts) {
        global $PAGE;

        $output = $PAGE->get_renderer('mod_cquiz');
        $imageurl = new moodle_url('/mod/cquiz/report/statistics/statistics_graph.php', compact('cquizid', 'currentgroup', 'whichattempts'));
        $graphname = get_string('statisticsreportgraph', 'cquiz_statistics');
        echo $output->graph($imageurl, $graphname);
    }

    /**
     * Get the cquiz and question statistics, either by loading the cached results,
     * or by recomputing them.
     *
     * @param object $cquiz               the cquiz settings.
     * @param string $whichattempts      which attempts to use, represented internally as one of the constants as used in
     *                                   $cquiz->grademethod ie.
     *                                   CQUIZ_GRADEAVERAGE, CQUIZ_GRADEHIGHEST, CQUIZ_ATTEMPTLAST or CQUIZ_ATTEMPTFIRST
     *                                   we calculate stats based on which attempts would affect the grade for each student.
     * @param string $whichtries         which tries to analyse for response analysis. Will be one of
     *                                   question_attempt::FIRST_TRY, LAST_TRY or ALL_TRIES.
     * @param array  $groupstudents      students in this group.
     * @param array  $questions          full question data.
     * @param \core\progress\base|null   $progress
     * @return array with 2 elements:    - $cquizstats The statistics for overall attempt scores.
     *                                   - $questionstats \core_question\statistics\questions\all_calculated_for_qubaid_condition
     */
    public function get_all_stats_and_analysis($cquiz, $whichattempts, $whichtries, $groupstudents, $questions, $progress = null) {

        if ($progress === null) {
            $progress = new \core\progress\none();
        }

        $qubaids = cquiz_statistics_qubaids_condition($cquiz->id, $groupstudents, $whichattempts);

        $qcalc = new \core_question\statistics\questions\calculator($questions, $progress);

        $cquizcalc = new \cquiz_statistics\calculator($progress);

        $progress->start_progress('', 3);
        if ($cquizcalc->get_last_calculated_time($qubaids) === false) {

            // Recalculate now.
            $questionstats = $qcalc->calculate($qubaids);
            $progress->progress(1);

            $cquizstats = $cquizcalc->calculate($cquiz->id, $whichattempts, $groupstudents, count($questions), $qcalc->get_sum_of_mark_variance());
            $progress->progress(2);
        } else {
            $cquizstats = $cquizcalc->get_cached($qubaids);
            $progress->progress(1);
            $questionstats = $qcalc->get_cached($qubaids);
            $progress->progress(2);
        }

        if ($cquizstats->s()) {
            $subquestions = $questionstats->get_sub_questions();
            $this->analyse_responses_for_all_questions_and_subquestions($questions, $subquestions, $qubaids, $whichtries, $progress);
        }
        $progress->progress(3);
        $progress->end_progress();

        return array($cquizstats, $questionstats);
    }

    /**
     * Appropriate instance depending if we want html output for the user or not.
     *
     * @return \core\progress\base child of \core\progress\base to handle the display (or not) of task progress.
     */
    protected function get_progress_trace_instance() {
        if ($this->progress === null) {
            if (!$this->table->is_downloading()) {
                $this->progress = new \core\progress\display_if_slow(get_string('calculatingallstats', 'cquiz_statistics'));
                $this->progress->set_display_names();
            } else {
                $this->progress = new \core\progress\none();
            }
        }
        return $this->progress;
    }

    /**
     * Analyse responses for all questions and sub questions in this cquiz.
     *
     * @param object[] $questions as returned by self::load_and_initialise_questions_for_calculations
     * @param object[] $subquestions full question objects.
     * @param qubaid_condition $qubaids the question usages whose responses to analyse.
     * @param string $whichtries which tries to analyse \question_attempt::FIRST_TRY, LAST_TRY or ALL_TRIES.
     * @param null|\core\progress\base $progress Used to indicate progress of task.
     */
    protected function analyse_responses_for_all_questions_and_subquestions($questions, $subquestions, $qubaids, $whichtries, $progress = null) {
        if ($progress === null) {
            $progress = new \core\progress\none();
        }

        // Starting response analysis tasks.
        $progress->start_progress('', count($questions) + count($subquestions));

        $done = $this->analyse_responses_for_questions($questions, $qubaids, $whichtries, $progress);

        $this->analyse_responses_for_questions($subquestions, $qubaids, $whichtries, $progress, $done);

        // Finished all response analysis tasks.
        $progress->end_progress();
    }

    /**
     * Analyse responses for an array of questions or sub questions.
     *
     * @param object[] $questions  as returned by self::load_and_initialise_questions_for_calculations.
     * @param qubaid_condition $qubaids the question usages whose responses to analyse.
     * @param string $whichtries which tries to analyse \question_attempt::FIRST_TRY, LAST_TRY or ALL_TRIES.
     * @param null|\core\progress\base $progress Used to indicate progress of task.
     * @param int[] $done array keys are ids of questions that have been analysed before calling method.
     * @return array array keys are ids of questions that were analysed after this method call.
     */
    protected function analyse_responses_for_questions($questions, $qubaids, $whichtries, $progress = null, $done = array()) {
        $countquestions = count($questions);
        if (!$countquestions) {
            return array();
        }
        if ($progress === null) {
            $progress = new \core\progress\none();
        }
        $progress->start_progress('', $countquestions, $countquestions);
        foreach ($questions as $question) {
            $progress->increment_progress();
            if (question_bank::get_qtype($question->qtype, false)->can_analyse_responses() && !isset($done[$question->id])) {
                $responesstats = new \core_question\statistics\responses\analyser($question, $whichtries);
                if ($responesstats->get_last_analysed_time($qubaids, $whichtries) === false) {
                    $responesstats->calculate($qubaids, $whichtries);
                }
            }
            $done[$question->id] = 1;
        }
        $progress->end_progress();
        return $done;
    }

    /**
     * Return a little form for the user to request to download the full report, including cquiz stats and response analysis for
     * all questions and sub-questions.
     *
     * @return string HTML.
     */
    protected function everything_download_options() {
        global $OUTPUT;

        return $OUTPUT->download_dataformat_selector(get_string('downloadeverything', 'cquiz_statistics'), $this->table->baseurl->out_omit_querystring(), 'download', $this->table->baseurl->params() + array('everything' => 1));
    }

    /**
     * Return HTML for a message that says when the stats were last calculated and a 'recalculate now' button.
     *
     * @param int    $lastcachetime  the time the stats were last cached.
     * @param int    $cquizid         the cquiz id.
     * @param array  $groupstudents  ids of students in the group or empty array if groups not used.
     * @param string $whichattempts which attempts to use, represented internally as one of the constants as used in
     *                                   $cquiz->grademethod ie.
     *                                   CQUIZ_GRADEAVERAGE, CQUIZ_GRADEHIGHEST, CQUIZ_ATTEMPTLAST or CQUIZ_ATTEMPTFIRST
     *                                   we calculate stats based on which attempts would affect the grade for each student.
     * @param moodle_url $reporturl url for this report
     * @return string HTML.
     */
    protected function output_caching_info($lastcachetime, $cquizid, $groupstudents, $whichattempts, $reporturl) {
        global $DB, $OUTPUT;

        if (empty($lastcachetime)) {
            return '';
        }

        // Find the number of attempts since the cached statistics were computed.
        list($fromqa, $whereqa, $qaparams) = cquiz_statistics_attempts_sql($cquizid, $groupstudents, $whichattempts, true);
        $count = $DB->count_records_sql("
                SELECT COUNT(1)
                FROM $fromqa
                WHERE $whereqa
                AND cquiza.timefinish > {$lastcachetime}", $qaparams);

        if (!$count) {
            $count = 0;
        }

        // Generate the output.
        $a = new stdClass();
        $a->lastcalculated = format_time(time() - $lastcachetime);
        $a->count = $count;

        $recalcualteurl = new moodle_url($reporturl, array('recalculate' => 1, 'sesskey' => sesskey()));
        $output = '';
        $output .= $OUTPUT->box_start(
                'boxaligncenter generalbox boxwidthnormal mdl-align', 'cachingnotice');
        $output .= get_string('lastcalculated', 'cquiz_statistics', $a);
        $output .= $OUTPUT->single_button($recalcualteurl, get_string('recalculatenow', 'cquiz_statistics'));
        $output .= $OUTPUT->box_end(true);

        return $output;
    }

    /**
     * Clear the cached data for a particular report configuration. This will trigger a re-computation the next time the report
     * is displayed.
     *
     * @param $qubaids qubaid_condition
     */
    protected function clear_cached_data($qubaids) {
        global $DB;
        $DB->delete_records('cquiz_statistics', array('hashcode' => $qubaids->get_hash_code()));
        $DB->delete_records('question_statistics', array('hashcode' => $qubaids->get_hash_code()));
        $DB->delete_records('question_response_analysis', array('hashcode' => $qubaids->get_hash_code()));
    }

    /**
     * Load the questions in this cquiz and add some properties to the objects needed in the reports.
     *
     * @param object $cquiz the cquiz.
     * @return array of questions for this cquiz.
     */
    public function load_and_initialise_questions_for_calculations($cquiz) {
        // Load the questions.
        $questions = cquiz_report_get_significant_questions($cquiz);
        $questionids = array();
        foreach ($questions as $question) {
            $questionids[] = $question->id;
        }
        $fullquestions = question_load_questions($questionids);
        foreach ($questions as $qno => $question) {
            $q = $fullquestions[$question->id];
            $q->maxmark = $question->maxmark;
            $q->slot = $qno;
            $q->number = $question->number;
            $questions[$qno] = $q;
        }
        return $questions;
    }

    /**
     * Output all response analysis for all questions, sub-questions and variants. For download in a number of formats.
     *
     * @param $qubaids
     * @param $questions
     * @param $questionstats
     * @param $reporturl
     * @param $whichtries string
     */
    protected function output_all_question_response_analysis($qubaids, $questions, $questionstats, $reporturl, $whichtries = question_attempt::LAST_TRY) {
        foreach ($questions as $slot => $question) {
            if (question_bank::get_qtype(
                            $question->qtype, false)->can_analyse_responses()
            ) {
                if ($questionstats->for_slot($slot)->get_variants()) {
                    foreach ($questionstats->for_slot($slot)->get_variants() as $variantno) {
                        $this->output_individual_question_response_analysis($question, $variantno, $questionstats->for_slot($slot, $variantno)->s, $reporturl, $qubaids, $whichtries);
                    }
                } else {
                    $this->output_individual_question_response_analysis($question, null, $questionstats->for_slot($slot)->s, $reporturl, $qubaids, $whichtries);
                }
            } else if ($subqids = $questionstats->for_slot($slot)->get_sub_question_ids()) {
                foreach ($subqids as $subqid) {
                    if ($variants = $questionstats->for_subq($subqid)->get_variants()) {
                        foreach ($variants as $variantno) {
                            $this->output_individual_question_response_analysis(
                                    $questionstats->for_subq($subqid, $variantno)->question, $variantno, $questionstats->for_subq($subqid, $variantno)->s, $reporturl, $qubaids, $whichtries);
                        }
                    } else {
                        $this->output_individual_question_response_analysis(
                                $questionstats->for_subq($subqid)->question, null, $questionstats->for_subq($subqid)->s, $reporturl, $qubaids, $whichtries);
                    }
                }
            }
        }
    }

}

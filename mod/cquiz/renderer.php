<?php

/**
 * Defines the renderer for the cquiz module.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * The renderer for the cquiz module.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class mod_cquiz_renderer extends plugin_renderer_base {

    private function print_preresults($finishbutttonshtml) {
        $html = '';
        $html .= html_writer::start_div('narrow-page navbar navbar-expand-md navbar-list navbar-dark border-bottom-0 mb-32pt', array(
                    'id' => 'preresults',
                    'style' => 'white-space: nowrap;'
        ));
        $html .= html_writer::start_div('nav navbar-nav ml-sm-auto navbar-list__item');
        $html .= html_writer::start_div('nav-item d-flex flex-column flex-sm-row ml-sm-16pt');
        $html .= $finishbutttonshtml;
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Builds the review page
     *
     * @param cquiz_attempt $attemptobj an instance of cquiz_attempt.
     * @param array $slots an array of integers relating to questions.
     * @param int $page the current page number
     * @param bool $showall whether to show entire attempt on one page.
     * @param bool $lastpage if true the current page is the last page.
     * @param mod_cquiz_display_options $displayoptions instance of mod_cquiz_display_options.
     * @param array $summarydata contains all table data
     * @return $output containing html data.
     */
    public function review_page(cquiz_attempt $attemptobj, $slots, $page, $showall, $lastpage, mod_cquiz_display_options $displayoptions, $summarydata) {
        global $CFG;

        $output = $this->header();
        $output .= '<!-- Review page start -->';
        $cquiz = $attemptobj->get_cquiz();
        $course = $attemptobj->get_course();
        $output .= $this->view_page_prebanner($course, $cquiz);
        $output .= $this->review_header($attemptobj, $slots);
        $output .= '<!-- Results BEGIN -->';
        $output .= html_writer::start_div('', array(
                    'id' => 'resultspane'
        ));
        $output .= $this->print_preresults($this->review_next_navigation($attemptobj, $page, $lastpage, $showall));
        $output .= html_writer::start_div('narrow-page container page__container', array(
                    'id' => 'results'
        ));
        $output .= html_writer::start_div('row');
        $output .= html_writer::start_div('questions col-lg-8');
        $output .= $this->review_form($page, $showall, $displayoptions, $this->questions($attemptobj, true, $slots, $page, $showall, $displayoptions), $attemptobj);
        $output .= html_writer::end_div();
        $output .= html_writer::start_div('col-lg-4');
        $output .= $this->review_summary_table($summarydata, $page);
        $output .= $this->review_competencies($attemptobj, $slots);
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        $output .= '<!-- Results END -->';
        $output .= '<!-- Review page end -->';
        $output .= $this->footer();
        return $output;
    }

    private function print_questions_navbar(cquiz_attempt $attemptobj, $slots) {
        $html = '';
        $html .= html_writer::start_tag('nav', array(
                    'class' => 'course-nav'
        ));
        foreach ($slots as $slot) {
            $questionattempt = $attemptobj->get_question_attempt($slot);
            $html .= html_writer::start_tag('a', array(
                        'href' => '#question' . $slot,
                        'data-toggle' => 'tooltip',
                        'class' => 'bg-white',
                        'data-placement' => 'bottom',
                        'data-title' => get_string('question', 'mod_cquiz') . ' ' . $slot,
                        'data-original-title' => '',
                        'title' => ''
            ));
            if ($questionattempt->get_fraction() > 0) {
                $html .= html_writer::start_tag('i', array(
                            'class' => 'icon fa fa-check-circle text-success',
                            'style' => 'margin-left: 9px;'
                ));
                $html .= html_writer::end_tag('i');
            } else {
                $html .= html_writer::start_tag('i', array(
                            'class' => 'icon fa fa-close text-danger',
                            'style' => 'margin-left: 9px;'
                ));
                $html .= html_writer::end_tag('i');
            }
            $html .= html_writer::end_tag('a');
        }
        $html .= html_writer::end_tag('nav');
        return $html;
    }

    private function print_dashboard_access() {
        $html = '';
        $html .= html_writer::start_tag('span', array(
                    'class' => 'text-white',
                    'style' => 'margin-bottom: 2rem;'
        ));
        $html .= get_string('checkyourdashboard', 'mod_cquiz', get_string('yourdashboard', 'mod_cquiz'));
        $html .= html_writer::end_tag('span');
        $html .= html_writer::start_tag('a', array(
                    'href' => $CFG->wwwroot . '/local/dashboard/view.php',
                    'class' => 'btn'
        ));

        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-dashboard',
                    'style' => 'margin-left: 9px;'
        ));
        $html .= html_writer::end_tag('i');
        $html .= get_string('dashboard', 'mod_cquiz');
        $html .= html_writer::end_tag('a');
        return $html;
    }

    private function review_header(cquiz_attempt $attemptobj, array $slots) {
        global $CFG;

        $html = '';
        $html .= '<!-- Banner BEGIN -->';
        $html .= html_writer::start_div('animated-gradient pb-lg-64pt py-32pt');
        $html .= html_writer::start_div('bg-img-1 mdk-box--bg-gradient-primary2 mb-0 container', array(
                    'id' => 'banner'
        ));
        $html .= html_writer::start_div('narrow-page mdk-box__content');
        $course = $attemptobj->get_course();
        $cm = $attemptobj->get_cm();
        $html .= $this->print_questions_navbar($attemptobj, $slots);
        $html .= html_writer::start_div('reviewheader text-center text-sm-left');
        $html .= html_writer::start_div('container d-flex flex-column justify-content-center align-items-center');
        $cquizobj = $attemptobj->get_cquiz();
        $sessiondesc = $this->get_session_name($cquizobj);
        $html .= html_writer::tag('p', $attemptobj->get_cquiz_name() . ': ' . $sessiondesc, array(
                    'class' => 'lead text-white-70 strong chip  measure-lead-max mb-0'
        ));
        $cquiz = new cquiz($attemptobj->get_cquiz(), $cm, $course);
        $totalquestions = $cquiz->get_structure()->get_question_count();
        $rightquestions = 0;
        foreach ($slots as $slot) {
            $questionattempt = $attemptobj->get_question_attempt($slot);
            if ($questionattempt->get_fraction() > 0) {
                ++$rightquestions;
            }
        }
        $text = get_string('rightquestions', 'mod_cquiz', array(
            'rightquestions' => $rightquestions,
            'totalquestions' => $totalquestions
        ));
        $html .= html_writer::tag('h1', $text, array(
                    'class' => 'mb-24pt'
        ));
        $html .= $this->print_dashboard_access();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Banner END -->';
        return $html;
    }

    /**
     * Renders competencies summary in review page.
     *
     * @param cquiz_attempt $attemptobj an instance of cquiz_attempt.
     * @param array $slots an array of integers relating to questions.
     */
    protected function review_competencies($attemptobj, $slots) {
        // Retrieve questions competencies
        $competencies = array();
        foreach ($slots as $slot) {
            $questionattempt = $attemptobj->get_question_attempt($slot);
            $question = $questionattempt->get_question();
            $answerid = $this->get_answer_id($questionattempt);
            $qcompetencies = $this->get_question_competencies($question, $answerid);
            foreach ($qcompetencies as $qcompetency) {
                if (!isset($competencies[$qcompetency->shortname])) {
                    $competencies[$qcompetency->shortname] = new stdClass();
                    $competencies[$qcompetency->shortname]->questions = '';
                    $competencies[$qcompetency->shortname]->numquestions = 0;
                    $competencies[$qcompetency->shortname]->fractionsum = 0.0;
                    $competencies[$qcompetency->shortname]->description = $qcompetency->description;
                }
                $class = ($questionattempt->get_fraction() == 1 ? 'bg-success' : 'bg-danger');
                $competencies[$qcompetency->shortname]->questions .= html_writer::div(html_writer::tag('span', $attemptobj->get_question_number($attemptobj->get_original_slot($slot)), array('class' => 'avatar-title rounded-circle border-0 text-white ' . $class)), 'avatar avatar-xs mr-1');
                ++$competencies[$qcompetency->shortname]->numquestions;
                $competencies[$qcompetency->shortname]->fractionsum += $questionattempt->get_fraction();
            }
        }
        if (count($competencies) == 0) {
            return '';
        }
        $this->sort_competencies($competencies);
        // Print question results by competency
        $html = '';
        $html .= html_writer::start_div('card mb-0', array(
                    'id' => 'competencies'
        ));
        $html .= html_writer::start_div('card-body');
        $html .= html_writer::tag('h5', get_string('competencies', 'core_competency'));
        foreach ($competencies as $shortname => $competency) {
            $html .= html_writer::start_div('d-flex mb-8pt');
            $html .= html_writer::start_div('flex');
            $title = $shortname;
            if (isset($competency->description) && $competency->description) {
                $title .= ': ' . $competency->description;
            }

            $html .= html_writer::tag('strong', $title, array(
                        'class' => 'text-70'
            ));
            $html .= html_writer::end_div();
            $html .= $competency->questions;
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Get answer provided by user in a question attempt
     * 
     * @param question_attempt $qa Question attempt to be considered
     * @return int Id of answer or null if no answer provided
     */
    private function get_answer_id(question_attempt $qa) {
        $answer = $qa->get_last_qt_var('answer');
        if ($answer === null) {
            return null;
        }
        $order = explode(',', $qa->get_last_qt_var('_order'));
        return $order[$answer];
    }

    /**
     * Sort competencies in descending achievement rate
     * 
     * @param array $competencies Competencies to be sorted
     */
    private function sort_competencies(&$competencies) {
        uasort($competencies, create_function('$a, $b', 'if($a->fractionsum/$a->numquestions == $b->fractionsum/$b->numquestions) return ($a->fractionsum == $b->fractionsum ? 0 : $b->fractionsum - $a->fractionsum); return ($a->fractionsum/$a->numquestions > $b->fractionsum/$b->numquestions ? -1 : 1);'));
    }

    /**
     * Return an appropriate icon (green tick, red cross, etc.) for a grade.
     * @param float $fraction grade on a scale 0..1.
     * @return string html fragment.
     */
    protected function feedback_image($fraction) {
        $feedbackclass = question_state::graded_state_for_fraction($fraction)->get_feedback_class();

        $attributes = array(
            'src' => $this->output->image_url('i/grade_' . $feedbackclass),
            'alt' => get_string($feedbackclass, 'question'),
            'class' => 'questioncorrectnessicon',
        );

        return html_writer::empty_tag('img', $attributes);
    }

    /**
     * Retrieve question competencies
     * 
     * @param object $question Question to be considered
     * @param int $answerid Answer to be considered
     * @return array Question competencies
     */
    protected function get_question_competencies($question, $answerid = null) {

        $methodname = "get_competencies";
        if (method_exists($question, $methodname)) {
            return $question->$methodname($answerid);
        }
        return array();
    }

    /**
     * Renders the review question pop-up.
     *
     * @param cquiz_attempt $attemptobj an instance of cquiz_attempt.
     * @param int $slot which question to display.
     * @param int $seq which step of the question attempt to show. null = latest.
     * @param mod_cquiz_display_options $displayoptions instance of mod_cquiz_display_options.
     * @param array $summarydata contains all table data
     * @return $output containing html data.
     */
    public function review_question_page(cquiz_attempt $attemptobj, $slot, $seq, mod_cquiz_display_options $displayoptions, $summarydata) {

        $output = '';
        $output .= $this->header();
        $output .= $this->review_summary_table($summarydata, 0);

        if (!is_null($seq)) {
            $output .= $attemptobj->render_question_at_step($slot, $seq, true, $this);
        } else {
            $output .= $attemptobj->render_question($slot, true, $this);
        }

        $output .= $this->close_window_button();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Renders the review question pop-up.
     *
     * @param cquiz_attempt $attemptobj an instance of cquiz_attempt.
     * @param string $message Why the review is not allowed.
     * @return string html to output.
     */
    public function review_question_not_allowed(cquiz_attempt $attemptobj, $message) {
        $output = '';
        $output .= $this->header();
        $output .= $this->heading(format_string($attemptobj->get_cquiz_name(), true, array("context" => $attemptobj->get_cquizobj()->get_context())));
        $output .= $this->notification($message);
        $output .= $this->close_window_button();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Filters the summarydata array.
     *
     * @param array $summarydata contains row data for table
     * @param int $page the current page number
     * @return $summarydata containing filtered row data
     */
    protected function filter_review_summary_table($summarydata, $page) {
        if ($page == 0) {
            return $summarydata;
        }

        // Only show some of summary table on subsequent pages.
        foreach ($summarydata as $key => $rowdata) {
            if (!in_array($key, array('user', 'attemptlist'))) {
                unset($summarydata[$key]);
            }
        }

        return $summarydata;
    }

    /**
     * Outputs the table containing data from summary data array
     *
     * @param array $summarydata contains row data for table
     * @param int $page contains the current page number
     */
    public function review_summary_table($summarydata, $page) {
        $html = '';
        $html .= html_writer::start_div('card mb-3', array(
                    'id' => 'summary'
        ));
        $html .= html_writer::start_div('card-body');
        $html .= html_writer::tag('h5', get_string('summary', 'mod_cquiz'));
        foreach ($summarydata as $rowdata) {
            $html .= html_writer::start_div('d-flex mb-8pt');
            $html .= html_writer::start_div('flex');
            if ($rowdata['title'] instanceof renderable) {
                $title = $this->render($rowdata['title']);
            } else {
                $title = $rowdata['title'];
            }
            $html .= html_writer::tag('strong', $title, array(
                        'class' => 'text-70',
            ));
            $html .= html_writer::end_div();
            if ($rowdata['content'] instanceof renderable) {
                $content = $this->render($rowdata['content']);
            } else {
                $content = $rowdata['content'];
            }
            $html .= html_writer::tag('strong', $content, array(
                        "style" => "text-align: right;",
            ));
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Renders each question
     *
     * @param cquiz_attempt $attemptobj instance of cquiz_attempt
     * @param bool $reviewing
     * @param array $slots array of intgers relating to questions
     * @param int $page current page number
     * @param bool $showall if true shows attempt on single page
     * @param mod_cquiz_display_options $displayoptions instance of mod_cquiz_display_options
     */
    public function questions(cquiz_attempt $attemptobj, $reviewing, $slots, $page, $showall, mod_cquiz_display_options $displayoptions) {
        $output = '';
        $count = 1;
        $numquestions = count($slots);
        $socialforumid = $this->get_social_forum_id($attemptobj->get_cquiz());
        foreach ($slots as $slot) {
            $questionattempt = $attemptobj->get_question_attempt($slot);
            $output .= html_writer::start_div('question', array(
                        'id' => 'question' . $slot
            ));
            $output .= html_writer::start_div('border-left-2 pb-32pt pl-32pt');
            $output .= html_writer::start_div('d-flex align-items-center page-num-container mb-16pt');
            $output .= html_writer::div($count, 'page-num');
            $output .= html_writer::tag('h4', get_string('question') . ' ' .
                            $count . ' ' . get_string('of', 'mod_cquiz') . ' ' . $numquestions);
            $output .= html_writer::end_div();
            $output .= $attemptobj->render_question($slot, $reviewing, $this, $attemptobj->review_url($slot, $page, $showall));
            if ($questionattempt->get_fraction() == 0) {
                $output .= $this->render_ask_for_help($attemptobj, $socialforumid);
            }
            $output .= html_writer::end_div();
            $output .= html_writer::end_div();
            ++$count;
        }
        return $output;
    }

    /**
     * Render an ask for help in HTML
     * 
     * @param cquiz_attempt $attemptobj instance of cquiz_attempt
     * @param string $socialforumid
     * @return string Ask for hehlp rendered in HTML
     */
    protected function render_ask_for_help(cquiz_attempt $attemptobj, string $socialforumid) {
        $output = '';

        $cquiz = $attemptobj->get_cquiz();
        if ($cquiz->helpforum) {
            $output .= html_writer::start_tag('div', array('class' => 'que cmultichoice deferredfeedback'));
            $output .= html_writer::start_tag('div', array('class' => 'content'));
            $output .= html_writer::start_tag('div', array('class' => 'outcome clearfix'));
            $output .= html_writer::start_tag('div', array('class' => 'feedback'));
            $output .= html_writer::start_tag('div', array('class' => 'specificfeedback incorrect'));
            if ($socialforumid) {
                $helpforumurl = new moodle_url('/mod/socialforum/view.php', array('id' => $socialforumid));
                $helpforumtag = html_writer::tag('a', $cquiz->helpforum, array('href' => $helpforumurl));
                $helpforumtext = get_string('askforhelp', 'cquiz', array('forum' => $helpforumtag));
            } else {
                $helpforumtext = get_string('askforhelp', 'cquiz', array('forum' => $cquiz->helpforum));
            }
            $formatoptions = new stdClass();
            $formatoptions->noclean = true;
            $helpforumtext = format_text($helpforumtext, 1, $formatoptions);
            $output .= $helpforumtext;
            $output .= html_writer::end_tag('div');
            $output .= html_writer::end_tag('div');
            $output .= html_writer::end_tag('div');
            $output .= html_writer::end_tag('div');
            $output .= html_writer::end_tag('div');
        }

        return $output;
    }

    /**
     * Return the id of the social forum associated with the competency quiz
     * 
     * @param stdClass $cquiz
     * @return integer Social forum id or 0 if not found
     */
    private function get_social_forum_id(stdClass $cquiz) {
        global $CFG;
        require_once($CFG->libdir . '/modinfolib.php');
        $modinfo = get_fast_modinfo($cquiz->course);
        if (!$modinfo) {
            return 0;
        }
        $cms = $modinfo->get_cms();
        foreach ($cms as $cm) {
            if ($cm->name == $cquiz->helpforum) {
                return $cm->id;
            }
        }
        return 0;
    }

    /**
     * Renders the main bit of the review page.
     *
     * @param array $summarydata contain row data for table
     * @param int $page current page number
     * @param mod_cquiz_display_options $displayoptions instance of mod_cquiz_display_options
     * @param $content contains each question
     * @param cquiz_attempt $attemptobj instance of cquiz_attempt
     * @param bool $showall if true display attempt on one page
     */
    public function review_form($page, $showall, $displayoptions, $content, $attemptobj) {
        if ($displayoptions->flags != question_display_options::EDITABLE) {
            return $content;
        }
        $output = '';
        $this->page->requires->js_init_call('M.mod_cquiz.init_review_form', null, false, cquiz_get_js_module());
        $output .= html_writer::start_tag('form', array('action' => $attemptobj->review_url(null, $page, $showall), 'method' => 'post', 'class' => 'questionflagsaveform'));
        $output .= html_writer::start_tag('div');
        $output .= $content;
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey',
                    'value' => sesskey()));
        $output .= html_writer::start_tag('div', array('class' => 'submitbtns'));
        $output .= html_writer::empty_tag('input', array('type' => 'submit',
                    'class' => 'questionflagsavebutton', 'name' => 'savingflags',
                    'value' => get_string('saveflags', 'question')));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');
        return $output;
    }

    /**
     * Returns either a liink or button
     *
     * @param cquiz_attempt $attemptobj instance of cquiz_attempt
     */
    public function finish_review_link(cquiz_attempt $attemptobj) {
        $url = $attemptobj->view_url();

        if ($attemptobj->get_access_manager(time())->attempt_must_be_in_popup()) {
            $this->page->requires->js_init_call('M.mod_cquiz.secure_window.init_close_button', array($url), false, cquiz_get_js_module());
            return html_writer::empty_tag('input', array('type' => 'button',
                        'value' => get_string('finishreview', 'cquiz'),
                        'id' => 'secureclosebutton',
                        'class' => 'mod_cquiz-next-nav'));
        } else {
            return html_writer::link($url, get_string('finishreview', 'cquiz'), array('class' => 'mod_cquiz-next-nav btn'));
        }
    }

    /**
     * Creates the navigation links/buttons at the bottom of the reivew attempt page.
     *
     * Note, the name of this function is no longer accurate, but when the design
     * changed, it was decided to keep the old name for backwards compatibility.
     *
     * @param cquiz_attempt $attemptobj instance of cquiz_attempt
     * @param int $page the current page
     * @param bool $lastpage if true current page is the last page
     * @param bool|null $showall if true, the URL will be to review the entire attempt on one page,
     *      and $page will be ignored. If null, a sensible default will be chosen.
     *
     * @return string HTML fragment.
     */
    public function review_next_navigation(cquiz_attempt $attemptobj, $page, $lastpage, $showall = null) {
        $nav = '';
        if ($page > 0) {
            $nav .= link_arrow_left(get_string('navigateprevious', 'cquiz'), $attemptobj->review_url(null, $page - 1, $showall), false, 'mod_cquiz-prev-nav');
        }
        if ($lastpage) {
            $nav .= $this->finish_review_link($attemptobj);
        } else {
            $nav .= link_arrow_right(get_string('navigatenext', 'cquiz'), $attemptobj->review_url(null, $page + 1, $showall), false, 'mod_cquiz-next-nav');
        }
        return html_writer::tag('div', $nav, array('class' => 'submitbtns row mt-16pt'));
    }

    /**
     * Return the HTML of the cquiz timer.
     * @return string HTML content.
     */
    public function countdown_timer(cquiz_attempt $attemptobj, $timenow, $showprogress = true) {
        $timeleft = $attemptobj->get_time_left_display($timenow);
        if ($timeleft !== false) {
            $ispreview = $attemptobj->is_preview();
            $timerstartvalue = $timeleft;
            if (!$ispreview) {
                // Make sure the timer starts just above zero. If $timeleft was <= 0, then
                // this will just have the effect of causing the cquiz to be submitted immediately.
                $timerstartvalue = max($timerstartvalue, 1);
            }
            $this->initialise_timer($timerstartvalue, $attemptobj->get_cquiz()->timelimit, $ispreview);
        }
        $output = '';
        if ($showprogress) {
            $output .= html_writer::start_div('time-tracker card');
            $output .= html_writer::start_div('card-body d-flex flex-row align-items-center');
            $timerheader = html_writer::tag('p', html_writer::tag('strong', get_string('timeleft', 'mod_cquiz')), array(
                        'class' => 'card-title d-flex align-items-center',
            ));
            $output .= html_writer::tag('div', $timerheader .
                            html_writer::tag('span', '', array('id' => 'cquiz-time-left', 'class' => 'h4 text-50 font-weight-light m-0')), array('id' => 'cquiz-timer', 'role' => 'timer',
                        'aria-atomic' => 'true', 'aria-relevant' => 'text', 'class' => 'flex'));
            $output .= html_writer::start_tag('i', array(
                        'class' => 'icon fa fa-clock-o',
                        'style' => 'font-size: xx-large;'
            ));
            $output .= html_writer::end_tag('i');
            $output .= html_writer::end_div();
            $output .= html_writer::start_div('progress', array(
                        'style' => 'height: 3px;',
            ));
            $output .= html_writer::start_div('progress-bar', array(
                        'id' => 'cquiz-elapsed-perc',
                        'role' => 'progressbar',
                        'style' => 'width: 0%; background-color: #FF0036;',
                        'aria-valuenow' => 0,
                        'aria-valuemin' => '0',
                        'aria-valuemax' => '100',
            ));
            $output .= html_writer::end_div();
            $output .= html_writer::end_div();
            $output .= html_writer::end_div();
        } else {
            $output .= html_writer::start_div('', array(
                        'id' => 'cquiz-timer',
                        'role' => 'timer',
                        'aria-atomic' => 'true',
                        'aria-relevant' => 'text',
                        'style' => 'display: block;',
            ));
            $output .= get_string('timeleft', 'mod_cquiz') . ' ';
            $output .= html_writer::empty_tag('span', array(
                        'id' => 'cquiz-time-left',
            ));
            $output .= html_writer::end_div();
        }

        return $output;
    }

    /**
     * Create a preview link
     *
     * @param $url contains a url to the given page
     */
    public function restart_preview_button($url) {
        return $this->single_button($url, get_string('startnewpreview', 'cquiz'));
    }

    /**
     * Outputs the navigation block panel
     *
     * @param cquiz_nav_panel_base $panel instance of cquiz_nav_panel_base
     */
    public function navigation_panel(cquiz_nav_panel_base $panel) {

        $output = '';
        $userpicture = $panel->user_picture();
        if ($userpicture) {
            $fullname = fullname($userpicture->user);
            if ($userpicture->size === true) {
                $fullname = html_writer::div($fullname);
            }
            $output .= html_writer::tag('div', $this->render($userpicture) . $fullname, array('id' => 'user-picture', 'class' => 'clearfix'));
        }
        $output .= $panel->render_before_button_bits($this);

        $bcc = $panel->get_button_container_class();
        $output .= html_writer::start_tag('div', array('class' => "qn_buttons clearfix $bcc"));
        foreach ($panel->get_question_buttons() as $button) {
            $output .= $this->render($button);
        }
        $output .= html_writer::end_tag('div');

        $output .= html_writer::tag('div', $panel->render_end_bits($this), array('class' => 'othernav'));

        $this->page->requires->js_init_call('M.mod_cquiz.nav.init', null, false, cquiz_get_js_module());

        return $output;
    }

    /**
     * Display a cquiz navigation button.
     *
     * @param cquiz_nav_question_button $button
     * @return string HTML fragment.
     */
    protected function render_cquiz_nav_question_button(cquiz_nav_question_button $button) {
        $classes = array('qnbutton', $button->stateclass, $button->navmethod);
        $extrainfo = array();

        if ($button->currentpage) {
            $classes[] = 'thispage';
            $extrainfo[] = get_string('onthispage', 'cquiz');
        }

        // Flagged?
        if ($button->flagged) {
            $classes[] = 'flagged';
            $flaglabel = get_string('flagged', 'question');
        } else {
            $flaglabel = '';
        }
        $extrainfo[] = html_writer::tag('span', $flaglabel, array('class' => 'flagstate'));

        if (is_numeric($button->number)) {
            $qnostring = 'questionnonav';
        } else {
            $qnostring = 'questionnonavinfo';
        }

        $a = new stdClass();
        $a->number = $button->number;
        $a->attributes = implode(' ', $extrainfo);
        $tagcontents = html_writer::tag('span', '', array('class' => 'thispageholder')) .
                html_writer::tag('span', '', array('class' => 'trafficlight')) .
                get_string($qnostring, 'cquiz', $a);
        $tagattributes = array('class' => implode(' ', $classes), 'id' => $button->id,
            'title' => $button->statestring, 'data-cquiz-page' => $button->page);

        if ($button->url) {
            return html_writer::link($button->url, $tagcontents, $tagattributes);
        } else {
            return html_writer::tag('span', $tagcontents, $tagattributes);
        }
    }

    /**
     * Display a cquiz navigation heading.
     *
     * @param cquiz_nav_section_heading $heading the heading.
     * @return string HTML fragment.
     */
    protected function render_cquiz_nav_section_heading(cquiz_nav_section_heading $heading) {
        return $this->heading($heading->heading, 3, 'mod_cquiz-section-heading');
    }

    /**
     * outputs the link the other attempts.
     *
     * @param mod_cquiz_links_to_other_attempts $links
     */
    protected function render_mod_cquiz_links_to_other_attempts(
    mod_cquiz_links_to_other_attempts $links) {
        $attemptlinks = array();
        foreach ($links->links as $attempt => $url) {
            if (!$url) {
                $attemptlinks[] = html_writer::tag('strong', $attempt);
            } else if ($url instanceof renderable) {
                $attemptlinks[] = $this->render($url);
            } else {
                $attemptlinks[] = html_writer::link($url, $attempt);
            }
        }
        return implode(', ', $attemptlinks);
    }

    public function start_attempt_page(cquiz $cquizobj, mod_cquiz_preflight_check_form $mform) {
        $output = '';
        $output .= $this->header();
        $output .= $this->heading(format_string($cquizobj->get_cquiz_name(), true, array("context" => $cquizobj->get_context())));
        $output .= $this->cquiz_intro($cquizobj->get_cquiz(), $cquizobj->get_cm());
        $output .= $mform->render();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Attempt Page
     *
     * @param cquiz_attempt $attemptobj Instance of cquiz_attempt
     * @param int $page Current page number
     * @param cquiz_access_manager $accessmanager Instance of cquiz_access_manager
     * @param array $messages An array of messages
     * @param array $slots Contains an array of integers that relate to questions
     * @param int $id The ID of an attempt
     * @param int $nextpage The number of the next page
     */
    public function attempt_page($attemptobj, $page, $accessmanager, $messages, $slots, $id, $nextpage) {
        global $CFG;

        $output = '';
        $output .= $this->header();
        $cquiz = $attemptobj->get_cquiz();
        $course = $attemptobj->get_course();
        $output .= $this->view_page_prebanner($course, $cquiz);
        $output .= $this->cquiz_notices($messages);
        $output .= $this->attempt_form($attemptobj, $page, $slots, $id, $nextpage);
        $output .= $this->footer();
        return $output;
    }

    /**
     * Returns any notices.
     *
     * @param array $messages
     */
    public function cquiz_notices($messages) {
        if (!$messages) {
            return '';
        }
        return $this->box($this->heading(get_string('accessnoticesheader', 'cquiz'), 3) .
                        $this->access_messages($messages), 'cquizaccessnotices');
    }

    private function new_page_url(string $url, int $newpage) {
        $urlcomponents = parse_url($url);
        $newurl = '';
        $newurl .= $urlcomponents['path'] . '?';
        $querycomponents = explode('&amp;', $urlcomponents['query']);
        $newquerycomponents = array();
        $pagefound = 0;
        foreach ($querycomponents as $querycomponent) {
            if (strpos($querycomponent, 'page=') === false) {
                $newquerycomponents[] = $querycomponent;
            }
        }
        $newquerycomponents[] = 'page=' . $newpage;
        $newurl .= implode('&', $newquerycomponents);
        return $newurl;
    }

    private function print_navigation_bar(cquiz_attempt $attemptobj, int $currentpage, int $numpages) {
        global $PAGE;

        $html = '';
        $html .= '<!-- Navigation bar BEGIN -->';
        $html .= html_writer::start_tag('nav', array(
                    'class' => 'course-nav',
                    'style' => 'margin-bottom: 3rem;'
        ));
        for ($page = 1; $page <= $numpages; ++$page) {
            $slots = $attemptobj->get_slots($page - 1);
            $slot = reset($slots);
            $questionattempt = $attemptobj->get_question_attempt($slot);
            $questionattemptstate = $questionattempt->get_state();
            $questionsanswered = ($questionattemptstate == question_state::$complete);
            $questionlink = $this->new_page_url($PAGE->url, $page - 1);
            $tooltiptext = get_string('question', 'mod_cquiz') . ' ' . $page . ' - ';
            if ($page - 1 == $currentpage) {
                $questionbgclass = 'bg-purple100';
                $tooltiptext .= get_string('youarehere', 'mod_cquiz');
            } else {
                if ($questionsanswered) {
                    $questionbgclass = 'bg-success';
                    $tooltiptext .= get_string('answersaved', 'mod_cquiz');
                } else {
                    $questionbgclass = '';
                    $tooltiptext .= get_string('notanswered', 'mod_cquiz');
                }
            }
            $html .= html_writer::start_tag('a', array(
                        'href' => $questionlink,
                        'data-toggle' => 'tooltip',
                        'class' => $questionbgclass,
                        'data-placement' => 'bottom',
                        'data-title' => $tooltiptext,
                        'data-original-title' => '',
                        'title' => ''
            ));
            if ($page - 1 == $currentpage) {
                $questionicstyle = 'color: rgb(112, 0, 196);';
                $questionicon = 'fa-location-arrow';
            } else {
                if ($questionsanswered) {
                    $questionicstyle = 'color: white';
                    $questionicon = 'fa-check-circle';
                } else {
                    $questionicstyle = 'color: rgba(39, 44, 51, 0.7)';
                    $questionicon = 'fa-question';
                }
            }
            $html .= html_writer::start_tag('i', array(
                        'class' => 'icon fa ' . $questionicon,
                        'style' => 'margin-left: 9px; ' . $questionicstyle
            ));
            $html .= html_writer::end_tag('i');
            $html .= html_writer::end_tag('a');
        }
        $html .= html_writer::end_tag('nav');
        $html .= '<!-- Navigation bar END -->';
        return $html;
    }

    private function page_header(cquiz_attempt $attemptobj, int $slot, int $page) {
        global $CFG;

        $html = '';
        $html .= '<!-- Slot header BEGIN -->';
        $html .= html_writer::start_div('question');
        $html .= html_writer::start_div('bg-primary py-32pt bg-img-1', array(
                    'id' => 'question-header',
                    'style' => 'background-color: #002C2F !important; padding-bottom: 0 !important;'
        ));
        $html .= html_writer::start_div('narrow-page container page__container');
        $cquiz = new cquiz($attemptobj->get_cquiz(), $attemptobj->get_cm(), $attemptobj->get_course());
        $numpages = $cquiz->get_structure()->get_question_count();
        $html .= $this->print_navigation_bar($attemptobj, $page, $numpages);
        $html .= '<!-- Quiz name BEGIN -->';
        $html .= html_writer::start_div('d-flex flex-wrap align-items-end justify-content-end');
        $quizname = $attemptobj->get_cquiz_name();
        $html .= html_writer::tag('h6', $quizname, array(
                    'class' => 'flex'
        ));
        $html .= html_writer::end_div();
        $html .= '<!-- Quiz name END -->';
        $html .= '<!-- Question number BEGIN -->';
        $html .= html_writer::start_div('d-flex flex-wrap align-items-end justify-content-end mb-16pt');
        $html .= html_writer::tag('h1', get_string('question', 'mod_cquiz') .
                        ' ' . ($page + 1) . ' ' .
                        get_string('of', 'mod_cquiz') . ' ' . $numpages, array(
                    'class' => 'text-white flex m-0'
        ));
        $html .= html_writer::end_div();
        $html .= '<!-- Question number END -->';
        $questionattempt = $attemptobj->get_question_attempt($slot);
        $question = $questionattempt->get_question();
        $questiontext = $question->format_text($question->questiontext, $question->questiontextformat, $questionattempt, 'question', 'questiontext', $question->id, false);
        $html .= html_writer::tag('p', $questiontext, array(
                    'class' => 'hero__lead measure-hero-lead text-white-70',
                    'style' => 'font-size: 1rem; width: 100%; max-width: unset;'
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Slot header END -->';
        return $html;
    }

    /**
     * Ouputs the form for making an attempt
     *
     * @param cquiz_attempt $attemptobj
     * @param int $page Current page number
     * @param array $slots Array of integers relating to questions
     * @param int $id ID of the attempt
     * @param int $nextpage Next page number
     */
    public function attempt_form($attemptobj, $page, $slots, $id, $nextpage) {
        global $CFG;

        $output = '';
        foreach ($slots as $slot) {
            $output .= $this->page_header($attemptobj, $slot, $page);
            $output .= '<!-- Question form BEGIN -->';
            $output .= html_writer::start_tag('form', array('action' => $attemptobj->processattempt_url(), 'method' => 'post',
                        'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
                        'id' => 'responseform', 'class' => 'responseform'));
            $output .= '<!-- Form header BEGIN -->';
            $output .= html_writer::start_div('navbar navbar-expand-md navbar-list navbar-light bg-white border-bottom-2', array(
                        'style' => 'white-space: nowrap;'
            ));
            $output .= html_writer::start_div('container page__container narrow-page', array(
                        'style' => 'width: -moz-available; padding-bottom: 0;'
            ));
            $output .= html_writer::start_div('nav navbar-nav ml-sm-auto navbar-list__item');
            $output .= html_writer::start_div('nav-item d-flex flex-column flex-sm-row ml-sm-16pt');
            if ($page > 0) {
                $output .= html_writer::empty_tag('input', array(
                            'type' => 'submit',
                            'name' => 'previous',
                            'value' => get_string('navigateprevious', 'cquiz'),
                            'class' => 'btn',
                            'style' => 'margin-right: 1rem;'));
            }
            $lastpage = $attemptobj->is_last_page($page);
            if ($lastpage) {
                $nextname = 'finishattempt';
                $nextlabel = get_string('endtest', 'cquiz');
            } else {
                $nextname = 'next';
                $nextlabel = get_string('navigatenext', 'cquiz');
            }
            $output .= html_writer::empty_tag('input', array(
                        'type' => 'submit',
                        'name' => $nextname,
                        'value' => $nextlabel,
                        'class' => 'btn'));
            $output .= html_writer::end_div();
            $output .= html_writer::end_div();
            $output .= html_writer::end_div();
            $output .= html_writer::end_div();
            $output .= '<!-- Form header END -->';
            $output .= '<!-- Form content BEGIN -->';
            $output .= html_writer::start_div('narrow-page');
            $output .= html_writer::start_div('row');
            $output .= html_writer::start_div('col-lg-8');
            $output .= html_writer::start_div('', array(
                        'style' => 'width: 100%; display: inline-flex; text-transform: uppercase; font-size: .9375rem; color: #303840; font-weight: 600; letter-spacing: 2px; font-family: Typo-Round-Bold\ 2, Helvetica Neue, Arial, sans-serif; border-bottom: 1px dotted #303840; padding-bottom: .4rem;'
            ));
            $output .= get_string('youranswer', 'mod_cquiz');
            $output .= html_writer::end_div();
            $output .= $attemptobj->render_question($slot, false, $this, $attemptobj->attempt_url($slot, $page), $this);
            $output .= html_writer::end_div();
            $output .= html_writer::start_div('col-lg-4');
            $output .= html_writer::start_div('', array(
                        'style' => 'width: 100%; display: inline-flex; text-transform: uppercase; font-size: .9375rem; color: #303840; font-weight: 600; letter-spacing: 2px; font-family: Typo-Round-Bold\ 2, Helvetica Neue, Arial, sans-serif; border-bottom: 1px dotted #303840; padding-bottom: .4rem;'
            ));
            $output .= get_string('informations', 'mod_cquiz');
            $output .= html_writer::end_div();
            $output .= $this->attempt_navigation_buttons($attemptobj, $slot, $page, $attemptobj->is_last_page($page));
            $output .= html_writer::end_div();
            // Some hidden fields to trach what is going on.
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'attempt',
                        'value' => $attemptobj->get_attemptid()));
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'thispage',
                        'value' => $page, 'id' => 'followingpage'));
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'nextpage',
                        'value' => $nextpage));
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'timeup',
                        'value' => '0', 'id' => 'timeup'));
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey',
                        'value' => sesskey()));
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'scrollpos',
                        'value' => '', 'id' => 'scrollpos'));
            // Add a hidden field with questionids. Do this at the end of the form, so
            // if you navigate before the form has finished loading, it does not wipe all
            // the student's answers.
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slots',
                        'value' => implode(',', $attemptobj->get_active_slots($page))));
            // Finish the form.
            $output .= html_writer::end_div();
            $output .= html_writer::end_div();
            $output .= '<!-- Form content END -->';
            $output .= html_writer::end_tag('form');
            $output .= '<!-- Question form END -->';
        }
        $output .= $this->connection_warning();
        return $output;
    }

    private function question_status(cquiz_attempt $attemptobj, int $slot) {
        $html = '';

        // Question status
        $state = $attemptobj->get_question_attempt($slot)->get_state();
        $html .= html_writer::start_div('alert alert-light border-1 border-left-4 ' .
                        ($state != question_state::$complete ? 'border-left-danger' :
                        'border-left-success'), array(
                    'role' => 'alert',
        ));
        $html .= html_writer::start_div('d-flex flex-wrap align-items-start');
        $html .= html_writer::start_div('mr-8pt');
        if ($state != question_state::$complete) {
            $html .= html_writer::start_tag('i', array(
                        'class' => 'icon fa fa-exclamation',
                        'style' => 'color: #d9534f;'
            ));
            $html .= html_writer::end_tag('i');
        }
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('flex');
        $html .= html_writer::start_tag('small', array(
                    'class' => 'text-100',
        ));
        $html .= $attemptobj->get_question_attempt($slot)->get_state_string($state);
        $html .= html_writer::end_tag('small');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        // Question value
        $html .= html_writer::start_div('alert alert-light border-1 border-left-4 border-left-primary', array(
                    'role' => 'alert',
        ));
        $html .= html_writer::start_div('d-flex flex-wrap align-items-start');
        $html .= html_writer::start_div('mr-8pt');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('flex');
        $html .= html_writer::start_tag('small', array(
                    'class' => 'text-100',
        ));
        $mark = $attemptobj->get_question_attempt($slot)->get_max_mark();
        $html .= html_writer::tag('strong', get_string('value', 'mod_cquiz') .
                        ': ') . $mark . ' ' . get_string('point', 'mod_cquiz') . '.';
        $html .= html_writer::end_tag('small');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Display the prev/next buttons that go at the bottom of each page of the attempt.
     *
     * @param int $page the page number. Starts at 0 for the first page.
     * @param bool $lastpage is this the last page in the cquiz?
     * @return string HTML fragment.
     */
    protected function attempt_navigation_buttons($attemptobj, $slot, $page, $lastpage) {
        $output = '';
        $output .= html_writer::start_tag('div', array('class' => 'submitbtns d-flex flex-column col-lg-4 pt-24pt'));
        $cquiz = $attemptobj->get_cquiz();
        $timelimit = $cquiz->timelimit;
        if ($timelimit > 0) {
            $output .= $this->countdown_timer($attemptobj, time(), true /* $showprogress */);
        }
        $output .= $this->question_status($attemptobj, $slot);
        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * Render a button which allows students to redo a question in the attempt.
     *
     * @param int $slot the number of the slot to generate the button for.
     * @param bool $disabled if true, output the button disabled.
     * @return string HTML fragment.
     */
    public function redo_question_button($slot, $disabled) {
        $attributes = array('type' => 'submit', 'name' => 'redoslot' . $slot,
            'value' => get_string('redoquestion', 'cquiz'), 'class' => 'mod_cquiz-redo_question_button');
        if ($disabled) {
            $attributes['disabled'] = 'disabled';
        }
        return html_writer::div(html_writer::empty_tag('input', $attributes));
    }

    /**
     * Output the JavaScript required to initialise the countdown timer.
     * @param int $timerstartvalue time remaining, in seconds.
     * @param int $maxtimer Timer max value, in seconds
     */
    public function initialise_timer($timerstartvalue, $maxtimer, $ispreview) {
        $options = array($timerstartvalue, $maxtimer, (bool) $ispreview);
        $this->page->requires->js_init_call('M.mod_cquiz.timer.init', $options, false, cquiz_get_js_module());
    }

    /**
     * Output a page with an optional message, and JavaScript code to close the
     * current window and redirect the parent window to a new URL.
     * @param moodle_url $url the URL to redirect the parent window to.
     * @param string $message message to display before closing the window. (optional)
     * @return string HTML to output.
     */
    public function close_attempt_popup($url, $message = '') {
        $output = '';
        $output .= $this->header();
        $output .= $this->box_start();

        if ($message) {
            $output .= html_writer::tag('p', $message);
            $output .= html_writer::tag('p', get_string('windowclosing', 'cquiz'));
            $delay = 5;
        } else {
            $output .= html_writer::tag('p', get_string('pleaseclose', 'cquiz'));
            $delay = 0;
        }
        $this->page->requires->js_init_call('M.mod_cquiz.secure_window.close', array($url, $delay), false, cquiz_get_js_module());

        $output .= $this->box_end();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Print each message in an array, surrounded by &lt;p>, &lt;/p> tags.
     *
     * @param array $messages the array of message strings.
     * @param bool $return if true, return a string, instead of outputting.
     *
     * @return string HTML to output.
     */
    public function access_messages($messages) {
        $output = '';
        foreach ($messages as $message) {
            $output .= html_writer::tag('p', $message) . "\n";
        }
        return $output;
    }

    /*
     * Summary Page
     */

    private function summary_page_banner($cquizname) {
        $html = '';
        $html .= html_writer::start_div('bg-img-1 pb-lg-64pt py-32pt', array(
                    'id' => 'banner'
        ));
        $html .= html_writer::start_div('narrow-page container page__container');
        $html .= html_writer::start_div('align-items-end justify-content-end mb-16pt text-center');
        $html .= html_writer::tag('h1', $cquizname, array(
                    'class' => 'text-white flex m-0'
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Create the summary page
     *
     * @param cquiz_attempt $attemptobj
     * @param mod_cquiz_display_options $displayoptions
     */
    public function summary_page($attemptobj, $displayoptions) {
        $output = '';
        $output .= $this->summary_page_banner($attemptobj->get_cquiz_name());
        $output .= $this->heading(format_string($attemptobj->get_cquiz_name()));
        $output .= $this->heading(get_string('summaryofattempt', 'cquiz'), 3);
        $output .= html_writer::start_div('narrow-page');
        $output .= $this->summary_table($attemptobj, $displayoptions);
        $output .= html_writer::start_tag('div', array('class' => 'submitbtns d-flex flex-column col-lg-4 pt-24pt'));
        $output .= $this->summary_page_controls($attemptobj);
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * Generates the table of summarydata
     *
     * @param cquiz_attempt $attemptobj
     * @param mod_cquiz_display_options $displayoptions
     */
    public function summary_table($attemptobj, $displayoptions) {
        // Prepare the summary table header.
        $table = new html_table();
        $table->attributes['class'] = 'generaltable cquizsummaryofattempt boxaligncenter';
        $table->head = array(get_string('question', 'cquiz'), get_string('status', 'cquiz'));
        $table->align = array('left', 'left');
        $table->size = array('', '');
        $markscolumn = $displayoptions->marks >= question_display_options::MARK_AND_MAX;
        if ($markscolumn) {
            $table->head[] = get_string('marks', 'cquiz');
            $table->align[] = 'left';
            $table->size[] = '';
        }
        $tablewidth = count($table->align);
        $table->data = array();

        // Get the summary info for each question.
        $slots = $attemptobj->get_slots();
        foreach ($slots as $slot) {
            // Add a section headings if we need one here.
            $heading = $attemptobj->get_heading_before_slot($slot);
            if ($heading) {
                $cell = new html_table_cell(format_string($heading));
                $cell->header = true;
                $cell->colspan = $tablewidth;
                $table->data[] = array($cell);
                $table->rowclasses[] = 'cquizsummaryheading';
            }

            // Don't display information items.
            if (!$attemptobj->is_real_question($slot)) {
                continue;
            }

            // Real question, show it.
            $flag = '';
            if ($attemptobj->is_question_flagged($slot)) {
                $flag = html_writer::empty_tag('img', array('src' => $this->image_url('i/flagged'),
                            'alt' => get_string('flagged', 'question'), 'class' => 'questionflag icon-post'));
            }
            if ($attemptobj->can_navigate_to($slot)) {
                $row = array(html_writer::link($attemptobj->attempt_url($slot), $attemptobj->get_question_number($slot) . $flag),
                    $attemptobj->get_question_status($slot, $displayoptions->correctness));
            } else {
                $row = array($attemptobj->get_question_number($slot) . $flag,
                    $attemptobj->get_question_status($slot, $displayoptions->correctness));
            }
            if ($markscolumn) {
                $row[] = $attemptobj->get_question_mark($slot);
            }
            $table->data[] = $row;
            $table->rowclasses[] = 'cquizsummary' . $slot . ' ' . $attemptobj->get_question_state_class(
                            $slot, $displayoptions->correctness);
        }

        // Print the summary table.
        $output = html_writer::table($table);

        return $output;
    }

    /**
     * Creates any controls a the page should have.
     *
     * @param cquiz_attempt $attemptobj
     */
    public function summary_page_controls($attemptobj) {
        $output = '';

        // Finish attempt button.
        $options = array(
            'attempt' => $attemptobj->get_attemptid(),
            'finishattempt' => 1,
            'timeup' => 0,
            'slots' => '',
            'sesskey' => sesskey(),
        );

        $finishurl = new moodle_url($attemptobj->processattempt_url(), $options);
        $output .= html_writer::tag('a', get_string('submitallandfinish', 'cquiz'), array(
                    'class' => 'btn mb-16pt',
                    'href' => $finishurl,
        ));

        // Return to place button.
        if ($attemptobj->get_state() == cquiz_attempt::IN_PROGRESS) {
            $returnurl = new moodle_url($attemptobj->attempt_url(null, $attemptobj->get_currentpage()));
            $output .= html_writer::tag('a', get_string('returnattempt', 'cquiz'), array(
                        'class' => 'btn mb-16pt',
                        'href' => $returnurl,
            ));
        }

        $output .= $this->countdown_timer($attemptobj, time());

        return $output;
    }

    /*
     * View Page
     */

    private function view_page_banner($cquiz, $sectionnavbar, $viewobj) {
        $html = '';
        $html .= '<!-- Banner BEGIN -->';
        $stylebanner = 'background-color: #042121 !important; background-image: none !important; padding-bottom: 1rem !important; padding-top: 4rem !important;';
        $html .= html_writer::start_div('bg-img-1 pb-lg-64pt py-32pt', array(
                    'id' => 'banner',
                    'style' => $stylebanner
        ));
        $html .= html_writer::start_div('narrow-page container page__container');
        $html .= $sectionnavbar;
        $html .= html_writer::start_div('align-items-end justify-content-end mb-16pt text-center');
        $styletitle = 'margin-bottom: 3rem !important;';
        $html .= html_writer::tag('h2', get_string('testyourabilities', 'mod_cquiz'), array(
                    'class' => 'text-white flex m-0',
                    'style' => $styletitle
        ));
        $stylename = 'margin-bottom: 2rem !important; color: rgb(205, 254, 253) !important; line-height: normal;';
        $html .= html_writer::tag('h1', $this->get_session_name($cquiz) . ': ' . $cquiz->name, array(
                    'class' => 'text-white flex m-0',
                    'style' => $stylename
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('d-flex flex-wrap mb-16pt align-items-center justify-content-center');
        $html .= $this->box($this->view_page_buttons($viewobj), 'cquizattempt');
        $courseurl = new moodle_url('/course/view.php', array(
            'id' => $cquiz->course
        ));
        $html .= html_writer::start_tag('a', array(
                    'href' => $courseurl,
                    'class' => 'btn btn-outline-white btn-rounded ml-sm-16pt',
                    'style' => 'margin-top: 17px;'
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-chevron-left'
        ));
        $html .= html_writer::end_tag('i');
        $html .= get_string('back');
        $html .= html_writer::end_tag('a');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Banner END -->';
        return $html;
    }

    private function get_session_name($cquiz) {
        global $DB;

        $coursemodule = $DB->get_record('course_modules', array(
            'id' => $cquiz->cmid
        ));
        $session = $DB->get_record('course_sections', array(
            'id' => $coursemodule->section
        ));
        return strip_tags($session->name);
    }

    private function view_page_prebanner($course, $cquiz) {
        $html = '';
        $html .= '<!-- Pre-banner BEGIN -->';
        $html .= html_writer::start_div('', array(
                    'id' => 'prebanner'
        ));
        $html .= html_writer::start_div('d-flex align-items-center narrow-page');
        $html .= html_writer::start_div('mr-16pt');
        $html .= html_writer::start_tag('span');
        $html .= html_writer::tag('img', '', array(
                    'src' => '/mod/cquiz/pix/icon-cquiz.png',
                    'width' => '40',
                    'alt' => 'Quiz',
                    'class' => 'rounded'
        ));
        $html .= html_writer::end_tag('span');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('flex');
        $quiztext = get_string('cquiz', 'mod_cquiz');
        $html .= html_writer::tag('span', $quiztext, array(
                    'style' => 'font-weight: 500; font-size: 1rem; font-family: Typo-Round-Bold\ 2, Helvetica Neue, Arial, sans-serif; color: #272c33 !important;'
        ));
        $html .= html_writer::start_tag('p', array(
                    'class' => 'lh-1 d-flex align-items-center mb-0'
        ));
        $coursetitle = $course->fullname;
        $html .= html_writer::tag('span', $coursetitle, array(
                    'class' => 'text-50 small font-weight-bold mr-8pt'
        ));
        $html .= html_writer::end_tag('p');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Pre-banner END -->';
        return $html;
    }

    private function get_num_questions($cquizid) {
        global $DB;

        $sql = 'SELECT count(*) AS numslots '
                . 'FROM {cquiz_slots} '
                . 'WHERE cquizid = :cquizid;';
        $records = $DB->get_records_sql($sql, array(
            'cquizid' => $cquizid
        ));
        $record = reset($records);
        return $record->numslots;
    }

    private function view_footer_info($cquiz, $viewobj) {
        $html = '';
        $html .= html_writer::start_div('narrow-page', array(
                    'style' => 'padding-bottom: 4rem;'
        ));

        $html .= '<!-- Lets go BEGIN -->';
        $html .= html_writer::start_div('', array(
                    'style' => 'padding-top: 3.5rem; padding-bottom: 2rem; border-bottom: 2px solid #e9edf2 !important; border-top: 2px solid #e9edf2 !important;'
        ));
        $html .= html_writer::start_div('container page__container', array(
                    'style' => 'padding-right: 1.5rem !important; padding-left: 1.5rem !important;'
        ));
        $html .= html_writer::start_div('card');
        $html .= html_writer::tag('img', '', array(
                    'src' => '/mod/cquiz/pix/background-blur.jpg',
                    'alt' => 'blur',
                    'class' => 'card-img',
                    'style' => 'max-height: 100%; width: initial;'
        ));
        $html .= html_writer::div('', 'fullbleed', array(
                    'style' => 'opacity: .5; background-color: #002C2F !important;'
        ));
        $html .= html_writer::tag('img', '', array(
                    'src' => '/mod/cquiz/pix/logo-color.svg',
                    'width' => '64',
                    'alt' => 'logo',
                    'class' => 'rounded position-absolute',
                    'style' => 'right: 1rem; top: 1rem;'
        ));
        $html .= html_writer::start_div('card-body d-flex align-items-center justify-content-center', array(
                    'style' => 'position: absolute; background-color: transparent; align-self: center; margin-top: 5rem;')
        );
        $html .= html_writer::start_div();
        $letsgotext = get_string('letsgo', 'mod_cquiz');
        $html .= html_writer::tag('h2', $letsgotext, array(
                    'class' => 'text-white mb-16pt'
        ));
        $html .= html_writer::start_div('d-flex align-items-center mb-16pt justify-content-center');
        $timelimit = $cquiz->timelimit;
        if ($timelimit > 0) {
            $html .= html_writer::start_div('d-flex align-items-center mr-16pt');
            $html .= html_writer::start_tag('i', array(
                        'class' => 'icon fa fa-clock-o',
                        'style' => 'color: rgb(255, 255, 255);'
            ));
            $html .= html_writer::end_tag('i');
            $timelimitstr = $this->get_duration_str($timelimit);
            $html .= html_writer::tag('p', $timelimitstr, array(
                        'class' => 'flex text-white-50 lh-1 mb-0'
            ));
            $html .= html_writer::end_div();
        }

        $html .= html_writer::start_div('d-flex align-items-center');
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-play-circle-o',
                    'style' => 'color: rgb(255, 255, 255);'
        ));
        $html .= html_writer::end_tag('i');
        $numquestions = $this->get_num_questions($cquiz->id);
        $numquestionstxt = $numquestions . ' ' . get_string('questions', 'mod_cquiz');
        $html .= html_writer::tag('p', $numquestionstxt, array(
                    'class' => 'flex text-white-50 lh-1 mb-0'
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('d-flex flex-wrap mb-16pt align-items-center justify-content-center', array(
                    'style' => 'color: white;'
        ));
        $html .= $this->box($this->view_page_buttons($viewobj), 'cquizattempt');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Lets go END -->';

        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Generates the view page
     *
     * @param int $course The id of the course
     * @param array $cquiz Array conting cquiz data
     * @param int $cm Course Module ID
     * @param int $context The page context ID
     * @param mod_cquiz_view_object $viewobj
     * @param string $sectionnavbar
     */
    public function view_page($course, $cquiz, $cm, $context, $viewobj, $sectionnavbar) {
        global $CFG;

        $output = '';
        $output .= $this->view_page_prebanner($course, $cquiz);
        $output .= $this->view_page_banner($cquiz, $sectionnavbar, $viewobj);
        $output .= $this->view_information($cquiz, $cm, $context, $viewobj->infomessages);
        $output .= $this->view_table($cquiz, $context, $viewobj);
        $output .= $this->view_result_info($cquiz, $context, $cm, $viewobj);
        $output .= $this->view_footer_info($cquiz, $viewobj);
        return $output;
    }

    /**
     * Work out, and render, whatever buttons, and surrounding info, should appear
     * at the end of the review page.
     * @param mod_cquiz_view_object $viewobj the information required to display
     * the view page.
     * @return string HTML to output.
     */
    public function view_page_buttons(mod_cquiz_view_object $viewobj) {
        $output = '';
        if (!$viewobj->cquizhasquestions) {
            $output .= $this->no_questions_message($viewobj->canedit, $viewobj->editurl);
        }
        $output .= $this->access_messages($viewobj->preventmessages);
        if ($viewobj->buttontext) {
            $output .= $this->start_attempt_button($viewobj->buttontext, $viewobj->startattempturl, $viewobj->preflightcheckform, $viewobj->popuprequired, $viewobj->popupoptions);
        }
        if ($viewobj->showbacktocourse) {
            $output .= $this->single_button($viewobj->backtocourseurl, get_string('backtocourse', 'cquiz'), 'get', array('class' => 'continuebutton'));
        }
        return $output;
    }

    /**
     * Generates the view attempt button
     *
     * @param string $buttontext the label to display on the button.
     * @param moodle_url $url The URL to POST to in order to start the attempt.
     * @param mod_cquiz_preflight_check_form $preflightcheckform deprecated.
     * @param bool $popuprequired whether the attempt needs to be opened in a pop-up.
     * @param array $popupoptions the options to use if we are opening a popup.
     * @return string HTML fragment.
     */
    public function start_attempt_button($buttontext, moodle_url $url, mod_cquiz_preflight_check_form $preflightcheckform = null, $popuprequired = false, $popupoptions = null) {

        if (is_string($preflightcheckform)) {
            // Calling code was not updated since the API change.
            debugging('The third argument to start_attempt_button should now be the ' .
                    'mod_cquiz_preflight_check_form from ' .
                    'cquiz_access_manager::get_preflight_check_form, not a warning message string.');
        }

        $button = new single_button($url, $buttontext);
        $button->class .= ' cquizstartbuttondiv';

        $popupjsoptions = null;
        if ($popuprequired && $popupoptions) {
            $action = new popup_action('click', $url, 'popup', $popupoptions);
            $popupjsoptions = $action->get_js_options();
        }

        if ($preflightcheckform) {
            $checkform = $preflightcheckform->render();
        } else {
            $checkform = null;
        }

        $this->page->requires->js_call_amd('mod_cquiz/preflightcheck', 'init', array('.cquizstartbuttondiv input[type=submit]', get_string('startattempt', 'cquiz'),
            '#mod_cquiz_preflight_form', $popupjsoptions));

        return $this->render($button) . $checkform;
    }

    /**
     * Generate a message saying that this cquiz has no questions, with a button to
     * go to the edit page, if the user has the right capability.
     * @param object $cquiz the cquiz settings.
     * @param object $cm the course_module object.
     * @param object $context the cquiz context.
     * @return string HTML to output.
     */
    public function no_questions_message($canedit, $editurl) {
        $output = '';
        $output .= $this->notification(get_string('noquestions', 'cquiz'));
        if ($canedit) {
            $output .= $this->single_button($editurl, get_string('editcquiz', 'cquiz'), 'get');
        }

        return $output;
    }

    /**
     * Outputs an error message for any guests accessing the cquiz
     *
     * @param int $course The course ID
     * @param array $cquiz Array contingin cquiz data
     * @param int $cm Course Module ID
     * @param int $context The page contect ID
     * @param array $messages Array containing any messages
     */
    public function view_page_guest($course, $cquiz, $cm, $context, $messages) {
        $output = '';
        $output .= $this->view_information($cquiz, $cm, $context, $messages);
        $guestno = html_writer::tag('p', get_string('guestsno', 'cquiz'));
        $liketologin = html_writer::tag('p', get_string('liketologin'));
        $referer = get_local_referer(false);
        $output .= $this->confirm($guestno . "\n\n" . $liketologin . "\n", get_login_url(), $referer);
        return $output;
    }

    /**
     * Outputs and error message for anyone who is not enrolle don the course
     *
     * @param int $course The course ID
     * @param array $cquiz Array contingin cquiz data
     * @param int $cm Course Module ID
     * @param int $context The page contect ID
     * @param array $messages Array containing any messages
     */
    public function view_page_notenrolled($course, $cquiz, $cm, $context, $messages) {
        global $CFG;
        $output = '';
        $output .= $this->view_information($cquiz, $cm, $context, $messages);
        $youneedtoenrol = html_writer::tag('p', get_string('youneedtoenrol', 'cquiz'));
        $button = html_writer::tag('p', $this->continue_button($CFG->wwwroot . '/course/view.php?id=' . $course->id));
        $output .= $this->box($youneedtoenrol . "\n\n" . $button . "\n", 'generalbox', 'notice');
        return $output;
    }

    private function get_duration_str($duration) {
        $durationstr = '';
        $numhours = floor($duration / 3600);
        $remainder = $duration % 3600;
        $numminutes = floor($remainder / 60);
        $numseconds = $remainder % 60;
        if ($numhours > 0) {
            if ($numhours > 1) {
                $durationstr .= $numhours . ' ' . get_string('hours');
            } else {
                $durationstr .= $numhours . ' ' . get_string('hour');
            }
        }
        if ($numminutes > 0) {
            if (!empty($durationstr)) {
                if ($numseconds == 0) {
                    $durationstr .= ' ' . get_string('and', 'mod_cquiz') . ' ';
                } else {
                    $durationstr .= ', ';
                }
            }
            if ($numminutes > 1) {
                $durationstr .= $numminutes . ' ' . get_string('minutes');
            } else {
                $durationstr .= $numminutes . ' ' . get_string('minute');
            }
        }
        if ($numseconds > 0) {
            if (!empty($durationstr)) {
                $durationstr .= ' ' . get_string('and', 'mod_cquiz') . ' ';
            }
            if ($numseconds > 1) {
                $durationstr .= $numseconds . ' ' . get_string('seconds', 'mod_cquiz');
            } else {
                $durationstr .= $numseconds . ' ' . get_string('second', 'mod_cquiz');
            }
        }
        return $durationstr;
    }

    private function get_attempts_number_str($numattempts) {
        switch ($numattempts) {
            case 0: return get_string('zero', 'mod_cquiz');
                break;
            case 1: return get_string('one', 'mod_cquiz');
                break;
            case 2: return get_string('two', 'mod_cquiz');
                break;
            case 3: return get_string('three', 'mod_cquiz');
                break;
            case 4: return get_string('four', 'mod_cquiz');
                break;
            case 5: return get_string('five', 'mod_cquiz');
                break;
            case 6: return get_string('six', 'mod_cquiz');
                break;
            case 7: return get_string('seven', 'mod_cquiz');
                break;
            case 8: return get_string('eight', 'mod_cquiz');
                break;
            case 9: return get_string('nine', 'mod_cquiz');
                break;
        }
        return get_string('unknown', 'mod_cquiz');
    }

    private function is_cquiz_required($cmid) {
        global $DB;

        $sql = 'SELECT * '
                . 'FROM {course_completion_criteria} '
                . 'WHERE moduleinstance = :coursemoduleid;';
        $record = $DB->get_record_sql($sql, array('coursemoduleid' => $cmid));
        return ($record != null);
        return true;
    }

    /**
     * Output the page information
     *
     * @param object $cquiz the cquiz settings.
     * @param object $cm the course_module object.
     * @param object $context the cquiz context.
     * @param array $messages any access messages that should be described.
     * @return string HTML to output.
     */
    public function view_information($cquiz, $cm, $context, $messages) {
        global $CFG;

        $html = '';
        $html .= html_writer::start_div('narrow-page container page__container mt-5', array(
                    'id' => 'info',
                    'style' => 'padding-bottom: 3rem !important;'
        ));
        $html .= html_writer::start_div('instructions mb-2');
        $html .= html_writer::tag('h4', get_string('beforeyoustart', 'mod_cquiz'));
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('instructions row');
        $html .= html_writer::start_div('instructions col d-flex');
        $attemptsallowed = $cquiz->attempts;
        if ($attemptsallowed > 1) {
            $html .= html_writer::tag('p', get_string('multipleattemptsinstructions', 'mod_cquiz'), array(
                        'class' => 'text-70 text-center',
                        'id' => 'instructions'
            ));
        } else {
            $html .= html_writer::tag('p', get_string('singleattemptinstructions', 'mod_cquiz'), array(
                        'class' => 'text-70 text-center',
                        'id' => 'instructions'
            ));
        }
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('instructions col flex', array(
                    'style' => 'display: flex; flex-flow: row; justify-content: space-around;'
        ));
        // Time instructions
        $html .= html_writer::start_div('alert alert-soft-warning');
        $html .= html_writer::start_div('d-flex align-items-start');
        $html .= html_writer::start_tag('span', array(
                    'class' => 'icon-holder icon-holder--outline-muted text-white rounded-circle d-inline-flex mr-16pt bg-purple600',
                    'style' => 'background-color: #FD7352 !important; align-items: baseline; padding-top: 12px;'
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-clock-o',
                    'style' => 'color: rgb(255, 255, 255); font-size: 30px;'
        ));
        $html .= html_writer::end_tag('i');
        $html .= html_writer::end_tag('span');
        $html .= html_writer::start_div('flex', array(
                    'style' => 'min-width: 180px'
        ));
        $html .= html_writer::start_tag('small', array(
                    'class' => 'text-white-100'
        ));
        $html .= html_writer::tag('strong', get_string('payattention', 'mod_cquiz') . '<br>');
        $payattentiontxt = get_string('payattentiontxt', 'mod_cquiz');
        $html .= html_writer::tag('span', '<br>' . $payattentiontxt, array(
                    'class' => 'text-white-50'
        ));
        $html .= html_writer::end_tag('small');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        // Grade instructions
        $html .= html_writer::start_div('alert alert-soft-success border-0');
        $html .= html_writer::start_div('d-flex align-items-start');
        $html .= html_writer::start_tag('span', array(
                    'class' => 'icon-holder icon-holder--outline-muted text-white rounded-circle d-inline-flex mr-16pt bg-purple600',
                    'style' => 'background-color: #42B77A !important; align-items: baseline; padding-top: 15px;'
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-check-circle',
                    'style' => 'color: rgb(255, 255, 255); font-size: 26px; margin-left: 2px;'
        ));
        $html .= html_writer::end_tag('i');
        $html .= html_writer::end_tag('span');
        $html .= html_writer::start_div('flex', array(
                    'style' => 'min-width: 180px'
        ));
        $html .= html_writer::start_tag('small', array(
                    'class' => 'text-white-100'
        ));
        $html .= html_writer::tag('strong', get_string('gradingmethod', 'mod_cquiz') . '<br>');
        if ($this->is_cquiz_required($cm->id)) {
            $gradestr = get_string('highestgrade', 'mod_cquiz');
        } else {
            $gradestr = get_string('replaceifhigher', 'mod_cquiz');
        }
        $html .= html_writer::tag('span', '<br>' . $gradestr, array(
                    'class' => 'text-white-70 border-0'
        ));
        $html .= html_writer::end_tag('small');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Output the cquiz intro.
     * @param object $cquiz the cquiz settings.
     * @param object $cm the course_module object.
     * @return string HTML to output.
     */
    public function cquiz_intro($cquiz, $cm) {
        if (html_is_blank($cquiz->intro)) {
            return '';
        }

        return $this->box(format_module_intro('cquiz', $cquiz, $cm->id), 'generalbox', 'intro');
    }

    /**
     * Generates the table heading.
     */
    public function view_table_heading() {
        return $this->heading(get_string('summaryofattempts', 'cquiz'), 3);
    }

    /**
     * Generates the table of data
     *
     * @param array $cquiz Array contining cquiz data
     * @param int $context The page context ID
     * @param mod_cquiz_view_object $viewobj
     */
    public function view_table($cquiz, $context, $viewobj) {
        if (!$viewobj->attempts) {
            return '';
        }

        // Prepare table header.
        $table = new html_table();
        $table->attributes['class'] = 'narrow-page generaltable cquizattemptsummary';
        $table->head = array();
        $table->align = array();
        $table->size = array();
        if ($viewobj->attemptcolumn) {
            $table->head[] = get_string('attemptnumber', 'cquiz');
            $table->align[] = 'center';
            $table->size[] = '';
        }
        $table->head[] = get_string('attemptstate', 'cquiz');
        $table->align[] = 'left';
        $table->size[] = '';
        if ($viewobj->markcolumn) {
            $table->head[] = get_string('marks', 'cquiz') . ' / ' .
                    cquiz_format_grade($cquiz, $cquiz->sumgrades);
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->gradecolumn) {
            $table->head[] = get_string('grade') . ' / ' .
                    cquiz_format_grade($cquiz, $cquiz->grade);
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->canreviewmine) {
            $table->head[] = get_string('review', 'cquiz');
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->feedbackcolumn) {
            $table->head[] = get_string('feedback', 'cquiz');
            $table->align[] = 'left';
            $table->size[] = '';
        }

        // One row for each attempt.
        foreach ($viewobj->attemptobjs as $attemptobj) {
            $attemptoptions = $attemptobj->get_display_options(true);
            $row = array();

            // Add the attempt number.
            if ($viewobj->attemptcolumn) {
                if ($attemptobj->is_preview()) {
                    $row[] = get_string('preview', 'cquiz');
                } else {
                    $row[] = $attemptobj->get_attempt_number();
                }
            }

            $row[] = $this->attempt_state($attemptobj);

            if ($viewobj->markcolumn) {
                if ($attemptoptions->marks >= question_display_options::MARK_AND_MAX &&
                        $attemptobj->is_finished()) {
                    $row[] = cquiz_format_grade($cquiz, $attemptobj->get_sum_marks());
                } else {
                    $row[] = '';
                }
            }

            // Ouside the if because we may be showing feedback but not grades.
            $attemptgrade = cquiz_rescale_grade($attemptobj->get_sum_marks(), $cquiz, false);

            if ($viewobj->gradecolumn) {
                if ($attemptoptions->marks >= question_display_options::MARK_AND_MAX &&
                        $attemptobj->is_finished()) {

                    // Highlight the highest grade if appropriate.
                    if ($viewobj->overallstats && !$attemptobj->is_preview() && $viewobj->numattempts > 1 && !is_null($viewobj->mygrade) && $attemptobj->get_state() == cquiz_attempt::FINISHED && $attemptgrade == $viewobj->mygrade && $cquiz->grademethod == CQUIZ_GRADEHIGHEST) {
                        $table->rowclasses[$attemptobj->get_attempt_number()] = 'bestrow';
                    }

                    $row[] = cquiz_format_grade($cquiz, $attemptgrade);
                } else {
                    $row[] = '';
                }
            }

            if ($viewobj->canreviewmine) {
                $row[] = $viewobj->accessmanager->make_review_link($attemptobj->get_attempt(), $attemptoptions, $this);
            }

            if ($viewobj->feedbackcolumn && $attemptobj->is_finished()) {
                if ($attemptoptions->overallfeedback) {
                    $row[] = cquiz_feedback_for_grade($attemptgrade, $cquiz, $context);
                } else {
                    $row[] = '';
                }
            }

            if ($attemptobj->is_preview()) {
                $table->data['preview'] = $row;
            } else {
                $table->data[$attemptobj->get_attempt_number()] = $row;
            }
        } // End of loop over attempts.

        $output = '';
        $output .= $this->view_table_heading();
        $output .= html_writer::table($table);
        return $output;
    }

    /**
     * Generate a brief textual desciption of the current state of an attempt.
     * @param cquiz_attempt $attemptobj the attempt
     * @param int $timenow the time to use as 'now'.
     * @return string the appropriate lang string to describe the state.
     */
    public function attempt_state($attemptobj) {
        switch ($attemptobj->get_state()) {
            case cquiz_attempt::IN_PROGRESS:
                return get_string('stateinprogress', 'cquiz');

            case cquiz_attempt::OVERDUE:
                return get_string('stateoverdue', 'cquiz') . html_writer::tag('span', get_string('stateoverduedetails', 'cquiz', userdate($attemptobj->get_due_date())), array('class' => 'statedetails'));

            case cquiz_attempt::FINISHED:
                return get_string('statefinished', 'cquiz') . html_writer::tag('span', get_string('statefinisheddetails', 'cquiz', userdate($attemptobj->get_submitted_date())), array('class' => 'statedetails'));

            case cquiz_attempt::ABANDONED:
                return get_string('stateabandoned', 'cquiz');
        }
    }

    /**
     * Generates data pertaining to cquiz results
     *
     * @param array $cquiz Array containing cquiz data
     * @param int $context The page context ID
     * @param int $cm The Course Module Id
     * @param mod_cquiz_view_object $viewobj
     */
    public function view_result_info($cquiz, $context, $cm, $viewobj) {
        $output = '';
        if (!$viewobj->numattempts && !$viewobj->gradecolumn && is_null($viewobj->mygrade)) {
            return $output;
        }
        $resultinfo = '';

        if ($viewobj->overallstats) {
            if ($viewobj->moreattempts) {
                $a = new stdClass();
                $a->method = cquiz_get_grading_option_name($cquiz->grademethod);
                $a->mygrade = cquiz_format_grade($cquiz, $viewobj->mygrade);
                $a->cquizgrade = cquiz_format_grade($cquiz, $cquiz->grade);
                $resultinfo .= $this->heading(get_string('gradesofar', 'cquiz', $a), 3);
            } else {
                $a = new stdClass();
                $a->grade = cquiz_format_grade($cquiz, $viewobj->mygrade);
                $a->maxgrade = cquiz_format_grade($cquiz, $cquiz->grade);
                $a = get_string('outofshort', 'cquiz', $a);
                $resultinfo .= $this->heading(get_string('yourfinalgradeis', 'cquiz', $a), 3);
            }
        }

        if ($viewobj->mygradeoverridden) {

            $resultinfo .= html_writer::tag('p', get_string('overriddennotice', 'grades'), array('class' => 'overriddennotice')) . "\n";
        }
        if ($viewobj->gradebookfeedback) {
            $resultinfo .= $this->heading(get_string('comment', 'cquiz'), 3);
            $resultinfo .= html_writer::div($viewobj->gradebookfeedback, 'cquizteacherfeedback') . "\n";
        }
        if ($viewobj->feedbackcolumn) {
            $resultinfo .= $this->heading(get_string('overallfeedback', 'cquiz'), 3);
            $resultinfo .= html_writer::div(
                            cquiz_feedback_for_grade($viewobj->mygrade, $cquiz, $context), 'cquizgradefeedback') . "\n";
        }

        if ($resultinfo) {
            $output .= $this->box($resultinfo, 'generalbox', 'feedback');
        }
        return $output;
    }

    /**
     * Output either a link to the review page for an attempt, or a button to
     * open the review in a popup window.
     *
     * @param moodle_url $url of the target page.
     * @param bool $reviewinpopup whether a pop-up is required.
     * @param array $popupoptions options to pass to the popup_action constructor.
     * @return string HTML to output.
     */
    public function review_link($url, $reviewinpopup, $popupoptions) {
        if ($reviewinpopup) {
            $button = new single_button($url, get_string('review', 'cquiz'));
            $button->add_action(new popup_action('click', $url, 'cquizpopup', $popupoptions));
            return $this->render($button);
        } else {
            return html_writer::link($url, get_string('review', 'cquiz'), array('title' => get_string('reviewthisattempt', 'cquiz')));
        }
    }

    /**
     * Displayed where there might normally be a review link, to explain why the
     * review is not available at this time.
     * @param string $message optional message explaining why the review is not possible.
     * @return string HTML to output.
     */
    public function no_review_message($message) {
        return html_writer::nonempty_tag('span', $message, array('class' => 'noreviewmessage'));
    }

    /**
     * Returns the same as {@link cquiz_num_attempt_summary()} but wrapped in a link
     * to the cquiz reports.
     *
     * @param object $cquiz the cquiz object. Only $cquiz->id is used at the moment.
     * @param object $cm the cm object. Only $cm->course, $cm->groupmode and $cm->groupingid
     * fields are used at the moment.
     * @param object $context the cquiz context.
     * @param bool $returnzero if false (default), when no attempts have been made '' is returned
     * instead of 'Attempts: 0'.
     * @param int $currentgroup if there is a concept of current group where this method is being
     * called
     *         (e.g. a report) pass it in here. Default 0 which means no current group.
     * @return string HTML fragment for the link.
     */
    public function cquiz_attempt_summary_link_to_reports($cquiz, $cm, $context, $returnzero = false, $currentgroup = 0) {
        global $CFG;
        $summary = cquiz_num_attempt_summary($cquiz, $cm, $returnzero, $currentgroup);
        if (!$summary) {
            return '';
        }

        require_once($CFG->dirroot . '/mod/cquiz/report/reportlib.php');
        $url = new moodle_url('/mod/cquiz/report.php', array(
            'id' => $cm->id, 'mode' => cquiz_report_default_report($context)));
        return html_writer::link($url, $summary);
    }

    /**
     * Output a graph, or a message saying that GD is required.
     * @param moodle_url $url the URL of the graph.
     * @param string $title the title to display above the graph.
     * @return string HTML fragment for the graph.
     */
    public function graph(moodle_url $url, $title) {
        global $CFG;

        $graph = html_writer::empty_tag('img', array('src' => $url, 'alt' => $title));

        return $this->heading($title, 3) . html_writer::tag('div', $graph, array('class' => 'graph'));
    }

    /**
     * Output the connection warning messages, which are initially hidden, and
     * only revealed by JavaScript if necessary.
     */
    public function connection_warning() {
        $options = array('filter' => false, 'newlines' => false);
        $warning = format_text(get_string('connectionerror', 'cquiz'), FORMAT_MARKDOWN, $options);
        $ok = format_text(get_string('connectionok', 'cquiz'), FORMAT_MARKDOWN, $options);
        return html_writer::tag('div', $warning, array('id' => 'connection-error', 'style' => 'display: none;', 'role' => 'alert')) .
                html_writer::tag('div', $ok, array('id' => 'connection-ok', 'style' => 'display: none;', 'role' => 'alert'));
    }

}

class mod_cquiz_links_to_other_attempts implements renderable {

    /**
     * @var array string attempt number => url, or null for the current attempt.
     * url may be either a moodle_url, or a renderable.
     */
    public $links = array();

}

class mod_cquiz_view_object {

    /** @var array $infomessages of messages with information to display about the cquiz. */
    public $infomessages;

    /** @var array $attempts contains all the user's attempts at this cquiz. */
    public $attempts;

    /** @var array $attemptobjs cquiz_attempt objects corresponding to $attempts. */
    public $attemptobjs;

    /** @var cquiz_access_manager $accessmanager contains various access rules. */
    public $accessmanager;

    /** @var bool $canreviewmine whether the current user has the capability to
     *       review their own attempts. */
    public $canreviewmine;

    /** @var bool $canedit whether the current user has the capability to edit the cquiz. */
    public $canedit;

    /** @var moodle_url $editurl the URL for editing this cquiz. */
    public $editurl;

    /** @var int $attemptcolumn contains the number of attempts done. */
    public $attemptcolumn;

    /** @var int $gradecolumn contains the grades of any attempts. */
    public $gradecolumn;

    /** @var int $markcolumn contains the marks of any attempt. */
    public $markcolumn;

    /** @var int $overallstats contains all marks for any attempt. */
    public $overallstats;

    /** @var string $feedbackcolumn contains any feedback for and attempt. */
    public $feedbackcolumn;

    /** @var string $timenow contains a timestamp in string format. */
    public $timenow;

    /** @var int $numattempts contains the total number of attempts. */
    public $numattempts;

    /** @var float $mygrade contains the user's final grade for a cquiz. */
    public $mygrade;

    /** @var bool $moreattempts whether this user is allowed more attempts. */
    public $moreattempts;

    /** @var int $mygradeoverridden contains an overriden grade. */
    public $mygradeoverridden;

    /** @var string $gradebookfeedback contains any feedback for a gradebook. */
    public $gradebookfeedback;

    /** @var bool $unfinished contains 1 if an attempt is unfinished. */
    public $unfinished;

    /** @var object $lastfinishedattempt the last attempt from the attempts array. */
    public $lastfinishedattempt;

    /** @var array $preventmessages of messages telling the user why they can't
     *       attempt the cquiz now. */
    public $preventmessages;

    /** @var string $buttontext caption for the start attempt button. If this is null, show no
     *      button, or if it is '' show a back to the course button. */
    public $buttontext;

    /** @var moodle_url $startattempturl URL to start an attempt. */
    public $startattempturl;

    /** @var moodleform|null $preflightcheckform confirmation form that must be
     *       submitted before an attempt is started, if required. */
    public $preflightcheckform;

    /** @var moodle_url $startattempturl URL for any Back to the course button. */
    public $backtocourseurl;

    /** @var bool $showbacktocourse should we show a back to the course button? */
    public $showbacktocourse;

    /** @var bool whether the attempt must take place in a popup window. */
    public $popuprequired;

    /** @var array options to use for the popup window, if required. */
    public $popupoptions;

    /** @var bool $cquizhasquestions whether the cquiz has any questions. */
    public $cquizhasquestions;

    public function __get($field) {
        switch ($field) {
            case 'startattemptwarning':
                debugging('startattemptwarning has been deprecated. It is now always blank.');
                return '';

            default:
                debugging('Unknown property ' . $field);
                return null;
        }
    }

}

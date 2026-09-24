<?php

/**
 * Unit tests for (some of) mod/cquiz/locallib.php.
 *
 * @package    mod_cquiz
 * @category   test
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/cquiz/locallib.php');

/**
 * Unit tests for (some of) mod/cquiz/locallib.php.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class mod_cquiz_locallib_testcase extends advanced_testcase {

    public function test_cquiz_rescale_grade() {
        $cquiz = new stdClass();
        $cquiz->decimalpoints = 2;
        $cquiz->questiondecimalpoints = 3;
        $cquiz->grade = 10;
        $cquiz->sumgrades = 10;
        $this->assertEquals(cquiz_rescale_grade(0.12345678, $cquiz, false), 0.12345678);
        $this->assertEquals(cquiz_rescale_grade(0.12345678, $cquiz, true), format_float(0.12, 2));
        $this->assertEquals(cquiz_rescale_grade(0.12345678, $cquiz, 'question'), format_float(0.123, 3));
        $cquiz->sumgrades = 5;
        $this->assertEquals(cquiz_rescale_grade(0.12345678, $cquiz, false), 0.24691356);
        $this->assertEquals(cquiz_rescale_grade(0.12345678, $cquiz, true), format_float(0.25, 2));
        $this->assertEquals(cquiz_rescale_grade(0.12345678, $cquiz, 'question'), format_float(0.247, 3));
    }

    public function test_cquiz_attempt_state_in_progress() {
        $attempt = new stdClass();
        $attempt->state = cquiz_attempt::IN_PROGRESS;
        $attempt->timefinish = 0;

        $cquiz = new stdClass();
        $cquiz->timeclose = 0;

        $this->assertEquals(mod_cquiz_display_options::DURING, cquiz_attempt_state($cquiz, $attempt));
    }

    public function test_cquiz_attempt_state_recently_submitted() {
        $attempt = new stdClass();
        $attempt->state = cquiz_attempt::FINISHED;
        $attempt->timefinish = time() - 10;

        $cquiz = new stdClass();
        $cquiz->timeclose = 0;

        $this->assertEquals(mod_cquiz_display_options::IMMEDIATELY_AFTER, cquiz_attempt_state($cquiz, $attempt));
    }

    public function test_cquiz_attempt_state_sumitted_cquiz_never_closes() {
        $attempt = new stdClass();
        $attempt->state = cquiz_attempt::FINISHED;
        $attempt->timefinish = time() - 7200;

        $cquiz = new stdClass();
        $cquiz->timeclose = 0;

        $this->assertEquals(mod_cquiz_display_options::LATER_WHILE_OPEN, cquiz_attempt_state($cquiz, $attempt));
    }

    public function test_cquiz_attempt_state_sumitted_cquiz_closes_later() {
        $attempt = new stdClass();
        $attempt->state = cquiz_attempt::FINISHED;
        $attempt->timefinish = time() - 7200;

        $cquiz = new stdClass();
        $cquiz->timeclose = time() + 3600;

        $this->assertEquals(mod_cquiz_display_options::LATER_WHILE_OPEN, cquiz_attempt_state($cquiz, $attempt));
    }

    public function test_cquiz_attempt_state_sumitted_cquiz_closed() {
        $attempt = new stdClass();
        $attempt->state = cquiz_attempt::FINISHED;
        $attempt->timefinish = time() - 7200;

        $cquiz = new stdClass();
        $cquiz->timeclose = time() - 3600;

        $this->assertEquals(mod_cquiz_display_options::AFTER_CLOSE, cquiz_attempt_state($cquiz, $attempt));
    }

    public function test_cquiz_attempt_state_never_sumitted_cquiz_never_closes() {
        $attempt = new stdClass();
        $attempt->state = cquiz_attempt::ABANDONED;
        $attempt->timefinish = 1000; // A very long time ago!

        $cquiz = new stdClass();
        $cquiz->timeclose = 0;

        $this->assertEquals(mod_cquiz_display_options::LATER_WHILE_OPEN, cquiz_attempt_state($cquiz, $attempt));
    }

    public function test_cquiz_attempt_state_never_sumitted_cquiz_closes_later() {
        $attempt = new stdClass();
        $attempt->state = cquiz_attempt::ABANDONED;
        $attempt->timefinish = time() - 7200;

        $cquiz = new stdClass();
        $cquiz->timeclose = time() + 3600;

        $this->assertEquals(mod_cquiz_display_options::LATER_WHILE_OPEN, cquiz_attempt_state($cquiz, $attempt));
    }

    public function test_cquiz_attempt_state_never_sumitted_cquiz_closed() {
        $attempt = new stdClass();
        $attempt->state = cquiz_attempt::ABANDONED;
        $attempt->timefinish = time() - 7200;

        $cquiz = new stdClass();
        $cquiz->timeclose = time() - 3600;

        $this->assertEquals(mod_cquiz_display_options::AFTER_CLOSE, cquiz_attempt_state($cquiz, $attempt));
    }

    public function test_cquiz_question_tostring() {
        $question = new stdClass();
        $question->qtype = 'multichoice';
        $question->name = 'The question name';
        $question->questiontext = '<p>What sort of <b>inequality</b> is x &lt; y<img alt="?" src="..."></p>';
        $question->questiontextformat = FORMAT_HTML;

        $summary = cquiz_question_tostring($question);
        $this->assertEquals('<span class="questionname">The question name</span> ' .
                '<span class="questiontext">What sort of INEQUALITY is x &lt; y[?]' . "\n" . '</span>', $summary);
    }

    /**
     * Test cquiz_view
     * @return void
     */
    public function test_cquiz_view() {
        global $CFG;

        $CFG->enablecompletion = 1;
        $this->resetAfterTest();

        $this->setAdminUser();
        // Setup test data.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $cquiz = $this->getDataGenerator()->create_module('cquiz', array('course' => $course->id), array('completion' => 2, 'completionview' => 1));
        $context = context_module::instance($cquiz->cmid);
        $cm = get_coursemodule_from_instance('cquiz', $cquiz->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        cquiz_view($cquiz, $course, $cm, $context);

        $events = $sink->get_events();
        // 2 additional events thanks to completion.
        $this->assertCount(3, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_cquiz\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodleurl = new \moodle_url('/mod/cquiz/view.php', array('id' => $cm->id));
        $this->assertEquals($moodleurl, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());
        // Check completion status.
        $completion = new completion_info($course);
        $completiondata = $completion->get_data($cm);
        $this->assertEquals(1, $completiondata->completionstate);
    }

}

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
require_once($CFG->dirroot . '/mod/cquiz/lib.php');

/**
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class mod_cquiz_lib_testcase extends advanced_testcase {

    public function test_cquiz_has_grades() {
        $cquiz = new stdClass();
        $cquiz->grade = '100.0000';
        $cquiz->sumgrades = '100.0000';
        $this->assertTrue(cquiz_has_grades($cquiz));
        $cquiz->sumgrades = '0.0000';
        $this->assertFalse(cquiz_has_grades($cquiz));
        $cquiz->grade = '0.0000';
        $this->assertFalse(cquiz_has_grades($cquiz));
        $cquiz->sumgrades = '100.0000';
        $this->assertFalse(cquiz_has_grades($cquiz));
    }

    public function test_cquiz_format_grade() {
        $cquiz = new stdClass();
        $cquiz->decimalpoints = 2;
        $this->assertEquals(cquiz_format_grade($cquiz, 0.12345678), format_float(0.12, 2));
        $this->assertEquals(cquiz_format_grade($cquiz, 0), format_float(0, 2));
        $this->assertEquals(cquiz_format_grade($cquiz, 1.000000000000), format_float(1, 2));
        $cquiz->decimalpoints = 0;
        $this->assertEquals(cquiz_format_grade($cquiz, 0.12345678), '0');
    }

    public function test_cquiz_get_grade_format() {
        $cquiz = new stdClass();
        $cquiz->decimalpoints = 2;
        $this->assertEquals(cquiz_get_grade_format($cquiz), 2);
        $this->assertEquals($cquiz->questiondecimalpoints, -1);
        $cquiz->questiondecimalpoints = 2;
        $this->assertEquals(cquiz_get_grade_format($cquiz), 2);
        $cquiz->decimalpoints = 3;
        $cquiz->questiondecimalpoints = -1;
        $this->assertEquals(cquiz_get_grade_format($cquiz), 3);
        $cquiz->questiondecimalpoints = 4;
        $this->assertEquals(cquiz_get_grade_format($cquiz), 4);
    }

    public function test_cquiz_format_question_grade() {
        $cquiz = new stdClass();
        $cquiz->decimalpoints = 2;
        $cquiz->questiondecimalpoints = 2;
        $this->assertEquals(cquiz_format_question_grade($cquiz, 0.12345678), format_float(0.12, 2));
        $this->assertEquals(cquiz_format_question_grade($cquiz, 0), format_float(0, 2));
        $this->assertEquals(cquiz_format_question_grade($cquiz, 1.000000000000), format_float(1, 2));
        $cquiz->decimalpoints = 3;
        $cquiz->questiondecimalpoints = -1;
        $this->assertEquals(cquiz_format_question_grade($cquiz, 0.12345678), format_float(0.123, 3));
        $this->assertEquals(cquiz_format_question_grade($cquiz, 0), format_float(0, 3));
        $this->assertEquals(cquiz_format_question_grade($cquiz, 1.000000000000), format_float(1, 3));
        $cquiz->questiondecimalpoints = 4;
        $this->assertEquals(cquiz_format_question_grade($cquiz, 0.12345678), format_float(0.1235, 4));
        $this->assertEquals(cquiz_format_question_grade($cquiz, 0), format_float(0, 4));
        $this->assertEquals(cquiz_format_question_grade($cquiz, 1.000000000000), format_float(1, 4));
    }

    /**
     * Test deleting a cquiz instance.
     */
    public function test_cquiz_delete_instance() {
        global $SITE, $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Setup a cquiz with 1 standard and 1 random question.
        $cquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_cquiz');
        $cquiz = $cquizgenerator->create_instance(array('course' => $SITE->id, 'questionsperpage' => 3, 'grade' => 100.0));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $standardq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));

        cquiz_add_cquiz_question($standardq->id, $cquiz);
        cquiz_add_random_questions($cquiz, 0, $cat->id, 1, false);

        // Get the random question.
        $randomq = $DB->get_record('question', array('qtype' => 'random'));

        cquiz_delete_instance($cquiz->id);

        // Check that the random question was deleted.
        $count = $DB->count_records('question', array('id' => $randomq->id));
        $this->assertEquals(0, $count);
        // Check that the standard question was not deleted.
        $count = $DB->count_records('question', array('id' => $standardq->id));
        $this->assertEquals(1, $count);

        // Check that all the slots were removed.
        $count = $DB->count_records('cquiz_slots', array('cquizid' => $cquiz->id));
        $this->assertEquals(0, $count);

        // Check that the cquiz was removed.
        $count = $DB->count_records('cquiz', array('id' => $cquiz->id));
        $this->assertEquals(0, $count);
    }

    /**
     * Test checking the completion state of a cquiz.
     */
    public function test_cquiz_get_completion_state() {
        global $CFG, $DB;
        $this->resetAfterTest(true);

        // Enable completion before creating modules, otherwise the completion data is not written in DB.
        $CFG->enablecompletion = true;

        // Create a course and student.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => true));
        $passstudent = $this->getDataGenerator()->create_user();
        $failstudent = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->assertNotEmpty($studentrole);

        // Enrol students.
        $this->assertTrue($this->getDataGenerator()->enrol_user($passstudent->id, $course->id, $studentrole->id));
        $this->assertTrue($this->getDataGenerator()->enrol_user($failstudent->id, $course->id, $studentrole->id));

        // Make a scale and an outcome.
        $scale = $this->getDataGenerator()->create_scale();
        $data = array('courseid' => $course->id,
            'fullname' => 'Team work',
            'shortname' => 'Team work',
            'scaleid' => $scale->id);
        $outcome = $this->getDataGenerator()->create_grade_outcome($data);

        // Make a cquiz with the outcome on.
        $cquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_cquiz');
        $data = array('course' => $course->id,
            'outcome_' . $outcome->id => 1,
            'grade' => 100.0,
            'questionsperpage' => 0,
            'sumgrades' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 1);
        $cquiz = $cquizgenerator->create_instance($data);
        $cm = get_coursemodule_from_id('cquiz', $cquiz->cmid);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        cquiz_add_cquiz_question($question->id, $cquiz);

        $cquizobj = cquiz::create($cquiz->id, $passstudent->id);

        // Set grade to pass.
        $item = grade_item::fetch(array('courseid' => $course->id, 'itemtype' => 'mod',
                    'itemmodule' => 'cquiz', 'iteminstance' => $cquiz->id, 'outcomeid' => null));
        $item->gradepass = 80;
        $item->update();

        // Start the passing attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_cquiz', $cquizobj->get_context());
        $quba->set_preferred_behaviour($cquizobj->get_cquiz()->preferredbehaviour);

        $timenow = time();
        $attempt = cquiz_create_attempt($cquizobj, 1, false, $timenow, false, $passstudent->id);
        cquiz_start_new_attempt($cquizobj, $quba, $attempt, 1, $timenow);
        cquiz_attempt_save_started($cquizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = cquiz_attempt::create($attempt->id);
        $tosubmit = array(1 => array('answer' => '3.14'));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = cquiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // Start the failing attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_cquiz', $cquizobj->get_context());
        $quba->set_preferred_behaviour($cquizobj->get_cquiz()->preferredbehaviour);

        $timenow = time();
        $attempt = cquiz_create_attempt($cquizobj, 1, false, $timenow, false, $failstudent->id);
        cquiz_start_new_attempt($cquizobj, $quba, $attempt, 1, $timenow);
        cquiz_attempt_save_started($cquizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = cquiz_attempt::create($attempt->id);
        $tosubmit = array(1 => array('answer' => '0'));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = cquiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // Check the results.
        $this->assertTrue(cquiz_get_completion_state($course, $cm, $passstudent->id, 'return'));
        $this->assertFalse(cquiz_get_completion_state($course, $cm, $failstudent->id, 'return'));
    }

    /**
     * Test sending of grade messages 
     */
    public function test_cquiz_send_grade_message() {
        global $CFG, $DB;
        $this->resetAfterTest(true);

        $messagesink = $this->redirectMessages();

        // Enable completion before creating modules, otherwise the completion data is not written in DB.
        $CFG->enablecompletion = true;

        // Create a course and student.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => true));
        $improvingstudent = $this->getDataGenerator()->create_user();
        $degradingstudent = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->assertNotEmpty($studentrole);

        // Enrol students.
        $this->assertTrue($this->getDataGenerator()->enrol_user($improvingstudent->id, $course->id, $studentrole->id));
        $this->assertTrue($this->getDataGenerator()->enrol_user($degradingstudent->id, $course->id, $studentrole->id));

        // Make a cquiz.
        $cquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_cquiz');
        $data = array('course' => $course->id,
            'grade' => 100.0,
            'questionsperpage' => 0,
            'sumgrades' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 1);
        $cquiz = $cquizgenerator->create_instance($data);
        $cm = get_coursemodule_from_id('cquiz', $cquiz->cmid);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        cquiz_add_cquiz_question($question->id, $cquiz);

        // Start first attempt to be improved.
        $cquizobj = cquiz::create($cquiz->id, $improvingstudent->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_cquiz', $cquizobj->get_context());
        $quba->set_preferred_behaviour($cquizobj->get_cquiz()->preferredbehaviour);
        $timenow = time();
        $attempt = cquiz_create_attempt($cquizobj, 1, false, $timenow, false, $improvingstudent->id);
        cquiz_start_new_attempt($cquizobj, $quba, $attempt, 1, $timenow);
        cquiz_attempt_save_started($cquizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = cquiz_attempt::create($attempt->id);
        $tosubmit = array(1 => array('answer' => '0'));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = cquiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // Check grade message sent
        $messages = $messagesink->get_messages();
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertEquals($message->useridto, $improvingstudent->id);
        $this->assertEquals($message->subject, get_string('emailgradeearnedsubject', 'mod_cquiz'));
        $messagesink->clear();

        // Start second attempt (improved).
        $cquizobj = cquiz::create($cquiz->id, $improvingstudent->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_cquiz', $cquizobj->get_context());
        $quba->set_preferred_behaviour($cquizobj->get_cquiz()->preferredbehaviour);
        $timenow = time();
        $attempt = cquiz_create_attempt($cquizobj, 2, false, $timenow, false, $improvingstudent->id);
        cquiz_start_new_attempt($cquizobj, $quba, $attempt, 2, $timenow);
        cquiz_attempt_save_started($cquizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = cquiz_attempt::create($attempt->id);
        $tosubmit = array(1 => array('answer' => '3.14'));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = cquiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // Check grade message sent
        $messages = $messagesink->get_messages();
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertEquals($message->useridto, $improvingstudent->id);
        $this->assertEquals($message->subject, get_string('emailgradeimprovedsubject', 'mod_cquiz'));
        $messagesink->clear();

        // Start the first attempt (to be degraded).
        $cquizobj = cquiz::create($cquiz->id, $degradingstudent->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_cquiz', $cquizobj->get_context());
        $quba->set_preferred_behaviour($cquizobj->get_cquiz()->preferredbehaviour);
        $timenow = time();
        $attempt = cquiz_create_attempt($cquizobj, 1, false, $timenow, false, $degradingstudent->id);
        cquiz_start_new_attempt($cquizobj, $quba, $attempt, 1, $timenow);
        cquiz_attempt_save_started($cquizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = cquiz_attempt::create($attempt->id);
        $tosubmit = array(1 => array('answer' => '3.14'));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = cquiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // Check grade message sent
        $messages = $messagesink->get_messages();
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertEquals($message->useridto, $degradingstudent->id);
        $this->assertEquals($message->subject, get_string('emailgradeearnedsubject', 'mod_cquiz'));
        $messagesink->clear();

        // Start the second attempt (degraded).
        $cquizobj = cquiz::create($cquiz->id, $degradingstudent->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_cquiz', $cquizobj->get_context());
        $quba->set_preferred_behaviour($cquizobj->get_cquiz()->preferredbehaviour);
        $timenow = time();
        $attempt = cquiz_create_attempt($cquizobj, 2, false, $timenow, false, $degradingstudent->id);
        cquiz_start_new_attempt($cquizobj, $quba, $attempt, 2, $timenow);
        cquiz_attempt_save_started($cquizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = cquiz_attempt::create($attempt->id);
        $tosubmit = array(1 => array('answer' => '0'));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = cquiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // Check grade message not sent
        $messages = $messagesink->get_messages();
        $this->assertCount(0, $messages);
    }

}

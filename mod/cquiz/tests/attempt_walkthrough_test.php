<?php

/**
 * Cquiz attempt walk through tests.
 *
 * @package    mod_cquiz
 * @category   phpunit
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/cquiz/locallib.php');

/**
 * Cquiz attempt walk through.
 *
 * @package    mod_cquiz
 * @category   phpunit
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Jamie Pratt <me@jamiep.org>
 * @author     Ricardo Drummond
 */
class mod_cquiz_attempt_walkthrough_testcase extends advanced_testcase {

    /**
     * Create a cquiz with questions and walk through a cquiz attempt.
     */
    public function test_cquiz_attempt_walkthrough() {
        global $SITE;

        $this->resetAfterTest(true);

        // Make a cquiz.
        $cquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_cquiz');

        $cquiz = $cquizgenerator->create_instance(array('course' => $SITE->id, 'questionsperpage' => 0, 'grade' => 100.0,
            'sumgrades' => 2));

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));

        // Add them to the cquiz.
        cquiz_add_cquiz_question($saq->id, $cquiz);
        cquiz_add_cquiz_question($numq->id, $cquiz);

        // Make a user to do the cquiz.
        $user1 = $this->getDataGenerator()->create_user();

        $cquizobj = cquiz::create($cquiz->id, $user1->id);

        // Start the attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_cquiz', $cquizobj->get_context());
        $quba->set_preferred_behaviour($cquizobj->get_cquiz()->preferredbehaviour);

        $timenow = time();
        $attempt = cquiz_create_attempt($cquizobj, 1, false, $timenow, false, $user1->id);

        cquiz_start_new_attempt($cquizobj, $quba, $attempt, 1, $timenow);
        $this->assertEquals('1,2,0', $attempt->layout);

        cquiz_attempt_save_started($cquizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = cquiz_attempt::create($attempt->id);
        $this->assertFalse($attemptobj->has_response_to_at_least_one_graded_question());

        $prefix1 = $quba->get_field_prefix(1);
        $prefix2 = $quba->get_field_prefix(2);

        $tosubmit = array(1 => array('answer' => 'frog'),
            2 => array('answer' => '3.14'));

        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = cquiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // Re-load cquiz attempt data.
        $attemptobj = cquiz_attempt::create($attempt->id);

        // Check that results are stored as expected.
        $this->assertEquals(1, $attemptobj->get_attempt_number());
        $this->assertEquals(2, $attemptobj->get_sum_marks());
        $this->assertEquals(true, $attemptobj->is_finished());
        $this->assertEquals($timenow, $attemptobj->get_submitted_date());
        $this->assertEquals($user1->id, $attemptobj->get_userid());
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());

        // Check cquiz grades.
        $grades = cquiz_get_user_grades($cquiz, $user1->id);
        $grade = array_shift($grades);
        $this->assertEquals(100.0, $grade->rawgrade);

        // Check grade book.
        $gradebookgrades = grade_get_grades($SITE->id, 'mod', 'cquiz', $cquiz->id, $user1->id);
        $gradebookitem = array_shift($gradebookgrades->items);
        $gradebookgrade = array_shift($gradebookitem->grades);
        $this->assertEquals(100, $gradebookgrade->grade);
    }

    /**
     * Create a cquiz with a random as well as other questions and walk through cquiz attempts.
     */
    public function test_cquiz_with_random_question_attempt_walkthrough() {
        global $SITE;

        $this->resetAfterTest(true);
        question_bank::get_qtype('random')->clear_caches_before_testing();

        $this->setAdminUser();

        // Make a cquiz.
        $cquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_cquiz');

        $cquiz = $cquizgenerator->create_instance(array('course' => $SITE->id, 'questionsperpage' => 2, 'grade' => 100.0,
            'sumgrades' => 4));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        // Add two questions to question category.
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));

        // Add random question to the cquiz.
        cquiz_add_random_questions($cquiz, 0, $cat->id, 1, false);

        // Make another category.
        $cat2 = $questiongenerator->create_question_category();
        $match = $questiongenerator->create_question('match', null, array('category' => $cat->id));

        cquiz_add_cquiz_question($match->id, $cquiz, 0);

        $multichoicemulti = $questiongenerator->create_question('multichoice', 'two_of_four', array('category' => $cat->id));

        cquiz_add_cquiz_question($multichoicemulti->id, $cquiz, 0);

        $multichoicesingle = $questiongenerator->create_question('multichoice', 'one_of_four', array('category' => $cat->id));

        cquiz_add_cquiz_question($multichoicesingle->id, $cquiz, 0);

        foreach (array($saq->id => 'frog', $numq->id => '3.14') as $randomqidtoselect => $randqanswer) {
            // Make a new user to do the cquiz each loop.
            $user1 = $this->getDataGenerator()->create_user();
            $this->setUser($user1);

            $cquizobj = cquiz::create($cquiz->id, $user1->id);

            // Start the attempt.
            $quba = question_engine::make_questions_usage_by_activity('mod_cquiz', $cquizobj->get_context());
            $quba->set_preferred_behaviour($cquizobj->get_cquiz()->preferredbehaviour);

            $timenow = time();
            $attempt = cquiz_create_attempt($cquizobj, 1, false, $timenow);

            cquiz_start_new_attempt($cquizobj, $quba, $attempt, 1, $timenow, array(1 => $randomqidtoselect));
            $this->assertEquals('1,2,0,3,4,0', $attempt->layout);

            cquiz_attempt_save_started($cquizobj, $quba, $attempt);

            // Process some responses from the student.
            $attemptobj = cquiz_attempt::create($attempt->id);
            $this->assertFalse($attemptobj->has_response_to_at_least_one_graded_question());

            $tosubmit = array();
            $selectedquestionid = $quba->get_question_attempt(1)->get_question()->id;
            $tosubmit[1] = array('answer' => $randqanswer);
            $tosubmit[2] = array(
                'frog' => 'amphibian',
                'cat' => 'mammal',
                'newt' => 'amphibian');
            $tosubmit[3] = array('One' => '1', 'Two' => '0', 'Three' => '1', 'Four' => '0'); // First and third choice.
            $tosubmit[4] = array('answer' => 'One'); // The first choice.

            $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

            // Finish the attempt.
            $attemptobj = cquiz_attempt::create($attempt->id);
            $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
            $attemptobj->process_finish($timenow, false);

            // Re-load cquiz attempt data.
            $attemptobj = cquiz_attempt::create($attempt->id);

            // Check that results are stored as expected.
            $this->assertEquals(1, $attemptobj->get_attempt_number());
            $this->assertEquals(4, $attemptobj->get_sum_marks());
            $this->assertEquals(true, $attemptobj->is_finished());
            $this->assertEquals($timenow, $attemptobj->get_submitted_date());
            $this->assertEquals($user1->id, $attemptobj->get_userid());
            $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());

            // Check cquiz grades.
            $grades = cquiz_get_user_grades($cquiz, $user1->id);
            $grade = array_shift($grades);
            $this->assertEquals(100.0, $grade->rawgrade);

            // Check grade book.
            $gradebookgrades = grade_get_grades($SITE->id, 'mod', 'cquiz', $cquiz->id, $user1->id);
            $gradebookitem = array_shift($gradebookgrades->items);
            $gradebookgrade = array_shift($gradebookitem->grades);
            $this->assertEquals(100, $gradebookgrade->grade);
        }
    }

    public function get_correct_response_for_variants() {
        return array(array(1, 9.9), array(2, 8.5), array(5, 14.2), array(10, 6.8, true));
    }

    protected $cquizwithvariants = null;

    /**
     * Create a cquiz with a single question with variants and walk through cquiz attempts.
     *
     * @dataProvider get_correct_response_for_variants
     */
    public function test_cquiz_with_question_with_variants_attempt_walkthrough($variantno, $correctresponse, $done = false) {
        global $SITE;

        $this->resetAfterTest($done);

        $this->setAdminUser();

        if ($this->cquizwithvariants === null) {
            // Make a cquiz.
            $cquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_cquiz');

            $this->cquizwithvariants = $cquizgenerator->create_instance(array('course' => $SITE->id,
                'questionsperpage' => 0,
                'grade' => 100.0,
                'sumgrades' => 1));

            $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

            $cat = $questiongenerator->create_question_category();
            $calc = $questiongenerator->create_question('calculatedsimple', 'sumwithvariants', array('category' => $cat->id));
            cquiz_add_cquiz_question($calc->id, $this->cquizwithvariants, 0);
        }


        // Make a new user to do the cquiz.
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);
        $cquizobj = cquiz::create($this->cquizwithvariants->id, $user1->id);

        // Start the attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_cquiz', $cquizobj->get_context());
        $quba->set_preferred_behaviour($cquizobj->get_cquiz()->preferredbehaviour);

        $timenow = time();
        $attempt = cquiz_create_attempt($cquizobj, 1, false, $timenow);

        // Select variant.
        cquiz_start_new_attempt($cquizobj, $quba, $attempt, 1, $timenow, array(), array(1 => $variantno));
        $this->assertEquals('1,0', $attempt->layout);
        cquiz_attempt_save_started($cquizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = cquiz_attempt::create($attempt->id);
        $this->assertFalse($attemptobj->has_response_to_at_least_one_graded_question());

        $tosubmit = array(1 => array('answer' => $correctresponse));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = cquiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());

        $attemptobj->process_finish($timenow, false);

        // Re-load cquiz attempt data.
        $attemptobj = cquiz_attempt::create($attempt->id);

        // Check that results are stored as expected.
        $this->assertEquals(1, $attemptobj->get_attempt_number());
        $this->assertEquals(1, $attemptobj->get_sum_marks());
        $this->assertEquals(true, $attemptobj->is_finished());
        $this->assertEquals($timenow, $attemptobj->get_submitted_date());
        $this->assertEquals($user1->id, $attemptobj->get_userid());
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());

        // Check cquiz grades.
        $grades = cquiz_get_user_grades($this->cquizwithvariants, $user1->id);
        $grade = array_shift($grades);
        $this->assertEquals(100.0, $grade->rawgrade);

        // Check grade book.
        $gradebookgrades = grade_get_grades($SITE->id, 'mod', 'cquiz', $this->cquizwithvariants->id, $user1->id);
        $gradebookitem = array_shift($gradebookgrades->items);
        $gradebookgrade = array_shift($gradebookitem->grades);
        $this->assertEquals(100, $gradebookgrade->grade);
    }

}

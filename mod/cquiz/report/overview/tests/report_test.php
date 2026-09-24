<?php

/**
 * Tests for the cquiz overview report.
 *
 * @package   cquiz_overview
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/cquiz/locallib.php');
require_once($CFG->dirroot . '/mod/cquiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/cquiz/report/default.php');
require_once($CFG->dirroot . '/mod/cquiz/report/overview/report.php');

/**
 * Tests for the cquiz overview report.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquiz_overview_report_testcase extends advanced_testcase {

    public function test_report_sql() {
        global $DB, $SITE;
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $cquizgenerator = $generator->get_plugin_generator('mod_cquiz');
        $cquiz = $cquizgenerator->create_instance(array('course' => $SITE->id,
            'grademethod' => CQUIZ_GRADEHIGHEST, 'grade' => 100.0, 'sumgrades' => 10.0,
            'attempts' => 10));

        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $student3 = $generator->create_user();

        $cquizid = 123;
        $timestamp = 1234567890;

        // The test data.
        $fields = array('cquiz', 'userid', 'attempt', 'sumgrades', 'state');
        $attempts = array(
            array($cquiz->id, $student1->id, 1, 0.0, cquiz_attempt::FINISHED),
            array($cquiz->id, $student1->id, 2, 5.0, cquiz_attempt::FINISHED),
            array($cquiz->id, $student1->id, 3, 8.0, cquiz_attempt::FINISHED),
            array($cquiz->id, $student1->id, 4, null, cquiz_attempt::ABANDONED),
            array($cquiz->id, $student1->id, 5, null, cquiz_attempt::IN_PROGRESS),
            array($cquiz->id, $student2->id, 1, null, cquiz_attempt::ABANDONED),
            array($cquiz->id, $student2->id, 2, null, cquiz_attempt::ABANDONED),
            array($cquiz->id, $student2->id, 3, 7.0, cquiz_attempt::FINISHED),
            array($cquiz->id, $student2->id, 4, null, cquiz_attempt::ABANDONED),
            array($cquiz->id, $student2->id, 5, null, cquiz_attempt::ABANDONED),
        );

        // Load it in to cquiz attempts table.
        $uniqueid = 1;
        foreach ($attempts as $attempt) {
            $data = array_combine($fields, $attempt);
            $data['timestart'] = $timestamp + 3600 * $data['attempt'];
            $data['timemodifed'] = $data['timestart'];
            if ($data['state'] == cquiz_attempt::FINISHED) {
                $data['timefinish'] = $data['timestart'] + 600;
                $data['timemodifed'] = $data['timefinish'];
            }
            $data['layout'] = ''; // Not used, but cannot be null.
            $data['uniqueid'] = $uniqueid++;
            $data['preview'] = 0;
            $DB->insert_record('cquiz_attempts', $data);
        }

        // Actually getting the SQL to run is quit hard. Do a minimal set up of
        // some objects.
        $context = context_module::instance($cquiz->cmid);
        $cm = get_coursemodule_from_id('cquiz', $cquiz->cmid);
        $qmsubselect = cquiz_report_qm_filter_select($cquiz);
        $reportstudents = array($student1->id, $student2->id, $student3->id);

        // Set the options.
        $reportoptions = new cquiz_overview_options('overview', $cquiz, $cm, null);
        $reportoptions->attempts = cquiz_attempts_report::ENROLLED_ALL;
        $reportoptions->onlygraded = true;
        $reportoptions->states = array(cquiz_attempt::IN_PROGRESS, cquiz_attempt::OVERDUE, cquiz_attempt::FINISHED);

        // Now do a minimal set-up of the table class.
        $table = new cquiz_overview_table($cquiz, $context, $qmsubselect, $reportoptions, array(), $reportstudents, array(1), null);
        $table->define_columns(array('attempt'));
        $table->sortable(true, 'uniqueid');
        $table->define_baseurl(new moodle_url('/mod/cquiz/report.php'));
        $table->setup();

        // Run the query.
        list($fields, $from, $where, $params) = $table->base_sql($reportstudents);
        $table->set_sql($fields, $from, $where, $params);
        $table->query_db(30, false);

        // Verify what was returned: Student 1's best and in progress attempts.
        // Stuent 2's finshed attempt, and Student 3 with no attempt.
        // The array key is {student id}#{attempt number}.
        $this->assertEquals(4, count($table->rawdata));
        $this->assertArrayHasKey($student1->id . '#3', $table->rawdata);
        $this->assertEquals(1, $table->rawdata[$student1->id . '#3']->gradedattempt);
        $this->assertArrayHasKey($student1->id . '#3', $table->rawdata);
        $this->assertEquals(0, $table->rawdata[$student1->id . '#5']->gradedattempt);
        $this->assertArrayHasKey($student2->id . '#3', $table->rawdata);
        $this->assertEquals(1, $table->rawdata[$student2->id . '#3']->gradedattempt);
        $this->assertArrayHasKey($student3->id . '#0', $table->rawdata);
        $this->assertEquals(0, $table->rawdata[$student3->id . '#0']->gradedattempt);
    }

}

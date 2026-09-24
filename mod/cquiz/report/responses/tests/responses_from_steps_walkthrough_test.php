<?php

/**
 * Cquiz attempt walk through using data from csv file.
 *
 * @package    cquiz_statistics
 * @category   phpunit
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Jamie Pratt <me@jamiep.org>
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/cquiz/tests/attempt_walkthrough_from_csv_test.php');
require_once($CFG->dirroot . '/mod/cquiz/report/default.php');
require_once($CFG->dirroot . '/mod/cquiz/report/statistics/report.php');
require_once($CFG->dirroot . '/mod/cquiz/report/reportlib.php');

/**
 * Cquiz attempt walk through using data from csv file.
 *
 * @package    cquiz_statistics
 * @category   phpunit
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Jamie Pratt <me@jamiep.org>
 * @author     Ricardo Drummond
 */
class cquiz_report_responses_from_steps_testcase extends mod_cquiz_attempt_walkthrough_from_csv_testcase {

    protected function get_full_path_of_csv_file($setname, $test) {
        // Overridden here so that __DIR__ points to the path of this file.
        return __DIR__ . "/fixtures/{$setname}{$test}.csv";
    }

    protected $files = array('questions', 'steps', 'responses');

    /**
     * Create a cquiz add questions to it, walk through cquiz attempts and then check results.
     *
     * @param array $cquizsettings settings to override default settings for cquiz created by generator. Taken from cquizzes.csv.
     * @param PHPUnit_Extensions_Database_DataSet_ITable[] $csvdata of data read from csv file "questionsXX.csv",
     *                                                                                  "stepsXX.csv" and "responsesXX.csv".
     * @dataProvider get_data_for_walkthrough
     */
    public function test_walkthrough_from_csv($cquizsettings, $csvdata) {

        $this->resetAfterTest(true);
        question_bank::get_qtype('random')->clear_caches_before_testing();

        $this->create_cquiz($cquizsettings, $csvdata['questions']);

        $cquizattemptids = $this->walkthrough_attempts($csvdata['steps']);

        for ($rowno = 0; $rowno < $csvdata['responses']->getRowCount(); $rowno++) {
            $responsesfromcsv = $csvdata['responses']->getRow($rowno);
            $responses = $this->explode_dot_separated_keys_to_make_subindexs($responsesfromcsv);

            if (!isset($cquizattemptids[$responses['cquizattempt']])) {
                throw new coding_exception("There is no cquizattempt {$responses['cquizattempt']}!");
            }
            $this->assert_response_test($cquizattemptids[$responses['cquizattempt']], $responses);
        }
    }

    protected function assert_response_test($cquizattemptid, $responses) {
        $cquizattempt = cquiz_attempt::create($cquizattemptid);

        foreach ($responses['slot'] as $slot => $tests) {
            $slothastests = false;
            foreach ($tests as $test) {
                if ('' !== $test) {
                    $slothastests = true;
                }
            }
            if (!$slothastests) {
                continue;
            }
            $qa = $cquizattempt->get_question_attempt($slot);
            $stepswithsubmit = $qa->get_steps_with_submitted_response_iterator();
            $step = $stepswithsubmit[$responses['submittedstepno']];
            if (null === $step) {
                throw new coding_exception("There is no step no {$responses['submittedstepno']} " .
                "for slot $slot in cquizattempt {$responses['cquizattempt']}!");
            }
            foreach (array('responsesummary', 'fraction', 'state') as $column) {
                if (isset($tests[$column]) && $tests[$column] != '') {
                    switch ($column) {
                        case 'responsesummary' :
                            $actual = $qa->get_question()->summarise_response($step->get_qt_data());
                            break;
                        case 'fraction' :
                            if (count($stepswithsubmit) == $responses['submittedstepno']) {
                                // If this is the last step then we need to look at the fraction after the question has been
                                // finished.
                                $actual = $qa->get_fraction();
                            } else {
                                $actual = $step->get_fraction();
                            }
                            break;
                        case 'state' :
                            if (count($stepswithsubmit) == $responses['submittedstepno']) {
                                // If this is the last step then we need to look at the state after the question has been
                                // finished.
                                $state = $qa->get_state();
                            } else {
                                $state = $step->get_state();
                            }
                            $actual = substr(get_class($state), strlen('question_state_'));
                    }
                    $expected = $tests[$column];
                    $failuremessage = "Error in  cquizattempt {$responses['cquizattempt']} in $column, slot $slot, " .
                            "submittedstepno {$responses['submittedstepno']}";
                    $this->assertEquals($expected, $actual, $failuremessage);
                }
            }
        }
    }

}

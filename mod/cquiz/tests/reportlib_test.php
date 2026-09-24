<?php

/**
 * Unit tests for (some of) mod/cquiz/report/reportlib.php
 *
 * @package   mod_cquiz
 * @category  phpunit
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/cquiz/report/reportlib.php');

/**
 * This class contains the test cases for the functions in reportlib.php.
 *
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class mod_cquiz_reportlib_testcase extends advanced_testcase {

    public function test_cquiz_report_index_by_keys() {
        $datum = array();
        $object = new stdClass();
        $object->qid = 3;
        $object->aid = 101;
        $object->response = '';
        $object->grade = 3;
        $datum[] = $object;

        $indexed = cquiz_report_index_by_keys($datum, array('aid', 'qid'));

        $this->assertEquals($indexed[101][3]->qid, 3);
        $this->assertEquals($indexed[101][3]->aid, 101);
        $this->assertEquals($indexed[101][3]->response, '');
        $this->assertEquals($indexed[101][3]->grade, 3);

        $indexed = cquiz_report_index_by_keys($datum, array('aid', 'qid'), false);

        $this->assertEquals($indexed[101][3][0]->qid, 3);
        $this->assertEquals($indexed[101][3][0]->aid, 101);
        $this->assertEquals($indexed[101][3][0]->response, '');
        $this->assertEquals($indexed[101][3][0]->grade, 3);
    }

    public function test_cquiz_report_scale_summarks_as_percentage() {
        $cquiz = new stdClass();
        $cquiz->sumgrades = 10;
        $cquiz->decimalpoints = 2;

        $this->assertEquals('12.34567%', cquiz_report_scale_summarks_as_percentage(1.234567, $cquiz, false));
        $this->assertEquals('12.35%', cquiz_report_scale_summarks_as_percentage(1.234567, $cquiz, true));
        $this->assertEquals('-', cquiz_report_scale_summarks_as_percentage('-', $cquiz, true));
    }

    public function test_cquiz_report_qm_filter_select_only_one_attempt_allowed() {
        $cquiz = new stdClass();
        $cquiz->attempts = 1;
        $this->assertSame('', cquiz_report_qm_filter_select($cquiz));
    }

    public function test_cquiz_report_qm_filter_select_average() {
        $cquiz = new stdClass();
        $cquiz->attempts = 10;
        $cquiz->grademethod = CQUIZ_GRADEAVERAGE;
        $this->assertSame('', cquiz_report_qm_filter_select($cquiz));
    }

    public function test_cquiz_report_qm_filter_select_first_last_best() {
        global $DB;
        $this->resetAfterTest();

        $fakeattempt = new stdClass();
        $fakeattempt->userid = 123;
        $fakeattempt->cquiz = 456;
        $fakeattempt->layout = '1,2,0,3,4,0,5';
        $fakeattempt->state = cquiz_attempt::FINISHED;

        // We intentionally insert these in a funny order, to test the SQL better.
        // The test data is:
        // id | cquizid | user | attempt | sumgrades | state
        // ---------------------------------------------------
        // 4  | 456    | 123  | 1       | 30        | finished
        // 2  | 456    | 123  | 2       | 50        | finished
        // 1  | 456    | 123  | 3       | 50        | finished
        // 3  | 456    | 123  | 4       | null      | inprogress
        // 5  | 456    | 1    | 1       | 100       | finished
        // layout is only given because it has a not-null constraint.
        // uniqueid values are meaningless, but that column has a unique constraint.

        $fakeattempt->attempt = 3;
        $fakeattempt->sumgrades = 50;
        $fakeattempt->uniqueid = 13;
        $DB->insert_record('cquiz_attempts', $fakeattempt);

        $fakeattempt->attempt = 2;
        $fakeattempt->sumgrades = 50;
        $fakeattempt->uniqueid = 26;
        $DB->insert_record('cquiz_attempts', $fakeattempt);

        $fakeattempt->attempt = 4;
        $fakeattempt->sumgrades = null;
        $fakeattempt->uniqueid = 39;
        $fakeattempt->state = cquiz_attempt::IN_PROGRESS;
        $DB->insert_record('cquiz_attempts', $fakeattempt);

        $fakeattempt->attempt = 1;
        $fakeattempt->sumgrades = 30;
        $fakeattempt->uniqueid = 52;
        $fakeattempt->state = cquiz_attempt::FINISHED;
        $DB->insert_record('cquiz_attempts', $fakeattempt);

        $fakeattempt->attempt = 1;
        $fakeattempt->userid = 1;
        $fakeattempt->sumgrades = 100;
        $fakeattempt->uniqueid = 65;
        $DB->insert_record('cquiz_attempts', $fakeattempt);

        $cquiz = new stdClass();
        $cquiz->attempts = 10;

        $cquiz->grademethod = CQUIZ_ATTEMPTFIRST;
        $firstattempt = $DB->get_records_sql("
                SELECT * FROM {cquiz_attempts} cquiza WHERE userid = ? AND cquiz = ? AND "
                . cquiz_report_qm_filter_select($cquiz), array(123, 456));
        $this->assertEquals(1, count($firstattempt));
        $firstattempt = reset($firstattempt);
        $this->assertEquals(1, $firstattempt->attempt);

        $cquiz->grademethod = CQUIZ_ATTEMPTLAST;
        $lastattempt = $DB->get_records_sql("
                SELECT * FROM {cquiz_attempts} cquiza WHERE userid = ? AND cquiz = ? AND "
                . cquiz_report_qm_filter_select($cquiz), array(123, 456));
        $this->assertEquals(1, count($lastattempt));
        $lastattempt = reset($lastattempt);
        $this->assertEquals(3, $lastattempt->attempt);

        $cquiz->attempts = 0;
        $cquiz->grademethod = CQUIZ_GRADEHIGHEST;
        $bestattempt = $DB->get_records_sql("
                SELECT * FROM {cquiz_attempts} qa_alias WHERE userid = ? AND cquiz = ? AND "
                . cquiz_report_qm_filter_select($cquiz, 'qa_alias'), array(123, 456));
        $this->assertEquals(1, count($bestattempt));
        $bestattempt = reset($bestattempt);
        $this->assertEquals(2, $bestattempt->attempt);
    }

}

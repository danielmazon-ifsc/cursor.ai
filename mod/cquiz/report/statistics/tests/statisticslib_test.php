<?php

/**
 * Unit tests for (some of) statisticslib.php.
 *
 * @package   cquiz_statistics
 * @category  test
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/cquiz/report/statistics/statisticslib.php');

/**
 * Unit tests for (some of) statisticslib.php.
 *
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquiz_statistics_statisticslib_testcase extends basic_testcase {

    public function test_cquiz_statistics_renumber_placeholders_no_op() {
        list($sql, $params) = cquiz_statistics_renumber_placeholders(
                ' IN (:u1, :u2)', array('u1' => 1, 'u2' => 2), 'u');
        $this->assertEquals(' IN (:u1, :u2)', $sql);
        $this->assertEquals(array('u1' => 1, 'u2' => 2), $params);
    }

    public function test_cquiz_statistics_renumber_placeholders_work_to_do() {
        list($sql, $params) = cquiz_statistics_renumber_placeholders(
                'frog1 IN (:frog100 , :frog101)', array('frog100' => 1, 'frog101' => 2), 'frog');
        $this->assertEquals('frog1 IN (:frog1 , :frog2)', $sql);
        $this->assertEquals(array('frog1' => 1, 'frog2' => 2), $params);
    }

}

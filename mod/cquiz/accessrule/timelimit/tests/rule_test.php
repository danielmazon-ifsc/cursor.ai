<?php

/**
 * Unit tests for the cquizaccess_timelimit plugin.
 *
 * @package    cquizaccess
 * @subpackage timelimit
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/cquiz/accessrule/timelimit/rule.php');

/**
 * Unit tests for the cquizaccess_timelimit plugin.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquizaccess_timelimit_testcase extends basic_testcase {

    public function test_time_limit_access_rule() {
        $cquiz = new stdClass();
        $cquiz->timelimit = 3600;
        $cm = new stdClass();
        $cm->id = 0;
        $cquizobj = new cquiz($cquiz, $cm, null);
        $rule = new cquizaccess_timelimit($cquizobj, 10000);
        $attempt = new stdClass();

        $this->assertEquals($rule->description(), get_string('cquiztimelimit', 'cquizaccess_timelimit', format_time(3600)));

        $attempt->timestart = 10000;
        $attempt->preview = 0;
        $this->assertEquals($rule->end_time($attempt), 13600);
        $this->assertEquals($rule->time_left_display($attempt, 10000), 3600);
        $this->assertEquals($rule->time_left_display($attempt, 12000), 1600);
        $this->assertEquals($rule->time_left_display($attempt, 14000), -400);

        $this->assertFalse($rule->prevent_access());
        $this->assertFalse($rule->prevent_new_attempt(0, $attempt));
        $this->assertFalse($rule->is_finished(0, $attempt));
    }

}

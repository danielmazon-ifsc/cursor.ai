<?php

/**
 * Unit tests for the cquizaccess_numattempts plugin.
 *
 * @package    cquizaccess
 * @subpackage numattempts
 * @category   phpunit
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/cquiz/accessrule/numattempts/rule.php');

/**
 * Unit tests for the cquizaccess_numattempts plugin.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquizaccess_numattempts_testcase extends basic_testcase {

    public function test_num_attempts_access_rule() {
        $cquiz = new stdClass();
        $cquiz->attempts = 3;
        $cm = new stdClass();
        $cm->id = 0;
        $cquizobj = new cquiz($cquiz, $cm, null);
        $rule = new cquizaccess_numattempts($cquizobj, 0);
        $attempt = new stdClass();

        $this->assertEquals($rule->description(), get_string('attemptsallowedn', 'cquizaccess_numattempts', 3));

        $this->assertFalse($rule->prevent_new_attempt(0, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(2, $attempt));
        $this->assertEquals($rule->prevent_new_attempt(3, $attempt), get_string('nomoreattempts', 'cquiz'));
        $this->assertEquals($rule->prevent_new_attempt(666, $attempt), get_string('nomoreattempts', 'cquiz'));

        $this->assertFalse($rule->is_finished(0, $attempt));
        $this->assertFalse($rule->is_finished(2, $attempt));
        $this->assertTrue($rule->is_finished(3, $attempt));
        $this->assertTrue($rule->is_finished(666, $attempt));

        $this->assertFalse($rule->prevent_access());
        $this->assertFalse($rule->end_time($attempt));
        $this->assertFalse($rule->time_left_display($attempt, 0));
    }

}

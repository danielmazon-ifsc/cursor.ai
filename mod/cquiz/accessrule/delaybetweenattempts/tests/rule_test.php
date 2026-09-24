<?php

/**
 * Unit tests for the cquizaccess_delaybetweenattempts plugin.
 *
 * @package    cquizaccess
 * @subpackage delaybetweenattempts
 * @category   phpunit
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/cquiz/accessrule/delaybetweenattempts/rule.php');

/**
 * Unit tests for the cquizaccess_delaybetweenattempts plugin.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquizaccess_delaybetweenattempts_testcase extends basic_testcase {

    public function test_just_first_delay() {
        $cquiz = new stdClass();
        $cquiz->attempts = 3;
        $cquiz->timelimit = 0;
        $cquiz->delay1 = 1000;
        $cquiz->delay2 = 0;
        $cquiz->timeclose = 0;
        $cm = new stdClass();
        $cm->id = 0;
        $cquizobj = new cquiz($cquiz, $cm, null);
        $attempt = new stdClass();
        $attempt->timefinish = 10000;

        $rule = new cquizaccess_delaybetweenattempts($cquizobj, 10000);
        $this->assertEmpty($rule->description());
        $this->assertFalse($rule->prevent_access());
        $this->assertFalse($rule->is_finished(0, $attempt));
        $this->assertFalse($rule->end_time($attempt));
        $this->assertFalse($rule->time_left_display($attempt, 0));

        $this->assertFalse($rule->prevent_new_attempt(0, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(3, $attempt));
        $this->assertEquals($rule->prevent_new_attempt(1, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(11000)));
        $this->assertFalse($rule->prevent_new_attempt(2, $attempt));
        $attempt->timefinish = 9000;
        $this->assertFalse($rule->prevent_new_attempt(1, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(2, $attempt));
        $attempt->timefinish = 9001;
        $this->assertEquals($rule->prevent_new_attempt(1, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(10001)));
        $this->assertFalse($rule->prevent_new_attempt(2, $attempt));
    }

    public function test_just_second_delay() {
        $cquiz = new stdClass();
        $cquiz->attempts = 5;
        $cquiz->timelimit = 0;
        $cquiz->delay1 = 0;
        $cquiz->delay2 = 1000;
        $cquiz->timeclose = 0;
        $cm = new stdClass();
        $cm->id = 0;
        $cquizobj = new cquiz($cquiz, $cm, null);
        $attempt = new stdClass();
        $attempt->timefinish = 10000;

        $rule = new cquizaccess_delaybetweenattempts($cquizobj, 10000);
        $this->assertEmpty($rule->description());
        $this->assertFalse($rule->prevent_access());
        $this->assertFalse($rule->is_finished(0, $attempt));
        $this->assertFalse($rule->end_time($attempt));
        $this->assertFalse($rule->time_left_display($attempt, 0));

        $this->assertFalse($rule->prevent_new_attempt(0, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(5, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(1, $attempt));
        $this->assertEquals($rule->prevent_new_attempt(2, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(11000)));
        $this->assertEquals($rule->prevent_new_attempt(3, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(11000)));
        $attempt->timefinish = 9000;
        $this->assertFalse($rule->prevent_new_attempt(1, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(2, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(3, $attempt));
        $attempt->timefinish = 9001;
        $this->assertFalse($rule->prevent_new_attempt(1, $attempt));
        $this->assertEquals($rule->prevent_new_attempt(2, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(10001)));
        $this->assertEquals($rule->prevent_new_attempt(4, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(10001)));
    }

    public function test_just_both_delays() {
        $cquiz = new stdClass();
        $cquiz->attempts = 5;
        $cquiz->timelimit = 0;
        $cquiz->delay1 = 2000;
        $cquiz->delay2 = 1000;
        $cquiz->timeclose = 0;
        $cm = new stdClass();
        $cm->id = 0;
        $cquizobj = new cquiz($cquiz, $cm, null);
        $attempt = new stdClass();
        $attempt->timefinish = 10000;

        $rule = new cquizaccess_delaybetweenattempts($cquizobj, 10000);
        $this->assertEmpty($rule->description());
        $this->assertFalse($rule->prevent_access());
        $this->assertFalse($rule->is_finished(0, $attempt));
        $this->assertFalse($rule->end_time($attempt));
        $this->assertFalse($rule->time_left_display($attempt, 0));

        $this->assertFalse($rule->prevent_new_attempt(0, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(5, $attempt));
        $this->assertEquals($rule->prevent_new_attempt(1, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(12000)));
        $this->assertEquals($rule->prevent_new_attempt(2, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(11000)));
        $this->assertEquals($rule->prevent_new_attempt(3, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(11000)));
        $attempt->timefinish = 8000;
        $this->assertFalse($rule->prevent_new_attempt(1, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(2, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(3, $attempt));
        $attempt->timefinish = 8001;
        $this->assertEquals($rule->prevent_new_attempt(1, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(10001)));
        $this->assertFalse($rule->prevent_new_attempt(2, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(4, $attempt));
        $attempt->timefinish = 9000;
        $this->assertEquals($rule->prevent_new_attempt(1, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(11000)));
        $this->assertFalse($rule->prevent_new_attempt(2, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(3, $attempt));
        $attempt->timefinish = 9001;
        $this->assertEquals($rule->prevent_new_attempt(1, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(11001)));
        $this->assertEquals($rule->prevent_new_attempt(2, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(10001)));
        $this->assertEquals($rule->prevent_new_attempt(4, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(10001)));
        $cquiz->attemptsmaxdelay = 1000;
        $attempt->timefinish = 8999;
        $this->assertEquals($rule->prevent_new_attempt(3, $attempt), get_string('waitedtoolong', 'cquizaccess_delaybetweenattempts', userdate($attempt->timefinish + $cquiz->attemptsmaxdelay)));
        $attempt->timefinish = 9000;
        $this->assertFalse($rule->prevent_new_attempt(3, $attempt));
    }

    public function test_with_close_date() {
        $cquiz = new stdClass();
        $cquiz->attempts = 5;
        $cquiz->timelimit = 0;
        $cquiz->delay1 = 2000;
        $cquiz->delay2 = 1000;
        $cquiz->timeclose = 15000;
        $cm = new stdClass();
        $cm->id = 0;
        $cquizobj = new cquiz($cquiz, $cm, null);
        $attempt = new stdClass();
        $attempt->timefinish = 13000;

        $rule = new cquizaccess_delaybetweenattempts($cquizobj, 10000);
        $this->assertEmpty($rule->description());
        $this->assertFalse($rule->prevent_access());
        $this->assertFalse($rule->is_finished(0, $attempt));
        $this->assertFalse($rule->end_time($attempt));
        $this->assertFalse($rule->time_left_display($attempt, 0));

        $attempt->timefinish = 13000;
        $this->assertEquals($rule->prevent_new_attempt(1, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(15000)));
        $attempt->timefinish = 13001;
        $this->assertEquals($rule->prevent_new_attempt(1, $attempt), get_string('youcannotwait', 'cquizaccess_delaybetweenattempts'));
        $attempt->timefinish = 14000;
        $this->assertEquals($rule->prevent_new_attempt(2, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(15000)));
        $attempt->timefinish = 14001;
        $this->assertEquals($rule->prevent_new_attempt(2, $attempt), get_string('youcannotwait', 'cquizaccess_delaybetweenattempts'));

        $rule = new cquizaccess_delaybetweenattempts($cquizobj, 15000);
        $attempt->timefinish = 13000;
        $this->assertFalse($rule->prevent_new_attempt(1, $attempt));
        $attempt->timefinish = 13001;
        $this->assertEquals($rule->prevent_new_attempt(1, $attempt), get_string('youcannotwait', 'cquizaccess_delaybetweenattempts'));
        $attempt->timefinish = 14000;
        $this->assertFalse($rule->prevent_new_attempt(2, $attempt));
        $attempt->timefinish = 14001;
        $this->assertEquals($rule->prevent_new_attempt(2, $attempt), get_string('youcannotwait', 'cquizaccess_delaybetweenattempts'));

        $rule = new cquizaccess_delaybetweenattempts($cquizobj, 15001);
        $attempt->timefinish = 13000;
        $this->assertFalse($rule->prevent_new_attempt(1, $attempt));
        $attempt->timefinish = 13001;
        $this->assertFalse($rule->prevent_new_attempt(1, $attempt));
        $attempt->timefinish = 14000;
        $this->assertFalse($rule->prevent_new_attempt(2, $attempt));
        $attempt->timefinish = 14001;
        $this->assertFalse($rule->prevent_new_attempt(2, $attempt));
    }

    public function test_time_limit_and_overdue() {
        $cquiz = new stdClass();
        $cquiz->attempts = 5;
        $cquiz->timelimit = 100;
        $cquiz->delay1 = 2000;
        $cquiz->delay2 = 1000;
        $cquiz->timeclose = 0;
        $cm = new stdClass();
        $cm->id = 0;
        $cquizobj = new cquiz($cquiz, $cm, null);
        $attempt = new stdClass();
        $attempt->timestart = 9900;
        $attempt->timefinish = 10100;

        $rule = new cquizaccess_delaybetweenattempts($cquizobj, 10000);
        $this->assertEmpty($rule->description());
        $this->assertFalse($rule->prevent_access());
        $this->assertFalse($rule->is_finished(0, $attempt));
        $this->assertFalse($rule->end_time($attempt));
        $this->assertFalse($rule->time_left_display($attempt, 0));

        $this->assertFalse($rule->prevent_new_attempt(0, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(5, $attempt));
        $this->assertEquals($rule->prevent_new_attempt(1, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(12000)));
        $this->assertEquals($rule->prevent_new_attempt(2, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(11000)));
        $this->assertEquals($rule->prevent_new_attempt(3, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(11000)));
        $attempt->timestart = 7950;
        $attempt->timefinish = 8000;
        $this->assertFalse($rule->prevent_new_attempt(1, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(2, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(3, $attempt));
        $attempt->timestart = 7950;
        $attempt->timefinish = 8001;
        $this->assertEquals($rule->prevent_new_attempt(1, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(10001)));
        $this->assertFalse($rule->prevent_new_attempt(2, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(4, $attempt));
        $attempt->timestart = 8950;
        $attempt->timefinish = 9000;
        $this->assertEquals($rule->prevent_new_attempt(1, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(11000)));
        $this->assertFalse($rule->prevent_new_attempt(2, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(3, $attempt));
        $attempt->timestart = 8950;
        $attempt->timefinish = 9001;
        $this->assertEquals($rule->prevent_new_attempt(1, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(11001)));
        $this->assertEquals($rule->prevent_new_attempt(2, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(10001)));
        $this->assertEquals($rule->prevent_new_attempt(4, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(10001)));
        $attempt->timestart = 8900;
        $attempt->timefinish = 9100;
        $this->assertEquals($rule->prevent_new_attempt(1, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(11000)));
        $this->assertFalse($rule->prevent_new_attempt(2, $attempt));
        $this->assertFalse($rule->prevent_new_attempt(3, $attempt));
        $attempt->timestart = 8901;
        $attempt->timefinish = 9100;
        $this->assertEquals($rule->prevent_new_attempt(1, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(11001)));
        $this->assertEquals($rule->prevent_new_attempt(2, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(10001)));
        $this->assertEquals($rule->prevent_new_attempt(4, $attempt), get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate(10001)));
    }

}

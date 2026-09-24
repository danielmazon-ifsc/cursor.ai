<?php

/**
 * Unit tests for the cquizaccess_safebrowser plugin.
 *
 * @package    cquizaccess
 * @subpackage safebrowser
 * @category   phpunit
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/cquiz/accessrule/safebrowser/rule.php');

/**
 * Unit tests for the cquizaccess_safebrowser plugin.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquizaccess_safebrowser_testcase extends basic_testcase {

    // Nothing very testable in this class, just test that it obeys the general access rule contact.
    public function test_safebrowser_access_rule() {
        $cquiz = new stdClass();
        $cquiz->browsersecurity = 'safebrowser';
        $cm = new stdClass();
        $cm->id = 0;
        $cquizobj = new cquiz($cquiz, $cm, null);
        $rule = new cquizaccess_safebrowser($cquizobj, 0);
        $attempt = new stdClass();

        // This next test assumes the unit tests are not being run using Safe Exam Browser!
        $_SERVER['HTTP_USER_AGENT'] = 'unknonw browser';
        $this->assertEquals(get_string('safebrowsererror', 'cquizaccess_safebrowser'), $rule->prevent_access());

        $this->assertEquals(get_string('safebrowsernotice', 'cquizaccess_safebrowser'), $rule->description());
        $this->assertFalse($rule->prevent_new_attempt(0, $attempt));
        $this->assertFalse($rule->is_finished(0, $attempt));
        $this->assertFalse($rule->end_time($attempt));
        $this->assertFalse($rule->time_left_display($attempt, 0));
    }

}

<?php

/**
 * Unit tests for the cquizaccess_securewindow plugin.
 *
 * @package    cquizaccess
 * @subpackage securewindow
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/cquiz/accessrule/securewindow/rule.php');

/**
 * Unit tests for the cquizaccess_securewindow plugin.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquizaccess_securewindow_testcase extends basic_testcase {

    public static $includecoverage = array('mod/cquiz/accessrule/securewindow/rule.php');

    // Nothing very testable in this class, just test that it obeys the general access rule contact.
    public function test_securewindow_access_rule() {
        $cquiz = new stdClass();
        $cquiz->browsersecurity = 'securewindow';
        $cm = new stdClass();
        $cm->id = 0;
        $cquizobj = new cquiz($cquiz, $cm, null);
        $rule = new cquizaccess_securewindow($cquizobj, 0);
        $attempt = new stdClass();

        $this->assertFalse($rule->prevent_access());
        $this->assertEmpty($rule->description());
        $this->assertFalse($rule->prevent_new_attempt(0, $attempt));
        $this->assertFalse($rule->is_finished(0, $attempt));
        $this->assertFalse($rule->end_time($attempt));
        $this->assertFalse($rule->time_left_display($attempt, 0));
    }

}

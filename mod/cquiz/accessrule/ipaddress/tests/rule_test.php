<?php

/**
 * Unit tests for the cquizaccess_ipaddress plugin.
 *
 * @package    cquizaccess
 * @subpackage ipaddress
 * @category   phpunit
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/cquiz/accessrule/ipaddress/rule.php');

/**
 * Unit tests for the cquizaccess_ipaddress plugin.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquizaccess_ipaddress_testcase extends basic_testcase {

    public function test_ipaddress_access_rule() {
        $cquiz = new stdClass();
        $attempt = new stdClass();
        $cm = new stdClass();
        $cm->id = 0;

        // Test the allowed case by getting the user's IP address. However, this
        // does not always work, for example using the mac install package on my laptop.
        $cquiz->subnet = getremoteaddr(null);
        if (!empty($cquiz->subnet)) {
            $cquizobj = new cquiz($cquiz, $cm, null);
            $rule = new cquizaccess_ipaddress($cquizobj, 0);

            $this->assertFalse($rule->prevent_access());
            $this->assertFalse($rule->description());
            $this->assertFalse($rule->prevent_new_attempt(0, $attempt));
            $this->assertFalse($rule->is_finished(0, $attempt));
            $this->assertFalse($rule->end_time($attempt));
            $this->assertFalse($rule->time_left_display($attempt, 0));
        }

        $cquiz->subnet = '0.0.0.0';
        $cquizobj = new cquiz($cquiz, $cm, null);
        $rule = new cquizaccess_ipaddress($cquizobj, 0);

        $this->assertNotEmpty($rule->prevent_access());
        $this->assertEmpty($rule->description());
        $this->assertFalse($rule->prevent_new_attempt(0, $attempt));
        $this->assertFalse($rule->is_finished(0, $attempt));
        $this->assertFalse($rule->end_time($attempt));
        $this->assertFalse($rule->time_left_display($attempt, 0));
    }

}

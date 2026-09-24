<?php

/**
 * Unit tests for the cquiz class.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/cquiz/locallib.php');

/**
 * Unit tests for the cquiz class
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class mod_cquiz_class_testcase extends basic_testcase {

    public function test_cannot_review_message() {
        $cquiz = new stdClass();
        $cquiz->reviewattempt = 0x10010;
        $cquiz->timeclose = 0;
        $cquiz->attempts = 0;

        $cm = new stdClass();
        $cm->id = 123;

        $cquizobj = new cquiz($cquiz, $cm, new stdClass(), false);

        $this->assertEquals('', $cquizobj->cannot_review_message(mod_cquiz_display_options::DURING));
        $this->assertEquals('', $cquizobj->cannot_review_message(mod_cquiz_display_options::IMMEDIATELY_AFTER));
        $this->assertEquals(get_string('noreview', 'cquiz'), $cquizobj->cannot_review_message(mod_cquiz_display_options::LATER_WHILE_OPEN));
        $this->assertEquals(get_string('noreview', 'cquiz'), $cquizobj->cannot_review_message(mod_cquiz_display_options::AFTER_CLOSE));

        $closetime = time() + 10000;
        $cquiz->timeclose = $closetime;
        $cquizobj = new cquiz($cquiz, $cm, new stdClass(), false);

        $this->assertEquals(get_string('noreviewuntil', 'cquiz', userdate($closetime)), $cquizobj->cannot_review_message(mod_cquiz_display_options::LATER_WHILE_OPEN));
    }

}

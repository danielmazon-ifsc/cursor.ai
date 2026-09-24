<?php

/**
 * PHPUnit data generator tests
 *
 * @package   mod_video
 * @category   phpunit
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit data generator testcase
 *
 * @package    mod_video
 * @category   phpunit
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_video_generator_testcase extends advanced_testcase {

    public function test_generator() {
        global $DB, $SITE;

        $this->resetAfterTest(true);

        $this->assertEquals(0, $DB->count_records('video'));

        /** @var mod_video_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_video');
        $this->assertInstanceOf('mod_video_generator', $generator);
        $this->assertEquals('video', $generator->get_modulename());

        $generator->create_instance(array('course' => $SITE->id));
        $generator->create_instance(array('course' => $SITE->id));
        $video = $generator->create_instance(array('course' => $SITE->id));
        $this->assertEquals(3, $DB->count_records('video'));

        $cm = get_coursemodule_from_instance('video', $video->id);
        $this->assertEquals($video->id, $cm->instance);
        $this->assertEquals('video', $cm->modname);
        $this->assertEquals($SITE->id, $cm->course);

        $context = context_module::instance($cm->id);
        $this->assertEquals($video->cmid, $context->instanceid);
    }

}

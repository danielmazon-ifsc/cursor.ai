<?php

/**
 * PHPUnit data generator tests
 *
 * @package    mod_cquiz
 * @category   phpunit
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit data generator testcase
 *
 * @package    mod_cquiz
 * @category   phpunit
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class mod_cquiz_generator_testcase extends advanced_testcase {

    public function test_generator() {
        global $DB, $SITE;

        $this->resetAfterTest(true);

        $this->assertEquals(0, $DB->count_records('cquiz'));

        /** @var mod_cquiz_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_cquiz');
        $this->assertInstanceOf('mod_cquiz_generator', $generator);
        $this->assertEquals('cquiz', $generator->get_modulename());

        $generator->create_instance(array('course' => $SITE->id));
        $generator->create_instance(array('course' => $SITE->id));
        $cquiz = $generator->create_instance(array('course' => $SITE->id));
        $this->assertEquals(3, $DB->count_records('cquiz'));

        $cm = get_coursemodule_from_instance('cquiz', $cquiz->id);
        $this->assertEquals($cquiz->id, $cm->instance);
        $this->assertEquals('cquiz', $cm->modname);
        $this->assertEquals($SITE->id, $cm->course);

        $context = context_module::instance($cm->id);
        $this->assertEquals($cquiz->cmid, $context->instanceid);
    }

}

<?php

/**
 * File containing the SCORM module local library function tests.
 *
 * @package mod_scorm
 * @category test
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/scorm/lib.php');

/**
 * Class containing the SCORM module local library function tests.
 *
 * @package mod_scorm
 * @category test
 */
class mod_scorm_locallib_testcase extends advanced_testcase {

    public function setUp() {
        $this->resetAfterTest();
    }

    public function test_scorm_update_calendar() {
        global $DB;

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a scorm activity.
        $time = time();
        $scorm = $this->getDataGenerator()->create_module('scorm', array(
            'course' => $course->id,
            'timeopen' => $time
                )
        );

        // Check that there is now an event in the database.
        $events = $DB->get_records('event');
        $this->assertCount(1, $events);

        // Get the event.
        $event = reset($events);

        // Confirm the event is correct.
        $this->assertEquals('scorm', $event->modulename);
        $this->assertEquals($scorm->id, $event->instance);
        $this->assertEquals(CALENDAR_EVENT_TYPE_ACTION, $event->type);
        $this->assertEquals(DATA_EVENT_TYPE_OPEN, $event->eventtype);
        $this->assertEquals($time, $event->timestart);
        $this->assertEquals($time, $event->timesort);
    }

    public function test_scorm_update_calendar_time_open_update() {
        global $DB;

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a scorm activity.
        $time = time();
        $scorm = $this->getDataGenerator()->create_module('scorm', array(
            'course' => $course->id,
            'timeopen' => $time
                )
        );

        // Set the time open and update the event.
        $scorm->timeopen = $time + DAYSECS;
        scorm_update_calendar($scorm, $scorm->cmid);

        // Check that there is an event in the database.
        $events = $DB->get_records('event');
        $this->assertCount(1, $events);

        // Get the event.
        $event = reset($events);

        // Confirm the event time was updated.
        $this->assertEquals('scorm', $event->modulename);
        $this->assertEquals($scorm->id, $event->instance);
        $this->assertEquals(CALENDAR_EVENT_TYPE_ACTION, $event->type);
        $this->assertEquals(DATA_EVENT_TYPE_OPEN, $event->eventtype);
        $this->assertEquals($time + DAYSECS, $event->timestart);
        $this->assertEquals($time + DAYSECS, $event->timesort);
    }

    public function test_scorm_update_calendar_time_open_delete() {
        global $DB;

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a scorm activity.
        $scorm = $this->getDataGenerator()->create_module('scorm', array('course' => $course->id));

        // Create a scorm activity.
        $time = time();
        $scorm = $this->getDataGenerator()->create_module('scorm', array(
            'course' => $course->id,
            'timeopen' => $time
                )
        );

        // Set the time open to 0 and update the event.
        $scorm->timeopen = 0;
        scorm_update_calendar($scorm, $scorm->cmid);

        // Confirm the event was deleted.
        $this->assertEquals(0, $DB->count_records('event'));
    }

    public function test_scorm_update_calendar_time_close() {
        global $DB;

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a scorm activity.
        $time = time();
        $scorm = $this->getDataGenerator()->create_module('scorm', array(
            'course' => $course->id,
            'timeclose' => $time
                )
        );

        // Check that there is now an event in the database.
        $events = $DB->get_records('event');
        $this->assertCount(1, $events);

        // Get the event.
        $event = reset($events);

        // Confirm the event is correct.
        $this->assertEquals('scorm', $event->modulename);
        $this->assertEquals($scorm->id, $event->instance);
        $this->assertEquals(CALENDAR_EVENT_TYPE_ACTION, $event->type);
        $this->assertEquals(DATA_EVENT_TYPE_CLOSE, $event->eventtype);
        $this->assertEquals($time, $event->timestart);
        $this->assertEquals($time, $event->timesort);
    }

    public function test_scorm_update_calendar_time_close_update() {
        global $DB;

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a scorm activity.
        $time = time();
        $scorm = $this->getDataGenerator()->create_module('scorm', array(
            'course' => $course->id,
            'timeclose' => $time
                )
        );

        // Set the time close and update the event.
        $scorm->timeclose = $time + DAYSECS;
        scorm_update_calendar($scorm, $scorm->cmid);

        // Check that there is an event in the database.
        $events = $DB->get_records('event');
        $this->assertCount(1, $events);

        // Get the event.
        $event = reset($events);

        // Confirm the event time was updated.
        $this->assertEquals('scorm', $event->modulename);
        $this->assertEquals($scorm->id, $event->instance);
        $this->assertEquals(CALENDAR_EVENT_TYPE_ACTION, $event->type);
        $this->assertEquals(DATA_EVENT_TYPE_CLOSE, $event->eventtype);
        $this->assertEquals($time + DAYSECS, $event->timestart);
        $this->assertEquals($time + DAYSECS, $event->timesort);
    }

    public function test_scorm_update_calendar_time_close_delete() {
        global $DB;

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a scorm activity.
        $scorm = $this->getDataGenerator()->create_module('scorm', array(
            'course' => $course->id,
            'timeclose' => time()
                )
        );

        // Set the time close to 0 and update the event.
        $scorm->timeclose = 0;
        scorm_update_calendar($scorm, $scorm->cmid);

        // Confirm the event time was deleted.
        $this->assertEquals(0, $DB->count_records('event'));
    }

}

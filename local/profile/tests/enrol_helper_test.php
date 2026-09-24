<?php
// This file is part of Moodle - http://moodle.org/
//
// @package   local_profile
// @copyright 2026 Viddia (http://viddia.com.br)
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for enrol helpers in local_profile.
 */
class local_profile_enrol_helper_testcase extends advanced_testcase {

    public function test_enrol_user_requires_single_instance(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual');
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual');

        $instances = $DB->count_records('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        $this->assertGreaterThan(1, $instances);

        $this->assertFalse(local_profile_enrol_user($user->id, $course->id, 'student', 'manual'));
    }

    public function test_enrol_in_course_auto_uses_manual_when_available(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->assertTrue(local_profile_enrol_in_course_auto($user->id, $course->id));
        $this->assertTrue(is_enrolled(context_course::instance($course->id), $user));
    }
}

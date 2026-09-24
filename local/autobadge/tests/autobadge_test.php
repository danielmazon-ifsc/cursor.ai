<?php
// This file is part of Moodle - http://moodle.org/
//
// @package   local_autobadge
// @copyright 2026 Viddia (http://viddia.com.br)
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

use local_autobadge\autobadge;

/**
 * @covers \local_autobadge\autobadge
 */
class local_autobadge_autobadge_testcase extends advanced_testcase {

    /** @var \phpunit_badge_generator */
    private $badgegen;

    protected function setUp(): void {
        parent::setUp();
        $this->badgegen = $this->getDataGenerator()->get_plugin_generator('core_badges');
    }

    public function test_resolve_badge_record_prefers_course_scope(): void {
        $this->resetAfterTest(true);

        $coursea = $this->getDataGenerator()->create_course(['shortname' => 'EIXO1']);
        $courseb = $this->getDataGenerator()->create_course(['shortname' => 'EIXO1']);

        $badgea = $this->badgegen->create_badge(['name' => 'EIXO1', 'courseid' => $coursea->id]);
        $this->badgegen->create_badge(['name' => 'EIXO1', 'courseid' => $courseb->id]);

        $resolved = autobadge::resolve_badge_record((int) $coursea->id, 'EIXO1');
        $this->assertNotNull($resolved);
        $this->assertEquals($badgea->id, $resolved->id);
    }

    public function test_resolve_badge_record_legacy_single_name(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['shortname' => 'UNIQ']);
        $badge = $this->badgegen->create_badge(['name' => 'UNIQ', 'courseid' => 0]);

        $resolved = autobadge::resolve_badge_record((int) $course->id, 'UNIQ');
        $this->assertNotNull($resolved);
        $this->assertEquals($badge->id, $resolved->id);
    }

    public function test_resolve_badge_record_ambiguous_returns_null(): void {
        $this->resetAfterTest(true);

        $coursea = $this->getDataGenerator()->create_course(['shortname' => 'DUP']);
        $courseb = $this->getDataGenerator()->create_course(['shortname' => 'DUP']);
        $this->badgegen->create_badge(['name' => 'DUP', 'courseid' => $coursea->id]);
        $this->badgegen->create_badge(['name' => 'DUP', 'courseid' => $courseb->id]);

        $this->assertNull(autobadge::resolve_badge_record((int) $coursea->id, 'DUP'));
    }
}

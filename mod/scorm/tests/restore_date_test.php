<?php

/**
 * Restore date tests.
 *
 * @package    mod_scorm
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . "/phpunit/classes/restore_date_testcase.php");

/**
 * Restore date tests.
 *
 * @package    mod_scorm
 */
class mod_scorm_restore_date_testcase extends restore_date_testcase {

    public function test_restore_dates() {
        global $DB, $USER;

        $time = 10000;

        list($course, $scorm) = $this->create_course_and_module('scorm', ['timeopen' => $time, 'timeclose' => $time]);
        $scoes = scorm_get_scoes($scorm->id);
        $sco = array_shift($scoes);
        scorm_insert_track($USER->id, $scorm->id, $sco->id, 4, 'cmi.core.score.raw', 10);

        // We do not want second differences to fail our test because of execution delays.
        $DB->set_field('scorm_scoes_track', 'timemodified', $time);

        // Do backup and restore.
        $newcourseid = $this->backup_and_restore($course);
        $newscorm = $DB->get_record('scorm', ['course' => $newcourseid]);

        $this->assertFieldsNotRolledForward($scorm, $newscorm, ['timemodified']);
        $props = ['timeopen', 'timeclose'];
        $this->assertFieldsRolledForward($scorm, $newscorm, $props);

        $tracks = $DB->get_records('scorm_scoes_track', ['scormid' => $newscorm->id]);
        foreach ($tracks as $track) {
            $this->assertEquals($time, $track->timemodified);
        }
    }

}

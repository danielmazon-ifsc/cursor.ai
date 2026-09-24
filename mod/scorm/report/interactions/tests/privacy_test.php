<?php

/**
 * Unit tests for the scormreport_interactions implementation of the privacy API.
 *
 * @package    scormreport_interactions
 * @category   test
 */
defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\writer;
use scormreport_interactions\privacy\provider;

/**
 * Unit tests for the scormreport_interactions implementation of the privacy API.
 *
 */
class scormreport_interactions_privacy_testcase extends \core_privacy\tests\provider_testcase {

    /**
     * Basic setup for these tests.
     */
    public function setUp() {
        $this->resetAfterTest(true);
    }

    /**
     * Ensure that export_user_preferences returns no data if the user has no data.
     */
    public function test_export_user_preferences_not_defined() {
        $user = \core_user::get_user_by_username('admin');
        provider::export_user_preferences($user->id);

        $writer = writer::with_context(\context_system::instance());
        $this->assertFalse($writer->has_any_data());
    }

    /**
     * Ensure that export_user_preferences returns single preferences.
     */
    public function test_export_user_preferences_single() {
        // Define a user preference.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_user_preference('scorm_report_pagesize', 50);
        set_user_preference('scorm_report_interactions_qtext', 1);
        set_user_preference('scorm_report_interactions_resp', 0);
        set_user_preference('scorm_report_interactions_right', 1);
        set_user_preference('scorm_report_interactions_result', 1);

        // Validate exported data.
        provider::export_user_preferences($user->id);
        $context = \context_user::instance($user->id);
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $prefs = $writer->get_user_preferences('scormreport_interactions');
        $this->assertCount(5, (array) $prefs);
        $this->assertEquals(
                get_string('privacy:metadata:preference:scorm_report_pagesize', 'scormreport_interactions'), $prefs->scorm_report_pagesize->description
        );
        $this->assertEquals(50, $prefs->scorm_report_pagesize->value);
        $this->assertEquals(
                get_string('privacy:metadata:preference:scorm_report_interactions_qtext', 'scormreport_interactions'), $prefs->scorm_report_interactions_qtext->description
        );
        $this->assertEquals(get_string('yes'), $prefs->scorm_report_interactions_qtext->value);
        $this->assertEquals(get_string('no'), $prefs->scorm_report_interactions_resp->value);
    }

}

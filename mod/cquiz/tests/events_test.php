<?php

/**
 * Cquiz events tests.
 *
 * @package    mod_cquiz
 * @category   phpunit
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/cquiz/attemptlib.php');

/**
 * Unit tests for cquiz events.
 *
 * @package    mod_cquiz
 * @category   phpunit
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class mod_cquiz_events_testcase extends advanced_testcase {

    /**
     * Setup some convenience test data with a single attempt.
     *
     * @param bool $ispreview Make the attempt a preview attempt when true.
     */
    protected function prepare_cquiz_data($ispreview = false) {

        $this->resetAfterTest(true);

        // Create a course
        $course = $this->getDataGenerator()->create_course();

        // Make a cquiz.
        $cquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_cquiz');

        $cquiz = $cquizgenerator->create_instance(array('course' => $course->id, 'questionsperpage' => 0,
            'grade' => 100.0, 'sumgrades' => 2));

        $cm = get_coursemodule_from_instance('cquiz', $cquiz->id, $course->id);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));

        // Add them to the cquiz.
        cquiz_add_cquiz_question($saq->id, $cquiz);
        cquiz_add_cquiz_question($numq->id, $cquiz);

        // Make a user to do the cquiz.
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        $cquizobj = cquiz::create($cquiz->id, $user1->id);

        // Start the attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_cquiz', $cquizobj->get_context());
        $quba->set_preferred_behaviour($cquizobj->get_cquiz()->preferredbehaviour);

        $timenow = time();
        $attempt = cquiz_create_attempt($cquizobj, 1, false, $timenow, $ispreview);
        cquiz_start_new_attempt($cquizobj, $quba, $attempt, 1, $timenow);
        cquiz_attempt_save_started($cquizobj, $quba, $attempt);

        return array($cquizobj, $quba, $attempt);
    }

    public function test_attempt_submitted() {

        list($cquizobj, $quba, $attempt) = $this->prepare_cquiz_data();
        $attemptobj = cquiz_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();

        $timefinish = time();
        $attemptobj->process_finish($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(4, $events);
        $event = $events[2];
        $this->assertInstanceOf('\mod_cquiz\event\attempt_submitted', $event);
        $this->assertEquals('cquiz_attempts', $event->objecttable);
        $this->assertEquals($cquizobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertEquals(null, $event->other['submitterid']); // Should be the user, but PHP Unit complains...
        $this->assertEquals('cquiz_attempt_submitted', $event->get_legacy_eventname());
        $legacydata = new stdClass();
        $legacydata->component = 'mod_cquiz';
        $legacydata->attemptid = (string) $attempt->id;
        $legacydata->timestamp = $timefinish;
        $legacydata->userid = $attempt->userid;
        $legacydata->cmid = $cquizobj->get_cmid();
        $legacydata->courseid = $cquizobj->get_courseid();
        $legacydata->cquizid = $cquizobj->get_cquizid();
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $legacydata->submitterid = null;
        $legacydata->timefinish = $timefinish;
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_becameoverdue() {

        list($cquizobj, $quba, $attempt) = $this->prepare_cquiz_data();
        $attemptobj = cquiz_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();
        $timefinish = time();
        $attemptobj->process_going_overdue($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf('\mod_cquiz\event\attempt_becameoverdue', $event);
        $this->assertEquals('cquiz_attempts', $event->objecttable);
        $this->assertEquals($cquizobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertNotEmpty($event->get_description());
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $this->assertEquals(null, $event->other['submitterid']);
        $this->assertEquals('cquiz_attempt_overdue', $event->get_legacy_eventname());
        $legacydata = new stdClass();
        $legacydata->component = 'mod_cquiz';
        $legacydata->attemptid = (string) $attempt->id;
        $legacydata->timestamp = $timefinish;
        $legacydata->userid = $attempt->userid;
        $legacydata->cmid = $cquizobj->get_cmid();
        $legacydata->courseid = $cquizobj->get_courseid();
        $legacydata->cquizid = $cquizobj->get_cquizid();
        $legacydata->submitterid = null; // Should be the user, but PHP Unit complains...
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_abandoned() {

        list($cquizobj, $quba, $attempt) = $this->prepare_cquiz_data();
        $attemptobj = cquiz_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();
        $timefinish = time();
        $attemptobj->process_abandon($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf('\mod_cquiz\event\attempt_abandoned', $event);
        $this->assertEquals('cquiz_attempts', $event->objecttable);
        $this->assertEquals($cquizobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $this->assertEquals(null, $event->other['submitterid']);
        $this->assertEquals('cquiz_attempt_abandoned', $event->get_legacy_eventname());
        $legacydata = new stdClass();
        $legacydata->component = 'mod_cquiz';
        $legacydata->attemptid = (string) $attempt->id;
        $legacydata->timestamp = $timefinish;
        $legacydata->userid = $attempt->userid;
        $legacydata->cmid = $cquizobj->get_cmid();
        $legacydata->courseid = $cquizobj->get_courseid();
        $legacydata->cquizid = $cquizobj->get_cquizid();
        $legacydata->submitterid = null; // Should be the user, but PHP Unit complains...
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_started() {
        list($cquizobj, $quba, $attempt) = $this->prepare_cquiz_data();

        // Create another attempt.
        $attempt = cquiz_create_attempt($cquizobj, 1, false, time(), false, 2);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        cquiz_attempt_save_started($cquizobj, $quba, $attempt);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_cquiz\event\attempt_started', $event);
        $this->assertEquals('cquiz_attempts', $event->objecttable);
        $this->assertEquals($attempt->id, $event->objectid);
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertEquals($cquizobj->get_context(), $event->get_context());
        $this->assertEquals('cquiz_attempt_started', $event->get_legacy_eventname());
        $this->assertEquals(context_module::instance($cquizobj->get_cmid()), $event->get_context());
        // Check legacy log data.
        $expected = array($cquizobj->get_courseid(), 'cquiz', 'attempt', 'review.php?attempt=' . $attempt->id,
            $cquizobj->get_cquizid(), $cquizobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        // Check legacy event data.
        $legacydata = new stdClass();
        $legacydata->component = 'mod_cquiz';
        $legacydata->attemptid = $attempt->id;
        $legacydata->timestart = $attempt->timestart;
        $legacydata->timestamp = $attempt->timestart;
        $legacydata->userid = $attempt->userid;
        $legacydata->cquizid = $cquizobj->get_cquizid();
        $legacydata->cmid = $cquizobj->get_cmid();
        $legacydata->courseid = $cquizobj->get_courseid();
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the edit page viewed event.
     *
     * There is no external API for updating a cquiz, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_edit_page_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cquiz = $this->getDataGenerator()->create_module('cquiz', array('course' => $course->id));

        $params = array(
            'courseid' => $course->id,
            'context' => context_module::instance($cquiz->cmid),
            'other' => array(
                'cquizid' => $cquiz->id
            )
        );
        $event = \mod_cquiz\event\edit_page_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_cquiz\event\edit_page_viewed', $event);
        $this->assertEquals(context_module::instance($cquiz->cmid), $event->get_context());
        $expected = array($course->id, 'cquiz', 'editquestions', 'view.php?id=' . $cquiz->cmid, $cquiz->id, $cquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt deleted event.
     */
    public function test_attempt_deleted() {
        list($cquizobj, $quba, $attempt) = $this->prepare_cquiz_data();

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        cquiz_delete_attempt($attempt, $cquizobj->get_cquiz());
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_cquiz\event\attempt_deleted', $event);
        $this->assertEquals(context_module::instance($cquizobj->get_cmid()), $event->get_context());
        $expected = array($cquizobj->get_courseid(), 'cquiz', 'delete attempt', 'report.php?id=' . $cquizobj->get_cmid(),
            $attempt->id, $cquizobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test that preview attempt deletions are not logged.
     */
    public function test_preview_attempt_deleted() {
        // Create cquiz with preview attempt.
        list($cquizobj, $quba, $previewattempt) = $this->prepare_cquiz_data(true);

        // Delete a preview attempt, capturing events.
        $sink = $this->redirectEvents();
        cquiz_delete_attempt($previewattempt, $cquizobj->get_cquiz());

        // Verify that no events were generated.
        $this->assertEmpty($sink->get_events());
    }

    /**
     * Test the report viewed event.
     *
     * There is no external API for viewing reports, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_report_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cquiz = $this->getDataGenerator()->create_module('cquiz', array('course' => $course->id));

        $params = array(
            'context' => $context = context_module::instance($cquiz->cmid),
            'other' => array(
                'cquizid' => $cquiz->id,
                'reportname' => 'overview'
            )
        );
        $event = \mod_cquiz\event\report_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_cquiz\event\report_viewed', $event);
        $this->assertEquals(context_module::instance($cquiz->cmid), $event->get_context());
        $expected = array($course->id, 'cquiz', 'report', 'report.php?id=' . $cquiz->cmid . '&mode=overview',
            $cquiz->id, $cquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt reviewed event.
     *
     * There is no external API for reviewing attempts, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_reviewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cquiz = $this->getDataGenerator()->create_module('cquiz', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => context_module::instance($cquiz->cmid),
            'other' => array(
                'cquizid' => $cquiz->id
            )
        );
        $event = \mod_cquiz\event\attempt_reviewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_cquiz\event\attempt_reviewed', $event);
        $this->assertEquals(context_module::instance($cquiz->cmid), $event->get_context());
        $expected = array($course->id, 'cquiz', 'review', 'review.php?attempt=1', $cquiz->id, $cquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt summary viewed event.
     *
     * There is no external API for viewing the attempt summary, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_summary_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cquiz = $this->getDataGenerator()->create_module('cquiz', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => context_module::instance($cquiz->cmid),
            'other' => array(
                'cquizid' => $cquiz->id
            )
        );
        $event = \mod_cquiz\event\attempt_summary_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_cquiz\event\attempt_summary_viewed', $event);
        $this->assertEquals(context_module::instance($cquiz->cmid), $event->get_context());
        $expected = array($course->id, 'cquiz', 'view summary', 'summary.php?attempt=1', $cquiz->id, $cquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override created event.
     *
     * There is no external API for creating a user override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_user_override_created() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cquiz = $this->getDataGenerator()->create_module('cquiz', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'context' => context_module::instance($cquiz->cmid),
            'other' => array(
                'cquizid' => $cquiz->id
            )
        );
        $event = \mod_cquiz\event\user_override_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_cquiz\event\user_override_created', $event);
        $this->assertEquals(context_module::instance($cquiz->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override created event.
     *
     * There is no external API for creating a group override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_group_override_created() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cquiz = $this->getDataGenerator()->create_module('cquiz', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'context' => context_module::instance($cquiz->cmid),
            'other' => array(
                'cquizid' => $cquiz->id,
                'groupid' => 2
            )
        );
        $event = \mod_cquiz\event\group_override_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_cquiz\event\group_override_created', $event);
        $this->assertEquals(context_module::instance($cquiz->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override updated event.
     *
     * There is no external API for updating a user override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_user_override_updated() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cquiz = $this->getDataGenerator()->create_module('cquiz', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'context' => context_module::instance($cquiz->cmid),
            'other' => array(
                'cquizid' => $cquiz->id
            )
        );
        $event = \mod_cquiz\event\user_override_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_cquiz\event\user_override_updated', $event);
        $this->assertEquals(context_module::instance($cquiz->cmid), $event->get_context());
        $expected = array($course->id, 'cquiz', 'edit override', 'overrideedit.php?id=1', $cquiz->id, $cquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override updated event.
     *
     * There is no external API for updating a group override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_group_override_updated() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cquiz = $this->getDataGenerator()->create_module('cquiz', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'context' => context_module::instance($cquiz->cmid),
            'other' => array(
                'cquizid' => $cquiz->id,
                'groupid' => 2
            )
        );
        $event = \mod_cquiz\event\group_override_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_cquiz\event\group_override_updated', $event);
        $this->assertEquals(context_module::instance($cquiz->cmid), $event->get_context());
        $expected = array($course->id, 'cquiz', 'edit override', 'overrideedit.php?id=1', $cquiz->id, $cquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override deleted event.
     */
    public function test_user_override_deleted() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cquiz = $this->getDataGenerator()->create_module('cquiz', array('course' => $course->id));

        // Create an override.
        $override = new stdClass();
        $override->cquiz = $cquiz->id;
        $override->userid = 2;
        $override->id = $DB->insert_record('cquiz_overrides', $override);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        cquiz_delete_override($cquiz, $override->id);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_cquiz\event\user_override_deleted', $event);
        $this->assertEquals(context_module::instance($cquiz->cmid), $event->get_context());
        $expected = array($course->id, 'cquiz', 'delete override', 'overrides.php?cmid=' . $cquiz->cmid, $cquiz->id, $cquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override deleted event.
     */
    public function test_group_override_deleted() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cquiz = $this->getDataGenerator()->create_module('cquiz', array('course' => $course->id));

        // Create an override.
        $override = new stdClass();
        $override->cquiz = $cquiz->id;
        $override->groupid = 2;
        $override->id = $DB->insert_record('cquiz_overrides', $override);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        cquiz_delete_override($cquiz, $override->id);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_cquiz\event\group_override_deleted', $event);
        $this->assertEquals(context_module::instance($cquiz->cmid), $event->get_context());
        $expected = array($course->id, 'cquiz', 'delete override', 'overrides.php?cmid=' . $cquiz->cmid, $cquiz->id, $cquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt viewed event.
     *
     * There is no external API for continuing an attempt, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cquiz = $this->getDataGenerator()->create_module('cquiz', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => context_module::instance($cquiz->cmid),
            'other' => array(
                'cquizid' => $cquiz->id
            )
        );
        $event = \mod_cquiz\event\attempt_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_cquiz\event\attempt_viewed', $event);
        $this->assertEquals(context_module::instance($cquiz->cmid), $event->get_context());
        $expected = array($course->id, 'cquiz', 'continue attempt', 'review.php?attempt=1', $cquiz->id, $cquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt previewed event.
     */
    public function test_attempt_preview_started() {
        list($cquizobj, $quba, $attempt) = $this->prepare_cquiz_data();

        // We want to preview this attempt.
        $attempt = cquiz_create_attempt($cquizobj, 1, false, time(), false, 2);
        $attempt->preview = 1;

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        cquiz_attempt_save_started($cquizobj, $quba, $attempt);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_cquiz\event\attempt_preview_started', $event);
        $this->assertEquals(context_module::instance($cquizobj->get_cmid()), $event->get_context());
        $expected = array($cquizobj->get_courseid(), 'cquiz', 'preview', 'view.php?id=' . $cquizobj->get_cmid(),
            $cquizobj->get_cquizid(), $cquizobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the question manually graded event.
     *
     * There is no external API for manually grading a question, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_question_manually_graded() {
        list($cquizobj, $quba, $attempt) = $this->prepare_cquiz_data();

        $params = array(
            'objectid' => 1,
            'courseid' => $cquizobj->get_courseid(),
            'context' => context_module::instance($cquizobj->get_cmid()),
            'other' => array(
                'cquizid' => $cquizobj->get_cquizid(),
                'attemptid' => 2,
                'slot' => 3
            )
        );
        $event = \mod_cquiz\event\question_manually_graded::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_cquiz\event\question_manually_graded', $event);
        $this->assertEquals(context_module::instance($cquizobj->get_cmid()), $event->get_context());
        $expected = array($cquizobj->get_courseid(), 'cquiz', 'manualgrade', 'comment.php?attempt=2&slot=3',
            $cquizobj->get_cquizid(), $cquizobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

}

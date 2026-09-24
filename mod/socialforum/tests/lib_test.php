<?php

/**
 * The module social forums tests
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/socialforum/lib.php');
require_once($CFG->dirroot . '/rating/lib.php');

class mod_socialforum_lib_testcase extends advanced_testcase {

    public function setUp() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_socialforum\subscriptions::reset_socialforum_cache();
    }

    public function tearDown() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_socialforum\subscriptions::reset_socialforum_cache();
    }

    public function test_socialforum_trigger_content_uploaded_event() {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $socialforum = $this->getDataGenerator()->create_module('socialforum', array('course' => $course->id));
        $context = context_module::instance($socialforum->cmid);

        $this->setUser($user->id);
        $fakepost = (object) array('id' => 123, 'message' => 'Yay!', 'discussion' => 100);
        $cm = get_coursemodule_from_instance('socialforum', $socialforum->id);

        $fs = get_file_storage();
        $dummy = (object) array(
                    'contextid' => $context->id,
                    'component' => 'mod_socialforum',
                    'filearea' => 'attachment',
                    'itemid' => $fakepost->id,
                    'filepath' => '/',
                    'filename' => 'myassignmnent.pdf'
        );
        $fi = $fs->create_file_from_string($dummy, 'Content of ' . $dummy->filename);

        $data = new stdClass();
        $sink = $this->redirectEvents();
        socialforum_trigger_content_uploaded_event($fakepost, $cm, 'some triggered from value');
        $events = $sink->get_events();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_socialforum\event\assessable_uploaded', $event);
        $this->assertEquals($context->id, $event->contextid);
        $this->assertEquals($fakepost->id, $event->objectid);
        $this->assertEquals($fakepost->message, $event->other['content']);
        $this->assertEquals($fakepost->discussion, $event->other['discussionid']);
        $this->assertCount(1, $event->other['pathnamehashes']);
        $this->assertEquals($fi->get_pathnamehash(), $event->other['pathnamehashes'][0]);
        $expected = new stdClass();
        $expected->modulename = 'socialforum';
        $expected->name = 'some triggered from value';
        $expected->cmid = $socialforum->cmid;
        $expected->itemid = $fakepost->id;
        $expected->courseid = $course->id;
        $expected->userid = $user->id;
        $expected->content = $fakepost->message;
        $expected->pathnamehashes = array($fi->get_pathnamehash());
        $this->assertEventLegacyData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_socialforum_get_courses_user_posted_in() {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();

        // Create 3 social forums, one in each course.
        $record = new stdClass();
        $record->course = $course1->id;
        $socialforum1 = $this->getDataGenerator()->create_module('socialforum', $record);

        $record = new stdClass();
        $record->course = $course2->id;
        $socialforum2 = $this->getDataGenerator()->create_module('socialforum', $record);

        $record = new stdClass();
        $record->course = $course3->id;
        $socialforum3 = $this->getDataGenerator()->create_module('socialforum', $record);

        // Add a second social forum in course 1.
        $record = new stdClass();
        $record->course = $course1->id;
        $socialforum4 = $this->getDataGenerator()->create_module('socialforum', $record);

        // Add discussions to course 1 started by user1.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->socialforum = $socialforum1->id;
        $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->socialforum = $socialforum4->id;
        $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // Add discussions to course2 started by user1.
        $record = new stdClass();
        $record->course = $course2->id;
        $record->userid = $user1->id;
        $record->socialforum = $socialforum2->id;
        $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // Add discussions to course 3 started by user2.
        $record = new stdClass();
        $record->course = $course3->id;
        $record->userid = $user2->id;
        $record->socialforum = $socialforum3->id;
        $discussion3 = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // Add post to course 3 by user1.
        $record = new stdClass();
        $record->course = $course3->id;
        $record->userid = $user1->id;
        $record->socialforum = $socialforum3->id;
        $record->discussion = $discussion3->id;
        $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_post($record);

        // User 3 hasn't posted anything, so shouldn't get any results.
        $user3courses = socialforum_get_courses_user_posted_in($user3);
        $this->assertEmpty($user3courses);

        // User 2 has only posted in course3.
        $user2courses = socialforum_get_courses_user_posted_in($user2);
        $this->assertCount(1, $user2courses);
        $user2course = array_shift($user2courses);
        $this->assertEquals($course3->id, $user2course->id);
        $this->assertEquals($course3->shortname, $user2course->shortname);

        // User 1 has posted in all 3 courses.
        $user1courses = socialforum_get_courses_user_posted_in($user1);
        $this->assertCount(3, $user1courses);
        foreach ($user1courses as $course) {
            $this->assertContains($course->id, array($course1->id, $course2->id, $course3->id));
            $this->assertContains($course->shortname, array($course1->shortname, $course2->shortname,
                $course3->shortname));
        }

        // User 1 has only started a discussion in course 1 and 2 though.
        $user1courses = socialforum_get_courses_user_posted_in($user1, true);
        $this->assertCount(2, $user1courses);
        foreach ($user1courses as $course) {
            $this->assertContains($course->id, array($course1->id, $course2->id));
            $this->assertContains($course->shortname, array($course1->shortname, $course2->shortname));
        }
    }

    /**
     * Test the logic in the socialforum_tp_can_track_socialforums() function.
     */
    public function test_socialforum_tp_can_track_socialforums() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackforums' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackforums' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => SOCIALFORUM_TRACKING_OFF); // Off.
        $socialforumoff = $this->getDataGenerator()->create_module('socialforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => SOCIALFORUM_TRACKING_FORCED); // On.
        $socialforumforce = $this->getDataGenerator()->create_module('socialforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => SOCIALFORUM_TRACKING_OPTIONAL); // Optional.
        $socialforumoptional = $this->getDataGenerator()->create_module('socialforum', $options);

        // Allow force.
        $CFG->socialforum_allowforcedreadtracking = 1;

        // User on, social forum off, should be off.
        $result = socialforum_tp_can_track_socialforums($socialforumoff, $useron);
        $this->assertEquals(false, $result);

        // User on, social forum on, should be on.
        $result = socialforum_tp_can_track_socialforums($socialforumforce, $useron);
        $this->assertEquals(true, $result);

        // User on, social forum optional, should be on.
        $result = socialforum_tp_can_track_socialforums($socialforumoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, social forum off, should be off.
        $result = socialforum_tp_can_track_socialforums($socialforumoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, social forum force, should be on.
        $result = socialforum_tp_can_track_socialforums($socialforumforce, $useroff);
        $this->assertEquals(true, $result);

        // User off, social forum optional, should be off.
        $result = socialforum_tp_can_track_socialforums($socialforumoptional, $useroff);
        $this->assertEquals(false, $result);

        // Don't allow force.
        $CFG->socialforum_allowforcedreadtracking = 0;

        // User on, social forum off, should be off.
        $result = socialforum_tp_can_track_socialforums($socialforumoff, $useron);
        $this->assertEquals(false, $result);

        // User on, social forum on, should be on.
        $result = socialforum_tp_can_track_socialforums($socialforumforce, $useron);
        $this->assertEquals(true, $result);

        // User on, social forum optional, should be on.
        $result = socialforum_tp_can_track_socialforums($socialforumoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, social forum off, should be off.
        $result = socialforum_tp_can_track_socialforums($socialforumoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, social forum force, should be off.
        $result = socialforum_tp_can_track_socialforums($socialforumforce, $useroff);
        $this->assertEquals(false, $result);

        // User off, social forum optional, should be off.
        $result = socialforum_tp_can_track_socialforums($socialforumoptional, $useroff);
        $this->assertEquals(false, $result);
    }

    /**
     * Test the logic in the test_socialforum_tp_is_tracked() function.
     */
    public function test_socialforum_tp_is_tracked() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackforums' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackforums' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => SOCIALFORUM_TRACKING_OFF); // Off.
        $socialforumoff = $this->getDataGenerator()->create_module('socialforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => SOCIALFORUM_TRACKING_FORCED); // On.
        $socialforumforce = $this->getDataGenerator()->create_module('socialforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => SOCIALFORUM_TRACKING_OPTIONAL); // Optional.
        $socialforumoptional = $this->getDataGenerator()->create_module('socialforum', $options);

        // Allow force.
        $CFG->socialforum_allowforcedreadtracking = 1;

        // User on, social forum off, should be off.
        $result = socialforum_tp_is_tracked($socialforumoff, $useron);
        $this->assertEquals(false, $result);

        // User on, social forum force, should be on.
        $result = socialforum_tp_is_tracked($socialforumforce, $useron);
        $this->assertEquals(true, $result);

        // User on, social forum optional, should be on.
        $result = socialforum_tp_is_tracked($socialforumoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, social forum off, should be off.
        $result = socialforum_tp_is_tracked($socialforumoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, social forum force, should be on.
        $result = socialforum_tp_is_tracked($socialforumforce, $useroff);
        $this->assertEquals(true, $result);

        // User off, social forum optional, should be off.
        $result = socialforum_tp_is_tracked($socialforumoptional, $useroff);
        $this->assertEquals(false, $result);

        // Don't allow force.
        $CFG->socialforum_allowforcedreadtracking = 0;

        // User on, social forum off, should be off.
        $result = socialforum_tp_is_tracked($socialforumoff, $useron);
        $this->assertEquals(false, $result);

        // User on, social forum force, should be on.
        $result = socialforum_tp_is_tracked($socialforumforce, $useron);
        $this->assertEquals(true, $result);

        // User on, social forum optional, should be on.
        $result = socialforum_tp_is_tracked($socialforumoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, social forum off, should be off.
        $result = socialforum_tp_is_tracked($socialforumoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, social forum force, should be off.
        $result = socialforum_tp_is_tracked($socialforumforce, $useroff);
        $this->assertEquals(false, $result);

        // User off, social forum optional, should be off.
        $result = socialforum_tp_is_tracked($socialforumoptional, $useroff);
        $this->assertEquals(false, $result);

        // Stop tracking so we can test again.
        socialforum_tp_stop_tracking($socialforumforce->id, $useron->id);
        socialforum_tp_stop_tracking($socialforumoptional->id, $useron->id);
        socialforum_tp_stop_tracking($socialforumforce->id, $useroff->id);
        socialforum_tp_stop_tracking($socialforumoptional->id, $useroff->id);

        // Allow force.
        $CFG->socialforum_allowforcedreadtracking = 1;

        // User on, preference off, social forum force, should be on.
        $result = socialforum_tp_is_tracked($socialforumforce, $useron);
        $this->assertEquals(true, $result);

        // User on, preference off, social forum optional, should be on.
        $result = socialforum_tp_is_tracked($socialforumoptional, $useron);
        $this->assertEquals(false, $result);

        // User off, preference off, social forum force, should be on.
        $result = socialforum_tp_is_tracked($socialforumforce, $useroff);
        $this->assertEquals(true, $result);

        // User off, preference off, social forum optional, should be off.
        $result = socialforum_tp_is_tracked($socialforumoptional, $useroff);
        $this->assertEquals(false, $result);

        // Don't allow force.
        $CFG->socialforum_allowforcedreadtracking = 0;

        // User on, preference off, social forum force, should be on.
        $result = socialforum_tp_is_tracked($socialforumforce, $useron);
        $this->assertEquals(false, $result);

        // User on, preference off, social forum optional, should be on.
        $result = socialforum_tp_is_tracked($socialforumoptional, $useron);
        $this->assertEquals(false, $result);

        // User off, preference off, social forum force, should be off.
        $result = socialforum_tp_is_tracked($socialforumforce, $useroff);
        $this->assertEquals(false, $result);

        // User off, preference off, social forum optional, should be off.
        $result = socialforum_tp_is_tracked($socialforumoptional, $useroff);
        $this->assertEquals(false, $result);
    }

    /**
     * Test the logic in the socialforum_tp_get_course_unread_posts() function.
     */
    public function test_socialforum_tp_get_course_unread_posts() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackforums' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackforums' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => SOCIALFORUM_TRACKING_OFF); // Off.
        $socialforumoff = $this->getDataGenerator()->create_module('socialforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => SOCIALFORUM_TRACKING_FORCED); // On.
        $socialforumforce = $this->getDataGenerator()->create_module('socialforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => SOCIALFORUM_TRACKING_OPTIONAL); // Optional.
        $socialforumoptional = $this->getDataGenerator()->create_module('socialforum', $options);

        // Add discussions to the tracking off social forum.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useron->id;
        $record->socialforum = $socialforumoff->id;
        $discussionoff = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // Add discussions to the tracking forced social forum.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useron->id;
        $record->socialforum = $socialforumforce->id;
        $discussionforce = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // Add post to the tracking forced discussion.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useroff->id;
        $record->socialforum = $socialforumforce->id;
        $record->discussion = $discussionforce->id;
        $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_post($record);

        // Add discussions to the tracking optional social forum.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useron->id;
        $record->socialforum = $socialforumoptional->id;
        $discussionoptional = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // Allow force.
        $CFG->socialforum_allowforcedreadtracking = 1;

        $result = socialforum_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(false, isset($result[$socialforumoff->id]));
        $this->assertEquals(true, isset($result[$socialforumforce->id]));
        $this->assertEquals(2, $result[$socialforumforce->id]->unread);
        $this->assertEquals(true, isset($result[$socialforumoptional->id]));
        $this->assertEquals(1, $result[$socialforumoptional->id]->unread);

        $result = socialforum_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(false, isset($result[$socialforumoff->id]));
        $this->assertEquals(true, isset($result[$socialforumforce->id]));
        $this->assertEquals(2, $result[$socialforumforce->id]->unread);
        $this->assertEquals(false, isset($result[$socialforumoptional->id]));

        // Don't allow force.
        $CFG->socialforum_allowforcedreadtracking = 0;

        $result = socialforum_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(false, isset($result[$socialforumoff->id]));
        $this->assertEquals(true, isset($result[$socialforumforce->id]));
        $this->assertEquals(2, $result[$socialforumforce->id]->unread);
        $this->assertEquals(true, isset($result[$socialforumoptional->id]));
        $this->assertEquals(1, $result[$socialforumoptional->id]->unread);

        $result = socialforum_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(0, count($result));
        $this->assertEquals(false, isset($result[$socialforumoff->id]));
        $this->assertEquals(false, isset($result[$socialforumforce->id]));
        $this->assertEquals(false, isset($result[$socialforumoptional->id]));

        // Stop tracking so we can test again.
        socialforum_tp_stop_tracking($socialforumforce->id, $useron->id);
        socialforum_tp_stop_tracking($socialforumoptional->id, $useron->id);
        socialforum_tp_stop_tracking($socialforumforce->id, $useroff->id);
        socialforum_tp_stop_tracking($socialforumoptional->id, $useroff->id);

        // Allow force.
        $CFG->socialforum_allowforcedreadtracking = 1;

        $result = socialforum_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(false, isset($result[$socialforumoff->id]));
        $this->assertEquals(true, isset($result[$socialforumforce->id]));
        $this->assertEquals(2, $result[$socialforumforce->id]->unread);
        $this->assertEquals(false, isset($result[$socialforumoptional->id]));

        $result = socialforum_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(false, isset($result[$socialforumoff->id]));
        $this->assertEquals(true, isset($result[$socialforumforce->id]));
        $this->assertEquals(2, $result[$socialforumforce->id]->unread);
        $this->assertEquals(false, isset($result[$socialforumoptional->id]));

        // Don't allow force.
        $CFG->socialforum_allowforcedreadtracking = 0;

        $result = socialforum_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(0, count($result));
        $this->assertEquals(false, isset($result[$socialforumoff->id]));
        $this->assertEquals(false, isset($result[$socialforumforce->id]));
        $this->assertEquals(false, isset($result[$socialforumoptional->id]));

        $result = socialforum_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(0, count($result));
        $this->assertEquals(false, isset($result[$socialforumoff->id]));
        $this->assertEquals(false, isset($result[$socialforumforce->id]));
        $this->assertEquals(false, isset($result[$socialforumoptional->id]));
    }

    /**
     * Test the logic in the test_socialforum_tp_get_untracked_socialforums() function.
     */
    public function test_socialforum_tp_get_untracked_socialforums() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackforums' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackforums' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => SOCIALFORUM_TRACKING_OFF); // Off.
        $socialforumoff = $this->getDataGenerator()->create_module('socialforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => SOCIALFORUM_TRACKING_FORCED); // On.
        $socialforumforce = $this->getDataGenerator()->create_module('socialforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => SOCIALFORUM_TRACKING_OPTIONAL); // Optional.
        $socialforumoptional = $this->getDataGenerator()->create_module('socialforum', $options);

        // Allow force.
        $CFG->socialforum_allowforcedreadtracking = 1;

        // On user with force on.
        $result = socialforum_tp_get_untracked_socialforums($useron->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(true, isset($result[$socialforumoff->id]));

        // Off user with force on.
        $result = socialforum_tp_get_untracked_socialforums($useroff->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(true, isset($result[$socialforumoff->id]));
        $this->assertEquals(true, isset($result[$socialforumoptional->id]));

        // Don't allow force.
        $CFG->socialforum_allowforcedreadtracking = 0;

        // On user with force off.
        $result = socialforum_tp_get_untracked_socialforums($useron->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(true, isset($result[$socialforumoff->id]));

        // Off user with force off.
        $result = socialforum_tp_get_untracked_socialforums($useroff->id, $course->id);
        $this->assertEquals(3, count($result));
        $this->assertEquals(true, isset($result[$socialforumoff->id]));
        $this->assertEquals(true, isset($result[$socialforumoptional->id]));
        $this->assertEquals(true, isset($result[$socialforumforce->id]));

        // Stop tracking so we can test again.
        socialforum_tp_stop_tracking($socialforumforce->id, $useron->id);
        socialforum_tp_stop_tracking($socialforumoptional->id, $useron->id);
        socialforum_tp_stop_tracking($socialforumforce->id, $useroff->id);
        socialforum_tp_stop_tracking($socialforumoptional->id, $useroff->id);

        // Allow force.
        $CFG->socialforum_allowforcedreadtracking = 1;

        // On user with force on.
        $result = socialforum_tp_get_untracked_socialforums($useron->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(true, isset($result[$socialforumoff->id]));
        $this->assertEquals(true, isset($result[$socialforumoptional->id]));

        // Off user with force on.
        $result = socialforum_tp_get_untracked_socialforums($useroff->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(true, isset($result[$socialforumoff->id]));
        $this->assertEquals(true, isset($result[$socialforumoptional->id]));

        // Don't allow force.
        $CFG->socialforum_allowforcedreadtracking = 0;

        // On user with force off.
        $result = socialforum_tp_get_untracked_socialforums($useron->id, $course->id);
        $this->assertEquals(3, count($result));
        $this->assertEquals(true, isset($result[$socialforumoff->id]));
        $this->assertEquals(true, isset($result[$socialforumoptional->id]));
        $this->assertEquals(true, isset($result[$socialforumforce->id]));

        // Off user with force off.
        $result = socialforum_tp_get_untracked_socialforums($useroff->id, $course->id);
        $this->assertEquals(3, count($result));
        $this->assertEquals(true, isset($result[$socialforumoff->id]));
        $this->assertEquals(true, isset($result[$socialforumoptional->id]));
        $this->assertEquals(true, isset($result[$socialforumforce->id]));
    }

    /**
     * Test subscription using automatic subscription on create.
     */
    public function test_socialforum_auto_subscribe_on_create() {
        global $CFG;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = array();

        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_INITIALSUBSCRIBE); // Automatic Subscription.
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        $result = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum);
        $this->assertEquals($usercount, count($result));
        foreach ($users as $user) {
            $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($user->id, $socialforum));
        }
    }

    /**
     * Test subscription using forced subscription on create.
     */
    public function test_socialforum_forced_subscribe_on_create() {
        global $CFG;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = array();

        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_FORCESUBSCRIBE); // Forced subscription.
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        $result = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum);
        $this->assertEquals($usercount, count($result));
        foreach ($users as $user) {
            $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($user->id, $socialforum));
        }
    }

    /**
     * Test subscription using optional subscription on create.
     */
    public function test_socialforum_optional_subscribe_on_create() {
        global $CFG;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = array();

        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_CHOOSESUBSCRIBE); // Subscription optional.
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        $result = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum);
        // No subscriptions by default.
        $this->assertEquals(0, count($result));
        foreach ($users as $user) {
            $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($user->id, $socialforum));
        }
    }

    /**
     * Test subscription using disallow subscription on create.
     */
    public function test_socialforum_disallow_subscribe_on_create() {
        global $CFG;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = array();

        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_DISALLOWSUBSCRIBE); // Subscription prevented.
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        $result = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum);
        // No subscriptions by default.
        $this->assertEquals(0, count($result));
        foreach ($users as $user) {
            $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($user->id, $socialforum));
        }
    }

    /**
     * Test that context fetching returns the appropriate context.
     */
    public function test_socialforum_get_context() {
        global $DB, $PAGE;

        $this->resetAfterTest();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_CHOOSESUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);
        $socialforumcm = get_coursemodule_from_instance('socialforum', $socialforum->id);
        $socialforumcontext = \context_module::instance($socialforumcm->id);

        // First check that specifying the context results in the correct context being returned.
        // Do this before we set up the page object and we should return from the coursemodule record.
        // There should be no DB queries here because the context type was correct.
        $startcount = $DB->perf_get_reads();
        $result = socialforum_get_context($socialforum->id, $socialforumcontext);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($socialforumcontext, $result);
        $this->assertEquals(0, $aftercount - $startcount);

        // And a context which is not the correct type.
        // This tests will result in a DB query to fetch the course_module.
        $startcount = $DB->perf_get_reads();
        $result = socialforum_get_context($socialforum->id, $coursecontext);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($socialforumcontext, $result);
        $this->assertEquals(1, $aftercount - $startcount);

        // Now do not specify a context at all.
        // This tests will result in a DB query to fetch the course_module.
        $startcount = $DB->perf_get_reads();
        $result = socialforum_get_context($socialforum->id);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($socialforumcontext, $result);
        $this->assertEquals(1, $aftercount - $startcount);

        // Set up the default page event to use the social forum.
        $PAGE = new moodle_page();
        $PAGE->set_context($socialforumcontext);
        $PAGE->set_cm($socialforumcm, $course, $socialforum);

        // Now specify a context which is not a context_module.
        // There should be no DB queries here because we use the PAGE.
        $startcount = $DB->perf_get_reads();
        $result = socialforum_get_context($socialforum->id, $coursecontext);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($socialforumcontext, $result);
        $this->assertEquals(0, $aftercount - $startcount);

        // Now do not specify a context at all.
        // There should be no DB queries here because we use the PAGE.
        $startcount = $DB->perf_get_reads();
        $result = socialforum_get_context($socialforum->id);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($socialforumcontext, $result);
        $this->assertEquals(0, $aftercount - $startcount);

        // Now specify the page context of the course instead..
        $PAGE = new moodle_page();
        $PAGE->set_context($coursecontext);

        // Now specify a context which is not a context_module.
        // This tests will result in a DB query to fetch the course_module.
        $startcount = $DB->perf_get_reads();
        $result = socialforum_get_context($socialforum->id, $coursecontext);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($socialforumcontext, $result);
        $this->assertEquals(1, $aftercount - $startcount);

        // Now do not specify a context at all.
        // This tests will result in a DB query to fetch the course_module.
        $startcount = $DB->perf_get_reads();
        $result = socialforum_get_context($socialforum->id);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($socialforumcontext, $result);
        $this->assertEquals(1, $aftercount - $startcount);
    }

    /**
     * Test getting the neighbour threads of a discussion.
     */
    public function test_socialforum_get_neighbours() {
        global $CFG, $DB;
        $this->resetAfterTest();

        $timenow = time();
        $timenext = $timenow;

        // Setup test data.
        $socialforumgen = $this->getDataGenerator()->get_plugin_generator('mod_socialforum');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $socialforum = $this->getDataGenerator()->create_module('socialforum', array('course' => $course->id));
        $cm = get_coursemodule_from_instance('socialforum', $socialforum->id);
        $context = context_module::instance($cm->id);

        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->socialforum = $socialforum->id;
        $record->timemodified = time();
        $disc1 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $disc2 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $disc3 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $disc4 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $disc5 = $socialforumgen->create_discussion($record);

        // Getting the neighbours.
        $neighbours = socialforum_get_discussion_neighbours($cm, $disc1, $socialforum);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc2->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc2, $socialforum);
        $this->assertEquals($disc1->id, $neighbours['prev']->id);
        $this->assertEquals($disc3->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc3, $socialforum);
        $this->assertEquals($disc2->id, $neighbours['prev']->id);
        $this->assertEquals($disc4->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc4, $socialforum);
        $this->assertEquals($disc3->id, $neighbours['prev']->id);
        $this->assertEquals($disc5->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc5, $socialforum);
        $this->assertEquals($disc4->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Post in some discussions. We manually update the discussion record because
        // the data generator plays with timemodified in a way that would break this test.
        $record->timemodified++;
        $disc1->timemodified = $record->timemodified;
        $DB->update_record('socialforum_discussions', $disc1);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc5, $socialforum);
        $this->assertEquals($disc4->id, $neighbours['prev']->id);
        $this->assertEquals($disc1->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc2, $socialforum);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc3->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc1, $socialforum);
        $this->assertEquals($disc5->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // After some discussions were created.
        $record->timemodified++;
        $disc6 = $socialforumgen->create_discussion($record);
        $neighbours = socialforum_get_discussion_neighbours($cm, $disc6, $socialforum);
        $this->assertEquals($disc1->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        $record->timemodified++;
        $disc7 = $socialforumgen->create_discussion($record);
        $neighbours = socialforum_get_discussion_neighbours($cm, $disc7, $socialforum);
        $this->assertEquals($disc6->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Adding timed discussions.
        $CFG->socialforum_enabletimedposts = true;
        $now = $record->timemodified;
        $past = $now - 60;
        $future = $now + 60;

        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->socialforum = $socialforum->id;
        $record->timestart = $past;
        $record->timeend = $future;
        $record->timemodified = $now;
        $record->timemodified++;
        $disc8 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = $future;
        $record->timeend = 0;
        $disc9 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = 0;
        $record->timeend = 0;
        $disc10 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = 0;
        $record->timeend = $past;
        $disc11 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = $past;
        $record->timeend = $future;
        $disc12 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = $future + 1; // Should be last post for those that can see it.
        $record->timeend = 0;
        $disc13 = $socialforumgen->create_discussion($record);

        // Admin user ignores the timed settings of discussions.
        // Post ordering taking into account timestart:
        //  8 = t
        // 10 = t+3
        // 11 = t+4
        // 12 = t+5
        //  9 = t+60
        // 13 = t+61.
        $this->setAdminUser();
        $neighbours = socialforum_get_discussion_neighbours($cm, $disc8, $socialforum);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc9, $socialforum);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc13->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc10, $socialforum);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc11->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc11, $socialforum);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc12, $socialforum);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc9->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc13, $socialforum);
        $this->assertEquals($disc9->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user can see their own timed discussions.
        $this->setUser($user);
        $neighbours = socialforum_get_discussion_neighbours($cm, $disc8, $socialforum);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc9, $socialforum);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc13->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc10, $socialforum);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc11->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc11, $socialforum);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc12, $socialforum);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc9->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc13, $socialforum);
        $this->assertEquals($disc9->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user does not ignore timed settings.
        $this->setUser($user2);
        $neighbours = socialforum_get_discussion_neighbours($cm, $disc8, $socialforum);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc10, $socialforum);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc12, $socialforum);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Reset to normal mode.
        $CFG->socialforum_enabletimedposts = false;
        $this->setAdminUser();

        // Two discussions with identical timemodified will sort by id.
        $record->timemodified += 25;
        $DB->update_record('socialforum_discussions', (object) array('id' => $disc3->id, 'timemodified' => $record->timemodified));
        $DB->update_record('socialforum_discussions', (object) array('id' => $disc2->id, 'timemodified' => $record->timemodified));
        $DB->update_record('socialforum_discussions', (object) array('id' => $disc12->id, 'timemodified' => $record->timemodified - 5));
        $disc2 = $DB->get_record('socialforum_discussions', array('id' => $disc2->id));
        $disc3 = $DB->get_record('socialforum_discussions', array('id' => $disc3->id));

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc3, $socialforum);
        $this->assertEquals($disc2->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc2, $socialforum);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc3->id, $neighbours['next']->id);

        // Set timemodified to not be identical.
        $DB->update_record('socialforum_discussions', (object) array('id' => $disc2->id, 'timemodified' => $record->timemodified - 1));

        // Test pinned posts behave correctly.
        $disc8->pinned = SOCIALFORUM_DISCUSSION_PINNED;
        $DB->update_record('socialforum_discussions', (object) array('id' => $disc8->id, 'pinned' => $disc8->pinned));
        $neighbours = socialforum_get_discussion_neighbours($cm, $disc8, $socialforum);
        $this->assertEquals($disc3->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc3, $socialforum);
        $this->assertEquals($disc2->id, $neighbours['prev']->id);
        $this->assertEquals($disc8->id, $neighbours['next']->id);

        // Test 3 pinned posts.
        $disc6->pinned = SOCIALFORUM_DISCUSSION_PINNED;
        $DB->update_record('socialforum_discussions', (object) array('id' => $disc6->id, 'pinned' => $disc6->pinned));
        $disc4->pinned = SOCIALFORUM_DISCUSSION_PINNED;
        $DB->update_record('socialforum_discussions', (object) array('id' => $disc4->id, 'pinned' => $disc4->pinned));

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc6, $socialforum);
        $this->assertEquals($disc4->id, $neighbours['prev']->id);
        $this->assertEquals($disc8->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc4, $socialforum);
        $this->assertEquals($disc3->id, $neighbours['prev']->id);
        $this->assertEquals($disc6->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc8, $socialforum);
        $this->assertEquals($disc6->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
    }

    /**
     * Test getting the neighbour threads of a blog-like social forum.
     */
    public function test_socialforum_get_neighbours_blog() {
        global $CFG, $DB;
        $this->resetAfterTest();

        $timenow = time();
        $timenext = $timenow;

        // Setup test data.
        $socialforumgen = $this->getDataGenerator()->get_plugin_generator('mod_socialforum');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $socialforum = $this->getDataGenerator()->create_module('socialforum', array('course' => $course->id, 'type' => 'blog'));
        $cm = get_coursemodule_from_instance('socialforum', $socialforum->id);
        $context = context_module::instance($cm->id);

        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->socialforum = $socialforum->id;
        $record->timemodified = time();
        $disc1 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $disc2 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $disc3 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $disc4 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $disc5 = $socialforumgen->create_discussion($record);

        // Getting the neighbours.
        $neighbours = socialforum_get_discussion_neighbours($cm, $disc1, $socialforum);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc2->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc2, $socialforum);
        $this->assertEquals($disc1->id, $neighbours['prev']->id);
        $this->assertEquals($disc3->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc3, $socialforum);
        $this->assertEquals($disc2->id, $neighbours['prev']->id);
        $this->assertEquals($disc4->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc4, $socialforum);
        $this->assertEquals($disc3->id, $neighbours['prev']->id);
        $this->assertEquals($disc5->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc5, $socialforum);
        $this->assertEquals($disc4->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Make sure that the thread's timemodified does not affect the order.
        $record->timemodified++;
        $disc1->timemodified = $record->timemodified;
        $DB->update_record('socialforum_discussions', $disc1);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc1, $socialforum);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc2->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc2, $socialforum);
        $this->assertEquals($disc1->id, $neighbours['prev']->id);
        $this->assertEquals($disc3->id, $neighbours['next']->id);

        // Add another blog post.
        $record->timemodified++;
        $disc6 = $socialforumgen->create_discussion($record);
        $neighbours = socialforum_get_discussion_neighbours($cm, $disc6, $socialforum);
        $this->assertEquals($disc5->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        $record->timemodified++;
        $disc7 = $socialforumgen->create_discussion($record);
        $neighbours = socialforum_get_discussion_neighbours($cm, $disc7, $socialforum);
        $this->assertEquals($disc6->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Adding timed discussions.
        $CFG->socialforum_enabletimedposts = true;
        $now = $record->timemodified;
        $past = $now - 60;
        $future = $now + 60;

        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->socialforum = $socialforum->id;
        $record->timestart = $past;
        $record->timeend = $future;
        $record->timemodified = $now;
        $record->timemodified++;
        $disc8 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = $future;
        $record->timeend = 0;
        $disc9 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = 0;
        $record->timeend = 0;
        $disc10 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = 0;
        $record->timeend = $past;
        $disc11 = $socialforumgen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = $past;
        $record->timeend = $future;
        $disc12 = $socialforumgen->create_discussion($record);

        // Admin user ignores the timed settings of discussions.
        $this->setAdminUser();
        $neighbours = socialforum_get_discussion_neighbours($cm, $disc8, $socialforum);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc9->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc9, $socialforum);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc10, $socialforum);
        $this->assertEquals($disc9->id, $neighbours['prev']->id);
        $this->assertEquals($disc11->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc11, $socialforum);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc12, $socialforum);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user can see their own timed discussions.
        $this->setUser($user);
        $neighbours = socialforum_get_discussion_neighbours($cm, $disc8, $socialforum);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc9->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc9, $socialforum);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc10, $socialforum);
        $this->assertEquals($disc9->id, $neighbours['prev']->id);
        $this->assertEquals($disc11->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc11, $socialforum);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc12, $socialforum);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user does not ignore timed settings.
        $this->setUser($user2);
        $neighbours = socialforum_get_discussion_neighbours($cm, $disc8, $socialforum);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc10, $socialforum);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc12, $socialforum);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Reset to normal mode.
        $CFG->socialforum_enabletimedposts = false;
        $this->setAdminUser();

        $record->timemodified++;
        // Two blog posts with identical creation time will sort by id.
        $DB->update_record('socialforum_posts', (object) array('id' => $disc2->firstpost, 'created' => $record->timemodified));
        $DB->update_record('socialforum_posts', (object) array('id' => $disc3->firstpost, 'created' => $record->timemodified));

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc2, $socialforum);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc3->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm, $disc3, $socialforum);
        $this->assertEquals($disc2->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
    }

    /**
     * Test getting the neighbour threads of a discussion.
     */
    public function test_socialforum_get_neighbours_with_groups() {
        $this->resetAfterTest();

        $timenow = time();
        $timenext = $timenow;

        // Setup test data.
        $socialforumgen = $this->getDataGenerator()->get_plugin_generator('mod_socialforum');
        $course = $this->getDataGenerator()->create_course();
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id);
        $this->getDataGenerator()->create_group_member(array('userid' => $user1->id, 'groupid' => $group1->id));

        $socialforum1 = $this->getDataGenerator()->create_module('socialforum', array('course' => $course->id, 'groupmode' => VISIBLEGROUPS));
        $socialforum2 = $this->getDataGenerator()->create_module('socialforum', array('course' => $course->id, 'groupmode' => SEPARATEGROUPS));
        $cm1 = get_coursemodule_from_instance('socialforum', $socialforum1->id);
        $cm2 = get_coursemodule_from_instance('socialforum', $socialforum2->id);
        $context1 = context_module::instance($cm1->id);
        $context2 = context_module::instance($cm2->id);

        // Creating discussions in both social forums.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user1->id;
        $record->socialforum = $socialforum1->id;
        $record->groupid = $group1->id;
        $record->timemodified = time();
        $disc11 = $socialforumgen->create_discussion($record);
        $record->socialforum = $socialforum2->id;
        $record->timemodified++;
        $disc21 = $socialforumgen->create_discussion($record);

        $record->timemodified++;
        $record->userid = $user2->id;
        $record->socialforum = $socialforum1->id;
        $record->groupid = $group2->id;
        $disc12 = $socialforumgen->create_discussion($record);
        $record->socialforum = $socialforum2->id;
        $disc22 = $socialforumgen->create_discussion($record);

        $record->timemodified++;
        $record->userid = $user1->id;
        $record->socialforum = $socialforum1->id;
        $record->groupid = null;
        $disc13 = $socialforumgen->create_discussion($record);
        $record->socialforum = $socialforum2->id;
        $disc23 = $socialforumgen->create_discussion($record);

        $record->timemodified++;
        $record->userid = $user2->id;
        $record->socialforum = $socialforum1->id;
        $record->groupid = $group2->id;
        $disc14 = $socialforumgen->create_discussion($record);
        $record->socialforum = $socialforum2->id;
        $disc24 = $socialforumgen->create_discussion($record);

        $record->timemodified++;
        $record->userid = $user1->id;
        $record->socialforum = $socialforum1->id;
        $record->groupid = $group1->id;
        $disc15 = $socialforumgen->create_discussion($record);
        $record->socialforum = $socialforum2->id;
        $disc25 = $socialforumgen->create_discussion($record);

        // Admin user can see all groups.
        $this->setAdminUser();
        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc11, $socialforum1);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc12->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc21, $socialforum2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc22->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc12, $socialforum1);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc22, $socialforum2);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc13, $socialforum1);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc14->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc23, $socialforum2);
        $this->assertEquals($disc22->id, $neighbours['prev']->id);
        $this->assertEquals($disc24->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc14, $socialforum1);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc24, $socialforum2);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc15, $socialforum1);
        $this->assertEquals($disc14->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc25, $socialforum2);
        $this->assertEquals($disc24->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Admin user is only viewing group 1.
        $_POST['group'] = $group1->id;
        $this->assertEquals($group1->id, groups_get_activity_group($cm1, true));
        $this->assertEquals($group1->id, groups_get_activity_group($cm2, true));

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc11, $socialforum1);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc21, $socialforum2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc13, $socialforum1);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc23, $socialforum2);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc15, $socialforum1);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc25, $socialforum2);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user viewing non-grouped posts (this is only possible in visible groups).
        $this->setUser($user1);
        $_POST['group'] = 0;
        $this->assertEquals(0, groups_get_activity_group($cm1, true));

        // They can see anything in visible groups.
        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc12, $socialforum1);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc13, $socialforum1);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc14->id, $neighbours['next']->id);

        // Normal user, orphan of groups, can only see non-grouped posts in separate groups.
        $this->setUser($user2);
        $_POST['group'] = 0;
        $this->assertEquals(0, groups_get_activity_group($cm2, true));

        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc23, $socialforum2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEmpty($neighbours['next']);

        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc22, $socialforum2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc24, $socialforum2);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Switching to viewing group 1.
        $this->setUser($user1);
        $_POST['group'] = $group1->id;
        $this->assertEquals($group1->id, groups_get_activity_group($cm1, true));
        $this->assertEquals($group1->id, groups_get_activity_group($cm2, true));

        // They can see non-grouped or same group.
        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc11, $socialforum1);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc21, $socialforum2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc13, $socialforum1);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc23, $socialforum2);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc15, $socialforum1);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc25, $socialforum2);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Querying the neighbours of a discussion passing the wrong CM.
        $this->setExpectedException('coding_exception');
        socialforum_get_discussion_neighbours($cm2, $disc11, $socialforum2);
    }

    /**
     * Test getting the neighbour threads of a blog-like social forum with groups involved.
     */
    public function test_socialforum_get_neighbours_with_groups_blog() {
        $this->resetAfterTest();

        $timenow = time();
        $timenext = $timenow;

        // Setup test data.
        $socialforumgen = $this->getDataGenerator()->get_plugin_generator('mod_socialforum');
        $course = $this->getDataGenerator()->create_course();
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id);
        $this->getDataGenerator()->create_group_member(array('userid' => $user1->id, 'groupid' => $group1->id));

        $socialforum1 = $this->getDataGenerator()->create_module('socialforum', array('course' => $course->id, 'type' => 'blog',
            'groupmode' => VISIBLEGROUPS));
        $socialforum2 = $this->getDataGenerator()->create_module('socialforum', array('course' => $course->id, 'type' => 'blog',
            'groupmode' => SEPARATEGROUPS));
        $cm1 = get_coursemodule_from_instance('socialforum', $socialforum1->id);
        $cm2 = get_coursemodule_from_instance('socialforum', $socialforum2->id);
        $context1 = context_module::instance($cm1->id);
        $context2 = context_module::instance($cm2->id);

        // Creating blog posts in both social forums.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user1->id;
        $record->socialforum = $socialforum1->id;
        $record->groupid = $group1->id;
        $record->timemodified = time();
        $disc11 = $socialforumgen->create_discussion($record);
        $record->timenow = $timenext++;
        $record->socialforum = $socialforum2->id;
        $record->timemodified++;
        $disc21 = $socialforumgen->create_discussion($record);

        $record->timemodified++;
        $record->userid = $user2->id;
        $record->socialforum = $socialforum1->id;
        $record->groupid = $group2->id;
        $disc12 = $socialforumgen->create_discussion($record);
        $record->socialforum = $socialforum2->id;
        $disc22 = $socialforumgen->create_discussion($record);

        $record->timemodified++;
        $record->userid = $user1->id;
        $record->socialforum = $socialforum1->id;
        $record->groupid = null;
        $disc13 = $socialforumgen->create_discussion($record);
        $record->socialforum = $socialforum2->id;
        $disc23 = $socialforumgen->create_discussion($record);

        $record->timemodified++;
        $record->userid = $user2->id;
        $record->socialforum = $socialforum1->id;
        $record->groupid = $group2->id;
        $disc14 = $socialforumgen->create_discussion($record);
        $record->socialforum = $socialforum2->id;
        $disc24 = $socialforumgen->create_discussion($record);

        $record->timemodified++;
        $record->userid = $user1->id;
        $record->socialforum = $socialforum1->id;
        $record->groupid = $group1->id;
        $disc15 = $socialforumgen->create_discussion($record);
        $record->socialforum = $socialforum2->id;
        $disc25 = $socialforumgen->create_discussion($record);

        // Admin user can see all groups.
        $this->setAdminUser();
        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc11, $socialforum1);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc12->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc21, $socialforum2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc22->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc12, $socialforum1);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc22, $socialforum2);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc13, $socialforum1);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc14->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc23, $socialforum2);
        $this->assertEquals($disc22->id, $neighbours['prev']->id);
        $this->assertEquals($disc24->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc14, $socialforum1);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc24, $socialforum2);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc15, $socialforum1);
        $this->assertEquals($disc14->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc25, $socialforum2);
        $this->assertEquals($disc24->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Admin user is only viewing group 1.
        $_POST['group'] = $group1->id;
        $this->assertEquals($group1->id, groups_get_activity_group($cm1, true));
        $this->assertEquals($group1->id, groups_get_activity_group($cm2, true));

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc11, $socialforum1);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc21, $socialforum2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc13, $socialforum1);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc23, $socialforum2);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc15, $socialforum1);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc25, $socialforum2);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user viewing non-grouped posts (this is only possible in visible groups).
        $this->setUser($user1);
        $_POST['group'] = 0;
        $this->assertEquals(0, groups_get_activity_group($cm1, true));

        // They can see anything in visible groups.
        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc12, $socialforum1);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc13, $socialforum1);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc14->id, $neighbours['next']->id);

        // Normal user, orphan of groups, can only see non-grouped posts in separate groups.
        $this->setUser($user2);
        $_POST['group'] = 0;
        $this->assertEquals(0, groups_get_activity_group($cm2, true));

        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc23, $socialforum2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEmpty($neighbours['next']);

        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc22, $socialforum2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc24, $socialforum2);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Switching to viewing group 1.
        $this->setUser($user1);
        $_POST['group'] = $group1->id;
        $this->assertEquals($group1->id, groups_get_activity_group($cm1, true));
        $this->assertEquals($group1->id, groups_get_activity_group($cm2, true));

        // They can see non-grouped or same group.
        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc11, $socialforum1);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc21, $socialforum2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc13, $socialforum1);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc23, $socialforum2);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = socialforum_get_discussion_neighbours($cm1, $disc15, $socialforum1);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = socialforum_get_discussion_neighbours($cm2, $disc25, $socialforum2);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Querying the neighbours of a discussion passing the wrong CM.
        $this->setExpectedException('coding_exception');
        socialforum_get_discussion_neighbours($cm2, $disc11, $socialforum2);
    }

    public function test_count_discussion_replies_basic() {
        list($socialforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);

        // Count the discussion replies in the social forum.
        $result = socialforum_count_discussion_replies($socialforum->id);
        $this->assertCount(10, $result);
    }

    public function test_count_discussion_replies_limited() {
        list($socialforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Adding limits shouldn't make a difference.
        $result = socialforum_count_discussion_replies($socialforum->id, "", 20);
        $this->assertCount(10, $result);
    }

    public function test_count_discussion_replies_paginated() {
        list($socialforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Adding paging shouldn't make any difference.
        $result = socialforum_count_discussion_replies($socialforum->id, "", -1, 0, 100);
        $this->assertCount(10, $result);
    }

    public function test_count_discussion_replies_paginated_sorted() {
        list($socialforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Specifying the socialforumsort should also give a good result. This follows a different path.
        $result = socialforum_count_discussion_replies($socialforum->id, "d.id asc", -1, 0, 100);
        $this->assertCount(10, $result);
        foreach ($result as $row) {
            // Grab the first discussionid.
            $discussionid = array_shift($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_count_discussion_replies_limited_sorted() {
        list($socialforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Adding limits, and a socialforumsort shouldn't make a difference.
        $result = socialforum_count_discussion_replies($socialforum->id, "d.id asc", 20);
        $this->assertCount(10, $result);
        foreach ($result as $row) {
            // Grab the first discussionid.
            $discussionid = array_shift($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_count_discussion_replies_paginated_sorted_small() {
        list($socialforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Grabbing a smaller subset and they should be ordered as expected.
        $result = socialforum_count_discussion_replies($socialforum->id, "d.id asc", -1, 0, 5);
        $this->assertCount(5, $result);
        foreach ($result as $row) {
            // Grab the first discussionid.
            $discussionid = array_shift($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_count_discussion_replies_paginated_sorted_small_reverse() {
        list($socialforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Grabbing a smaller subset and they should be ordered as expected.
        $result = socialforum_count_discussion_replies($socialforum->id, "d.id desc", -1, 0, 5);
        $this->assertCount(5, $result);
        foreach ($result as $row) {
            // Grab the last discussionid.
            $discussionid = array_pop($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_count_discussion_replies_limited_sorted_small_reverse() {
        list($socialforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Adding limits, and a socialforumsort shouldn't make a difference.
        $result = socialforum_count_discussion_replies($socialforum->id, "d.id desc", 5);
        $this->assertCount(5, $result);
        foreach ($result as $row) {
            // Grab the last discussionid.
            $discussionid = array_pop($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_discussion_pinned_sort() {
        list($socialforum, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        $cm = get_coursemodule_from_instance('socialforum', $socialforum->id);
        $discussions = socialforum_get_discussions($cm);
        // First discussion should be pinned.
        $first = reset($discussions);
        $this->assertEquals(1, $first->pinned, "First discussion should be pinned discussion");
    }

    public function test_socialforum_view() {
        global $CFG;

        $CFG->enablecompletion = 1;
        $this->resetAfterTest();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $socialforum = $this->getDataGenerator()->create_module('socialforum', array('course' => $course->id), array('completion' => 2, 'completionview' => 1));
        $context = context_module::instance($socialforum->cmid);
        $cm = get_coursemodule_from_instance('socialforum', $socialforum->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $this->setAdminUser();
        socialforum_view($socialforum, $course, $cm, $context);

        $events = $sink->get_events();
        // 2 additional events thanks to completion.
        $this->assertCount(3, $events);
        $event = array_pop($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_socialforum\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/socialforum/view.php', array('f' => $socialforum->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Check completion status.
        $completion = new completion_info($course);
        $completiondata = $completion->get_data($cm);
        $this->assertEquals(1, $completiondata->completionstate);
    }

    /**
     * Test socialforum_discussion_view.
     */
    public function test_socialforum_discussion_view() {
        global $CFG, $USER;

        $this->resetAfterTest();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $socialforum = $this->getDataGenerator()->create_module('socialforum', array('course' => $course->id));
        $discussion = $this->create_single_discussion_with_replies($socialforum, $USER, 2);

        $context = context_module::instance($socialforum->cmid);
        $cm = get_coursemodule_from_instance('socialforum', $socialforum->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $this->setAdminUser();
        socialforum_discussion_view($context, $socialforum, $discussion);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_pop($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_socialforum\event\discussion_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'socialforum', 'view discussion', "discuss.php?d={$discussion->id}",
            $discussion->id, $socialforum->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Create a new course, social forum, and user with a number of discussions and replies.
     *
     * @param int $discussioncount The number of discussions to create
     * @param int $replycount The number of replies to create in each discussion
     * @return array Containing the created social forum object, and the ids of the created discussions.
     */
    protected function create_multiple_discussions_with_replies($discussioncount, $replycount) {
        $this->resetAfterTest();

        // Setup the content.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $record = new stdClass();
        $record->course = $course->id;
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $record);

        // Create 10 discussions with replies.
        $discussionids = array();
        for ($i = 0; $i < $discussioncount; $i++) {
            // Pin 3rd discussion.
            if ($i == 3) {
                $discussion = $this->create_single_discussion_pinned_with_replies($socialforum, $user, $replycount);
            } else {
                $discussion = $this->create_single_discussion_with_replies($socialforum, $user, $replycount);
            }

            $discussionids[] = $discussion->id;
        }
        return array($socialforum, $discussionids);
    }

    /**
     * Create a discussion with a number of replies.
     *
     * @param object $socialforum The social forum which has been created
     * @param object $user The user making the discussion and replies
     * @param int $replycount The number of replies
     * @return object $discussion
     */
    protected function create_single_discussion_with_replies($socialforum, $user, $replycount) {
        global $DB;

        $generator = self::getDataGenerator()->get_plugin_generator('mod_socialforum');

        $record = new stdClass();
        $record->course = $socialforum->course;
        $record->socialforum = $socialforum->id;
        $record->userid = $user->id;
        $discussion = $generator->create_discussion($record);

        // Retrieve the first post.
        $replyto = $DB->get_record('socialforum_posts', array('discussion' => $discussion->id));

        // Create the replies.
        $post = new stdClass();
        $post->userid = $user->id;
        $post->discussion = $discussion->id;
        $post->parent = $replyto->id;

        for ($i = 0; $i < $replycount; $i++) {
            $generator->create_post($post);
        }

        return $discussion;
    }

    /**
     * Create a discussion with a number of replies.
     *
     * @param object $socialforum The social forum which has been created
     * @param object $user The user making the discussion and replies
     * @param int $replycount The number of replies
     * @return object $discussion
     */
    protected function create_single_discussion_pinned_with_replies($socialforum, $user, $replycount) {
        global $DB;

        $generator = self::getDataGenerator()->get_plugin_generator('mod_socialforum');

        $record = new stdClass();
        $record->course = $socialforum->course;
        $record->socialforum = $socialforum->id;
        $record->userid = $user->id;
        $record->pinned = SOCIALFORUM_DISCUSSION_PINNED;
        $discussion = $generator->create_discussion($record);

        // Retrieve the first post.
        $replyto = $DB->get_record('socialforum_posts', array('discussion' => $discussion->id));

        // Create the replies.
        $post = new stdClass();
        $post->userid = $user->id;
        $post->discussion = $discussion->id;
        $post->parent = $replyto->id;

        for ($i = 0; $i < $replycount; $i++) {
            $generator->create_post($post);
        }

        return $discussion;
    }

    /**
     * Tests for mod_socialforum_rating_can_see_item_ratings().
     *
     * @throws coding_exception
     * @throws rating_exception
     */
    public function test_mod_socialforum_rating_can_see_item_ratings() {
        global $DB;

        $this->resetAfterTest();

        // Setup test data.
        $course = new stdClass();
        $course->groupmode = SEPARATEGROUPS;
        $course->groupmodeforce = true;
        $course = $this->getDataGenerator()->create_course($course);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', array('course' => $course->id));
        $generator = self::getDataGenerator()->get_plugin_generator('mod_socialforum');
        $cm = get_coursemodule_from_instance('socialforum', $socialforum->id);
        $context = context_module::instance($cm->id);

        // Create users.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        // Groups and stuff.
        $role = $DB->get_record('role', array('shortname' => 'teacher'), '*', MUST_EXIST);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, $role->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, $role->id);
        $this->getDataGenerator()->enrol_user($user3->id, $course->id, $role->id);
        $this->getDataGenerator()->enrol_user($user4->id, $course->id, $role->id);

        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        groups_add_member($group1, $user1);
        groups_add_member($group1, $user2);
        groups_add_member($group2, $user3);
        groups_add_member($group2, $user4);

        $record = new stdClass();
        $record->course = $socialforum->course;
        $record->socialforum = $socialforum->id;
        $record->userid = $user1->id;
        $record->groupid = $group1->id;
        $discussion = $generator->create_discussion($record);

        // Retrieve the first post.
        $post = $DB->get_record('socialforum_posts', array('discussion' => $discussion->id));

        $ratingoptions = new stdClass;
        $ratingoptions->context = $context;
        $ratingoptions->ratingarea = 'post';
        $ratingoptions->component = 'mod_socialforum';
        $ratingoptions->itemid = $post->id;
        $ratingoptions->scaleid = 2;
        $ratingoptions->userid = $user2->id;
        $rating = new rating($ratingoptions);
        $rating->update_rating(2);

        // Now try to access it as various users.
        unassign_capability('moodle/site:accessallgroups', $role->id);
        $params = array('contextid' => 2,
            'component' => 'mod_socialforum',
            'ratingarea' => 'post',
            'itemid' => $post->id,
            'scaleid' => 2);
        $this->setUser($user1);
        $this->assertTrue(mod_socialforum_rating_can_see_item_ratings($params));
        $this->setUser($user2);
        $this->assertTrue(mod_socialforum_rating_can_see_item_ratings($params));
        $this->setUser($user3);
        $this->assertFalse(mod_socialforum_rating_can_see_item_ratings($params));
        $this->setUser($user4);
        $this->assertFalse(mod_socialforum_rating_can_see_item_ratings($params));

        // Now try with accessallgroups cap and make sure everything is visible.
        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $role->id, $context->id);
        $this->setUser($user1);
        $this->assertTrue(mod_socialforum_rating_can_see_item_ratings($params));
        $this->setUser($user2);
        $this->assertTrue(mod_socialforum_rating_can_see_item_ratings($params));
        $this->setUser($user3);
        $this->assertTrue(mod_socialforum_rating_can_see_item_ratings($params));
        $this->setUser($user4);
        $this->assertTrue(mod_socialforum_rating_can_see_item_ratings($params));

        // Change group mode and verify visibility.
        $course->groupmode = VISIBLEGROUPS;
        $DB->update_record('course', $course);
        unassign_capability('moodle/site:accessallgroups', $role->id);
        $this->setUser($user1);
        $this->assertTrue(mod_socialforum_rating_can_see_item_ratings($params));
        $this->setUser($user2);
        $this->assertTrue(mod_socialforum_rating_can_see_item_ratings($params));
        $this->setUser($user3);
        $this->assertTrue(mod_socialforum_rating_can_see_item_ratings($params));
        $this->setUser($user4);
        $this->assertTrue(mod_socialforum_rating_can_see_item_ratings($params));
    }

    /**
     * Test socialforum_get_discussions
     */
    public function test_socialforum_get_discussions_with_groups() {
        global $DB;

        $this->resetAfterTest(true);

        // Create course to add the module.
        $course = self::getDataGenerator()->create_course(array('groupmode' => VISIBLEGROUPS, 'groupmodeforce' => 0));
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        $role = $DB->get_record('role', array('shortname' => 'student'), '*', MUST_EXIST);
        self::getDataGenerator()->enrol_user($user1->id, $course->id, $role->id);
        self::getDataGenerator()->enrol_user($user2->id, $course->id, $role->id);
        self::getDataGenerator()->enrol_user($user3->id, $course->id, $role->id);

        // Social Forum forcing separate gropus.
        $record = new stdClass();
        $record->course = $course->id;
        $socialforum = self::getDataGenerator()->create_module('socialforum', $record, array('groupmode' => SEPARATEGROUPS));
        $cm = get_coursemodule_from_instance('socialforum', $socialforum->id);

        // Create groups.
        $group1 = self::getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = self::getDataGenerator()->create_group(array('courseid' => $course->id));
        $group3 = self::getDataGenerator()->create_group(array('courseid' => $course->id));

        // Add the user1 to g1 and g2 groups.
        groups_add_member($group1->id, $user1->id);
        groups_add_member($group2->id, $user1->id);

        // Add the user 2 and 3 to only one group.
        groups_add_member($group1->id, $user2->id);
        groups_add_member($group3->id, $user3->id);

        // Add a few discussions.
        $record = array();
        $record['course'] = $course->id;
        $record['socialforum'] = $socialforum->id;
        $record['userid'] = $user1->id;
        $record['groupid'] = $group1->id;
        $discussiong1u1 = self::getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        $record['groupid'] = $group2->id;
        $discussiong2u1 = self::getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        $record['userid'] = $user2->id;
        $record['groupid'] = $group1->id;
        $discussiong1u2 = self::getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        $record['userid'] = $user3->id;
        $record['groupid'] = $group3->id;
        $discussiong3u3 = self::getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        self::setUser($user1);
        // Test retrieve discussions not passing the groupid parameter. We will receive only first group discussions.
        $discussions = socialforum_get_discussions($cm);
        self::assertCount(2, $discussions);
        foreach ($discussions as $discussion) {
            self::assertEquals($group1->id, $discussion->groupid);
        }

        // Get all my discussions.
        $discussions = socialforum_get_discussions($cm, '', true, -1, -1, false, -1, 0, 0);
        self::assertCount(3, $discussions);

        // Get all my g1 discussions.
        $discussions = socialforum_get_discussions($cm, '', true, -1, -1, false, -1, 0, $group1->id);
        self::assertCount(2, $discussions);
        foreach ($discussions as $discussion) {
            self::assertEquals($group1->id, $discussion->groupid);
        }

        // Get all my g2 discussions.
        $discussions = socialforum_get_discussions($cm, '', true, -1, -1, false, -1, 0, $group2->id);
        self::assertCount(1, $discussions);
        $discussion = array_shift($discussions);
        self::assertEquals($group2->id, $discussion->groupid);
        self::assertEquals($user1->id, $discussion->userid);
        self::assertEquals($discussiong2u1->id, $discussion->discussion);

        // Get all my g3 discussions (I'm not enrolled in that group).
        $discussions = socialforum_get_discussions($cm, '', true, -1, -1, false, -1, 0, $group3->id);
        self::assertCount(0, $discussions);

        // This group does not exist.
        $discussions = socialforum_get_discussions($cm, '', true, -1, -1, false, -1, 0, $group3->id + 1000);
        self::assertCount(0, $discussions);

        self::setUser($user2);

        // Test retrieve discussions not passing the groupid parameter. We will receive only first group discussions.
        $discussions = socialforum_get_discussions($cm);
        self::assertCount(2, $discussions);
        foreach ($discussions as $discussion) {
            self::assertEquals($group1->id, $discussion->groupid);
        }

        // Get all my viewable discussions.
        $discussions = socialforum_get_discussions($cm, '', true, -1, -1, false, -1, 0, 0);
        self::assertCount(2, $discussions);
        foreach ($discussions as $discussion) {
            self::assertEquals($group1->id, $discussion->groupid);
        }

        // Get all my g2 discussions (I'm not enrolled in that group).
        $discussions = socialforum_get_discussions($cm, '', true, -1, -1, false, -1, 0, $group2->id);
        self::assertCount(0, $discussions);

        // Get all my g3 discussions (I'm not enrolled in that group).
        $discussions = socialforum_get_discussions($cm, '', true, -1, -1, false, -1, 0, $group3->id);
        self::assertCount(0, $discussions);
    }

    /**
     * Test socialforum_user_can_post_discussion
     */
    public function test_socialforum_user_can_post_discussion() {
        global $CFG, $DB;

        $this->resetAfterTest(true);

        // Create course to add the module.
        $course = self::getDataGenerator()->create_course(array('groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1));
        $user = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Social Forum forcing separate gropus.
        $record = new stdClass();
        $record->course = $course->id;
        $socialforum = self::getDataGenerator()->create_module('socialforum', $record, array('groupmode' => SEPARATEGROUPS));
        $cm = get_coursemodule_from_instance('socialforum', $socialforum->id);
        $context = context_module::instance($cm->id);

        self::setUser($user);

        // The user is not enroled in any group, try to post in a social forum with separate groups.
        $can = socialforum_user_can_post_discussion($socialforum, null, -1, $cm, $context);
        $this->assertFalse($can);

        // Create a group.
        $group = $this->getDataGenerator()->create_group(array('courseid' => $course->id));

        // Try to post in a group the user is not enrolled.
        $can = socialforum_user_can_post_discussion($socialforum, $group->id, -1, $cm, $context);
        $this->assertFalse($can);

        // Add the user to a group.
        groups_add_member($group->id, $user->id);

        // Try to post in a group the user is not enrolled.
        $can = socialforum_user_can_post_discussion($socialforum, $group->id + 1, -1, $cm, $context);
        $this->assertFalse($can);

        // Now try to post in the user group. (null means it will guess the group).
        $can = socialforum_user_can_post_discussion($socialforum, null, -1, $cm, $context);
        $this->assertTrue($can);

        $can = socialforum_user_can_post_discussion($socialforum, $group->id, -1, $cm, $context);
        $this->assertTrue($can);

        // Test all groups.
        $can = socialforum_user_can_post_discussion($socialforum, -1, -1, $cm, $context);
        $this->assertFalse($can);

        $this->setAdminUser();
        $can = socialforum_user_can_post_discussion($socialforum, -1, -1, $cm, $context);
        $this->assertTrue($can);

        // Change social forum type.
        $socialforum->type = 'news';
        $DB->update_record('socialforum', $socialforum);

        // Admin can post news.
        $can = socialforum_user_can_post_discussion($socialforum, null, -1, $cm, $context);
        $this->assertTrue($can);

        // Normal users don't.
        self::setUser($user);
        $can = socialforum_user_can_post_discussion($socialforum, null, -1, $cm, $context);
        $this->assertFalse($can);

        // Change social forum type.
        $socialforum->type = 'eachuser';
        $DB->update_record('socialforum', $socialforum);

        // I didn't post yet, so I should be able to post.
        $can = socialforum_user_can_post_discussion($socialforum, null, -1, $cm, $context);
        $this->assertTrue($can);

        // Post now.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->socialforum = $socialforum->id;
        $record->groupid = $group->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // I already posted, I shouldn't be able to post.
        $can = socialforum_user_can_post_discussion($socialforum, null, -1, $cm, $context);
        $this->assertFalse($can);

        // Last check with no groups, normal social forum and course.
        $course->groupmode = NOGROUPS;
        $course->groupmodeforce = 0;
        $DB->update_record('course', $course);

        $socialforum->type = 'general';
        $socialforum->groupmode = NOGROUPS;
        $DB->update_record('socialforum', $socialforum);

        $can = socialforum_user_can_post_discussion($socialforum, null, -1, $cm, $context);
        $this->assertTrue($can);
    }

    /**
     * Test socialforum_user_has_posted_discussion with no groups.
     */
    public function test_socialforum_user_has_posted_discussion_no_groups() {
        global $CFG;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $author = self::getDataGenerator()->create_user();
        $other = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course->id);
        $socialforum = self::getDataGenerator()->create_module('socialforum', (object) ['course' => $course->id]);

        self::setUser($author);

        // Neither user has posted.
        $this->assertFalse(socialforum_user_has_posted_discussion($socialforum->id, $author->id));
        $this->assertFalse(socialforum_user_has_posted_discussion($socialforum->id, $other->id));

        // Post in the social forum.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $author->id;
        $record->socialforum = $socialforum->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // The author has now posted, but the other user has not.
        $this->assertTrue(socialforum_user_has_posted_discussion($socialforum->id, $author->id));
        $this->assertFalse(socialforum_user_has_posted_discussion($socialforum->id, $other->id));
    }

    /**
     * Test socialforum_user_has_posted_discussion with multiple social forums
     */
    public function test_socialforum_user_has_posted_discussion_multiple_socialforums() {
        global $CFG;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $author = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course->id);
        $socialforum1 = self::getDataGenerator()->create_module('socialforum', (object) ['course' => $course->id]);
        $socialforum2 = self::getDataGenerator()->create_module('socialforum', (object) ['course' => $course->id]);

        self::setUser($author);

        // No post in either social forum.
        $this->assertFalse(socialforum_user_has_posted_discussion($socialforum1->id, $author->id));
        $this->assertFalse(socialforum_user_has_posted_discussion($socialforum2->id, $author->id));

        // Post in the social forum.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $author->id;
        $record->socialforum = $socialforum1->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // The author has now posted in forum1, but not forum2.
        $this->assertTrue(socialforum_user_has_posted_discussion($socialforum1->id, $author->id));
        $this->assertFalse(socialforum_user_has_posted_discussion($socialforum2->id, $author->id));
    }

    /**
     * Test socialforum_user_has_posted_discussion with multiple groups.
     */
    public function test_socialforum_user_has_posted_discussion_multiple_groups() {
        global $CFG;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $author = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course->id);

        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        groups_add_member($group1->id, $author->id);
        groups_add_member($group2->id, $author->id);

        $socialforum = self::getDataGenerator()->create_module('socialforum', (object) ['course' => $course->id], [
            'groupmode' => SEPARATEGROUPS,
        ]);

        self::setUser($author);

        // The user has not posted in either group.
        $this->assertFalse(socialforum_user_has_posted_discussion($socialforum->id, $author->id));
        $this->assertFalse(socialforum_user_has_posted_discussion($socialforum->id, $author->id, $group1->id));
        $this->assertFalse(socialforum_user_has_posted_discussion($socialforum->id, $author->id, $group2->id));

        // Post in one group.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $author->id;
        $record->socialforum = $socialforum->id;
        $record->groupid = $group1->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // The author has now posted in one group, but the other user has not.
        $this->assertTrue(socialforum_user_has_posted_discussion($socialforum->id, $author->id));
        $this->assertTrue(socialforum_user_has_posted_discussion($socialforum->id, $author->id, $group1->id));
        $this->assertFalse(socialforum_user_has_posted_discussion($socialforum->id, $author->id, $group2->id));

        // Post in the other group.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $author->id;
        $record->socialforum = $socialforum->id;
        $record->groupid = $group2->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // The author has now posted in one group, but the other user has not.
        $this->assertTrue(socialforum_user_has_posted_discussion($socialforum->id, $author->id));
        $this->assertTrue(socialforum_user_has_posted_discussion($socialforum->id, $author->id, $group1->id));
        $this->assertTrue(socialforum_user_has_posted_discussion($socialforum->id, $author->id, $group2->id));
    }

    /**
     * Tests the mod_socialforum_myprofile_navigation() function.
     */
    public function test_mod_socialforum_myprofile_navigation() {
        $this->resetAfterTest(true);

        // Set up the test.
        $tree = new \core_user\output\myprofile\tree();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $iscurrentuser = true;

        // Set as the current user.
        $this->setUser($user);

        // Check the node tree is correct.
        mod_socialforum_myprofile_navigation($tree, $user, $iscurrentuser, $course);
        $reflector = new ReflectionObject($tree);
        $nodes = $reflector->getProperty('nodes');
        $nodes->setAccessible(true);
        $this->assertArrayHasKey('socialforumposts', $nodes->getValue($tree));
        $this->assertArrayHasKey('socialforumdiscussions', $nodes->getValue($tree));
    }

    /**
     * Tests the mod_socialforum_myprofile_navigation() function as a guest.
     */
    public function test_mod_socialforum_myprofile_navigation_as_guest() {
        global $USER;

        $this->resetAfterTest(true);

        // Set up the test.
        $tree = new \core_user\output\myprofile\tree();
        $course = $this->getDataGenerator()->create_course();
        $iscurrentuser = true;

        // Set user as guest.
        $this->setGuestUser();

        // Check the node tree is correct.
        mod_socialforum_myprofile_navigation($tree, $USER, $iscurrentuser, $course);
        $reflector = new ReflectionObject($tree);
        $nodes = $reflector->getProperty('nodes');
        $nodes->setAccessible(true);
        $this->assertArrayNotHasKey('socialforumposts', $nodes->getValue($tree));
        $this->assertArrayNotHasKey('socialforumdiscussions', $nodes->getValue($tree));
    }

    /**
     * Tests the mod_socialforum_myprofile_navigation() function as a user viewing another user's profile.
     */
    public function test_mod_socialforum_myprofile_navigation_different_user() {
        $this->resetAfterTest(true);

        // Set up the test.
        $tree = new \core_user\output\myprofile\tree();
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $iscurrentuser = true;

        // Set to different user's profile.
        $this->setUser($user2);

        // Check the node tree is correct.
        mod_socialforum_myprofile_navigation($tree, $user, $iscurrentuser, $course);
        $reflector = new ReflectionObject($tree);
        $nodes = $reflector->getProperty('nodes');
        $nodes->setAccessible(true);
        $this->assertArrayHasKey('socialforumposts', $nodes->getValue($tree));
        $this->assertArrayHasKey('socialforumdiscussions', $nodes->getValue($tree));
    }

    public function test_print_overview() {
        $this->resetAfterTest();
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        // Create an author user.
        $author = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course1->id);
        $this->getDataGenerator()->enrol_user($author->id, $course2->id);

        // Create a viewer user.
        $viewer = self::getDataGenerator()->create_user((object) array('trackforums' => 1));
        $this->getDataGenerator()->enrol_user($viewer->id, $course1->id);
        $this->getDataGenerator()->enrol_user($viewer->id, $course2->id);

        // Create two forums - one in each course.
        $record = new stdClass();
        $record->course = $course1->id;
        $socialforum1 = self::getDataGenerator()->create_module('socialforum', (object) array('course' => $course1->id));
        $socialforum2 = self::getDataGenerator()->create_module('socialforum', (object) array('course' => $course2->id));

        // A standard post in the forum.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $author->id;
        $record->socialforum = $socialforum1->id;
        $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        $this->setUser($viewer->id);
        $courses = array(
            $course1->id => clone $course1,
            $course2->id => clone $course2,
        );

        foreach ($courses as $courseid => $course) {
            $courses[$courseid]->lastaccess = 0;
        }
        $results = array();
        socialforum_print_overview($courses, $results);

        // There should be one entry for course1, and no others.
        $this->assertCount(1, $results);

        // There should be one entry for a forum in course1.
        $this->assertCount(1, $results[$course1->id]);
        $this->assertArrayHasKey('socialforum', $results[$course1->id]);
    }

    public function test_print_overview_groups() {
        $this->resetAfterTest();
        $course1 = self::getDataGenerator()->create_course();
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));

        // Create an author user.
        $author = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course1->id);

        // Create two viewer users - one in each group.
        $viewer1 = self::getDataGenerator()->create_user((object) array('trackforums' => 1));
        $this->getDataGenerator()->enrol_user($viewer1->id, $course1->id);
        $this->getDataGenerator()->create_group_member(array('userid' => $viewer1->id, 'groupid' => $group1->id));

        $viewer2 = self::getDataGenerator()->create_user((object) array('trackforums' => 1));
        $this->getDataGenerator()->enrol_user($viewer2->id, $course1->id);
        $this->getDataGenerator()->create_group_member(array('userid' => $viewer2->id, 'groupid' => $group2->id));

        // Create a forum.
        $record = new stdClass();
        $record->course = $course1->id;
        $socialforum1 = self::getDataGenerator()->create_module('socialforum', (object) array(
                    'course' => $course1->id,
                    'groupmode' => SEPARATEGROUPS,
        ));

        // A post in the forum for group1.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $author->id;
        $record->socialforum = $socialforum1->id;
        $record->groupid = $group1->id;
        $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        $course1->lastaccess = 0;
        $courses = array($course1->id => $course1);

        // As viewer1 (same group as post).
        $this->setUser($viewer1->id);
        $results = array();
        socialforum_print_overview($courses, $results);

        // There should be one entry for course1.
        $this->assertCount(1, $results);

        // There should be one entry for a forum in course1.
        $this->assertCount(1, $results[$course1->id]);
        $this->assertArrayHasKey('socialforum', $results[$course1->id]);

        $this->setUser($viewer2->id);
        $results = array();
        socialforum_print_overview($courses, $results);

        // There should be one entry for course1.
        $this->assertCount(0, $results);
    }

    /**
     * @dataProvider print_overview_timed_provider
     */
    public function test_print_overview_timed($config, $hasresult) {
        $this->resetAfterTest();
        $course1 = self::getDataGenerator()->create_course();

        // Create an author user.
        $author = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course1->id);

        // Create a viewer user.
        $viewer = self::getDataGenerator()->create_user((object) array('trackforums' => 1));
        $this->getDataGenerator()->enrol_user($viewer->id, $course1->id);

        // Create a forum.
        $record = new stdClass();
        $record->course = $course1->id;
        $socialforum1 = self::getDataGenerator()->create_module('socialforum', (object) array('course' => $course1->id));

        // A timed post with a timestart in the past (24 hours ago).
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $author->id;
        $record->socialforum = $socialforum1->id;
        if (isset($config['timestartmodifier'])) {
            $record->timestart = time() + $config['timestartmodifier'];
        }
        if (isset($config['timeendmodifier'])) {
            $record->timeend = time() + $config['timeendmodifier'];
        }
        $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        $course1->lastaccess = 0;
        $courses = array($course1->id => $course1);

        // As viewer, check the socialforum_print_overview result.
        $this->setUser($viewer->id);
        $results = array();
        socialforum_print_overview($courses, $results);

        if ($hasresult) {
            // There should be one entry for course1.
            $this->assertCount(1, $results);

            // There should be one entry for a social forum in course1.
            $this->assertCount(1, $results[$course1->id]);
            $this->assertArrayHasKey('socialforum', $results[$course1->id]);
        } else {
            // There should be no entries for any course.
            $this->assertCount(0, $results);
        }
    }

    /**
     * @dataProvider print_overview_timed_provider
     */
    public function test_print_overview_timed_groups($config, $hasresult) {
        $this->resetAfterTest();
        $course1 = self::getDataGenerator()->create_course();
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));

        // Create an author user.
        $author = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course1->id);

        // Create two viewer users - one in each group.
        $viewer1 = self::getDataGenerator()->create_user((object) array('trackforums' => 1));
        $this->getDataGenerator()->enrol_user($viewer1->id, $course1->id);
        $this->getDataGenerator()->create_group_member(array('userid' => $viewer1->id, 'groupid' => $group1->id));

        $viewer2 = self::getDataGenerator()->create_user((object) array('trackforums' => 1));
        $this->getDataGenerator()->enrol_user($viewer2->id, $course1->id);
        $this->getDataGenerator()->create_group_member(array('userid' => $viewer2->id, 'groupid' => $group2->id));

        // Create a social forum.
        $record = new stdClass();
        $record->course = $course1->id;
        $socialforum1 = self::getDataGenerator()->create_module('socialforum', (object) array(
                    'course' => $course1->id,
                    'groupmode' => SEPARATEGROUPS,
        ));

        // A post in the social forum for group1.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $author->id;
        $record->socialforum = $socialforum1->id;
        $record->groupid = $group1->id;
        if (isset($config['timestartmodifier'])) {
            $record->timestart = time() + $config['timestartmodifier'];
        }
        if (isset($config['timeendmodifier'])) {
            $record->timeend = time() + $config['timeendmodifier'];
        }
        $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        $course1->lastaccess = 0;
        $courses = array($course1->id => $course1);

        // As viewer1 (same group as post).
        $this->setUser($viewer1->id);
        $results = array();
        socialforum_print_overview($courses, $results);

        if ($hasresult) {
            // There should be one entry for course1.
            $this->assertCount(1, $results);

            // There should be one entry for a social forum in course1.
            $this->assertCount(1, $results[$course1->id]);
            $this->assertArrayHasKey('socialforum', $results[$course1->id]);
        } else {
            // There should be no entries for any course.
            $this->assertCount(0, $results);
        }

        $this->setUser($viewer2->id);
        $results = array();
        socialforum_print_overview($courses, $results);

        // There should be one entry for course1.
        $this->assertCount(0, $results);
    }

    public function print_overview_timed_provider() {
        return array(
            'timestart_past' => array(
                'discussionconfig' => array(
                    'timestartmodifier' => -86000,
                ),
                'hasresult' => true,
            ),
            'timestart_future' => array(
                'discussionconfig' => array(
                    'timestartmodifier' => 86000,
                ),
                'hasresult' => false,
            ),
            'timeend_past' => array(
                'discussionconfig' => array(
                    'timeendmodifier' => -86000,
                ),
                'hasresult' => false,
            ),
            'timeend_future' => array(
                'discussionconfig' => array(
                    'timeendmodifier' => 86000,
                ),
                'hasresult' => true,
            ),
        );
    }

    /**
     * Test test_pinned_discussion_with_group.
     */
    public function test_pinned_discussion_with_group() {
        global $SESSION;

        $this->resetAfterTest();
        $course1 = $this->getDataGenerator()->create_course();
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));

        // Create an author user.
        $author = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course1->id);

        // Create two viewer users - one in a group, one not.
        $viewer1 = $this->getDataGenerator()->create_user((object) array('trackforums' => 1));
        $this->getDataGenerator()->enrol_user($viewer1->id, $course1->id);

        $viewer2 = $this->getDataGenerator()->create_user((object) array('trackforums' => 1));
        $this->getDataGenerator()->enrol_user($viewer2->id, $course1->id);
        $this->getDataGenerator()->create_group_member(array('userid' => $viewer2->id, 'groupid' => $group1->id));

        $socialforum1 = $this->getDataGenerator()->create_module('socialforum', (object) array(
                    'course' => $course1->id,
                    'groupmode' => SEPARATEGROUPS,
        ));

        $coursemodule = get_coursemodule_from_instance('socialforum', $socialforum1->id);

        $alldiscussions = array();
        $group1discussions = array();

        // Create 4 discussions in all participants group and group1, where the first
        // discussion is pinned in each group.
        $allrecord = new stdClass();
        $allrecord->course = $course1->id;
        $allrecord->userid = $author->id;
        $allrecord->socialforum = $socialforum1->id;
        $allrecord->pinned = SOCIALFORUM_DISCUSSION_PINNED;

        $group1record = new stdClass();
        $group1record->course = $course1->id;
        $group1record->userid = $author->id;
        $group1record->socialforum = $socialforum1->id;
        $group1record->groupid = $group1->id;
        $group1record->pinned = SOCIALFORUM_DISCUSSION_PINNED;

        $alldiscussions[] = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($allrecord);
        $group1discussions[] = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($group1record);

        // Create unpinned discussions.
        $allrecord->pinned = SOCIALFORUM_DISCUSSION_UNPINNED;
        $group1record->pinned = SOCIALFORUM_DISCUSSION_UNPINNED;
        for ($i = 0; $i < 3; $i++) {
            $alldiscussions[] = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($allrecord);
            $group1discussions[] = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($group1record);
        }

        // As viewer1 (no group). This user shouldn't see any of group1's discussions
        // so their expected discussion order is (where rightmost is highest priority):
        // Ad1, ad2, ad3, ad0.
        $this->setUser($viewer1->id);

        // CHECK 1.
        // Take the neighbours of ad3, which should be prev: ad2 and next: ad0.
        $neighbours = socialforum_get_discussion_neighbours($coursemodule, $alldiscussions[3], $socialforum1);
        // Ad2 check.
        $this->assertEquals($alldiscussions[2]->id, $neighbours['prev']->id);
        // Ad0 check.
        $this->assertEquals($alldiscussions[0]->id, $neighbours['next']->id);

        // CHECK 2.
        // Take the neighbours of ad0, which should be prev: ad3 and next: null.
        $neighbours = socialforum_get_discussion_neighbours($coursemodule, $alldiscussions[0], $socialforum1);
        // Ad3 check.
        $this->assertEquals($alldiscussions[3]->id, $neighbours['prev']->id);
        // Null check.
        $this->assertEmpty($neighbours['next']);

        // CHECK 3.
        // Take the neighbours of ad1, which should be prev: null and next: ad2.
        $neighbours = socialforum_get_discussion_neighbours($coursemodule, $alldiscussions[1], $socialforum1);
        // Null check.
        $this->assertEmpty($neighbours['prev']);
        // Ad2 check.
        $this->assertEquals($alldiscussions[2]->id, $neighbours['next']->id);

        // Temporary hack to workaround for MDL-52656.
        $SESSION->currentgroup = null;

        // As viewer2 (group1). This user should see all of group1's posts and the all participants group.
        // The expected discussion order is (rightmost is highest priority):
        // Ad1, gd1, ad2, gd2, ad3, gd3, ad0, gd0.
        $this->setUser($viewer2->id);

        // CHECK 1.
        // Take the neighbours of ad1, which should be prev: null and next: gd1.
        $neighbours = socialforum_get_discussion_neighbours($coursemodule, $alldiscussions[1], $socialforum1);
        // Null check.
        $this->assertEmpty($neighbours['prev']);
        // Gd1 check.
        $this->assertEquals($group1discussions[1]->id, $neighbours['next']->id);

        // CHECK 2.
        // Take the neighbours of ad3, which should be prev: gd2 and next: gd3.
        $neighbours = socialforum_get_discussion_neighbours($coursemodule, $alldiscussions[3], $socialforum1);
        // Gd2 check.
        $this->assertEquals($group1discussions[2]->id, $neighbours['prev']->id);
        // Gd3 check.
        $this->assertEquals($group1discussions[3]->id, $neighbours['next']->id);

        // CHECK 3.
        // Take the neighbours of gd3, which should be prev: ad3 and next: ad0.
        $neighbours = socialforum_get_discussion_neighbours($coursemodule, $group1discussions[3], $socialforum1);
        // Ad3 check.
        $this->assertEquals($alldiscussions[3]->id, $neighbours['prev']->id);
        // Ad0 check.
        $this->assertEquals($alldiscussions[0]->id, $neighbours['next']->id);

        // CHECK 4.
        // Take the neighbours of gd0, which should be prev: ad0 and next: null.
        $neighbours = socialforum_get_discussion_neighbours($coursemodule, $group1discussions[0], $socialforum1);
        // Ad0 check.
        $this->assertEquals($alldiscussions[0]->id, $neighbours['prev']->id);
        // Null check.
        $this->assertEmpty($neighbours['next']);
    }

    /**
     * Test test_pinned_with_timed_discussions.
     */
    public function test_pinned_with_timed_discussions() {
        global $CFG;

        $CFG->socialforum_enabletimedposts = true;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        // Create an user.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Create a social forum.
        $record = new stdClass();
        $record->course = $course->id;
        $socialforum = $this->getDataGenerator()->create_module('socialforum', (object) array(
                    'course' => $course->id,
                    'groupmode' => SEPARATEGROUPS,
        ));

        $coursemodule = get_coursemodule_from_instance('socialforum', $socialforum->id);
        $now = time();
        $discussions = array();
        $discussiongenerator = $this->getDataGenerator()->get_plugin_generator('mod_socialforum');

        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->socialforum = $socialforum->id;
        $record->pinned = SOCIALFORUM_DISCUSSION_PINNED;
        $record->timemodified = $now;

        $discussions[] = $discussiongenerator->create_discussion($record);

        $record->pinned = SOCIALFORUM_DISCUSSION_UNPINNED;
        $record->timestart = $now + 10;

        $discussions[] = $discussiongenerator->create_discussion($record);

        $record->timestart = $now;

        $discussions[] = $discussiongenerator->create_discussion($record);

        // Expected order of discussions:
        // D2, d1, d0.
        $this->setUser($user->id);

        // CHECK 1.
        $neighbours = socialforum_get_discussion_neighbours($coursemodule, $discussions[2], $socialforum);
        // Null check.
        $this->assertEmpty($neighbours['prev']);
        // D1 check.
        $this->assertEquals($discussions[1]->id, $neighbours['next']->id);

        // CHECK 2.
        $neighbours = socialforum_get_discussion_neighbours($coursemodule, $discussions[1], $socialforum);
        // D2 check.
        $this->assertEquals($discussions[2]->id, $neighbours['prev']->id);
        // D0 check.
        $this->assertEquals($discussions[0]->id, $neighbours['next']->id);

        // CHECK 3.
        $neighbours = socialforum_get_discussion_neighbours($coursemodule, $discussions[0], $socialforum);
        // D2 check.
        $this->assertEquals($discussions[1]->id, $neighbours['prev']->id);
        // Null check.
        $this->assertEmpty($neighbours['next']);
    }

    /**
     * Test test_pinned_timed_discussions_with_timed_discussions.
     */
    public function test_pinned_timed_discussions_with_timed_discussions() {
        global $CFG;

        $CFG->socialforum_enabletimedposts = true;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        // Create an user.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Create a social forum.
        $record = new stdClass();
        $record->course = $course->id;
        $socialforum = $this->getDataGenerator()->create_module('socialforum', (object) array(
                    'course' => $course->id,
                    'groupmode' => SEPARATEGROUPS,
        ));

        $coursemodule = get_coursemodule_from_instance('socialforum', $socialforum->id);
        $now = time();
        $discussions = array();
        $discussiongenerator = $this->getDataGenerator()->get_plugin_generator('mod_socialforum');

        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->socialforum = $socialforum->id;
        $record->pinned = SOCIALFORUM_DISCUSSION_PINNED;
        $record->timemodified = $now;
        $record->timestart = $now + 10;

        $discussions[] = $discussiongenerator->create_discussion($record);

        $record->pinned = SOCIALFORUM_DISCUSSION_UNPINNED;

        $discussions[] = $discussiongenerator->create_discussion($record);

        $record->timestart = $now;

        $discussions[] = $discussiongenerator->create_discussion($record);

        $record->pinned = SOCIALFORUM_DISCUSSION_PINNED;

        $discussions[] = $discussiongenerator->create_discussion($record);

        // Expected order of discussions:
        // D2, d1, d3, d0.
        $this->setUser($user->id);

        // CHECK 1.
        $neighbours = socialforum_get_discussion_neighbours($coursemodule, $discussions[2], $socialforum);
        // Null check.
        $this->assertEmpty($neighbours['prev']);
        // D1 check.
        $this->assertEquals($discussions[1]->id, $neighbours['next']->id);

        // CHECK 2.
        $neighbours = socialforum_get_discussion_neighbours($coursemodule, $discussions[1], $socialforum);
        // D2 check.
        $this->assertEquals($discussions[2]->id, $neighbours['prev']->id);
        // D3 check.
        $this->assertEquals($discussions[3]->id, $neighbours['next']->id);

        // CHECK 3.
        $neighbours = socialforum_get_discussion_neighbours($coursemodule, $discussions[3], $socialforum);
        // D1 check.
        $this->assertEquals($discussions[1]->id, $neighbours['prev']->id);
        // D0 check.
        $this->assertEquals($discussions[0]->id, $neighbours['next']->id);

        // CHECK 4.
        $neighbours = socialforum_get_discussion_neighbours($coursemodule, $discussions[0], $socialforum);
        // D3 check.
        $this->assertEquals($discussions[3]->id, $neighbours['prev']->id);
        // Null check.
        $this->assertEmpty($neighbours['next']);
    }

    /**
     * @dataProvider socialforum_get_unmailed_posts_provider
     */
    public function test_socialforum_get_unmailed_posts($discussiondata, $enabletimedposts, $expectedcount, $expectedreplycount) {
        global $CFG, $DB;

        $this->resetAfterTest();

        // Configure timed posts.
        $CFG->socialforum_enabletimedposts = $enabletimedposts;

        $course = $this->getDataGenerator()->create_course();
        $socialforum = $this->getDataGenerator()->create_module('socialforum', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $socialforumgen = $this->getDataGenerator()->get_plugin_generator('mod_socialforum');

        // Keep track of the start time of the test. Do not use time() after this point to prevent random failures.
        $time = time();

        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->socialforum = $socialforum->id;
        if (isset($discussiondata['timecreated'])) {
            $record->timemodified = $time + $discussiondata['timecreated'];
        }
        if (isset($discussiondata['timestart'])) {
            $record->timestart = $time + $discussiondata['timestart'];
        }
        if (isset($discussiondata['timeend'])) {
            $record->timeend = $time + $discussiondata['timeend'];
        }
        if (isset($discussiondata['mailed'])) {
            $record->mailed = $discussiondata['mailed'];
        }

        $discussion = $socialforumgen->create_discussion($record);

        // Fetch the unmailed posts.
        $timenow = $time;
        $endtime = $timenow - $CFG->maxeditingtime;
        $starttime = $endtime - 2 * DAYSECS;

        $unmailed = socialforum_get_unmailed_posts($starttime, $endtime, $timenow);
        $this->assertCount($expectedcount, $unmailed);

        // Add a reply just outside the maxeditingtime.
        $replyto = $DB->get_record('socialforum_posts', array('discussion' => $discussion->id));
        $reply = new stdClass();
        $reply->userid = $user->id;
        $reply->discussion = $discussion->id;
        $reply->parent = $replyto->id;
        $reply->created = max($replyto->created, $endtime - 1);
        $socialforumgen->create_post($reply);

        $unmailed = socialforum_get_unmailed_posts($starttime, $endtime, $timenow);
        $this->assertCount($expectedreplycount, $unmailed);
    }

    public function socialforum_get_unmailed_posts_provider() {
        return [
            'Untimed discussion; Single post; maxeditingtime not expired' => [
                'discussion' => [
                ],
                'timedposts' => false,
                'postcount' => 0,
                'replycount' => 0,
            ],
            'Untimed discussion; Single post; maxeditingtime expired' => [
                'discussion' => [
                    'timecreated' => - DAYSECS,
                ],
                'timedposts' => false,
                'postcount' => 1,
                'replycount' => 2,
            ],
            'Timed discussion; Single post; Posted 1 week ago; timestart maxeditingtime not expired' => [
                'discussion' => [
                    'timecreated' => - WEEKSECS,
                    'timestart' => 0,
                ],
                'timedposts' => true,
                'postcount' => 0,
                'replycount' => 0,
            ],
            'Timed discussion; Single post; Posted 1 week ago; timestart maxeditingtime expired' => [
                'discussion' => [
                    'timecreated' => - WEEKSECS,
                    'timestart' => - DAYSECS,
                ],
                'timedposts' => true,
                'postcount' => 1,
                'replycount' => 2,
            ],
            'Timed discussion; Single post; Posted 1 week ago; timestart maxeditingtime expired; timeend not reached' => [
                'discussion' => [
                    'timecreated' => - WEEKSECS,
                    'timestart' => - DAYSECS,
                    'timeend' => + DAYSECS
                ],
                'timedposts' => true,
                'postcount' => 1,
                'replycount' => 2,
            ],
            'Timed discussion; Single post; Posted 1 week ago; timestart maxeditingtime expired; timeend passed' => [
                'discussion' => [
                    'timecreated' => - WEEKSECS,
                    'timestart' => - DAYSECS,
                    'timeend' => - HOURSECS,
                ],
                'timedposts' => true,
                'postcount' => 0,
                'replycount' => 0,
            ],
            'Timed discussion; Single post; Posted 1 week ago; timeend not reached' => [
                'discussion' => [
                    'timecreated' => - WEEKSECS,
                    'timeend' => + DAYSECS
                ],
                'timedposts' => true,
                'postcount' => 0,
                'replycount' => 1,
            ],
            'Timed discussion; Single post; Posted 1 week ago; timeend passed' => [
                'discussion' => [
                    'timecreated' => - WEEKSECS,
                    'timeend' => - DAYSECS,
                ],
                'timedposts' => true,
                'postcount' => 0,
                'replycount' => 0,
            ],
            'Previously mailed; Untimed discussion; Single post; maxeditingtime not expired' => [
                'discussion' => [
                    'mailed' => 1,
                ],
                'timedposts' => false,
                'postcount' => 0,
                'replycount' => 0,
            ],
            'Previously mailed; Untimed discussion; Single post; maxeditingtime expired' => [
                'discussion' => [
                    'timecreated' => - DAYSECS,
                    'mailed' => 1,
                ],
                'timedposts' => false,
                'postcount' => 0,
                'replycount' => 1,
            ],
            'Previously mailed; Timed discussion; Single post; Posted 1 week ago; timestart maxeditingtime not expired' => [
                'discussion' => [
                    'timecreated' => - WEEKSECS,
                    'timestart' => 0,
                    'mailed' => 1,
                ],
                'timedposts' => true,
                'postcount' => 0,
                'replycount' => 0,
            ],
            'Previously mailed; Timed discussion; Single post; Posted 1 week ago; timestart maxeditingtime expired' => [
                'discussion' => [
                    'timecreated' => - WEEKSECS,
                    'timestart' => - DAYSECS,
                    'mailed' => 1,
                ],
                'timedposts' => true,
                'postcount' => 0,
                'replycount' => 1,
            ],
            'Previously mailed; Timed discussion; Single post; Posted 1 week ago; timestart maxeditingtime expired; timeend not reached' => [
                'discussion' => [
                    'timecreated' => - WEEKSECS,
                    'timestart' => - DAYSECS,
                    'timeend' => + DAYSECS,
                    'mailed' => 1,
                ],
                'timedposts' => true,
                'postcount' => 0,
                'replycount' => 1,
            ],
            'Previously mailed; Timed discussion; Single post; Posted 1 week ago; timestart maxeditingtime expired; timeend passed' => [
                'discussion' => [
                    'timecreated' => - WEEKSECS,
                    'timestart' => - DAYSECS,
                    'timeend' => - HOURSECS,
                    'mailed' => 1,
                ],
                'timedposts' => true,
                'postcount' => 0,
                'replycount' => 0,
            ],
            'Previously mailed; Timed discussion; Single post; Posted 1 week ago; timeend not reached' => [
                'discussion' => [
                    'timecreated' => - WEEKSECS,
                    'timeend' => + DAYSECS,
                    'mailed' => 1,
                ],
                'timedposts' => true,
                'postcount' => 0,
                'replycount' => 1,
            ],
            'Previously mailed; Timed discussion; Single post; Posted 1 week ago; timeend passed' => [
                'discussion' => [
                    'timecreated' => - WEEKSECS,
                    'timeend' => - DAYSECS,
                    'mailed' => 1,
                ],
                'timedposts' => true,
                'postcount' => 0,
                'replycount' => 0,
            ],
        ];
    }

}

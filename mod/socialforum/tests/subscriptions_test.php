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

class mod_socialforum_subscriptions_testcase extends advanced_testcase {

    /**
     * Test setUp.
     */
    public function setUp() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_socialforum\subscriptions::reset_socialforum_cache();
        \mod_socialforum\subscriptions::reset_discussion_cache();
    }

    /**
     * Test tearDown.
     */
    public function tearDown() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_socialforum\subscriptions::reset_socialforum_cache();
        \mod_socialforum\subscriptions::reset_discussion_cache();
    }

    /**
     * Helper to create the required number of users in the specified
     * course.
     * Users are enrolled as students.
     *
     * @param stdClass $course The course object
     * @param integer $count The number of users to create
     * @return array The users created
     */
    protected function helper_create_users($course, $count) {
        $users = array();

        for ($i = 0; $i < $count; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
            $users[] = $user;
        }

        return $users;
    }

    /**
     * Create a new discussion and post within the specified forum, as the
     * specified author.
     *
     * @param stdClass $socialforum The forum to post in
     * @param stdClass $author The author to post as
     * @param array An array containing the discussion object, and the post object
     */
    protected function helper_post_to_socialforum($socialforum, $author) {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_socialforum');

        // Create a discussion in the forum, and then add a post to that discussion.
        $record = new stdClass();
        $record->course = $socialforum->course;
        $record->userid = $author->id;
        $record->socialforum = $socialforum->id;
        $discussion = $generator->create_discussion($record);

        // Retrieve the post which was created by create_discussion.
        $post = $DB->get_record('socialforum_posts', array('discussion' => $discussion->id));

        return array($discussion, $post);
    }

    public function test_subscription_modes() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        \mod_socialforum\subscriptions::set_subscription_mode($socialforum->id, SOCIALFORUM_FORCESUBSCRIBE);
        $socialforum = $DB->get_record('socialforum', array('id' => $socialforum->id));
        $this->assertEquals(SOCIALFORUM_FORCESUBSCRIBE, \mod_socialforum\subscriptions::get_subscription_mode($socialforum));
        $this->assertTrue(\mod_socialforum\subscriptions::is_forcesubscribed($socialforum));
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribable($socialforum));
        $this->assertFalse(\mod_socialforum\subscriptions::subscription_disabled($socialforum));

        \mod_socialforum\subscriptions::set_subscription_mode($socialforum->id, SOCIALFORUM_DISALLOWSUBSCRIBE);
        $socialforum = $DB->get_record('socialforum', array('id' => $socialforum->id));
        $this->assertEquals(SOCIALFORUM_DISALLOWSUBSCRIBE, \mod_socialforum\subscriptions::get_subscription_mode($socialforum));
        $this->assertTrue(\mod_socialforum\subscriptions::subscription_disabled($socialforum));
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribable($socialforum));
        $this->assertFalse(\mod_socialforum\subscriptions::is_forcesubscribed($socialforum));

        \mod_socialforum\subscriptions::set_subscription_mode($socialforum->id, SOCIALFORUM_INITIALSUBSCRIBE);
        $socialforum = $DB->get_record('socialforum', array('id' => $socialforum->id));
        $this->assertEquals(SOCIALFORUM_INITIALSUBSCRIBE, \mod_socialforum\subscriptions::get_subscription_mode($socialforum));
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribable($socialforum));
        $this->assertFalse(\mod_socialforum\subscriptions::subscription_disabled($socialforum));
        $this->assertFalse(\mod_socialforum\subscriptions::is_forcesubscribed($socialforum));

        \mod_socialforum\subscriptions::set_subscription_mode($socialforum->id, SOCIALFORUM_CHOOSESUBSCRIBE);
        $socialforum = $DB->get_record('socialforum', array('id' => $socialforum->id));
        $this->assertEquals(SOCIALFORUM_CHOOSESUBSCRIBE, \mod_socialforum\subscriptions::get_subscription_mode($socialforum));
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribable($socialforum));
        $this->assertFalse(\mod_socialforum\subscriptions::subscription_disabled($socialforum));
        $this->assertFalse(\mod_socialforum\subscriptions::is_forcesubscribed($socialforum));
    }

    /**
     * Test fetching unsubscribable forums.
     */
    public function test_unsubscribable_forums() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        // Create a user enrolled in the course as a student.
        list($user) = $this->helper_create_users($course, 1);

        // Must be logged in as the current user.
        $this->setUser($user);

        // Without any subscriptions, there should be nothing returned.
        $result = \mod_socialforum\subscriptions::get_unsubscribable_socialforums();
        $this->assertEquals(0, count($result));

        // Create the forums.
        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_FORCESUBSCRIBE);
        $forceforum = $this->getDataGenerator()->create_module('socialforum', $options);
        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_DISALLOWSUBSCRIBE);
        $disallowforum = $this->getDataGenerator()->create_module('socialforum', $options);
        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_CHOOSESUBSCRIBE);
        $chooseforum = $this->getDataGenerator()->create_module('socialforum', $options);
        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_INITIALSUBSCRIBE);
        $initialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // At present the user is only subscribed to the initial forum.
        $result = \mod_socialforum\subscriptions::get_unsubscribable_socialforums();
        $this->assertEquals(1, count($result));

        // Ensure that the user is enrolled in all of the forums except force subscribed.
        \mod_socialforum\subscriptions::subscribe_user($user->id, $disallowforum);
        \mod_socialforum\subscriptions::subscribe_user($user->id, $chooseforum);

        $result = \mod_socialforum\subscriptions::get_unsubscribable_socialforums();
        $this->assertEquals(3, count($result));

        // Hide the forums.
        set_coursemodule_visible($forceforum->cmid, 0);
        set_coursemodule_visible($disallowforum->cmid, 0);
        set_coursemodule_visible($chooseforum->cmid, 0);
        set_coursemodule_visible($initialforum->cmid, 0);
        $result = \mod_socialforum\subscriptions::get_unsubscribable_socialforums();
        $this->assertEquals(0, count($result));

        // Add the moodle/course:viewhiddenactivities capability to the student user.
        $roleids = $DB->get_records_menu('role', null, '', 'shortname, id');
        $context = \context_course::instance($course->id);
        assign_capability('moodle/course:viewhiddenactivities', CAP_ALLOW, $roleids['student'], $context);
        $context->mark_dirty();

        // All of the unsubscribable forums should now be listed.
        $result = \mod_socialforum\subscriptions::get_unsubscribable_socialforums();
        $this->assertEquals(3, count($result));
    }

    /**
     * Test that toggling the forum-level subscription for a different user does not affect their discussion-level
     * subscriptions.
     */
    public function test_forum_subscribe_toggle_as_other() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_CHOOSESUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create a user enrolled in the course as a student.
        list($author) = $this->helper_create_users($course, 1);

        // Post a discussion to the forum.
        list($discussion, $post) = $this->helper_post_to_socialforum($socialforum, $author);

        // Check that the user is currently not subscribed to the forum.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertFalse(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // Check that the user is unsubscribed from the discussion too.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum, $discussion->id));

        // Check that we have no records in either of the subscription tables.
        $this->assertEquals(0, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(0, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // Subscribing to the forum should create a record in the subscriptions table, but not the forum discussion
        // subscriptions table.
        \mod_socialforum\subscriptions::subscribe_user($author->id, $socialforum);
        $this->assertEquals(1, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(0, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // Unsubscribing should remove the record from the forum subscriptions table, and not modify the forum
        // discussion subscriptions table.
        \mod_socialforum\subscriptions::unsubscribe_user($author->id, $socialforum);
        $this->assertEquals(0, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(0, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // The same thing should happen calling the deprecated versions of
        // these functions.
        // Subscribing to the forum should create a record in the subscriptions table, but not the forum discussion
        // subscriptions table.
        socialforum_subscribe($author->id, $socialforum->id);
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();
        $this->assertEquals(1, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(0, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // Unsubscribing should remove the record from the forum subscriptions table, and not modify the forum
        // discussion subscriptions table.
        socialforum_unsubscribe($author->id, $socialforum->id);
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();
        $this->assertEquals(0, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(0, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // Enroling the user in the discussion should add one record to the forum discussion table without modifying the
        // form subscriptions.
        \mod_socialforum\subscriptions::subscribe_user_to_discussion($author->id, $discussion);
        $this->assertEquals(0, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(1, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // Unsubscribing should remove the record from the forum subscriptions table, and not modify the forum
        // discussion subscriptions table.
        \mod_socialforum\subscriptions::unsubscribe_user_from_discussion($author->id, $discussion);
        $this->assertEquals(0, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(0, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // Re-subscribe to the discussion so that we can check the effect of forum-level subscriptions.
        \mod_socialforum\subscriptions::subscribe_user_to_discussion($author->id, $discussion);
        $this->assertEquals(0, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(1, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // Subscribing to the forum should have no effect on the forum discussion subscriptions table if the user did
        // not request the change themself.
        \mod_socialforum\subscriptions::subscribe_user($author->id, $socialforum);
        $this->assertEquals(1, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(1, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // Unsubscribing from the forum should have no effect on the forum discussion subscriptions table if the user
        // did not request the change themself.
        \mod_socialforum\subscriptions::unsubscribe_user($author->id, $socialforum);
        $this->assertEquals(0, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(1, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // Subscribing to the forum should remove the per-discussion subscription preference if the user requested the
        // change themself.
        \mod_socialforum\subscriptions::subscribe_user($author->id, $socialforum, null, true);
        $this->assertEquals(1, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(0, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // Now unsubscribe from the current discussion whilst being subscribed to the forum as a whole.
        \mod_socialforum\subscriptions::unsubscribe_user_from_discussion($author->id, $discussion);
        $this->assertEquals(1, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(1, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // Unsubscribing from the forum should remove the per-discussion subscription preference if the user requested the
        // change themself.
        \mod_socialforum\subscriptions::unsubscribe_user($author->id, $socialforum, null, true);
        $this->assertEquals(0, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(0, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // Subscribe to the discussion.
        \mod_socialforum\subscriptions::subscribe_user_to_discussion($author->id, $discussion);
        $this->assertEquals(0, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(1, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // Subscribe to the forum without removing the discussion preferences.
        \mod_socialforum\subscriptions::subscribe_user($author->id, $socialforum);
        $this->assertEquals(1, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(1, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // Unsubscribing from the discussion should result in a change.
        \mod_socialforum\subscriptions::unsubscribe_user_from_discussion($author->id, $discussion);
        $this->assertEquals(1, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(1, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));
    }

    /**
     * Test that a user unsubscribed from a forum is not subscribed to it's discussions by default.
     */
    public function test_forum_discussion_subscription_forum_unsubscribed() {
        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_CHOOSESUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create users enrolled in the course as students.
        list($author) = $this->helper_create_users($course, 1);

        // Check that the user is currently not subscribed to the forum.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertFalse(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // Post a discussion to the forum.
        list($discussion, $post) = $this->helper_post_to_socialforum($socialforum, $author);

        // Check that the user is unsubscribed from the discussion too.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum, $discussion->id));
    }

    /**
     * Test that the act of subscribing to a forum subscribes the user to it's discussions by default.
     */
    public function test_forum_discussion_subscription_forum_subscribed() {
        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_CHOOSESUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create users enrolled in the course as students.
        list($author) = $this->helper_create_users($course, 1);

        // Enrol the user in the forum.
        // If a subscription was added, we get the record ID.
        $this->assertInternalType('int', \mod_socialforum\subscriptions::subscribe_user($author->id, $socialforum));

        // If we already have a subscription when subscribing the user, we get a boolean (true).
        $this->assertTrue(\mod_socialforum\subscriptions::subscribe_user($author->id, $socialforum));

        // Check that the user is currently subscribed to the forum.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertTrue(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // Post a discussion to the forum.
        list($discussion, $post) = $this->helper_post_to_socialforum($socialforum, $author);

        // Check that the user is subscribed to the discussion too.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum, $discussion->id));
    }

    /**
     * Test that a user unsubscribed from a forum can be subscribed to a discussion.
     */
    public function test_forum_discussion_subscription_forum_unsubscribed_discussion_subscribed() {
        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_CHOOSESUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create a user enrolled in the course as a student.
        list($author) = $this->helper_create_users($course, 1);

        // Check that the user is currently not subscribed to the forum.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertFalse(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // Post a discussion to the forum.
        list($discussion, $post) = $this->helper_post_to_socialforum($socialforum, $author);

        // Attempting to unsubscribe from the discussion should not make a change.
        $this->assertFalse(\mod_socialforum\subscriptions::unsubscribe_user_from_discussion($author->id, $discussion));

        // Then subscribe them to the discussion.
        $this->assertTrue(\mod_socialforum\subscriptions::subscribe_user_to_discussion($author->id, $discussion));

        // Check that the user is still unsubscribed from the forum.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertFalse(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // But subscribed to the discussion.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum, $discussion->id));
    }

    /**
     * Test that a user subscribed to a forum can be unsubscribed from a discussion.
     */
    public function test_forum_discussion_subscription_forum_subscribed_discussion_unsubscribed() {
        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_CHOOSESUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create two users enrolled in the course as students.
        list($author) = $this->helper_create_users($course, 2);

        // Enrol the student in the forum.
        \mod_socialforum\subscriptions::subscribe_user($author->id, $socialforum);

        // Check that the user is currently subscribed to the forum.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertTrue(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // Post a discussion to the forum.
        list($discussion, $post) = $this->helper_post_to_socialforum($socialforum, $author);

        // Then unsubscribe them from the discussion.
        \mod_socialforum\subscriptions::unsubscribe_user_from_discussion($author->id, $discussion);

        // Check that the user is still subscribed to the forum.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertTrue(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // But unsubscribed from the discussion.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum, $discussion->id));
    }

    /**
     * Test the effect of toggling the discussion subscription status when subscribed to the forum.
     */
    public function test_forum_discussion_toggle_forum_subscribed() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_CHOOSESUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create two users enrolled in the course as students.
        list($author) = $this->helper_create_users($course, 2);

        // Enrol the student in the forum.
        \mod_socialforum\subscriptions::subscribe_user($author->id, $socialforum);

        // Check that the user is currently subscribed to the forum.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertTrue(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // Post a discussion to the forum.
        list($discussion, $post) = $this->helper_post_to_socialforum($socialforum, $author);

        // Check that the user is initially subscribed to that discussion.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum, $discussion->id));

        // An attempt to subscribe again should result in a falsey return to indicate that no change was made.
        $this->assertFalse(\mod_socialforum\subscriptions::subscribe_user_to_discussion($author->id, $discussion));

        // And there should be no discussion subscriptions (and one forum subscription).
        $this->assertEquals(0, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));
        $this->assertEquals(1, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));

        // Then unsubscribe them from the discussion.
        \mod_socialforum\subscriptions::unsubscribe_user_from_discussion($author->id, $discussion);

        // Check that the user is still subscribed to the forum.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertTrue(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // An attempt to unsubscribe again should result in a falsey return to indicate that no change was made.
        $this->assertFalse(\mod_socialforum\subscriptions::unsubscribe_user_from_discussion($author->id, $discussion));

        // And there should be a discussion subscriptions (and one forum subscription).
        $this->assertEquals(1, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));
        $this->assertEquals(1, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));

        // But unsubscribed from the discussion.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum, $discussion->id));

        // There should be a record in the discussion subscription tracking table.
        $this->assertEquals(1, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // And one in the forum subscription tracking table.
        $this->assertEquals(1, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));

        // Now subscribe the user again to the discussion.
        \mod_socialforum\subscriptions::subscribe_user_to_discussion($author->id, $discussion);

        // Check that the user is still subscribed to the forum.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertTrue(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // Check the deprecated function too.
        $this->assertTrue(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // And is subscribed to the discussion again.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum, $discussion->id));

        // There should be no record in the discussion subscription tracking table.
        $this->assertEquals(0, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // And one in the forum subscription tracking table.
        $this->assertEquals(1, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));

        // And unsubscribe again.
        \mod_socialforum\subscriptions::unsubscribe_user_from_discussion($author->id, $discussion);

        // Check that the user is still subscribed to the forum.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertTrue(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // But unsubscribed from the discussion.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum, $discussion->id));

        // There should be a record in the discussion subscription tracking table.
        $this->assertEquals(1, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // And one in the forum subscription tracking table.
        $this->assertEquals(1, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));

        // And subscribe the user again to the discussion.
        \mod_socialforum\subscriptions::subscribe_user_to_discussion($author->id, $discussion);

        // Check that the user is still subscribed to the forum.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // And is subscribed to the discussion again.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum, $discussion->id));

        // There should be no record in the discussion subscription tracking table.
        $this->assertEquals(0, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // And one in the forum subscription tracking table.
        $this->assertEquals(1, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));

        // And unsubscribe again.
        \mod_socialforum\subscriptions::unsubscribe_user_from_discussion($author->id, $discussion);

        // Check that the user is still subscribed to the forum.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertTrue(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // But unsubscribed from the discussion.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum, $discussion->id));

        // There should be a record in the discussion subscription tracking table.
        $this->assertEquals(1, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // And one in the forum subscription tracking table.
        $this->assertEquals(1, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));

        // Now unsubscribe the user from the forum.
        $this->assertTrue(\mod_socialforum\subscriptions::unsubscribe_user($author->id, $socialforum, null, true));

        // This removes both the SOCIALFORUM_subscriptions, and the SOCIALFORUM_discussion_subs records.
        $this->assertEquals(0, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));
        $this->assertEquals(0, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $author->id,
                    'socialforum' => $socialforum->id,
        )));

        // And should have reset the discussion cache value.
        $result = \mod_socialforum\subscriptions::fetch_discussion_subscription($socialforum->id, $author->id);
        $this->assertInternalType('array', $result);
        $this->assertFalse(isset($result[$discussion->id]));
    }

    /**
     * Test the effect of toggling the discussion subscription status when unsubscribed from the forum.
     */
    public function test_forum_discussion_toggle_forum_unsubscribed() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_CHOOSESUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create two users enrolled in the course as students.
        list($author) = $this->helper_create_users($course, 2);

        // Check that the user is currently unsubscribed to the forum.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertFalse(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // Post a discussion to the forum.
        list($discussion, $post) = $this->helper_post_to_socialforum($socialforum, $author);

        // Check that the user is initially unsubscribed to that discussion.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum, $discussion->id));

        // Then subscribe them to the discussion.
        $this->assertTrue(\mod_socialforum\subscriptions::subscribe_user_to_discussion($author->id, $discussion));

        // An attempt to subscribe again should result in a falsey return to indicate that no change was made.
        $this->assertFalse(\mod_socialforum\subscriptions::subscribe_user_to_discussion($author->id, $discussion));

        // Check that the user is still unsubscribed from the forum.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertFalse(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // But subscribed to the discussion.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum, $discussion->id));

        // There should be a record in the discussion subscription tracking table.
        $this->assertEquals(1, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // Now unsubscribe the user again from the discussion.
        \mod_socialforum\subscriptions::unsubscribe_user_from_discussion($author->id, $discussion);

        // Check that the user is still unsubscribed from the forum.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertFalse(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // And is unsubscribed from the discussion again.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum, $discussion->id));

        // There should be no record in the discussion subscription tracking table.
        $this->assertEquals(0, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // And subscribe the user again to the discussion.
        \mod_socialforum\subscriptions::subscribe_user_to_discussion($author->id, $discussion);

        // Check that the user is still unsubscribed from the forum.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertFalse(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // And is subscribed to the discussion again.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum, $discussion->id));

        // There should be a record in the discussion subscription tracking table.
        $this->assertEquals(1, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));

        // And unsubscribe again.
        \mod_socialforum\subscriptions::unsubscribe_user_from_discussion($author->id, $discussion);

        // Check that the user is still unsubscribed from the forum.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum));

        // Check the deprecated function too.
        $this->assertFalse(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // But unsubscribed from the discussion.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($author->id, $socialforum, $discussion->id));

        // There should be no record in the discussion subscription tracking table.
        $this->assertEquals(0, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $author->id,
                    'discussion' => $discussion->id,
        )));
    }

    /**
     * Test that the deprecated socialforum_is_subscribed accepts numeric forum IDs.
     */
    public function test_forum_is_subscribed_numeric() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_CHOOSESUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create a user enrolled in the course as a students.
        list($author) = $this->helper_create_users($course, 1);

        // Check that the user is currently unsubscribed to the forum.
        $this->assertFalse(socialforum_is_subscribed($author->id, $socialforum->id));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // It should match the result of when it's called with the forum object.
        $this->assertFalse(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // And when the user is subscribed, we should also get the correct result.
        \mod_socialforum\subscriptions::subscribe_user($author->id, $socialforum);

        $this->assertTrue(socialforum_is_subscribed($author->id, $socialforum->id));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();

        // It should match the result of when it's called with the forum object.
        $this->assertTrue(socialforum_is_subscribed($author->id, $socialforum));
        $this->assertEquals(1, count($this->getDebuggingMessages()));
        $this->resetDebugging();
    }

    /**
     * Test that the correct users are returned when fetching subscribed users from a forum where users can choose to
     * subscribe and unsubscribe.
     */
    public function test_fetch_subscribed_users_subscriptions() {
        global $DB, $CFG;

        $this->resetAfterTest(true);

        // Create a course, with a forum. where users are initially subscribed.
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_INITIALSUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create some user enrolled in the course as a student.
        $usercount = 5;
        $users = $this->helper_create_users($course, $usercount);

        // All users should be subscribed.
        $subscribers = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum);
        $this->assertEquals($usercount, count($subscribers));

        // Subscribe the guest user too to the forum - they should never be returned by this function.
        $this->getDataGenerator()->enrol_user($CFG->siteguest, $course->id);
        $subscribers = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum);
        $this->assertEquals($usercount, count($subscribers));

        // Unsubscribe 2 users.
        $unsubscribedcount = 2;
        for ($i = 0; $i < $unsubscribedcount; $i++) {
            \mod_socialforum\subscriptions::unsubscribe_user($users[$i]->id, $socialforum);
        }

        // The subscription count should now take into account those users who have been unsubscribed.
        $subscribers = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum);
        $this->assertEquals($usercount - $unsubscribedcount, count($subscribers));
    }

    /**
     * Test that the correct users are returned hwen fetching subscribed users from a forum where users are forcibly
     * subscribed.
     */
    public function test_fetch_subscribed_users_forced() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a forum. where users are initially subscribed.
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_FORCESUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create some user enrolled in the course as a student.
        $usercount = 5;
        $users = $this->helper_create_users($course, $usercount);

        // All users should be subscribed.
        $subscribers = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum);
        $this->assertEquals($usercount, count($subscribers));
    }

    /**
     * Test that unusual combinations of discussion subscriptions do not affect the subscribed user list.
     */
    public function test_fetch_subscribed_users_discussion_subscriptions() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a forum. where users are initially subscribed.
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_INITIALSUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create some user enrolled in the course as a student.
        $usercount = 5;
        $users = $this->helper_create_users($course, $usercount);

        list($discussion, $post) = $this->helper_post_to_socialforum($socialforum, $users[0]);

        // All users should be subscribed.
        $subscribers = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum);
        $this->assertEquals($usercount, count($subscribers));
        $subscribers = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum, 0, null, null, true);
        $this->assertEquals($usercount, count($subscribers));

        \mod_socialforum\subscriptions::unsubscribe_user_from_discussion($users[0]->id, $discussion);

        // All users should be subscribed.
        $subscribers = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum);
        $this->assertEquals($usercount, count($subscribers));

        // All users should be subscribed.
        $subscribers = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum, 0, null, null, true);
        $this->assertEquals($usercount, count($subscribers));

        // Manually insert an extra subscription for one of the users.
        $record = new stdClass();
        $record->userid = $users[2]->id;
        $record->socialforum = $socialforum->id;
        $record->discussion = $discussion->id;
        $record->preference = time();
        $DB->insert_record('socialforum_discussion_subs', $record);

        // The discussion count should not have changed.
        $subscribers = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum);
        $this->assertEquals($usercount, count($subscribers));
        $subscribers = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum, 0, null, null, true);
        $this->assertEquals($usercount, count($subscribers));

        // Unsubscribe 2 users.
        $unsubscribedcount = 2;
        for ($i = 0; $i < $unsubscribedcount; $i++) {
            \mod_socialforum\subscriptions::unsubscribe_user($users[$i]->id, $socialforum);
        }

        // The subscription count should now take into account those users who have been unsubscribed.
        $subscribers = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum);
        $this->assertEquals($usercount - $unsubscribedcount, count($subscribers));
        $subscribers = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum, 0, null, null, true);
        $this->assertEquals($usercount - $unsubscribedcount, count($subscribers));

        // Now subscribe one of those users back to the discussion.
        $subscribeddiscussionusers = 1;
        for ($i = 0; $i < $subscribeddiscussionusers; $i++) {
            \mod_socialforum\subscriptions::subscribe_user_to_discussion($users[$i]->id, $discussion);
        }
        $subscribers = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum);
        $this->assertEquals($usercount - $unsubscribedcount, count($subscribers));
        $subscribers = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum, 0, null, null, true);
        $this->assertEquals($usercount - $unsubscribedcount + $subscribeddiscussionusers, count($subscribers));
    }

    /**
     * Test whether a user is force-subscribed to a forum.
     */
    public function test_force_subscribed_to_forum() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_FORCESUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create a user enrolled in the course as a student.
        $roleids = $DB->get_records_menu('role', null, '', 'shortname, id');
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $roleids['student']);

        // Check that the user is currently subscribed to the forum.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($user->id, $socialforum));

        // Remove the allowforcesubscribe capability from the user.
        $cm = get_coursemodule_from_instance('socialforum', $socialforum->id);
        $context = \context_module::instance($cm->id);
        assign_capability('mod/socialforum:allowforcesubscribe', CAP_PROHIBIT, $roleids['student'], $context);
        $context->mark_dirty();
        $this->assertFalse(has_capability('mod/socialforum:allowforcesubscribe', $context, $user->id));

        // Check that the user is no longer subscribed to the forum.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($user->id, $socialforum));
    }

    /**
     * Test that the subscription cache can be pre-filled.
     */
    public function test_subscription_cache_prefill() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_INITIALSUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create some users.
        $users = $this->helper_create_users($course, 20);

        // Reset the subscription cache.
        \mod_socialforum\subscriptions::reset_socialforum_cache();

        // Filling the subscription cache should only use a single query.
        $startcount = $DB->perf_get_reads();
        $this->assertNull(\mod_socialforum\subscriptions::fill_subscription_cache($socialforum->id));
        $postfillcount = $DB->perf_get_reads();
        $this->assertEquals(1, $postfillcount - $startcount);

        // Now fetch some subscriptions from that forum - these should use
        // the cache and not perform additional queries.
        foreach ($users as $user) {
            $this->assertTrue(\mod_socialforum\subscriptions::fetch_subscription_cache($socialforum->id, $user->id));
        }
        $finalcount = $DB->perf_get_reads();
        $this->assertEquals(0, $finalcount - $postfillcount);
    }

    /**
     * Test that the subscription cache can filled user-at-a-time.
     */
    public function test_subscription_cache_fill() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_INITIALSUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create some users.
        $users = $this->helper_create_users($course, 20);

        // Reset the subscription cache.
        \mod_socialforum\subscriptions::reset_socialforum_cache();

        // Filling the subscription cache should only use a single query.
        $startcount = $DB->perf_get_reads();

        // Fetch some subscriptions from that forum - these should not use the cache and will perform additional queries.
        foreach ($users as $user) {
            $this->assertTrue(\mod_socialforum\subscriptions::fetch_subscription_cache($socialforum->id, $user->id));
        }
        $finalcount = $DB->perf_get_reads();
        $this->assertEquals(20, $finalcount - $startcount);
    }

    /**
     * Test that the discussion subscription cache can filled course-at-a-time.
     */
    public function test_discussion_subscription_cache_fill_for_course() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        // Create the forums.
        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_DISALLOWSUBSCRIBE);
        $disallowforum = $this->getDataGenerator()->create_module('socialforum', $options);
        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_CHOOSESUBSCRIBE);
        $chooseforum = $this->getDataGenerator()->create_module('socialforum', $options);
        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_INITIALSUBSCRIBE);
        $initialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create some users and keep a reference to the first user.
        $users = $this->helper_create_users($course, 20);
        $user = reset($users);

        // Reset the subscription caches.
        \mod_socialforum\subscriptions::reset_socialforum_cache();

        $startcount = $DB->perf_get_reads();
        $result = \mod_socialforum\subscriptions::fill_subscription_cache_for_course($course->id, $user->id);
        $this->assertNull($result);
        $postfillcount = $DB->perf_get_reads();
        $this->assertEquals(1, $postfillcount - $startcount);
        $this->assertFalse(\mod_socialforum\subscriptions::fetch_subscription_cache($disallowforum->id, $user->id));
        $this->assertFalse(\mod_socialforum\subscriptions::fetch_subscription_cache($chooseforum->id, $user->id));
        $this->assertTrue(\mod_socialforum\subscriptions::fetch_subscription_cache($initialforum->id, $user->id));
        $finalcount = $DB->perf_get_reads();
        $this->assertEquals(0, $finalcount - $postfillcount);

        // Test for all users.
        foreach ($users as $user) {
            $result = \mod_socialforum\subscriptions::fill_subscription_cache_for_course($course->id, $user->id);
            $this->assertFalse(\mod_socialforum\subscriptions::fetch_subscription_cache($disallowforum->id, $user->id));
            $this->assertFalse(\mod_socialforum\subscriptions::fetch_subscription_cache($chooseforum->id, $user->id));
            $this->assertTrue(\mod_socialforum\subscriptions::fetch_subscription_cache($initialforum->id, $user->id));
        }
        $finalcount = $DB->perf_get_reads();
        $this->assertEquals(count($users), $finalcount - $postfillcount);
    }

    /**
     * Test that the discussion subscription cache can be forcibly updated for a user.
     */
    public function test_discussion_subscription_cache_prefill() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_INITIALSUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create some users.
        $users = $this->helper_create_users($course, 20);

        // Post some discussions to the forum.
        $discussions = array();
        $author = $users[0];
        for ($i = 0; $i < 20; $i++) {
            list($discussion, $post) = $this->helper_post_to_socialforum($socialforum, $author);
            $discussions[] = $discussion;
        }

        // Unsubscribe half the users from the half the discussions.
        $socialforumcount = 0;
        $usercount = 0;
        foreach ($discussions as $data) {
            if ($socialforumcount % 2) {
                continue;
            }
            foreach ($users as $user) {
                if ($usercount % 2) {
                    continue;
                }
                \mod_socialforum\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion);
                $usercount++;
            }
            $socialforumcount++;
        }

        // Reset the subscription caches.
        \mod_socialforum\subscriptions::reset_socialforum_cache();
        \mod_socialforum\subscriptions::reset_discussion_cache();

        // Filling the discussion subscription cache should only use a single query.
        $startcount = $DB->perf_get_reads();
        $this->assertNull(\mod_socialforum\subscriptions::fill_discussion_subscription_cache($socialforum->id));
        $postfillcount = $DB->perf_get_reads();
        $this->assertEquals(1, $postfillcount - $startcount);

        // Now fetch some subscriptions from that forum - these should use
        // the cache and not perform additional queries.
        foreach ($users as $user) {
            $result = \mod_socialforum\subscriptions::fetch_discussion_subscription($socialforum->id, $user->id);
            $this->assertInternalType('array', $result);
        }
        $finalcount = $DB->perf_get_reads();
        $this->assertEquals(0, $finalcount - $postfillcount);
    }

    /**
     * Test that the discussion subscription cache can filled user-at-a-time.
     */
    public function test_discussion_subscription_cache_fill() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_INITIALSUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create some users.
        $users = $this->helper_create_users($course, 20);

        // Post some discussions to the forum.
        $discussions = array();
        $author = $users[0];
        for ($i = 0; $i < 20; $i++) {
            list($discussion, $post) = $this->helper_post_to_socialforum($socialforum, $author);
            $discussions[] = $discussion;
        }

        // Unsubscribe half the users from the half the discussions.
        $socialforumcount = 0;
        $usercount = 0;
        foreach ($discussions as $data) {
            if ($socialforumcount % 2) {
                continue;
            }
            foreach ($users as $user) {
                if ($usercount % 2) {
                    continue;
                }
                \mod_socialforum\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion);
                $usercount++;
            }
            $socialforumcount++;
        }

        // Reset the subscription caches.
        \mod_socialforum\subscriptions::reset_socialforum_cache();
        \mod_socialforum\subscriptions::reset_discussion_cache();

        $startcount = $DB->perf_get_reads();

        // Now fetch some subscriptions from that forum - these should use
        // the cache and not perform additional queries.
        foreach ($users as $user) {
            $result = \mod_socialforum\subscriptions::fetch_discussion_subscription($socialforum->id, $user->id);
            $this->assertInternalType('array', $result);
        }
        $finalcount = $DB->perf_get_reads();
        $this->assertEquals(20, $finalcount - $startcount);
    }

    /**
     * Test that after toggling the forum subscription as another user,
     * the discussion subscription functionality works as expected.
     */
    public function test_forum_subscribe_toggle_as_other_repeat_subscriptions() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_CHOOSESUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create a user enrolled in the course as a student.
        list($user) = $this->helper_create_users($course, 1);

        // Post a discussion to the forum.
        list($discussion, $post) = $this->helper_post_to_socialforum($socialforum, $user);

        // Confirm that the user is currently not subscribed to the forum.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($user->id, $socialforum));

        // Confirm that the user is unsubscribed from the discussion too.
        $this->assertFalse(\mod_socialforum\subscriptions::is_subscribed($user->id, $socialforum, $discussion->id));

        // Confirm that we have no records in either of the subscription tables.
        $this->assertEquals(0, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $user->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(0, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $user->id,
                    'discussion' => $discussion->id,
        )));

        // Subscribing to the forum should create a record in the subscriptions table, but not the forum discussion
        // subscriptions table.
        \mod_socialforum\subscriptions::subscribe_user($user->id, $socialforum);
        $this->assertEquals(1, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $user->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(0, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $user->id,
                    'discussion' => $discussion->id,
        )));

        // Now unsubscribe from the discussion. This should return true.
        $this->assertTrue(\mod_socialforum\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion));

        // Attempting to unsubscribe again should return false because no change was made.
        $this->assertFalse(\mod_socialforum\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion));

        // Subscribing to the discussion again should return truthfully as the subscription preference was removed.
        $this->assertTrue(\mod_socialforum\subscriptions::subscribe_user_to_discussion($user->id, $discussion));

        // Attempting to subscribe again should return false because no change was made.
        $this->assertFalse(\mod_socialforum\subscriptions::subscribe_user_to_discussion($user->id, $discussion));

        // Now unsubscribe from the discussion. This should return true once more.
        $this->assertTrue(\mod_socialforum\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion));

        // And unsubscribing from the forum but not as a request from the user should maintain their preference.
        \mod_socialforum\subscriptions::unsubscribe_user($user->id, $socialforum);

        $this->assertEquals(0, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $user->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(1, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $user->id,
                    'discussion' => $discussion->id,
        )));

        // Subscribing to the discussion should return truthfully because a change was made.
        $this->assertTrue(\mod_socialforum\subscriptions::subscribe_user_to_discussion($user->id, $discussion));
        $this->assertEquals(0, $DB->count_records('socialforum_subscriptions', array(
                    'userid' => $user->id,
                    'socialforum' => $socialforum->id,
        )));
        $this->assertEquals(1, $DB->count_records('socialforum_discussion_subs', array(
                    'userid' => $user->id,
                    'discussion' => $discussion->id,
        )));
    }

    /**
     * Test that providing a context_module instance to is_subscribed does not result in additional lookups to retrieve
     * the context_module.
     */
    public function test_is_subscribed_cm() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a forum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => SOCIALFORUM_FORCESUBSCRIBE);
        $socialforum = $this->getDataGenerator()->create_module('socialforum', $options);

        // Create a user enrolled in the course as a student.
        list($user) = $this->helper_create_users($course, 1);

        // Retrieve the $cm now.
        $cm = get_fast_modinfo($socialforum->course)->instances['socialforum'][$socialforum->id];

        // Reset get_fast_modinfo.
        get_fast_modinfo(0, 0, true);

        // Call is_subscribed without passing the $cmid - this should result in a lookup and filling of some of the
        // caches. This provides us with consistent data to start from.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($user->id, $socialforum));
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($user->id, $socialforum));

        // Make a note of the number of DB calls.
        $basecount = $DB->perf_get_reads();

        // Call is_subscribed - it should give return the correct result (False), and result in no additional queries.
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($user->id, $socialforum, null, $cm));

        // The capability check does require some queries, so we don't test it directly.
        // We don't assert here because this is dependant upon linked code which could change at any time.
        $suppliedcmcount = $DB->perf_get_reads() - $basecount;

        // Call is_subscribed without passing the $cmid now - this should result in a lookup.
        get_fast_modinfo(0, 0, true);
        $basecount = $DB->perf_get_reads();
        $this->assertTrue(\mod_socialforum\subscriptions::is_subscribed($user->id, $socialforum));
        $calculatedcmcount = $DB->perf_get_reads() - $basecount;

        // There should be more queries than when we performed the same check a moment ago.
        $this->assertGreaterThan($suppliedcmcount, $calculatedcmcount);
    }

}

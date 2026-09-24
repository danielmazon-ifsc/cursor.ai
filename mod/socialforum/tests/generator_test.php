<?php

/**
 * PHPUnit data generator tests
 *
 * @package    mod_socialforum
 * @category   phpunit
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit data generator testcase
 *
 * @package    mod_socialforum
 * @category   phpunit
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class mod_socialforum_generator_testcase extends advanced_testcase {

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

    public function test_generator() {
        global $DB;

        $this->resetAfterTest(true);

        $this->assertEquals(0, $DB->count_records('socialforum'));

        $course = $this->getDataGenerator()->create_course();

        /** @var mod_socialforum_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_socialforum');
        $this->assertInstanceOf('mod_socialforum_generator', $generator);
        $this->assertEquals('socialforum', $generator->get_modulename());

        $generator->create_instance(array('course' => $course->id));
        $generator->create_instance(array('course' => $course->id));
        $socialforum = $generator->create_instance(array('course' => $course->id));
        $this->assertEquals(3, $DB->count_records('socialforum'));

        $cm = get_coursemodule_from_instance('socialforum', $socialforum->id);
        $this->assertEquals($socialforum->id, $cm->instance);
        $this->assertEquals('socialforum', $cm->modname);
        $this->assertEquals($course->id, $cm->course);

        $context = context_module::instance($cm->id);
        $this->assertEquals($socialforum->cmid, $context->instanceid);

        // test gradebook integration using low level DB access - DO NOT USE IN PLUGIN CODE!
        $socialforum = $generator->create_instance(array('course' => $course->id, 'assessed' => 1, 'scale' => 100));
        $gitem = $DB->get_record('grade_items', array('courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'socialforum', 'iteminstance' => $socialforum->id));
        $this->assertNotEmpty($gitem);
        $this->assertEquals(100, $gitem->grademax);
        $this->assertEquals(0, $gitem->grademin);
        $this->assertEquals(GRADE_TYPE_VALUE, $gitem->gradetype);
    }

    /**
     * Test create_discussion.
     */
    public function test_create_discussion() {
        global $DB;

        $this->resetAfterTest(true);

        // User that will create the forum.
        $user = self::getDataGenerator()->create_user();

        // Create course to add the forum to.
        $course = self::getDataGenerator()->create_course();

        // The forum.
        $record = new stdClass();
        $record->course = $course->id;
        $socialforum = self::getDataGenerator()->create_module('socialforum', $record);

        // Add a few discussions.
        $record = array();
        $record['course'] = $course->id;
        $record['socialforum'] = $socialforum->id;
        $record['userid'] = $user->id;
        $record['pinned'] = SOCIALFORUM_DISCUSSION_PINNED; // Pin one discussion.
        self::getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);
        $record['pinned'] = SOCIALFORUM_DISCUSSION_UNPINNED; // No pin for others.
        self::getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);
        self::getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // Check the discussions were correctly created.
        $this->assertEquals(3, $DB->count_records_select('socialforum_discussions', 'socialforum = :socialforum', array('socialforum' => $socialforum->id)));
    }

    /**
     * Test create_post.
     */
    public function test_create_post() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a bunch of users
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        // Create course to add the forum.
        $course = self::getDataGenerator()->create_course();

        // The forum.
        $record = new stdClass();
        $record->course = $course->id;
        $socialforum = self::getDataGenerator()->create_module('socialforum', $record);

        // Add a discussion.
        $record->socialforum = $socialforum->id;
        $record->userid = $user1->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // Add a bunch of replies, changing the userid.
        $record = new stdClass();
        $record->discussion = $discussion->id;
        $record->userid = $user2->id;
        self::getDataGenerator()->get_plugin_generator('mod_socialforum')->create_post($record);
        $record->userid = $user3->id;
        self::getDataGenerator()->get_plugin_generator('mod_socialforum')->create_post($record);
        $record->userid = $user4->id;
        self::getDataGenerator()->get_plugin_generator('mod_socialforum')->create_post($record);

        // Check the posts were correctly created, remember, when creating a discussion a post
        // is generated as well, so we should have 4 posts, not 3.
        $this->assertEquals(4, $DB->count_records_select('socialforum_posts', 'discussion = :discussion', array('discussion' => $discussion->id)));
    }

    public function test_create_content() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a bunch of users
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        $this->setAdminUser();

        // Create course and forum.
        $course = self::getDataGenerator()->create_course();
        $socialforum = self::getDataGenerator()->create_module('socialforum', array('course' => $course));

        $generator = self::getDataGenerator()->get_plugin_generator('mod_socialforum');
        // This should create discussion.
        $post1 = $generator->create_content($socialforum);
        // This should create posts in the discussion.
        $post2 = $generator->create_content($socialforum, array('parent' => $post1->id));
        $post3 = $generator->create_content($socialforum, array('discussion' => $post1->discussion));
        // This should create posts answering another post.
        $post4 = $generator->create_content($socialforum, array('parent' => $post2->id));

        $discussionrecords = $DB->get_records('socialforum_discussions', array('socialforum' => $socialforum->id));
        $postrecords = $DB->get_records('socialforum_posts');
        $postrecords2 = $DB->get_records('socialforum_posts', array('discussion' => $post1->discussion));
        $this->assertEquals(1, count($discussionrecords));
        $this->assertEquals(4, count($postrecords));
        $this->assertEquals(4, count($postrecords2));
        $this->assertEquals($post1->id, $discussionrecords[$post1->discussion]->firstpost);
        $this->assertEquals($post1->id, $postrecords[$post2->id]->parent);
        $this->assertEquals($post1->id, $postrecords[$post3->id]->parent);
        $this->assertEquals($post2->id, $postrecords[$post4->id]->parent);
    }

}

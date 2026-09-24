<?php

/**
 *  Testing socialforum_votes class.
 * 
 * @package   mod_socialforum
 * @category  test
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/socialforum/tests/generator/lib.php');

use \mod_socialforum\socialforum_votes;

class socialforum_votes_testcase extends advanced_testcase {

    private $course;
    private $socialforum;
    private $discussion;
    private $post;
    private $duser;
    private $puser;

    public function setUp() {

        $this->resetAfterTest();

        // Setup test data.
        $this->course = $this->getDataGenerator()->create_course();
        $this->socialforum = $this->getDataGenerator()->create_module('socialforum', array('course' => $this->course->id));
        $this->duser = $this->getDataGenerator()->create_user();
        $this->puser = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $this->course->id;
        $record['socialforum'] = $this->socialforum->id;
        $record['userid'] = $this->duser->id;
        $this->discussion = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // Add a discussion post.
        $record = array();
        $record['discussion'] = $this->discussion->id;
        $record['userid'] = $this->puser->id;
        $this->discussionpost = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_post($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $this->discussion->id;
        $record['userid'] = $this->puser->id;
        $record['parent'] = $this->discussionpost->id;
        $this->post = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_post($record);
    }

    public function test_construct() {

        // Try no parameters
        try {
            new socialforum_votes();
            $this->fail();
        } catch (Exception $ex) {
            $this->assertTrue(true);
        }
        // Try with null social forum id 
        try {
            new socialforum_votes(null, $this->discussion->id, null, $this->duser->id);
            $this->fail();
        } catch (invalid_parameter_exception $ex) {
            $this->assertTrue(true);
        }
        // Try with null discussion and post
        try {
            new socialforum_votes($this->socialforum->id, null, null, $this->duser->id);
            $this->fail();
        } catch (invalid_parameter_exception $ex) {
            $this->assertTrue(true);
        }
        // Try with both discussion and post 
        try {
            new socialforum_votes($this->socialforum->id, $this->discussion->id, $this->post->id, $this->duser->id);
            $this->fail();
        } catch (invalid_parameter_exception $ex) {
            $this->assertTrue(true);
        }
        // Try with nonexistent social forum
        try {
            new socialforum_votes($this->socialforum->id + 1, $this->discussion->id, null, $this->duser->id);
            $this->fail();
        } catch (invalid_state_exception $ex) {
            $this->assertTrue(true);
        }
        // Try with nonexistent discussion
        try {
            new socialforum_votes($this->socialforum->id, $this->discussion->id + 1, null, $this->duser->id);
            $this->fail();
        } catch (invalid_state_exception $ex) {
            $this->assertTrue(true);
        }
        // Try with nonexistent post
        try {
            new socialforum_votes($this->socialforum->id, null, $this->post->id + 1, $this->duser->id);
            $this->fail();
        } catch (invalid_state_exception $ex) {
            $this->assertTrue(true);
        }

        $pvotes = new socialforum_votes($this->socialforum->id, null, $this->post->id, $this->puser->id);
        $this->assertNotNull($pvotes);
        $dvotes = new socialforum_votes($this->socialforum->id, $this->discussion->id, null, $this->duser->id);
        $this->assertNotNull($dvotes);
        $dvotes = new socialforum_votes($this->socialforum->id, $this->discussion->id, null, $this->duser->id);
        $this->assertNotNull($dvotes);
    }

    public function test_get_set_isset_discussion() {

        $dvotes = new socialforum_votes($this->socialforum->id, $this->discussion->id, null, $this->duser->id);

        // Test get with invalid field
        $dvotes->invalid;
        $this->assertDebuggingCalled();

        // Test set with invalid field
        try {
            $dvotes->invalid = 1;
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertTrue(true);
        }
        // Test set with valid fields
        try {
            $dvotes->socialforumid = $this->socialforum->id;
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertTrue(true);
        }
        // Test set with valid fields
        try {
            $dvotes->discussionid = $this->discussion->id;
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertTrue(true);
        }
        // Test set with valid fields
        try {
            $dvotes->postid = $this->post->id;
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertTrue(true);
        }
        // Test set with valid fields
        try {
            $dvotes->votes = 1;
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertTrue(true);
        }

        // Test invalid isset
        $this->assertFalse(isset($dvotes->invalidfield));

        // Test valid issets
        $this->assertTrue(isset($dvotes->socialforumid));
        $this->assertTrue(isset($dvotes->discussionid));
        $this->assertFalse(isset($dvotes->postid));
        $this->assertTrue(isset($dvotes->votes));
        $this->assertTrue(isset($dvotes->userid));

        // Test valid gets
        $this->assertEquals($dvotes->socialforumid, $this->socialforum->id);
        $this->assertEquals($dvotes->discussionid, $this->discussion->id);
        $this->assertNull($dvotes->postid);
        $this->assertEquals($dvotes->votes, 0);
        $this->assertEquals($dvotes->userid, $this->duser->id);
    }

    public function test_get_set_isset_post() {

        $pvotes = new socialforum_votes($this->socialforum->id, null, $this->post->id, $this->puser->id);

        // Test get with invalid field
        $pvotes->invalid;
        $this->assertDebuggingCalled();

        // Test set with invalid field
        try {
            $pvotes->invalid = 1;
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertTrue(true);
        }
        // Test set with valid fields
        try {
            $dvotes->socialforumid = $this->socialforum->id;
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
        // Test set with valid fields
        try {
            $dvotes->discussionid = $this->discussion->id;
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
        // Test set with valid fields
        try {
            $dvotes->postid = $this->post->id;
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
        // Test set with valid fields
        try {
            $dvotes->votes = 1;
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        // Test invalid isset
        $this->assertFalse(isset($pvotes->invalidfield));

        // Test valid issets
        $this->assertTrue(isset($pvotes->socialforumid));
        $this->assertFalse(isset($pvotes->discussionid));
        $this->assertTrue(isset($pvotes->postid));
        $this->assertTrue(isset($pvotes->votes));
        $this->assertTrue(isset($pvotes->userid));

        // Test valid gets
        $this->assertEquals($pvotes->socialforumid, $this->socialforum->id);
        $this->assertNull($pvotes->discussionid);
        $this->assertEquals($pvotes->postid, $this->post->id);
        $this->assertEquals($pvotes->votes, 0);
        $this->assertEquals($pvotes->userid, $this->puser->id);
    }

    public function test_add_votes_discussion() {

        // Create discussion creators
        $d1user = $this->getDataGenerator()->create_user();

        // Create discussion voters
        $v1user = $this->getDataGenerator()->create_user();
        $v2user = $this->getDataGenerator()->create_user();
        $v3user = $this->getDataGenerator()->create_user();
        $v4user = $this->getDataGenerator()->create_user();

        // Create discussion
        $record = array();
        $record['course'] = $this->course->id;
        $record['socialforum'] = $this->socialforum->id;
        $record['userid'] = $d1user->id;
        $d1 = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // Create discussion objects
        $d1votes = new socialforum_votes($this->socialforum->id, $d1->id, null, $d1user->id);

        // Test no parameters
        try {
            $d1votes->add_votes();
            $this->fail();
        } catch (Exception $ex) {
            $this->assertTrue(true);
        }
        // Test invalid user id
        try {
            $d1votes->add_votes(0, 1);
            $this->fail();
        } catch (invalid_parameter_exception $ex) {
            $this->assertTrue(true);
        }
        // Test same user who created discussion
        try {
            $d1votes->add_votes($d1user->id, 1);
            $this->fail();
        } catch (invalid_state_exception $ex) {
            $this->assertTrue(true);
        }
        // Test invalid votes
        try {
            $d1votes->add_votes($this->puser->id, 'x');
            $this->fail();
        } catch (invalid_parameter_exception $ex) {
            $this->assertTrue(true);
        }
        // Test zero votes
        try {
            $d1votes->add_votes($this->puser->id, 0);
            $this->fail();
        } catch (invalid_parameter_exception $ex) {
            $this->assertTrue(true);
        }

        // Computes 1 vote for discussion 1 and check events
        $sink = $this->redirectEvents();
        $this->assertEquals($d1votes->add_votes($v1user->id), 1);
        $this->assertEquals($d1votes->votes, 1);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 2);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\discussion_relevancyvoted) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\discussion_relevancyvoted', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_discussions');
        $this->assertEquals($voteevent->objectid, $d1->id);
        $this->assertEquals($voteevent->userid, $v1user->id);
        $this->assertEquals($voteevent->relateduserid, $d1user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\discussion_votedrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\discussion_votedrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_discussions');
        $this->assertEquals($voteevent->objectid, $d1->id);
        $this->assertEquals($voteevent->userid, $d1user->id);

        // Test repeated vote
        try {
            $d1votes->add_votes($v1user->id);
            $this->fail();
        } catch (invalid_state_exception $ex) {
            $this->assertTrue(true);
        }

        // Computes 1 more vote for discussion 1 and check events
        $sink = $this->redirectEvents();
        $this->assertEquals($d1votes->add_votes($v2user->id), 2);
        $this->assertEquals($d1votes->votes, 2);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 1);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\discussion_relevancyvoted) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\discussion_relevancyvoted', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_discussions');
        $this->assertEquals($voteevent->objectid, $d1->id);
        $this->assertEquals($voteevent->userid, $v2user->id);
        $this->assertEquals($voteevent->relateduserid, $d1user->id);

        // Computes 1 negative vote for discussion 1 and check events
        $sink = $this->redirectEvents();
        $this->assertEquals($d1votes->add_votes($v3user->id, -1), 1);
        $this->assertEquals($d1votes->votes, 1);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 1);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\discussion_relevancyvoted) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\discussion_relevancyvoted', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_discussions');
        $this->assertEquals($voteevent->objectid, $d1->id);
        $this->assertEquals($voteevent->userid, $v3user->id);
        $this->assertEquals($voteevent->relateduserid, $d1user->id);

        // Computes 1 more negative vote for discussion 1 and check events
        $sink = $this->redirectEvents();
        $this->assertEquals($d1votes->add_votes($v4user->id, -1), 0);
        $this->assertEquals($d1votes->votes, 0);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 2);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\discussion_relevancyvoted) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\discussion_relevancyvoted', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_discussions');
        $this->assertEquals($voteevent->objectid, $d1->id);
        $this->assertEquals($voteevent->userid, $v4user->id);
        $this->assertEquals($voteevent->relateduserid, $d1user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\discussion_votedirrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\discussion_votedirrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_discussions');
        $this->assertEquals($voteevent->objectid, $d1->id);
        $this->assertEquals($voteevent->userid, $d1user->id);
    }

    public function test_remove_votes_discussion() {

        // Create discussion voters
        $v1user = $this->getDataGenerator()->create_user();

        // Create discussion objects
        $votes = new socialforum_votes($this->socialforum->id, $this->discussion->id, null, $this->duser->id);

        // Test invalid values
        try {
            $votes->remove_votes();
            $this->fail();
        } catch (Exception $ex) {
            $this->assertTrue(true);
        }
        try {
            $votes->remove_votes('');
            $this->fail();
        } catch (invalid_parameter_exception $ex) {
            $this->assertTrue(true);
        }
        try {
            $votes->remove_votes(0);
            $this->fail();
        } catch (invalid_parameter_exception $ex) {
            $this->assertTrue(true);
        }
        try {
            $votes->remove_votes(-1);
            $this->fail();
        } catch (invalid_parameter_exception $ex) {
            $this->assertTrue(true);
        }

        // Computes 2 vote for discussion
        $this->assertEquals($votes->add_votes($this->puser->id), 1);
        $this->assertEquals($votes->add_votes($v1user->id), 2);
        $this->assertEquals($votes->votes, 2);

        // Remove 1 vote for discussion and check events
        $sink = $this->redirectEvents();
        $this->assertEquals($votes->remove_votes($this->puser->id), 1);
        $this->assertEquals($votes->votes, 1);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 1);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\discussion_relevancyvotecancelled) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\discussion_relevancyvotecancelled', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_discussions');
        $this->assertEquals($voteevent->objectid, $this->discussion->id);
        $this->assertEquals($voteevent->userid, $this->puser->id);
        $this->assertEquals($voteevent->relateduserid, $this->duser->id);

        // Test repeated remove
        $this->assertEquals($votes->remove_votes($this->puser->id), 1);
        $this->assertEquals($votes->votes, 1);
        $this->assertEquals(count($events), 1);

        // Remove 1 vote for discussion and check events
        $sink = $this->redirectEvents();
        $this->assertEquals($votes->remove_votes($v1user->id), 0);
        $this->assertEquals($votes->votes, 0);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 2);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\discussion_relevancyvotecancelled) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\discussion_relevancyvotecancelled', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_discussions');
        $this->assertEquals($voteevent->objectid, $this->discussion->id);
        $this->assertEquals($voteevent->userid, $v1user->id);
        $this->assertEquals($voteevent->relateduserid, $this->duser->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\discussion_votedirrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\discussion_votedirrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_discussions');
        $this->assertEquals($voteevent->objectid, $this->discussion->id);
        $this->assertEquals($voteevent->userid, $this->duser->id);
    }

    public function test_add_votes_post() {

        // Create post creators
        $d1user = $this->getDataGenerator()->create_user();
        $p1user = $this->getDataGenerator()->create_user();
        $p2user = $this->getDataGenerator()->create_user();

        // Create discussion
        $record = array();
        $record['course'] = $this->course->id;
        $record['socialforum'] = $this->socialforum->id;
        $record['userid'] = $d1user->id;
        $d1 = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // Create posts
        $record = array();
        $record['discussion'] = $d1->id;
        $record['userid'] = $p1user->id;
        $record['parent'] = $this->discussionpost->id;
        $p1 = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_post($record);
        $record['userid'] = $p2user->id;
        $p2 = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_post($record);

        // Create post voters
        $v1user = $this->getDataGenerator()->create_user();
        $v2user = $this->getDataGenerator()->create_user();
        $v3user = $this->getDataGenerator()->create_user();
        $v4user = $this->getDataGenerator()->create_user();
        $v5user = $this->getDataGenerator()->create_user();
        $v6user = $this->getDataGenerator()->create_user();

        // Create post objects
        $p1votes = new socialforum_votes($this->socialforum->id, null, $p1->id, $p1user->id);
        $p2votes = new socialforum_votes($this->socialforum->id, null, $p2->id, $p2user->id);

        // Test invalid values
        // No parameters
        try {
            $p1votes->add_votes();
            $this->fail();
        } catch (Exception $ex) {
            $this->assertTrue(true);
        }
        // Zero as user id
        try {
            $p1votes->add_votes(0, 1);
            $this->fail();
        } catch (invalid_parameter_exception $ex) {
            $this->assertTrue(true);
        }
        // Same voter as creator
        try {
            $p1votes->add_votes($p1user->id, 1);
            $this->fail();
        } catch (invalid_state_exception $ex) {
            $this->assertTrue(true);
        }
        // Zero numvotes
        try {
            $p1votes->add_votes($this->duser->id, 0);
            $this->fail();
        } catch (invalid_parameter_exception $ex) {
            $this->assertTrue(true);
        }
        // Invalid numvotes
        try {
            $p1votes->add_votes($this->duser->id, 'x');
            $this->fail();
        } catch (invalid_parameter_exception $ex) {
            $this->assertTrue(true);
        }

        // Computes 1 vote for post 1 and check events: vote, relevant and most
        // relevant
        $sink = $this->redirectEvents();
        $this->assertEquals($p1votes->add_votes($v1user->id), 1);
        $this->assertEquals($p1votes->votes, 1);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 3);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_relevancyvoted) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_relevancyvoted', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p1->id);
        $this->assertEquals($voteevent->userid, $v1user->id);
        $this->assertEquals($voteevent->relateduserid, $p1user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_votedrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_votedrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p1->id);
        $this->assertEquals($voteevent->userid, $p1user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_votedmostrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_votedmostrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p1->id);
        $this->assertEquals($voteevent->userid, $p1user->id);

        // Test repeated vote
        try {
            $p1votes->add_votes($v1user->id);
            $this->fail();
        } catch (invalid_state_exception $ex) {
            $this->assertTrue(true);
        }

        // Computes 1 vote for post 2 and check events: vote and relevant
        $sink = $this->redirectEvents();
        $this->assertEquals($p2votes->add_votes($v2user->id), 1);
        $this->assertEquals(1, $p2votes->votes);
        $events = $sink->get_events();
        $this->assertEquals(2, count($events));
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_relevancyvoted) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_relevancyvoted', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p2->id);
        $this->assertEquals($voteevent->userid, $v2user->id);
        $this->assertEquals($voteevent->relateduserid, $p2user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_votedrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_votedrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p2->id);
        $this->assertEquals($voteevent->userid, $p2user->id);

        // Computes 1 more vote for post 2 and check events: vote and most
        // relevant for post 2 and no longer most relevant for post 1
        $sink = $this->redirectEvents();
        $this->assertEquals($p2votes->add_votes($v3user->id), 2);
        $this->assertEquals($p2votes->votes, 2);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 3);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_relevancyvoted) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_relevancyvoted', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p2->id);
        $this->assertEquals($voteevent->userid, $v3user->id);
        $this->assertEquals($voteevent->relateduserid, $p2user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_nolongermostrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_nolongermostrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p1->id);
        $this->assertEquals($voteevent->userid, $p1user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_votedmostrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_votedmostrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p2->id);
        $this->assertEquals($voteevent->userid, $p2user->id);

        // Computes 1 negative vote for post 2 and check events: vote and no
        // longer most relevant for post 2 and most relevant for post 1
        $sink = $this->redirectEvents();
        $this->assertEquals($p2votes->add_votes($v4user->id, -1), 1);
        $this->assertEquals($p2votes->votes, 1);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 3);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_relevancyvoted) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_relevancyvoted', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p2->id);
        $this->assertEquals($voteevent->userid, $v4user->id);
        $this->assertEquals($voteevent->relateduserid, $p2user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_nolongermostrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_nolongermostrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p2->id);
        $this->assertEquals($voteevent->userid, $p2user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_votedmostrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_votedmostrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p1->id);
        $this->assertEquals($voteevent->userid, $p1user->id);

        // Computes 1 more negative vote for post 2 and check events: vote and
        // irrelevant 
        $sink = $this->redirectEvents();
        $this->assertEquals($p2votes->add_votes($v5user->id, -1), 0);
        $this->assertEquals($p2votes->votes, 0);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 2);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_relevancyvoted) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_relevancyvoted', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p2->id);
        $this->assertEquals($voteevent->userid, $v5user->id);
        $this->assertEquals($voteevent->relateduserid, $p2user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_votedirrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_votedirrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p2->id);
        $this->assertEquals($voteevent->userid, $p2user->id);

        // Computes 1 negative vote for post 1 and check events: vote, irelevant
        // and no longer most relevant 
        $sink = $this->redirectEvents();
        $this->assertEquals($p1votes->add_votes($v6user->id, -1), 0);
        $this->assertEquals($p1votes->votes, 0);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 3);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_relevancyvoted) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_relevancyvoted', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p1->id);
        $this->assertEquals($voteevent->userid, $v6user->id);
        $this->assertEquals($voteevent->relateduserid, $p1user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_votedirrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_votedirrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p1->id);
        $this->assertEquals($voteevent->userid, $p1user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_nolongermostrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_nolongermostrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p1->id);
        $this->assertEquals($voteevent->userid, $p1user->id);
    }

    public function test_remove_votes_post() {

        // Create post creators
        $p1user = $this->getDataGenerator()->create_user();
        $p2user = $this->getDataGenerator()->create_user();

        // Create posts
        $record = array();
        $record['discussion'] = $this->discussion->id;
        $record['userid'] = $p1user->id;
        $record['parent'] = $this->discussionpost->id;
        $p1 = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_post($record);
        $record['userid'] = $p2user->id;
        $p2 = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_post($record);

        // Create post voters
        $v1user = $this->getDataGenerator()->create_user();
        $v2user = $this->getDataGenerator()->create_user();
        $v3user = $this->getDataGenerator()->create_user();

        // Create post objects
        $p1votes = new socialforum_votes($this->socialforum->id, null, $p1->id, $p1user->id);
        $p2votes = new socialforum_votes($this->socialforum->id, null, $p2->id, $p2user->id);

        // Test invalid values
        try {
            $p1votes->remove_votes();
            $this->fail();
        } catch (Exception $ex) {
            $this->assertTrue(true);
        }
        try {
            $p1votes->remove_votes('');
            $this->fail();
        } catch (invalid_parameter_exception $ex) {
            $this->assertTrue(true);
        }
        try {
            $p1votes->remove_votes(0);
            $this->fail();
        } catch (invalid_parameter_exception $ex) {
            $this->assertTrue(true);
        }
        try {
            $p1votes->remove_votes(-1);
            $this->fail();
        } catch (invalid_parameter_exception $ex) {
            $this->assertTrue(true);
        }

        // Computes 1 vote for post 1 and check events: vote, relevant and
        // most relevant
        $sink = $this->redirectEvents();
        $this->assertEquals($p1votes->add_votes($v1user->id), 1);
        $this->assertEquals($p1votes->votes, 1);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 3);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_relevancyvoted) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_relevancyvoted', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p1->id);
        $this->assertEquals($voteevent->userid, $v1user->id);
        $this->assertEquals($voteevent->relateduserid, $p1user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_votedrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_votedrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p1->id);
        $this->assertEquals($voteevent->userid, $p1user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_votedmostrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_votedmostrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p1->id);
        $this->assertEquals($voteevent->userid, $p1user->id);

        // Computes 1 vote for post 2 and check events: vote and relevant
        $sink = $this->redirectEvents();
        $this->assertEquals($p2votes->add_votes($v2user->id), 1);
        $this->assertEquals($p2votes->votes, 1);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 2);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_relevancyvoted) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_relevancyvoted', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p2->id);
        $this->assertEquals($voteevent->userid, $v2user->id);
        $this->assertEquals($voteevent->relateduserid, $p2user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_votedrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_votedrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p2->id);
        $this->assertEquals($voteevent->userid, $p2user->id);

        // Computes 1 more vote for post 2 and check events: vote and most
        // relevant for post 2 and no longer most relevant for post 1
        $sink = $this->redirectEvents();
        $this->assertEquals($p2votes->add_votes($v3user->id), 2);
        $this->assertEquals($p2votes->votes, 2);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 3);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_relevancyvoted) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_relevancyvoted', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p2->id);
        $this->assertEquals($voteevent->userid, $v3user->id);
        $this->assertEquals($voteevent->relateduserid, $p2user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_nolongermostrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_nolongermostrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p1->id);
        $this->assertEquals($voteevent->userid, $p1user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_votedmostrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_votedmostrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p2->id);
        $this->assertEquals($voteevent->userid, $p2user->id);

        // Remove 1 vote for post 2 and check events: cancelled and no longer
        // most relevant for post 2 and most relevant for post 1
        $sink = $this->redirectEvents();
        $this->assertEquals($p2votes->remove_votes($v3user->id), 1);
        $this->assertEquals($p2votes->votes, 1);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 3);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_relevancyvotecancelled) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_relevancyvotecancelled', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p2->id);
        $this->assertEquals($voteevent->userid, $v3user->id);
        $this->assertEquals($voteevent->relateduserid, $p2user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_nolongermostrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_nolongermostrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p2->id);
        $this->assertEquals($voteevent->userid, $p2user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_votedmostrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_votedmostrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p1->id);
        $this->assertEquals($voteevent->userid, $p1user->id);

        // Test repeated remove
        $sink = $this->redirectEvents();
        $this->assertEquals($p2votes->remove_votes($v3user->id), 1);
        $this->assertEquals($p2votes->votes, 1);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 0);

        // Remove another vote for post 2 and check events: cancelled and 
        // irrelevant for post 2
        $sink = $this->redirectEvents();
        $this->assertEquals($p2votes->remove_votes($v2user->id), 0);
        $this->assertEquals($p2votes->votes, 0);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 2);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_relevancyvotecancelled) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_relevancyvotecancelled', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p2->id);
        $this->assertEquals($voteevent->userid, $v2user->id);
        $this->assertEquals($voteevent->relateduserid, $p2user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_votedirrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_votedirrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p2->id);
        $this->assertEquals($voteevent->userid, $p2user->id);

        // Remove vote for post 1 and check events: cancelled, irrelevant
        // and no longer most relevant for post 1
        $sink = $this->redirectEvents();
        $this->assertEquals($p1votes->remove_votes($v1user->id), 0);
        $this->assertEquals($p1votes->votes, 0);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 3);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_relevancyvotecancelled) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_relevancyvotecancelled', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p1->id);
        $this->assertEquals($voteevent->userid, $v1user->id);
        $this->assertEquals($voteevent->relateduserid, $p1user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_votedirrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_votedirrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p1->id);
        $this->assertEquals($voteevent->userid, $p1user->id);
        foreach ($events as $voteevent) {
            if ($voteevent instanceof \mod_socialforum\event\post_nolongermostrelevant) {
                break;
            }
        }
        $this->assertInstanceOf('\mod_socialforum\event\post_nolongermostrelevant', $voteevent);
        $this->assertEquals($voteevent->objecttable, 'socialforum_posts');
        $this->assertEquals($voteevent->objectid, $p1->id);
        $this->assertEquals($voteevent->userid, $p1user->id);
    }

    public function test_get_user_votes() {

        // Create creators
        $p1user = $this->getDataGenerator()->create_user();
        $d1user = $this->getDataGenerator()->create_user();

        // Create discussion
        $record = array();
        $record['course'] = $this->course->id;
        $record['socialforum'] = $this->socialforum->id;
        $record['userid'] = $d1user->id;
        $d1 = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion($record);

        // Create discussion post
        $record = array();
        $record['discussion'] = $d1->id;
        $record['userid'] = $d1user->id;
        $record['parent'] = 0;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_post($record);

        // Create post
        $record = array();
        $record['discussion'] = $d1->id;
        $record['userid'] = $p1user->id;
        $record['parent'] = $post->id;
        $p1 = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_post($record);

        // Create post voters
        $v1user = $this->getDataGenerator()->create_user();
        $v2user = $this->getDataGenerator()->create_user();
        $v3user = $this->getDataGenerator()->create_user();

        // Create post objects
        $d1votes = new socialforum_votes($this->socialforum->id, $d1->id, null, $d1user->id);
        $p1votes = new socialforum_votes($this->socialforum->id, null, $p1->id, $p1user->id);

        // Test no parameters
        try {
            $p1votes->get_user_votes();
            $this->fail();
        } catch (Exception $ex) {
            $this->assertTrue(true);
        }
        // Test invalid parameter
        try {
            $p1votes->get_user_votes('x');
            $this->fail();
        } catch (invalid_parameter_exception $ex) {
            $this->assertTrue(true);
        }

        // Test positive user votes in a discussion
        $d1votes->add_votes($v1user->id, 5);
        $this->assertEquals(5, $d1votes->get_user_votes($v1user->id));
        // Test negative user votes in a discussion
        $d1votes->add_votes($v2user->id, -5);
        $this->assertEquals(-5, $d1votes->get_user_votes($v2user->id));
        // Test user who hasn't voted in a discussion
        $this->assertEquals(0, $d1votes->get_user_votes($v3user->id));
        // Test discussion owner
        $this->assertEquals(0, $d1votes->get_user_votes($d1user->id));

        // Test positive user votes in a post
        $p1votes->add_votes($v1user->id, 5);
        $this->assertEquals(5, $p1votes->get_user_votes($v1user->id));
        // Test negative user votes in a post
        $p1votes->add_votes($v2user->id, -5);
        $this->assertEquals(-5, $p1votes->get_user_votes($v2user->id));
        // Test user who hasn't voted in a post
        $this->assertEquals(0, $p1votes->get_user_votes($v3user->id));
        // Test post owner
        $this->assertEquals(0, $p1votes->get_user_votes($p1user->id));
    }

}

<?php

/**
 * The module social forums tests
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the social forum output/email class.
 *
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class mod_socialforum_output_email_testcase extends advanced_testcase {

    /**
     * Data provider for the postdate function tests.
     */
    public function postdate_provider() {
        return array(
            'Timed discussions disabled, timestart unset' => array(
                'globalconfig' => array(
                    'socialforum_enabletimedposts' => 0,
                ),
                'socialforumconfig' => array(
                ),
                'postconfig' => array(
                    'modified' => 1000,
                ),
                'discussionconfig' => array(
                ),
                'expectation' => 1000,
            ),
            'Timed discussions disabled, timestart set and newer' => array(
                'globalconfig' => array(
                    'socialforum_enabletimedposts' => 0,
                ),
                'socialforumconfig' => array(
                ),
                'postconfig' => array(
                    'modified' => 1000,
                ),
                'discussionconfig' => array(
                    'timestart' => 2000,
                ),
                'expectation' => 1000,
            ),
            'Timed discussions disabled, timestart set but older' => array(
                'globalconfig' => array(
                    'socialforum_enabletimedposts' => 0,
                ),
                'socialforumconfig' => array(
                ),
                'postconfig' => array(
                    'modified' => 1000,
                ),
                'discussionconfig' => array(
                    'timestart' => 500,
                ),
                'expectation' => 1000,
            ),
            'Timed discussions enabled, timestart unset' => array(
                'globalconfig' => array(
                    'socialforum_enabletimedposts' => 1,
                ),
                'socialforumconfig' => array(
                ),
                'postconfig' => array(
                    'modified' => 1000,
                ),
                'discussionconfig' => array(
                ),
                'expectation' => 1000,
            ),
            'Timed discussions enabled, timestart set and newer' => array(
                'globalconfig' => array(
                    'socialforum_enabletimedposts' => 1,
                ),
                'socialforumconfig' => array(
                ),
                'postconfig' => array(
                    'modified' => 1000,
                ),
                'discussionconfig' => array(
                    'timestart' => 2000,
                ),
                'expectation' => 2000,
            ),
            'Timed discussions enabled, timestart set but older' => array(
                'globalconfig' => array(
                    'socialforum_enabletimedposts' => 1,
                ),
                'socialforumconfig' => array(
                ),
                'postconfig' => array(
                    'modified' => 1000,
                ),
                'discussionconfig' => array(
                    'timestart' => 500,
                ),
                'expectation' => 1000,
            ),
        );
    }

    /**
     * Test for the social forum email renderable postdate.
     *
     * @dataProvider postdate_provider
     *
     * @param array  $globalconfig      The configuration to set on $CFG
     * @param array  $socialforumconfig       The configuration for this social forum
     * @param array  $postconfig        The configuration for this post
     * @param array  $discussionconfig  The configuration for this discussion
     * @param string $expectation       The expected date
     */
    public function test_postdate($globalconfig, $socialforumconfig, $postconfig, $discussionconfig, $expectation) {
        global $CFG, $DB;
        $this->resetAfterTest(true);

        // Apply the global configuration.
        foreach ($globalconfig as $key => $value) {
            $CFG->$key = $value;
        }

        // Create the fixture.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $socialforum = $this->getDataGenerator()->create_module('socialforum', (object) array('course' => $course->id));
        $cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $course->id, false, MUST_EXIST);

        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Create a new discussion.
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_socialforum')->create_discussion(
                (object) array_merge($discussionconfig, array(
                    'course' => $course->id,
                    'socialforum' => $socialforum->id,
                    'userid' => $user->id,
        )));

        // Apply the discussion configuration.
        // Some settings are ignored by the generator and must be set manually.
        $discussion = $DB->get_record('socialforum_discussions', array('id' => $discussion->id));
        foreach ($discussionconfig as $key => $value) {
            $discussion->$key = $value;
        }
        $DB->update_record('socialforum_discussions', $discussion);

        // Apply the post configuration.
        // Some settings are ignored by the generator and must be set manually.
        $post = $DB->get_record('socialforum_posts', array('discussion' => $discussion->id));
        foreach ($postconfig as $key => $value) {
            $post->$key = $value;
        }
        $DB->update_record('socialforum_posts', $post);

        // Create the renderable.
        $renderable = new mod_socialforum\output\socialforum_post_email(
                $course, $cm, $socialforum, $discussion, $post, $user, $user, true
        );

        // Check the postdate matches our expectations.
        $this->assertEquals(userdate($expectation, "", \core_date::get_user_timezone($user)), $renderable->get_postdate());
    }

}

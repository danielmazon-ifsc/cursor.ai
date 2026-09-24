<?php

/**
 * Steps definitions related with the social forum activity.
 *
 * @package   mod_socialforum
 * @category  test
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode as TableNode;

/**
 * Social Forum-related steps definitions.
 *
 * @package   mod_socialforum
 * @category  test
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class behat_mod_socialforum extends behat_base {

    /**
     * Adds a topic to the social forum specified by it's name. Useful for the Announcements and blog-style social forums.
     *
     * @Given /^I add a new topic to "(?P<socialforumname_string>(?:[^"]|\\")*)" social forum with:$/
     * @param string $socialforumname
     * @param TableNode $table
     */
    public function i_add_a_new_topic_to_socialforum_with($socialforumname, TableNode $table) {
        $this->add_new_discussion($socialforumname, $table, get_string('addanewtopic', 'mod_socialforum'));
    }

    /**
     * Adds a discussion to the social forum specified by it's name with the provided table data (usually Subject and Message). The step begins from the social forum's course page.
     *
     * @Given /^I add a new discussion to "(?P<socialforumname_string>(?:[^"]|\\")*)" social forum with:$/
     * @param string $socialforumname
     * @param TableNode $table
     */
    public function i_add_a_socialforum_discussion_to_socialforum_with($socialforumname, TableNode $table) {
        $this->add_new_discussion($socialforumname, $table, get_string('addanewdiscussion', 'mod_socialforum'));
    }

    /**
     * Adds a question to the social forum specified by it's name with the provided table data (usually Subject and Message). The step begins from the social forum's course page.
     *
     * @Given /^I add a new question to "(?P<socialforumname_string>(?:[^"]|\\")*)" social forum with:$/
     * @param string $socialforumname
     * @param TableNode $table
     */
    public function i_add_a_socialforum_question_to_socialforum_with($socialforumname, TableNode $table) {
        $this->add_new_discussion($socialforumname, $table, get_string('addanewquestion', 'mod_socialforum'));
    }

    /**
     * Adds a reply to the specified post of the specified social forum. The step begins from the social forum's page or from the social forum's course page.
     *
     * @Given /^I reply "(?P<post_subject_string>(?:[^"]|\\")*)" post from "(?P<socialforumname_string>(?:[^"]|\\")*)" social forum with:$/
     * @param string $postname The subject of the post
     * @param string $socialforumname The social forum name
     * @param TableNode $table
     */
    public function i_reply_post_from_socialforum_with($postsubject, $socialforumname, TableNode $table) {

        // Navigate to social forum.
        $this->execute('behat_general::click_link', $this->escape($socialforumname));
        $this->execute('behat_general::click_link', $this->escape($postsubject));
        $this->execute('behat_general::click_link', get_string('reply', 'mod_socialforum'));

        // Fill form and post.
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $table);

        $this->execute('behat_forms::press_button', get_string('posttosocialforum', 'mod_socialforum'));
        $this->execute('behat_general::i_wait_to_be_redirected');
    }

    /**
     * Returns the steps list to add a new discussion to a social forum.
     *
     * Abstracts add a new topic and add a new discussion, as depending
     * on the social forum type the button string changes.
     *
     * @param string $socialforumname
     * @param TableNode $table
     * @param string $buttonstr
     */
    protected function add_new_discussion($socialforumname, TableNode $table, $buttonstr) {

        // Navigate to social forum.
        $this->execute('behat_general::click_link', $this->escape($socialforumname));
        $this->execute('behat_forms::press_button', $buttonstr);

        // Fill form and post.
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $table);
        $this->execute('behat_forms::press_button', get_string('posttosocialforum', 'mod_socialforum'));
        $this->execute('behat_general::i_wait_to_be_redirected');
    }

}

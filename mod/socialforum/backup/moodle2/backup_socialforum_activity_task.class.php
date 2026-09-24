<?php

/**
 * Defines backup_socialforum_activity_task class
 *
 * @package   mod_socialforum
 * @category  backup
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/socialforum/backup/moodle2/backup_socialforum_stepslib.php');
require_once($CFG->dirroot . '/mod/socialforum/backup/moodle2/backup_socialforum_settingslib.php');

/**
 * Provides the steps to perform one complete backup of the Social Forum instance
 */
class backup_socialforum_activity_task extends backup_activity_task {

    /**
     * No specific settings for this activity
     */
    protected function define_my_settings() {
        
    }

    /**
     * Defines a backup step to store the instance data in the socialforum.xml file
     */
    protected function define_my_steps() {
        $this->add_step(new backup_socialforum_activity_structure_step('socialforum structure', 'socialforum.xml'));
    }

    /**
     * Encodes URLs to the index.php, view.php and discuss.php scripts
     *
     * @param string $content some HTML text that eventually contains URLs to the activity instance scripts
     * @return string the content with the URLs encoded
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, "/");

        // Link to the list of socialforums
        $search = "/(" . $base . "\/mod\/socialforum\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@FORUMINDEX*$2@$', $content);

        // Link to socialforum view by moduleid
        $search = "/(" . $base . "\/mod\/socialforum\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@FORUMVIEWBYID*$2@$', $content);

        // Link to socialforum view by socialforumid
        $search = "/(" . $base . "\/mod\/socialforum\/view.php\?f\=)([0-9]+)/";
        $content = preg_replace($search, '$@FORUMVIEWBYF*$2@$', $content);

        // Link to socialforum discussion with parent syntax
        $search = "/(" . $base . "\/mod\/socialforum\/discuss.php\?d\=)([0-9]+)(?:\&amp;|\&)parent\=([0-9]+)/";
        $content = preg_replace($search, '$@FORUMDISCUSSIONVIEWPARENT*$2*$3@$', $content);

        // Link to socialforum discussion with relative syntax
        $search = "/(" . $base . "\/mod\/socialforum\/discuss.php\?d\=)([0-9]+)\#([0-9]+)/";
        $content = preg_replace($search, '$@FORUMDISCUSSIONVIEWINSIDE*$2*$3@$', $content);

        // Link to socialforum discussion by discussionid
        $search = "/(" . $base . "\/mod\/socialforum\/discuss.php\?d\=)([0-9]+)/";
        $content = preg_replace($search, '$@FORUMDISCUSSIONVIEW*$2@$', $content);

        return $content;
    }

}

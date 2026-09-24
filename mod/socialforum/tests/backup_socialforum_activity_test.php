<?php

/**
 * Tests for mod_socialforum_backup_forum_activity_task.
 *
 * @package    mod_socialforum
 * @category   test
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

require_once($CFG->dirroot . '/backup/moodle2/backup_stepslib.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_activity_task.class.php');
require_once($CFG->dirroot . '/mod/socialforum/backup/moodle2/backup_socialforum_activity_task.class.php');

/**
 * Tests for mod_socialforum_backup_forum_activity_task.
 *
 * @package   mod_socialforum
 * @category  test
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class mod_socialforum_backup_socialforum_activity_task_testcase extends advanced_testcase {

    /**
     * Test the encoding of forum content links.
     *
     * @param string $content       The incoming content
     * @param string $expectation   The expected result
     *
     * @dataProvider encode_content_links_provider
     */
    public function test_encode_content_links($content, $expectation) {
        $this->assertEquals($expectation, backup_socialforum_activity_task::encode_content_links($content));
    }

    public function encode_content_links_provider() {
        global $CFG;
        $altwwwroot = 'http://invalid.example.com/';
        return [
            'Link to the list of forums for current wwwroot' => [
                sprintf('%s/mod/socialforum/index.php?id=42', $CFG->wwwroot),
                '$@FORUMINDEX*42@$',
            ],
            'Link to forum view by moduleid for current wwwroot' => [
                sprintf('%s/mod/socialforum/view.php?id=29', $CFG->wwwroot),
                '$@FORUMVIEWBYID*29@$',
            ],
            'Link to forum view by forumid for current wwwroot' => [
                sprintf('%s/mod/socialforum/view.php?f=31', $CFG->wwwroot),
                '$@FORUMVIEWBYF*31@$',
            ],
            'Link to forum discussion with parent syntax for current wwwroot' => [
                sprintf('%s/mod/socialforum/discuss.php?d=26&parent=99', $CFG->wwwroot),
                '$@FORUMDISCUSSIONVIEWPARENT*26*99@$',
            ],
            'Link to forum discussion with parent syntax for current wwwroot encoded' => [
                sprintf('%s/mod/socialforum/discuss.php?d=26&amp;parent=99', $CFG->wwwroot),
                '$@FORUMDISCUSSIONVIEWPARENT*26*99@$',
            ],
            'Link to forum discussion with relative syntax for current wwwroot' => [
                sprintf('%s/mod/socialforum/discuss.php?d=1040#9930', $CFG->wwwroot),
                '$@FORUMDISCUSSIONVIEWINSIDE*1040*9930@$',
            ],
            'Link to forum discussion by discussionid for current wwwroot' => [
                sprintf('%s/mod/socialforum/discuss.php?d=9304', $CFG->wwwroot),
                '$@FORUMDISCUSSIONVIEW*9304@$',
            ],
            'Link to the list of forums for other wwwroot' => [
                sprintf('%s/mod/socialforum/index.php?id=42', $altwwwroot),
                sprintf('%s/mod/socialforum/index.php?id=42', $altwwwroot),
            ],
            'Link to forum view by moduleid for other wwwroot' => [
                sprintf('%s/mod/socialforum/view.php?id=29', $altwwwroot),
                sprintf('%s/mod/socialforum/view.php?id=29', $altwwwroot),
            ],
            'Link to forum view by forumid for other wwwroot' => [
                sprintf('%s/mod/socialforum/view.php?f=31', $altwwwroot),
                sprintf('%s/mod/socialforum/view.php?f=31', $altwwwroot),
            ],
            'Link to forum discussion with parent syntax for other wwwroot' => [
                sprintf('%s/mod/socialforum/discuss.php?d=26&parent=99', $altwwwroot),
                sprintf('%s/mod/socialforum/discuss.php?d=26&parent=99', $altwwwroot),
            ],
            'Link to forum discussion with parent syntax for other wwwroot encoded' => [
                sprintf('%s/mod/socialforum/discuss.php?d=26&amp;parent=99', $altwwwroot),
                sprintf('%s/mod/socialforum/discuss.php?d=26&amp;parent=99', $altwwwroot),
            ],
            'Link to forum discussion with relative syntax for other wwwroot' => [
                sprintf('%s/mod/socialforum/discuss.php?d=1040#9930', $altwwwroot),
                sprintf('%s/mod/socialforum/discuss.php?d=1040#9930', $altwwwroot),
            ],
            'Link to forum discussion by discussionid for other wwwroot' => [
                sprintf('%s/mod/socialforum/discuss.php?d=9304', $altwwwroot),
                sprintf('%s/mod/socialforum/discuss.php?d=9304', $altwwwroot),
            ],
        ];
    }

}

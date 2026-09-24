<?php

/**
 * @package    mod_socialforum
 * @subpackage backup-moodle2
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/socialforum/backup/moodle2/restore_socialforum_stepslib.php'); // Because it exists (must)

/**
 * socialforum restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_socialforum_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Choice only has one structure step
        $this->add_step(new restore_socialforum_activity_structure_step('socialforum_structure', 'socialforum.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('socialforum', array('intro'), 'socialforum');
        $contents[] = new restore_decode_content('socialforum_posts', array('message'), 'socialforum_post');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        // List of socialforums in course
        $rules[] = new restore_decode_rule('SOCIALFORUMINDEX', '/mod/socialforum/index.php?id=$1', 'course');
        // Social Forum by cm->id and socialforum->id
        $rules[] = new restore_decode_rule('SOCIALFORUMVIEWBYID', '/mod/socialforum/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('SOCIALFORUMVIEWBYF', '/mod/socialforum/view.php?f=$1', 'socialforum');
        // Link to socialforum discussion
        $rules[] = new restore_decode_rule('SOCIALFORUMDISCUSSIONVIEW', '/mod/socialforum/discuss.php?d=$1', 'socialforum_discussion');
        // Link to discussion with parent and with anchor posts
        $rules[] = new restore_decode_rule('SOCIALFORUMDISCUSSIONVIEWPARENT', '/mod/socialforum/discuss.php?d=$1&parent=$2', array('socialforum_discussion', 'socialforum_post'));
        $rules[] = new restore_decode_rule('SOCIALFORUMDISCUSSIONVIEWINSIDE', '/mod/socialforum/discuss.php?d=$1#$2', array('socialforum_discussion', 'socialforum_post'));

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * socialforum logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('socialforum', 'add', 'view.php?id={course_module}', '{socialforum}');
        $rules[] = new restore_log_rule('socialforum', 'update', 'view.php?id={course_module}', '{socialforum}');
        $rules[] = new restore_log_rule('socialforum', 'view', 'view.php?id={course_module}', '{socialforum}');
        $rules[] = new restore_log_rule('socialforum', 'view socialforum', 'view.php?id={course_module}', '{socialforum}');
        $rules[] = new restore_log_rule('socialforum', 'mark read', 'view.php?f={socialforum}', '{socialforum}');
        $rules[] = new restore_log_rule('socialforum', 'start tracking', 'view.php?f={socialforum}', '{socialforum}');
        $rules[] = new restore_log_rule('socialforum', 'stop tracking', 'view.php?f={socialforum}', '{socialforum}');
        $rules[] = new restore_log_rule('socialforum', 'subscribe', 'view.php?f={socialforum}', '{socialforum}');
        $rules[] = new restore_log_rule('socialforum', 'unsubscribe', 'view.php?f={socialforum}', '{socialforum}');
        $rules[] = new restore_log_rule('socialforum', 'subscriber', 'subscribers.php?id={socialforum}', '{socialforum}');
        $rules[] = new restore_log_rule('socialforum', 'subscribers', 'subscribers.php?id={socialforum}', '{socialforum}');
        $rules[] = new restore_log_rule('socialforum', 'view subscribers', 'subscribers.php?id={socialforum}', '{socialforum}');
        $rules[] = new restore_log_rule('socialforum', 'add discussion', 'discuss.php?d={socialforum_discussion}', '{socialforum_discussion}');
        $rules[] = new restore_log_rule('socialforum', 'view discussion', 'discuss.php?d={socialforum_discussion}', '{socialforum_discussion}');
        $rules[] = new restore_log_rule('socialforum', 'move discussion', 'discuss.php?d={socialforum_discussion}', '{socialforum_discussion}');
        $rules[] = new restore_log_rule('socialforum', 'delete discussi', 'view.php?id={course_module}', '{socialforum}', null, 'delete discussion');
        $rules[] = new restore_log_rule('socialforum', 'delete discussion', 'view.php?id={course_module}', '{socialforum}');
        $rules[] = new restore_log_rule('socialforum', 'add post', 'discuss.php?d={socialforum_discussion}&parent={socialforum_post}', '{socialforum_post}');
        $rules[] = new restore_log_rule('socialforum', 'update post', 'discuss.php?d={socialforum_discussion}#p{socialforum_post}&parent={socialforum_post}', '{socialforum_post}');
        $rules[] = new restore_log_rule('socialforum', 'update post', 'discuss.php?d={socialforum_discussion}&parent={socialforum_post}', '{socialforum_post}');
        $rules[] = new restore_log_rule('socialforum', 'prune post', 'discuss.php?d={socialforum_discussion}', '{socialforum_post}');
        $rules[] = new restore_log_rule('socialforum', 'delete post', 'discuss.php?d={socialforum_discussion}', '[post]');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('socialforum', 'view socialforums', 'index.php?id={course}', null);
        $rules[] = new restore_log_rule('socialforum', 'subscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('socialforum', 'unsubscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('socialforum', 'user report', 'user.php?course={course}&id={user}&mode=[mode]', '{user}');
        $rules[] = new restore_log_rule('socialforum', 'search', 'search.php?id={course}&search=[searchenc]', '[search]');

        return $rules;
    }

}

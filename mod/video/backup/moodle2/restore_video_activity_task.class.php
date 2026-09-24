<?php

/**
 * @package   mod_video
 * @category  backup
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/video/backup/moodle2/restore_video_stepslib.php'); // Because it exists (must)

/**
 * video restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_video_activity_task extends restore_activity_task {

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
        // label only has one structure step
        $this->add_step(new restore_video_activity_structure_step('video_structure', 'video.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('video', array('intro', 'content'), 'video');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('VIDEOVIEWBYID', '/mod/video/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('VIDEOINDEX', '/mod/video/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * video logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('video', 'add', 'view.php?id={course_module}', '{video}');
        $rules[] = new restore_log_rule('video', 'update', 'view.php?id={course_module}', '{video}');
        $rules[] = new restore_log_rule('video', 'view', 'view.php?id={course_module}', '{video}');

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

        $rules[] = new restore_log_rule('video', 'view all', 'index.php?id={course}', null);

        return $rules;
    }

}

<?php

/**
 * Defines backup_cquiz_activity_task class
 *
 * @package     mod_cquiz
 * @category    backup
 * @copyright   2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/cquiz/backup/moodle2/backup_cquiz_stepslib.php');

/**
 * Provides the steps to perform one complete backup of the Cquiz instance
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class backup_cquiz_activity_task extends backup_activity_task {

    /**
     * No specific settings for this activity
     */
    protected function define_my_settings() {
        
    }

    /**
     * Defines backup steps to store the instance data and required questions
     */
    protected function define_my_steps() {
        // Generate the cquiz.xml file containing all the cquiz information
        // and annotating used questions.
        $this->add_step(new backup_cquiz_activity_structure_step('cquiz_structure', 'cquiz.xml'));

        // Note: Following  steps must be present
        // in all the activities using question banks (only cquiz for now)
        // TODO: Specialise these step to a new subclass of backup_activity_task.
        // Process all the annotated questions to calculate the question
        // categories needing to be included in backup for this activity
        // plus the categories belonging to the activity context itself.
        $this->add_step(new backup_calculate_question_categories('activity_question_categories'));

        // Clean backup_temp_ids table from questions. We already
        // have used them to detect question_categories and aren't
        // needed anymore.
        $this->add_step(new backup_delete_temp_questions('clean_temp_questions'));
    }

    /**
     * Encodes URLs to the index.php and view.php scripts
     *
     * @param string $content some HTML text that eventually contains URLs to the activity instance scripts
     * @return string the content with the URLs encoded
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        // Link to the list of cquizzes.
        $search = "/(" . $base . "\/mod\/cquiz\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@CQUIZINDEX*$2@$', $content);

        // Link to cquiz view by moduleid.
        $search = "/(" . $base . "\/mod\/cquiz\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@CQUIZVIEWBYID*$2@$', $content);

        // Link to cquiz view by cquizid.
        $search = "/(" . $base . "\/mod\/cquiz\/view.php\?q\=)([0-9]+)/";
        $content = preg_replace($search, '$@CQUIZVIEWBYQ*$2@$', $content);

        return $content;
    }

}

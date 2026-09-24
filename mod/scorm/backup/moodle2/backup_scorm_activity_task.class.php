<?php

/**
 * Defines backup_scorm_activity_task class
 *
 * @package     mod_scorm
 * @category    backup
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/scorm/backup/moodle2/backup_scorm_stepslib.php');

/**
 * Provides the steps to perform one complete backup of the SCORM instance
 */
class backup_scorm_activity_task extends backup_activity_task {

    /**
     * No specific settings for this activity
     */
    protected function define_my_settings() {
        
    }

    /**
     * Defines a backup step to store the instance data in the scorm.xml file
     */
    protected function define_my_steps() {
        $this->add_step(new backup_scorm_activity_structure_step('scorm_structure', 'scorm.xml'));
    }

    /**
     * Encodes URLs to the index.php and view.php scripts
     *
     * @param string $content some HTML text that eventually contains URLs to the activity instance scripts
     * @return string the content with the URLs encoded
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, "/");

        // Link to the list of scorms
        $search = "/(" . $base . "\/mod\/scorm\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@SCORMINDEX*$2@$', $content);

        // Link to scorm view by moduleid
        $search = "/(" . $base . "\/mod\/scorm\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@SCORMVIEWBYID*$2@$', $content);

        return $content;
    }

}

<?php

/**
 * @package   mod_video
 * @category  backup
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die;

/**
 * Define all the backup steps that will be used by the backup_video_activity_task
 */

/**
 * Define the complete video structure for backup, with file and id annotations
 */
class backup_video_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $video = new backup_nested_element('video', array('id'), array(
            'name', 'intro', 'introformat', 'content', 'contentformat',
            'display', 'displayoptions', 'revision', 'timemodified',
            'socialforumid', 'thumbnailurl'));

        // Build the tree
        // (love this)
        // Define sources
        $video->set_source_table('video', array('id' => backup::VAR_ACTIVITYID));

        // Define id annotations
        // (none)
        // Define file annotations
        $video->annotate_files('mod_video', 'intro', null); // This file areas haven't itemid
        $video->annotate_files('mod_video', 'storedvideo', null); // This file areas haven't itemid
        $video->annotate_files('mod_video', 'download', null); // This file areas haven't itemid
        $video->annotate_files('mod_video', 'cardthumbnail', null); // This file areas haven't itemid
        $video->annotate_files('mod_video', 'caption', null); // This file areas haven't itemid
        // Return the root element (video), wrapped into standard activity structure
        return $this->prepare_activity_structure($video);
    }

}

<?php

/**
 * @package   mod_video
 * @category  backup
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
/**
 * Define all the restore steps that will be used by the restore_video_activity_task
 */

/**
 * Structure step to restore one video activity
 */
class restore_video_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $paths[] = new restore_path_element('video', '/activity/video');

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_video($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // insert the video record
        $newitemid = $DB->insert_record('video', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function after_execute() {
        // Add video related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_video', 'intro', null);
        $this->add_related_files('mod_video', 'storedvideo', null);
        $this->add_related_files('mod_video', 'download', null);
        $this->add_related_files('mod_video', 'cardthumbnail', null);
        $this->add_related_files('mod_video', 'caption', null);
    }

    /**
     * Hook to execute assignment upgrade after restore.
     */
    protected function after_restore() {
        global $DB;
        // Update reference to social forum
        $videoid = $this->elementsnewid['video'];
        $video = $DB->get_record('video', array('id' => $videoid));
        if ($video) {
            $newsocialforumid = $this->get_mappingid('socialforum', $video->socialforumid);
            $video->socialforumid = $newsocialforumid;
            $DB->update_record('video', $video);
        }
    }

}

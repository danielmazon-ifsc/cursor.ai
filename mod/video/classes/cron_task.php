<?php

/**
 * A scheduled task for video cron.
 *
 * @package   mod_video
 * @copyright 2023 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace mod_video;

class cron_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('crontask', 'mod_video');
    }

    /**
     * Execute video cron task
     */
    public function execute() {
        global $CFG;

        require_once($CFG->dirroot . '/mod/video/lib.php');
        if (get_config('video', 'uploadtovimeo')) {
            video_upload_videos();
        }
    }

}

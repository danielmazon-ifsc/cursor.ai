<?php

/**
 * A scheduled task for socialforum cron.
 *
 * @todo MDL-44734 This job will be split up properly.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace mod_socialforum\task;

class cron_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('crontask', 'mod_socialforum');
    }

    /**
     * Run socialforum cron.
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/mod/socialforum/lib.php');
        socialforum_cron();
    }

}

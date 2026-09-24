<?php

/**
 * A scheduled task for Coin local plugin cron.
 *
 * @package   local_coin
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace local_coin;

class cron_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('crontask', 'local_coin');
    }

    /**
     * Run coin cron.
     */
    public function execute() {
        global $CFG;

        require_once($CFG->dirroot . '/local/coin/lib.php');
        local_coin_cron($this->get_last_run_time(), $this->get_next_scheduled_time());
    }

}

<?php

/**
 * The local_coin coins awarded event.
 *
 * @package   local_coin
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace local_coin\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The local_coin coins awarded event class.
 *
 * @package    local_coin
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class user_coinsawarded extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'coin_ledger';
        $this->data['contextlevel'] = CONTEXT_USER;
        $this->data['courseid'] = null;
        $this->data['relateduserid'] = null;
        $this->data['anonymous'] = 0;
        $this->data['other'] = null;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has been awarded with coins registered in coin_ledger under id '$this->objectid'";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventcoinsawarded', 'local_coin');
    }

}

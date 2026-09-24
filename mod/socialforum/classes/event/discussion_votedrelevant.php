<?php

/**
 * The mod_socialforum discussion voted as relevant event.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace mod_socialforum\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_socialforum discussion voted as relevant event class.
 *
 * @package    mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class discussion_votedrelevant extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'socialforum_discussions';
        $this->data['contextlevel'] = CONTEXT_USER;
        $this->data['courseid'] = null;
        $this->data['anonymous'] = 0;
        $this->data['other'] = null;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id ' . $this->userid . ' voted discussion id "
                . $this->objectid . " and it has become relevant";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventdiscussionvotedrelevant', 'mod_socialforum');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/socialforum/discuss.php', array('d' => $this->objectid));
    }

}

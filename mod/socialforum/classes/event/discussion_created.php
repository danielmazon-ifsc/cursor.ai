<?php

/**
 * The mod_socialforum discussion created event.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace mod_socialforum\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_socialforum discussion created event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int socialforumid: The id of the socialforum the discussion is in.
 * }
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class discussion_created extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'socialforum_discussions';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has created the discussion with id '$this->objectid' in the socialforum " .
                "with course module id '$this->contextinstanceid'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventdiscussioncreated', 'mod_socialforum');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/socialforum/discuss.php', array('d' => $this->objectid));
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {

        // The legacy log table expects a relative path to /mod/socialforum/.
        $logurl = substr($this->get_url()->out_as_local_url(), strlen('/mod/socialforum/'));

        return array($this->courseid, 'socialforum', 'add discussion', $logurl, $this->objectid, $this->contextinstanceid);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['socialforumid'])) {
            throw new \coding_exception('The \'socialforumid\' value must be set in other.');
        }

        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'socialforum_discussions', 'restore' => 'socialforum_discussion');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['socialforumid'] = array('db' => 'socialforum', 'restore' => 'socialforum');

        return $othermapped;
    }

}

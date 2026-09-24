<?php

/**
 * The mod_socialforum discussion moved event.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace mod_socialforum\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_socialforum discussion moved event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int fromsocialforumid: The id of the socialforum the discussion is being moved from.
 *      - int tosocialforumid: The id of the socialforum the discussion is being moved to.
 * }
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class discussion_moved extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'socialforum_discussions';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has moved the discussion with id '$this->objectid' from the " .
                "socialforum with id '{$this->other['fromsocialforumid']}' to the socialforum with id '{$this->other['tosocialforumid']}'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventdiscussionmoved', 'mod_socialforum');
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
        return array($this->courseid, 'socialforum', 'move discussion', 'discuss.php?d=' . $this->objectid,
            $this->objectid, $this->contextinstanceid);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['fromsocialforumid'])) {
            throw new \coding_exception('The \'fromsocialforumid\' value must be set in other.');
        }

        if (!isset($this->other['tosocialforumid'])) {
            throw new \coding_exception('The \'tosocialforumid\' value must be set in other.');
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
        $othermapped['fromsocialforumid'] = array('db' => 'socialforum', 'restore' => 'socialforum');
        $othermapped['tosocialforumid'] = array('db' => 'socialforum', 'restore' => 'socialforum');

        return $othermapped;
    }

}

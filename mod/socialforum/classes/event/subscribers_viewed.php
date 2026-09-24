<?php

/**
 * The mod_socialforum subscribers list viewed event.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace mod_socialforum\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_socialforum subscribers list viewed event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int socialforumid: The id of the socialforum which the subscriberslist has been viewed.
 * }
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class subscribers_viewed extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has viewed the subscribers list for the socialforum with course " .
                "module id '$this->contextinstanceid'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsubscribersviewed', 'mod_socialforum');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/socialforum/subscribers.php', array('id' => $this->other['socialforumid']));
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, 'socialforum', 'view subscribers', 'subscribers.php?id=' . $this->other['socialforumid'],
            $this->other['socialforumid'], $this->contextinstanceid);
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

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['socialforumid'] = array('db' => 'socialforum', 'restore' => 'socialforum');

        return $othermapped;
    }

}

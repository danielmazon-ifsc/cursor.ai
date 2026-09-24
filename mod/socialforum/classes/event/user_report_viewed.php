<?php

/**
 * The mod_socialforum user report viewed event.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace mod_socialforum\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_socialforum user report viewed event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - string reportmode: The mode the report has been viewed in (posts or discussions).
 * }
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class user_report_viewed extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has viewed the user report for the user with id '$this->relateduserid' in " .
                "the course with id '$this->courseid' with viewing mode '{$this->other['reportmode']}'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventuserreportviewed', 'mod_socialforum');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {

        $url = new \moodle_url('/mod/socialforum/user.php', array('id' => $this->relateduserid,
            'mode' => $this->other['reportmode']));

        if ($this->courseid != SITEID) {
            $url->param('course', $this->courseid);
        }

        return $url;
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        // The legacy log table expects a relative path to /mod/socialforum/.
        $logurl = substr($this->get_url()->out_as_local_url(), strlen('/mod/socialforum/'));

        return array($this->courseid, 'socialforum', 'user report', $logurl, $this->relateduserid);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        if (!isset($this->other['reportmode'])) {
            throw new \coding_exception('The \'reportmode\' value must be set in other.');
        }

        switch ($this->contextlevel) {
            case CONTEXT_COURSE:
            case CONTEXT_SYSTEM:
            case CONTEXT_USER:
                // OK, expected context level.
                break;
            default:
                // Unexpected contextlevel.
                throw new \coding_exception('Context level must be either CONTEXT_SYSTEM, CONTEXT_COURSE or CONTEXT_USER.');
        }
    }

    public static function get_other_mapping() {
        return false;
    }

}

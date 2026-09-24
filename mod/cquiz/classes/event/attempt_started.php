<?php

/**
 * The mod_cquiz attempt started event.
 *
 * @package    mod_cquiz
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */

namespace mod_cquiz\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_cquiz attempt started event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int cquizid: (optional) the id of the cquiz.
 * }
 *
 * @package    mod_cquiz
 * @since      Moodle 2.6
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class attempt_started extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'cquiz_attempts';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->relateduserid' has started the attempt with id '$this->objectid' for the " .
                "cquiz with course module id '$this->contextinstanceid'.";
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventcquizattemptstarted', 'mod_cquiz');
    }

    /**
     * Does this event replace a legacy event?
     *
     * @return string legacy event name
     */
    static public function get_legacy_eventname() {
        return 'cquiz_attempt_started';
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/cquiz/review.php', array('attempt' => $this->objectid));
    }

    /**
     * Legacy event data if get_legacy_eventname() is not empty.
     *
     * @return \stdClass
     */
    protected function get_legacy_eventdata() {
        $attempt = $this->get_record_snapshot('cquiz_attempts', $this->objectid);

        $legacyeventdata = new \stdClass();
        $legacyeventdata->component = 'mod_cquiz';
        $legacyeventdata->attemptid = $attempt->id;
        $legacyeventdata->timestart = $attempt->timestart;
        $legacyeventdata->timestamp = $attempt->timestart;
        $legacyeventdata->userid = $this->relateduserid;
        $legacyeventdata->cquizid = $attempt->cquiz;
        $legacyeventdata->cmid = $this->contextinstanceid;
        $legacyeventdata->courseid = $this->courseid;

        return $legacyeventdata;
    }

    /**
     * Return the legacy event log data.
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        $attempt = $this->get_record_snapshot('cquiz_attempts', $this->objectid);

        return array($this->courseid, 'cquiz', 'attempt', 'review.php?attempt=' . $this->objectid,
            $attempt->cquiz, $this->contextinstanceid);
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
    }

    public static function get_objectid_mapping() {
        return array('db' => 'cquiz_attempts', 'restore' => 'cquiz_attempt');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['cquizid'] = array('db' => 'cquiz', 'restore' => 'cquiz');

        return $othermapped;
    }

}

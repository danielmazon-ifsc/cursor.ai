<?php

/**
 * The mod_cquiz attempt viewed event.
 *
 * @package    mod_cquiz
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */

namespace mod_cquiz\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_cquiz attempt viewed event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int cquizid: the id of the cquiz.
 * }
 *
 * @package    mod_cquiz
 * @since      Moodle 2.7
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class attempt_viewed extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'cquiz_attempts';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventattemptviewed', 'mod_cquiz');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has viewed the attempt with id '$this->objectid' belonging to the user " .
                "with id '$this->relateduserid' for the cquiz with course module id '$this->contextinstanceid'.";
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
     * Return the legacy event log data.
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, 'cquiz', 'continue attempt', 'review.php?attempt=' . $this->objectid,
            $this->other['cquizid'], $this->contextinstanceid);
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

        if (!isset($this->other['cquizid'])) {
            throw new \coding_exception('The \'cquizid\' value must be set in other.');
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

<?php

/**
 * The mod_scorm interactions viewed event.
 *
 * @package    mod_scorm
 */

namespace mod_scorm\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_scorm interactions viewed event class.
 *
 * @property-read array $other {
 *      Extra information about event properties.
 *
 *      - int attemptid: Attempt id.
 *      - int instanceid: Instance id of the scorm activity.
 * }
 *
 * @package    mod_scorm
 */
class interactions_viewed extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' viewed the interactions for the user with id '$this->relateduserid' " .
                "for the scorm activity with course module id '$this->contextinstanceid'.";
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventinteractionsviewed', 'mod_scorm');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        $params = array(
            'id' => $this->contextinstanceid,
            'user' => $this->relateduserid,
            'attempt' => $this->other['attemptid']
        );
        return new \moodle_url('/mod/scorm/userreportinteractions.php', $params);
    }

    /**
     * Return the legacy event log data.
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, 'scorm', 'userreportinteractions', 'report/userreportinteractions.php?id=' .
            $this->contextinstanceid . '&user=' . $this->relateduserid . '&attempt=' . $this->other['attemptid'],
            $this->other['instanceid'], $this->contextinstanceid);
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

        if (empty($this->other['attemptid'])) {
            throw new \coding_exception('The \'attemptid\' must be set in other.');
        }

        if (empty($this->other['instanceid'])) {
            throw new \coding_exception('The \'instanceid\' must be set in other.');
        }
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['instanceid'] = array('db' => 'scorm', 'restore' => 'scorm');

        return $othermapped;
    }

}

<?php

/**
 * The mod_scorm report viewed event.
 *
 * @package    mod_scorm
 */

namespace mod_scorm\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_scorm report viewed event class.
 *
 * @property-read array $other {
 *      Extra information about event properties.
 *
 *      - int scormid: The ID of the scorm.
 *      - string mode: Mode of the report viewed.
 * }
 *
 * @package    mod_scorm
 */
class report_viewed extends \core\event\base {

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
        return "The user with id '$this->userid' viewed the scorm report '{$this->other['mode']}' for the scorm with " .
                "course module id '$this->contextinstanceid'.";
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventreportviewed', 'mod_scorm');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/scorm/report.php', array('id' => $this->contextinstanceid, 'mode' => $this->other['mode']));
    }

    /**
     * Replace add_to_log() statement.
     *
     * @return array of parameters to be passed to legacy add_to_log() function.
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, 'scorm', 'report', 'report.php?id=' . $this->contextinstanceid .
            '&mode=' . $this->other['mode'], $this->other['scormid'], $this->contextinstanceid);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (empty($this->other['scormid'])) {
            throw new \coding_exception('The \'scormid\' value must be set in other.');
        }

        if (empty($this->other['mode'])) {
            throw new \coding_exception('The \'mode\' value must be set in other.');
        }
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['scormid'] = array('db' => 'scorm', 'restore' => 'scorm');

        return $othermapped;
    }

}

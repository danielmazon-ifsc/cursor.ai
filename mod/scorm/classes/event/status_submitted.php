<?php

/**
 * The mod_scorm status submitted event.
 *
 * @package    mod_scorm
 */

namespace mod_scorm\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_scorm status submitted event class.
 *
 * @property-read array $other {
 *      Extra information about event properties.
 *
 *      - int attemptid: Attempt id.
 *      - string cmielement: CMI element representing a status.
 *      - string cmivalue: CMI value.
 * }
 *
 * @package    mod_scorm
 */
class status_submitted extends cmielement_submitted {

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventstatussubmitted', 'mod_scorm');
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!in_array($this->other['cmielement'], array('cmi.completion_status', 'cmi.core.lesson_status', 'cmi.success_status'))) {
            throw new \coding_exception(
            "The 'cmielement' must represents a valid CMI status element ({$this->other['cmielement']}).");
        }

        if (!in_array($this->other['cmivalue'], array('passed', 'completed', 'failed', 'incomplete', 'browsed', 'not attempted', 'unknown'))) {
            throw new \coding_exception(
            "The 'cmivalue' must represents a valid CMI status value ({$this->other['cmivalue']}).");
        }
    }

}

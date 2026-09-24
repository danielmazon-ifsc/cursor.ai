<?php

/**
 * The mod_scorm raw score submitted event.
 *
 * @package    mod_scorm
 */

namespace mod_scorm\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_scorm raw score submitted event class.
 *
 * @property-read array $other {
 *      Extra information about event properties.
 *
 *      - int attemptid: Attempt id.
 *      - string cmielement: CMI element representing a raw score.
 *      - string cmivalue: CMI value.
 * }
 *
 * @package    mod_scorm
 */
class scoreraw_submitted extends cmielement_submitted {

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventscorerawsubmitted', 'mod_scorm');
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!strstr($this->other['cmielement'], '.score.raw')) {
            throw new \coding_exception(
            "The 'cmielement' must represents a valid CMI raw score ({$this->other['cmielement']}).");
        }

        // Note: we trust that 'cmivalue' represents a valid SCORM CMI score value.
    }

}

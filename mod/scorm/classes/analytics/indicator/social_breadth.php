<?php

/**
 * Social breadth indicator - scorm.
 *
 * @package   mod_scorm
 */

namespace mod_scorm\analytics\indicator;

defined('MOODLE_INTERNAL') || die();

/**
 * Social breadth indicator - scorm.
 *
 * @package   mod_scorm
 */
class social_breadth extends activity_base {

    /**
     * Returns the name.
     *
     * If there is a corresponding '_help' string this will be shown as well.
     *
     * @return \lang_string
     */
    public static function get_name(): \lang_string {
        return new \lang_string('indicator:socialbreadth', 'mod_scorm');
    }

    public function get_indicator_type() {
        return self::INDICATOR_SOCIAL;
    }

    public function get_social_breadth_level(\cm_info $cm) {
        return self::SOCIAL_LEVEL_2;
    }

}

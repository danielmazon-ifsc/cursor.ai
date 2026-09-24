<?php

/**
 * @package    mod_scorm
 */

namespace mod_scorm;

/* * **************************************************************** */
// Default class for Scorm plugins
//
// Doesn't do anything on it's own -- it needs to be extended.
// This class displays scorm reports. Because it is called from
// within /mod/scorm/report.php you can assume that the page header
// and footer are taken care of.
//
// This file can refer to itself as report.php to pass variables
// to itself - all these will also be globally available.
/* * **************************************************************** */

defined('MOODLE_INTERNAL') || die();

class report {

    /**
     * displays the full report
     * @param stdClass $scorm full SCORM object
     * @param stdClass $cm - full course_module object
     * @param stdClass $course - full course object
     * @param string $download - type of download being requested
     */
    public function display($scorm, $cm, $course, $download) {
        // This function just displays the report.
        return true;
    }

    /**
     * allows the plugin to control who can see this plugin.
     * @return boolean
     */
    public function canview($contextmodule) {
        return true;
    }

}

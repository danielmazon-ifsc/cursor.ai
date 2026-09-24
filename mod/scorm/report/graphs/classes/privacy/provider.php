<?php

/**
 * Privacy Subsystem implementation for scormreport_graphs.
 *
 * @package    scormreport_graphs
 */

namespace scormreport_graphs\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem for scormreport_graphs implementing null_provider.
 *
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * Get the language string identifier with the component's language
     * file to explain why this plugin stores no data.
     *
     * @return  string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }

}

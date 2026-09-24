<?php

/**
 * Privacy Subsystem implementation for scormreport_objectives.
 *
 * @package    scormreport_objectives
 */

namespace scormreport_objectives\privacy;

defined('MOODLE_INTERNAL') || die();

use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\transform;
use \core_privacy\local\request\writer;

/**
 * Privacy Subsystem for scormreport_objectives.
 *
 */
class provider implements
\core_privacy\local\metadata\provider, \core_privacy\local\request\user_preference_provider {

    /**
     * Returns meta data about this system.
     *
     * @param  collection $collection The initialised item collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        // User preferences shared between different scorm reports.
        $collection->add_user_preference('scorm_report_pagesize', 'privacy:metadata:preference:scorm_report_pagesize');

        // User preferences specific for this scorm report.
        $collection->add_user_preference(
                'scorm_report_objectives_score', 'privacy:metadata:preference:scorm_report_objectives_score'
        );

        return $collection;
    }

    /**
     * Store all user preferences for the plugin.
     *
     * @param  int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        static::get_and_export_user_preference($userid, 'scorm_report_pagesize');
        static::get_and_export_user_preference($userid, 'scorm_report_objectives_score', true);
    }

    /**
     * Get and export a user preference.
     *
     * @param  int     $userid The userid of the user whose data is to be exported.
     * @param  string  $userpreference The user preference to export.
     * @param  boolean $transform If true, transform value to yesno.
     */
    protected static function get_and_export_user_preference(int $userid, string $userpreference, $transform = false) {
        $prefvalue = get_user_preferences($userpreference, null, $userid);
        if ($prefvalue !== null) {
            if ($transform) {
                $transformedvalue = transform::yesno($prefvalue);
            } else {
                $transformedvalue = $prefvalue;
            }
            writer::export_user_preference(
                    'scormreport_objectives', $userpreference, $transformedvalue, get_string('privacy:metadata:preference:' . $userpreference, 'scormreport_objectives')
            );
        }
    }

}

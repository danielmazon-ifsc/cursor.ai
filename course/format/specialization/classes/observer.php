<?php

/**
 * Format specialization observers
 *
 * @package   format_specialization
 * @copyright 2019 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace format_specialization;

defined('MOODLE_INTERNAL') || die();

/**
 * Format specialization observer class.
 *
 * @package   format_specialization
 * @copyright 2019 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class observer {

    /**
     * Make sure the format_specialization row exists whenever a course in this
     * format is created or its format is changed. The row used to be created
     * inside the format class constructor, which had side effects every time
     * course_get_format() ran (REST, web services, navigation...). Moving the
     * write into explicit observers keeps reads cheap.
     *
     * @param \core\event\base $event
     */
    public static function course_created($event) {
        self::ensure_for_course((int) $event->courseid);
    }

    /**
     * Same idea as course_created but also covers manual format changes.
     *
     * @param \core\event\base $event
     */
    public static function course_updated($event) {
        self::ensure_for_course((int) $event->courseid);
    }

    /**
     * Process configured events and change user's stack accordingly
     *
     * @param \core\event\base $event The event.
     */
    public static function course_deleted($event) {
        // Only touch the DB if the deleted course actually belonged to the
        // specialization format; otherwise every site-wide bulk delete pays
        // an extra round-trip per course.
        if (\format_specialization::is_specialization((int) $event->courseid)) {
            \format_specialization::delete_specialization((int) $event->courseid);
        }
    }

    /**
     * Create the format_specialization row if (and only if) the course is in
     * the specialization format.
     */
    private static function ensure_for_course(int $courseid) {
        global $DB;
        $format = $DB->get_field('course', 'format', ['id' => $courseid]);
        if ($format === 'specialization') {
            \format_specialization::create_specialization($courseid);
        }
    }

}

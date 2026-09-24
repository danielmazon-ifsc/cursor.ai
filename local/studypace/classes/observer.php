<?php

namespace local_studypace;

defined('MOODLE_INTERNAL') || die();

use local_studypace\studypace;

class observer {

    /**
     * Process user enrolment events
     * 
     * @param \core\event\base $event The event.
     */
    public static function process_user_enrolment(\core\event\base $event, $quiet = false) {
        studypace::process_user_enrolment($event);
    }

    /**
     * Process course module completion events
     * 
     * @param \core\event\base $event The event.
     */
    public static function process_course_module_completion(\core\event\base $event, $quiet = false) {
        studypace::process_course_module_completion($event);
    }

    /**
     * Drop every pace row that belonged to the deleted course, otherwise
     * dashboard queries keep aggregating phantom courses for years.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event) {
        global $DB;
        $courseid = (int) $event->courseid;
        // target='course' + targetid=courseid covers the per-course paces.
        // target='cm' rows are not directly keyed by courseid, so we wipe
        // them via the studypace.courseid column, which always holds the
        // owning course for cm-level rows.
        $DB->delete_records('local_studypace', ['courseid' => $courseid]);
    }

    /**
     * Drop the pace row tied to a deleted course module. cm.id is recycled
     * by Moodle, so leaving the row in place would cause a future activity to
     * inherit a stale estimatedcompletion.
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event) {
        global $DB;
        $cmid = (int) $event->contextinstanceid;
        if ($cmid <= 0) {
            return;
        }
        $DB->delete_records('local_studypace', ['target' => 'cm', 'targetid' => $cmid]);
    }

    /**
     * Drop every pace row owned by the deleted user. Otherwise the ranking
     * sums in local_dashboard include estimatedcompletion timestamps for
     * accounts that no longer exist (they JOIN users via INNER JOIN, but
     * orphaned rows still bloat the table and skew COUNT(*) audits).
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event) {
        global $DB;
        $userid = (int) $event->objectid;
        if ($userid <= 0) {
            return;
        }
        $DB->delete_records('local_studypace', ['userid' => $userid]);
    }

    /**
     * If the course's format moves out of the tracked set, the existing
     * paces for that course must be cleared - leaving them would let the
     * ranking and dashboard keep counting an untracked course as
     * contributing. Conversely we don't backfill here because there's no
     * guarantee anyone is enrolled yet; the next enrolment event will pick
     * up the new format. Tracked formats and the migration are owned by the
     * studypace::TRACKED_FORMATS constant.
     *
     * @param \core\event\course_updated $event
     */
    public static function course_updated(\core\event\course_updated $event) {
        global $DB;
        $courseid = (int) $event->courseid;
        if ($courseid <= 0) {
            return;
        }
        $format = $DB->get_field('course', 'format', ['id' => $courseid]);
        if (!$format) {
            return;
        }
        if (!studypace::is_format_tracked($format)) {
            $DB->delete_records('local_studypace', ['courseid' => $courseid]);
        }
    }

    /**
     * Seed pace rows when a new monitorable activity appears in a tracked course.
     *
     * @param \core\event\course_module_created $event
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        $courseid = (int) $event->courseid;
        $cmid = (int) $event->contextinstanceid;
        if ($cmid <= 0 || $courseid <= 0) {
            return;
        }
        if (!studypace::is_tracked_course($courseid)) {
            return;
        }
        if (!studypace::is_monitorable_cm($cmid)) {
            return;
        }
        studypace::sync_course_enrolments($courseid);
    }

}

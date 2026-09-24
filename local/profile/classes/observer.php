<?php

/**
 * Local profile event observers.
 *
 * Listens to the \local_studypace\event\course_completed signal so users are
 * advanced to the next Eixo in the progression as soon as they finish the
 * current one — restoring the "auto-enrol in next" behaviour that existed
 * before 2025060401.
 *
 * @package   local_profile
 */

namespace local_profile;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * Auto-progression hook: a tracked course was completed by the user.
     *
     * When that course is part of the Eixo progression, enrol the user in
     * the next Eixo using the manual enrolment plugin. The helper itself
     * absorbs all the safety checks (site admin, already enrolled, no
     * next Eixo, completed course wasn't an Eixo at all, etc.) so the
     * observer stays narrow.
     *
     * Failures are swallowed and logged via error_log: a delegated event
     * observer that throws will abort the originating transaction
     * (course-module completion), which we never want — the user finishing
     * an activity must not regress because progression enrolment had a
     * transient hiccup.
     *
     * @param \core\event\base $event The fired event.
     */
    public static function course_completed(\core\event\base $event) {
        global $CFG;
        require_once($CFG->dirroot . '/local/profile/lib.php');

        $userid = (int) $event->relateduserid;
        $courseid = (int) $event->courseid;
        if ($userid <= 0 || $courseid <= 0) {
            return;
        }
        try {
            local_profile_enrol_in_next_eixo($userid, $courseid);
        } catch (\Throwable $e) {
            // debugging() routes through Moodle's standard logging instead
            // of going straight to error_log() (which lands in the PHP/Apache
            // log and is invisible to admins via the Moodle UI). The
            // DEBUG_NORMAL level surfaces it under "Site administration ->
            // Development -> Debug messages" without spamming logs in
            // production where debugging is off.
            debugging('local_profile observer::course_completed failed for user '
                    . $userid . ' / course ' . $courseid . ': ' . $e->getMessage(),
                    DEBUG_NORMAL);
        }
    }
}

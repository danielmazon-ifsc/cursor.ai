<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Event observers for local_salagrade.
 *
 * @package   local_salagrade
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_salagrade;

/**
 * Award the Sala grade when video completion changes.
 *
 * Deliberately does not listen to \local_studypace\event\course_completed:
 * that event fires inside mark_course_completed() before the grade is read
 * for planned_completion vs planned_completion_high_grade. Writing 100
 * there would award high-grade medals/keys in the Sala.
 *
 * @package   local_salagrade
 */
class observer {
    /**
     * After a video (or any CM) completion change in a Sala course.
     *
     * Runs after local_studypace on the same event (see db/events.php priority).
     *
     * @param \core\event\course_module_completion_updated $event
     */
    public static function course_module_completion_updated(
        \core\event\course_module_completion_updated $event
    ): void {
        $courseid = (int) $event->courseid;
        $userid = (int) $event->relateduserid;
        if ($courseid <= 0 || $userid <= 0) {
            return;
        }
        if (!grader::is_sala_course($courseid)) {
            return;
        }
        try {
            grader::sync_user($userid, $courseid);
        } catch (\Throwable $e) {
            // Never block activity completion because the gradebook write failed.
            debugging('local_salagrade: observer failed user ' . $userid . ' course ' . $courseid
                . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

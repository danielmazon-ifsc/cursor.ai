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
 * Adhoc task: retry auto-enrolment into the next Eixo after a transient
 * failure of the live course_completed observer.
 *
 * @package   local_profile
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_profile\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Bounded, idempotent retry of local_profile_enrol_in_next_eixo().
 *
 * Queued by local_profile_queue_next_eixo_retry() only when a genuine enrol
 * failure occurred for one specific user/completed-Eixo pair. It re-runs the
 * same idempotent helper, which itself re-queues the next attempt (up to the
 * retry cap) if the failure persists.
 */
class enrol_next_eixo extends \core\task\adhoc_task {

    public function get_name() {
        return get_string('task_enrol_next_eixo', 'local_profile');
    }

    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/local/profile/lib.php');

        $data = (array) $this->get_custom_data();
        $userid = (int) ($data['userid'] ?? 0);
        $completedeixoid = (int) ($data['completedeixoid'] ?? 0);
        $attempt = (int) ($data['attempt'] ?? 0);

        if ($userid <= 0 || $completedeixoid <= 0) {
            return;
        }

        // Swallow throwables: letting this adhoc task throw would make Moodle
        // reschedule the SAME task (same custom data / attempt) on its own
        // fail-delay, producing a retry loop OUTSIDE our bounded attempt cap.
        // Our retry budget is owned exclusively by local_profile_enrol_in_next_eixo().
        try {
            $created = local_profile_enrol_in_next_eixo($userid, $completedeixoid, $attempt);
            mtrace('local_profile: enrol_next_eixo retry (attempt ' . $attempt . ') for user '
                    . $userid . ' after course ' . $completedeixoid . ' — '
                    . ($created ? 'enrolled.' : 'no-op or re-queued.'));
        } catch (\Throwable $e) {
            debugging('local_profile enrol_next_eixo retry (attempt ' . $attempt . ') failed for user '
                    . $userid . ' / course ' . $completedeixoid . ': ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }
}

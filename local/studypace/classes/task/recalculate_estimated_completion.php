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
 * Adhoc task: recalculate every tracked user_enrolment's estimatedcompletion.
 *
 * Queued from db/upgrade.php so the heavy recalculation does NOT run inside
 * the synchronous upgrade flow (which used to time out before the savepoint
 * was reached, leaving the upgrade in a permanent loop).
 *
 * @package   local_studypace
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studypace\task;

defined('MOODLE_INTERNAL') || die();

use local_studypace\studypace;

class recalculate_estimated_completion extends \core\task\adhoc_task {

    public function get_name() {
        return get_string('task_recalculate_estimated_completion', 'local_studypace');
    }

    /**
     * Whether a site-wide full-pipeline recalculation is already queued.
     *
     * Lets the admin "Recalcular todos" page avoid stacking duplicate
     * heavy tasks when clicked repeatedly before cron drains the queue.
     *
     * @return bool
     */
    public static function has_pending_full_recalc(): bool {
        $tasks = \core\task\manager::get_adhoc_tasks('\\local_studypace\\task\\recalculate_estimated_completion');
        foreach ($tasks as $task) {
            $data = $task->get_custom_data();
            if (!empty($data->fullpipeline) && empty($data->userid)) {
                return true;
            }
        }
        return false;
    }

    public function execute() {
        global $CFG;

        // Cron already removes most resource limits, but make sure here too:
        // recalculating every enrolment on a large site can take a long time.
        @set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);

        $data = $this->get_custom_data();
        $userid = isset($data->userid) ? (int) $data->userid : 0;
        // When true, run the same repair pipeline as the admin "Recalcular
        // todos" page (seed paces + purge stale cm rows + sync completions +
        // mark finished courses + backfill Eixo progression). The
        // upgrade-queued tasks leave this false so they only refresh
        // estimatedcompletion, preserving their historical behaviour.
        $fullpipeline = !empty($data->fullpipeline);

        if ($userid > 0) {
            mtrace('local_studypace: recalculating estimatedcompletion for user ' . $userid
                . ' from their current study plan...');
            $pacestats = studypace::update_all_user_enrolments($userid);
            mtrace('  ... plan months=' . (int) $pacestats->months
                . ', enrolments=' . (int) $pacestats->processed
                . ', course deadlines updated=' . (int) $pacestats->course_updated
                . ', cm deadlines updated=' . (int) $pacestats->cm_updated);
        } else {
            mtrace('local_studypace: recalculating estimatedcompletion for every tracked enrolment'
                . ' from each user\'s study plan...');
            $pacestats = studypace::update_all_user_enrolments();
            mtrace('  ... enrolments=' . (int) $pacestats->processed
                . ', course deadlines updated=' . (int) $pacestats->course_updated
                . ', cm deadlines updated=' . (int) $pacestats->cm_updated);
        }

        if ($fullpipeline) {
            mtrace('local_studypace: running full recalculation pipeline...');
            $purged = studypace::purge_nonmandatory_cm_rows($userid);
            mtrace('  ... purged ' . $purged . ' non-mandatory cm pace row(s)');
            $synced = studypace::sync_cm_completions($userid);
            mtrace('  ... synced ' . $synced . ' cm completion(s) into pace rows');
            $revalid = studypace::revalidate_stale_course_completions(false, $userid);
            mtrace('  ... cleared ' . (int) ($revalid->cleared ?? 0) . ' stale course completion(s)');
            $marked = studypace::repair_pending_course_completions($userid);
            mtrace('  ... marked ' . $marked . ' course(s) completed');
            require_once($CFG->dirroot . '/local/profile/lib.php');
            $backfilled = local_profile_backfill_eixo_progression($userid);
            mtrace('  ... backfilled ' . $backfilled . ' Eixo progression enrolment(s)');
        }

        mtrace('local_studypace: recalculation finished.');
    }
}

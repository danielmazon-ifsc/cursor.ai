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
 * Scheduled task: heal missing gamification rewards on recent completions.
 *
 * @package   local_studypace
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studypace\task;

defined('MOODLE_INTERNAL') || die();

use local_studypace\gamification_gaps;

class heal_gamification_rewards extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_heal_gamification_rewards', 'local_studypace');
    }

    public function execute() {
        // Surface a crash from the PREVIOUS run. heal_recent_missing_rewards()
        // persists the user/course pair it is about to process and (when
        // possible) the last PHP fatal. On a hard crash (segfault / OOM / time
        // limit) the task dies before Moodle can write the failure to the log,
        // so the only visible part is this header region — which is exactly
        // where we print the beacon. This lets sites with no CLI/server-log
        // access pinpoint the offending record and the cause.
        $beacon = get_config('local_studypace', 'heal_beacon');
        if (!empty($beacon)) {
            $lasterror = get_config('local_studypace', 'heal_lasterror');
            mtrace('local_studypace: heal_gamification_rewards — PREVIOUS run crashed while '
                . 'processing pair (userid:courseid) ' . $beacon . '.');
            if (!empty($lasterror)) {
                mtrace('local_studypace: last PHP fatal captured: ' . $lasterror);
            } else {
                mtrace('local_studypace: no PHP error captured for that pair — likely a native '
                    . 'crash (segfault) in an extension (GD/openssl/pgsql) during badge issue.');
            }
            // Clear so a clean run won't keep reporting the same stale crash.
            unset_config('heal_beacon', 'local_studypace');
            unset_config('heal_lasterror', 'local_studypace');
        }

        if (!gamification_gaps::triggers_configured()) {
            mtrace('local_studypace: heal_gamification_rewards — coin triggers incomplete; badge heal still runs.');
        }

        // Bounded run: at most 300 heals or 120s per hourly execution so the
        // task can never run for hours and overlap with the next cron.
        $started = microtime(true);
        $healed = gamification_gaps::heal_recent_missing_rewards(14, 300, 120);
        $elapsed = round(microtime(true) - $started, 1);
        mtrace('local_studypace: heal_gamification_rewards processed ' . $healed
            . ' gap row(s) in ' . $elapsed . 's.');
    }
}

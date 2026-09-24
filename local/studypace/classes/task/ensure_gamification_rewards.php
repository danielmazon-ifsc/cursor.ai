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
 * Adhoc task: re-run gamification ensure after gradebook / observers settle.
 *
 * @package   local_studypace
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studypace\task;

defined('MOODLE_INTERNAL') || die();

use local_studypace\gamification_gaps;

class ensure_gamification_rewards extends \core\task\adhoc_task {

    public function get_name() {
        return get_string('task_ensure_gamification_rewards', 'local_studypace');
    }

    public function execute() {
        $data = $this->get_custom_data();
        $userid = isset($data->userid) ? (int) $data->userid : 0;
        $courseid = isset($data->courseid) ? (int) $data->courseid : 0;
        if ($userid <= 0 || $courseid <= 0) {
            mtrace('local_studypace: ensure_gamification_rewards — missing userid/courseid.');
            return;
        }

        try {
            gamification_gaps::ensure_course_rewards($userid, $courseid, true);
        } catch (\Throwable $e) {
            mtrace('local_studypace: ensure_gamification_rewards failed for user '
                . $userid . ' course ' . $courseid . ': ' . $e->getMessage());
        }
    }
}

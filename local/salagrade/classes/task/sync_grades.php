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
 * Daily scheduled sync of Sala completion grades.
 *
 * @package   local_salagrade
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_salagrade\task;

use local_salagrade\grader;

/**
 * Scheduled backfill / catch-up of Sala grades.
 *
 * @package   local_salagrade
 */
class sync_grades extends \core\task\scheduled_task {
    /**
     * Task name shown in the scheduled-task admin UI.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_sync_grades', 'local_salagrade');
    }

    /**
     * Run the sync.
     */
    public function execute() {
        @set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);
        mtrace('local_salagrade: syncing Sala completion grades...');
        $result = grader::sync_all();
        if (!empty($result->locked)) {
            mtrace('  skipped: another sync already holds the lock');
            return;
        }
        mtrace('  courses=' . $result->courses
            . ' checked=' . $result->userschecked
            . ' awarded=' . $result->awarded
            . ' skipped=' . $result->skipped
            . ' errors=' . $result->errors);
    }
}

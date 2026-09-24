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
 * Adhoc full sync queued from the admin page.
 *
 * @package   local_salagrade
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_salagrade\task;

use local_salagrade\grader;

/**
 * One-shot full Sala grade sync.
 *
 * @package   local_salagrade
 */
class sync_grades_adhoc extends \core\task\adhoc_task {
    /**
     * Task name shown in the adhoc-task admin UI.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_sync_grades_adhoc', 'local_salagrade');
    }

    /**
     * Whether a full adhoc sync is already waiting.
     *
     * @return bool
     */
    public static function has_pending(): bool {
        $tasks = \core\task\manager::get_adhoc_tasks('\\local_salagrade\\task\\sync_grades_adhoc');
        return !empty($tasks);
    }

    /**
     * Run the sync.
     */
    public function execute() {
        @set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);
        $result = grader::sync_all();
        if (!empty($result->locked)) {
            throw new \moodle_exception('syncalreadyqueued', 'local_salagrade');
        }
        mtrace('local_salagrade: adhoc sync of Sala completion grades...');
        mtrace('  courses=' . $result->courses
            . ' checked=' . $result->userschecked
            . ' awarded=' . $result->awarded
            . ' skipped=' . $result->skipped
            . ' errors=' . $result->errors);
    }
}

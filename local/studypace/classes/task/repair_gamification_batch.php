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
 * Adhoc task: repair missing keys and/or badges in batches.
 *
 * @package   local_studypace
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studypace\task;

defined('MOODLE_INTERNAL') || die();

use local_studypace\gamification_gaps;

class repair_gamification_batch extends \core\task\adhoc_task {

    public function get_name() {
        return get_string('task_repair_gamification_batch', 'local_studypace');
    }

    public function execute() {
        @set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);

        $data = $this->get_custom_data();
        if (empty($data->userids) || !is_array($data->userids)) {
            mtrace('local_studypace: repair_gamification_batch — no userids in task data.');
            return;
        }

        $offset = isset($data->offset) ? (int) $data->offset : 0;
        $batchsize = isset($data->batchsize) ? (int) $data->batchsize : 50;
        $batchsize = max(10, min(200, $batchsize));
        $total = count($data->userids);
        $slice = array_slice($data->userids, $offset, $batchsize);

        if (empty($slice)) {
            mtrace('local_studypace: repair_gamification_batch — nothing left to process.');
            return;
        }

        $options = (object) [
            'repairwhat' => $data->repairwhat ?? gamification_gaps::REPAIR_BOTH,
            'dryrun' => !empty($data->dryrun),
            'quiet' => empty($data->notify),
            'recalcstudypace' => !empty($data->recalcstudypace),
        ];

        $mode = $options->dryrun ? 'DRY-RUN' : 'LIVE';
        mtrace('local_studypace: repair_gamification_batch [' . $mode . '] users '
            . ($offset + 1) . '–' . ($offset + count($slice)) . ' of ' . $total
            . ' (scope: ' . $options->repairwhat . ')');

        $coinstotal = 0;
        $badgestotal = 0;
        $errors = 0;

        foreach ($slice as $userid) {
            $userid = (int) $userid;
            if ($userid <= 0) {
                continue;
            }
            try {
                $result = gamification_gaps::repair_user($userid, $options);
                $coinstotal += (int) ($result->coins->credited ?? 0);
                $badgestotal += (int) ($result->badges->awarded ?? 0);
                $errors += count($result->coins->errors ?? []) + count($result->badges->errors ?? []);
            } catch (\Throwable $e) {
                $errors++;
                mtrace('  user ' . $userid . ' FAILED: ' . $e->getMessage());
            }
        }

        mtrace('local_studypace: batch done — keys/badges credited or simulated: '
            . $coinstotal . '/' . $badgestotal . ', errors: ' . $errors);

        $nextoffset = $offset + count($slice);
        if ($nextoffset < $total) {
            $data->offset = $nextoffset;
            $followup = new self();
            $followup->set_custom_data($data);
            \core\task\manager::queue_adhoc_task($followup);
            mtrace('local_studypace: queued next batch at offset ' . $nextoffset);
        } else {
            mtrace('local_studypace: all batches complete.');
        }
    }
}

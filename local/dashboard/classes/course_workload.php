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
 * Stores and reads per-course workload hours for dashboard progress weighting.
 *
 * @package   local_dashboard
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dashboard;

/**
 * Workload hours per tracked course.
 *
 * @package   local_dashboard
 */
class course_workload {
    /**
     * Whether the workload table is available (plugin installed/upgraded).
     *
     * @return bool
     */
    public static function is_available(): bool {
        global $DB;
        static $available = null;
        if ($available === null) {
            $available = $DB->get_manager()->table_exists('local_dashboard_workload');
        }
        return $available;
    }

    /**
     * Tracked courses grouped by format for the admin form.
     *
     * @return array{specialization: \stdClass[], saladeconferencias: \stdClass[]}
     */
    public static function get_tracked_courses_grouped(): array {
        global $DB;

        $groups = [
            'specialization' => [],
            'saladeconferencias' => [],
        ];

        $records = $DB->get_records_sql(
            "SELECT id, fullname, shortname, visible, format
               FROM {course}
              WHERE format IN ('specialization', 'saladeconferencias')
           ORDER BY format ASC, sortorder ASC, shortname ASC"
        );

        foreach ($records as $course) {
            if (isset($groups[$course->format])) {
                $groups[$course->format][] = $course;
            }
        }

        return $groups;
    }

    /**
     * IDs of every tracked specialization / sala course.
     *
     * @return array<int,true>
     */
    public static function get_tracked_course_id_map(): array {
        $allowed = [];
        foreach (self::get_tracked_courses_grouped() as $courses) {
            foreach ($courses as $course) {
                $allowed[(int) $course->id] = true;
            }
        }
        return $allowed;
    }

    /**
     * Workload hours indexed by course id.
     *
     * @return array<int,float>
     */
    public static function get_all(): array {
        global $DB;

        if (!self::is_available()) {
            return [];
        }

        $workloads = [];
        $records = $DB->get_records('local_dashboard_workload');
        foreach ($records as $record) {
            $workloads[(int) $record->courseid] = (float) $record->workloadhours;
        }
        return $workloads;
    }

    /**
     * Persist workload hours for the given courses.
     *
     * Zero or negative hours delete the stored row. Only specialization and
     * saladeconferencias course ids are accepted.
     *
     * @param array<int,float|int|string|null> $workloads Courseid => hours.
     */
    public static function save_all(array $workloads): void {
        global $DB;

        if (!self::is_available()) {
            return;
        }

        $allowed = self::get_tracked_course_id_map();
        $now = time();
        $existing = $DB->get_records('local_dashboard_workload', null, '', 'courseid, id');

        foreach ($workloads as $courseid => $hours) {
            $courseid = (int) $courseid;
            if ($courseid <= 0 || !isset($allowed[$courseid])) {
                continue;
            }
            $hours = is_numeric($hours) ? (float) $hours : 0.0;

            if ($hours <= 0) {
                if (isset($existing[$courseid])) {
                    $DB->delete_records('local_dashboard_workload', ['id' => $existing[$courseid]->id]);
                }
                continue;
            }

            $record = (object) [
                'courseid' => $courseid,
                'workloadhours' => $hours,
                'timemodified' => $now,
            ];

            if (isset($existing[$courseid])) {
                $record->id = $existing[$courseid]->id;
                $DB->update_record('local_dashboard_workload', $record);
            } else {
                $DB->insert_record('local_dashboard_workload', $record);
            }
        }
    }

    /**
     * Progress weight (0.0–1.0) per course id based on configured workload hours.
     *
     * Returns an empty array when no positive workloads exist for the given
     * courses (caller should fall back to the legacy 90/10 split).
     *
     * @param int[] $courseids
     * @return array<int,float>
     */
    public static function get_progress_weights(array $courseids): array {
        global $DB;

        if (empty($courseids) || !self::is_available()) {
            return [];
        }

        $courseids = array_map('intval', $courseids);
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cw');
        $records = $DB->get_records_sql(
            "SELECT courseid, workloadhours
               FROM {local_dashboard_workload}
              WHERE courseid {$insql}
                AND workloadhours > 0",
            $params
        );

        if (empty($records)) {
            return [];
        }

        $total = 0.0;
        foreach ($records as $record) {
            $total += (float) $record->workloadhours;
        }
        if ($total <= 0) {
            return [];
        }

        $weights = [];
        foreach ($courseids as $courseid) {
            $weights[$courseid] = 0.0;
        }
        foreach ($records as $record) {
            $weights[(int) $record->courseid] = (float) $record->workloadhours / $total;
        }

        return $weights;
    }
}

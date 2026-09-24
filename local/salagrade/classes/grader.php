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
 * Assigns grade 100 when all required Sala videos are complete.
 *
 * @package   local_salagrade
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_salagrade;

/**
 * Gradebook writer for Sala de Conferências video completion.
 *
 * @package   local_salagrade
 */
class grader {
    /** @var string Grade item idnumber (stable across language changes). */
    const ITEM_IDNUMBER = 'local_salagrade';

    /** @var float Grade awarded when every required video is complete. */
    const GRADE_COMPLETE = 100.0;

    /** @var array<int,bool> Request cache: courseid => is Sala format. */
    private static $salacache = [];

    /** @var array<int,\grade_item|null> Request cache of grade items. */
    private static $itemcache = [];

    /** @var array<int,int[]> Request cache of required visible video cm ids. */
    private static $requiredcmcache = [];

    /**
     * Award 100 if this user finished every required visible video in a Sala course.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool True when a grade was written (or already 100).
     */
    public static function sync_user(int $userid, int $courseid): bool {
        if ($userid <= 0 || $courseid <= 0) {
            return false;
        }
        if (!self::is_sala_course($courseid)) {
            return false;
        }
        $item = self::ensure_grade_item($courseid);
        if ($item && self::user_already_has_complete_grade($item, $userid)) {
            return true;
        }
        if (!self::has_completed_all_required_videos($userid, $courseid)) {
            return false;
        }
        return self::award_complete_grade($userid, $courseid);
    }

    /**
     * Sync every active enrolment in every Sala de Conferências course.
     *
     * @return \stdClass {courses, userschecked, awarded, skipped, errors}
     */
    public static function sync_all(): \stdClass {
        $result = (object) [
            'courses' => 0,
            'userschecked' => 0,
            'awarded' => 0,
            'skipped' => 0,
            'errors' => 0,
            'locked' => false,
        ];

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_salagrade');
        $lock = $lockfactory->get_lock('sync_all', 0);
        if (!$lock) {
            debugging('local_salagrade: sync_all skipped, another run holds the lock', DEBUG_DEVELOPER);
            $result->errors = 1;
            $result->locked = true;
            return $result;
        }

        try {
            $courseids = self::get_sala_course_ids();
            $result->courses = count($courseids);
            foreach ($courseids as $courseid) {
                $one = self::sync_course($courseid);
                $result->userschecked += $one->userschecked;
                $result->awarded += $one->awarded;
                $result->skipped += $one->skipped;
                $result->errors += $one->errors;
            }
        } finally {
            $lock->release();
        }

        return $result;
    }

    /**
     * Sync one Sala course using bulk SQL (safe for tens of thousands of enrolments).
     *
     * @param int $courseid
     * @return \stdClass {userschecked, awarded, skipped, errors}
     */
    public static function sync_course(int $courseid): \stdClass {
        global $DB;

        $result = (object) [
            'userschecked' => 0,
            'awarded' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        if (!self::is_sala_course($courseid)) {
            return $result;
        }

        $cmids = self::get_required_video_cmids($courseid);
        $enrolledcount = self::count_active_enrolled_users($courseid);
        $result->userschecked = $enrolledcount;
        if ($enrolledcount === 0) {
            return $result;
        }

        $item = self::ensure_grade_item($courseid);
        if (!$item) {
            $result->errors++;
            return $result;
        }

        $already = self::userids_with_complete_grade($item);
        $completed = self::userids_who_completed_all_in_course($courseid, $cmids);
        $toaward = array_values(array_diff($completed, $already));

        foreach ($toaward as $userid) {
            try {
                if (self::award_complete_grade((int) $userid, $courseid)) {
                    $result->awarded++;
                }
            } catch (\Throwable $e) {
                $result->errors++;
                debugging('local_salagrade: failed user ' . $userid . ' course ' . $courseid
                    . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        $result->skipped = max(0, $result->userschecked - $result->awarded);

        return $result;
    }

    /**
     * Whether the course uses the Sala de Conferências format.
     *
     * @param int $courseid
     * @return bool
     */
    public static function is_sala_course(int $courseid): bool {
        if (isset(self::$salacache[$courseid])) {
            return self::$salacache[$courseid];
        }
        global $DB;
        $format = $DB->get_field('course', 'format', ['id' => $courseid]);
        self::$salacache[$courseid] = ($format === 'saladeconferencias');
        return self::$salacache[$courseid];
    }

    /**
     * IDs of every Sala de Conferências course.
     *
     * @return int[]
     */
    public static function get_sala_course_ids(): array {
        global $DB;
        return array_keys($DB->get_records('course', ['format' => 'saladeconferencias'], 'id', 'id'));
    }

    /**
     * Visible videos with completion tracking (watchable required set).
     *
     * Hidden "em breve" videos are excluded: they cannot be watched, and
     * counting them would block the grade for every student.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function has_completed_all_required_videos(int $userid, int $courseid): bool {
        $cmids = self::get_required_video_cmids($courseid);
        if (empty($cmids)) {
            return false;
        }
        global $DB;
        [$in, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cm');
        $params['userid'] = $userid;
        $params['c1'] = COMPLETION_COMPLETE;
        $params['c2'] = COMPLETION_COMPLETE_PASS;
        $count = $DB->count_records_select(
            'course_modules_completion',
            "userid = :userid AND coursemoduleid {$in} AND completionstate IN (:c1, :c2)",
            $params
        );
        return (int) $count === count($cmids);
    }

    /**
     * Write 100 on the plugin grade item. Idempotent when already 100.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool True if the stored grade is 100 afterwards.
     */
    public static function award_complete_grade(int $userid, int $courseid): bool {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/grade/grade_item.php');
        require_once($CFG->libdir . '/grade/grade_grade.php');

        $item = self::ensure_grade_item($courseid);
        if (!$item) {
            return false;
        }

        if (self::user_already_has_complete_grade($item, $userid)) {
            return true;
        }

        $existing = new \grade_grade(['itemid' => $item->id, 'userid' => $userid]);
        if (!empty($existing->id) && $existing->is_locked()) {
            return false;
        }

        $item->markasoverriddenwhengraded = false;
        return (bool) $item->update_final_grade($userid, self::GRADE_COMPLETE, 'local/salagrade');
    }

    /**
     * Create or load the manual grade item for a Sala course.
     *
     * @param int $courseid
     * @return \grade_item|null
     */
    public static function ensure_grade_item(int $courseid): ?\grade_item {
        global $CFG;

        if (array_key_exists($courseid, self::$itemcache)) {
            return self::$itemcache[$courseid];
        }

        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/grade/grade_item.php');

        $item = self::fetch_grade_item($courseid);
        if ($item) {
            $dirty = false;
            if ((float) $item->grademax != self::GRADE_COMPLETE) {
                $item->grademax = self::GRADE_COMPLETE;
                $dirty = true;
            }
            if ((int) $item->gradetype !== GRADE_TYPE_VALUE) {
                $item->gradetype = GRADE_TYPE_VALUE;
                $dirty = true;
            }
            if ($dirty) {
                $item->update();
            }
            self::$itemcache[$courseid] = $item;
            return $item;
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_salagrade');
        $createlock = $lockfactory->get_lock('gradeitem_' . $courseid, 10);
        if (!$createlock) {
            $item = self::fetch_grade_item($courseid);
            self::$itemcache[$courseid] = $item ?: null;
            return $item ?: null;
        }
        try {
            $item = self::fetch_grade_item($courseid);
            if ($item) {
                self::$itemcache[$courseid] = $item;
                return $item;
            }
            $item = new \grade_item();
            $item->courseid = $courseid;
            $item->itemtype = 'manual';
            $item->itemmodule = null;
            $item->iteminstance = null;
            $item->itemnumber = 0;
            $item->idnumber = self::ITEM_IDNUMBER;
            $item->itemname = get_string('gradeitemname', 'local_salagrade');
            $item->gradetype = GRADE_TYPE_VALUE;
            $item->grademax = self::GRADE_COMPLETE;
            $item->grademin = 0;
            $item->hidden = 0;
            $item->insert('local/salagrade');
            self::$itemcache[$courseid] = $item;
            return $item;
        } catch (\Throwable $e) {
            $item = self::fetch_grade_item($courseid);
            if ($item) {
                self::$itemcache[$courseid] = $item;
                return $item;
            }
            debugging('local_salagrade: cannot create grade item for course ' . $courseid
                . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            self::$itemcache[$courseid] = null;
            return null;
        } finally {
            if ($createlock) {
                $createlock->release();
            }
        }
    }

    /**
     * Visible video CMs with completion tracking.
     *
     * @param int $courseid
     * @return int[]
     */
    public static function get_required_video_cmids(int $courseid): array {
        global $CFG, $DB;

        if (isset(self::$requiredcmcache[$courseid])) {
            return self::$requiredcmcache[$courseid];
        }

        require_once($CFG->libdir . '/completionlib.php');

        $sql = "SELECT cm.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid
                   AND m.name = :modname
                   AND cm.completion > :none
                   AND cm.visible = :visible
                   AND cm.deletioninprogress = 0";
        $ids = $DB->get_fieldset_sql($sql, [
            'courseid' => $courseid,
            'modname' => 'video',
            'none' => COMPLETION_TRACKING_NONE,
            'visible' => 1,
        ]);
        self::$requiredcmcache[$courseid] = array_map('intval', $ids);
        return self::$requiredcmcache[$courseid];
    }

    /**
     * Fetch the plugin grade item without throwing if duplicates exist.
     *
     * @param int $courseid
     * @return \grade_item|null
     */
    private static function fetch_grade_item(int $courseid): ?\grade_item {
        global $DB, $CFG;

        require_once($CFG->libdir . '/grade/grade_item.php');

        $records = $DB->get_records('grade_items', [
            'courseid' => $courseid,
            'itemtype' => 'manual',
            'idnumber' => self::ITEM_IDNUMBER,
        ], 'id ASC', '*', 0, 1);
        if (empty($records)) {
            return null;
        }
        return new \grade_item((array) reset($records), false);
    }

    /**
     * Count active enrolments in the course.
     *
     * @param int $courseid
     * @return int
     */
    private static function count_active_enrolled_users(int $courseid): int {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT ue.userid)
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {user} u ON u.id = ue.userid
                 WHERE e.courseid = :courseid
                   AND ue.status = :uestatus
                   AND e.status = :estatus
                   AND u.deleted = 0
                   AND u.suspended = 0";
        return (int) $DB->count_records_sql($sql, [
            'courseid' => $courseid,
            'uestatus' => ENROL_USER_ACTIVE,
            'estatus' => ENROL_INSTANCE_ENABLED,
        ]);
    }

    /**
     * Enrolled users who completed every required CM (one query, no 18k IN list).
     *
     * @param int $courseid
     * @param int[] $cmids
     * @return int[]
     */
    private static function userids_who_completed_all_in_course(int $courseid, array $cmids): array {
        global $DB;

        if (empty($cmids)) {
            return [];
        }

        $needed = count($cmids);
        [$cmin, $cmparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cm');
        $params = $cmparams;
        $params['courseid'] = $courseid;
        $params['uestatus'] = ENROL_USER_ACTIVE;
        $params['estatus'] = ENROL_INSTANCE_ENABLED;
        $params['c1'] = COMPLETION_COMPLETE;
        $params['c2'] = COMPLETION_COMPLETE_PASS;
        $params['needed'] = $needed;

        $sql = "SELECT cmc.userid
                  FROM {course_modules_completion} cmc
                  JOIN {user_enrolments} ue ON ue.userid = cmc.userid
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
                  JOIN {user} u ON u.id = cmc.userid
                 WHERE cmc.coursemoduleid {$cmin}
                   AND cmc.completionstate IN (:c1, :c2)
                   AND ue.status = :uestatus
                   AND e.status = :estatus
                   AND u.deleted = 0
                   AND u.suspended = 0
              GROUP BY cmc.userid
                HAVING COUNT(DISTINCT cmc.coursemoduleid) = :needed";
        return array_map('intval', $DB->get_fieldset_sql($sql, $params));
    }

    /**
     * Users who already have the complete grade on this item.
     *
     * @param \grade_item $item
     * @return int[]
     */
    private static function userids_with_complete_grade(\grade_item $item): array {
        global $DB;

        $sql = "SELECT userid
                  FROM {grade_grades}
                 WHERE itemid = :itemid
                   AND finalgrade >= :grade";
        return array_map('intval', $DB->get_fieldset_sql($sql, [
            'itemid' => $item->id,
            'grade' => self::GRADE_COMPLETE,
        ]));
    }

    /**
     * Whether this user already has 100 on the item.
     *
     * @param \grade_item $item
     * @param int $userid
     * @return bool
     */
    private static function user_already_has_complete_grade(\grade_item $item, int $userid): bool {
        global $DB;
        $grade = $DB->get_field('grade_grades', 'finalgrade', [
            'itemid' => $item->id,
            'userid' => $userid,
        ]);
        return $grade !== false && (float) $grade >= self::GRADE_COMPLETE;
    }
}

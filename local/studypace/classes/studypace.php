<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_studypace;

defined('MOODLE_INTERNAL') || die();

// Config $CFG must be pulled into scope: when this class file is first autoloaded
// from inside a function/method that didn't declare `global $CFG`, the
// top-level code runs in that caller's scope, so $CFG would be null and these
// require_once paths would collapse to "/local/studypace/..." → a FATAL,
// uncatchable require failure (the same class of bug that looped the
// heal_gamification_rewards task via coin_stack.php).
global $CFG;

require_once($CFG->dirroot . '/local/studypace/classes/event/level_advanced.php');
require_once($CFG->dirroot . '/local/studypace/classes/event/planned_completion_high_grade.php');
require_once($CFG->dirroot . '/local/studypace/classes/event/planned_completion.php');

use format_specialization;

/**
 * Study pace tracking and dashboard progress helpers.
 *
 * @package   local_studypace
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class studypace {
    /**
     * Minimum course-total grade (%) required for an Eixo/Sala to count toward
     * dashboard overall progress.
     */
    const PROGRESS_PASS_GRADE = 60.0;

    /**
     * Course formats whose enrolments are tracked by the studypace plugin.
     *
     * Both formats live in the same "specialization track" from the user's point
     * of view, so every aggregation (progress %, expected %, number of tracked
     * courses, etc.) must include both. Use the helpers below — never inline
     * the literal list or rely on format_specialization::is_specialization(),
     * because that helper checks a separate table and silently skips
     * saladeconferencias courses, producing incoherent progress percentages.
     *
     * @var string[]
     */
    const TRACKED_FORMATS = ['specialization', 'saladeconferencias'];

    /** @var int Share of overall dashboard progress attributed to Eixos. */
    const PROGRESS_WEIGHT_EIXOS = 90;

    /** @var int Share of overall dashboard progress attributed to Sala de Conferências. */
    const PROGRESS_WEIGHT_SALA = 10;

    /**
     * Days after enrolment in an Eixo within which planned-completion medals/keys apply.
     * Independent from the study-plan estimatedcompletion used on the dashboard.
     */
    const GAMIFICATION_DEADLINE_DAYS = 100;

    /**
     * Seconds per plan month (30.4166666667 days), matching the historical studypace budget.
     *
     * @var float
     */
    const SECONDS_PER_MONTH = 2628000.0;

    /** @var array<int,bool> Request cache for is_cm_monitorable(). */
    private static $cmmonitorcache = [];

    /** @var array<int,bool> Request cache: course has explicit activity criteria. */
    private static $courseexplicitcache = [];

    /** @var int|null Cached count_tracked_courses() result. */
    private static $trackedcoursescount = null;

    /** @var int[]|null Cached ordered visible tracked course ids. */
    private static $orderedtrackedcourseids = null;

    /** @var array<int,true>|null Cached completed course ids for a user. */
    private static $usercoursecompleted = null;

    /** @var int */
    private static $usercoursecompleteduserid = 0;

    /** @var array<int,string|null> Request cache: course format by course id. */
    private static $courseformatcache = [];

    /** @var int|null Cached {modules}.id for mod_video. */
    private static $videomoduleid = null;

    /** @var bool Re-entrancy guard for refresh_user_plan_deadlines(). */
    private static $refreshingplandeadlines = false;

    /**
     * Preload completed course rows for a user (avoids N+1 in dashboard/menu).
     *
     * @param int $userid
     */
    public static function preload_user_course_completions(int $userid): void {
        global $DB;
        self::$usercoursecompleteduserid = $userid;
        self::$usercoursecompleted = [];
        $rows = $DB->get_records_select(
            'local_studypace',
            'userid = :u AND target = :t AND actualcompletion IS NOT NULL',
            ['u' => $userid, 't' => 'course'],
            '',
            'targetid'
        );
        foreach ($rows as $row) {
            self::$usercoursecompleted[(int) $row->targetid] = true;
        }
    }

    /**
     * SQL fragment matching tracked formats inside a {course} query.
     *
     * @param string $alias optional table alias prefix.
     * @return string SQL like "format IN ('specialization', 'saladeconferencias')"
     */
    private static function tracked_format_sql(string $alias = ''): string {
        $col = $alias === '' ? 'format' : ($alias . '.format');
        $quoted = array_map(function ($f) {
            return "'" . $f . "'";
        }, self::TRACKED_FORMATS);
        return $col . ' IN (' . implode(', ', $quoted) . ')';
    }

    /**
     * IDs of every course tracked by studypace (specialization + saladeconferencias).
     *
     * @param bool $onlyvisible only return visible courses.
     * @return int[]
     */
    public static function get_tracked_course_ids(bool $onlyvisible = false): array {
        global $DB;
        $sql = 'SELECT id FROM {course} WHERE ' . self::tracked_format_sql()
            . ($onlyvisible ? ' AND visible = 1' : '')
            . ' ORDER BY shortname ASC';
        return array_keys($DB->get_records_sql($sql));
    }

    /**
     * Tracked course IDs for a single format (specialization or saladeconferencias).
     *
     * @param string $format
     * @param bool $onlyvisible
     * @return int[]
     */
    public static function get_tracked_course_ids_for_format(string $format, bool $onlyvisible = false): array {
        if (!in_array($format, self::TRACKED_FORMATS, true)) {
            return [];
        }
        global $DB;
        $sql = 'SELECT id FROM {course} WHERE format = :format'
            . ($onlyvisible ? ' AND visible = 1' : '')
            . ' ORDER BY shortname ASC';
        return array_keys($DB->get_records_sql($sql, ['format' => $format]));
    }

    /**
     * Total number of visible tracked courses (Eixos + Sala).
     *
     * Used as N when splitting the study-plan length into sequential slots:
     * course i is due at plan_anchor + (i+1) * (plan_months / N).
     */
    public static function count_tracked_courses(): int {
        global $DB;
        if (self::$trackedcoursescount !== null) {
            return self::$trackedcoursescount;
        }
        self::$trackedcoursescount = (int) $DB->count_records_select(
            'course',
            self::tracked_format_sql() . ' AND visible = 1'
        );
        return self::$trackedcoursescount;
    }

    /**
     * Tracked courses in journey order: specializations by sortorder, then Sala.
     *
     * @param bool $onlyvisible
     * @return int[]
     */
    public static function get_ordered_tracked_course_ids(bool $onlyvisible = true): array {
        global $CFG;

        if ($onlyvisible && self::$orderedtrackedcourseids !== null) {
            return self::$orderedtrackedcourseids;
        }

        $ids = [];
        $speclib = $CFG->dirroot . '/course/format/specialization/lib.php';
        if (is_readable($speclib)) {
            require_once($speclib);
            if (class_exists('\format_specialization')) {
                foreach (\format_specialization::get_courses_in_format($onlyvisible) as $course) {
                    $ids[] = (int) $course->id;
                }
            }
        }
        $salalib = $CFG->dirroot . '/course/format/saladeconferencias/lib.php';
        if (is_readable($salalib)) {
            require_once($salalib);
            if (class_exists('\format_saladeconferencias')) {
                foreach (\format_saladeconferencias::get_courses_in_format() as $course) {
                    if ($onlyvisible && empty($course->visible)) {
                        continue;
                    }
                    $ids[] = (int) $course->id;
                }
            }
        }
        if (empty($ids)) {
            $ids = array_merge(
                self::get_tracked_course_ids_for_format('specialization', $onlyvisible),
                self::get_tracked_course_ids_for_format('saladeconferencias', $onlyvisible)
            );
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($onlyvisible) {
            self::$orderedtrackedcourseids = $ids;
        }
        return $ids;
    }

    /**
     * Earliest active enrolment start across tracked courses (plan timeline origin).
     *
     * @param int $userid
     * @return int Unix timestamp
     */
    private static function get_user_plan_anchor(int $userid): int {
        global $DB;

        $trackedids = self::get_ordered_tracked_course_ids(true);
        if ($userid <= 0 || empty($trackedids)) {
            return time();
        }

        [$insql, $params] = $DB->get_in_or_equal($trackedids, SQL_PARAMS_NAMED, 'c');
        $params['userid'] = $userid;
        $params['now'] = time();
        $sql = "SELECT ue.id, ue.timestart, ue.timecreated
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid
                   AND e.courseid {$insql}
                   AND ue.status = 0
                   AND (ue.timeend = 0 OR ue.timeend > :now)";
        $rows = $DB->get_records_sql($sql, $params);
        $anchor = null;
        foreach ($rows as $row) {
            $start = self::effective_enrolment_timestart($row);
            if ($anchor === null || $start < $anchor) {
                $anchor = $start;
            }
        }
        return $anchor ?? time();
    }

    /**
     * Sequential plan window for one course: [slotstart, slotend].
     *
     * Slot i (0-based) ends at plan_anchor + (i+1) * (months/N), so the last
     * course is due at the end of the full plan (not at months/N for every course).
     *
     * @param int $userid
     * @param int $courseid
     * @return \stdClass|null {slotstart, slotend, months, index, count} or null if unknown course
     */
    private static function get_course_plan_window(int $userid, int $courseid): ?\stdClass {
        global $CFG;

        $ordered = self::get_ordered_tracked_course_ids(true);
        $index = array_search((int) $courseid, $ordered, true);
        if ($index === false) {
            return null;
        }

        require_once($CFG->dirroot . '/local/profile/lib.php');
        $months = (int) local_profile_get_user_months($userid);
        if ($months <= 0) {
            $months = 18;
        }

        $count = max(1, count($ordered));
        $slotseconds = (int) round(($months * self::SECONDS_PER_MONTH) / $count);
        if ($slotseconds < 1) {
            $slotseconds = 1;
        }
        $anchor = self::get_user_plan_anchor($userid);

        $window = new \stdClass();
        $window->months = $months;
        $window->index = (int) $index;
        $window->count = $count;
        $window->slotstart = $anchor + ((int) $index * $slotseconds);
        $window->slotend = $anchor + (((int) $index + 1) * $slotseconds);
        return $window;
    }

    /**
     * Whether a given course is tracked by studypace.
     */
    public static function is_tracked_course(int $courseid): bool {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], 'id, format');
        return $course && in_array($course->format, self::TRACKED_FORMATS, true);
    }

    /**
     * Whether a given course format name is tracked. Cheaper than
     * is_tracked_course() when the caller already has the format string
     * (e.g. course_updated observer).
     */
    public static function is_format_tracked(string $format): bool {
        return in_array($format, self::TRACKED_FORMATS, true);
    }

    /**
     * Start timestamp used to spread estimatedcompletion across activities.
     *
     * Moodle often leaves user_enrolments.timestart at 0 ("from now") without
     * substituting the current time. Adding month-based offsets to 0 placed
     * every deadline in 1970, so get_expected_progress() treated 100% of
     * activities as already overdue on the student's first day.
     *
     * @param \stdClass $userenrolment Row from {user_enrolments}
     * @return int Unix timestamp
     */
    private static function effective_enrolment_timestart(\stdClass $userenrolment): int {
        $timestart = (int) $userenrolment->timestart;
        if ($timestart > 0) {
            return $timestart;
        }
        if (!empty($userenrolment->timecreated)) {
            return (int) $userenrolment->timecreated;
        }
        return time();
    }

    /**
     * process_user_enrolment().
     *
     * @param \core\event\base $event
     */
    public static function process_user_enrolment(\core\event\base $event) {
        switch ($event->eventname) {
            case '\\core\\event\\user_enrolment_created':
                self::create_user_enrolment($event->objectid, $event->relateduserid, $event->courseid);
                break;
            case '\\core\\event\\user_enrolment_updated':
                self::update_user_enrolment($event->objectid, $event->relateduserid, $event->courseid);
                break;
            case '\\core\\event\\user_enrolment_deleted':
                self::delete_user_enrolment($event->courseid, $event->relateduserid);
                break;
        }
    }

    /**
     * mark_course_completed().
     *
     * @param int $userid
     * @param int $courseid
     * @param int $completiontime
     */
    public static function mark_course_completed(int $userid, int $courseid, int $completiontime) {
        global $DB, $CFG;

        $courserecord = $DB->get_record('local_studypace', [
            'userid' => $userid,
            'target' => 'course',
            'targetid' => $courseid,
        ]);
        if (!$courserecord) {
            if (!self::ensure_user_course_pace($userid, $courseid)) {
                debugging('Study pace course record not found for user ' . $userid . ' in course ' . $courseid, DEBUG_NORMAL);
                return;
            }
            $courserecord = $DB->get_record('local_studypace', [
                'userid' => $userid,
                'target' => 'course',
                'targetid' => $courseid,
            ]);
            if (!$courserecord) {
                return;
            }
        }
        // Idempotency: if we've already marked this course completed for the
        // user, don't re-trigger the planned_completion event. Otherwise
        // recalculation or a duplicated completion event re-fires the
        // "high grade" achievement messages forever.
        if (!empty($courserecord->actualcompletion)) {
            if (self::is_eixo_course($courseid)) {
                self::ensure_gamification_rewards($userid, $courseid);
                self::queue_delayed_gamification_ensure($userid, $courseid);
            }
            return;
        }
        $courserecord->actualcompletion = $completiontime;
        $DB->update_record('local_studypace', $courserecord);

        // Generic "this user just finished this course" signal, fired ONCE
        // per (user, course) thanks to the idempotency guard above. Late
        // completers also get this event — the planned_completion fork
        // below skips them because it carries badge/timing semantics, but
        // for downstream workflows (auto-enrol in next Eixo, etc.) timing
        // is irrelevant.
        $completedevent = \local_studypace\event\course_completed::create([
            'objectid' => $courserecord->id,
            'userid' => $userid,
            'relateduserid' => $userid,
            'courseid' => $courseid,
            'context' => \context_course::instance($courseid),
        ]);
        $completedevent->trigger();

        // Medals/keys: Eixos only, 100 days from enrolment in that Eixo —
        // independent from dashboard estimatedcompletion (study plan).
        $ontime = self::is_completion_on_time_for_gamification($userid, $courseid, $completiontime);
        if ($ontime && !self::course_planned_completion_coins_already_awarded($userid, $courseid)) {
            try {
                $grade = self::get_course_grade_for_gamification($userid, $courseid);
                $params = [
                    'objectid' => $courserecord->id,
                    'userid' => $userid,
                    'relateduserid' => $userid,
                    'courseid' => $courseid,
                    'context' => \context_course::instance($courseid),
                    'other' => $grade,
                ];
                if ($grade >= 80) {
                    $plannedcompletionevent = \local_studypace\event\planned_completion_high_grade::create($params);
                } else {
                    $plannedcompletionevent = \local_studypace\event\planned_completion::create($params);
                }
                $plannedcompletionevent->trigger();
            } catch (\Throwable $e) {
                debugging('mark_course_completed: planned_completion trigger failed for user '
                        . $userid . ' course ' . $courseid . ': ' . $e->getMessage(), DEBUG_NORMAL);
            }
        }

        // Heal path for Eixos only (Sala never receives planned-completion rewards).
        if (self::is_eixo_course($courseid)) {
            self::ensure_gamification_rewards($userid, $courseid);
            self::queue_delayed_gamification_ensure($userid, $courseid);
        }
    }

    /**
     * Re-run gamification ensure after the gradebook has time to settle.
     *
     * @param int $userid
     * @param int $courseid
     */
    private static function queue_delayed_gamification_ensure(int $userid, int $courseid): void {
        $task = new \local_studypace\task\ensure_gamification_rewards();
        $task->set_custom_data((object) [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
        $task->set_next_run_time(time() + 300);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Credit missing keys/badges when the course is already marked complete
     * but observers did not run (misconfigured triggers, historical bugs).
     *
     * @param int $userid
     * @param int $courseid
     */
    private static function ensure_gamification_rewards(int $userid, int $courseid): void {
        try {
            gamification_gaps::ensure_course_rewards($userid, $courseid, true);
        } catch (\Throwable $e) {
            debugging('ensure_gamification_rewards failed for user ' . $userid
                    . ' course ' . $courseid . ': ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }

    /**
     * Public wrapper used by observers and admin tools.
     *
     * @param int $cmid
     * @return bool
     */
    public static function is_monitorable_cm(int $cmid): bool {
        return self::is_cm_monitorable($cmid);
    }

    /**
     * Resolve the Moodle course format name for a course id.
     *
     * @param int $courseid
     * @return string|null
     */
    private static function get_course_format(int $courseid): ?string {
        if (array_key_exists($courseid, self::$courseformatcache)) {
            return self::$courseformatcache[$courseid];
        }
        global $DB;
        $format = $DB->get_field('course', 'format', ['id' => $courseid]);
        self::$courseformatcache[$courseid] = $format !== false ? (string) $format : null;
        return self::$courseformatcache[$courseid];
    }

    /**
     * Whether this course can award planned-completion medals/keys (Eixos only).
     *
     * @param int $courseid
     * @return bool
     */
    public static function is_eixo_course(int $courseid): bool {
        return self::get_course_format($courseid) === 'specialization';
    }

    /**
     * Earliest active enrolment start for a user in one course.
     *
     * @param int $userid
     * @param int $courseid
     * @return int Unix timestamp (0 if not enrolled)
     */
    public static function get_user_course_enrolment_timestart(int $userid, int $courseid): int {
        global $DB;

        if ($userid <= 0 || $courseid <= 0) {
            return 0;
        }

        $sql = "SELECT ue.id, ue.timestart, ue.timecreated
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid
                   AND e.courseid = :courseid
                   AND ue.status = 0
                   AND (ue.timeend = 0 OR ue.timeend > :now)";
        $rows = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
            'now' => time(),
        ]);
        $start = null;
        foreach ($rows as $row) {
            $effective = self::effective_enrolment_timestart($row);
            if ($start === null || $effective < $start) {
                $start = $effective;
            }
        }
        return $start ?? 0;
    }

    /**
     * Medal/key deadline: enrolment in that Eixo + GAMIFICATION_DEADLINE_DAYS.
     * Not used for dashboard expected progress (elapsed plan fraction).
     *
     * @param int $userid
     * @param int $courseid
     * @return int Unix timestamp (0 if not an Eixo or not enrolled)
     */
    public static function get_gamification_deadline(int $userid, int $courseid): int {
        if (!self::is_eixo_course($courseid)) {
            return 0;
        }
        $enrolstart = self::get_user_course_enrolment_timestart($userid, $courseid);
        if ($enrolstart <= 0) {
            return 0;
        }
        return $enrolstart + (self::GAMIFICATION_DEADLINE_DAYS * DAYSECS);
    }

    /**
     * Whether a completion time qualifies for planned-completion medals/keys.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $completiontime
     * @return bool
     */
    public static function is_completion_on_time_for_gamification(
        int $userid,
        int $courseid,
        int $completiontime
    ): bool {
        if ($completiontime <= 0 || !self::is_eixo_course($courseid)) {
            return false;
        }
        $deadline = self::get_gamification_deadline($userid, $courseid);
        return ($deadline > 0 && $completiontime <= $deadline);
    }

    /**
     * Hidden (Oculto) Sala de Conferências videos count as mandatory for
     * aggregate completion (blocking the checkmark until published and
     * watched) and in progress denominators, but never as completed while
     * they remain hidden.
     *
     * @param \stdClass $cm Row with course, module, completion, visible
     * @return bool
     */
    private static function is_sala_conferencias_hidden_video_cm(\stdClass $cm): bool {
        global $DB;

        if ((int) ($cm->visible ?? 1) === 1) {
            return false;
        }
        if (self::get_course_format((int) $cm->course) !== 'saladeconferencias') {
            return false;
        }
        if (self::$videomoduleid === null) {
            self::$videomoduleid = (int) $DB->get_field('modules', 'id', ['name' => 'video']);
        }
        return (int) $cm->module === self::$videomoduleid
            && (int) $cm->completion !== COMPLETION_TRACKING_NONE;
    }

    /**
     * is_cm_monitorable().
     *
     * @param mixed $cmid
     */
    private static function is_cm_monitorable($cmid) {
        global $CFG, $DB;

        $cmid = (int) $cmid;
        if (isset(self::$cmmonitorcache[$cmid])) {
            return self::$cmmonitorcache[$cmid];
        }

        // Source of truth for what counts as "the student must finish
        // this to close the course" is /course/completion.php?id=<cid>,
        // which the admin populates by ticking the checkboxes under
        // "Condição: Conclusão de atividade". Those ticks are stored in
        // {course_completion_criteria} with criteriatype =
        // COMPLETION_CRITERIA_TYPE_ACTIVITY (4) and moduleinstance =
        // cm.id.
        //
        // Earlier versions of this function had a permissive fallback
        // that returned TRUE for any cm with course_modules.completion >
        // 0, even if the course already had an explicit criteria list.
        // That made the studypace track ALL "completion enabled"
        // activities — SCORMs, enquetes etc. that the admin had on the
        // course but DID NOT tick at /course/completion.php — and the
        // course never matched "all monitored cms done", so
        // mark_course_completed() never fired and the auto-progression
        // never advanced the user to the next Eixo.
        //
        // New rule: if the course has any explicit
        // COMPLETION_CRITERIA_TYPE_ACTIVITY entry, ONLY the cms on that
        // list count. Otherwise (no explicit criteria configured) we
        // still need *something* to monitor so progress isn't always
        // 0% for legacy courses; in that case we fall back to "any cm
        // with completion > 0".
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');
        $cm = $DB->get_record(
            'course_modules',
            ['id' => $cmid],
            'id, course, module, completion, visible, deletioninprogress'
        );
        if (!$cm || !empty($cm->deletioninprogress)) {
            self::$cmmonitorcache[$cmid] = false;
            return false;
        }
        if ((int) $cm->visible !== 1 && !self::is_sala_conferencias_hidden_video_cm($cm)) {
            self::$cmmonitorcache[$cmid] = false;
            return false;
        }
        $courseid = (int) $cm->course;
        if (!isset(self::$courseexplicitcache[$courseid])) {
            self::$courseexplicitcache[$courseid] = $DB->record_exists('course_completion_criteria', [
                'course' => $courseid,
                'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
            ]);
        }
        if (self::$courseexplicitcache[$courseid]) {
            $result = $DB->record_exists('course_completion_criteria', [
                'course' => $courseid,
                'moduleinstance' => $cmid,
                'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
            ]);
        } else {
            $result = ((int) $cm->completion > 0);
        }
        self::$cmmonitorcache[$cmid] = $result;
        return $result;
    }

    /**
     * is_cm_completed().
     *
     * @param mixed $userid
     * @param mixed $cmid
     */
    private static function is_cm_completed($userid, $cmid) {
        global $DB;

        $cm = $DB->get_record('course_modules', [
            'id' => $cmid,
        ]);
        if (!$cm) {
            return false;
        }
        if (!$cm->visible) {
            return false;
        }
        $completion = $DB->get_record('course_modules_completion', ['coursemoduleid' => $cmid, 'userid' => $userid]);
        if ($completion) {
            return ($completion->completionstate == COMPLETION_COMPLETE
                || $completion->completionstate == COMPLETION_COMPLETE_FAIL
                || $completion->completionstate == COMPLETION_COMPLETE_PASS);
        } else {
            return false;
        }
    }

    /**
     * Whether a Sala video counts toward aggregate course completion.
     *
     * Visible videos with completion tracking always count. Hidden (Oculto)
     * videos with completion tracking also count — they block the Sala from
     * being marked complete until published and watched, preventing premature
     * completion while admins are still adding episodes.
     *
     * @param \cm_info $cm
     * @return bool
     */
    private static function is_sala_required_video_cm(\cm_info $cm): bool {
        if ($cm->modname !== 'video') {
            return false;
        }
        if ($cm->completion == COMPLETION_TRACKING_NONE) {
            return false;
        }
        if ($cm->uservisible) {
            return true;
        }
        return (int) $cm->visible === 0;
    }

    /**
     * all_sala_required_videos_completed().
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    private static function all_sala_required_videos_completed(int $userid, int $courseid): bool {
        global $CFG;

        require_once($CFG->libdir . '/modinfolib.php');
        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course, $userid);

        $required = 0;
        foreach ($modinfo->cms as $cm) {
            if (!self::is_sala_required_video_cm($cm)) {
                continue;
            }
            $required++;
            if (!self::is_cm_completed($userid, $cm->id)) {
                return false;
            }
        }

        return $required > 0;
    }

    /**
     * Per-activity breakdown used to explain why a Sala course is/ isn't
     * considered complete for one user. Mirrors the rules in
     * all_sala_required_videos_completed().
     *
     * @param int $userid
     * @param int $courseid
     * @return \stdClass[] rows with name, modname, visible, uservisible,
     *   completiontracking, counted, completed
     */
    public static function get_sala_completion_breakdown(int $userid, int $courseid): array {
        global $CFG;

        require_once($CFG->libdir . '/modinfolib.php');
        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course, $userid);

        $rows = [];
        foreach ($modinfo->cms as $cm) {
            $tracked = ((int) $cm->completion !== COMPLETION_TRACKING_NONE);
            $isvideo = ($cm->modname === 'video');
            $counted = self::is_sala_required_video_cm($cm);
            $rows[] = (object) [
                'cmid' => (int) $cm->id,
                'name' => $cm->get_formatted_name(),
                'modname' => $cm->modname,
                'visible' => (int) $cm->visible === 1,
                'uservisible' => (bool) $cm->uservisible,
                'completiontracking' => $tracked,
                'counted' => $counted,
                'completed' => $counted ? self::is_cm_completed($userid, $cm->id) : false,
                'hiddenmandatory' => $counted && !$cm->uservisible,
            ];
        }

        return $rows;
    }

    /**
     * all_cms_completed().
     *
     * @param int $userid
     * @param int $courseid
     * @param int $cmid
     */
    private static function all_cms_completed(int $userid, int $courseid, int $cmid) {
        global $DB, $CFG;

        if (self::get_course_format($courseid) === 'saladeconferencias') {
            return self::all_sala_required_videos_completed($userid, $courseid);
        }

        require_once($CFG->libdir . '/modinfolib.php');
        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course, $userid);

        $monitorablevisible = 0;
        foreach ($modinfo->cms as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            if (!self::is_cm_monitorable($cm->id)) {
                continue;
            }
            $monitorablevisible++;
            if (!self::is_cm_completed($userid, $cm->id)) {
                return false;
            }
        }

        if ($monitorablevisible === 0) {
            return false;
        }

        // Pace rows with NULL actualcompletion only block when Moodle still shows incomplete.
        $pendingpace = $DB->get_records_select(
            'local_studypace',
            "userid = :userid AND courseid = :courseid AND target = 'cm' AND actualcompletion IS NULL",
            ['userid' => $userid, 'courseid' => $courseid]
        );
        foreach ($pendingpace as $pace) {
            $pendingcmid = (int) $pace->targetid;
            if (!isset($modinfo->cms[$pendingcmid]) || !$modinfo->cms[$pendingcmid]->uservisible) {
                continue;
            }
            if (self::is_cm_monitorable($pendingcmid) && !self::is_cm_completed($userid, $pendingcmid)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Seed course/cm pace rows for an enrolled user when missing (legacy enrolments).
     *
     * @param int $userid
     * @param int $courseid
     * @return bool True when a course-level pace row exists after the call.
     */
    private static function ensure_user_course_pace(int $userid, int $courseid): bool {
        global $DB;

        if (!self::is_tracked_course($courseid)) {
            return false;
        }
        if (
            $DB->record_exists('local_studypace', [
            'userid' => $userid,
            'target' => 'course',
            'targetid' => $courseid,
            ])
        ) {
            return true;
        }
        $enrolment = $DB->get_record_sql(
            'SELECT ue.id
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid
                AND ue.userid = :userid
                AND ue.status = 0
           ORDER BY ue.id ASC',
            ['courseid' => $courseid, 'userid' => $userid],
            IGNORE_MULTIPLE
        );
        if (!$enrolment) {
            return false;
        }
        self::update_user_enrolment((int) $enrolment->id, $userid, $courseid);
        return $DB->record_exists('local_studypace', [
            'userid' => $userid,
            'target' => 'course',
            'targetid' => $courseid,
        ]);
    }

    /**
     * process_course_module_completion_data().
     *
     * @param mixed $userid
     * @param mixed $courseid
     * @param mixed $completiontime
     * @param mixed $coursemodulescompletionid
     * @param mixed $event
     */
    public static function process_course_module_completion_data($userid, $courseid, $completiontime, $coursemodulescompletionid, $event = null) {
        global $DB;

        $coursemodulescompletion = $DB->get_record('course_modules_completion', [
            'id' => $coursemodulescompletionid,
        ]);
        if (!$coursemodulescompletion || $coursemodulescompletion->completionstate == COMPLETION_INCOMPLETE) {
            return;
        }
        $cmid = $coursemodulescompletion->coursemoduleid;
        $cmrecord = $DB->get_record('local_studypace', [
            'userid' => $userid,
            'target' => 'cm',
            'targetid' => $cmid,
        ]);
        if (!$cmrecord) {
            self::ensure_user_course_pace($userid, $courseid);
            self::sync_cm_completions($userid);
            $cmrecord = $DB->get_record('local_studypace', [
                'userid' => $userid,
                'target' => 'cm',
                'targetid' => $cmid,
            ]);
        }
        if (!$cmrecord) {
            debugging('Study pace course module record not found for user ' . $userid . ' and cm ' . $cmid, DEBUG_NORMAL);
            if (self::all_cms_completed($userid, $courseid, $cmid)) {
                self::mark_course_completed($userid, $courseid, $completiontime);
            }
            return;
        }
        if (empty($cmrecord->actualcompletion)) {
            $cmrecord->actualcompletion = $completiontime;
            $DB->update_record('local_studypace', $cmrecord);
        }
        if ($event) {
            $newlevel = self::get_current_level($userid, $courseid);
            $params = [
                'objectid' => $cmrecord->id,
                'userid' => $userid,
                'relateduserid' => $userid,
                'courseid' => $courseid,
                'context' => \context_course::instance($courseid),
                'other' => $newlevel,
            ];
            $levelevent = \local_studypace\event\level_advanced::create($params);
            $levelevent->trigger();
            self::send_message($userid, $newlevel, $courseid, $event);
        }
        if (self::all_cms_completed($userid, $courseid, $cmid)) {
            self::mark_course_completed($userid, $courseid, $completiontime);
        }
    }

    /**
     * process_course_module_completion().
     *
     * @param \core\event\base $event
     */
    public static function process_course_module_completion(\core\event\base $event) {
        $userid = $event->relateduserid;
        $courseid = $event->courseid;
        $completiontime = $event->timecreated;
        $coursemodulescompletionid = $event->objectid;
        self::process_course_module_completion_data($userid, $courseid, $completiontime, $coursemodulescompletionid, $event);
    }

    /**
     * send_message().
     *
     * @param mixed $userid
     * @param mixed $newlevel
     * @param mixed $courseid
     * @param mixed $event
     */
    private static function send_message($userid, $newlevel, $courseid, $event = null) {
        global $CFG, $DB, $OUTPUT;

        if (!is_numeric($userid) || $userid <= 0) {
            throw new \invalid_parameter_exception('userid must be positive integer');
        }
        if (!is_numeric($newlevel)) {
            throw new \invalid_parameter_exception('level must be integer');
        }
        if (!is_numeric($courseid) || $courseid <= 0) {
            throw new \invalid_parameter_exception('courseid must be positive integer');
        }

        $course = get_course($courseid);
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $message = get_string('leveladvancedmessage', 'local_studypace', $course->fullname);
        $messagesubject = get_string('leveladvancedsubject', 'local_studypace');

        if (strpos($message, '<') === false) {
            // Plain text only.
            $messagetext = $message;
            $messagehtml = text_to_html($messagetext, null, false, true);
        } else {
            // This is most probably the tag/newline soup known as FORMAT_MOODLE.
            $messagehtml = format_text($message, FORMAT_HTML, ['para' => false, 'newlines' => true, 'filter' => true]);
            $messagetext = html_to_text($messagehtml);
        }

        // Admin as sender (fallback to noreply if siteadmins is empty).
        $sender = false;
        if (!empty($CFG->siteadmins)) {
            $adminids = explode(',', $CFG->siteadmins);
            $adminid = (int) reset($adminids);
            if ($adminid > 0) {
                $sender = get_complete_user_data('id', $adminid);
            }
        }
        if (!$sender) {
            $sender = \core_user::get_noreply_user();
        }

        // Prepare the message.
        $update = new \core\message\message();
        $update->component = 'local_studypace';
        $update->name = 'leveladvanced';
        $update->notification = 1;
        $update->courseid = $courseid;
        $update->userfrom = $sender;
        $update->userto = $user;
        $update->subject = $messagesubject;
        $update->fullmessage = $messagetext;
        $update->fullmessageformat = FORMAT_PLAIN;
        $update->fullmessagehtml = $messagehtml;
        $update->smallmessage = $messagetext;
        $update->contexturl = $event ? $event->get_url() : null;
        $update->contexturlname = $event ? $event->get_name() : null;

        // ... and send it.
        message_send($update);
    }

    /**
     * get_course_cmids().
     *
     * @param int $courseid
     * @param mixed $onlymandatory
     */
    private static function get_course_cmids(int $courseid, $onlymandatory = true) {
        global $DB;

        $coursecmids = [];
        $sql = 'SELECT * '
                . 'FROM {course_sections} '
                . 'WHERE course = :courseid '
                . 'AND section > 0 '
                . 'ORDER BY section ASC;';
        $sections = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
        ]);
        foreach ($sections as $section) {
            // course_sections.sequence is a comma-separated list of cm IDs
            // and is frequently empty (newly created section). explode('') on
            // PHP returns [''], which then gets passed to is_cm_monitorable
            // and coerced to 0, polluting the result list with a fake "cm 0".
            // Filter out empty fragments before iterating.
            if ($section->sequence === null || $section->sequence === '') {
                continue;
            }
            $cmids = array_filter(array_map('trim', explode(',', $section->sequence)));
            foreach ($cmids as $cmid) {
                $cmid = (int) $cmid;
                if ($cmid <= 0) {
                    continue;
                }
                if (!$onlymandatory || self::is_cm_monitorable($cmid)) {
                    $coursecmids[] = $cmid;
                }
            }
        }
        return $coursecmids;
    }

    /**
     * create_user_enrolment().
     *
     * @param int $userenrolmentid
     * @param int $userid
     * @param int $courseid
     */
    private static function create_user_enrolment(int $userenrolmentid, int $userid, int $courseid) {
        global $DB;

        if (!self::is_tracked_course($courseid)) {
            return;
        }

        $userenrolment = $DB->get_record('user_enrolments', [
            'id' => $userenrolmentid,
        ]);
        if (!$userenrolment) {
            debugging('@create_user_enrolment> User enrolment ' . $userenrolmentid . ' not found', DEBUG_NORMAL);
            return;
        }

        // Sequential plan slots depend on every enrolment of the user — refresh
        // the whole chain (creates missing course/CM pace rows as needed).
        self::refresh_user_plan_deadlines($userid);
    }

    /**
     * Recompute course + CM estimatedcompletion from the user's study plan.
     *
     * @return \stdClass {course_updated, cm_updated}
     */
    private static function update_user_enrolment(int $userenrolmentid, int $userid, int $courseid): \stdClass {
        global $DB;

        $result = new \stdClass();
        $result->course_updated = 0;
        $result->cm_updated = 0;

        if (!self::is_tracked_course($courseid)) {
            return $result;
        }
        $userenrolment = $DB->get_record('user_enrolments', [
            'id' => $userenrolmentid,
        ]);
        if (!$userenrolment) {
            debugging('@update_user_enrolment> User enrolment ' . $userenrolmentid . ' not found', DEBUG_NORMAL);
            return $result;
        }

        return self::refresh_user_plan_deadlines($userid);
    }

    /**
     * Rewrite sequential plan deadlines for every tracked enrolment of a user.
     *
     * Each visible Eixo/Sala occupies one equal slot along the full plan length
     * (nummonths). Course i is due at plan_anchor + (i+1)*(months/N); the last
     * course lands at the end of the plan. Activity deadlines are spread inside
     * each course's own slot.
     *
     * @param int $userid
     * @return \stdClass {processed, course_updated, cm_updated, months}
     */
    public static function refresh_user_plan_deadlines(int $userid): \stdClass {
        global $DB, $CFG;

        $stats = new \stdClass();
        $stats->processed = 0;
        $stats->course_updated = 0;
        $stats->cm_updated = 0;
        $stats->months = 0;

        if ($userid <= 0) {
            return $stats;
        }
        if (self::$refreshingplandeadlines) {
            return $stats;
        }

        require_once($CFG->dirroot . '/local/profile/lib.php');
        $stats->months = (int) local_profile_get_user_months($userid);
        if ($stats->months <= 0) {
            $stats->months = 18;
        }

        $ordered = self::get_ordered_tracked_course_ids(true);
        if (empty($ordered)) {
            return $stats;
        }

        [$insql, $params] = $DB->get_in_or_equal($ordered, SQL_PARAMS_NAMED, 'c');
        $params['userid'] = $userid;
        $params['now'] = time();
        $sql = "SELECT ue.id AS enrolmentid, e.courseid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid
                   AND e.courseid {$insql}
                   AND ue.status = 0
                   AND (ue.timeend = 0 OR ue.timeend > :now)";
        $enrolments = $DB->get_records_sql($sql, $params);
        if (empty($enrolments)) {
            return $stats;
        }

        self::$refreshingplandeadlines = true;
        try {
            foreach ($enrolments as $enrolment) {
                $courseid = (int) $enrolment->courseid;
                $partial = self::apply_course_plan_deadlines($userid, $courseid);
                $stats->processed++;
                $stats->course_updated += (int) ($partial->course_updated ?? 0);
                $stats->cm_updated += (int) ($partial->cm_updated ?? 0);
            }
        } finally {
            self::$refreshingplandeadlines = false;
        }

        return $stats;
    }

    /**
     * Apply sequential plan slot deadlines to one course (course + CM rows).
     *
     * @param int $userid
     * @param int $courseid
     * @return \stdClass {course_updated, cm_updated}
     */
    private static function apply_course_plan_deadlines(int $userid, int $courseid): \stdClass {
        global $DB;

        $result = new \stdClass();
        $result->course_updated = 0;
        $result->cm_updated = 0;

        $window = self::get_course_plan_window($userid, $courseid);
        if ($window === null) {
            return $result;
        }

        $slotstart = (int) $window->slotstart;
        $slotend = (int) $window->slotend;
        if ($slotend <= $slotstart) {
            $slotend = $slotstart + 1;
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            $courserecord = $DB->get_record('local_studypace', [
                'userid' => $userid,
                'target' => 'course',
                'targetid' => $courseid,
            ]);
            if ($courserecord) {
                if ((int) $courserecord->estimatedcompletion !== $slotend) {
                    $result->course_updated = 1;
                }
                $courserecord->estimatedcompletion = $slotend;
                $DB->update_record('local_studypace', $courserecord);
            } else {
                $courserecord = new \stdClass();
                $courserecord->userid = $userid;
                $courserecord->courseid = $courseid;
                $courserecord->level = 0;
                $courserecord->target = 'course';
                $courserecord->targetid = $courseid;
                $courserecord->estimatedcompletion = $slotend;
                $DB->insert_record('local_studypace', $courserecord);
                $result->course_updated = 1;
            }

            $cmids = self::get_course_cmids($courseid);
            $monitorable = [];
            foreach ($cmids as $cmid) {
                if (self::is_cm_monitorable($cmid)) {
                    $monitorable[] = (int) $cmid;
                }
            }
            if (!empty($monitorable)) {
                $courseduration = $slotend - $slotstart;
                $cmduration = (int) max(1, round($courseduration / count($monitorable)));
                $cmend = $slotstart + $cmduration;
                $level = 1;
                foreach ($monitorable as $cmid) {
                    $cmrecord = $DB->get_record('local_studypace', [
                        'userid' => $userid,
                        'target' => 'cm',
                        'targetid' => $cmid,
                    ]);
                    if ($cmrecord) {
                        if ((int) $cmrecord->estimatedcompletion !== (int) $cmend) {
                            $result->cm_updated++;
                        }
                        $cmrecord->estimatedcompletion = $cmend;
                        $cmrecord->level = $level;
                        $DB->update_record('local_studypace', $cmrecord);
                    } else {
                        $cmrecord = new \stdClass();
                        $cmrecord->userid = $userid;
                        $cmrecord->courseid = $courseid;
                        $cmrecord->level = $level;
                        $cmrecord->target = 'cm';
                        $cmrecord->targetid = $cmid;
                        $cmrecord->estimatedcompletion = $cmend;
                        $DB->insert_record('local_studypace', $cmrecord);
                        $result->cm_updated++;
                    }
                    $cmend += $cmduration;
                    ++$level;
                }
            }

            $existingcms = $DB->get_records('local_studypace', [
                'userid' => $userid,
                'courseid' => $courseid,
                'target' => 'cm',
            ]);
            if (!empty($existingcms)) {
                $valid = array_flip($monitorable);
                foreach ($existingcms as $row) {
                    if (!isset($valid[(int) $row->targetid])) {
                        $DB->delete_records('local_studypace', ['id' => $row->id]);
                    }
                }
            }
            $transaction->allow_commit();

            if ($courserecord && !empty($courserecord->actualcompletion)) {
                if (!self::all_cms_completed($userid, $courseid, 0)) {
                    self::clear_course_completion_marker($userid, $courseid);
                } else {
                    try {
                        gamification_gaps::ensure_course_rewards($userid, $courseid, true);
                    } catch (\Throwable $e) {
                        debugging('ensure_course_rewards after enrolment update failed for user '
                                . $userid . ' course ' . $courseid . ': ' . $e->getMessage(), DEBUG_NORMAL);
                    }
                }
            }
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            debugging('apply_course_plan_deadlines failed: ' . $e->getMessage(), DEBUG_NORMAL);
        }

        return $result;
    }

    /**
     * Recompute estimatedcompletion for every active enrolment in tracked courses.
     * Uses the student's current study plan ({local_profile}.nummonths).
     *
     * @param int $userid Restrict to one user (0 = every user).
     * @return \stdClass {processed, course_updated, cm_updated, months}
     */
    public static function update_all_user_enrolments(int $userid = 0): \stdClass {
        global $DB, $CFG;

        $stats = new \stdClass();
        $stats->processed = 0;
        $stats->course_updated = 0;
        $stats->cm_updated = 0;
        $stats->months = 0;

        if ($userid > 0) {
            $partial = self::refresh_user_plan_deadlines($userid);
            $stats->processed = (int) ($partial->processed ?? 0);
            $stats->course_updated = (int) ($partial->course_updated ?? 0);
            $stats->cm_updated = (int) ($partial->cm_updated ?? 0);
            $stats->months = (int) ($partial->months ?? 0);
            return $stats;
        }

        $trackedids = self::get_ordered_tracked_course_ids(true);
        if (empty($trackedids)) {
            return $stats;
        }

        [$insql, $params] = $DB->get_in_or_equal($trackedids, SQL_PARAMS_NAMED, 'tc');
        $params['now'] = time();
        $sql = 'SELECT DISTINCT ue.userid '
                . 'FROM {user_enrolments} ue '
                . 'JOIN {enrol} e ON e.id = ue.enrolid '
                . 'WHERE e.courseid ' . $insql . ' '
                . 'AND ue.status = 0 '
                . 'AND (ue.timeend = 0 OR ue.timeend > :now)';
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $row) {
            $partial = self::refresh_user_plan_deadlines((int) $row->userid);
            $stats->processed += (int) ($partial->processed ?? 0);
            $stats->course_updated += (int) ($partial->course_updated ?? 0);
            $stats->cm_updated += (int) ($partial->cm_updated ?? 0);
        }
        $rs->close();
        return $stats;
    }

    /**
     * Rebuild cm-level pace rows for every active enrolment in one course.
     *
     * @param int $courseid
     */
    public static function sync_course_enrolments(int $courseid): void {
        global $DB;

        if (!self::is_tracked_course($courseid)) {
            return;
        }

        $now = time();
        $rs = $DB->get_recordset_sql(
            'SELECT ue.id AS enrolmentid, ue.userid '
                . 'FROM {user_enrolments} ue '
                . 'JOIN {enrol} e ON e.id = ue.enrolid '
                . 'WHERE e.courseid = :courseid '
                . 'AND ue.status = 0 '
                . 'AND (ue.timeend = 0 OR ue.timeend > :now)',
            ['courseid' => $courseid, 'now' => $now]
        );
        foreach ($rs as $enrolment) {
            self::update_user_enrolment((int) $enrolment->enrolmentid, (int) $enrolment->userid, $courseid);
        }
        $rs->close();
    }

    /**
     * Sync Moodle's per-CM completion ({course_modules_completion}) into the
     * studypace per-CM pace rows.
     *
     * Background: studypace listens for \core\event\course_module_completion_updated
     * and writes local_studypace.actualcompletion from there. If that event
     * was lost for any reason (plugin disabled at the time, observer cache
     * stale, error inside the handler), the course_modules_completion row
     * still records the completion but the studypace shadow row stays NULL.
     * That cascades into a wrong "Eixo em Andamento" / 0% progress display
     * and prevents auto-progression because all_cms_completed() never sees
     * the row flip.
     *
     * This helper closes that gap without re-firing CM-level events: for
     * each studypace cm-row that's still NULL, if the underlying
     * course_modules_completion row is in a completed state, copy its
     * timemodified into actualcompletion.
     *
     * @param int $userid Restrict to a single user (0 = every user).
     * @return int Number of cm rows that were synced.
     */
    public static function sync_cm_completions(int $userid = 0): int {
        global $DB;

        // COMPLETION_COMPLETE = 1, COMPLETION_COMPLETE_PASS = 2,
        // COMPLETION_COMPLETE_FAIL = 3. We treat all three as "done" so a
        // failed mastery-graded activity still counts as a completion event
        // (it does in Moodle's own completion UI).
        $sql = "SELECT sp.id AS spid, cmc.timemodified AS completiontime
                  FROM {local_studypace} sp
                  JOIN {course_modules_completion} cmc
                    ON cmc.userid = sp.userid
                   AND cmc.coursemoduleid = sp.targetid
                 WHERE sp.target = 'cm'
                   AND sp.actualcompletion IS NULL
                   AND cmc.completionstate IN (1, 2, 3)";
        $params = [];
        if ($userid > 0) {
            $sql .= ' AND sp.userid = :userid';
            $params['userid'] = $userid;
        }

        $synced = 0;
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $rec) {
            $time = (int) $rec->completiontime;
            if ($time <= 0) {
                // Defensive: timemodified can be 0 for very old rows. Use
                // "now" rather than 0 so the row carries a sortable value
                // and downstream "max completion time" math doesn't break.
                $time = time();
            }
            $DB->set_field(
                'local_studypace',
                'actualcompletion',
                $time,
                ['id' => $rec->spid]
            );
            $synced++;
        }
        $rs->close();
        return $synced;
    }

    /**
     * Mark every course as completed when all its cm-paces are done but the
     * course-pace row's actualcompletion is still NULL.
     *
     * Drives mark_course_completed(), which:
     *  1. Sets local_studypace.actualcompletion on the course row.
     *  2. Fires \local_studypace\event\course_completed (which the
     *     local_profile observer turns into "enrol in next Eixo").
     *  3. Fires planned_completion / planned_completion_high_grade ONLY for
     *     Eixos completed within GAMIFICATION_DEADLINE_DAYS of enrolment in
     *     that Eixo (dashboard study-plan estimatedcompletion is unrelated).
     *
     * Idempotent against re-runs: mark_course_completed() short-circuits
     * the moment actualcompletion is non-NULL, so the second pass is a
     * no-op even if the upgrade step ships AND an admin clicks the
     * "Recalcular Study Pace" page.
     *
     * @param int $userid Restrict to a single user (0 = every user).
     * @return int Number of course rows that got marked completed.
     */
    public static function repair_pending_course_completions(int $userid = 0): int {
        global $DB;

        // One grouped query per pending course row instead of 3 queries each.
        $params = [];
        $usersql = '';
        if ($userid > 0) {
            $usersql = ' AND sp.userid = :userid';
            $params['userid'] = $userid;
        }

        $sql = "SELECT sp.userid,
                       sp.courseid,
                       COUNT(cm.id) AS total,
                       SUM(CASE WHEN cm.actualcompletion IS NULL THEN 1 ELSE 0 END) AS pending,
                       MAX(cm.actualcompletion) AS maxtime
                  FROM {local_studypace} sp
             LEFT JOIN {local_studypace} cm
                    ON cm.userid = sp.userid
                   AND cm.courseid = sp.courseid
                   AND cm.target = 'cm'
                 WHERE sp.target = 'course'
                   AND sp.actualcompletion IS NULL
                   {$usersql}
              GROUP BY sp.userid, sp.courseid";

        $marked = 0;
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $rec) {
            $total = (int) $rec->total;
            $pending = (int) $rec->pending;
            if ($total === 0 || $pending > 0 || empty($rec->maxtime)) {
                continue;
            }
            $uid = (int) $rec->userid;
            $cid = (int) $rec->courseid;
            try {
                self::mark_course_completed($uid, $cid, (int) $rec->maxtime);
                $marked++;
            } catch (\Throwable $e) {
                debugging(
                    'repair_pending_course_completions failed for user ' . $uid
                        . ' / course ' . $cid . ': ' . $e->getMessage(),
                    DEBUG_NORMAL
                );
            }
        }
        $rs->close();
        return $marked;
    }

    /**
     * Drop {local_studypace} cm-rows that point to course modules NOT
     * listed as explicit COMPLETION_CRITERIA_TYPE_ACTIVITY criteria for
     * their course.
     *
     * Earlier versions of is_cm_monitorable() considered any cm with
     * course_modules.completion > 0 as monitorable, even when the
     * course already had an explicit "Condição: Conclusão de atividade"
     * list at /course/completion.php. That over-reported the work the
     * student had to do — SCORMs and enquetes with cm-level completion
     * but no tick on the course completion page entered the studypace
     * mandatory list, and the course never matched "all monitored cms
     * done", so mark_course_completed() never fired and the auto-
     * progression stalled.
     *
     * The function is idempotent and safe to call from the upgrade
     * pipeline and the Recalcular admin page. It only deletes rows for
     * courses that DO have an explicit criteria list configured — for
     * legacy courses with no explicit list (where is_cm_monitorable()
     * still falls back to cm.completion > 0) it leaves the existing
     * rows alone.
     *
     * @param int $userid Optional single-user restriction; 0 = all users.
     * @return int Number of cm rows deleted.
     */
    public static function purge_nonmandatory_cm_rows(int $userid = 0): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');

        // Find every distinct courseid currently referenced by cm-rows.
        // Scoping by user when applicable keeps single-user recalcs fast
        // and avoids touching unrelated rows.
        $sql = "SELECT DISTINCT courseid FROM {local_studypace} WHERE target = 'cm'";
        $params = [];
        if ($userid > 0) {
            $sql .= ' AND userid = :userid';
            $params['userid'] = $userid;
        }
        $courseids = $DB->get_fieldset_sql($sql, $params);
        if (empty($courseids)) {
            return 0;
        }
        $deleted = 0;
        foreach ($courseids as $cid) {
            $cid = (int) $cid;
            // Whitelist of cms the admin explicitly ticked on
            // /course/completion.php for this course. Empty = course
            // has no explicit list → fallback path applies → nothing
            // to purge.
            $whitelist = $DB->get_fieldset_select(
                'course_completion_criteria',
                'moduleinstance',
                'course = :course AND criteriatype = :criteriatype',
                ['course' => $cid, 'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY]
            );
            if (empty($whitelist)) {
                continue;
            }
            // get_in_or_equal with NOT semantics: delete any cm-row
            // pointing to a targetid that is NOT in the explicit list.
            [$insql, $inparams] = $DB->get_in_or_equal(
                array_map('intval', $whitelist),
                SQL_PARAMS_NAMED,
                'cm',
                false /* NOT IN */
            );
            $where = "target = 'cm' AND courseid = :courseid AND targetid $insql";
            $delparams = array_merge(['courseid' => $cid], $inparams);
            if ($userid > 0) {
                $where .= ' AND userid = :userid';
                $delparams['userid'] = $userid;
            }
            // count_records_select first so we can return a meaningful
            // total without relying on the DB driver returning an
            // affected-row count from delete_records_select().
            $count = (int) $DB->count_records_select('local_studypace', $where, $delparams);
            if ($count > 0) {
                $DB->delete_records_select('local_studypace', $where, $delparams);
                $deleted += $count;
            }

            // (Renumeração dos 'level' das cm-rows remanescentes foi
            // removida deliberadamente: get_current_level() agora lê
            // diretamente de course_modules_completion, então o valor
            // do campo 'level' não impacta mais o número exibido no
            // profileCard / chip do header / tooltip da Sua Jornada.
            // Manter os 'level' originais simplifica o helper e evita
            // o efeito colateral de "Nível 5 de 5 estrito" deflando o
            // que historicamente era exibido como "Nível 7" para o
            // aluno.)
        }
        return $deleted;
    }

    /**
     * delete_user_enrolment().
     *
     * @param int $courseid
     * @param int $userid
     */
    private static function delete_user_enrolment(int $courseid, int $userid) {
        global $DB;

        if (!self::is_tracked_course($courseid)) {
            return;
        }
        // Delete course record
        $DB->delete_records('local_studypace', [
            'userid' => $userid,
            'target' => 'course',
            'targetid' => $courseid,
        ]);

        // Delete all cm-level pace rows for this user/course (same scope as update_user_enrolment prune).
        $DB->delete_records('local_studypace', [
            'userid' => $userid,
            'courseid' => $courseid,
            'target' => 'cm',
        ]);
    }

    /**
     * Find the user's current "in-progress" tracked course.
     *
     * @param int $userid
     * @param array|null $formats Optional whitelist of course formats. When
     *   provided, only courses with one of those formats are considered.
     *   Defaults to all tracked formats (specialization + saladeconferencias).
     *   Callers that want to surface only the "Eixos" track should pass
     *   ['specialization'] so the conference-room course doesn't take over
     *   the minidashboard chip.
     */
    public static function get_current_course($userid, ?array $formats = null) {
        global $DB;

        // Explicit JOIN over the implicit comma syntax: legacy Moodle DBs
        // (Oracle in particular) refuse implicit joins under certain
        // ANSI_NULLS settings. Also fixes the ambiguity around the unaliased
        // "target" / "userid" / "estimatedcompletion" fields.
        //
        // The query also filters by an active user_enrolments row on the
        // course. Without that, a user who was auto-enroled in Eixo 1
        // and never completed it (so the studypace course row stays
        // actualcompletion=NULL) but then self-enroled into Eixo 3 and is
        // actively progressing there sees the dashboard stuck on
        // "Eixo 1 em andamento" forever, because the previous ORDER BY
        // sp.id ASC always picks the earliest NULL row. By restricting
        // the candidate set to courses the user is currently enroled in
        // and ORDER BY c.sortorder DESC, the function now returns the
        // LATEST (progression-wise) Eixo the user hasn't finished yet —
        // which is the right "where you are right now" answer for both
        // the dashboard "Sua Jornada" widget and the header minidashboard
        // chip.
        $params = ['userid' => $userid, 'now' => time()];
        $formatfilter = '';
        if ($formats !== null && !empty($formats)) {
            [$insql, $inparams] = $DB->get_in_or_equal($formats, SQL_PARAMS_NAMED, 'fmt');
            $formatfilter = ' AND c.format ' . $insql;
            $params = array_merge($params, $inparams);
        }
        $sql = 'SELECT c.*, sp.actualcompletion '
                . 'FROM {local_studypace} sp '
                . 'INNER JOIN {course} c ON sp.courseid = c.id '
                . 'INNER JOIN {user_enrolments} ue ON ue.userid = sp.userid '
                . 'INNER JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = c.id '
                . "WHERE sp.target = 'course' "
                . 'AND sp.userid = :userid '
                . 'AND sp.estimatedcompletion > 0 '
                . 'AND c.visible = 1 '
                . 'AND ue.status = 0 '
                . 'AND (ue.timeend = 0 OR ue.timeend > :now) '
                . $formatfilter
                . ' ORDER BY c.sortorder DESC, c.shortname DESC';
        $courses = $DB->get_records_sql($sql, $params);
        if (empty($courses)) {
            return null;
        }
        // Iterate from latest progression → earliest. The first row whose
        // actualcompletion is NULL is the user's current "in-progress"
        // Eixo (the latest one they joined and haven't finished). If all
        // enroled Eixos are completed, fall back to the highest-sortorder
        // one (the user's final position in the journey).
        $highestcourse = reset($courses);
        foreach ($courses as $course) {
            if (is_null($course->actualcompletion)) {
                return $course;
            }
        }
        return $highestcourse;
    }

    /**
     * get_current_level().
     *
     * @param mixed $userid
     * @param mixed $courseid
     */
    public static function get_current_level($userid, $courseid) {
        global $DB;

        // "Nível" exibido no profileCard (course view) e no chip do
        // header sempre teve a semântica "quantas coisas você já fez
        // neste curso". Originalmente era MAX(level) das cm-rows do
        // local_studypace, e como o is_cm_monitorable() antigo era
        // permissivo (qualquer cm com completion > 0), o número
        // refletia o engajamento total do aluno no curso (vídeos,
        // SCORMs, enquetes — tudo entrava na conta).
        //
        // Quando is_cm_monitorable() foi corrigido para ser estrito
        // (só conta cms marcadas em /course/completion.php), as cm-rows
        // do local_studypace passaram a refletir só a lista
        // obrigatória — o que é o comportamento certo para os gates
        // (all_cms_completed) e para o percentual de progresso, mas
        // deflacionou esse "Nível N" para quem já havia completado
        // atividades extras como SCORMs/enquetes.
        //
        // Para preservar a UX original do profileCard sem desfazer a
        // correção dos gates, o "Nível" agora é computado direto da
        // course_modules_completion do Moodle (fonte de verdade do
        // próprio engine de conclusão), contando todas as cms com
        // completion habilitada que o aluno concluiu. Assim valores
        // como "Nível 7 do Eixo 3" continuam aparecendo mesmo após o
        // strict-mode rolar nas demais funções.
        $sql = "SELECT COUNT(cmc.id) AS lvl
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :courseid
                   AND cm.visible = 1
                   AND cm.completion > 0
                   AND cmc.userid = :userid
                   AND cmc.completionstate IN (:ccomplete, :cpass, :cfail)";
        $record = $DB->get_record_sql($sql, [
            'courseid' => $courseid,
            'userid' => $userid,
            'ccomplete' => COMPLETION_COMPLETE,
            'cpass' => COMPLETION_COMPLETE_PASS,
            'cfail' => COMPLETION_COMPLETE_FAIL,
        ]);
        return $record ? (int) $record->lvl : 0;
    }

    /**
     * get_new_levels_in_a_month().
     *
     * @param mixed $userid
     * @param mixed $timewithinmonth
     */
    public static function get_new_levels_in_a_month($userid, $timewithinmonth) {
        global $DB;

        // Use Moodle's user timezone helpers so the month boundary respects
        // the user's configured TZ. The old strtotime(date(...)) round-tripped
        // through the server's PHP default TZ and could shift the window by
        // up to a day for users in the Americas (-3h) compared to a UTC PHP
        // process.
        $tz = \core_date::get_user_timezone_object();
        $dt = new \DateTime('@' . $timewithinmonth);
        $dt->setTimezone($tz);
        $startdt = new \DateTime($dt->format('Y-m-01 00:00:00'), $tz);
        $enddt = new \DateTime($dt->format('Y-m-t 23:59:59'), $tz);
        $startofmonth = $startdt->getTimestamp();
        $endofmonth = $enddt->getTimestamp();
        $sql = 'SELECT count(*) as count '
                . 'FROM {local_studypace} '
                . "WHERE target = 'cm' "
                . 'AND userid = :userid '
                . 'AND actualcompletion >= :startofmonth '
                . 'AND actualcompletion <= :endofmonth;';
        $records = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'startofmonth' => $startofmonth,
            'endofmonth' => $endofmonth,
        ]);
        $record = reset($records);
        return $record->count;
    }

    /**
     * Overall dashboard progress percent for a user (0–100).
     *
     * @param int $userid
     * @return int
     */
    public static function get_current_progress($userid) {
        return self::compute_weighted_progress_percent((int) $userid);
    }

    /**
     * Expected dashboard progress percent by elapsed study-plan time (0–100).
     *
     * Continuous: (now − plan_anchor) / (plan_months × month_seconds), capped
     * at 100. Changing 12/18/24 months rescales the forecast immediately.
     *
     * @param int $userid
     * @return int
     */
    public static function get_expected_progress($userid) {
        return self::compute_elapsed_plan_progress_percent((int) $userid);
    }

    /**
     * Fraction of the selected study plan that has already elapsed (0–100).
     *
     * @param int $userid
     * @return int
     */
    private static function compute_elapsed_plan_progress_percent(int $userid): int {
        global $CFG;

        if ($userid <= 0) {
            return 0;
        }

        require_once($CFG->dirroot . '/local/profile/lib.php');
        $months = (int) local_profile_get_user_months($userid);
        if ($months <= 0) {
            $months = 18;
        }

        $planseconds = (float) $months * self::SECONDS_PER_MONTH;
        if ($planseconds <= 0) {
            return 0;
        }

        $elapsed = time() - self::get_user_plan_anchor($userid);
        if ($elapsed <= 0) {
            return 0;
        }

        return min(100, max(0, (int) round(($elapsed / $planseconds) * 100)));
    }

    /**
     * Dashboard progress weighted by course workload hours when configured in
     * local_dashboard; otherwise Eixos 90% / Sala 10% with equal split inside each group.
     *
     * Each course contributes its full weight only when the student finished
     * every mandatory activity AND the course total grade is
     * >= PROGRESS_PASS_GRADE (60%). For Eixos, every graded section
     * (disciplina with cquiz) must also be >= 60%. Partial activity progress
     * no longer moves the overall percentage.
     *
     * @param int $userid
     * @return int 0–100
     */
    private static function compute_weighted_progress_percent(int $userid): int {
        $eixoids = self::get_tracked_course_ids_for_format('specialization', true);
        $saloids = self::get_tracked_course_ids_for_format('saladeconferencias', true);
        $allids = array_merge($eixoids, $saloids);
        if (empty($allids)) {
            return 0;
        }

        $weights = self::resolve_progress_weights($eixoids, $saloids);
        $credited = self::course_ids_passed_for_progress($userid, $allids);

        $progress = 0.0;
        foreach ($allids as $courseid) {
            $share = $weights[$courseid] ?? 0.0;
            if ($share <= 0) {
                continue;
            }
            if (!empty($credited[(int) $courseid])) {
                $progress += $share * 100;
            }
        }

        return min(100, max(0, (int) round($progress, 0)));
    }

    /**
     * Courses that count for current overall progress.
     *
     * Requires activities completed, course total grade >= 60, and — for
     * Eixos only — every graded section (disciplina) also >= 60.
     * Sala de Conferências keeps the course-total gate only.
     *
     * @param int $userid
     * @param int[] $courseids
     * @return array<int,bool> courseid => true when credited
     */
    private static function course_ids_passed_for_progress(int $userid, array $courseids): array {
        global $CFG;

        $passed = [];
        if ($userid <= 0 || empty($courseids)) {
            return $passed;
        }

        self::preload_user_course_completions($userid);
        $grades = self::get_course_total_grades_batch($userid, $courseids);

        $eixoids = [];
        foreach ($courseids as $courseid) {
            $courseid = (int) $courseid;
            if (self::is_eixo_course($courseid)) {
                $eixoids[] = $courseid;
            }
        }
        $sectionpass = [];
        if (!empty($eixoids)) {
            $speclib = $CFG->dirroot . '/course/format/specialization/lib.php';
            if (is_readable($speclib)) {
                require_once($speclib);
                if (class_exists('\\format_specialization')
                        && method_exists('\\format_specialization', 'user_section_grades_pass_map')) {
                    $sectionpass = \format_specialization::user_section_grades_pass_map(
                        $userid,
                        $eixoids,
                        self::PROGRESS_PASS_GRADE
                    );
                }
            }
            if (empty($sectionpass)) {
                // Soft degrade if format helper is missing (old installs).
                $sectionpass = array_fill_keys($eixoids, true);
            }
        }

        foreach ($courseids as $courseid) {
            $courseid = (int) $courseid;
            if (!self::is_course_completed($courseid, $userid)) {
                continue;
            }
            if (($grades[$courseid] ?? 0.0) < self::PROGRESS_PASS_GRADE) {
                continue;
            }
            if (self::is_eixo_course($courseid)
                    && (!array_key_exists($courseid, $sectionpass) || $sectionpass[$courseid] !== true)) {
                continue;
            }
            $passed[$courseid] = true;
        }

        return $passed;
    }

    /**
     * Course-total final grades for one user across several courses (one query).
     *
     * @param int $userid
     * @param int[] $courseids
     * @return array<int,float> courseid => grade (missing = 0)
     */
    private static function get_course_total_grades_batch(int $userid, array $courseids): array {
        global $DB;

        $courseids = array_values(array_filter(array_map('intval', $courseids)));
        $grades = array_fill_keys($courseids, 0.0);
        if ($userid <= 0 || empty($courseids)) {
            return $grades;
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $params['userid'] = $userid;
        $params['itemtype'] = 'course';
        $rows = $DB->get_records_sql(
            "SELECT gi.courseid, gg.finalgrade
               FROM {grade_items} gi
               JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
              WHERE gi.itemtype = :itemtype
                AND gi.courseid {$insql}",
            $params
        );
        foreach ($rows as $row) {
            $cid = (int) $row->courseid;
            $grades[$cid] = ($row->finalgrade === null) ? 0.0 : (float) $row->finalgrade;
        }
        return $grades;
    }

    /**
     * Progress share (0.0–1.0) per visible tracked course.
     *
     * Uses workload hours from local_dashboard when configured; otherwise the
     * legacy 90/10 bucket split with equal weight inside each format group.
     *
     * @param int[] $eixoids
     * @param int[] $saloids
     * @return array<int,float>
     */
    private static function resolve_progress_weights(array $eixoids, array $saloids): array {
        global $CFG;

        $allids = array_merge($eixoids, $saloids);
        $legacy = self::build_legacy_progress_weights($eixoids, $saloids);

        $classfile = $CFG->dirroot . '/local/dashboard/classes/course_workload.php';
        if (!is_readable($classfile)) {
            return $legacy;
        }
        require_once($classfile);
        if (!class_exists('\local_dashboard\course_workload')) {
            return $legacy;
        }

        $weights = \local_dashboard\course_workload::get_progress_weights($allids);
        $configuredtotal = 0.0;
        foreach ($weights as $share) {
            $configuredtotal += $share;
        }
        if ($configuredtotal <= 0) {
            return $legacy;
        }

        return $weights;
    }

    /**
     * Legacy fixed 90% Eixos / 10% Sala weights, split equally within each group.
     *
     * @param int[] $eixoids
     * @param int[] $saloids
     * @return array<int,float> courseid => share 0.0–1.0
     */
    private static function build_legacy_progress_weights(array $eixoids, array $saloids): array {
        $weights = [];
        foreach (array_merge($eixoids, $saloids) as $courseid) {
            $weights[(int) $courseid] = 0.0;
        }
        if (empty($eixoids) && empty($saloids)) {
            return $weights;
        }

        $eixoweight = self::PROGRESS_WEIGHT_EIXOS;
        $salaweight = self::PROGRESS_WEIGHT_SALA;
        if (empty($eixoids)) {
            $eixoweight = 0;
            $salaweight = 100;
        } else if (empty($saloids)) {
            $salaweight = 0;
            $eixoweight = 100;
        }

        if (!empty($eixoids)) {
            $share = ($eixoweight / 100) / count($eixoids);
            foreach ($eixoids as $courseid) {
                $weights[(int) $courseid] = $share;
            }
        }
        if (!empty($saloids)) {
            $share = ($salaweight / 100) / count($saloids);
            foreach ($saloids as $courseid) {
                $weights[(int) $courseid] = $share;
            }
        }

        return $weights;
    }

    /**
     * Share of a course completed (done / total).
     *
     * @param int $done
     * @param int $total
     * @return float 0.0–1.0
     */
    private static function course_progress_ratio(int $done, int $total): float {
        if ($total <= 0) {
            return 0.0;
        }
        return min(1.0, $done / $total);
    }

    /**
     * Mandatory activity counts per course — same rules as is_cm_monitorable().
     *
     * @param int[] $courseids
     * @return array<int,int> courseid => count
     */
    private static function count_mandatory_cms_by_course(array $courseids): array {
        global $DB;

        $counts = [];
        foreach ($courseids as $courseid) {
            $counts[(int) $courseid] = 0;
        }
        if (empty($courseids)) {
            return $counts;
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'tc');
        $params['salaformat'] = 'saladeconferencias';
        $cms = $DB->get_records_sql(
            'SELECT cm.id, cm.course, cm.visible, cm.module, cm.completion '
                . 'FROM {course_modules} cm '
                . 'JOIN {course} c ON c.id = cm.course '
                . 'WHERE cm.course ' . $insql
                . ' AND cm.deletioninprogress = 0 '
                . ' AND (cm.visible = 1 OR c.format = :salaformat)',
            $params
        );
        foreach ($cms as $cm) {
            if (self::is_cm_monitorable((int) $cm->id)) {
                $counts[(int) $cm->course]++;
            }
        }
        return $counts;
    }

    /**
     * Per-course numerator for weighted progress (completed or expected-by-pace).
     *
     * Current progress uses Moodle completion state; expected progress uses
     * studypace deadlines. Both share the is_cm_monitorable() denominator.
     * Hidden Sala videos always count as incomplete.
     *
     * @param int[] $courseids
     * @param int $userid
     * @param bool $expected
     * @return array<int,int> courseid => count
     */
    private static function count_user_progress_cms_by_course(array $courseids, int $userid, bool $expected): array {
        global $DB;

        $counts = [];
        foreach ($courseids as $courseid) {
            $counts[(int) $courseid] = 0;
        }
        if (empty($courseids)) {
            return $counts;
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'tc');
        $params['salaformat'] = 'saladeconferencias';
        $cms = $DB->get_records_sql(
            'SELECT cm.id, cm.course, cm.visible, cm.module, cm.completion '
                . 'FROM {course_modules} cm '
                . 'JOIN {course} c ON c.id = cm.course '
                . 'WHERE cm.course ' . $insql
                . ' AND cm.deletioninprogress = 0 '
                . ' AND (cm.visible = 1 OR c.format = :salaformat)',
            $params
        );
        $now = time();
        foreach ($cms as $cm) {
            $cmid = (int) $cm->id;
            $courseid = (int) $cm->course;
            if (!self::is_cm_monitorable($cmid)) {
                continue;
            }
            if (self::is_sala_conferencias_hidden_video_cm($cm)) {
                continue;
            }
            if ($expected) {
                $pace = $DB->get_record('local_studypace', [
                    'userid' => $userid,
                    'target' => 'cm',
                    'targetid' => $cmid,
                ], 'id, estimatedcompletion', IGNORE_MISSING);
                if ($pace && (int) $pace->estimatedcompletion > 0 && (int) $pace->estimatedcompletion <= $now) {
                    $counts[$courseid]++;
                }
            } else if (self::is_cm_completed($userid, $cmid)) {
                $counts[$courseid]++;
            }
        }
        return $counts;
    }

    /**
     * is_course_completed().
     *
     * @param mixed $courseid
     * @param mixed $userid
     */
    public static function is_course_completed($courseid, $userid) {
        global $DB;

        $courseid = (int) $courseid;
        $userid = (int) $userid;
        $hasmarker = false;
        if (self::$usercoursecompleted !== null && self::$usercoursecompleteduserid === $userid) {
            $hasmarker = isset(self::$usercoursecompleted[$courseid]);
        } else {
            $hasmarker = $DB->record_exists_select(
                'local_studypace',
                'userid = :userid AND target = :target AND targetid = :courseid AND actualcompletion IS NOT NULL',
                ['userid' => $userid, 'target' => 'course', 'courseid' => $courseid]
            );
        }

        if (!$hasmarker) {
            return false;
        }

        // The studypace flag can survive after new mandatory Sala videos are
        // published; always re-check live activity progress for display/gates.
        return self::all_cms_completed($userid, $courseid, 0);
    }

    /**
     * Register visible Sala videos on the Moodle course completion criteria list.
     *
     * @return int Number of criteria rows inserted
     */
    public static function ensure_sala_video_completion_criteria(): int {
        global $CFG;

        $lib = $CFG->dirroot . '/course/format/saladeconferencias/lib.php';
        if (!file_exists($lib)) {
            return 0;
        }
        require_once($lib);
        if (!function_exists('format_saladeconferencias_backfill_video_completion_criteria')) {
            return 0;
        }
        return format_saladeconferencias_backfill_video_completion_criteria();
    }

    /**
     * Whether the user already has any planned_completion keys for a course.
     * Blocks duplicate awards when studypace row ids change or when a second
     * event type (standard vs high_grade) fires for the same completion.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function course_planned_completion_coins_already_awarded(int $userid, int $courseid): bool {
        global $DB;

        if ($userid <= 0 || $courseid <= 0) {
            return false;
        }

        return $DB->record_exists_sql(
            'SELECT 1
               FROM {coin_ledger}
              WHERE userid = :userid
                AND courseid = :courseid
                AND amount > 0
                AND ' . $DB->sql_like('action', ':pattern', false),
            [
                'userid' => $userid,
                'courseid' => $courseid,
                'pattern' => '%planned_completion%',
            ]
        );
    }

    /**
     * @deprecated Use {@see course_planned_completion_coins_already_awarded()}.
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function sala_completion_coins_already_awarded(int $userid, int $courseid): bool {
        return self::course_planned_completion_coins_already_awarded($userid, $courseid);
    }

    /**
     * Preview or remove all planned_completion keys for one user/course pair.
     *
     * @param int $userid
     * @param int $courseid
     * @param bool $dryrun
     * @return \stdClass
     */
    public static function revoke_course_planned_completion_coins(int $userid, int $courseid, bool $dryrun = true): \stdClass {
        global $DB;

        $result = new \stdClass();
        $result->valid = false;
        $result->dryrun = $dryrun;
        $result->executed = false;
        $result->userid = (int) $userid;
        $result->courseid = (int) $courseid;
        $result->ledger_rows = 0;
        $result->coins_total = 0;
        $result->errors = [];

        if ($userid <= 0 || $courseid <= 0) {
            $result->errors[] = 'missing_ids';
            return $result;
        }

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id, firstname, lastname');
        $course = $DB->get_record('course', ['id' => $courseid], 'id, shortname, fullname');
        if (!$user || !$course) {
            $result->errors[] = 'user_or_course_not_found';
            return $result;
        }
        $result->valid = true;
        $result->user = $user;
        $result->course = $course;

        $params = [
            'userid' => $userid,
            'courseid' => $courseid,
            'pattern' => '%planned_completion%',
        ];
        $result->ledger_rows = (int) $DB->count_records_sql(
            'SELECT COUNT(*)
               FROM {coin_ledger}
              WHERE userid = :userid
                AND courseid = :courseid
                AND amount > 0
                AND ' . $DB->sql_like('action', ':pattern', false),
            $params
        );
        $result->coins_total = (int) $DB->get_field_sql(
            'SELECT COALESCE(SUM(amount), 0)
               FROM {coin_ledger}
              WHERE userid = :userid
                AND courseid = :courseid
                AND amount > 0
                AND ' . $DB->sql_like('action', ':pattern', false),
            $params
        );

        if ($dryrun || $result->ledger_rows === 0) {
            return $result;
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->delete_records_select(
                'coin_ledger',
                'userid = :userid
                 AND courseid = :courseid
                 AND amount > 0
                 AND ' . $DB->sql_like('action', ':pattern', false),
                $params
            );

            $sum = (int) $DB->get_field_sql(
                'SELECT COALESCE(SUM(amount), 0)
                   FROM {coin_ledger}
                  WHERE userid = :userid
                    AND courseid = :courseid',
                ['userid' => $userid, 'courseid' => $courseid]
            );
            if ($sum <= 0) {
                $DB->delete_records('coin_stack', ['userid' => $userid, 'courseid' => $courseid]);
            } else if ($DB->record_exists('coin_stack', ['userid' => $userid, 'courseid' => $courseid])) {
                $DB->set_field('coin_stack', 'coins', $sum, ['userid' => $userid, 'courseid' => $courseid]);
            }

            $transaction->allow_commit();
            $result->executed = true;

            if (class_exists('\cache')) {
                try {
                    \cache::make('local_dashboard', 'coinranking')->purge();
                } catch (\Throwable $e) {
                    $result->errors[] = 'cache_purge_failed';
                }
            }
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            $result->errors[] = $e->getMessage();
        }

        return $result;
    }

    /**
     * get_num_courses_completed().
     *
     * @param mixed $userid
     */
    public static function get_num_courses_completed($userid) {
        global $DB;

        if (self::$usercoursecompleted !== null && self::$usercoursecompleteduserid === (int) $userid) {
            return count(self::$usercoursecompleted);
        }

        return (int) $DB->count_records_select(
            'local_studypace',
            "userid = :userid AND target = 'course' AND actualcompletion IS NOT NULL",
            ['userid' => $userid]
        );
    }

    /**
     * is_enroled().
     *
     * @param mixed $courseid
     * @param mixed $userid
     */
    public static function is_enroled($courseid, $userid) {
        return is_enrolled(\context_course::instance($courseid), $userid);
    }

    /**
     * all_previous_courses_completed().
     *
     * @param mixed $courses
     * @param mixed $courseid
     * @param mixed $userid
     */
    public static function all_previous_courses_completed($courses, $courseid, $userid) {
        foreach ($courses as $course) {
            if ($course->id == $courseid) {
                return true;
            }
            if (!self::is_course_completed($course->id, $userid)) {
                return false;
            }
        }
        // If $courseid is not in $courses at all there are no previous courses
        // to gate on, so consider the precondition satisfied.
        return true;
    }

    /**
     * get_first_not_enroled_course_id().
     *
     * @param mixed $courses
     * @param mixed $userid
     */
    public static function get_first_not_enroled_course_id($courses, $userid) {
        foreach ($courses as $course) {
            if (self::is_course_completed($course->id, $userid)) {
                continue;
            }
            if (self::is_enroled($course->id, $userid)) {
                return 0;
            }
            return $course->id;
        }
        return 0;
    }

    /**
     * Grade used by mark_course_completed() to choose between
     * planned_completion and planned_completion_high_grade.
     *
     * @param int $userid
     * @param int $courseid
     * @return float
     */
    public static function get_course_grade_for_gamification(int $userid, int $courseid): float {
        global $DB, $CFG;

        require_once($CFG->libdir . '/gradelib.php');
        $grade = 0.0;
        $gradinginfo = grade_get_grades($courseid, 'course', null, null, $userid);
        if (!empty($gradinginfo->items[0]->grades[$userid]->grade)) {
            $grade = (float) $gradinginfo->items[0]->grades[$userid]->grade;
        }
        if (
            $grade == 0.0 && class_exists('\\format_specialization')
                && method_exists('\\format_specialization', 'get_student_grade')
        ) {
            $course = $DB->get_record('course', ['id' => $courseid], 'id, format');
            if ($course && $course->format === 'specialization') {
                $grade = (float) \format_specialization::get_student_grade($userid, $courseid);
            }
        }
        return $grade;
    }

    /**
     * Per-course gamification diagnostic for the Recalcular admin UI.
     *
     * Explains whether keys (local_coin) and badges (local_autobadge) would
     * have fired for this user/course. Both plugins listen to
     * planned_completion / planned_completion_high_grade, which
     * mark_course_completed() only triggers for specialization (Eixo) courses
     * when actualcompletion is set for the first time AND completion is within
     * GAMIFICATION_DEADLINE_DAYS of enrolment in that Eixo (not the dashboard
     * study-plan estimatedcompletion).
     *
     * @param int $userid
     * @param int $courseid
     * @return \stdClass Plain object safe to pass to get_string()
     */
    public static function get_gamification_diagnostic(int $userid, int $courseid): \stdClass {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'id, shortname, fullname, format');
        if (!$course) {
            $empty = new \stdClass();
            $empty->valid = false;
            return $empty;
        }

        $diag = new \stdClass();
        $diag->valid = true;
        $diag->courseid = $courseid;
        $diag->courseshortname = $course->shortname;
        $diag->coursename = $course->fullname;
        $diag->is_eixo = ($course->format === 'specialization');

        $courserecord = $DB->get_record('local_studypace', [
            'userid' => $userid,
            'target' => 'course',
            'targetid' => $courseid,
        ]);

        $diag->has_pace_row = (bool) $courserecord;
        // Plan deadline (dashboard expected progress) — informational only here.
        $diag->estimatedcompletion = $courserecord ? (int) $courserecord->estimatedcompletion : 0;
        $diag->actualcompletion = ($courserecord && !empty($courserecord->actualcompletion))
            ? (int) $courserecord->actualcompletion
            : null;
        $diag->estimatedcompletiontxt = $diag->estimatedcompletion > 0
            ? userdate($diag->estimatedcompletion)
            : '—';
        $diag->actualcompletiontxt = $diag->actualcompletion
            ? userdate($diag->actualcompletion)
            : '—';

        $diag->enrolment_timestart = self::get_user_course_enrolment_timestart($userid, $courseid);
        $diag->gamification_deadline = self::get_gamification_deadline($userid, $courseid);
        $diag->gamification_deadlinetxt = $diag->gamification_deadline > 0
            ? userdate($diag->gamification_deadline)
            : '—';

        $diag->course_marked_complete = ($diag->actualcompletion !== null);

        $cmstracked = (int) $DB->count_records('local_studypace', [
            'userid' => $userid,
            'courseid' => $courseid,
            'target' => 'cm',
        ]);
        $cmsdone = (int) $DB->count_records_select(
            'local_studypace',
            "userid = :userid AND courseid = :courseid AND target = 'cm' AND actualcompletion IS NOT NULL",
            ['userid' => $userid, 'courseid' => $courseid]
        );
        $diag->mandatory_total = $cmstracked;
        $diag->mandatory_done = $cmsdone;
        $diag->all_mandatory_done = ($cmstracked > 0 && $cmsdone >= $cmstracked);

        $diag->simulated_completion_time = null;
        if (!$diag->course_marked_complete && $diag->all_mandatory_done) {
            $maxtime = $DB->get_field_sql(
                "SELECT MAX(actualcompletion)
                   FROM {local_studypace}
                  WHERE userid = :userid
                    AND courseid = :courseid
                    AND target = 'cm'",
                ['userid' => $userid, 'courseid' => $courseid]
            );
            $diag->simulated_completion_time = $maxtime ? (int) $maxtime : null;
        }

        if (!$diag->is_eixo) {
            // Sala (and any non-Eixo): never medals/keys via planned_completion.
            $diag->completed_on_time = false;
            $diag->planned_event_key = 'not_applicable';
            $diag->grade = 0.0;
            $diag->badge_standard_name = $course->shortname;
            $diag->badge_master_name = $course->shortname . 'master';
            $diag->badge_standard_exists = false;
            $diag->badge_master_exists = false;
            $diag->badge_expected_name = '';
            $diag->badge_expected_exists = false;
            $diag->coin_trigger_standard = null;
            $diag->coin_trigger_highgrade = null;
            $diag->coins_credited_course = 0;
            $diag->badges_issued_list = [];
            $diag->badge_issued = false;
            $diag->badge_issued_names = '';
            $diag->gamification_expected = false;
            $diag->gamification_delivered = false;
            return $diag;
        }

        if ($diag->course_marked_complete) {
            $diag->completed_on_time = self::is_completion_on_time_for_gamification(
                $userid,
                $courseid,
                (int) $diag->actualcompletion
            );
        } else if ($diag->simulated_completion_time !== null) {
            $diag->completed_on_time = self::is_completion_on_time_for_gamification(
                $userid,
                $courseid,
                (int) $diag->simulated_completion_time
            );
        } else {
            $diag->completed_on_time = null;
        }

        $diag->grade = 0.0;
        if ($diag->course_marked_complete || $diag->all_mandatory_done) {
            $diag->grade = self::get_course_grade_for_gamification($userid, $courseid);
        }

        // Mirror mark_course_completed() fork (grade >= 80 → high_grade event).
        $diag->planned_event_key = 'none';
        if ($diag->course_marked_complete && $diag->completed_on_time === true) {
            $diag->planned_event_key = ($diag->grade >= 80)
                ? 'high_grade'
                : 'standard';
        } else if (
            !$diag->course_marked_complete && $diag->all_mandatory_done
                && $diag->completed_on_time === true
        ) {
            $diag->planned_event_key = ($diag->grade >= 80)
                ? 'would_high_grade'
                : 'would_standard';
        } else if ($diag->course_marked_complete && $diag->completed_on_time === false) {
            $diag->planned_event_key = 'late';
        } else if (!$diag->all_mandatory_done) {
            $diag->planned_event_key = 'incomplete';
        } else {
            $diag->planned_event_key = 'late';
        }

        // Badge names follow local_autobadge::get_badge_to_award().
        $diag->badge_standard_name = $course->shortname;
        $diag->badge_master_name = $course->shortname . 'master';
        if (class_exists('\\local_autobadge\\autobadge')) {
            $diag->badge_standard_exists = (bool) \local_autobadge\autobadge::resolve_badge_record(
                $courseid,
                $diag->badge_standard_name
            );
            $diag->badge_master_exists = (bool) \local_autobadge\autobadge::resolve_badge_record(
                $courseid,
                $diag->badge_master_name
            );
        } else {
            $diag->badge_standard_exists = $DB->record_exists('badge', ['name' => $diag->badge_standard_name, 'courseid' => $courseid]);
            $diag->badge_master_exists = $DB->record_exists('badge', ['name' => $diag->badge_master_name, 'courseid' => $courseid]);
        }
        $expectedbadgename = ($diag->grade >= 90) ? $diag->badge_master_name : $diag->badge_standard_name;
        $diag->badge_expected_name = $expectedbadgename;

        if (class_exists('\\local_autobadge\\autobadge')) {
            $expectedbadge = \local_autobadge\autobadge::resolve_badge_record($courseid, $expectedbadgename);
            $diag->badge_expected_exists = (bool) $expectedbadge;
        } else {
            $diag->badge_expected_exists = $DB->record_exists('badge', ['name' => $expectedbadgename, 'courseid' => $courseid]);
        }

        // Coin triggers from site config (may differ from plugin defaults).
        $diag->coin_trigger_standard = null;
        $diag->coin_trigger_highgrade = null;
        $triggersjson = get_config('local_coin', 'triggersjson');
        if ($triggersjson) {
            $triggers = json_decode($triggersjson, true);
            if (is_array($triggers)) {
                $stdkey = '\\local_studypace\\event\\planned_completion';
                $hgkey = '\\local_studypace\\event\\planned_completion_high_grade';
                if (isset($triggers[$stdkey]['amount'])) {
                    $diag->coin_trigger_standard = (int) $triggers[$stdkey]['amount'];
                }
                if (isset($triggers[$hgkey]['amount'])) {
                    $diag->coin_trigger_highgrade = (int) $triggers[$hgkey]['amount'];
                }
            }
        }

        // All positive ledger rows for this course (same scope the dashboard
        // ultimately reflects via coin_stack). Do not filter by action name:
        // production sites may still have legacy triggers
        // (grade_a_completion, etc.) or escaped event paths that fail a
        // narrow LIKE '%planned_completion/%' match.
        $diag->coins_credited_course = (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(amount), 0)
               FROM {coin_ledger}
              WHERE userid = :userid
                AND courseid = :courseid
                AND amount > 0",
            ['userid' => $userid, 'courseid' => $courseid]
        );

        $issuedbadges = self::get_course_badges_issued($userid, $courseid);
        $diag->badges_issued_list = $issuedbadges;
        $diag->badge_issued = !empty($issuedbadges);
        if (!empty($issuedbadges)) {
            $names = array_map(function ($row) {
                return $row->name;
            }, $issuedbadges);
            $diag->badge_issued_names = implode(', ', $names);
        } else {
            $diag->badge_issued_names = '';
        }

        $diag->gamification_expected = ($diag->planned_event_key === 'standard'
            || $diag->planned_event_key === 'high_grade');
        $diag->gamification_delivered = ($diag->coins_credited_course > 0 || $diag->badge_issued);

        return $diag;
    }

    /**
     * Badges issued to a user that belong to a course (shortname or master).
     *
     * @param int $userid
     * @param int $courseid
     * @return array<int, \stdClass> badgeid => {id, name, dateissued}
     */
    public static function get_course_badges_issued(int $userid, int $courseid): array {
        global $DB;

        $shortname = $DB->get_field('course', 'shortname', ['id' => $courseid]);
        if (!$shortname) {
            return [];
        }
        $names = [$shortname, $shortname . 'master'];
        [$insql, $params] = $DB->get_in_or_equal($names, SQL_PARAMS_NAMED, 'bn');
        $params['userid'] = $userid;
        // Match purely by badge name (shortname / shortnamemaster), exactly
        // like gamification_gaps::find()'s badge map. Eixo badges on this
        // site are SITE badges: core stores those with badge.courseid = NULL
        // (some imports use 0). An earlier "courseid = :courseid OR 0" filter
        // matched neither NULL nor the course id, so award_badge() issued the
        // badge correctly yet this lookup reported it missing — producing the
        // false "badge not issued" error during repair while find() found no
        // gap. Course shortnames are unique, so name-only matching is safe.
        return $DB->get_records_sql(
            "SELECT b.id, b.name, bi.dateissued
               FROM {badge_issued} bi
               JOIN {badge} b ON b.id = bi.badgeid
              WHERE bi.userid = :userid
                AND b.name $insql",
            $params
        );
    }

    /**
     * Coin ledger rows for a user (optionally filtered by course).
     *
     * @param int $userid
     * @param int $courseid 0 = all courses
     * @return array<int, \stdClass>
     */
    public static function get_user_coin_ledger(int $userid, int $courseid = 0): array {
        global $DB;

        $select = 'userid = :userid';
        $params = ['userid' => $userid];
        if ($courseid > 0) {
            $select .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        return $DB->get_records_select('coin_ledger', $select, $params, 'transactiontime DESC');
    }

    /**
     * All badges issued to a user (same data source as the dashboard).
     *
     * @param int $userid
     * @return array<int, \stdClass> rows with badgeid, name, description, dateissued
     */
    public static function get_user_badges_issued(int $userid): array {
        global $DB;

        return $DB->get_records_sql(
            "SELECT bi.badgeid AS id, b.name, b.description, bi.dateissued
               FROM {badge_issued} bi
               JOIN {badge} b ON b.id = bi.badgeid
              WHERE bi.userid = :userid
              ORDER BY bi.dateissued DESC",
            ['userid' => $userid]
        );
    }

    /**
     * User-level gamification summary (total keys + trigger config health).
     *
     * @param int $userid
     * @return \stdClass
     */
    public static function get_user_gamification_summary(int $userid): \stdClass {
        global $DB;

        $summary = new \stdClass();
        // Sum coin_stack directly so admin diagnostics do not depend on
        // instantiating local_coin\coin_stack (avoids fatals if $CFG scope
        // or plugin autoload differs from the dashboard code path).
        $summary->total_keys = 0;
        if ($DB->get_manager()->table_exists('coin_stack')) {
            $summary->total_keys = (int) $DB->get_field_sql(
                'SELECT COALESCE(SUM(coins), 0) FROM {coin_stack} WHERE userid = :userid',
                ['userid' => $userid]
            );
        }

        $summary->triggers_configured = gamification_gaps::triggers_configured();
        $summary->coin_trigger_standard = null;
        $summary->coin_trigger_highgrade = null;
        $triggersjson = get_config('local_coin', 'triggersjson');
        if ($triggersjson) {
            $triggers = json_decode($triggersjson, true);
            if (is_array($triggers)) {
                $stdkey = '\\local_studypace\\event\\planned_completion';
                $hgkey = '\\local_studypace\\event\\planned_completion_high_grade';
                if (isset($triggers[$stdkey]['amount'])) {
                    $summary->coin_trigger_standard = (int) $triggers[$stdkey]['amount'];
                }
                if (isset($triggers[$hgkey]['amount'])) {
                    $summary->coin_trigger_highgrade = (int) $triggers[$hgkey]['amount'];
                }
            }
        }

        $summary->total_badges = (int) $DB->count_records_sql(
            'SELECT COUNT(bi.id) FROM {badge_issued} bi WHERE bi.userid = :userid',
            ['userid' => $userid]
        );

        return $summary;
    }

    /**
     * Drop in-memory completion cache for one user/course pair.
     *
     * @param int $userid
     * @param int $courseid
     */
    private static function bust_user_course_completion_cache(int $userid, int $courseid): void {
        if (self::$usercoursecompleted !== null && self::$usercoursecompleteduserid === $userid) {
            unset(self::$usercoursecompleted[$courseid]);
        }
    }

    /**
     * Clear studypace and Moodle aggregate course completion without touching
     * per-activity progress or gamification keys.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool True when a completion marker was removed.
     */
    public static function clear_course_completion_marker(int $userid, int $courseid): bool {
        global $DB;

        $record = $DB->get_record('local_studypace', [
            'userid' => $userid,
            'target' => 'course',
            'targetid' => $courseid,
        ], 'id, actualcompletion', IGNORE_MISSING);
        if (!$record || empty($record->actualcompletion)) {
            return false;
        }

        $DB->set_field('local_studypace', 'actualcompletion', null, ['id' => $record->id]);
        self::bust_user_course_completion_cache($userid, $courseid);

        if ($DB->get_manager()->table_exists('course_completions')) {
            $DB->execute(
                'UPDATE {course_completions}
                    SET timecompleted = NULL,
                        reaggregate = 1
                  WHERE userid = :userid
                    AND course = :courseid
                    AND timecompleted IS NOT NULL',
                ['userid' => $userid, 'courseid' => $courseid]
            );
        }

        return true;
    }

    /**
     * Remove aggregate completion when mandatory activities remain open.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool True when a stale completion marker was cleared.
     */
    public static function revalidate_course_completion(int $userid, int $courseid): bool {
        global $DB;

        if ($userid <= 0 || $courseid <= 0) {
            return false;
        }
        if (
            !$DB->record_exists_select(
                'local_studypace',
                'userid = :userid AND target = :target AND targetid = :courseid AND actualcompletion IS NOT NULL',
                ['userid' => $userid, 'target' => 'course', 'courseid' => $courseid]
            )
        ) {
            return false;
        }
        if (!self::all_cms_completed($userid, $courseid, 0)) {
            return self::clear_course_completion_marker($userid, $courseid);
        }
        return false;
    }

    /**
     * Find users marked complete while mandatory activities are still open.
     *
     * @param int $userid Restrict to one user (0 = all).
     * @param int[] $courseids Empty = every tracked course.
     * @return \stdClass[]
     */
    public static function find_stale_course_completions(int $userid = 0, array $courseids = []): array {
        global $DB;

        if (empty($courseids)) {
            $courseids = self::get_tracked_course_ids(false);
        }
        $courseids = array_values(array_filter(array_map('intval', $courseids)));
        if (empty($courseids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'stalecourse');
        $usersql = '';
        if ($userid > 0) {
            $params['filteruserid'] = $userid;
            $usersql = ' AND sp.userid = :filteruserid';
        }

        $stale = [];
        $rs = $DB->get_recordset_sql(
            "SELECT sp.userid, sp.courseid, sp.actualcompletion
               FROM {local_studypace} sp
              WHERE sp.target = 'course'
                AND sp.actualcompletion IS NOT NULL
                AND sp.courseid $insql
                $usersql
           ORDER BY sp.courseid, sp.userid",
            $params
        );
        foreach ($rs as $row) {
            $uid = (int) $row->userid;
            $cid = (int) $row->courseid;
            if (!self::all_cms_completed($uid, $cid, 0)) {
                $stale[] = $row;
            }
        }
        $rs->close();

        return $stale;
    }

    /**
     * Preview stale aggregate completions for admin tools.
     *
     * @param int $userid
     * @param int[] $courseids
     * @return \stdClass
     */
    public static function get_stale_course_completions_preview(int $userid = 0, array $courseids = []): \stdClass {
        global $DB;

        self::ensure_sala_video_completion_criteria();

        $userid = (int) $userid;
        $preview = new \stdClass();
        $preview->valid = false;
        $preview->userid = $userid;
        $preview->targetuser = null;
        $preview->courseids = $courseids;
        $preview->stale_count = 0;
        $preview->stale_users = 0;
        $preview->details = [];

        if (empty($courseids)) {
            $preview->courseids = self::get_tracked_course_ids(false);
        }
        if (empty($preview->courseids)) {
            return $preview;
        }

        if ($userid > 0) {
            $preview->targetuser = $DB->get_record(
                'user',
                ['id' => $userid, 'deleted' => 0],
                'id, firstname, lastname, username, email'
            );
            if (!$preview->targetuser) {
                $preview->error = 'user_not_found';
                return $preview;
            }
        }

        $preview->valid = true;
        $stale = self::find_stale_course_completions($userid, $preview->courseids);
        $preview->stale_count = count($stale);
        $userids = [];
        $coursenames = $DB->get_records_list('course', 'id', $preview->courseids, '', 'id, shortname, fullname');
        foreach ($stale as $row) {
            $userids[(int) $row->userid] = true;
            if (count($preview->details) < 100) {
                $user = $DB->get_record('user', ['id' => (int) $row->userid], 'id, firstname, lastname', IGNORE_MISSING);
                $course = $coursenames[(int) $row->courseid] ?? null;
                $preview->details[] = (object) [
                    'userid' => (int) $row->userid,
                    'courseid' => (int) $row->courseid,
                    'userfullname' => $user ? fullname($user) : ('id=' . (int) $row->userid),
                    'courseshortname' => $course ? $course->shortname : ('id=' . (int) $row->courseid),
                    'markedon' => (int) $row->actualcompletion,
                ];
            }
        }
        $preview->stale_users = count($userids);

        return $preview;
    }

    /**
     * Clear aggregate completions that no longer match mandatory activity progress.
     *
     * @param bool $dryrun
     * @param int $userid
     * @param int[] $courseids
     * @return \stdClass
     */
    public static function revalidate_stale_course_completions(
        bool $dryrun = true,
        int $userid = 0,
        array $courseids = []
    ): \stdClass {
        $preview = self::get_stale_course_completions_preview($userid, $courseids);
        $preview->dryrun = $dryrun;
        $preview->executed = false;
        $preview->cleared = 0;

        if (!$preview->valid || $dryrun || $preview->stale_count === 0) {
            return $preview;
        }

        foreach (self::find_stale_course_completions($userid, $preview->courseids) as $row) {
            if (self::clear_course_completion_marker((int) $row->userid, (int) $row->courseid)) {
                $preview->cleared++;
            }
        }
        $preview->executed = true;

        return $preview;
    }

    /**
     * Preview counts for resetting Sala de Conferências course-level completion
     * and keys. Per-video progress is not affected.
     *
     * @param int $userid Restrict to one user (0 = all users with Sala data).
     * @return \stdClass
     */
    public static function get_sala_conferencias_reset_preview(int $userid = 0): \stdClass {
        global $DB;

        $userid = (int) $userid;
        $preview = new \stdClass();
        $preview->valid = false;
        $preview->userid = $userid;
        $preview->targetuser = null;
        $preview->courseids = self::get_tracked_course_ids_for_format('saladeconferencias', false);
        if (empty($preview->courseids)) {
            return $preview;
        }

        if ($userid > 0) {
            $preview->targetuser = $DB->get_record(
                'user',
                ['id' => $userid, 'deleted' => 0],
                'id, firstname, lastname, username, email'
            );
            if (!$preview->targetuser) {
                $preview->error = 'user_not_found';
                return $preview;
            }
        }

        $preview->valid = true;
        [$insql, $params] = $DB->get_in_or_equal($preview->courseids, SQL_PARAMS_NAMED, 'sc');
        $params['pattern'] = '%planned_completion%';
        $usersql = '';
        if ($userid > 0) {
            $params['filteruserid'] = $userid;
            $usersql = ' AND userid = :filteruserid';
        }

        $preview->courses = $DB->get_records_list(
            'course',
            'id',
            $preview->courseids,
            'shortname',
            'id, shortname, fullname'
        );

        $preview->coins_ledger_rows = (int) $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {coin_ledger}
              WHERE courseid $insql
                AND amount > 0
                AND " . $DB->sql_like('action', ':pattern', false) . "
                $usersql",
            $params
        );
        $preview->coins_total = (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(amount), 0)
               FROM {coin_ledger}
              WHERE courseid $insql
                AND amount > 0
                AND " . $DB->sql_like('action', ':pattern', false) . "
                $usersql",
            $params
        );
        $preview->coins_users = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT userid)
               FROM {coin_ledger}
              WHERE courseid $insql
                AND amount > 0
                AND " . $DB->sql_like('action', ':pattern', false) . "
                $usersql",
            $params
        );

        $preview->pace_course_completions = (int) $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {local_studypace}
              WHERE courseid $insql
                AND target = 'course'
                AND actualcompletion IS NOT NULL
                $usersql",
            $params
        );
        $paceusersql = '';
        if ($userid > 0) {
            $paceusersql = ' AND sp.userid = :filteruserid';
        }
        $preview->pace_cm_completions = (int) $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {local_studypace} sp
              WHERE sp.courseid $insql
                AND sp.target = 'cm'
                AND sp.actualcompletion IS NOT NULL
                $paceusersql",
            $params
        );

        $preview->moodle_course_completions = 0;
        $preview->moodle_cm_completions = 0;
        if ($DB->get_manager()->table_exists('course_completions')) {
            $preview->moodle_course_completions = (int) $DB->count_records_sql(
                "SELECT COUNT(*)
                   FROM {course_completions}
                  WHERE course $insql
                    AND timecompleted IS NOT NULL
                    $usersql",
                $params
            );
        }
        $cmusersql = '';
        if ($userid > 0) {
            $cmusersql = ' AND cmc.userid = :filteruserid';
        }
        $preview->moodle_cm_completions = (int) $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {course_modules_completion} cmc
              JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
             WHERE cm.course $insql
               AND cmc.completionstate <> :incomplete
               $cmusersql",
            array_merge($params, ['incomplete' => COMPLETION_INCOMPLETE])
        );

        return $preview;
    }

    /**
     * Remove Sala planned_completion keys and clear only the aggregate course
     * completion (studypace course row + Moodle course_completions). Individual
     * video completions are preserved.
     *
     * @param bool $dryrun When true, only return the preview counts.
     * @param int $userid Restrict to one user (0 = all users).
     * @return \stdClass
     */
    public static function reset_sala_conferencias_progress(bool $dryrun = true, int $userid = 0): \stdClass {
        global $DB;

        $userid = (int) $userid;
        $result = self::get_sala_conferencias_reset_preview($userid);
        $result->dryrun = $dryrun;
        $result->executed = false;
        $result->coins_removed = 0;
        $result->stacks_updated = 0;
        $result->stacks_deleted = 0;
        $result->pace_rows_cleared = 0;
        $result->moodle_course_rows_reset = 0;
        $result->errors = [];

        if (!$result->valid || $dryrun) {
            return $result;
        }

        [$insql, $params] = $DB->get_in_or_equal($result->courseids, SQL_PARAMS_NAMED, 'sc');
        $usersql = '';
        if ($userid > 0) {
            $params['filteruserid'] = $userid;
            $usersql = ' AND userid = :filteruserid';
        }
        $coinparams = $params;
        $coinparams['pattern'] = '%planned_completion%';

        $transaction = $DB->start_delegated_transaction();
        try {
            $affectedpairs = $DB->get_records_sql(
                "SELECT DISTINCT userid, courseid
                   FROM {coin_ledger}
                  WHERE courseid $insql
                    AND amount > 0
                    AND " . $DB->sql_like('action', ':pattern', false) . "
                    $usersql",
                $coinparams
            );

            $result->coins_removed = (int) $DB->count_records_sql(
                "SELECT COUNT(*)
                   FROM {coin_ledger}
                  WHERE courseid $insql
                    AND amount > 0
                    AND " . $DB->sql_like('action', ':pattern', false) . "
                    $usersql",
                $coinparams
            );
            $DB->delete_records_select(
                'coin_ledger',
                "courseid $insql
                 AND amount > 0
                 AND " . $DB->sql_like('action', ':pattern', false) . "
                 $usersql",
                $coinparams
            );

            foreach ($affectedpairs as $pair) {
                $pairuserid = (int) $pair->userid;
                $courseid = (int) $pair->courseid;
                $sum = (int) $DB->get_field_sql(
                    'SELECT COALESCE(SUM(amount), 0)
                       FROM {coin_ledger}
                      WHERE userid = :userid
                        AND courseid = :courseid',
                    ['userid' => $pairuserid, 'courseid' => $courseid]
                );
                if ($sum <= 0) {
                    if ($DB->record_exists('coin_stack', ['userid' => $pairuserid, 'courseid' => $courseid])) {
                        $DB->delete_records('coin_stack', ['userid' => $pairuserid, 'courseid' => $courseid]);
                        $result->stacks_deleted++;
                    }
                } else if ($DB->record_exists('coin_stack', ['userid' => $pairuserid, 'courseid' => $courseid])) {
                    $DB->set_field(
                        'coin_stack',
                        'coins',
                        $sum,
                        ['userid' => $pairuserid, 'courseid' => $courseid]
                    );
                    $result->stacks_updated++;
                }
            }

            $result->pace_rows_cleared = (int) $DB->count_records_sql(
                "SELECT COUNT(*)
                   FROM {local_studypace}
                  WHERE courseid $insql
                    AND target = 'course'
                    AND actualcompletion IS NOT NULL
                    $usersql",
                $params
            );
            $DB->execute(
                "UPDATE {local_studypace}
                    SET actualcompletion = NULL
                  WHERE courseid $insql
                    AND target = 'course'
                    AND actualcompletion IS NOT NULL
                    $usersql",
                $params
            );

            if ($DB->get_manager()->table_exists('course_completions')) {
                $result->moodle_course_rows_reset = (int) $DB->count_records_sql(
                    "SELECT COUNT(*)
                       FROM {course_completions}
                      WHERE course $insql
                        AND timecompleted IS NOT NULL
                        $usersql",
                    $params
                );
                $DB->execute(
                    "UPDATE {course_completions}
                        SET timecompleted = NULL,
                            reaggregate = 1
                      WHERE course $insql
                        AND timecompleted IS NOT NULL
                        $usersql",
                    $params
                );
            }

            $transaction->allow_commit();
            $result->executed = true;

            if (class_exists('\cache')) {
                try {
                    \cache::make('local_dashboard', 'coinranking')->purge();
                } catch (\Throwable $e) {
                    $result->errors[] = 'cache_purge_failed';
                }
            }
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            $result->errors[] = $e->getMessage();
        }

        return $result;
    }
}

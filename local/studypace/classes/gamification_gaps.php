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
 * Find users with on-time Eixo completions but missing gamification rewards.
 *
 * @package   local_studypace
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studypace;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/format/specialization/lib.php');

/**
 * Gamification gap scanner (same rules as Recalcular diagnostic).
 */
class gamification_gaps {

    /** @var string */
    public const FILTER_ANY = 'any';

    /** @var string */
    public const FILTER_BOTH = 'both';

    /** @var string */
    public const FILTER_BADGE = 'badge';

    /** @var string */
    public const FILTER_COINS = 'coins';

    /** @var string Repair scope: keys only. */
    public const REPAIR_COINS = 'coins';

    /** @var string Repair scope: badges only. */
    public const REPAIR_BADGES = 'badges';

    /** @var string Repair scope: keys and badges. */
    public const REPAIR_BOTH = 'both';

    /**
     * @return string[]
     */
    public static function allowed_repair_scopes(): array {
        return [
            self::REPAIR_COINS,
            self::REPAIR_BADGES,
            self::REPAIR_BOTH,
        ];
    }

    /**
     * @return string[]
     */
    public static function allowed_filters(): array {
        return [
            self::FILTER_ANY,
            self::FILTER_BOTH,
            self::FILTER_BADGE,
            self::FILTER_COINS,
        ];
    }

    /**
     * Whether local_coin triggers are configured for planned_completion events.
     *
     * @return bool
     */
    public static function triggers_configured(): bool {
        $triggersjson = get_config('local_coin', 'triggersjson');
        if (!$triggersjson) {
            return false;
        }
        $triggers = json_decode($triggersjson, true);
        return is_array($triggers)
            && isset($triggers['\\local_studypace\\event\\planned_completion']['amount'])
            && isset($triggers['\\local_studypace\\event\\planned_completion_high_grade']['amount']);
    }

    /**
     * Cached coin trigger amounts from local_coin config.
     *
     * @return array{standard: ?int, highgrade: ?int}
     */
    public static function get_coin_trigger_amounts(): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = ['standard' => null, 'highgrade' => null];
        $triggersjson = get_config('local_coin', 'triggersjson');
        if (!$triggersjson) {
            return $cache;
        }
        $triggers = json_decode($triggersjson, true);
        if (!is_array($triggers)) {
            return $cache;
        }

        $stdkey = '\\local_studypace\\event\\planned_completion';
        $hgkey = '\\local_studypace\\event\\planned_completion_high_grade';
        if (isset($triggers[$stdkey]['amount'])) {
            $cache['standard'] = (int) $triggers[$stdkey]['amount'];
        }
        if (isset($triggers[$hgkey]['amount'])) {
            $cache['highgrade'] = (int) $triggers[$hgkey]['amount'];
        }

        return $cache;
    }

    /**
     * Whether a user/course pair is missing keys.
     *
     * The reward is FROZEN at completion time: the event that actually fired
     * (recorded in coin_ledger.action) decides how many keys were due. We do
     * NOT recompute the expected amount from the student's current grade —
     * that produced thousands of phantom "under-credit" gaps when a student
     * completed as standard (+10) and later had their grade rise to >= 80.
     *
     * Gap rules:
     *   - No positive credit at all            → missing.
     *   - A high_grade event was recorded but   → missing (genuine under-credit).
     *     the credited total is below its amount
     *   - Otherwise (standard credit on record) → NOT missing.
     *
     * @param int $credited Sum of positive coin_ledger rows for the course.
     * @param bool $hashighgradeaction A planned_completion_high_grade ledger row exists.
     * @return bool
     */
    public static function is_course_missing_coins(int $credited, bool $hashighgradeaction = false): bool {
        if ($credited <= 0) {
            return true;
        }

        if ($hashighgradeaction) {
            $hg = self::get_coin_trigger_amounts()['highgrade'];
            if ($hg !== null && (int) $hg > 0 && $credited < (int) $hg) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int $userid Restrict to one user (0 = all).
     * @param string $only Gap filter: any|both|badge|coins.
     * @return array<int, \stdClass> Gap rows keyed by userid-courseid.
     */
    public static function find(int $userid = 0, string $only = self::FILTER_ANY): array {
        global $DB;

        if (!in_array($only, self::allowed_filters(), true)) {
            $only = self::FILTER_ANY;
        }

        $eixos = \format_specialization::get_courses_in_format(true);
        if (empty($eixos)) {
            return [];
        }

        $eixoids = array_map('intval', array_keys($eixos));
        list($eixinsql, $eixparams) = $DB->get_in_or_equal($eixoids, SQL_PARAMS_NAMED, 'eixo');

        $cmstats = [];
        $cmrs = $DB->get_recordset_sql(
            "SELECT userid,
                    courseid,
                    COUNT(id) AS total,
                    SUM(CASE WHEN actualcompletion IS NULL THEN 1 ELSE 0 END) AS pending
               FROM {local_studypace}
              WHERE target = 'cm'
                AND courseid $eixinsql
              GROUP BY userid, courseid",
            $eixparams
        );
        foreach ($cmrs as $row) {
            $cmstats[(int) $row->userid . ':' . (int) $row->courseid] = [
                'total' => (int) $row->total,
                'pending' => (int) $row->pending,
            ];
        }
        $cmrs->close();

        $coinmap = [];
        $highgrademap = [];
        $coinrs = $DB->get_recordset_sql(
            "SELECT userid,
                    courseid,
                    COALESCE(SUM(amount), 0) AS credited,
                    MAX(CASE WHEN " . $DB->sql_like('action', ':hgaction') . " THEN 1 ELSE 0 END) AS hasighgrade
               FROM {coin_ledger}
              WHERE amount > 0
                AND courseid $eixinsql
              GROUP BY userid, courseid",
            $eixparams + ['hgaction' => '%planned_completion_high_grade%']
        );
        foreach ($coinrs as $row) {
            $rowkey = (int) $row->userid . ':' . (int) $row->courseid;
            $coinmap[$rowkey] = (int) $row->credited;
            $highgrademap[$rowkey] = ((int) $row->hasighgrade === 1);
        }
        $coinrs->close();

        $badgenames = [];
        foreach ($eixos as $eixo) {
            $sn = $eixo->shortname;
            $badgenames[$sn] = true;
            $badgenames[$sn . 'master'] = true;
        }
        $usermap = [];
        if (!empty($badgenames)) {
            list($bninsql, $bnparams) = $DB->get_in_or_equal(array_keys($badgenames), SQL_PARAMS_NAMED, 'bn');
            // Index by userid + badge name only. Production sites often store
            // Eixo badges as site badges (badge.courseid = 0); matching on
            // badge.courseid = eixo id made every on-time completer look
            // badge-less even when badge_issued rows exist (see verify_gamification.sql).
            $badgers = $DB->get_recordset_sql(
                "SELECT bi.userid, b.name
                   FROM {badge_issued} bi
                   JOIN {badge} b ON b.id = bi.badgeid
                  WHERE b.name $bninsql",
                $bnparams
            );
            foreach ($badgers as $row) {
                $uid = (int) $row->userid;
                if (!isset($usermap[$uid])) {
                    $usermap[$uid] = [];
                }
                $usermap[$uid][$row->name] = true;
            }
            $badgers->close();
        }

        $usersql = '';
        $completionparams = $eixparams;
        if ($userid > 0) {
            $usersql = ' AND sp.userid = :filteruserid';
            $completionparams['filteruserid'] = $userid;
        }

        $gaps = [];
        $rs = $DB->get_recordset_sql(
            "SELECT sp.userid,
                    sp.targetid AS courseid,
                    sp.actualcompletion,
                    u.username,
                    u.firstname,
                    u.lastname
               FROM {local_studypace} sp
               JOIN {user} u ON u.id = sp.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE sp.target = 'course'
                AND sp.targetid $eixinsql
                AND sp.actualcompletion IS NOT NULL
                $usersql
              ORDER BY sp.userid ASC, sp.targetid ASC",
            $completionparams
        );

        foreach ($rs as $row) {
            $uid = (int) $row->userid;
            $cid = (int) $row->courseid;
            $key = $uid . ':' . $cid;
            $actual = (int) $row->actualcompletion;

            // Medals/keys use enrolment + 100 days, not dashboard plan deadlines.
            if (!studypace::is_completion_on_time_for_gamification($uid, $cid, $actual)) {
                continue;
            }

            $cms = $cmstats[$key] ?? ['total' => 0, 'pending' => 0];
            if ($cms['total'] === 0 || $cms['pending'] > 0) {
                continue;
            }

            $eixo = $eixos[$cid];
            $shortname = $eixo->shortname;
            $userbadges = $usermap[$uid] ?? [];
            $hasbadge = !empty($userbadges[$shortname]) || !empty($userbadges[$shortname . 'master']);
            $coins = $coinmap[$key] ?? 0;

            $missingbadge = !$hasbadge;
            $missingcoins = self::is_course_missing_coins($coins, $highgrademap[$key] ?? false);

            if ($only === self::FILTER_BOTH && !($missingbadge && $missingcoins)) {
                continue;
            }
            if ($only === self::FILTER_BADGE && !$missingbadge) {
                continue;
            }
            if ($only === self::FILTER_COINS && !$missingcoins) {
                continue;
            }
            if ($only === self::FILTER_ANY && !$missingbadge && !$missingcoins) {
                continue;
            }

            $gap = new \stdClass();
            $gap->userid = $uid;
            $gap->username = $row->username;
            $gap->firstname = $row->firstname;
            $gap->lastname = $row->lastname;
            $gap->courseid = $cid;
            $gap->courseshortname = $shortname;
            $gap->coursename = $eixo->fullname;
            $gap->actualcompletion = $actual;
            $gap->estimatedcompletion = studypace::get_gamification_deadline($uid, $cid);
            $gap->coins_credited = $coins;
            $gap->has_badge = $hasbadge;
            $gap->missing_badge = $missingbadge;
            $gap->missing_coins = $missingcoins;
            if ($missingbadge && $missingcoins) {
                $gap->gap_type = self::FILTER_BOTH;
            } else if ($missingbadge) {
                $gap->gap_type = self::FILTER_BADGE;
            } else {
                $gap->gap_type = self::FILTER_COINS;
            }

            $gaps[$key] = $gap;
        }
        $rs->close();

        return $gaps;
    }

    /**
     * Enrich gap rows with full diagnostic fields; drops rows not eligible for rewards.
     *
     * @param array<int, \stdClass> $gaps
     * @return void
     */
    public static function enrich(array &$gaps): void {
        self::enrich_slice($gaps, 0, 0);
    }

    /**
     * Enrich gap rows (expensive). When $limit > 0 only processes that slice.
     *
     * @param array<int, \stdClass> $gaps
     * @param int $offset
     * @param int $limit 0 = all rows
     */
    public static function enrich_slice(array &$gaps, int $offset = 0, int $limit = 0): void {
        if ($limit > 0) {
            $gaps = array_slice($gaps, $offset, $limit, true);
        }
        foreach ($gaps as $key => $gap) {
            $g = studypace::get_gamification_diagnostic((int) $gap->userid, (int) $gap->courseid);
            if (empty($g->valid) || !$g->gamification_expected) {
                unset($gaps[$key]);
                continue;
            }
            $gap->grade = round($g->grade, 0);
            $gap->planned_event = $g->planned_event_key;
            $gap->badge_expected = $g->badge_expected_name;
            $gap->badge_issued_names = $g->badge_issued_names;
            $expectedcoins = ($g->planned_event_key === 'high_grade')
                ? $g->coin_trigger_highgrade
                : $g->coin_trigger_standard;
            $gap->coins_expected = $expectedcoins;

            // Refresh badge status only — coin gap stays as computed by find()
            // (frozen at the recorded ledger event; never recomputed from the
            // current grade, which created phantom under-credit gaps).
            $gap->missing_badge = !$g->badge_issued;
            if ($gap->missing_badge && $gap->missing_coins) {
                $gap->gap_type = self::FILTER_BOTH;
            } else if ($gap->missing_badge) {
                $gap->gap_type = self::FILTER_BADGE;
            } else if ($gap->missing_coins) {
                $gap->gap_type = self::FILTER_COINS;
            } else {
                unset($gaps[$key]);
            }
        }
    }

    /**
     * @param array<int, \stdClass> $gaps
     * @return \stdClass Summary counters.
     */
    public static function summarize(array $gaps): \stdClass {
        $summary = new \stdClass();
        $summary->totalrows = count($gaps);
        $summary->users = [];
        $summary->bycourse = [];
        $summary->bytype = [
            self::FILTER_BOTH => 0,
            self::FILTER_BADGE => 0,
            self::FILTER_COINS => 0,
        ];

        foreach ($gaps as $gap) {
            $summary->users[$gap->userid] = true;
            if (!isset($summary->bycourse[$gap->courseid])) {
                $summary->bycourse[$gap->courseid] = (object) [
                    'name' => $gap->coursename,
                    'shortname' => $gap->courseshortname,
                    'rows' => 0,
                    'users' => [],
                ];
            }
            $summary->bycourse[$gap->courseid]->rows++;
            $summary->bycourse[$gap->courseid]->users[$gap->userid] = true;
            if (isset($summary->bytype[$gap->gap_type])) {
                $summary->bytype[$gap->gap_type]++;
            }
        }

        $summary->usercount = count($summary->users);
        return $summary;
    }

    /**
     * @param string $gaptype
     * @return string Lang string id for gap type label.
     */
    public static function gap_type_string_id(string $gaptype): string {
        $map = [
            self::FILTER_BOTH => 'gapsgaptypeboth',
            self::FILTER_BADGE => 'gapsgaptypebadge',
            self::FILTER_COINS => 'gapsgaptypecoins',
        ];
        return $map[$gaptype] ?? 'gapsgaptypeunknown';
    }

    /**
     * Escape a CSV cell against spreadsheet formula injection (Excel/Sheets).
     *
     * @param mixed $value
     * @return string
     */
    public static function csv_cell($value): string {
        if ($value === null || $value === '') {
            return '';
        }
        $text = (string) $value;
        if (preg_match('/^[\s]*[=+\-@]/', $text)) {
            return "'" . $text;
        }
        return $text;
    }

    /**
     * Build CSV content for gap rows.
     *
     * @param array<int, \stdClass> $gaps
     * @return string
     */
    public static function build_csv(array $gaps): string {
        $handle = fopen('php://memory', 'r+');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($handle, [
            'userid', 'username', 'firstname', 'lastname',
            'courseid', 'courseshortname', 'coursename',
            'completed_at', 'deadline_at', 'grade_pct', 'planned_event',
            'badge_expected', 'badge_issued', 'coins_expected', 'coins_credited', 'gap_type',
        ]);
        foreach ($gaps as $gap) {
            fputcsv($handle, [
                $gap->userid,
                self::csv_cell($gap->username),
                self::csv_cell($gap->firstname),
                self::csv_cell($gap->lastname),
                $gap->courseid,
                self::csv_cell($gap->courseshortname),
                self::csv_cell($gap->coursename),
                userdate($gap->actualcompletion, '%Y-%m-%d'),
                userdate($gap->estimatedcompletion, '%Y-%m-%d'),
                $gap->grade ?? '',
                self::csv_cell($gap->planned_event ?? ''),
                self::csv_cell($gap->badge_expected ?? ''),
                self::csv_cell($gap->badge_issued_names ?? ''),
                $gap->coins_expected ?? '',
                $gap->coins_credited,
                self::csv_cell($gap->gap_type),
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        return $csv;
    }

    /**
     * Run the same studypace repair pipeline as Recalcular (single user).
     *
     * @param int $userid
     * @return array<string, int>
     */
    public static function run_user_studypace_repair(int $userid): array {
        global $CFG;

        require_once($CFG->dirroot . '/local/profile/lib.php');

        \local_studypace\studypace::update_all_user_enrolments($userid);
        $revalid = \local_studypace\studypace::revalidate_stale_course_completions(false, $userid);
        $stats = [
            'purged' => \local_studypace\studypace::purge_nonmandatory_cm_rows($userid),
            'synced' => \local_studypace\studypace::sync_cm_completions($userid),
            'revalidated' => (int) ($revalid->cleared ?? 0),
            'marked' => \local_studypace\studypace::repair_pending_course_completions($userid),
            'backfilled' => local_profile_backfill_eixo_progression($userid),
        ];
        return $stats;
    }

    /**
     * Distinct user IDs with gamification gaps matching the filter.
     *
     * Does not call enrich() — that would run one diagnostic per gap row and
     * can timeout when queueing thousands of users from the admin UI. repair_user()
     * re-validates eligibility per user in cron.
     *
     * @param string $only
     * @return int[]
     */
    public static function get_repair_userids(string $only = self::FILTER_ANY): array {
        if (!in_array($only, self::allowed_filters(), true)) {
            $only = self::FILTER_ANY;
        }

        $gaps = self::find(0, $only);
        $ids = [];
        foreach ($gaps as $gap) {
            $ids[(int) $gap->userid] = (int) $gap->userid;
        }
        ksort($ids);
        return array_values($ids);
    }

    /**
     * Whether a repair_gamification_batch adhoc task is already queued or running.
     *
     * @return bool
     */
    public static function has_pending_batch_repair(): bool {
        $tasks = \core\task\manager::get_adhoc_tasks('\\local_studypace\\task\\repair_gamification_batch');
        return !empty($tasks);
    }

    /**
     * Queue an adhoc batch that repairs users in chunks (self-requeues until done).
     *
     * @param string $only Gap filter (any|both|badge|coins).
     * @param string $repairwhat coins|badges|both
     * @param bool $dryrun
     * @param bool $notify
     * @param bool $recalcstudypace
     * @param int $batchsize Users per adhoc run (10–200).
     * @return int Number of users enqueued.
     * @throws \moodle_exception If a batch is already pending.
     */
    public static function queue_batch_repair(
        string $only,
        string $repairwhat,
        bool $dryrun,
        bool $notify,
        bool $recalcstudypace,
        int $batchsize = 50
    ): int {
        if (!in_array($only, self::allowed_filters(), true)) {
            $only = self::FILTER_ANY;
        }
        if (!in_array($repairwhat, self::allowed_repair_scopes(), true)) {
            $repairwhat = self::REPAIR_BOTH;
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_studypace_repair');
        $queuelock = $lockfactory->get_lock('batch_repair_queue', 10);
        if (!$queuelock) {
            throw new \moodle_exception('repairbatchpending', 'local_studypace');
        }
        try {
            if (self::has_pending_batch_repair()) {
                throw new \moodle_exception('repairbatchpending', 'local_studypace');
            }

            @set_time_limit(0);
            raise_memory_limit(MEMORY_HUGE);

            $userids = self::get_repair_userids($only);
            if (empty($userids)) {
                return 0;
            }

            $batchsize = max(10, min(200, $batchsize));

            $task = new \local_studypace\task\repair_gamification_batch();
            $task->set_custom_data((object) [
                'userids' => $userids,
                'offset' => 0,
                'batchsize' => $batchsize,
                'only' => $only,
                'repairwhat' => $repairwhat,
                'dryrun' => $dryrun,
                'notify' => $notify,
                'recalcstudypace' => $recalcstudypace,
            ]);
            \core\task\manager::queue_adhoc_task($task);

            return count($userids);
        } finally {
            $queuelock->release();
        }
    }

    /**
     * Repair missing gamification rewards for one user.
     *
     * @param int $userid
     * @param \stdClass $options repairwhat, dryrun, quiet, recalcstudypace
     * @return \stdClass {progress?, coins, badges}
     */
    public static function repair_user(int $userid, \stdClass $options): \stdClass {
        global $DB;

        $repairwhat = $options->repairwhat ?? self::REPAIR_BOTH;
        if (!in_array($repairwhat, self::allowed_repair_scopes(), true)) {
            $repairwhat = self::REPAIR_BOTH;
        }
        $dryrun = !empty($options->dryrun);
        $quiet = !isset($options->quiet) || $options->quiet;
        $recalc = !empty($options->recalcstudypace);

        $out = new \stdClass();
        $out->progress = null;
        $out->coins = (object) ['credited' => 0, 'skipped' => 0, 'errors' => [], 'details' => []];
        $out->badges = (object) ['awarded' => 0, 'skipped' => 0, 'errors' => [], 'details' => []];

        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            $out->coins->errors[] = 'user_not_found';
            $out->badges->errors[] = 'user_not_found';
            return $out;
        }

        if ($recalc && !$dryrun) {
            $out->progress = self::run_user_studypace_repair($userid);
        }

        if ($repairwhat === self::REPAIR_COINS || $repairwhat === self::REPAIR_BOTH) {
            $out->coins = self::repair_user_coins($userid, $quiet, $dryrun);
        }
        if ($repairwhat === self::REPAIR_BADGES || $repairwhat === self::REPAIR_BOTH) {
            $out->badges = self::repair_user_badges($userid, $quiet, $dryrun);
        }

        return $out;
    }

    /**
     * Award missing keys/badges for one on-time course (idempotent).
     *
     * Called when mark_course_completed() short-circuits because the course
     * row already has actualcompletion, or as a safety net after events fire.
     *
     * @param int $userid
     * @param int $courseid
     * @param bool $quiet Suppress user notifications.
     */
    public static function ensure_course_rewards(int $userid, int $courseid, bool $quiet = true): void {
        $diag = studypace::get_gamification_diagnostic($userid, $courseid);
        if (empty($diag->valid) || !$diag->gamification_expected) {
            return;
        }
        // credit_course_coins_if_due() already absorbs its own throwables, but
        // award_course_badge_if_due() (badge->issue() in particular) can throw
        // — e.g. a misconfigured badge definition or a backpack/notification
        // failure. Isolate each side so a failure on one reward type can't
        // abort the other, and never lets a single bad pair bubble up to the
        // caller (the scheduled heal loop would otherwise fail forever on it).
        try {
            self::credit_course_coins_if_due($userid, $courseid, $quiet);
        } catch (\Throwable $e) {
            debugging('local_studypace ensure_course_rewards: coin heal failed for user '
                    . $userid . ' / course ' . $courseid . ': ' . $e->getMessage(), DEBUG_NORMAL);
        }
        try {
            self::award_course_badge_if_due($userid, $courseid, !$quiet);
        } catch (\Throwable $e) {
            debugging('local_studypace ensure_course_rewards: badge heal failed for user '
                    . $userid . ' / course ' . $courseid . ': ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }

    /**
     * Auto-heal recent on-time completions that still lack keys and/or badges.
     *
     * Bounded so the hourly scheduled task can never run for hours: it stops
     * after $maxprocess heals or once $timebudget seconds elapse, whichever
     * comes first. ensure_course_rewards() is the expensive part (several
     * diagnostic queries per user), so we cap the loop, not the scan.
     *
     * @param int $sincedays Only completions within this many days (default 14).
     * @param int $maxprocess Max heals per call (0 = library default 300).
     * @param int $timebudget Max seconds to spend (0 = library default 120).
     * @return int Number of user/course pairs processed.
     */
    public static function heal_recent_missing_rewards(int $sincedays = 14, int $maxprocess = 0, int $timebudget = 0): int {
        $since = time() - max(1, $sincedays) * DAYSECS;
        $maxprocess = $maxprocess > 0 ? $maxprocess : 300;
        $timebudget = $timebudget > 0 ? $timebudget : 120;
        $deadline = microtime(true) + $timebudget;

        // Crash beacon: a HARD PHP fatal (segfault, OOM, time limit) bypasses
        // try/catch AND dies before Moodle can write the failure to the task
        // log — which is exactly the "log shows only the header line" symptom
        // on sites with no CLI/server-log access. So we persist the pair we
        // are about to touch into plugin config (a committed DB write that
        // survives the crash) and register a shutdown hook that records the
        // last PHP error. The next run surfaces both at the top of its log,
        // where they ARE visible in the web task-log viewer.
        \core_shutdown_manager::register_function([self::class, 'capture_heal_fatal']);
        // Start clean so heal_lasterror only ever reflects THIS run's fatal.
        unset_config('heal_lasterror', 'local_studypace');

        $gaps = self::find(0, self::FILTER_ANY);
        $healed = 0;
        $failed = 0;

        foreach ($gaps as $gap) {
            if ((int) $gap->actualcompletion < $since) {
                continue;
            }
            if ($healed >= $maxprocess || microtime(true) >= $deadline) {
                break;
            }
            set_config('heal_beacon', (int) $gap->userid . ':' . (int) $gap->courseid, 'local_studypace');
            // Per-record isolation: a single poison user/course pair must not
            // abort the whole scheduled task. Before this guard one throwing
            // record made the hourly task fail on every run (0 rows written),
            // so the cron scheduler retried it in a tight loop without ever
            // making progress. Now we skip + log the offender and keep healing.
            try {
                self::ensure_course_rewards((int) $gap->userid, (int) $gap->courseid, true);
                $healed++;
            } catch (\Throwable $e) {
                $failed++;
                set_config('heal_lasterror', 'user ' . (int) $gap->userid . ' / course '
                        . (int) $gap->courseid . ': ' . $e->getMessage(), 'local_studypace');
                debugging('local_studypace heal_recent_missing_rewards: skipping user '
                        . (int) $gap->userid . ' / course ' . (int) $gap->courseid
                        . ' after error: ' . $e->getMessage(), DEBUG_NORMAL);
            }
        }

        // Clean finish: clear the beacon so the next run doesn't report a
        // stale crash. (A hard fatal skips this line, leaving the beacon set.)
        unset_config('heal_beacon', 'local_studypace');

        if ($failed > 0) {
            mtrace('local_studypace: heal_gamification_rewards skipped ' . $failed
                . ' gap row(s) that errored (see debug log).');
        }

        return $healed;
    }

    /**
     * Shutdown hook for heal_recent_missing_rewards(): if the run ended on a
     * fatal PHP error (E_ERROR/E_PARSE/etc., e.g. OOM or "maximum execution
     * time"), record it so the next run can surface it. Segfaults that kill
     * the process outright run no shutdown handler — in that case only the
     * heal_beacon survives, which is itself the signal ("died on this pair,
     * no PHP error captured → native crash").
     */
    public static function capture_heal_fatal(): void {
        $err = error_get_last();
        if (!$err) {
            return;
        }
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
        if (!in_array($err['type'], $fatal, true)) {
            return;
        }
        // set_config can fail if the DB connection itself is gone; ignore.
        try {
            set_config('heal_lasterror', $err['message'] . ' @ ' . $err['file'] . ':' . $err['line'],
                    'local_studypace');
        } catch (\Throwable $ignored) {
            return;
        }
    }

    /**
     * Build the ledger action id for a planned_completion coin award.
     *
     * @param string $eventclass Fully-qualified event class name.
     * @param int $pacerecordid local_studypace course row id.
     * @param int $courseid
     * @return string
     */
    public static function planned_completion_coin_action(string $eventclass, int $pacerecordid, int $courseid): string {
        return $eventclass . '/' . $pacerecordid . '/' . $courseid;
    }

    /**
     * Credit missing or under-credited keys for one on-time course.
     *
     * Uses the gamification diagnostic directly (not the gap scanner) so it
     * still works when observers fail, locks time out, or the student is no
     * longer actively enrolled in the Eixo.
     *
     * @param int $userid
     * @param int $courseid
     * @param bool $quiet Suppress coin award notifications.
     * @param bool $dryrun Simulate only; do not write to the ledger.
     * @return \stdClass {credited, skipped, errors[]}
     */
    public static function credit_course_coins_if_due(
        int $userid,
        int $courseid,
        bool $quiet = true,
        bool $dryrun = false
    ): \stdClass {
        global $DB, $CFG;

        $result = new \stdClass();
        $result->credited = 0;
        $result->skipped = false;
        $result->errors = [];

        if (!self::triggers_configured()) {
            $result->errors[] = 'triggers_not_configured';
            return $result;
        }

        $diag = studypace::get_gamification_diagnostic($userid, $courseid);
        if (empty($diag->valid) || !$diag->gamification_expected) {
            $result->skipped = true;
            return $result;
        }

        $ishigh = ($diag->planned_event_key === 'high_grade');
        $eventclass = $ishigh
            ? '\\local_studypace\\event\\planned_completion_high_grade'
            : '\\local_studypace\\event\\planned_completion';
        $expected = $ishigh ? $diag->coin_trigger_highgrade : $diag->coin_trigger_standard;
        if ($expected === null || (int) $expected <= 0) {
            $result->errors[] = 'no_trigger_amount';
            return $result;
        }
        $expected = (int) $expected;

        $courserecord = $DB->get_record('local_studypace', [
            'userid' => $userid,
            'target' => 'course',
            'targetid' => $courseid,
        ]);
        if (!$courserecord) {
            $result->errors[] = 'missing_pace_row';
            return $result;
        }

        $action = self::planned_completion_coin_action(
            $eventclass,
            (int) $courserecord->id,
            $courseid
        );

        if ($DB->record_exists('coin_ledger', [
            'userid' => $userid,
            'courseid' => $courseid,
            'action' => $action,
        ])) {
            $result->skipped = true;
            return $result;
        }

        $credited = (int) $diag->coins_credited_course;
        // Reward is frozen at the recorded completion event. If any positive
        // credit already exists for this Eixo, do NOT top it up based on the
        // current grade — that recomputation turned correctly-credited
        // standard completions (+10) into thousands of phantom "missing keys"
        // once the student's grade later reached >= 80. Only award when nothing
        // was credited yet (observer failed / historical lost event).
        if ($credited > 0) {
            $result->skipped = true;
            return $result;
        }
        $deficit = $expected;

        if ($dryrun) {
            $result->credited = $deficit;
            return $result;
        }

        require_once($CFG->dirroot . '/local/coin/classes/coin_stack.php');

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_studypace_repair');
        $lockkey = 'coins_' . $userid . '_' . $courseid;
        $lock = null;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $lock = $lockfactory->get_lock($lockkey, 60);
            if ($lock) {
                break;
            }
            usleep(200000);
        }
        if (!$lock) {
            $result->errors[] = 'lock_timeout';
            return $result;
        }

        try {
            if ($DB->record_exists('coin_ledger', [
                'userid' => $userid,
                'courseid' => $courseid,
                'action' => $action,
            ])) {
                $result->skipped = true;
                return $result;
            }

            $creditednow = (int) $DB->get_field_sql(
                "SELECT COALESCE(SUM(amount), 0)
                   FROM {coin_ledger}
                  WHERE userid = :userid
                    AND courseid = :courseid
                    AND amount > 0",
                ['userid' => $userid, 'courseid' => $courseid]
            );
            // Re-check under lock: only credit when still at zero (frozen reward).
            if ($creditednow > 0) {
                $result->skipped = true;
                return $result;
            }

            $stack = new \local_coin\coin_stack($userid, $courseid);
            $stack->stack_coins($expected, $action, null, $quiet);
            $result->credited = $expected;
        } catch (\Throwable $e) {
            $result->errors[] = $e->getMessage();
        } finally {
            $lock->release();
        }

        return $result;
    }

    /**
     * Issue the expected Eixo badge when missing (idempotent).
     *
     * @param int $userid
     * @param int $courseid
     * @param bool $notify
     * @return bool True when a badge was awarded.
     */
    public static function award_course_badge_if_due(int $userid, int $courseid, bool $notify = false): bool {
        global $CFG;

        $diag = studypace::get_gamification_diagnostic($userid, $courseid);
        if (empty($diag->valid) || !$diag->gamification_expected || $diag->badge_issued) {
            return false;
        }
        if (!$diag->badge_expected_exists) {
            return false;
        }
        if (!file_exists($CFG->dirroot . '/local/autobadge/classes/autobadge.php')) {
            return false;
        }
        require_once($CFG->dirroot . '/local/autobadge/classes/autobadge.php');

        \local_autobadge\autobadge::award_badge($userid, $courseid, $notify);
        $after = studypace::get_gamification_diagnostic($userid, $courseid);
        return !empty($after->badge_issued);
    }

    /**
     * Admin override: award the grade-appropriate Eixo badge even when late.
     *
     * Bypasses gamification_expected (on-time gate). Still idempotent: if the
     * badge is already issued, reports already_issued and does nothing.
     *
     * @param int $userid
     * @param int $courseid
     * @param bool $dryrun
     * @param bool $notify
     * @return \stdClass
     */
    public static function force_award_course_badge(
        int $userid,
        int $courseid,
        bool $dryrun = true,
        bool $notify = false
    ): \stdClass {
        global $CFG, $DB;

        $result = new \stdClass();
        $result->valid = false;
        $result->dryrun = $dryrun;
        $result->executed = false;
        $result->awarded = false;
        $result->already_issued = false;
        $result->userid = $userid;
        $result->courseid = $courseid;
        $result->badge_name = '';
        $result->badge_exists = false;
        $result->grade = null;
        $result->ontime = null;
        $result->user = null;
        $result->course = null;
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

        if (!file_exists($CFG->dirroot . '/local/autobadge/classes/autobadge.php')) {
            $result->errors[] = 'autobadge_missing';
            return $result;
        }
        require_once($CFG->dirroot . '/local/autobadge/classes/autobadge.php');

        $diag = studypace::get_gamification_diagnostic($userid, $courseid);
        if (empty($diag->valid)) {
            $result->errors[] = 'diagnostic_invalid';
            return $result;
        }

        $result->badge_name = (string) ($diag->badge_expected_name ?? '');
        $result->badge_exists = !empty($diag->badge_expected_exists);
        $result->grade = $diag->grade ?? null;
        $result->ontime = $diag->completed_on_time ?? null;
        $result->already_issued = !empty($diag->badge_issued);

        if ($result->already_issued) {
            if (!empty($diag->badge_issued_names)) {
                $result->badge_name = (string) $diag->badge_issued_names;
            }
            return $result;
        }

        if (!$result->badge_exists || $result->badge_name === '') {
            $result->errors[] = 'badge_not_found';
            return $result;
        }

        if ($dryrun) {
            return $result;
        }

        try {
            \local_autobadge\autobadge::award_badge($userid, $courseid, $notify);
            $after = studypace::get_gamification_diagnostic($userid, $courseid);
            $result->executed = true;
            $result->awarded = !empty($after->badge_issued);
            $result->already_issued = !empty($after->badge_issued);
            if (!empty($after->badge_issued_names)) {
                $result->badge_name = (string) $after->badge_issued_names;
            }
            if (!$result->awarded) {
                $result->errors[] = 'award_failed';
            }
        } catch (\Throwable $e) {
            $result->errors[] = $e->getMessage();
        }

        return $result;
    }

    /**
     * @param int $userid
     * @param bool $quiet
     * @param bool $dryrun
     * @param int $courseid Restrict to one course (0 = all gaps for user).
     * @return \stdClass
     */
    public static function repair_user_badges(int $userid, bool $quiet = true, bool $dryrun = false, int $courseid = 0): \stdClass {
        global $CFG, $DB;

        $result = new \stdClass();
        $result->awarded = 0;
        $result->skipped = 0;
        $result->errors = [];
        $result->details = [];

        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            $result->errors[] = 'user_not_found';
            return $result;
        }

        if (!file_exists($CFG->dirroot . '/local/autobadge/classes/autobadge.php')) {
            $result->errors[] = 'autobadge_missing';
            return $result;
        }

        $gaps = self::find($userid, self::FILTER_ANY);
        self::enrich($gaps);

        foreach ($gaps as $gap) {
            if ($courseid > 0 && (int) $gap->courseid !== $courseid) {
                continue;
            }
            if (empty($gap->missing_badge)) {
                $result->skipped++;
                continue;
            }

            $diag = studypace::get_gamification_diagnostic($userid, (int) $gap->courseid);
            if (empty($diag->valid) || !$diag->gamification_expected) {
                $result->skipped++;
                continue;
            }
            if ($diag->badge_issued) {
                $result->skipped++;
                continue;
            }
            if (!$diag->badge_expected_exists) {
                $result->errors[] = get_string('repairbadgemissingdef', 'local_studypace', s($diag->badge_expected_name));
                continue;
            }

            if ($dryrun) {
                $result->awarded++;
                $result->details[] = (object) [
                    'courseid' => (int) $gap->courseid,
                    'shortname' => $gap->courseshortname,
                    'badge' => $diag->badge_expected_name,
                    'dryrun' => true,
                ];
                continue;
            }

            try {
                if (self::award_course_badge_if_due($userid, (int) $gap->courseid, !$quiet)) {
                    $after = studypace::get_gamification_diagnostic($userid, (int) $gap->courseid);
                    $result->awarded++;
                    $result->details[] = (object) [
                        'courseid' => (int) $gap->courseid,
                        'shortname' => $gap->courseshortname,
                        'badge' => $after->badge_issued_names ?: $diag->badge_expected_name,
                        'dryrun' => false,
                    ];
                } else {
                    $result->errors[] = s($gap->courseshortname) . ': badge not issued';
                }
            } catch (\Throwable $e) {
                $result->errors[] = s($gap->courseshortname) . ': ' . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Credit missing keys (local_coin) for on-time Eixo completions.
     *
     * Idempotent: skips courses that already have a ledger row for the
     * planned_completion action or any positive credit for the course.
     *
     * @param int $userid
     * @param bool $quiet Suppress coin award notifications to the user.
     * @param bool $dryrun Simulate only; do not write to the ledger.
     * @param int $courseid Restrict to one course (0 = all gaps for user).
     * @return \stdClass {credited, skipped, errors[], details[]}
     */
    public static function repair_user_coins(int $userid, bool $quiet = true, bool $dryrun = false, int $courseid = 0): \stdClass {
        global $CFG, $DB;

        $result = new \stdClass();
        $result->credited = 0;
        $result->skipped = 0;
        $result->errors = [];
        $result->details = [];

        if (!self::triggers_configured()) {
            $result->errors[] = 'triggers_not_configured';
            return $result;
        }

        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            $result->errors[] = 'user_not_found';
            return $result;
        }

        require_once($CFG->dirroot . '/local/coin/classes/coin_stack.php');

        $gaps = self::find($userid, self::FILTER_ANY);
        self::enrich($gaps);

        foreach ($gaps as $gap) {
            if ($courseid > 0 && (int) $gap->courseid !== $courseid) {
                continue;
            }
            if (empty($gap->missing_coins)) {
                $result->skipped++;
                continue;
            }

            $cred = self::credit_course_coins_if_due($userid, (int) $gap->courseid, $quiet, $dryrun);
            if (!empty($cred->credited)) {
                $result->credited += (int) $cred->credited;
                $result->details[] = (object) [
                    'courseid' => (int) $gap->courseid,
                    'shortname' => $gap->courseshortname,
                    'amount' => (int) $cred->credited,
                    'dryrun' => false,
                ];
            } else if (!empty($cred->skipped)) {
                $result->skipped++;
            }
            if (!empty($cred->errors)) {
                foreach ($cred->errors as $error) {
                    $result->errors[] = s($gap->courseshortname) . ': ' . $error;
                }
            }
        }

        return $result;
    }

    /**
     * @param array<int, \stdClass> $gaps
     * @return array<int, \stdClass>
     */
    public static function gaps_for_user(int $userid): array {
        $gaps = self::find($userid, self::FILTER_ANY);
        self::enrich($gaps);
        return $gaps;
    }

    /**
     * Send gap rows as CSV download.
     *
     * @param array<int, \stdClass> $gaps
     * @return void
     */
    public static function send_csv(array $gaps): void {
        $filename = 'studypace_gamification_gaps_' . userdate(time(), '%Y%m%d_%H%M') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo self::build_csv($gaps);
    }
}

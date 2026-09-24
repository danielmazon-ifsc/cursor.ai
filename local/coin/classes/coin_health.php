<?php
// This file is part of Moodle - http://moodle.org/
//
// @package   local_coin
// @copyright 2026 Viddia (http://viddia.com.br)
// @license   http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later

namespace local_coin;

defined('MOODLE_INTERNAL') || die();

/**
 * Pre/post upgrade health checks for coin ledger and stacks.
 */
class coin_health {

    /** @var int Max sample rows shown in admin tables. */
    public const SAMPLE_LIMIT = 50;

    /**
     * Duplicate ledger groups: same (userid, courseid, action) more than once.
     *
     * @return \stdClass {totalgroups, extrasrows, samples[]}
     */
    public static function get_duplicate_ledger_report(): \stdClass {
        global $DB;

        $report = (object) [
            'totalgroups' => 0,
            'extrasrows' => 0,
            'samples' => [],
        ];

        $groups = $DB->get_records_sql(
            'SELECT userid, courseid, action, COUNT(1) AS cnt, MIN(id) AS keepid
               FROM {coin_ledger}
           GROUP BY userid, courseid, action
             HAVING COUNT(1) > 1'
        );
        $report->totalgroups = count($groups);
        foreach ($groups as $g) {
            $report->extrasrows += ((int) $g->cnt - 1);
        }

        if ($report->totalgroups > 0) {
            $report->samples = $DB->get_records_sql(
                'SELECT userid, courseid, action, COUNT(1) AS duplicatecount, MIN(id) AS keepid
                   FROM {coin_ledger}
               GROUP BY userid, courseid, action
                 HAVING COUNT(1) > 1
               ORDER BY duplicatecount DESC, userid ASC
                  LIMIT ' . self::SAMPLE_LIMIT
            );
        }

        return $report;
    }

    /**
     * Rows where coin_stack.coins differs from SUM(coin_ledger.amount).
     *
     * @return \stdClass {totalmismatches, samples[]}
     */
    public static function get_stack_ledger_mismatch_report(): \stdClass {
        global $DB;

        $report = (object) [
            'totalmismatches' => 0,
            'samples' => [],
        ];

        $sql = 'SELECT s.userid, s.courseid, s.coins AS stackcoins,
                       COALESCE(SUM(l.amount), 0) AS ledgersum
                  FROM {coin_stack} s
             LEFT JOIN {coin_ledger} l
                    ON l.userid = s.userid AND l.courseid = s.courseid
              GROUP BY s.userid, s.courseid, s.coins
                HAVING s.coins <> COALESCE(SUM(l.amount), 0)
              ORDER BY ABS(s.coins - COALESCE(SUM(l.amount), 0)) DESC, s.userid ASC';

        $all = $DB->get_records_sql($sql);
        $report->totalmismatches = count($all);
        $report->samples = array_slice($all, 0, self::SAMPLE_LIMIT, true);

        return $report;
    }

    /**
     * Ledger totals with no coin_stack row (post-upgrade orphan credits).
     *
     * @return \stdClass {totalorphans, samples[]}
     */
    public static function get_ledger_without_stack_report(): \stdClass {
        global $DB;

        $report = (object) [
            'totalorphans' => 0,
            'samples' => [],
        ];

        $sql = 'SELECT l.userid, l.courseid, COALESCE(SUM(l.amount), 0) AS ledgersum
                  FROM {coin_ledger} l
             LEFT JOIN {coin_stack} s
                    ON s.userid = l.userid AND s.courseid = l.courseid
                 WHERE s.id IS NULL
              GROUP BY l.userid, l.courseid
                HAVING COALESCE(SUM(l.amount), 0) <> 0
              ORDER BY ledgersum DESC, l.userid ASC';

        $all = $DB->get_records_sql($sql);
        $report->totalorphans = count($all);
        $report->samples = array_slice($all, 0, self::SAMPLE_LIMIT, true);

        return $report;
    }

    /**
     * Reconcile coin_stack.coins with SUM(coin_ledger.amount) per (userid, courseid).
     *
     * @return \stdClass {updated, inserted, zeroed}
     */
    public static function rebuild_stacks_from_ledger(): \stdClass {
        global $DB;

        $stats = (object) [
            'updated' => 0,
            'inserted' => 0,
            'zeroed' => 0,
        ];

        $totals = [];
        $rs = $DB->get_recordset_sql(
            'SELECT userid, courseid, COALESCE(SUM(amount), 0) AS total
               FROM {coin_ledger}
           GROUP BY userid, courseid'
        );
        foreach ($rs as $row) {
            $key = (int) $row->userid . ':' . (int) $row->courseid;
            $totals[$key] = [
                'userid' => (int) $row->userid,
                'courseid' => (int) $row->courseid,
                'total' => max(0, (int) $row->total),
            ];
        }
        $rs->close();

        foreach ($totals as $entry) {
            $stack = $DB->get_record('coin_stack', [
                'userid' => $entry['userid'],
                'courseid' => $entry['courseid'],
            ]);
            if ($stack) {
                if ((int) $stack->coins !== $entry['total']) {
                    $stack->coins = $entry['total'];
                    $DB->update_record('coin_stack', $stack);
                    $stats->updated++;
                }
            } else if ($entry['total'] > 0) {
                $DB->insert_record('coin_stack', (object) [
                    'userid' => $entry['userid'],
                    'courseid' => $entry['courseid'],
                    'coins' => $entry['total'],
                ]);
                $stats->inserted++;
            }
        }

        $stackrs = $DB->get_recordset('coin_stack');
        foreach ($stackrs as $stack) {
            $key = (int) $stack->userid . ':' . (int) $stack->courseid;
            if (!isset($totals[$key]) && (int) $stack->coins !== 0) {
                $stack->coins = 0;
                $DB->update_record('coin_stack', $stack);
                $stats->zeroed++;
            }
        }
        $stackrs->close();

        return $stats;
    }
}

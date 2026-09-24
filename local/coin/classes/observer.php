<?php

/**
 * Coin observers.
 *
 * @package   local_coin
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace local_coin;

defined('MOODLE_INTERNAL') || die();

/**
 * Coin observer class.
 *
 * @package   local_coin
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class observer {

    /** Maximum coins per single trigger (site-admin config guard). */
    public const MAX_TRIGGER_AMOUNT = 10000;

    /**
     * Process configured events and change user's stack accordingly
     *
     * @param \core\event\base $event The event.
     * @param bool $quiet
     */
    public static function process_event($event, $quiet = false) {

        $triggersjson = get_config('local_coin', 'triggersjson');
        $transactiontriggers = json_decode($triggersjson, true);
        if (!is_array($transactiontriggers)) {
            debugging('Invalid triggers configuration in Coins local plugin: ' . $triggersjson);
            $transactiontriggers = [];
        }
        $cancellersjson = get_config('local_coin', 'cancellersjson');
        $transactioncancellers = json_decode($cancellersjson, true);
        if (!is_array($transactioncancellers)) {
            debugging('Invalid cancellers configuration in Coins local plugin: ' . $cancellersjson);
            $transactioncancellers = [];
        }

        if (isset($transactiontriggers[$event->eventname])) {
            $trigger = $transactiontriggers[$event->eventname];
            $amount = self::normalize_trigger_amount($trigger['amount'] ?? 0);
            if ($amount === 0) {
                return;
            }
            $useridfield = $trigger['userid'] ?? '';
            $userid = self::resolve_event_userid($event, $useridfield);
            $courseid = (int) $event->courseid;
            if ($userid && $courseid > 0) {
                $action = self::get_trigger_action($event);
                self::stack_coins_with_lock($userid, $courseid, $amount, $action, $event, $quiet);
            }
        } else if (isset($transactioncancellers[$event->eventname])) {
            $cancel = $transactioncancellers[$event->eventname];
            $useridfield = $cancel['userid'] ?? '';
            $userid = self::resolve_event_userid($event, $useridfield);
            $courseid = (int) $event->courseid;
            if ($userid && $courseid > 0) {
                $action = self::get_cancel_action($event);
                $stack = new coin_stack($userid, $courseid);
                $stack->unstack_coins($action, $event, $quiet);
            }
        }
    }

    /**
     * @param mixed $amount
     * @return int
     */
    private static function normalize_trigger_amount($amount): int {
        if (!is_numeric($amount)) {
            return 0;
        }
        $amount = (int) round((float) $amount);
        if ($amount < 0) {
            return 0;
        }
        if ($amount > self::MAX_TRIGGER_AMOUNT) {
            debugging('local_coin: trigger amount capped to ' . self::MAX_TRIGGER_AMOUNT, DEBUG_NORMAL);
            return self::MAX_TRIGGER_AMOUNT;
        }
        return $amount;
    }

    /**
     * Resolve the user id configured for a trigger/canceller entry.
     *
     * @param \core\event\base $event
     * @param string $useridfield Property name on the event (userid or relateduserid).
     * @return int
     */
    private static function resolve_event_userid(\core\event\base $event, string $useridfield): int {
        $allowed = ['userid', 'relateduserid'];
        if (!in_array($useridfield, $allowed, true)) {
            return 0;
        }
        return (int) $event->{$useridfield};
    }

    /**
     * @param \core\event\base $event
     * @return string action id (max 100 chars for coin_ledger.action)
     */
    private static function get_trigger_action(\core\event\base $event): string {
        return self::normalize_action_id($event->eventname . '/' . $event->objectid . '/' . $event->courseid);
    }

    /**
     * Must match the action id stored by {@see get_trigger_action()}.
     *
     * @param \core\event\base $event
     * @return string
     */
    private static function get_cancel_action(\core\event\base $event): string {
        return self::get_trigger_action($event);
    }

    /**
     * @param string $action
     * @return string
     */
    private static function normalize_action_id(string $action): string {
        if (\core_text::strlen($action) <= 100) {
            return $action;
        }
        debugging('local_coin: action id hashed (exceeded 100 chars)', DEBUG_NORMAL);
        return \core_text::substr($action, 0, 60) . ':' . substr(sha1($action), 0, 39);
    }

    /**
     * Award coins under the same per-user/course lock used by studypace repair.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $amount
     * @param string $action
     * @param \core\event\base|null $event
     * @param bool $quiet
     */
    private static function stack_coins_with_lock(
        int $userid,
        int $courseid,
        int $amount,
        string $action,
        ?\core\event\base $event,
        bool $quiet
    ): void {
        $lockfactory = \core\lock\lock_config::get_lock_factory('local_studypace_repair');
        $lockkey = 'coins_' . $userid . '_' . $courseid;
        $lock = null;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $lock = $lockfactory->get_lock($lockkey, 10);
            if ($lock) {
                break;
            }
            usleep(200000);
        }
        if (!$lock) {
            debugging('local_coin: could not acquire stack lock for user ' . $userid
                    . ' course ' . $courseid . ' after retries', DEBUG_NORMAL);
            if (strpos($event->eventname, 'planned_completion') !== false
                    && class_exists('\\local_studypace\\gamification_gaps')) {
                \local_studypace\gamification_gaps::ensure_course_rewards($userid, $courseid, true);
            }
            return;
        }
        try {
            // One planned_completion reward per user/course even when studypace
            // row ids change or a second event type would use a different action.
            if ($event && strpos($event->eventname, 'planned_completion') !== false
                    && class_exists('\\local_studypace\\studypace')
                    && \local_studypace\studypace::course_planned_completion_coins_already_awarded($userid, $courseid)) {
                return;
            }
            $stack = new coin_stack($userid, $courseid);
            $stack->stack_coins($amount, $action, $event, $quiet);
        } finally {
            $lock->release();
        }
    }

    /**
     * Remove coin data when a user account is deleted.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $userid = (int) $event->objectid;
        if ($userid <= 0) {
            return;
        }
        $DB->delete_records('coin_ledger', ['userid' => $userid]);
        $DB->delete_records('coin_stack', ['userid' => $userid]);
    }

    /**
     * Remove coin data when a course is deleted.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $courseid = (int) $event->courseid;
        if ($courseid <= 0) {
            return;
        }
        $DB->delete_records('coin_ledger', ['courseid' => $courseid]);
        $DB->delete_records('coin_stack', ['courseid' => $courseid]);
    }

}

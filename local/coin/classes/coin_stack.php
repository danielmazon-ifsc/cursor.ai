<?php

/**
 * Coin stack.
 *
 * @package   local_coin
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace local_coin;

defined('MOODLE_INTERNAL') || die();

// $CFG must be pulled into scope explicitly: when this class file is loaded
// for the first time via require_once() from INSIDE a function/method (e.g.
// gamification_gaps::credit_course_coins_if_due(), which only declared
// `global $DB`), the file's top-level code runs in that caller's scope, so
// $CFG is undefined and "$CFG->libdir . '/setuplib.php'" collapses to the
// bogus path "/setuplib.php" → a FATAL require_once failure that bypasses
// try/catch and killed the heal_gamification_rewards scheduled task on every
// run. Declaring the global here makes the file self-sufficient regardless of
// the include context.
global $CFG;

require_once($CFG->libdir . '/setuplib.php');
require_once($CFG->libdir . '/outputcomponents.php');
require_once($CFG->libdir . '/outputactions.php');

use stdClass;
use action_link;
use popup_action;

/**
 * Coin stack class.
 *
 * @package   local_coin
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 * 
 * @property-read int $userid Id of the user this stack belongs to
 * @property-read int $coins How many coins there are in  user's stack
 */
class coin_stack {

    /** Cancel reason token: daily cron {@see remove_expired_coins()}. */
    public const CANCEL_KIND_EXPIRED = 'expired';

    /** @var array event data */
    protected $data;

    // Public methods

    /**
     * Constructs a coin stack for an user
     * 
     * @param int $userid User to whom stack will be constructed
     * @param int $courseid Course for which the stack will be constructed. Zero
     *                          means all courses
     */
    public function __construct($userid, $courseid = 0) {
        if (!is_numeric($userid) || $userid < 0) {
            throw new \invalid_parameter_exception('userid must be positive integer');
        }
        if (!is_numeric($courseid) || $courseid < 0) {
            throw new \invalid_parameter_exception('courseid must be positive integer');
        }

        $this->data = array();
        if (!$this->retrieve_user_stack($userid, $courseid)) {
            $this->create_user_stack($userid, $courseid);
            $this->retrieve_user_stack($userid, $courseid);
        }
    }

    /**
     * Magic getter for read only access.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }
        debugging("Accessing non-existent event property '$name'");
    }

    /**
     * Magic setter.
     *
     * Note: we must not allow modification of data from outside,
     *       after trigger() the data MUST NOT CHANGE!!!
     *
     * @param string $name
     * @param mixed $value
     *
     * @throws \coding_exception
     */
    public function __set($name, $value) {
        throw new \coding_exception('Event properties must not be modified.');
    }

    /**
     * Is data property set?
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name) {
        return isset($this->data[$name]);
    }

    /**
     * Creates a transaction changing the amount of coins in user's stack
     * 
     * @param int $amount Amount of coins to be added (positive) or removed (negative)
     * @param string $action Action which caused coin transaction
     * @param object $event Event which caused coin awarding
     * @param bool $quiet Inform user of award or not
     * @return int Updated amount of coins in users' stack after transaction
     */
    public function stack_coins($amount, $action, $event = null, $quiet = false) {
        global $DB;

        if (!is_numeric($amount)) {
            throw new \invalid_parameter_exception('amount must be integer');
        }
        if (!is_string($action) || empty($action)) {
            throw new \invalid_parameter_exception('action must be an non empty string');
        }
        if ($this->data['courseid'] == 0) {
            throw new \invalid_parameter_exception('cannot stack to a non-course-specific stack');
        }

        $userid = $this->data['userid'];
        $courseid = $this->data['courseid'];
        $amount = (int) round((float) $amount);

        if ($DB->record_exists('coin_ledger', [
            'userid' => $userid,
            'courseid' => $courseid,
            'action' => $action,
        ])) {
            $stackrecord = $DB->get_record('coin_stack', ['userid' => $userid, 'courseid' => $courseid]);
            $coins = $stackrecord ? (int) $stackrecord->coins : 0;
            $this->data['coins'] = $coins;
            return $coins;
        }

        // Start a database transaction to insure both or none operations
        // comitted
        $dbtransaction = $DB->start_delegated_transaction();
        try {

            // Insert ledger record
            $ledgerrecord = new stdclass();
            $ledgerrecord->userid = $userid;
            $ledgerrecord->amount = $amount;
            $ledgerrecord->action = $action;
            $ledgerrecord->transactiontime = time();
            $ledgerrecord->courseid = $courseid;
            $ledgerrecordid = $DB->insert_record('coin_ledger', $ledgerrecord);

            // Update stack record
            $stackrecord = $DB->get_record('coin_stack', array('userid' => $userid, 'courseid' => $courseid));
            if (!$stackrecord) {
                $stackrecord = $this->create_user_stack($userid, $courseid, $amount);
            } else {
                $stackrecord->coins += $amount;
                if ($stackrecord->coins < 0) {
                    $stackrecord->coins = 0;
                }
                $DB->update_record('coin_stack', $stackrecord);
            }

            // Confirm transaction
            $dbtransaction->allow_commit();
        } catch (\dml_exception $e) {
            $dbtransaction->rollback($e);
            if ($DB->record_exists('coin_ledger', [
                'userid' => $userid,
                'courseid' => $courseid,
                'action' => $action,
            ])) {
                $stackrecord = $DB->get_record('coin_stack', ['userid' => $userid, 'courseid' => $courseid]);
                $coins = $stackrecord ? (int) $stackrecord->coins : 0;
                $this->data['coins'] = $coins;
                return $coins;
            }
            debugging('coin_stack::stack_coins failed: ' . $e->getMessage(), DEBUG_NORMAL);
            throw $e;
        } catch (\Throwable $e) {
            $dbtransaction->rollback($e);
            debugging('coin_stack::stack_coins failed: ' . $e->getMessage(), DEBUG_NORMAL);
            throw $e;
        }

        // Update stack status
        $this->data['coins'] = (int) $stackrecord->coins;

        // Trigger event
        $params = array(
            'objectid' => $ledgerrecordid,
            'userid' => $userid,
            'context' => \context_user::instance($userid),
            'other' => serialize($event)
        );
        $coinevent = \local_coin\event\user_coinsawarded::create($params);
        $coinevent->trigger();

        if (!$quiet) {
            // Send message to user informing award
            coin_stack::send_message($userid, $amount, $event);
        }

        return $stackrecord->coins;
    }

    /**
     * Cancels a previous transaction changing the amount of coins in user's stack
     * 
     * @param string $action Action which previously caused coin transaction
     * @param object @event Event which caused transaction cancelling
     * @param bool $quiet Inform user of award or not
     * @return int Updated amount of coins in users' stack or null if no
     *          transaction could be found
     */
    public function unstack_coins($action, $event = null, $quiet = false) {
        global $DB;

        if (!is_string($action) || empty($action)) {
            throw new \invalid_parameter_exception('action must be a non empty string');
        }

        $userid = (int) $this->data['userid'];
        $courseid = (int) ($this->data['courseid'] ?? 0);
        $conditions = ['userid' => $userid, 'action' => $action];
        if ($courseid > 0) {
            $conditions['courseid'] = $courseid;
        }
        $ledgerrecords = $DB->get_records('coin_ledger', $conditions, 'transactiontime DESC', '*', 0, 1);
        if (empty($ledgerrecords)) {
            return null;
        }
        $ledgerrecord = reset($ledgerrecords);

        $this->data['coins'] = coin_stack::cancel_transaction($ledgerrecord->id, $event, $quiet);

        return $this->data['coins'];
    }

    /**
     * Remove expired coins from ledger and update stacks accordingly
     * 
     * @param type $expirationlimit Transaction time limit. Transactions older than
     *              this will be considered expired
     */
    public static function remove_expired_coins($expirationlimit) {
        global $DB;

        // Retrieve expired transactions
        $sql = 'SELECT * '
                . 'FROM {coin_ledger} '
                . 'WHERE transactiontime < :expirationlimit '
                . 'AND amount > 0';
        $params = ['expirationlimit' => $expirationlimit];
        // Negative ones, usually originated from
        // coins being used to acquire something,
        // can only cancelled by reversing
        // acquisition
        $records = $DB->get_records_sql($sql, $params);

        // One notification per user per cron run (not one per ledger row).
        $expiredbyuser = [];

        foreach ($records as $transaction) {
            // Eixo completion keys must not expire: removing their ledger rows
            // makes on-time completers look unrewarded in studypace gap scans.
            if (strpos((string) $transaction->action, 'planned_completion') !== false) {
                continue;
            }
            $result = coin_stack::cancel_transaction(
                $transaction->id,
                null,
                true,
                self::CANCEL_KIND_EXPIRED
            );
            if ($result !== null && (int) $transaction->amount > 0) {
                $uid = (int) $transaction->userid;
                $expiredbyuser[$uid] = ($expiredbyuser[$uid] ?? 0) + (int) $transaction->amount;
            }
        }

        foreach ($expiredbyuser as $userid => $total) {
            coin_stack::send_message($userid, -$total, null, self::CANCEL_KIND_EXPIRED);
        }
    }

    /**
     * Gets an icon representing a coin
     * @return html representing a coin
     */
    public static function get_coin_icon_html($class = '') {
        global $OUTPUT;

        return $OUTPUT->pix_icon('coin', '', 'local_coin', array(
                    'class' => $class,
        ));
    }

    // Protected methods

    /**
     * Creates a stack for user in database
     * 
     * @param int $userid User to whom stack will be created
     * @param int $courseid Course for which the stack will be constructed. Zero
     *                          means all courses
     * @param int $coins Initial amount of coins in stack
     * @return object user stack created
     */
    protected function create_user_stack($userid, $courseid, $coins = 0) {
        global $DB;

        if (!is_numeric($userid) || $userid < 0) {
            throw new \invalid_parameter_exception('userid must be non-negative integer');
        }
        if (!is_numeric($courseid) || $courseid < 0) {
            throw new \invalid_parameter_exception('courseid must be non-negative integer');
        }
        if (!is_numeric($coins) || $coins < 0) {
            throw new \invalid_parameter_exception('coins must be non-negative integer');
        }

        $record = new stdclass();
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->coins = $coins;
        if ($courseid > 0) {
            $recordid = $DB->insert_record('coin_stack', $record);
            return $DB->get_record('coin_stack', array('id' => $recordid));
        } else {
            return $record;
        }
    }

    /**
     * Retrieves an user stack from databased
     * 
     * @param int $userid User whose stack will be retrieved
     * @param int $courseid Course for which the stack will be constructed. Zero
     *                          means all courses
     * @return bool true if a stack for the user could be found
     */
    protected function retrieve_user_stack($userid, $courseid = 0) {
        global $DB;

        if (!is_numeric($userid) || $userid < 0) {
            throw new \invalid_parameter_exception('userid must be positive integer');
        }
        if (!is_numeric($courseid) || $courseid < 0) {
            throw new \invalid_parameter_exception('courseid must be positive integer');
        }

        $records = ($courseid == 0) ? $DB->get_records('coin_stack', array('userid' => $userid)) : $DB->get_records('coin_stack', array('userid' => $userid, 'courseid' => $courseid));
        $totalcoins = 0;
        foreach ($records as $record) {
            $totalcoins += $record->coins;
        }

        $this->data['userid'] = $userid;
        $this->data['courseid'] = $courseid;
        $this->data['coins'] = $totalcoins;
        return true;
    }

    /**
     * Remove a transaction from ledger and update user's stack accordingly
     * @param int $transactionid Transaction to be cancelled
     * @param object $event Event which caused transaction cancellation
     * @param bool $quiet Inform user of award or not
     * @return int Updated amount of coins in user's stack or null if no
     *          transaction could be found
     */
    protected static function cancel_transaction($transactionid, $event = null, $quiet = false, ?string $reason = null) {
        global $DB;

        // Retrieve transaction details
        $transaction = $DB->get_record('coin_ledger', array('id' => $transactionid));
        if (!$transaction) {
            return null;
        }
        $amount = $transaction->amount;
        $userid = $transaction->userid;
        // Retrieve stack details (per-course stacks; courseid 0 is aggregate-only).
        $stack = $DB->get_record('coin_stack', array(
            'userid' => $userid,
            'courseid' => (int) $transaction->courseid,
        ));
        if (!$stack) {
            return null;
        }

        // Start a database transaction to insure both or none operations
        // comitted
        $dbtransaction = $DB->start_delegated_transaction();

        try {
            // Delete ledger record
            $DB->delete_records('coin_ledger', array('id' => $transactionid));

            // Update stack record
            $stack->coins -= $amount;
            if ($stack->coins < 0) {
                $stack->coins = 0;
            }
            $DB->update_record('coin_stack', $stack);

            // Confirm transaction
            $dbtransaction->allow_commit();
            // Check if user still exists
            $user = $DB->get_record('user', array('id' => $userid, 'deleted' => 0));
            if ($user) {
                // Trigger user event
                $params = array(
                    'objectid' => $transactionid,
                    'userid' => $userid,
                    'context' => \context_user::instance($userid),
                    'other' => serialize($event)
                );
                $coinevent = \local_coin\event\user_coinscancelled::create($params);
                $coinevent->trigger();

                if (!$quiet) {
                    // Send message to user informing cancellation
                    coin_stack::send_message($userid, -$amount, $event, $reason);
                }
            } else {
                // Trigger system event
                $params = array(
                    'objectid' => $transactionid,
                    'userid' => $userid,
                    'context' => \context_system::instance(),
                    'other' => serialize($event)
                );
                $coinevent = \local_coin\event\user_coinscancelled::create($params);
                $coinevent->trigger();
            }
        } catch (\Throwable $e) {
            $dbtransaction->rollback($e);
            debugging('coin_stack::stack_coins failed: ' . $e->getMessage(), DEBUG_NORMAL);
            throw $e;
        }

        return $stack->coins;
    }

    /**
     * Send a message to an user informing a chage in his/her coin stack
     * 
     * @param int $userid Id of the user to whom the message will be sent
     * @param int $amount How many coins have been added/removed from user's stack
     * @param \core\event\base|null $event Event which triggered coin stack change
     * @param string|null $cancelreason Explicit cancel reason (e.g. cron expiration)
     * @throws \invalid_parameter_exception In case of any invalid parameter
     */
    protected static function send_message($userid, $amount, $event = null, ?string $cancelreason = null) {
        global $CFG, $DB, $OUTPUT;

        if (!is_numeric($userid) || $userid < 0) {
            throw new \invalid_parameter_exception('userid must be positive integer');
        }
        if (!is_numeric($amount)) {
            throw new \invalid_parameter_exception('amount must be integer');
        }

        $user = $DB->get_record('user', array('id' => $userid));
        if (!$user) {
            return;
        }
        $firstname = $user->firstname;
        $fullname = fullname($user);
        $reason = '';
        if ($amount < 0) {
            $reason = self::resolve_cancel_reason($event, $cancelreason, $OUTPUT);
        } else if ($event) {
            $stringname = 'event' . $event->target . $event->action;
            if (get_string_manager()->string_exists($stringname, $event->component)) {
                $reason = get_string($stringname, $event->component);
                $eventurl = $event->get_url();
                if ($eventurl) {
                    $reasonlink = new action_link($eventurl, $reason, new popup_action('click', $eventurl, 'popup', array('height' => 440, 'width' => 700)));
                    $reason = $OUTPUT->render($reasonlink);
                }
            }
        }

        // Get configured message and subject
        if ($amount < 0) {
            $useexpiredtemplate = ($cancelreason === self::CANCEL_KIND_EXPIRED);
            $message = self::get_configured_cancel_message($useexpiredtemplate);
            $messagesubject = get_config('local_coin', 'cancelsubject');
            if ($messagesubject === false || trim((string) $messagesubject) === '') {
                $messagesubject = get_string('cancelsubject_default', 'local_coin');
            }
        } else {
            $message = get_config('local_coin', 'awardmessage');
            $messagesubject = get_config('local_coin', 'awardsubject');
        }

        $key = array('{$a->fullname}', '{$a->firstname}', '{$a->amount}', '{$a->reason}');
        $value = array($fullname, $firstname, abs($amount), $reason);
        $message = str_replace($key, $value, $message);
        $messagesubject = str_replace($key, $value, $messagesubject);
        if ($amount < 0) {
            $message = self::sanitize_cancel_message_body($message, $reason);
        }
        if (strpos($message, '<') === false) {
            // Plain text only.
            $messagetext = $message;
            $messagehtml = text_to_html($messagetext, null, false, true);
        } else {
            // This is most probably the tag/newline soup known as FORMAT_MOODLE.
            $messagehtml = format_text($message, FORMAT_HTML, array('para' => false, 'newlines' => true, 'filter' => true));
            $messagetext = html_to_text($messagehtml);
        }

        // Admin as sender
        $adminids = explode(',', $CFG->siteadmins);
        $sender = get_complete_user_data('id', reset($adminids));

        // Prepare the message.
        $update = new \core\message\message();
        $update->component = 'local_coin';
        $update->name = 'coinstackchanges';
        $update->notification = 1;
        $update->courseid = SITEID;
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
     * Text shown as {$a->reason} when coins are removed from a stack.
     *
     * @param \core\event\base|null $event
     * @param string|null $explicitreason
     * @param \renderer_base $output
     * @return string Plain text or small HTML fragment
     */
    protected static function resolve_cancel_reason($event, ?string $explicitreason, $output): string {
        if ($explicitreason === self::CANCEL_KIND_EXPIRED) {
            return get_string('cancelreason_expired', 'local_coin');
        }
        if ($explicitreason !== null && $explicitreason !== '') {
            return $explicitreason;
        }
        if (!$event) {
            return '';
        }
        $stringname = 'event' . $event->target . $event->action;
        if (get_string_manager()->string_exists($stringname, $event->component)) {
            $reason = get_string($stringname, $event->component);
            $eventurl = $event->get_url();
            if ($eventurl) {
                $reasonlink = new action_link($eventurl, $reason, new popup_action('click', $eventurl, 'popup', array('height' => 440, 'width' => 700)));
                return $output->render($reasonlink);
            }
            return $reason;
        }
        try {
            return $event->get_name();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Cancel notification body from config, with safe fallbacks.
     *
     * @param bool $expiredcron When true, use the expiration-specific template.
     * @return string
     */
    protected static function get_configured_cancel_message(bool $expiredcron): string {
        if ($expiredcron) {
            $message = get_config('local_coin', 'cancelmessage_expired');
            if ($message === false || trim((string) $message) === '') {
                return get_string('cancelmessage_expired', 'local_coin');
            }
            return (string) $message;
        }

        $message = get_config('local_coin', 'cancelmessage');
        if ($message === false || trim((string) $message) === '') {
            return get_string('cancelmessage_default', 'local_coin');
        }
        $message = (string) $message;
        if (self::cancel_message_uses_legacy_secret($message)) {
            return get_string('cancelmessage_default', 'local_coin');
        }
        return $message;
    }

    /**
     * Detect the old PDI template that produced "Motivo? Segredo".
     *
     * @param string $message
     * @return bool
     */
    protected static function cancel_message_uses_legacy_secret(string $message): bool {
        if (stripos($message, '{$a->reason}') === false) {
            return false;
        }
        if (stripos($message, 'Motivo?') !== false || stripos($message, 'Reason?') !== false) {
            return true;
        }
        if (stripos($message, 'Segredo') !== false || stripos($message, 'Secret') !== false) {
            return true;
        }
        return false;
    }

    /**
     * Remove legacy "Motivo? Segredo" fragments from the final message body.
     *
     * @param string $message
     * @param string $reason
     * @return string
     */
    protected static function sanitize_cancel_message_body(string $message, string $reason): string {
        $secretpt = get_string('secret', 'local_coin');
        $secreten = 'Secret';

        if ($reason === $secretpt || $reason === $secreten) {
            $reason = '';
        }

        if (trim(strip_tags($reason)) === '') {
            $message = preg_replace('/\s*Motivo\?\s*' . preg_quote($secretpt, '/') . '\s*/iu', "\n", $message);
            $message = preg_replace('/\s*Reason\?\s*' . preg_quote($secreten, '/') . '\s*/iu', "\n", $message);
            $message = preg_replace('/\s*Motivo\?\s*\n*/u', "\n", $message);
            $message = preg_replace('/\s*Reason\?\s*\n*/u', "\n", $message);
        }

        $message = str_replace(
            ['Motivo? Segredo', 'Motivo? Secret', 'Reason? Segredo', 'Reason? Secret'],
            '',
            $message
        );
        $message = preg_replace("/\n{3,}/", "\n\n", trim($message));
        return $message;
    }

    function coins_in_a_month(int $timewithinmonth) {
        global $DB;

        $beginningofmonth = strtotime(date('01.m.Y', $timewithinmonth));
        $endofmonth = strtotime(date('t.m.Y', $timewithinmonth)) + (60 * 60 * 24) - 1;
        $sql = 'SELECT sum(amount) as sum '
                . 'FROM {coin_ledger} '
                . 'WHERE userid = :userid '
                . 'AND transactiontime >= :beginningofmonth '
                . 'AND transactiontime <= :endofmonth;';
        $record = $DB->get_record_sql($sql, array(
            'userid' => $this->userid,
            'beginningofmonth' => $beginningofmonth,
            'endofmonth' => $endofmonth
        ));
        if ($record) {
            return $record->sum;
        }
        return 0;
    }

}

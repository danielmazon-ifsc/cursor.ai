<?php

/**
 * Local coin plugin upgrade code
 *
 * @package   local_coin
 * @copyright 2023 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Clean stacks, ledges and reprocess all log events
 */
function local_coin_reprocess_everything() {
    global $DB;

    $manager = get_log_manager(true);
    $stores = $manager->get_readers();
    if (count($stores) == 0) {
        mtrace('local_coin: logstore_standard_log not available, skipping reprocess');
        return;
    }
    $store = $stores['logstore_standard'] ?? null;
    if (!$store) {
        mtrace('local_coin: logstore_standard not available, skipping reprocess');
        return;
    }

    $DB->delete_records('coin_stack');
    $DB->delete_records('coin_ledger');
    $from = 0;
    do {
        $events = $store->get_events_select('', [], 'timecreated ASC', $from, 1000);
        $from += count($events);
        foreach ($events as $event) {
            \local_coin\observer::process_event($event, true);
        }
    } while (count($events) > 0);
}

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_coin_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2022041003) {
        set_config('triggersjson', '{"\\\\local_studypace\\\\event\\\\planned_completion":{"amount":50,"userid":"relateduserid"},"\\\\local_studypace\\\\event\\\\grade_a_completion":{"amount":30,"userid":"relateduserid"},"\\\\local_autobadge\\\\event\\\\all_course_badges":{"amount":10,"userid":"relateduserid"},"\\\\mod_socialforum\\\\event\\\\post_created":{"amount":5,"userid":"userid"}}', 'local_coin');
        set_config('cancellersjson', '{}', 'local_coin');
        upgrade_plugin_savepoint(true, 2022041003, 'local', 'coin');
    }

    if ($oldversion < 2024041502) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('coin_stack');
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('userid', XMLDB_INDEX_UNIQUE, ['userid']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        $index = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('coin_ledger');
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        local_coin_reprocess_everything();

        upgrade_plugin_savepoint(true, 2024041502, 'local', 'coin');
    }

    if ($oldversion < 2026052632) {
        local_coin_ensure_planned_completion_triggers();
        upgrade_plugin_savepoint(true, 2026052632, 'local', 'coin');
    }

    if ($oldversion < 2026052633) {
        local_coin_upgrade_cancel_messages();
        upgrade_plugin_savepoint(true, 2026052633, 'local', 'coin');
    }

    if ($oldversion < 2026052634) {
        local_coin_upgrade_cancel_messages();
        upgrade_plugin_savepoint(true, 2026052634, 'local', 'coin');
    }

    if ($oldversion < 2026052635) {
        upgrade_plugin_savepoint(true, 2026052635, 'local', 'coin');
    }

    if ($oldversion < 2026052636) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('coin_ledger');
        $index = new xmldb_index('userid-courseid-action', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid', 'action']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_plugin_savepoint(true, 2026052636, 'local', 'coin');
    }

    if ($oldversion < 2026052637) {
        require_once(__DIR__ . '/../classes/coin_health.php');
        local_coin_remove_duplicate_ledger_rows();
        \local_coin\coin_health::rebuild_stacks_from_ledger();
        local_coin_apply_ledger_unique_index($dbman = $DB->get_manager());
        upgrade_plugin_savepoint(true, 2026052637, 'local', 'coin');
    }

    if ($oldversion < 2026052638) {
        upgrade_plugin_savepoint(true, 2026052638, 'local', 'coin');
    }

    if ($oldversion < 2026052639) {
        require_once(__DIR__ . '/../classes/coin_health.php');
        \local_coin\coin_health::rebuild_stacks_from_ledger();
        upgrade_plugin_savepoint(true, 2026052639, 'local', 'coin');
    }

    if ($oldversion < 2026052640) {
        upgrade_plugin_savepoint(true, 2026052640, 'local', 'coin');
    }

    if ($oldversion < 2026052641) {
        upgrade_plugin_savepoint(true, 2026052641, 'local', 'coin');
    }

    return true;
}

/**
 * Add the unique ledger index, deduplicating again if the first attempt fails.
 *
 * @param database_manager $dbman
 */
function local_coin_apply_ledger_unique_index(database_manager $dbman): void {
    $table = new xmldb_table('coin_ledger');
    $oldindex = new xmldb_index('userid-courseid-action', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid', 'action']);
    if ($dbman->index_exists($table, $oldindex)) {
        $dbman->drop_index($table, $oldindex);
    }
    $uniqueindex = new xmldb_index('userid-courseid-action-uix', XMLDB_INDEX_UNIQUE, ['userid', 'courseid', 'action']);
    if ($dbman->index_exists($table, $uniqueindex)) {
        return;
    }
    try {
        $dbman->add_index($table, $uniqueindex);
    } catch (moodle_exception $e) {
        mtrace('local_coin: retrying unique index after dedupe: ' . $e->getMessage());
        local_coin_remove_duplicate_ledger_rows();
        if (!$dbman->index_exists($table, $uniqueindex)) {
            $dbman->add_index($table, $uniqueindex);
        }
    }
}

/**
 * Keep the earliest ledger row per (userid, courseid, action) before adding a unique index.
 */
function local_coin_remove_duplicate_ledger_rows(): void {
    global $DB;

    $dupes = $DB->get_records_sql(
        "SELECT userid, courseid, action, MIN(id) AS keepid, COUNT(1) AS cnt
           FROM {coin_ledger}
       GROUP BY userid, courseid, action
         HAVING COUNT(1) > 1"
    );
    foreach ($dupes as $dupe) {
        $select = 'userid = :userid AND courseid = :courseid AND action = :action AND id <> :keepid';
        $params = [
            'userid' => $dupe->userid,
            'courseid' => $dupe->courseid,
            'action' => $dupe->action,
            'keepid' => $dupe->keepid,
        ];
        $DB->delete_records_select('coin_ledger', $select, $params);
    }
}

/**
 * Replace legacy cancel templates that produced "Motivo? Segredo" in notifications.
 */
function local_coin_upgrade_cancel_messages(): void {
    $cancelmessage = get_config('local_coin', 'cancelmessage');
    if (!is_string($cancelmessage) || $cancelmessage === '') {
        set_config('cancelmessage', get_string('cancelmessage_default', 'local_coin'));
        return;
    }
    if (stripos($cancelmessage, 'Motivo?') !== false
            || stripos($cancelmessage, 'Reason?') !== false
            || stripos($cancelmessage, 'Segredo') !== false
            || stripos($cancelmessage, 'Secret') !== false) {
        set_config('cancelmessage', get_string('cancelmessage_default', 'local_coin'));
    }
    if (!get_config('local_coin', 'cancelmessage_expired')) {
        set_config('cancelmessage_expired', get_string('cancelmessage_expired', 'local_coin'));
    }
}

/**
 * Ensure studypace planned_completion triggers exist in site config.
 */
function local_coin_ensure_planned_completion_triggers(): void {
    $triggersjson = get_config('local_coin', 'triggersjson');
    $triggers = json_decode($triggersjson ?: '{}', true);
    if (!is_array($triggers)) {
        $triggers = [];
    }

    $stdkey = '\\local_studypace\\event\\planned_completion';
    $hgkey = '\\local_studypace\\event\\planned_completion_high_grade';
    $legacykey = '\\local_studypace\\event\\grade_a_completion';

    $changed = false;

    if (!isset($triggers[$stdkey])) {
        $triggers[$stdkey] = ['amount' => 10, 'userid' => 'relateduserid'];
        $changed = true;
    } else if (empty($triggers[$stdkey]['userid'])) {
        $triggers[$stdkey]['userid'] = 'relateduserid';
        $changed = true;
    }

    if (!isset($triggers[$hgkey])) {
        $amount = 20;
        if (isset($triggers[$legacykey]['amount'])) {
            $amount = (int) $triggers[$legacykey]['amount'];
        } else if (isset($triggers[$stdkey]['amount'])) {
            $amount = max((int) $triggers[$stdkey]['amount'], 10);
        }
        $triggers[$hgkey] = ['amount' => $amount, 'userid' => 'relateduserid'];
        $changed = true;
    } else if (empty($triggers[$hgkey]['userid'])) {
        $triggers[$hgkey]['userid'] = 'relateduserid';
        $changed = true;
    }

    if ($changed) {
        set_config('triggersjson', json_encode($triggers));
    }
}

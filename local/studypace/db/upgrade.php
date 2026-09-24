<?php

defined('MOODLE_INTERNAL') || die;

use local_studypace\studypace;

function update_events($range, $userid = 0) {
    global $DB;

    if ($range == 1) {
        if ((int) $userid <= 0) {
            debugging('update_events(1) skipped: userid must be set to avoid site-wide delete', DEBUG_NORMAL);
            return;
        }
        $userselect = array('userid' => (int) $userid);
        $DB->delete_records('badge_issued', $userselect);
        $DB->delete_records('coin_stack', $userselect);
        $DB->delete_records('coin_ledger', $userselect);
        $select = array();
        $select['component'] = 'local_studypace';
        $select['useridto'] = $userid;
        $DB->delete_records('notifications', $select);
    }

    // Reprocess every \core\event\course_module_completion_updated row from the
    // standard log so badges/coins can be re-issued. The eventname is bound as
    // a parameter so the literal value is passed unchanged to both MySQL and
    // PostgreSQL; the previous '\\core\\event\\...' inline form only worked in
    // PostgreSQL and silently produced zero matches on MySQL/MariaDB.
    $eventnameparam = '\\core\\event\\course_module_completion_updated';
    $idparams = ['eventname' => $eventnameparam];
    $sqlid = 'SELECT id FROM {logstore_standard_log} WHERE eventname = :eventname';
    if ($userid != 0) {
        $sqlid .= ' AND relateduserid = :userid';
        $idparams['userid'] = $userid;
    }
    $idrecords = $DB->get_records_sql($sqlid, $idparams);
    $selectedids = array();
    $rangesize = ceil(count($idrecords) / 800) * 100;
    if ($rangesize <= 0) {
        echo 'No events to reprocess <br>';
        return;
    }
    $initialpos = ($range - 1) * $rangesize + 1;
    $finalpos = $initialpos + $rangesize - 1;
    $pos = 1;
    foreach ($idrecords as $idrecord) {
        if ($pos >= $initialpos && $pos <= $finalpos) {
            $selectedids[] = (int) $idrecord->id;
        }
        ++$pos;
    }
    if (count($selectedids) == 0) {
        echo 'No events to reprocess <br>';
        return;
    }
    echo 'Reprocessing events from seq ' . $initialpos . ' to seq ' . $finalpos . ' <br>';
    list($insql, $inparams) = $DB->get_in_or_equal($selectedids, SQL_PARAMS_NAMED, 'evid');
    $sqlevents = 'SELECT * FROM {logstore_standard_log} '
            . 'WHERE eventname = :eventname '
            . 'AND id ' . $insql;
    $eventparams = array_merge(['eventname' => $eventnameparam], $inparams);
    if ($userid != 0) {
        $sqlevents .= ' AND relateduserid = :userid';
        $eventparams['userid'] = $userid;
    }
    $events = $DB->get_records_sql($sqlevents, $eventparams);
    if (count($events) > 0) {
        echo 'Reprocessing ' . count($events) . ' course_module_completion_updated events <br>';
        foreach ($events as $event) {
            echo 'Reprocessing event ' . $event->id . ' <br>';
            studypace::process_course_module_completion_data(
                $event->relateduserid,
                $event->courseid,
                $event->timecreated,
                $event->objectid
            );
        }
    }
}

function xmldb_local_studypace_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025042302) {

        // Define table local_studypace to be created.
        $table = new xmldb_table('local_studypace');

        // Adding fields to table local_studypace.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('target', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('targetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('estimatedcompletion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('actualcompletion', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table local_studypace.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table local_studypace.
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('target', XMLDB_INDEX_NOTUNIQUE, ['target']);
        $table->add_index('targetid', XMLDB_INDEX_NOTUNIQUE, ['targetid']);

        // Conditionally launch create table for local_studypace.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Studypace savepoint reached.
        upgrade_plugin_savepoint(true, 2025042302, 'local', 'studypace');
    }

    if ($oldversion < 2025042501) {

        // Define field courseid to be added to local_studypace.
        $table = new xmldb_table('local_studypace');
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'userid');
        // Conditionally launch add field courseid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define index courseid (not unique) to be added to local_studypace.
        $table = new xmldb_table('local_studypace');
        $index = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        // Conditionally launch add index courseid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define field level to be added to local_studypace.
        $table = new xmldb_table('local_studypace');
        $field = new xmldb_field('level', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null, 'courseid');
        // Conditionally launch add field level.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define index level (not unique) to be added to local_studypace.
        $table = new xmldb_table('local_studypace');
        $index = new xmldb_index('level', XMLDB_INDEX_NOTUNIQUE, ['level']);
        // Conditionally launch add index level.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Studypace savepoint reached.
        upgrade_plugin_savepoint(true, 2025042501, 'local', 'studypace');
    }

    if ($oldversion < 2025042601) {

        // Define index level (not unique) to be added to local_studypace.
        $table = new xmldb_table('local_studypace');
        $index = new xmldb_index('actualcompletion', XMLDB_INDEX_NOTUNIQUE, ['actualcompletion']);
        // Conditionally launch add index actualcompletion.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Studypace savepoint reached.
        upgrade_plugin_savepoint(true, 2025042601, 'local', 'studypace');
    }

    if ($oldversion < 2025061302) {
        // Recompute estimatedcompletion for every enrolment. Deferred to the
        // adhoc task so the web upgrade can't time out on large sites.
        $task = new \local_studypace\task\recalculate_estimated_completion();
        \core\task\manager::queue_adhoc_task($task, true);
        upgrade_plugin_savepoint(true, 2025061302, 'local', 'studypace');
    }

    if ($oldversion < 2025061714) {
        // Run all seq numbers below from 1 to 8
        update_events(8);
        upgrade_plugin_savepoint(true, 2025061714, 'local', 'studypace');
    }

    if ($oldversion < 2025063002) {
        $sql = "SELECT ue.userid "
                . "FROM {user_enrolments} ue, {local_studypace} ls "
                . "WHERE ue.userid = ls.userid "
                . "AND ue.timestart > 0 "
                . "AND ls.target = 'course' "
                . "AND ls.estimatedcompletion = ue.timestart";
        $records = $DB->get_records_sql($sql);
        foreach ($records as $record) {
            $userid = $record->userid;
            echo 'Updating enrollments of user ' . $userid . ' <br><br>';
            studypace::update_all_user_enrolments($userid);
            echo 'Updating events of user ' . $userid . ' <br><br>';
            update_events(1, $userid);
        }
        upgrade_plugin_savepoint(true, 2025063002, 'local', 'studypace');
    }

    if ($oldversion < 2025063003) {
        // Previous versions silently skipped saladeconferencias courses in
        // update_user_enrolment() / delete_user_enrolment() because the guard
        // used format_specialization::is_specialization() which only matches
        // specialization-format courses. As a result:
        //   1. Existing specialization enrolments were divided by the *wrong*
        //      $numspecializations (only specialization, not specialization +
        //      saladeconferencias) → estimatedcompletion drifted from the value
        //      create_user_enrolment() produced for the same enrolment.
        //   2. Users who were unenrolled from a saladeconferencias course kept
        //      orphan local_studypace rows that contaminate the progress %.
        //
        // Orphan cleanup runs synchronously (single DELETE per query, fast),
        // but the actual recalculation is queued as an adhoc task so the
        // web-based upgrade never times out before reaching this savepoint.
        // The previous version of this step ran update_all_user_enrolments()
        // inline, which on busy sites exceeded max_execution_time / proxy
        // timeout — the savepoint was never written and the upgrade looped.

        echo 'Study pace: removing orphan local_studypace rows... <br>';
        // Remove course-level rows whose user no longer has an enrolment in
        // the corresponding course. Also clean up any cm-level rows that
        // reference a course where the user has no enrolment.
        $orphancoursesql = "DELETE FROM {local_studypace} "
                . "WHERE target = 'course' "
                . "AND NOT EXISTS ("
                . "    SELECT 1 FROM {user_enrolments} ue "
                . "    JOIN {enrol} e ON e.id = ue.enrolid "
                . "    WHERE ue.userid = {local_studypace}.userid "
                . "    AND e.courseid = {local_studypace}.targetid"
                . ")";
        $DB->execute($orphancoursesql);
        $orphancmsql = "DELETE FROM {local_studypace} "
                . "WHERE target = 'cm' "
                . "AND NOT EXISTS ("
                . "    SELECT 1 FROM {user_enrolments} ue "
                . "    JOIN {enrol} e ON e.id = ue.enrolid "
                . "    WHERE ue.userid = {local_studypace}.userid "
                . "    AND e.courseid = {local_studypace}.courseid"
                . ")";
        $DB->execute($orphancmsql);

        echo 'Study pace: queuing recalculation as an adhoc task (runs via cron). <br>';
        $task = new \local_studypace\task\recalculate_estimated_completion();
        \core\task\manager::queue_adhoc_task($task, true);

        upgrade_plugin_savepoint(true, 2025063003, 'local', 'studypace');
    }

    if ($oldversion < 2026052501) {
        // If the previous upgrade ran the recalc inline and timed out, the
        // savepoint above was still reached on retry but the recalc may not
        // have completed. Queue a fresh adhoc recalc so the new progress
        // formula sees consistent data. queue_adhoc_task() with $checkforexisting
        // dedupes by serialised payload, so this is safe to re-run.
        $task = new \local_studypace\task\recalculate_estimated_completion();
        \core\task\manager::queue_adhoc_task($task, true);

        upgrade_plugin_savepoint(true, 2026052501, 'local', 'studypace');
    }

    if ($oldversion < 2026052504) {
        // update_user_enrolment() now upserts (creates missing rows), so any
        // user who enrolled before the plugin was installed - or who had
        // tracking added to their course's format later - will finally get
        // their estimatedcompletion paces backfilled. Queue a recalc so the
        // dashboard progress bar reflects reality for everyone.
        $task = new \local_studypace\task\recalculate_estimated_completion();
        \core\task\manager::queue_adhoc_task($task, true);

        upgrade_plugin_savepoint(true, 2026052504, 'local', 'studypace');
    }

    if ($oldversion < 2026052505) {
        $dbman = $DB->get_manager();

        // 1. Bump level from INT(2) to INT(5). A course can easily have more
        //    than 99 monitored CMs (PDI conference rooms in particular), and
        //    INT(2) on Postgres can refuse values that big.
        //    change_field_precision() refuses to touch a column that has an
        //    index attached to it on Postgres, so we have to drop the
        //    {level} index, change the precision, then re-add it.
        $table = new xmldb_table('local_studypace');
        $levelindex = new xmldb_index('level', XMLDB_INDEX_NOTUNIQUE, array('level'));
        $hadlevelindex = $dbman->index_exists($table, $levelindex);
        if ($hadlevelindex) {
            $dbman->drop_index($table, $levelindex);
        }
        $field = new xmldb_field('level', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }
        if ($hadlevelindex) {
            $dbman->add_index($table, $levelindex);
        }

        // 2. Dedupe (userid, target, targetid) before adding the UNIQUE
        //    index. Read-then-insert in update/create_user_enrolment used to
        //    race; previous duplicates need to go before the constraint
        //    takes effect.
        $dedupesql = "DELETE FROM {local_studypace} "
                . "WHERE id NOT IN ("
                . "    SELECT min_id FROM ("
                . "        SELECT MIN(id) AS min_id FROM {local_studypace}"
                . "        GROUP BY userid, target, targetid"
                . "    ) AS keep"
                . ")";
        $DB->execute($dedupesql);

        // 3. Add the UNIQUE composite index.
        $index = new xmldb_index('user_target', XMLDB_INDEX_UNIQUE, array('userid', 'target', 'targetid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026052505, 'local', 'studypace');
    }

    if ($oldversion < 2026052602) {
        // One-shot repair after fixing the all_cms_completed() bug that
        // skipped mark_course_completed() whenever a user completed their
        // course's monitored CMs out of id order. Symptom in production:
        //
        //   - Eixo 1 forever "em Andamento" even though every monitored
        //     activity was completed.
        //   - Nível 0, 0% de conclusão.
        //   - No auto-enrolment into the next Eixo.
        //
        // Two complementary jobs run here:
        //
        //   sync_cm_completions(): closes the cm-level gap where Moodle's
        //   course_modules_completion row says "done" but the studypace
        //   shadow row's actualcompletion is still NULL (lost event,
        //   plugin temporarily disabled, observer cache stale, etc.).
        //
        //   repair_pending_course_completions(): for every course whose
        //   cms are now ALL done but whose course-level pace row is
        //   still NULL, call mark_course_completed() with the latest cm
        //   completion timestamp. That writes actualcompletion, fires
        //   \local_studypace\event\course_completed (which the
        //   local_profile observer turns into the Eixo auto-progression
        //   enrolment), and only fires the planned_completion fork for
        //   users who finished on time per their per-cm estimates.
        //
        // Both calls are idempotent: rerunning the upgrade or hitting
        // "Recalcular Study Pace" later is a safe no-op for already-
        // healed users.
        //
        // Running these site-wide inline timed out on large sites (~15k
        // students) — worse, repair_pending_course_completions() fires
        // course_completed / planned_completion (Eixo auto-enrol, coins,
        // badges, notifications) for every healed user, so a web timeout
        // could leave the savepoint unwritten and loop the upgrade while
        // re-triggering side effects. Defer the whole pipeline to cron.
        $task = new \local_studypace\task\recalculate_estimated_completion();
        $task->set_custom_data((object) ['fullpipeline' => true]);
        \core\task\manager::queue_adhoc_task($task, true);
        mtrace('  ... local_studypace: queued background sync + course-completion repair');

        upgrade_plugin_savepoint(true, 2026052602, 'local', 'studypace');
    }

    if ($oldversion < 2026052605) {
        // is_cm_monitorable() used to return TRUE for any cm with
        // course_modules.completion > 0, even on courses that already
        // had an explicit "Condição: Conclusão de atividade" list at
        // /course/completion.php. That over-tracked activities (SCORMs,
        // enquetes, etc. with cm-level completion ticked but NOT marked
        // as required for the course) and the course never matched
        // "all monitored cms done", so the local_profile observer
        // couldn't advance the student into the next Eixo.
        //
        // The new strict rule lives in is_cm_monitorable() (commit
        // shipping this savepoint). For users whose pace rows were
        // already seeded under the lax rule, we run
        // purge_nonmandatory_cm_rows() once to drop the now-unwanted
        // cm rows. Then we re-run repair_pending_course_completions()
        // so any course that now has 100% of its (correctly counted)
        // monitored cms completed is finally marked, fires
        // course_completed, and triggers the auto-progression.
        //
        // purge_nonmandatory_cm_rows() is idempotent and only touches
        // courses with an explicit criteria list — legacy courses
        // without explicit criteria fall through and keep the
        // permissive behaviour.
        //
        // Deferred to the adhoc full-pipeline task (purge + sync + mark +
        // backfill) so the web upgrade reaches this savepoint immediately
        // and can't loop on large sites.
        $task = new \local_studypace\task\recalculate_estimated_completion();
        $task->set_custom_data((object) ['fullpipeline' => true]);
        \core\task\manager::queue_adhoc_task($task, true);
        mtrace('  ... local_studypace: queued background purge + course-completion repair');

        upgrade_plugin_savepoint(true, 2026052605, 'local', 'studypace');
    }

    if ($oldversion < 2026052609) {
        // user_enrolments.timestart = 0 made every estimatedcompletion land
        // near 1970. Recompute via adhoc task so web upgrade does not time out.
        $task = new \local_studypace\task\recalculate_estimated_completion();
        \core\task\manager::queue_adhoc_task($task, true);
        mtrace('  ... local_studypace: queued estimatedcompletion recalc (effective enrolment start)');

        upgrade_plugin_savepoint(true, 2026052609, 'local', 'studypace');
    }

    if ($oldversion < 2026052633) {
        upgrade_plugin_savepoint(true, 2026052633, 'local', 'studypace');
    }

    if ($oldversion < 2026052635) {
        upgrade_plugin_savepoint(true, 2026052635, 'local', 'studypace');
    }

    if ($oldversion < 2026052636) {
        upgrade_plugin_savepoint(true, 2026052636, 'local', 'studypace');
    }

    if ($oldversion < 2026052637) {
        upgrade_plugin_savepoint(true, 2026052637, 'local', 'studypace');
    }

    if ($oldversion < 2026052641) {
        global $CFG;
        $salalib = $CFG->dirroot . '/course/format/saladeconferencias/lib.php';
        if (file_exists($salalib)) {
            require_once($salalib);
            if (function_exists('format_saladeconferencias_backfill_video_completion_criteria')) {
                $added = format_saladeconferencias_backfill_video_completion_criteria();
                if ($added > 0) {
                    mtrace('  ... local_studypace: registered ' . $added . ' Sala video(s) on course completion criteria');
                }
            }
        }
        // Rebuilding cm pace rows for every Sala enrolment inline timed out
        // on large sites (~15k students), so the savepoint below was never
        // reached and the upgrade looped. Defer the heavy recalculation to
        // the adhoc full-pipeline task (runs via cron). $checkforexisting
        // dedupes if the next step queues it too.
        $task = new \local_studypace\task\recalculate_estimated_completion();
        $task->set_custom_data((object) ['fullpipeline' => true]);
        \core\task\manager::queue_adhoc_task($task, true);
        mtrace('  ... local_studypace: queued background recalculation for Sala de Conferências (progress-count fix)');

        upgrade_plugin_savepoint(true, 2026052641, 'local', 'studypace');
    }

    if ($oldversion < 2026052642) {
        global $CFG;
        $salalib = $CFG->dirroot . '/course/format/saladeconferencias/lib.php';
        if (file_exists($salalib)) {
            require_once($salalib);
            if (function_exists('format_saladeconferencias_backfill_video_completion_criteria')) {
                $added = format_saladeconferencias_backfill_video_completion_criteria();
                if ($added > 0) {
                    mtrace('  ... local_studypace: registered ' . $added . ' hidden Sala video(s) on course completion criteria');
                }
            }
        }
        // Same as the previous step: never rebuild every enrolment inline.
        // Queue the adhoc full-pipeline recalculation instead so the upgrade
        // savepoint is reached immediately and the site can't loop.
        $task = new \local_studypace\task\recalculate_estimated_completion();
        $task->set_custom_data((object) ['fullpipeline' => true]);
        \core\task\manager::queue_adhoc_task($task, true);
        mtrace('  ... local_studypace: hidden Sala videos now count as incomplete in progress (recalculation queued)');

        upgrade_plugin_savepoint(true, 2026052642, 'local', 'studypace');
    }

    if ($oldversion < 2026052643) {
        // Admin "Recalcular todos" now queues an adhoc task (full pipeline)
        // instead of running inline — required on large sites (~15k users).
        // Version bump also refreshes the new lang strings.
        upgrade_plugin_savepoint(true, 2026052643, 'local', 'studypace');
    }

    if ($oldversion < 2026052644) {
        // get_course_badges_issued() now matches issued badges by name only
        // (site badges store badge.courseid = NULL), fixing the false
        // "badge not issued" error during reward repair.
        upgrade_plugin_savepoint(true, 2026052644, 'local', 'studypace');
    }

    if ($oldversion < 2026052646) {
        // Preventive gamification fixes: ensure always runs after course
        // completion, deadline recalc triggers ensure, hourly heal task.
        upgrade_plugin_savepoint(true, 2026052646, 'local', 'studypace');
    }

    if ($oldversion < 2026052647) {
        // Gap scan detects under-credited keys; heal cron awards badges even
        // when coin triggers are incomplete; aligned trigger diagnostics.
        upgrade_plugin_savepoint(true, 2026052647, 'local', 'studypace');
    }

    if ($oldversion < 2026052648) {
        // Faster gap scan: under-credit check no longer runs full diagnostic
        // per on-time completer with coins > 0.
        upgrade_plugin_savepoint(true, 2026052648, 'local', 'studypace');
    }

    if ($oldversion < 2026052649) {
        // Reward is frozen at the recorded completion event. Stop recomputing
        // expected keys from the current grade — that flagged (and would have
        // re-credited) thousands of correctly-credited standard completions as
        // under-credited once their grade later reached >= 80.
        upgrade_plugin_savepoint(true, 2026052649, 'local', 'studypace');
    }

    if ($oldversion < 2026052650) {
        // Bound the hourly heal task (max 300 heals / 120s per run) so it can
        // never run for hours and overlap with the next cron.
        upgrade_plugin_savepoint(true, 2026052650, 'local', 'studypace');
    }

    return true;
}

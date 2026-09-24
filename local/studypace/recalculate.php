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
 * Admin entry point that lets a site admin recompute every user's studypace
 * estimatedcompletion from their selected study plan (local_profile.nummonths).
 * Useful after a plan change (so the dashboard expected-progress forecast
 * refreshes), after enrolment imports, after fixing bad data, or whenever the
 * divisor of the studypace formula changes (new tracked format, new tracked
 * course, etc.).
 *
 * @package   local_studypace
 * @copyright 2025 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_studypace_recalculate');

$confirm = optional_param('confirm', 0, PARAM_INT);
$userid  = optional_param('userid', 0, PARAM_INT);
$mode    = optional_param('mode', 'all', PARAM_ALPHA);

$baseurl = new moodle_url('/local/studypace/recalculate.php');
$PAGE->set_url($baseurl);
$PAGE->set_title(get_string('recalculatepageheading', 'local_studypace'));
$PAGE->set_heading(get_string('recalculatepageheading', 'local_studypace'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('recalculatepageheading', 'local_studypace'));

if ($confirm && confirm_sesskey() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Run the recalc. update_all_user_enrolments($userid) hits both tracked
    // formats now (specialization + saladeconferencias), so this single call
    // covers every user's tracked enrolments coherently.
    @set_time_limit(0);
    raise_memory_limit(MEMORY_HUGE);
    // Release the session lock so concurrent requests from the same admin
    // aren't blocked while the batch runs.
    \core\session\manager::write_close();

    // Ordered pipeline. Each step is idempotent so re-running the page
    // never duplicates state, and each step depends on the previous one
    // having seeded the rows it operates on:
    //   1) update_all_user_enrolments(): seeds missing course/cm pace
    //      rows for users that enrolled before tracking existed and
    //      recomputes estimatedcompletion for every active enrolment.
    //   2) purge_nonmandatory_cm_rows(): drops cm-rows pointing to
    //      activities that are NOT in the course's explicit
    //      "Conclusão de atividade" criteria list. Until this runs the
    //      legacy is_cm_monitorable() fallback may have over-reported
    //      monitored cms for a course, so step 3 below can mark cms
    //      complete that step 4 still considers "pending".
    //   3) sync_cm_completions(): copies course_modules_completion into
    //      the studypace cm rows whose actualcompletion was left NULL
    //      (lost completion events, plugin disabled at the time, etc.).
    //   4) revalidate_stale_course_completions(): clears aggregate course
    //      completion when new mandatory activities were added after the
    //      student was marked complete (common in Sala de Conferências).
    //   5) repair_pending_course_completions(): marks the course row
    //      complete whenever step 3 finishes filling in its cms. This
    //      step calls mark_course_completed(), which fires the
    //      course_completed event the local_profile observer turns into
    //      "enrol the user in the next Eixo", so the recalculator also
    //      restores the Eixo auto-progression for any users it was lost
    //      on. Late completers (actualcompletion > estimatedcompletion)
    //      skip the planned_completion event the same way the live path
    //      does, so badges/messages aren't issued retroactively for
    //      users who didn't earn them at the time.
    // Step 5 (the local_profile backfill) catches the case mark_course_completed()
    // can't fix on its own: when course.actualcompletion was already set in a
    // previous repair pass, mark_course_completed() short-circuits before
    // firing the course_completed event, so the observer never runs. Calling
    // local_profile_enrol_in_next_eixo() directly bypasses that gate and
    // uses the permissive enrol helper that handles enrol_self Eixos too.
    $stats = ['purged' => 0, 'synced' => 0, 'revalidated' => 0, 'marked' => 0, 'backfilled' => 0,
        'pace' => null];
    $targetuser = null;
    if ($mode === 'user' && $userid > 0) {
        $targetuser = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, username');
        if (!$targetuser) {
            echo $OUTPUT->notification(get_string('recalculateusernotfound', 'local_studypace'), 'error');
        } else {
            // Step 1: rewrite estimatedcompletion (course + CM) from the
            // student's current study plan in local_profile.nummonths. This
            // is what refreshes the dashboard "previsão de progresso" after
            // an admin changes the plan.
            $stats['pace'] = \local_studypace\studypace::update_all_user_enrolments((int) $targetuser->id);
            $stats['purged'] = \local_studypace\studypace::purge_nonmandatory_cm_rows((int) $targetuser->id);
            $stats['synced'] = \local_studypace\studypace::sync_cm_completions((int) $targetuser->id);
            $revalid = \local_studypace\studypace::revalidate_stale_course_completions(false, (int) $targetuser->id);
            $stats['revalidated'] = (int) ($revalid->cleared ?? 0);
            $stats['marked'] = \local_studypace\studypace::repair_pending_course_completions((int) $targetuser->id);
            require_once($CFG->dirroot . '/local/profile/lib.php');
            $stats['backfilled'] = local_profile_backfill_eixo_progression((int) $targetuser->id);
            // fullname() can contain HTML metacharacters from custom name
            // fields; pass through s() so the notification doesn't paint
            // a stored name like "<script>alert(1)</script>" into the page.
            echo $OUTPUT->notification(get_string('recalculatedoneuser', 'local_studypace', s(fullname($targetuser))), 'success');
            if ($stats['pace']) {
                echo $OUTPUT->notification(get_string('recalculatepacesummary', 'local_studypace', [
                    'months' => (int) $stats['pace']->months,
                    'courses' => (int) $stats['pace']->course_updated,
                    'cms' => (int) $stats['pace']->cm_updated,
                    'processed' => (int) $stats['pace']->processed,
                    'expected' => \local_studypace\studypace::get_expected_progress((int) $targetuser->id),
                    'current' => \local_studypace\studypace::get_current_progress((int) $targetuser->id),
                ]), 'info');
            }
        }
    } else {
        // Site-wide recalculation runs as an adhoc task (cron), never inline:
        // with ~15k students the full pipeline cannot finish inside a web
        // request. Queue it and let the admin walk away.
        if (\local_studypace\task\recalculate_estimated_completion::has_pending_full_recalc()) {
            echo $OUTPUT->notification(get_string('recalculateallqueuedalready', 'local_studypace'), 'info');
        } else {
            $task = new \local_studypace\task\recalculate_estimated_completion();
            $task->set_custom_data((object) ['fullpipeline' => true]);
            \core\task\manager::queue_adhoc_task($task, true);
            echo $OUTPUT->notification(get_string('recalculateallqueued', 'local_studypace'), 'success');
        }
        echo $OUTPUT->continue_button($baseurl);
        echo $OUTPUT->footer();
        exit;
    }
    if ($stats['purged'] > 0 || $stats['synced'] > 0 || $stats['revalidated'] > 0
            || $stats['marked'] > 0 || $stats['backfilled'] > 0) {
        echo $OUTPUT->notification(
            get_string('recalculaterepairsummary', 'local_studypace', $stats),
            'info'
        );
    }

    // Per-user diagnostic: when the admin recalculates one specific user,
    // print a table with the state of each Eixo for that user so a stuck
    // case is immediately diagnosable from the UI.
    //
    // Columns:
    //   - Ordem: progression order (sortorder ASC from get_courses_in_format).
    //   - Eixo: fullname + id.
    //   - Matriculado?: is_enrolled() against the course context.
    //   - Concluído em: course-row local_studypace.actualcompletion. The
    //     menu's "next Eixo liberado" check (studypace::is_course_completed)
    //     keys off this exact field, so an empty value here means the
    //     previous Eixo is NOT actually marked complete for this user.
    //   - CMs rastreadas: total local_studypace cm-rows for the user in
    //     this course. Zero means create_user_enrolment() never ran (event
    //     lost or user enrolled before tracking was added).
    //   - CMs concluídas: cm-rows where actualcompletion IS NOT NULL.
    //     Together with "rastreadas" this shows the *real* progress of the
    //     student inside the Eixo and reveals when the user thinks they
    //     finished but didn't actually complete every monitored activity.
    //   - Pendentes sincronizar: cm-rows where studypace.actualcompletion
    //     is NULL but Moodle's course_modules_completion shows the activity
    //     as completed. The sync step normally drains this — a non-zero
    //     value here AFTER recalcular means the sync skipped these rows
    //     for some reason.
    //
    // Reading guide: the first Eixo whose "Concluído em" is empty is
    // where the progression is stuck. Look at "CMs concluídas / CMs
    // rastreadas" on that row — if it's less than 100%, the student
    // still has activities to complete; if it's 100%, the repair step
    // should have marked the course complete already, so re-running
    // Recalcular for this user is enough.
    if ($targetuser) {
        require_once($CFG->dirroot . '/course/format/specialization/lib.php');
        $eixos = \format_specialization::get_courses_in_format(true /* onlyvisible */);
        if (!empty($eixos)) {
            echo $OUTPUT->box_start('generalbox p-3 mt-3');
            echo html_writer::tag('h4', get_string('recalculatediagheading', 'local_studypace'));
            $table = new html_table();
            $table->head = [
                get_string('recalculatediagheaderorder', 'local_studypace'),
                get_string('recalculatediagheadername', 'local_studypace'),
                get_string('recalculatediagheaderenrolled', 'local_studypace'),
                get_string('recalculatediagheadercompleted', 'local_studypace'),
                get_string('recalculatediagheadercmstracked', 'local_studypace'),
                get_string('recalculatediagheadercmsdone', 'local_studypace'),
                get_string('recalculatediagheadercmspending', 'local_studypace'),
            ];
            $order = 1;
            foreach ($eixos as $eixo) {
                $ctx = \context_course::instance($eixo->id);
                $coursename = format_string($eixo->fullname, true, ['context' => $ctx]);
                $isenrolled = is_enrolled($ctx, $targetuser->id) ? '✓' : '—';
                $actual = $DB->get_field('local_studypace', 'actualcompletion', [
                    'userid' => $targetuser->id,
                    'target' => 'course',
                    'targetid' => $eixo->id,
                ]);
                $actualtxt = $actual ? userdate((int) $actual) : '—';
                // CM-level pace stats. Counts only the rows studypace itself
                // tracks (target='cm'), so the percentages stay aligned with
                // what mark_course_completed() actually evaluates.
                $cmstracked = $DB->count_records('local_studypace', [
                    'userid' => $targetuser->id,
                    'courseid' => $eixo->id,
                    'target' => 'cm',
                ]);
                $cmsdonesql = "SELECT COUNT(id) FROM {local_studypace} "
                        . "WHERE userid = :userid AND courseid = :courseid "
                        . "AND target = 'cm' "
                        . "AND actualcompletion IS NOT NULL";
                $cmsdone = (int) $DB->get_field_sql($cmsdonesql, [
                    'userid' => $targetuser->id,
                    'courseid' => $eixo->id,
                ]);
                // CMs that Moodle's completion considers done but whose
                // studypace shadow row is still NULL. This is exactly what
                // sync_cm_completions() targets — a non-zero value after
                // recalcular signals a real drift between the two tables
                // (CM not on the course's tracked list, etc.).
                $pendingsql = "SELECT COUNT(sp.id) "
                        . "FROM {local_studypace} sp "
                        . "JOIN {course_modules_completion} cmc "
                        . "  ON cmc.userid = sp.userid AND cmc.coursemoduleid = sp.targetid "
                        . "WHERE sp.userid = :userid AND sp.courseid = :courseid "
                        . "AND sp.target = 'cm' AND sp.actualcompletion IS NULL "
                        . "AND cmc.completionstate IN (1, 2, 3)";
                $cmspending = (int) $DB->get_field_sql($pendingsql, [
                    'userid' => $targetuser->id,
                    'courseid' => $eixo->id,
                ]);
                $table->data[] = [
                    $order,
                    $coursename . ' (id=' . $eixo->id . ')',
                    $isenrolled,
                    $actualtxt,
                    $cmstracked,
                    $cmsdone,
                    $cmspending,
                ];
                $order++;
            }
            echo html_writer::table($table);
            echo $OUTPUT->box_end();

            // Gamification diagnostic: keys (local_coin) and badges
            // (local_autobadge) both listen to planned_completion events,
            // which only fire when mark_course_completed() runs on time.
            echo $OUTPUT->box_start('generalbox p-3 mt-3');
            echo html_writer::tag('h4', get_string('recalculatediaggamificationheading', 'local_studypace'));
            try {
                if (!method_exists(\local_studypace\studypace::class, 'get_user_gamification_summary')) {
                    throw new \moodle_exception('recalculatediaggamificationoutdated', 'local_studypace');
                }
                $gsummary = \local_studypace\studypace::get_user_gamification_summary((int) $targetuser->id);
                echo html_writer::tag('p', get_string('recalculatediaggamificationsummary', 'local_studypace', [
                    'keys' => $gsummary->total_keys,
                    'badges' => $gsummary->total_badges,
                    'coinstd' => $gsummary->coin_trigger_standard !== null
                        ? $gsummary->coin_trigger_standard
                        : get_string('recalculatediagnotconfigured', 'local_studypace'),
                    'coinhg' => $gsummary->coin_trigger_highgrade !== null
                        ? $gsummary->coin_trigger_highgrade
                        : get_string('recalculatediagnotconfigured', 'local_studypace'),
                ]), ['class' => 'mb-3']);
                if (!$gsummary->triggers_configured) {
                    echo $OUTPUT->notification(
                        get_string('recalculatediagcointriggerswarning', 'local_studypace'),
                        'warning'
                    );
                }
            } catch (\Throwable $e) {
                echo $OUTPUT->notification(
                    get_string('recalculatediaggamificationerror', 'local_studypace', $e->getMessage()),
                    'notifyproblem'
                );
                $gsummary = null;
            }

            $gametable = new html_table();
            $gametable->head = [
                get_string('recalculatediagheadername', 'local_studypace'),
                get_string('recalculatediaggamificationdeadline', 'local_studypace'),
                get_string('recalculatediagheadercompleted', 'local_studypace'),
                get_string('recalculatediaggamificationontime', 'local_studypace'),
                get_string('recalculatediaggamificationevent', 'local_studypace'),
                get_string('recalculatediaggamificationgrade', 'local_studypace'),
                get_string('recalculatediaggamificationbadge', 'local_studypace'),
                get_string('recalculatediaggamificationcoins', 'local_studypace'),
                get_string('recalculatediaggamificationstatus', 'local_studypace'),
            ];
            foreach ($eixos as $eixo) {
                if (!method_exists(\local_studypace\studypace::class, 'get_gamification_diagnostic')) {
                    break;
                }
                try {
                    $g = \local_studypace\studypace::get_gamification_diagnostic((int) $targetuser->id, (int) $eixo->id);
                } catch (\Throwable $e) {
                    continue;
                }
                if (property_exists($g, 'valid') && $g->valid === false) {
                    continue;
                }
                $eixoctx = \context_course::instance($eixo->id);
                $eixoname = format_string($eixo->fullname, true, ['context' => $eixoctx])
                    . ' (' . s($g->courseshortname) . ')';

                if ($g->completed_on_time === true) {
                    $ontimetxt = get_string('recalculatediagyes', 'local_studypace');
                } else if ($g->completed_on_time === false) {
                    $ontimetxt = get_string('recalculatediagno', 'local_studypace');
                } else {
                    $ontimetxt = '—';
                }

                $eventkey = property_exists($g, 'planned_event_key') ? $g->planned_event_key : 'none';
                $eventstringid = 'recalculatediaggamificationevent_' . $eventkey;
                $eventtxt = get_string_manager()->string_exists($eventstringid, 'local_studypace')
                    ? get_string($eventstringid, 'local_studypace')
                    : $eventkey;

                $badgenames = property_exists($g, 'badge_issued_names') ? $g->badge_issued_names : '';
                if (!empty($g->badge_issued) && $badgenames !== '') {
                    $badgetxt = s($badgenames)
                        . ' (' . get_string('recalculatediaggamificationbadgeissued', 'local_studypace') . ')';
                } else if ($g->badge_expected_exists) {
                    $badgetxt = s($g->badge_expected_name);
                    if ($g->gamification_expected) {
                        $badgetxt .= ' (' . get_string('recalculatediaggamificationbadgemissing', 'local_studypace') . ')';
                    } else {
                        $badgetxt .= ' (' . get_string('recalculatediaggamificationbadgenotdue', 'local_studypace') . ')';
                    }
                } else {
                    $badgetxt = get_string('recalculatediaggamificationbadgenotfound', 'local_studypace', $g->badge_expected_name);
                }

                if ($g->coins_credited_course > 0) {
                    $coinstxt = get_string('recalculatediaggamificationcoinsreceived', 'local_studypace', $g->coins_credited_course);
                    if ($g->gamification_expected) {
                        $expectedcoins = ($g->planned_event_key === 'high_grade')
                            ? $g->coin_trigger_highgrade
                            : $g->coin_trigger_standard;
                        if ($expectedcoins !== null) {
                            $coinstxt .= ' ' . get_string('recalculatediaggamificationcoinsexpectedshort', 'local_studypace', $expectedcoins);
                        }
                    }
                } else if ($g->gamification_expected) {
                    $expectedcoins = ($g->planned_event_key === 'high_grade')
                        ? $g->coin_trigger_highgrade
                        : $g->coin_trigger_standard;
                    $coinstxt = ($expectedcoins !== null)
                        ? get_string('recalculatediaggamificationcoinsexpected', 'local_studypace', [
                            'expected' => $expectedcoins,
                            'received' => 0,
                        ])
                        : get_string('recalculatediagnotconfigured', 'local_studypace');
                } else {
                    $coinstxt = '—';
                }

                if ($g->gamification_delivered) {
                    $statustxt = get_string('recalculatediaggamificationstatusok', 'local_studypace');
                } else if ($g->gamification_expected && !$g->gamification_delivered) {
                    $statustxt = get_string('recalculatediaggamificationstatusmissing', 'local_studypace');
                } else if ($g->planned_event_key === 'incomplete') {
                    $statustxt = get_string('recalculatediaggamificationstatusincomplete', 'local_studypace', [
                        'done' => $g->mandatory_done,
                        'total' => $g->mandatory_total,
                    ]);
                } else {
                    $statustxt = get_string('recalculatediaggamificationstatusna', 'local_studypace');
                }

                $gametable->data[] = [
                    $eixoname,
                    !empty($g->gamification_deadlinetxt) ? $g->gamification_deadlinetxt : $g->estimatedcompletiontxt,
                    $g->actualcompletiontxt,
                    $ontimetxt,
                    $eventtxt,
                    $g->grade > 0 ? round($g->grade, 0) . '%' : '—',
                    $badgetxt,
                    $coinstxt,
                    $statustxt,
                ];
            }
            if (!empty($gametable->data)) {
                echo html_writer::table($gametable);
            } else {
                echo html_writer::tag('p', get_string('recalculatediaggamificationempty', 'local_studypace'), ['class' => 'mb-3']);
            }

            // Ledger + badges actually stored (same sources as the dashboard).
            $ledgerrows = [];
            $userbadges = [];
            if (method_exists(\local_studypace\studypace::class, 'get_user_coin_ledger')) {
                try {
                    $ledgerrows = \local_studypace\studypace::get_user_coin_ledger((int) $targetuser->id);
                } catch (\Throwable $e) {
                    // Shown via empty ledger message below.
                }
            }
            if (method_exists(\local_studypace\studypace::class, 'get_user_badges_issued')) {
                try {
                    $userbadges = \local_studypace\studypace::get_user_badges_issued((int) $targetuser->id);
                } catch (\Throwable $e) {
                    // Shown via empty badges message below.
                }
            }

            echo html_writer::tag('h5', get_string('recalculatediagrewardsheading', 'local_studypace'), ['class' => 'mt-4']);
            echo html_writer::tag('p', get_string('recalculatediagrewardsintro', 'local_studypace'), ['class' => 'text-muted small']);

            echo html_writer::tag('h6', get_string('recalculatediagrewardskeys', 'local_studypace'), ['class' => 'mt-3']);
            if (empty($ledgerrows)) {
                echo html_writer::tag('p', get_string('recalculatediagrewardsnone', 'local_studypace'), ['class' => 'mb-0']);
            } else {
                $ledgertable = new html_table();
                $ledgertable->head = [
                    get_string('recalculatediagrewardscourse', 'local_studypace'),
                    get_string('recalculatediagrewardsamount', 'local_studypace'),
                    get_string('recalculatediagrewardsaction', 'local_studypace'),
                    get_string('recalculatediagrewardsdate', 'local_studypace'),
                ];
                foreach ($ledgerrows as $row) {
                    $courselabel = $row->courseid > 0
                        ? format_string($DB->get_field('course', 'fullname', ['id' => $row->courseid]) ?: ('id=' . $row->courseid))
                        : get_string('recalculatediagrewardsglobal', 'local_studypace');
                    $actionshort = $row->action;
                    if (strpos($actionshort, 'planned_completion_high_grade') !== false) {
                        $actionshort = 'planned_completion_high_grade';
                    } else if (strpos($actionshort, 'planned_completion') !== false) {
                        $actionshort = 'planned_completion';
                    }
                    $ledgertable->data[] = [
                        $courselabel,
                        '+' . (int) $row->amount,
                        s($actionshort),
                        userdate((int) $row->transactiontime),
                    ];
                }
                echo html_writer::table($ledgertable);
            }

            echo html_writer::tag('h6', get_string('recalculatediagrewardsbadges', 'local_studypace'), ['class' => 'mt-3']);
            if (empty($userbadges)) {
                echo html_writer::tag('p', get_string('recalculatediagrewardsbadgesnone', 'local_studypace'), ['class' => 'mb-0']);
            } else {
                $badgestable = new html_table();
                $badgestable->head = [
                    get_string('recalculatediagrewardsbadgename', 'local_studypace'),
                    get_string('recalculatediagrewardsdate', 'local_studypace'),
                ];
                foreach ($userbadges as $badge) {
                    $badgestable->data[] = [
                        s($badge->name),
                        userdate((int) $badge->dateissued),
                    ];
                }
                echo html_writer::table($badgestable);
            }

            echo html_writer::tag('p', get_string('recalculatediaggamificationfootnote', 'local_studypace'), [
                'class' => 'text-muted small mb-0 mt-2',
            ]);
            echo $OUTPUT->box_end();

            // Per-Eixo breakdown of the activities that are still
            // pending. When the table above shows "CMs concluídas <
            // CMs rastreadas", the admin needs to know WHICH activities
            // the student has yet to complete — otherwise they're left
            // with just the count and have to dig manually. This block
            // lists, for each Eixo with any pending cm row, the
            // activity name, module type, and a direct link to it.
            // Ordered by studypace.level so the list matches the
            // estimated progression sequence the student sees.
            $hasanypending = false;
            foreach ($eixos as $eixo) {
                $pendingrows = $DB->get_records_sql(
                    "SELECT sp.id, sp.targetid AS cmid, sp.level
                       FROM {local_studypace} sp
                      WHERE sp.userid = :userid
                        AND sp.courseid = :courseid
                        AND sp.target = 'cm'
                        AND sp.actualcompletion IS NULL
                      ORDER BY sp.level ASC, sp.id ASC",
                    ['userid' => $targetuser->id, 'courseid' => $eixo->id]
                );
                if (empty($pendingrows)) {
                    continue;
                }
                if (!$hasanypending) {
                    echo html_writer::tag('h4',
                        get_string('recalculatediagpendingheading', 'local_studypace'),
                        ['class' => 'mt-4']
                    );
                    $hasanypending = true;
                }
                $eixoctx = \context_course::instance($eixo->id);
                $eixoname = format_string($eixo->fullname, true, ['context' => $eixoctx]);
                echo html_writer::tag('h5', $eixoname, ['class' => 'mt-3']);
                $list = html_writer::start_tag('ul');
                foreach ($pendingrows as $row) {
                    // get_coursemodule_from_id() with empty modname
                    // accepts any module type and returns FALSE when the
                    // cm record was deleted — keeps the list robust
                    // against legacy pace rows pointing to removed
                    // activities.
                    $cm = get_coursemodule_from_id('', (int) $row->cmid, 0, false, IGNORE_MISSING);
                    if (!$cm) {
                        $list .= html_writer::tag('li',
                            get_string('recalculatediagpendingmissingcm', 'local_studypace', (int) $row->cmid)
                        );
                        continue;
                    }
                    $cmname = format_string($cm->name, true, ['context' => $eixoctx]);
                    $cmurl = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);
                    $list .= html_writer::tag('li',
                        html_writer::link($cmurl, $cmname, ['target' => '_blank', 'rel' => 'noopener'])
                        . ' '
                        . html_writer::tag('span',
                            '(' . $cm->modname . ', cmid=' . $cm->id . ')',
                            ['class' => 'text-muted small']
                        )
                    );
                }
                $list .= html_writer::end_tag('ul');
                echo $list;
            }

            // Show what the auto-progression helper would do RIGHT NOW for
            // this user. If the table says "Eixo 2 concluído mas Eixo 3
            // não está matriculado", this line confirms whether the helper
            // sees that gap and which Eixo it intends to enrol the user
            // into next. Empty result = nothing to do (either all
            // completed Eixos are followed by an enrolment, or no Eixos
            // are completed yet so the helper has nothing to chain off).
            require_once($CFG->dirroot . '/local/profile/lib.php');
            $completedeixos = $DB->get_records_sql(
                "SELECT targetid AS courseid
                   FROM {local_studypace}
                  WHERE userid = :userid
                    AND target = 'course'
                    AND actualcompletion IS NOT NULL",
                ['userid' => $targetuser->id]
            );
            $diagmsg = get_string('recalculatediagnotarget', 'local_studypace');
            if (!empty($completedeixos)) {
                foreach ($completedeixos as $completed) {
                    // Simulate without enrolling: find the next Eixo in
                    // order and report whether the user is already in it.
                    $found = false;
                    foreach ($eixos as $eixo) {
                        if ($found) {
                            $alreadyin = is_enrolled(\context_course::instance($eixo->id), $targetuser->id);
                            $diagmsg = get_string('recalculatediagnext', 'local_studypace', [
                                'completed' => format_string($DB->get_field('course', 'fullname', ['id' => $completed->courseid])
                                    ?: ('id=' . $completed->courseid)),
                                'next' => format_string($eixo->fullname),
                                'state' => $alreadyin
                                    ? get_string('recalculatediagstatein', 'local_studypace')
                                    : get_string('recalculatediagstateout', 'local_studypace'),
                            ]);
                            break 2;
                        }
                        if ((int) $eixo->id === (int) $completed->courseid) {
                            $found = true;
                        }
                    }
                }
            }
            echo html_writer::tag('p', $diagmsg, ['class' => 'mt-3 mb-0']);
        }
    }

    echo $OUTPUT->continue_button($baseurl);
    echo $OUTPUT->footer();
    exit;
}

// Show the form.
echo html_writer::tag('p', get_string('recalculateintro', 'local_studypace'));
echo html_writer::tag('p', html_writer::tag('em', get_string('recalculatewarning', 'local_studypace')));

echo $OUTPUT->box_start('generalbox p-3 mb-4');
echo html_writer::tag('h4', get_string('recalculateallheading', 'local_studypace'));
echo html_writer::tag('p', get_string('recalculatealldescription', 'local_studypace'));
$allurl = new moodle_url($baseurl, ['confirm' => 1, 'mode' => 'all', 'sesskey' => sesskey()]);
echo $OUTPUT->single_button($allurl, get_string('recalculateallbutton', 'local_studypace'), 'post');
echo $OUTPUT->box_end();

echo $OUTPUT->box_start('generalbox p-3');
echo html_writer::tag('h4', get_string('recalculateuserheading', 'local_studypace'));
echo html_writer::tag('p', get_string('recalculateuserdescription', 'local_studypace'));
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $baseurl->out(false),
    'class'  => 'form-inline',
]);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'mode',
    'value' => 'user',
]);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'confirm',
    'value' => '1',
]);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sesskey',
    'value' => sesskey(),
]);
echo html_writer::tag('label', get_string('recalculateuseridlabel', 'local_studypace') . ': ', [
    'for' => 'studypace_userid',
    'class' => 'mr-2',
]);
echo html_writer::empty_tag('input', [
    'type'  => 'number',
    'min'   => '1',
    'id'    => 'studypace_userid',
    'name'  => 'userid',
    'class' => 'form-control mr-2',
    'required' => 'required',
    'style' => 'width: 8rem;',
]);
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'class' => 'btn btn-secondary',
    'value' => get_string('recalculateuserbutton', 'local_studypace'),
]);
echo html_writer::end_tag('form');
echo $OUTPUT->box_end();

echo $OUTPUT->footer();

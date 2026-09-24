<?php

/**
 * Profile helper functions.
 *
 * @package   local_profile
 */
defined('MOODLE_INTERNAL') || die();

function local_profile_get_avatars($context) {
    global $CFG;

    static $cache = [];
    if (isset($cache[$context])) {
        return $cache[$context];
    }

    $avatarspath = $CFG->dirroot . '/local/profile/pix/avatars';
    if (!is_dir($avatarspath)) {
        $cache[$context] = [];
        return $cache[$context];
    }
    $filenames = scandir($avatarspath);
    if ($filenames === false) {
        $cache[$context] = [];
        return $cache[$context];
    }
    $avatars = array();
    $count = 1;
    foreach ($filenames as $filename) {
        $filenameeparts = explode('-', $filename);
        if ((count($filenameeparts) >= 2) && ($filenameeparts[1] == $context)) {
            // Return paths relative to wwwroot. The avatar selector posts this
            // value back to updateprofile.php, which validates it as a
            // PARAM_PATH starting with /local/profile/pix/avatars/. Returning
            // an absolute URL (http://host/...) would survive PARAM_PATH only
            // by losing its colons, so the regex check would silently reject
            // it and the avatar would never be saved.
            $avatars[$count] = '/local/profile/pix/avatars/' . $filename;
            ++$count;
        }
    }
    $cache[$context] = $avatars;
    return $avatars;
}

function local_profile_get_last_profile_update($user) {
    global $DB;

    $lastprofileupdate = $DB->get_record('local_profile', array(
        'userid' => $user->id
    ));
    return $lastprofileupdate;
}

function local_profile_get_last_profile_update_time($user) {
    $lastprofileupdate = local_profile_get_last_profile_update($user);
    if ($lastprofileupdate) {
        return $lastprofileupdate->updatetime;
    }
    return 0;
}

/**
 * Enrol a user into a course via the given enrolment plugin.
 *
 * Returns false on every soft failure (plugin disabled, course doesn't have
 * the requested enrol method, course has multiple instances of that method),
 * matching the historical contract. Each false path now also writes to
 * debugging() so a misconfigured Eixo (e.g. manual enrolment instance
 * missing) doesn't fail silently when called from the auto-progression
 * observer — without this, the only signal an admin had was "students are
 * not progressing" with no log to look at.
 *
 * @param int $userid
 * @param int $courseid
 * @param int|string|null $roleidorshortname Role id, role shortname, or null
 *        to use the enrolment instance's default role.
 * @param string $enrol Enrolment plugin name, e.g. 'manual'.
 * @param int $timestart Unix timestamp; 0 means "now".
 * @param int $timeend Unix timestamp; 0 means "never".
 * @param int|null $status ENROL_USER_ACTIVE / ENROL_USER_SUSPENDED or null
 *        to use the plugin default.
 * @return bool True on success, false on soft failure.
 */
function local_profile_enrol_user($userid, $courseid, $roleidorshortname = null, $enrol = 'manual', $timestart = 0, $timeend = 0, $status = null) {
    global $DB;

    if (!is_numeric($roleidorshortname) && is_string($roleidorshortname)) {
        $roleid = $DB->get_field('role', 'id', array('shortname' => $roleidorshortname), MUST_EXIST);
    } else {
        $roleid = $roleidorshortname;
    }

    if (!$plugin = enrol_get_plugin($enrol)) {
        debugging("local_profile_enrol_user: enrolment plugin '{$enrol}' is "
                . "disabled or not installed (user={$userid}, course={$courseid}).",
                DEBUG_DEVELOPER);
        return false;
    }

    $instances = $DB->get_records('enrol', array('courseid' => $courseid, 'enrol' => $enrol));
    if (count($instances) != 1) {
        debugging("local_profile_enrol_user: course {$courseid} has "
                . count($instances) . " '{$enrol}' enrolment instances "
                . "(expected exactly 1) — user {$userid} was NOT enrolled. "
                . "Add a single '{$enrol}' enrolment method to the course.",
                DEBUG_DEVELOPER);
        return false;
    }
    $instance = reset($instances);

    if (is_null($roleid) and $instance->roleid) {
        $roleid = $instance->roleid;
    }

    $plugin->enrol_user($instance, $userid, $roleid, $timestart, $timeend, $status);
    return true;
}

/**
 * Auto-enrol a user into a course using whatever enrolment plugin the course
 * has configured. This is the helper used by every system-initiated enrolment
 * (first-Eixo on profile save, next-Eixo on course completion, backfill upgrade
 * step) and is intentionally MORE permissive than local_profile_enrol_user().
 *
 * Background: local_profile_enrol_user() requires the course to have exactly
 * one enabled instance of a specifically-named plugin (defaults to manual).
 * That works fine for sites where every Eixo has the default manual instance,
 * but production PDI Eixos commonly disable manual and rely on enrol_self so
 * students self-enrol from the top-navigation Eixos menu (which links to
 * /enrol/index.php). When the live observer that drives Eixo auto-progression
 * then calls into the enrol helper with 'manual' hardcoded, the helper finds
 * zero manual instances and bails — leaving the student stuck on the previous
 * Eixo even though the menu correctly shows the next one as "liberado".
 *
 * Strategy:
 *  1. Prefer 'manual' if the course has exactly one active instance. This is
 *     the conventional admin-initiated path and keeps the enrolment history
 *     readable ("enroled by manual" in {user_enrolments}.modifierid logs).
 *  2. Otherwise prefer 'self' if available. enrol_self_plugin::enrol_user()
 *     does not enforce the user-facing password/key gate — that gate lives
 *     in the /enrol/index.php form — so calling it programmatically simply
 *     creates the user_enrolments row, which is exactly what we want for an
 *     admin-driven progression.
 *  3. As a last resort, pick the FIRST other enabled instance the course has
 *     (cohort, lti, etc.). The fallback is best-effort and intentionally
 *     loose: if none of these work, the function logs a precise message via
 *     debugging() and returns false so the caller can decide what to do.
 *
 * Skips plugins that are disabled site-wide (enrol_get_plugin() returns null
 * for those), plugins on the course whose status is ENROL_INSTANCE_DISABLED,
 * and plugins that don't exist on this Moodle install.
 *
 * @param int $userid
 * @param int $courseid
 * @param int|string|null $roleidorshortname Role to assign (defaults to 'student').
 * @param int $timestart
 * @param int $timeend
 * @param int|null $status
 * @return bool True when an enrolment was created.
 */
function local_profile_auto_enrol_in_course($userid, $courseid, $roleidorshortname = 'student', $timestart = 0, $timeend = 0, $status = null) {
    global $DB;

    if (!is_numeric($roleidorshortname) && is_string($roleidorshortname)) {
        $roleid = $DB->get_field('role', 'id', array('shortname' => $roleidorshortname), MUST_EXIST);
    } else {
        $roleid = $roleidorshortname;
    }

    // Probe the course's enabled enrol instances ordered by our preference
    // list. ENROL_INSTANCE_ENABLED == 0 in Moodle's enrollib.
    $instances = $DB->get_records('enrol',
            array('courseid' => $courseid, 'status' => 0 /* ENROL_INSTANCE_ENABLED */),
            'sortorder ASC');
    if (empty($instances)) {
        debugging("local_profile_auto_enrol_in_course: course {$courseid} "
                . "has no enabled enrolment instances (user={$userid}).",
                DEBUG_NORMAL);
        return false;
    }

    // Group by plugin name so we can iterate the preference list cheaply.
    $byplugin = array();
    foreach ($instances as $instance) {
        $byplugin[$instance->enrol][] = $instance;
    }
    // Preference order: explicit known-good plugins first, then anything else
    // the course has on. We DON'T fall through to plugins that are clearly
    // not designed for automatic enrolment (e.g. paypal/stripe would
    // double-bill, guest doesn't create a real enrolment).
    $skipfallback = array('guest', 'paypal', 'stripe', 'fee');
    $preferred = array('manual', 'self');
    $others = array_diff(array_keys($byplugin), $preferred, $skipfallback);
    $order = array_merge($preferred, $others);

    foreach ($order as $enrolname) {
        if (empty($byplugin[$enrolname])) {
            continue;
        }
        if (!$plugin = enrol_get_plugin($enrolname)) {
            // Plugin disabled site-wide or not installed.
            continue;
        }
        // If a course has multiple instances of the same plugin (rare but
        // possible — e.g. two self instances for different cohorts) just
        // use the first one. The user only needs ONE enrolment row to be
        // "in" the course; the others stay available for human self-enrol.
        $instance = reset($byplugin[$enrolname]);
        $finalrole = $roleid;
        if (is_null($finalrole) && !empty($instance->roleid)) {
            $finalrole = $instance->roleid;
        }
        try {
            $plugin->enrol_user($instance, $userid, $finalrole, $timestart, $timeend, $status);
        } catch (\Throwable $e) {
            debugging("local_profile_auto_enrol_in_course: plugin '{$enrolname}' "
                    . "threw '{$e->getMessage()}' enroling user {$userid} in "
                    . "course {$courseid}; trying next plugin.",
                    DEBUG_NORMAL);
            continue;
        }
        return true;
    }

    debugging("local_profile_auto_enrol_in_course: no usable enrolment plugin "
            . "for course {$courseid} (user={$userid}). Tried: "
            . implode(', ', $order) . '.', DEBUG_NORMAL);
    return false;
}

function local_profile_update_enrolment_end($userid, $courseid, $enrolmentend, $enrol = 'manual') {
    global $DB;

    if (!$plugin = enrol_get_plugin($enrol)) {
        return false;
    }
    $instances = $DB->get_records('enrol', array('courseid' => $courseid, 'enrol' => $enrol));
    if (count($instances) != 1) {
        return false;
    }
    $instance = reset($instances);
    $plugin->update_user_enrol($instance, $userid, NULL, NULL, $enrolmentend);
}

function local_profile_get_user_months($userid) {
    global $DB;

    $userprofile = $DB->get_record('local_profile', array(
        'userid' => $userid
    ));
    if (!$userprofile) {
        return 0;
    }
    return $userprofile->nummonths;
}

/**
 * Return every Eixo (specialization-format course) in progression order.
 *
 * Wraps format_specialization::get_courses_in_format() so callers in this
 * plugin don't have to repeat the require_once or the onlyvisible argument.
 * Hidden courses are excluded because the auto-progression workflow only
 * enrols users into Eixos that are actually reachable.
 *
 * @return stdClass[] Indexed by course id.
 */
function local_profile_get_eixos_in_order() {
    global $CFG;
    require_once($CFG->dirroot . '/course/format/specialization/lib.php');
    return format_specialization::get_courses_in_format(true /* onlyvisible */);
}

/**
 * Auto-enrol the user in the first Eixo of the progression.
 *
 * Called on the very first profile save (from updateprofile.php). Skips:
 *  - Site admins (they shouldn't get auto-enrolled in student tracks).
 *  - Users that are already enrolled in the first Eixo (idempotent re-runs).
 *
 * Returns true when an enrolment was created, false when skipped (admin,
 * no Eixos defined, already enrolled, etc.).
 *
 * @param int $userid
 * @return bool
 */
function local_profile_enrol_in_first_eixo($userid) {
    if (is_siteadmin($userid)) {
        return false;
    }
    $eixos = local_profile_get_eixos_in_order();
    $first = reset($eixos);
    if (!$first) {
        return false;
    }
    $context = context_course::instance($first->id);
    if (is_enrolled($context, $userid)) {
        return false;
    }
    // Use the permissive auto-enrol helper so this works regardless of
    // whether the Eixo has manual or self enrolment configured. See
    // local_profile_auto_enrol_in_course() for the plugin-selection rules.
    return local_profile_auto_enrol_in_course($userid, $first->id, 'student');
}

/**
 * Auto-enrol the user in the Eixo that follows $completedeixoid in the
 * progression. Called by the local_studypace\event\course_completed
 * observer.
 *
 * Skips:
 *  - Site admins.
 *  - Completed Eixo isn't part of the progression at all (e.g. Sala de
 *    Conferências, which is a non-linear node).
 *  - There is no "next" (visible) Eixo (user finished the last one).
 *  - User is already enrolled in the next Eixo (could happen if they
 *    self-enrolled via the UI before completing the previous one).
 *
 * Hidden-Eixo handling: the completed Eixo is located against the FULL
 * ordered list (visible + hidden). A completion can legitimately land while
 * an Eixo is temporarily hidden (admin editing it); locating against the
 * visible-only list used to lose the completed row entirely and stall the
 * progression permanently (the course_completed event is idempotent and never
 * re-fires). We still only ENROL the user into the next *visible* (reachable)
 * Eixo — enrolling someone into a hidden course they cannot open is pointless.
 *
 * Transient-failure handling: when the next Eixo is found, the user isn't
 * enrolled yet, and local_profile_auto_enrol_in_course() reports failure
 * (e.g. a momentary DB/enrol-plugin hiccup), a bounded adhoc retry is queued
 * so the student isn't left stranded. This mirrors the gamification
 * ensure/heal retry. It is deliberately scoped to THIS freshly-completed
 * progression — it is NOT a history sweep, so it can never resurrect
 * enrolments an admin removed on purpose.
 *
 * @param int $userid
 * @param int $completedeixoid Course id of the Eixo the user just finished.
 * @param int $attempt Retry attempt number (0 = first/live call).
 * @param bool $queueretry Queue an adhoc retry on genuine enrol failure.
 * @return bool True when a new enrolment was created.
 */
function local_profile_enrol_in_next_eixo($userid, $completedeixoid, $attempt = 0, $queueretry = true) {
    global $CFG;

    if (is_siteadmin($userid)) {
        return false;
    }
    require_once($CFG->dirroot . '/course/format/specialization/lib.php');
    // Full list (visible + hidden) so a completed Eixo that is momentarily
    // hidden still resolves its position in the progression.
    $eixos = format_specialization::get_courses_in_format(false);
    if (empty($eixos)) {
        return false;
    }
    $found = false;
    foreach ($eixos as $eixo) {
        if ($found) {
            // Only enrol into reachable (visible) Eixos. A hidden one in the
            // middle is skipped so we land on the next visible target — the
            // same effect the previous visible-only scan had for intermediate
            // hidden Eixos, but now without breaking the locate step above.
            if (empty($eixo->visible)) {
                continue;
            }
            $context = context_course::instance($eixo->id);
            if (is_enrolled($context, $userid)) {
                return false;
            }
            // Auto-enrol fell back from 'manual' to a permissive plugin
            // search (manual → self → first other enabled) so the
            // progression keeps working on Eixos that disable manual
            // enrolment and rely on enrol_self for the menu-driven join.
            $ok = local_profile_auto_enrol_in_course($userid, $eixo->id, 'student');
            if (!$ok && $queueretry) {
                local_profile_queue_next_eixo_retry($userid, $completedeixoid, $attempt);
            }
            return $ok;
        }
        if ((int) $eixo->id === (int) $completedeixoid) {
            $found = true;
        }
    }
    // Either $completedeixoid wasn't an Eixo at all (e.g. Sala de
    // Conferências, which fires the same studypace event but isn't part
    // of the linear progression) or the user just finished the last Eixo.
    // Both cases: nothing to enrol into.
    return false;
}

/**
 * Queue a bounded adhoc retry of local_profile_enrol_in_next_eixo() after a
 * genuine auto-enrol failure.
 *
 * Safety properties for production:
 *  - Bounded: at most LOCAL_PROFILE_NEXT_EIXO_MAX_RETRIES attempts, then it
 *    gives up and logs (no infinite queue growth).
 *  - Targeted: only the specific user/completed-Eixo pair that just failed is
 *    retried. There is no scan of historical completions, so admin-removed
 *    enrolments are never recreated.
 *  - Idempotent: the actual enrol still goes through is_enrolled(), so a retry
 *    that races with a successful live enrol is a harmless no-op.
 *
 * @param int $userid
 * @param int $completedeixoid
 * @param int $attempt The attempt that just failed (0-based).
 * @return void
 */
function local_profile_queue_next_eixo_retry($userid, $completedeixoid, $attempt) {
    $attempt = (int) $attempt;
    if (!defined('LOCAL_PROFILE_NEXT_EIXO_MAX_RETRIES')) {
        define('LOCAL_PROFILE_NEXT_EIXO_MAX_RETRIES', 3);
    }
    if ($attempt >= LOCAL_PROFILE_NEXT_EIXO_MAX_RETRIES) {
        debugging('local_profile: giving up next-Eixo auto-enrol for user ' . (int) $userid
                . ' after course ' . (int) $completedeixoid . ' (max retries reached).', DEBUG_NORMAL);
        return;
    }
    // Exponential-ish backoff: 5 min, 30 min, 2 h.
    $delays = array(300, 1800, 7200);
    $delay = isset($delays[$attempt]) ? $delays[$attempt] : 7200;

    $task = new \local_profile\task\enrol_next_eixo();
    $task->set_custom_data(array(
        'userid' => (int) $userid,
        'completedeixoid' => (int) $completedeixoid,
        'attempt' => $attempt + 1,
    ));
    $task->set_next_run_time(time() + $delay);
    // checkforexisting=true dedupes identical pending tasks (same component +
    // classname + custom data), so repeated failures of the same attempt don't
    // pile up duplicates.
    \core\task\manager::queue_adhoc_task($task, true);
}

/**
 * One-shot backfill of the Eixo auto-progression for users who completed
 * earlier Eixos BEFORE the observer that wires \local_studypace\event\course_completed
 * into local_profile_enrol_in_next_eixo() shipped.
 *
 * Why this is needed:
 *  - studypace::mark_course_completed() short-circuits the moment
 *    local_studypace.actualcompletion is set, and only fires the
 *    course_completed event on that initial write. Sites that ran
 *    pre-2026052602 (when the observer didn't exist yet) have a population
 *    of students with completed Eixos whose event never had a listener,
 *    so they're stuck without auto-enrolment into the next Eixo.
 *  - Re-running mark_course_completed() to re-fire the event isn't safe:
 *    it would also re-fire planned_completion(_high_grade), re-sending
 *    achievement messages and double-counting badges.
 *
 * The backfill iterates every (user, completed-Eixo) pair already on disk
 * and pushes it through local_profile_enrol_in_next_eixo(), which is
 * idempotent: it skips site admins, completions that aren't part of the
 * Eixo progression (e.g. Sala de Conferências), and users that are
 * already enrolled in the next Eixo. So even if the upgrade re-runs for
 * any reason, it converges on the same state without creating duplicate
 * enrolments.
 *
 * The loop tolerates per-user exceptions so a single misconfigured
 * enrolment instance (e.g. manual enrol disabled on one Eixo) doesn't
 * abort the whole batch — the failure is reported via debugging() so
 * admins can chase it down without losing the rest of the backfill.
 *
 * @return int Number of new enrolments actually created.
 */
function local_profile_backfill_eixo_progression($userid = 0) {
    global $DB;

    $created = 0;

    // One DB pass: every course-level completion already on record.
    // target='course' filters out the cm-level rows in the same table.
    // actualcompletion IS NOT NULL is the marker mark_course_completed()
    // sets exactly once, so this is the right population to replay.
    $sql = "SELECT userid, targetid AS courseid
              FROM {local_studypace}
             WHERE target = :target
               AND actualcompletion IS NOT NULL";
    $params = array('target' => 'course');
    if ($userid > 0) {
        $sql .= ' AND userid = :userid';
        $params['userid'] = (int) $userid;
    }
    $sql .= ' ORDER BY userid ASC, targetid ASC';
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $rec) {
        $uid = (int) $rec->userid;
        $courseid = (int) $rec->courseid;
        if ($uid <= 0 || $courseid <= 0) {
            continue;
        }
        try {
            // queueretry=false: the backfill is an admin-initiated bulk replay
            // (Recalcular / upgrade). It already iterates every completion, so
            // queuing per-failure adhoc retries here would only add load and
            // duplicate work — the admin can simply re-run it.
            if (local_profile_enrol_in_next_eixo($uid, $courseid, 0, false)) {
                $created++;
            }
        } catch (\Throwable $e) {
            debugging('local_profile backfill failed for user ' . $uid
                    . ' / course ' . $courseid . ': ' . $e->getMessage(),
                    DEBUG_NORMAL);
        }
    }
    $rs->close();

    return $created;
}

/**
 * Mass-enrol the user into every visible non-Eixo course in the site.
 *
 * Triggered on first profile save and on study-plan change. The earlier
 * implementation issued one is_enrolled() query per candidate course, which
 * meant a site with hundreds of courses ran hundreds of context + enrolment
 * lookups on a single profile save (visibly slow on busy sites). The new
 * approach is:
 *
 *  1. Pull the user's existing enrolments in ONE query via
 *     enrol_get_users_courses() so the diff is done in memory.
 *  2. Push the "is not an Eixo" filter into SQL via NOT IN, so the loop
 *     iterates only the candidates we actually want.
 *  3. The remaining per-course query lives inside local_profile_enrol_user
 *     (one SELECT to find the manual enrol instance), but it runs only
 *     for the courses that genuinely need enrolment — typically zero on
 *     repeat saves and a small handful even on the first.
 *
 * @param int $userid
 * @return void
 */
function local_profile_enrol_in_all_non_specialization_courses($userid) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/course/format/specialization/lib.php');
    $specializations = format_specialization::get_courses_in_format();
    $specializationids = array_keys($specializations);

    // Pre-load the user's existing enrolments once. $onlyactive=false so a
    // suspended enrolment still counts as "already enrolled" and we don't
    // create a duplicate row. Result is keyed by course id, so array_flip
    // gives us an O(1) membership set.
    $enrolledcourses = enrol_get_users_courses($userid, false /* onlyactive */);
    $enrolledids = array_flip(array_keys($enrolledcourses));

    // Build a single SELECT that already excludes the front page, hidden
    // courses and all Eixos. get_in_or_equal() refuses an empty list, so
    // skip the NOT IN clause when there are no Eixos yet (fresh install).
    $params = array('siteid' => SITEID);
    $extra = '';
    if (!empty($specializationids)) {
        list($insql, $inparams) = $DB->get_in_or_equal(
                $specializationids, SQL_PARAMS_NAMED, 'spec', false /* NOT IN */);
        $extra = "AND id {$insql} ";
        $params += $inparams;
    }
    $sql = "SELECT id FROM {course} "
            . "WHERE id <> :siteid "
            . "AND visible = 1 "
            . $extra;
    $courseids = $DB->get_fieldset_sql($sql, $params);

    foreach ($courseids as $courseid) {
        if (isset($enrolledids[$courseid])) {
            continue;
        }
        // Permissive helper: most non-Eixo courses still have the default
        // manual instance, but some sites configure them with enrol_self
        // (open enrolment with no key) — the auto helper handles both
        // paths instead of silently skipping self-only courses the way
        // the strict manual-only enrolment did.
        local_profile_auto_enrol_in_course($userid, $courseid, 'student');
    }
}

function local_profile_get_study_plans() {
    $plans = array();
    $plans[12] = 'Acelerado';
    $plans[18] = 'Progressivo';
    $plans[24] = 'Convencional';
    return $plans;
}

/**
 * Apply a study-plan selection to an existing local_profile row.
 *
 * numupdates is incremented only when nummonths actually changes so avatar-
 * only saves are not counted as plan changes (audit counter only; plans may
 * be changed as often as the student wants).
 *
 * @param stdClass $record local_profile row (mutated in place)
 * @param int $timeoption Selected plan length in months (0 = not submitted)
 * @return bool True when nummonths changed
 */
function local_profile_apply_study_plan_change(stdClass $record, int $timeoption): bool {
    if ($timeoption <= 0) {
        return false;
    }
    if ((int) $record->nummonths === $timeoption) {
        return false;
    }
    $record->nummonths = $timeoption;
    $record->numupdates = (int) $record->numupdates + 1;
    return true;
}

/**
 * Build a map from each Eixo to the next *visible* Eixo in the progression.
 *
 * Mirrors exactly the target-selection logic of
 * local_profile_enrol_in_next_eixo(): the position is resolved against the
 * FULL ordered list (visible + hidden) but the "next" target skips any hidden
 * Eixos so it lands on the first reachable one. An Eixo that has no visible
 * successor (the last one) is simply absent from the map.
 *
 * @return array [array<int,\stdClass> $nextmap keyed by completed course id,
 *                array<int,\stdClass> $eixos keyed by course id]
 */
function local_profile_get_next_visible_eixo_map() {
    global $CFG;
    require_once($CFG->dirroot . '/course/format/specialization/lib.php');

    $eixos = format_specialization::get_courses_in_format(false /* full ordered list */);
    $list = array_values($eixos);
    $count = count($list);
    $nextmap = array();
    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            if (!empty($list[$j]->visible)) {
                $nextmap[(int) $list[$i]->id] = $list[$j];
                break;
            }
        }
    }
    return array($nextmap, $eixos);
}

/**
 * Load every pending next-Eixo retry adhoc task, keyed by "userid:completedid".
 *
 * Lets the diagnostic distinguish a student who is genuinely stuck (no retry
 * scheduled) from one whose auto-enrolment failure was transient and is
 * already queued for an automatic bounded retry.
 *
 * @return array<string,\stdClass> Keyed by "userid:completedeixoid".
 */
function local_profile_get_pending_next_eixo_retries() {
    global $DB;

    $map = array();
    $records = $DB->get_records('task_adhoc', array(
        'classname' => '\\local_profile\\task\\enrol_next_eixo',
    ));
    foreach ($records as $rec) {
        $data = json_decode($rec->customdata);
        if (!is_object($data)) {
            continue;
        }
        $uid = (int) ($data->userid ?? 0);
        $cid = (int) ($data->completedeixoid ?? 0);
        if ($uid > 0 && $cid > 0) {
            $map[$uid . ':' . $cid] = (object) array(
                'nextruntime' => (int) $rec->nextruntime,
                'attempt' => (int) ($data->attempt ?? 0),
            );
        }
    }
    return $map;
}

/**
 * Diagnostic: find students who completed an Eixo but were NOT enrolled into
 * the next visible Eixo — i.e. the auto-progression failed for them.
 *
 * The condition is the exact inverse of a successful
 * local_profile_enrol_in_next_eixo() call: a course-level completion exists
 * (local_studypace.actualcompletion IS NOT NULL), a next visible Eixo exists,
 * and the user has NO enrolment row of any status in that next Eixo. Set-based
 * (one query per Eixo, ~N_eixos total) and streamed via recordset so it scales.
 *
 * Site admins are skipped (they are never auto-enrolled). Deleted/suspended
 * user accounts are excluded to keep the report focused on active students.
 *
 * @param int $userid Optional: restrict to a single user (0 = all).
 * @return array<string,\stdClass> Gap rows keyed by "userid:completedeixoid".
 */
function local_profile_find_progression_gaps($userid = 0) {
    global $DB;

    list($nextmap, $eixos) = local_profile_get_next_visible_eixo_map();
    if (empty($nextmap)) {
        return array();
    }
    $pending = local_profile_get_pending_next_eixo_retries();

    $gaps = array();
    foreach ($nextmap as $completedid => $nexteixo) {
        $params = array(
            'completedid' => $completedid,
            'nextid' => $nexteixo->id,
        );
        $usercond = '';
        if ($userid > 0) {
            $usercond = ' AND u.id = :uid';
            $params['uid'] = (int) $userid;
        }
        $sql = "SELECT u.*, sp.actualcompletion AS completedtime
                  FROM {local_studypace} sp
                  JOIN {user} u ON u.id = sp.userid AND u.deleted = 0 AND u.suspended = 0
                 WHERE sp.target = 'course'
                   AND sp.targetid = :completedid
                   AND sp.actualcompletion IS NOT NULL
                   {$usercond}
                   AND NOT EXISTS (
                           SELECT 1
                             FROM {user_enrolments} ue
                             JOIN {enrol} e ON e.id = ue.enrolid
                            WHERE e.courseid = :nextid
                              AND ue.userid = u.id
                       )
              ORDER BY u.lastname ASC, u.firstname ASC";
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $u) {
            if (is_siteadmin($u->id)) {
                continue;
            }
            $completedcourse = $eixos[$completedid] ?? null;
            $key = (int) $u->id . ':' . (int) $completedid;
            $user = clone $u;
            $completedtime = (int) $user->completedtime;
            unset($user->completedtime);
            $gaps[$key] = (object) array(
                'userid' => (int) $u->id,
                'user' => $user,
                'completedcourseid' => (int) $completedid,
                'completedcoursename' => $completedcourse ? $completedcourse->shortname : ('#' . $completedid),
                'completedtime' => $completedtime,
                'nexteixoid' => (int) $nexteixo->id,
                'nexteixoname' => $nexteixo->shortname,
                'pending' => $pending[$key] ?? null,
            );
        }
        $rs->close();
    }
    return $gaps;
}

/**
 * Stream the progression-gap report as a CSV download (admins only — the
 * caller page enforces the capability). Caller must exit() afterwards.
 *
 * @param int $userid Optional single-user filter.
 * @return void
 */
function local_profile_send_progression_gaps_csv($userid = 0) {
    $gaps = local_profile_find_progression_gaps($userid);

    $filename = 'eixo-progression-gaps-' . userdate(time(), '%Y%m%d-%H%M') . '.csv';

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array(
        get_string('progcheckcoluser', 'local_profile'),
        get_string('progcheckcolusername', 'local_profile'),
        get_string('progcheckcolemail', 'local_profile'),
        get_string('progcheckcolcompleted', 'local_profile'),
        get_string('progcheckcolcompletedon', 'local_profile'),
        get_string('progcheckcolnext', 'local_profile'),
        get_string('progcheckcolretry', 'local_profile'),
    ));
    foreach ($gaps as $gap) {
        $retry = '';
        if ($gap->pending) {
            $retry = userdate($gap->pending->nextruntime) . ' (#' . $gap->pending->attempt . ')';
        }
        fputcsv($out, array(
            fullname($gap->user),
            $gap->user->username,
            $gap->user->email,
            $gap->completedcoursename,
            $gap->completedtime ? userdate($gap->completedtime) : '',
            $gap->nexteixoname,
            $retry,
        ));
    }
    fclose($out);
}

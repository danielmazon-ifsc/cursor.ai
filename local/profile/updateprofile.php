<?php

/**
 * Update profile of the current user
 *
 * @package   local_profile
 */
require_once("../../config.php");
require_once($CFG->dirroot . "/user/lib.php");
require_once("./editlib.php");
require_once("./lib.php");

use local_studypace\studypace;

require_login();
require_sesskey();

$systemcontext = context_system::instance();
$personalcontext = context_user::instance($USER->id);
require_capability('moodle/user:editownprofile', $systemcontext);

// Parameters
$fullname    = optional_param('fullname', '', PARAM_TEXT);
$email       = optional_param('email', '', PARAM_EMAIL);
// PARAM_ALPHANUM stripped legitimate password characters and let weak 4-char
// passwords through. Accept the raw value and let core's password_policy
// validate it below.
$password    = optional_param('password', '', PARAM_RAW_TRIMMED);
$timeoption  = optional_param('timeoption', 0, PARAM_INT);
$avatarurl   = optional_param('avatarurl', '', PARAM_PATH);
// PARAM_TEXT is too permissive for a date - strtotime() will happily turn any
// English-sounding string into a timestamp. Constrain to YYYY-MM-DD; reject
// anything else by leaving $hiredatets = 0.
$hiredate    = optional_param('hiredate', '', PARAM_TEXT);
$hiredatets  = 0;
if ($hiredate !== '') {
    $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $hiredate);
    if ($parsed && $parsed->format('Y-m-d') === $hiredate) {
        $hiredatets = $parsed->getTimestamp();
    } else {
        header('HTTP/1.1 422 Unprocessable Entity');
        print 'Invalid hire date format (expected YYYY-MM-DD)';
        exit;
    }
}

if ($password !== '') {
    $errmsg = '';
    if (!check_password_policy($password, $errmsg, $USER)) {
        // Surface the rejection so the AJAX caller can show a sensible message
        // instead of silently truncating to alnum-only as the old code did.
        header('HTTP/1.1 422 Unprocessable Entity');
        print strip_tags($errmsg);
        exit;
    }
}

// Restrict the avatar URL to the bundled gallery. Even though PARAM_PATH blocks
// traversal, it still accepts any subpath under dirroot (e.g. lang files), so
// we pin it to /local/profile/pix/avatars/.
if ($avatarurl !== '') {
    $avatarbase = '/local/profile/pix/avatars/';
    if (strpos($avatarurl, $avatarbase) !== 0
        || strpos($avatarurl, '..') !== false
        || !preg_match('#^/local/profile/pix/avatars/[A-Za-z0-9_\-]+\.(png|jpe?g)$#i', $avatarurl)
    ) {
        $avatarurl = '';
    }
}

$haschange = ($fullname !== '') || ($email !== '') || ($password !== '')
        || ($avatarurl !== '') || ($timeoption > 0) || ($hiredate !== '');
if (!$haschange) {
    header('HTTP/1.1 400 Bad Request');
    print get_string('nothingtoupdate', 'local_profile');
    exit;
}

$existing = $DB->get_record('local_profile', array('userid' => $USER->id));

$transaction = $DB->start_delegated_transaction();

// Clone $USER so mutations below don't poison the live in-session object before
// the DB write succeeds. In particular, $USER->password briefly holding the
// plaintext (until user_update_user hashes it) would leak into any downstream
// code in this request.
$usernew = clone $USER;
$updateuser = false;
if ($fullname !== '') {
    $nameparts = preg_split('/\s+/', trim($fullname));
    $usernew->firstname = array_shift($nameparts);
    $usernew->lastname  = implode(' ', $nameparts);
    $updateuser = true;
}
if ($email !== '') {
    $usernew->email = $email;
    $updateuser = true;
}
if ($password !== '') {
    $usernew->password = $password;
    $updateuser = true;
}
if ($updateuser) {
    user_update_user($usernew, $password !== '', true);
}

// Update user's avatar
if ($avatarurl !== '') {
    local_profile_set_avatar_by_url($avatarurl, $USER->id);
}

// Register profile update. We use $existing so the insert path and the
// duplicate-key recovery path share the same read. If two concurrent first
// saves race, the unique index on userid will reject the second insert; we
// catch and fall back to the update path.
$firstrun = !$existing;
$oldnummonths = $existing ? (int) $existing->nummonths : 0;
if ($existing) {
    $profilechanged = false;
    if ($hiredatets > 0) {
        $existing->hiredate = $hiredatets;
        $profilechanged = true;
    }
    if (local_profile_apply_study_plan_change($existing, (int) $timeoption)) {
        $profilechanged = true;
    }
    if ($profilechanged) {
        $existing->updatetime = time();
        $DB->update_record('local_profile', $existing);
    }
} else {
    $firstupdate = new stdClass();
    $firstupdate->userid     = $USER->id;
    $firstupdate->updatetime = time();
    $firstupdate->nummonths  = $timeoption;
    $firstupdate->numupdates = 0;
    $firstupdate->hiredate   = $hiredatets;
    try {
        $DB->insert_record('local_profile', $firstupdate);
    } catch (\dml_write_exception $e) {
        // Double-click race: another concurrent first save inserted between
        // our read and write. Re-read and treat as an update.
        $existing = $DB->get_record('local_profile', array('userid' => $USER->id));
        if (!$existing) {
            throw $e;
        }
        $firstrun = false;
        $oldnummonths = (int) $existing->nummonths;
        $profilechanged = false;
        if ($hiredatets > 0) {
            $existing->hiredate = $hiredatets;
            $profilechanged = true;
        }
        if (local_profile_apply_study_plan_change($existing, (int) $timeoption)) {
            $profilechanged = true;
        }
        if ($profilechanged) {
            $existing->updatetime = time();
            $DB->update_record('local_profile', $existing);
        }
    }
}

$transaction->allow_commit();

// Mass enrolment + studypace recalc are expensive: only run them when the
// study plan actually changed (or on the very first save). Saving just the
// avatar shouldn't iterate every course in the site.
$planchanged = $firstrun || ($timeoption > 0 && $oldnummonths !== (int) $timeoption);
if ($planchanged) {
    // Enrol first; studypace recalculation must still run even if enrolment
    // into non-Eixo courses fails, otherwise the dashboard keeps the old
    // estimatedcompletion / expected-progress forecast after a plan change.
    try {
        local_profile_enrol_in_all_non_specialization_courses($USER->id);
    } catch (\Throwable $e) {
        debugging('local_profile: enrol after plan change failed for user '
            . $USER->id . ': ' . $e->getMessage(), DEBUG_NORMAL);
    }
    try {
        studypace::update_all_user_enrolments($USER->id);
    } catch (\Throwable $e) {
        debugging('local_profile: studypace recalc after plan change failed for user '
            . $USER->id . ': ' . $e->getMessage(), DEBUG_NORMAL);
    }
}

// On the very first profile save we also enrol the user into the first
// Eixo of the progression. From there, the
// \local_studypace\event\course_completed observer in local_profile takes
// over: each time the user finishes an Eixo, they are automatically
// enrolled in the next one. Site admins are skipped inside the helper.
if ($firstrun) {
    local_profile_enrol_in_first_eixo($USER->id);
}

// Refresh in-memory $USER so the caller doesn't keep stale firstname/lastname.
if ($updateuser) {
    $fresh = get_complete_user_data('id', $USER->id);
    if ($fresh) {
        foreach ((array) $fresh as $variable => $value) {
            $USER->$variable = $value;
        }
    }
}

$returnto = optional_param('returnto', '', PARAM_LOCALURL);
$redirect = '';
if ($returnto !== '' && validate_local_url($returnto)) {
    $redirect = $returnto;
} else {
    $redirect = (new moodle_url('/local/profile/edit.php', ['id' => $USER->id]))->out(false);
}
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'redirect' => $redirect]);

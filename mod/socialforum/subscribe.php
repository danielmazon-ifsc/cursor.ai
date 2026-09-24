<?php

/**
 * Subscribe to or unsubscribe from a forum or manage forum subscription mode
 *
 * This script can be used by either individual users to subscribe to or
 * unsubscribe from a forum (no 'mode' param provided), or by forum managers
 * to control the subscription mode (by 'mode' param).
 * This script can be called from a link in email so the sesskey is not
 * required parameter. However, if sesskey is missing, the user has to go
 * through a confirmation page that redirects the user back with the
 * sesskey.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/mod/socialforum/lib.php');

$id = required_param('id', PARAM_INT);             // The forum to set subscription on.
$mode = optional_param('mode', null, PARAM_INT);     // The forum's subscription mode.
$user = optional_param('user', 0, PARAM_INT);        // The userid of the user to subscribe, defaults to $USER.
$discussionid = optional_param('d', null, PARAM_INT);        // The discussionid to subscribe.
$sesskey = optional_param('sesskey', null, PARAM_RAW);
$returnurl = optional_param('returnurl', null, PARAM_RAW);

$url = new moodle_url('/mod/socialforum/subscribe.php', array('id' => $id));
if (!is_null($mode)) {
    $url->param('mode', $mode);
}
if ($user !== 0) {
    $url->param('user', $user);
}
if (!is_null($sesskey)) {
    $url->param('sesskey', $sesskey);
}
if (!is_null($discussionid)) {
    $url->param('d', $discussionid);
    if (!$discussion = $DB->get_record('socialforum_discussions', array('id' => $discussionid, 'socialforum' => $id))) {
        print_error('invaliddiscussionid', 'socialforum');
    }
}
$PAGE->set_url($url);

$socialforum = $DB->get_record('socialforum', array('id' => $id), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $socialforum->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);

if ($user) {
    require_sesskey();
    if (!has_capability('mod/socialforum:managesubscriptions', $context)) {
        print_error('nopermissiontosubscribe', 'socialforum');
    }
    $user = $DB->get_record('user', array('id' => $user), '*', MUST_EXIST);
} else {
    $user = $USER;
}

if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
    $groupmode = $cm->groupmode;
} else {
    $groupmode = $course->groupmode;
}

$issubscribed = \mod_socialforum\subscriptions::is_subscribed($user->id, $socialforum, $discussionid, $cm);

// For a user to subscribe when a groupmode is set, they must have access to at least one group.
if ($groupmode && !$issubscribed && !has_capability('moodle/site:accessallgroups', $context)) {
    if (!groups_get_all_groups($course->id, $USER->id)) {
        print_error('cannotsubscribe', 'socialforum');
    }
}

require_login($course, false, $cm);

if (is_null($mode) and ! is_enrolled($context, $USER, '', true)) {   // Guests and visitors can't subscribe - only enrolled
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    if (isguestuser()) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(get_string('subscribeenrolledonly', 'mod_socialforum') . '<br /><br />' . get_string('liketologin'), get_login_url(), new moodle_url('/mod/socialforum/view.php', array('f' => $id)));
        echo $OUTPUT->footer();
        exit;
    } else {
        // There should not be any links leading to this place, just redirect.
        redirect(
                new moodle_url('/mod/socialforum/view.php', array('f' => $id)), get_string('subscribeenrolledonly', 'mod_socialforum'), null, \core\output\notification::NOTIFY_ERROR
        );
    }
}

$returnto = optional_param('backtoindex', 0, PARAM_INT) ? "index.php?id=" . $course->id : "view.php?f=$id";

if ($returnurl) {
    $returnto = $returnurl;
}

if (!is_null($mode) and has_capability('mod/socialforum:managesubscriptions', $context)) {
    require_sesskey();
    switch ($mode) {
        case SOCIALFORUM_CHOOSESUBSCRIBE : // 0
            \mod_socialforum\subscriptions::set_subscription_mode($socialforum->id, SOCIALFORUM_CHOOSESUBSCRIBE);
            redirect(
                    $returnto, get_string('everyonecannowchoose', 'mod_socialforum'), null, \core\output\notification::NOTIFY_SUCCESS
            );
            break;
        case SOCIALFORUM_FORCESUBSCRIBE : // 1
            \mod_socialforum\subscriptions::set_subscription_mode($socialforum->id, SOCIALFORUM_FORCESUBSCRIBE);
            redirect(
                    $returnto, get_string('everyoneisnowsubscribed', 'mod_socialforum'), null, \core\output\notification::NOTIFY_SUCCESS
            );
            break;
        case SOCIALFORUM_INITIALSUBSCRIBE : // 2
            if ($socialforum->forcesubscribe <> SOCIALFORUM_INITIALSUBSCRIBE) {
                $users = \mod_socialforum\subscriptions::get_potential_subscribers($context, 0, 'u.id, u.email', '');
                foreach ($users as $user) {
                    \mod_socialforum\subscriptions::subscribe_user($user->id, $socialforum, $context);
                }
            }
            \mod_socialforum\subscriptions::set_subscription_mode($socialforum->id, SOCIALFORUM_INITIALSUBSCRIBE);
            redirect(
                    $returnto, get_string('everyoneisnowsubscribed', 'mod_socialforum'), null, \core\output\notification::NOTIFY_SUCCESS
            );
            break;
        case SOCIALFORUM_DISALLOWSUBSCRIBE : // 3
            \mod_socialforum\subscriptions::set_subscription_mode($socialforum->id, SOCIALFORUM_DISALLOWSUBSCRIBE);
            redirect(
                    $returnto, get_string('noonecansubscribenow', 'mod_socialforum'), null, \core\output\notification::NOTIFY_SUCCESS
            );
            break;
        default:
            print_error(get_string('invalidforcesubscribe', 'mod_socialforum'));
    }
}

if (\mod_socialforum\subscriptions::is_forcesubscribed($socialforum)) {
    redirect(
            $returnto, get_string('everyoneisnowsubscribed', 'mod_socialforum'), null, \core\output\notification::NOTIFY_SUCCESS
    );
}

$info = new stdClass();
$info->name = fullname($user);
$info->socialforum = format_string($socialforum->name);

if ($issubscribed) {
    if (is_null($sesskey)) {
        // We came here via link in email.
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        $viewurl = new moodle_url('/mod/socialforum/view.php', array('f' => $id));
        if ($discussionid) {
            $a = new stdClass();
            $a->socialforum = format_string($socialforum->name);
            $a->discussion = format_string($discussion->name);
            echo $OUTPUT->confirm(get_string('confirmunsubscribediscussion', 'socialforum', $a), $PAGE->url, $viewurl);
        } else {
            echo $OUTPUT->confirm(get_string('confirmunsubscribe', 'socialforum', format_string($socialforum->name)), $PAGE->url, $viewurl);
        }
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    if ($discussionid === null) {
        if (\mod_socialforum\subscriptions::unsubscribe_user($user->id, $socialforum, $context, true)) {
            redirect(
                    $returnto, get_string('nownotsubscribed', 'socialforum', $info), null, \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            print_error('cannotunsubscribe', 'socialforum', get_local_referer(false));
        }
    } else {
        if (\mod_socialforum\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion, $context)) {
            $info->discussion = $discussion->name;
            redirect(
                    $returnto, get_string('discussionnownotsubscribed', 'socialforum', $info), null, \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            print_error('cannotunsubscribe', 'socialforum', get_local_referer(false));
        }
    }
} else {  // subscribe
    if (\mod_socialforum\subscriptions::subscription_disabled($socialforum) && !has_capability('mod/socialforum:managesubscriptions', $context)) {
        print_error('disallowsubscribe', 'socialforum', get_local_referer(false));
    }
    if (!has_capability('mod/socialforum:viewdiscussion', $context)) {
        print_error('noviewdiscussionspermission', 'socialforum', get_local_referer(false));
    }
    if (is_null($sesskey)) {
        // We came here via link in email.
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        $viewurl = new moodle_url('/mod/socialforum/view.php', array('f' => $id));
        if ($discussionid) {
            $a = new stdClass();
            $a->socialforum = format_string($socialforum->name);
            $a->discussion = format_string($discussion->name);
            echo $OUTPUT->confirm(get_string('confirmsubscribediscussion', 'socialforum', $a), $PAGE->url, $viewurl);
        } else {
            echo $OUTPUT->confirm(get_string('confirmsubscribe', 'socialforum', format_string($socialforum->name)), $PAGE->url, $viewurl);
        }
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    if ($discussionid == null) {
        \mod_socialforum\subscriptions::subscribe_user($user->id, $socialforum, $context, true);
        redirect(
                $returnto, get_string('nowsubscribed', 'socialforum', $info), null, \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        $info->discussion = $discussion->name;
        \mod_socialforum\subscriptions::subscribe_user_to_discussion($user->id, $discussion, $context);
        redirect(
                $returnto, get_string('discussionnowsubscribed', 'socialforum', $info), null, \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

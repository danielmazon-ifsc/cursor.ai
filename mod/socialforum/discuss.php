<?php

/**
 * Displays a post, and all the posts below it.
 * If no post is given, displays all posts in a discussion
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require_once('../../config.php');

$d = required_param('d', PARAM_INT);                // Discussion ID
$parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
$mode = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
$move = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another socialforum
$mark = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
$postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.
$pin = optional_param('pin', -1, PARAM_INT);          // If set, pin or unpin this discussion.

$url = new moodle_url('/mod/socialforum/discuss.php', array('d' => $d));
if ($parent !== 0) {
    $url->param('parent', $parent);
}
$PAGE->set_url($url);

$discussion = $DB->get_record('socialforum_discussions', array('id' => $d), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
$socialforum = $DB->get_record('socialforum', array('id' => $discussion->socialforum), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $course->id, false, MUST_EXIST);

require_course_login($course, true, $cm);

// move this down fix for MDL-6926
require_once($CFG->dirroot . '/mod/socialforum/lib.php');

$modcontext = context_module::instance($cm->id);
require_capability('mod/socialforum:viewdiscussion', $modcontext, NULL, true, 'noviewdiscussionspermission', 'socialforum');

if (!empty($CFG->enablerssfeeds) && !empty($CFG->socialforum_enablerssfeeds) && $socialforum->rsstype && $socialforum->rssarticles) {
    require_once("$CFG->libdir/rsslib.php");

    $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($socialforum->name);
    rss_add_http_header($modcontext, 'mod_socialforum', $socialforum, $rsstitle);
}

// Move discussion if requested.
if ($move > 0 and confirm_sesskey()) {
    $return = $CFG->wwwroot . '/mod/socialforum/discuss.php?d=' . $discussion->id;

    if (!$socialforumto = $DB->get_record('socialforum', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'socialforum', $return);
    }

    require_capability('mod/socialforum:movediscussions', $modcontext);

    if ($socialforum->type == 'single') {
        print_error('cannotmovefromsinglesocialforum', 'socialforum', $return);
    }

    if (!$socialforumto = $DB->get_record('socialforum', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'socialforum', $return);
    }

    if ($socialforumto->type == 'single') {
        print_error('cannotmovetosinglesocialforum', 'socialforum', $return);
    }

    // Get target socialforum cm and check it is visible to current user.
    $modinfo = get_fast_modinfo($course);
    $socialforums = $modinfo->get_instances_of('socialforum');
    if (!array_key_exists($socialforumto->id, $socialforums)) {
        print_error('cannotmovetonotfound', 'socialforum', $return);
    }
    $cmto = $socialforums[$socialforumto->id];
    if (!$cmto->uservisible) {
        print_error('cannotmovenotvisible', 'socialforum', $return);
    }

    $destinationctx = context_module::instance($cmto->id);
    require_capability('mod/socialforum:startdiscussion', $destinationctx);

    if (!socialforum_move_attachments($discussion, $socialforum->id, $socialforumto->id)) {
        echo $OUTPUT->notification("Errors occurred while moving attachment directories - check your file permissions");
    }
    // For each subscribed user in this socialforum and discussion, copy over per-discussion subscriptions if required.
    $discussiongroup = $discussion->groupid == -1 ? 0 : $discussion->groupid;
    $potentialsubscribers = \mod_socialforum\subscriptions::fetch_subscribed_users(
                    $socialforum, $discussiongroup, $modcontext, 'u.id', true
    );

    // Pre-seed the subscribed_discussion caches.
    // Firstly for the socialforum being moved to.
    \mod_socialforum\subscriptions::fill_subscription_cache($socialforumto->id);
    // And also for the discussion being moved.
    \mod_socialforum\subscriptions::fill_subscription_cache($socialforum->id);
    $subscriptionchanges = array();
    $subscriptiontime = time();
    foreach ($potentialsubscribers as $subuser) {
        $userid = $subuser->id;
        $targetsubscription = \mod_socialforum\subscriptions::is_subscribed($userid, $socialforumto, null, $cmto);
        $discussionsubscribed = \mod_socialforum\subscriptions::is_subscribed($userid, $socialforum, $discussion->id);
        $socialforumsubscribed = \mod_socialforum\subscriptions::is_subscribed($userid, $socialforum);

        if ($socialforumsubscribed && !$discussionsubscribed && $targetsubscription) {
            // The user has opted out of this discussion and the move would cause them to receive notifications again.
            // Ensure they are unsubscribed from the discussion still.
            $subscriptionchanges[$userid] = \mod_socialforum\subscriptions::SOCIALFORUM_DISCUSSION_UNSUBSCRIBED;
        } else if (!$socialforumsubscribed && $discussionsubscribed && !$targetsubscription) {
            // The user has opted into this discussion and would otherwise not receive the subscription after the move.
            // Ensure they are subscribed to the discussion still.
            $subscriptionchanges[$userid] = $subscriptiontime;
        }
    }

    $DB->set_field('socialforum_discussions', 'socialforum', $socialforumto->id, array('id' => $discussion->id));
    $DB->set_field('socialforum_read', 'socialforumid', $socialforumto->id, array('discussionid' => $discussion->id));

    // Delete the existing per-discussion subscriptions and replace them with the newly calculated ones.
    $DB->delete_records('socialforum_discussion_subs', array('discussion' => $discussion->id));
    $newdiscussion = clone $discussion;
    $newdiscussion->socialforum = $socialforumto->id;
    foreach ($subscriptionchanges as $userid => $preference) {
        if ($preference != \mod_socialforum\subscriptions::SOCIALFORUM_DISCUSSION_UNSUBSCRIBED) {
            // Users must have viewdiscussion to a discussion.
            if (has_capability('mod/socialforum:viewdiscussion', $destinationctx, $userid)) {
                \mod_socialforum\subscriptions::subscribe_user_to_discussion($userid, $newdiscussion, $destinationctx);
            }
        } else {
            \mod_socialforum\subscriptions::unsubscribe_user_from_discussion($userid, $newdiscussion, $destinationctx);
        }
    }

    $params = array(
        'context' => $destinationctx,
        'objectid' => $discussion->id,
        'other' => array(
            'fromsocialforumid' => $socialforum->id,
            'tosocialforumid' => $socialforumto->id,
        )
    );
    $event = \mod_socialforum\event\discussion_moved::create($params);
    $event->add_record_snapshot('socialforum_discussions', $discussion);
    $event->add_record_snapshot('socialforum', $socialforum);
    $event->add_record_snapshot('socialforum', $socialforumto);
    $event->trigger();

    // Delete the RSS files for the 2 socialforums to force regeneration of the feeds
    require_once($CFG->dirroot . '/mod/socialforum/rsslib.php');
    socialforum_rss_delete_file($socialforum);
    socialforum_rss_delete_file($socialforumto);

    redirect($return . '&move=-1&sesskey=' . sesskey());
}
// Pin or unpin discussion if requested.
if ($pin !== -1 && confirm_sesskey()) {
    require_capability('mod/socialforum:pindiscussions', $modcontext);

    $params = array('context' => $modcontext, 'objectid' => $discussion->id, 'other' => array('socialforumid' => $socialforum->id));

    switch ($pin) {
        case SOCIALFORUM_DISCUSSION_PINNED:
            // Pin the discussion and trigger discussion pinned event.
            socialforum_discussion_pin($modcontext, $socialforum, $discussion);
            break;
        case SOCIALFORUM_DISCUSSION_UNPINNED:
            // Unpin the discussion and trigger discussion unpinned event.
            socialforum_discussion_unpin($modcontext, $socialforum, $discussion);
            break;
        default:
            echo $OUTPUT->notification("Invalid value when attempting to pin/unpin discussion");
            break;
    }

    redirect(new moodle_url('/mod/socialforum/discuss.php', array('d' => $discussion->id)));
}

// Trigger discussion viewed event.
socialforum_discussion_view($modcontext, $socialforum, $discussion);

unset($SESSION->fromdiscussion);

if ($mode) {
    set_user_preference('socialforum_displaymode', $mode);
}

$displaymode = get_user_preferences('socialforum_displaymode', $CFG->socialforum_displaymode);

if ($parent) {
    $displaymode = SOCIALFORUM_MODE_NESTED;
} else {
    $parent = $discussion->firstpost;
}

if (!$post = socialforum_get_post_full($parent)) {
    print_error("notexists", 'socialforum', "$CFG->wwwroot/mod/socialforum/view.php?f=$socialforum->id");
}

if (!socialforum_user_can_see_post($socialforum, $discussion, $post, null, $cm)) {
    print_error('noviewdiscussionspermission', 'socialforum', "$CFG->wwwroot/mod/socialforum/view.php?id=$socialforum->id");
}

if ($mark == 'read' or $mark == 'unread') {
    if ($CFG->socialforum_usermarksread && socialforum_tp_can_track_socialforums($socialforum) && socialforum_tp_is_tracked($socialforum)) {
        if ($mark == 'read') {
            socialforum_tp_add_read_record($USER->id, $postid);
        } else {
            // unread
            socialforum_tp_delete_read_records($USER->id, $postid);
        }
    }
}

$socialforumnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
if (empty($socialforumnode)) {
    $socialforumnode = $PAGE->navbar;
} else {
    $socialforumnode->make_active();
}
$node = $socialforumnode->add(format_string($discussion->name), new moodle_url('/mod/socialforum/discuss.php', array('d' => $discussion->id)));
$node->display = false;
if ($node && $post->id != $discussion->firstpost) {
    $node->add(format_string($post->subject), $PAGE->url);
}

$PAGE->set_title("$course->shortname: " . format_string($discussion->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('noheader');
$renderer = $PAGE->get_renderer('mod_socialforum');

echo $OUTPUT->header();

echo socialforum_print_banner($cm);

echo html_writer::start_div('narrow-page discussion container', array(
    'style' => 'padding-bottom: 7rem !important;'
));

echo html_writer::start_div('', array(
    'id' => 'discussionlink',
    'style' => 'padding-top: 2rem !important; padding-bottom: 2rem;'
));
echo html_writer::start_div('container page__container d-flex flex-column flex-md-row align-items-center text-center text-sm-left');
echo html_writer::start_div('flex d-flex flex-column flex-sm-row align-items-center');
echo html_writer::start_div('mb-24pt mb-sm-0 mr-sm-24pt');
$discussionurl = new moodle_url('/mod/socialforum/discuss.php', array(
    'd' => $d
        ));
echo html_writer::start_tag('a', array(
    'href' => $discussionurl
));
echo html_writer::tag('h2', get_string('topic', 'mod_socialforum') . ': ' . $discussion->name, array('class' => 'mb-0'));
echo html_writer::end_tag('a');
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('row');

echo html_writer::start_div('posts col-lg-8');
echo $OUTPUT->heading(format_string($discussion->name), 3, 'discussionname');

/// Check to see if groups are being used in this socialforum
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly set $canreply for performance reasons

$canreply = socialforum_user_can_post($socialforum, $discussion, $USER, $cm, $course, $modcontext);
if (!$canreply and $socialforum->type !== 'news') {
    if (isguestuser() or ! isloggedin()) {
        $canreply = true;
    }
    if (!is_enrolled($modcontext) and ! is_viewing($modcontext)) {
        // allow guests and not-logged-in to see the link - they are prompted to log in after clicking the link
        // normal users with temporary guest access see this link too, they are asked to enrol instead
        $canreply = enrol_selfenrol_available($course->id);
    }
}

if (!empty($socialforum->blockafter) && !empty($socialforum->blockperiod)) {
    $a = new stdClass();
    $a->blockafter = $socialforum->blockafter;
    $a->blockperiod = get_string('secondstotime' . $socialforum->blockperiod);
    echo $OUTPUT->notification(get_string('thissocialforumisthrottled', 'socialforum', $a));
}

if ($move == -1 and confirm_sesskey()) {
    echo $OUTPUT->notification(get_string('discussionmoved', 'socialforum', format_string($socialforum->name, true)), 'notifysuccess');
}

$canrate = has_capability('mod/socialforum:rate', $modcontext);
socialforum_print_discussion($course, $cm, $socialforum, $discussion, $post, $displaymode, $canreply, $canrate);
echo html_writer::end_div();

echo html_writer::start_div('associated-video col-lg-4');
socialforum_print_associated_video($socialforum, $discussion);
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();

// Add the subscription toggle JS.
$PAGE->requires->yui_module('moodle-mod_socialforum-subscriptiontoggle', 'Y.M.mod_socialforum.subscriptiontoggle.init');

echo $OUTPUT->footer();

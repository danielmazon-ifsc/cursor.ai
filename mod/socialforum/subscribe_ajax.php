<?php

/**
 * Subscribe to or unsubscribe from a forum discussion.
 *
 * @package    mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
define('AJAX_SCRIPT', true);
require_once(dirname(dirname(__DIR__)) . '/config.php');
require_once($CFG->dirroot . '/mod/socialforum/lib.php');

$socialforumid = required_param('socialforumid', PARAM_INT);             // The forum to subscribe or unsubscribe.
$discussionid = optional_param('discussionid', null, PARAM_INT);  // The discussionid to subscribe.
$includetext = optional_param('includetext', false, PARAM_BOOL);

$socialforum = $DB->get_record('socialforum', array('id' => $socialforumid), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $socialforum->course), '*', MUST_EXIST);
if (!$discussion = $DB->get_record('socialforum_discussions', array('id' => $discussionid, 'socialforum' => $socialforumid))) {
    print_error('invaliddiscussionid', 'socialforum');
}
$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);

require_sesskey();
require_login($course, false, $cm);
require_capability('mod/socialforum:viewdiscussion', $context);

$return = new stdClass();

if (is_guest($context, $USER)) {
    // is_guest should be used here as this also checks whether the user is a guest in the current course.
    // Guests and visitors cannot subscribe - only enrolled users.
    throw new moodle_exception('noguestsubscribe', 'mod_socialforum');
}

if (!\mod_socialforum\subscriptions::is_subscribable($socialforum)) {
    // Nothing to do. We won't actually output any content here though.
    echo json_encode($return);
    die;
}

if (\mod_socialforum\subscriptions::is_subscribed($USER->id, $socialforum, $discussion->id, $cm)) {
    // The user is subscribed, unsubscribe them.
    \mod_socialforum\subscriptions::unsubscribe_user_from_discussion($USER->id, $discussion, $context);
} else {
    // The user is unsubscribed, subscribe them.
    \mod_socialforum\subscriptions::subscribe_user_to_discussion($USER->id, $discussion, $context);
}

// Now return the updated subscription icon.
$return->icon = SOCIALFORUM_get_discussion_subscription_icon($socialforum, $discussion->id, null, $includetext);
echo json_encode($return);
die;

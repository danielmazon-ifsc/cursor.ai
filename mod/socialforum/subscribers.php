<?php

/**
 * This file is used to display and organise forum subscribers
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require_once("../../config.php");
require_once($CFG->dirroot . '/mod/socialforum/lib.php');

$id = required_param('id', PARAM_INT);           // forum
$group = optional_param('group', 0, PARAM_INT);      // change of group
$edit = optional_param('edit', -1, PARAM_BOOL);     // Turn editing on and off

$url = new moodle_url('/mod/socialforum/subscribers.php', array('id' => $id));
if ($group !== 0) {
    $url->param('group', $group);
}
if ($edit !== 0) {
    $url->param('edit', $edit);
}
$PAGE->set_url($url);

$socialforum = $DB->get_record('socialforum', array('id' => $id), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $socialforum->course), '*', MUST_EXIST);
if (!$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $course->id)) {
    $cm->id = 0;
}

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
if (!has_capability('mod/socialforum:viewsubscribers', $context)) {
    print_error('nopermissiontosubscribe', 'socialforum');
}

unset($SESSION->fromdiscussion);

$params = array(
    'context' => $context,
    'other' => array('socialforumid' => $socialforum->id),
);
$event = \mod_socialforum\event\subscribers_viewed::create($params);
$event->trigger();

$socialforumoutput = $PAGE->get_renderer('mod_socialforum');
$currentgroup = groups_get_activity_group($cm);
$options = array('socialforumid' => $socialforum->id, 'currentgroup' => $currentgroup, 'context' => $context);
$existingselector = new mod_socialforum_existing_subscriber_selector('existingsubscribers', $options);
$subscriberselector = new mod_socialforum_potential_subscriber_selector('potentialsubscribers', $options);
$subscriberselector->set_existing_subscribers($existingselector->find_users(''));

if (data_submitted()) {
    require_sesskey();
    $subscribe = (bool) optional_param('subscribe', false, PARAM_RAW);
    $unsubscribe = (bool) optional_param('unsubscribe', false, PARAM_RAW);
    /** It has to be one or the other, not both or neither */
    if (!($subscribe xor $unsubscribe)) {
        print_error('invalidaction');
    }
    if ($subscribe) {
        $users = $subscriberselector->get_selected_users();
        foreach ($users as $user) {
            if (!\mod_socialforum\subscriptions::subscribe_user($user->id, $socialforum)) {
                print_error('cannotaddsubscriber', 'socialforum', '', $user->id);
            }
        }
    } else if ($unsubscribe) {
        $users = $existingselector->get_selected_users();
        foreach ($users as $user) {
            if (!\mod_socialforum\subscriptions::unsubscribe_user($user->id, $socialforum)) {
                print_error('cannotremovesubscriber', 'socialforum', '', $user->id);
            }
        }
    }
    $subscriberselector->invalidate_selected_users();
    $existingselector->invalidate_selected_users();
    $subscriberselector->set_existing_subscribers($existingselector->find_users(''));
}

$strsubscribers = get_string("subscribers", "mod_socialforum");
$PAGE->navbar->add($strsubscribers);
$PAGE->set_title($strsubscribers);
$PAGE->set_heading($COURSE->fullname);
if (has_capability('mod/socialforum:managesubscriptions', $context) && \mod_socialforum\subscriptions::is_forcesubscribed($socialforum) === false) {
    if ($edit != -1) {
        $USER->subscriptionsediting = $edit;
    }
    $PAGE->set_button(socialforum_update_subscriptions_button($course->id, $id));
} else {
    unset($USER->subscriptionsediting);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('socialforum', 'mod_socialforum') . ' ' . $strsubscribers);
if (empty($USER->subscriptionsediting)) {
    $subscribers = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum, $currentgroup, $context);
    if (\mod_socialforum\subscriptions::is_forcesubscribed($socialforum)) {
        $subscribers = mod_socialforum_filter_hidden_users($cm, $context, $subscribers);
    }
    echo $socialforumoutput->subscriber_overview($subscribers, $socialforum, $course);
} else {
    echo $socialforumoutput->subscriber_selection_form($existingselector, $subscriberselector);
}
echo $OUTPUT->footer();

/**
 * Filters a list of users for whether they can see a given activity.
 * If the course module is hidden (closed-eye icon), then only users who have
 * the permission to view hidden activities will appear in the output list.
 *
 * @todo MDL-48625 This filtering should be handled in core libraries instead.
 *
 * @param stdClass $cm the course module record of the activity.
 * @param context_module $context the activity context, to save re-fetching it.
 * @param array $users the list of users to filter.
 * @return array the filtered list of users.
 */
function mod_socialforum_filter_hidden_users(stdClass $cm, context_module $context, array $users) {
    if ($cm->visible) {
        return $users;
    } else {
        // Filter for users that can view hidden activities.
        $filteredusers = array();
        $hiddenviewers = get_users_by_capability($context, 'moodle/course:viewhiddenactivities');
        foreach ($hiddenviewers as $hiddenviewer) {
            if (array_key_exists($hiddenviewer->id, $users)) {
                $filteredusers[$hiddenviewer->id] = $users[$hiddenviewer->id];
            }
        }
        return $filteredusers;
    }
}

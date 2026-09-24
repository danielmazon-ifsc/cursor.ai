<?php

/**
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require_once("../../config.php");
require_once($CFG->dirroot . '/mod/socialforum/lib.php');

$confirm = optional_param('confirm', false, PARAM_BOOL);

$PAGE->set_url('/mod/socialforum/unsubscribeall.php');

// Do not autologin guest. Only proper users can have forum subscriptions.
require_login(null, false);
$PAGE->set_context(context_user::instance($USER->id));

$return = $CFG->wwwroot . '/';

if (isguestuser()) {
    redirect($return);
}

$strunsubscribeall = get_string('unsubscribeall', 'mod_socialforum');
$PAGE->navbar->add(get_string('modulename', 'mod_socialforum'));
$PAGE->navbar->add($strunsubscribeall);
$PAGE->set_title($strunsubscribeall);
$PAGE->set_heading($COURSE->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($strunsubscribeall);

if (data_submitted() and $confirm and confirm_sesskey()) {
    $socialforums = \mod_socialforum\subscriptions::get_unsubscribable_forums();

    foreach ($socialforums as $socialforum) {
        \mod_socialforum\subscriptions::unsubscribe_user($USER->id, $socialforum, context_module::instance($socialforum->cm), true);
    }
    $DB->delete_records('socialforum_discussion_subs', array('userid' => $USER->id));
    $DB->set_field('user', 'autosubscribe', 0, array('id' => $USER->id));

    echo $OUTPUT->box(get_string('unsubscribealldone', 'mod_socialforum'));
    echo $OUTPUT->continue_button($return);
    echo $OUTPUT->footer();
    die;
} else {
    $count = new stdClass();
    $count->socialforums = count(\mod_socialforum\subscriptions::get_unsubscribable_forums());
    $count->discussions = $DB->count_records('socialforum_discussion_subs', array('userid' => $USER->id));

    if ($count->socialforums || $count->discussions) {
        if ($count->socialforums && $count->discussions) {
            $msg = get_string('unsubscribeallconfirm', 'socialforum', $count);
        } else if ($count->socialforums) {
            $msg = get_string('unsubscribeallconfirmforums', 'socialforum', $count);
        } else if ($count->discussions) {
            $msg = get_string('unsubscribeallconfirmdiscussions', 'socialforum', $count);
        }
        echo $OUTPUT->confirm($msg, new moodle_url('unsubscribeall.php', array('confirm' => 1)), $return);
        echo $OUTPUT->footer();
        die;
    } else {
        echo $OUTPUT->box(get_string('unsubscribeallempty', 'mod_socialforum'));
        echo $OUTPUT->continue_button($return);
        echo $OUTPUT->footer();
        die;
    }
}

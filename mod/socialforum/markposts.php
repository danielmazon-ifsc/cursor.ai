<?php

/**
 * Set tracking option for the forum.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require_once("../../config.php");
require_once($CFG->dirroot . '/mod/socialforum/lib.php');

$f = required_param('f', PARAM_INT); // The forum to mark
$mark = required_param('mark', PARAM_ALPHA); // Read or unread?
$d = optional_param('d', 0, PARAM_INT); // Discussion to mark.
$returnpage = optional_param('returnpage', 'index.php', PARAM_FILE);    // Page to return to.

$url = new moodle_url('/mod/socialforum/markposts.php', array('f' => $f, 'mark' => $mark));
if ($d !== 0) {
    $url->param('d', $d);
}
if ($returnpage !== 'index.php') {
    $url->param('returnpage', $returnpage);
}
$PAGE->set_url($url);

if (!$socialforum = $DB->get_record("socialforum", array("id" => $f))) {
    print_error('invalidforumid', 'socialforum');
}

if (!$course = $DB->get_record("course", array("id" => $socialforum->course))) {
    print_error('invalidcourseid');
}

if (!$cm = get_coursemodule_from_instance("socialforum", $socialforum->id, $course->id)) {
    print_error('invalidcoursemodule');
}

$user = $USER;

require_login($course, false, $cm);
require_sesskey();

if ($returnpage == 'index.php') {
    $returnto = new moodle_url("/mod/socialforum/$returnpage", array('id' => $course->id));
} else {
    $returnto = new moodle_url("/mod/socialforum/$returnpage", array('f' => $socialforum->id));
}

if (isguestuser()) {   // Guests can't change forum
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('noguesttracking', 'mod_socialforum') . '<br /><br />' . get_string('liketologin'), get_login_url(), $returnto);
    echo $OUTPUT->footer();
    exit;
}

$info = new stdClass();
$info->name = fullname($user);
$info->socialforum = format_string($socialforum->name);

if ($mark == 'read') {
    if (!empty($d)) {
        if (!$discussion = $DB->get_record('socialforum_discussions', array('id' => $d, 'socialforum' => $socialforum->id))) {
            print_error('invaliddiscussionid', 'socialforum');
        }

        SOCIALFORUM_tp_mark_discussion_read($user, $d);
    } else {
        // Mark all messages read in current group
        $currentgroup = groups_get_activity_group($cm);
        if (!$currentgroup) {
            // mark_forum_read requires ===false, while get_activity_group
            // may return 0
            $currentgroup = false;
        }
        SOCIALFORUM_tp_mark_forum_read($user, $socialforum->id, $currentgroup);
    }

/// FUTURE - Add ability to mark them as unread.
//    } else { // subscribe
//        if (socialforum_tp_start_tracking($socialforum->id, $user->id)) {
//            redirect($returnto, get_string("nowtracking", "socialforum", $info), 1);
//        } else {
//            print_error("Could not start tracking that forum", get_local_referer());
//        }
}

redirect($returnto);


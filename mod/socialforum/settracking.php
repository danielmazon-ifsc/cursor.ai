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

$id = required_param('id', PARAM_INT);                           // The forum to subscribe or unsubscribe to
$returnpage = optional_param('returnpage', 'index.php', PARAM_FILE);    // Page to return to.

require_sesskey();

if (!$socialforum = $DB->get_record("socialforum", array("id" => $id))) {
    print_error('invalidforumid', 'socialforum');
}

if (!$course = $DB->get_record("course", array("id" => $socialforum->course))) {
    print_error('invalidcoursemodule');
}

if (!$cm = get_coursemodule_from_instance("socialforum", $socialforum->id, $course->id)) {
    print_error('invalidcoursemodule');
}
require_login($course, false, $cm);
$returnpageurl = new moodle_url('/mod/socialforum/' . $returnpage, array('id' => $course->id, 'f' => $socialforum->id));
$returnto = SOCIALFORUM_go_back_to($returnpageurl);

if (!socialforum_tp_can_track_socialforums($socialforum)) {
    redirect($returnto);
}

$info = new stdClass();
$info->name = fullname($USER);
$info->socialforum = format_string($socialforum->name);

$eventparams = array(
    'context' => context_module::instance($cm->id),
    'relateduserid' => $USER->id,
    'other' => array('socialforumid' => $socialforum->id),
);

if (socialforum_tp_is_tracked($socialforum)) {
    if (socialforum_tp_stop_tracking($socialforum->id)) {
        $event = \mod_socialforum\event\readtracking_disabled::create($eventparams);
        $event->trigger();
        redirect($returnto, get_string("nownottracking", "socialforum", $info), 1);
    } else {
        print_error('cannottrack', '', get_local_referer(false));
    }
} else { // subscribe
    if (socialforum_tp_start_tracking($socialforum->id)) {
        $event = \mod_socialforum\event\readtracking_enabled::create($eventparams);
        $event->trigger();
        redirect($returnto, get_string("nowtracking", "socialforum", $info), 1);
    } else {
        print_error('cannottrack', '', get_local_referer(false));
    }
}
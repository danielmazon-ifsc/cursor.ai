<?php

/**
 * Set the mail digest option in a specific forum for a user.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require_once(dirname(dirname(__DIR__)) . '/config.php');
require_once($CFG->dirroot . '/mod/socialforum/lib.php');

$id = required_param('id', PARAM_INT);
$maildigest = required_param('maildigest', PARAM_INT);
$backtoindex = optional_param('backtoindex', 0, PARAM_INT);

// We must have a valid session key.
require_sesskey();

$socialforum = $DB->get_record('socialforum', array('id' => $id));
$course = $DB->get_record('course', array('id' => $socialforum->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);

$url = new moodle_url('/mod/socialforum/maildigest.php', array(
    'id' => $id,
    'maildigest' => $maildigest,
        ));
$PAGE->set_url($url);
$PAGE->set_context($context);

$digestoptions = SOCIALFORUM_get_user_digest_options();

$info = new stdClass();
$info->name = fullname($USER);
$info->socialforum = format_string($socialforum->name);
forum_set_user_maildigest($socialforum, $maildigest);
$info->maildigest = $maildigest;

if ($maildigest === -1) {
    // Get the default maildigest options.
    $info->maildigest = $USER->maildigest;
    $info->maildigesttitle = $digestoptions[$info->maildigest];
    $info->maildigestdescription = get_string('emaildigest_' . $info->maildigest, 'mod_socialforum', $info);
    $updatemessage = get_string('emaildigestupdated_default', 'socialforum', $info);
} else {
    $info->maildigesttitle = $digestoptions[$info->maildigest];
    $info->maildigestdescription = get_string('emaildigest_' . $info->maildigest, 'mod_socialforum', $info);
    $updatemessage = get_string('emaildigestupdated', 'socialforum', $info);
}

if ($backtoindex) {
    $returnto = "index.php?id={$course->id}";
} else {
    $returnto = "view.php?f={$id}";
}

redirect($returnto, $updatemessage, null, \core\output\notification::NOTIFY_SUCCESS);

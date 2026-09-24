<?php

/**
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/socialforum/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = optional_param('id', 0, PARAM_INT);       // Course Module ID
$f = optional_param('f', 0, PARAM_INT);        // Social Forum ID
$mode = optional_param('mode', 0, PARAM_INT);     // Display mode (for single forum)
$showall = optional_param('showall', '', PARAM_INT); // show all discussions on one page
$changegroup = optional_param('group', -1, PARAM_INT);   // choose the current group
$page = optional_param('page', 0, PARAM_INT);     // which page to show
$search = optional_param('search', '', PARAM_CLEAN); // search string

$params = array();
if ($id) {
    $params['id'] = $id;
} else {
    $params['f'] = $f;
}
if ($page) {
    $params['page'] = $page;
}
if ($search) {
    $params['search'] = $search;
}
$PAGE->set_url('/mod/socialforum/view.php', $params);

if ($id) {
    if (!$cm = get_coursemodule_from_id('socialforum', $id)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error('coursemisconf');
    }
    if (!$socialforum = $DB->get_record("socialforum", array("id" => $cm->instance))) {
        print_error('invalidforumid', 'socialforum');
    }
    if ($socialforum->type == 'single') {
        $PAGE->set_pagetype('mod-forum-discuss');
    }
    // move require_course_login here to use forced language for course
    // fix for MDL-6926
    require_course_login($course, true, $cm);
    $strforums = get_string("modulenameplural", "mod_socialforum");
    $strforum = get_string("modulename", "mod_socialforum");
} else if ($f) {

    if (!$socialforum = $DB->get_record("socialforum", array("id" => $f))) {
        print_error('invalidforumid', 'socialforum');
    }
    if (!$course = $DB->get_record("course", array("id" => $socialforum->course))) {
        print_error('coursemisconf');
    }

    if (!$cm = get_coursemodule_from_instance("socialforum", $socialforum->id, $course->id)) {
        print_error('missingparameter');
    }
    // move require_course_login here to use forced language for course
    // fix for MDL-6926
    require_course_login($course, true, $cm);
    $strforums = get_string("modulenameplural", "mod_socialforum");
    $strforum = get_string("modulename", "mod_socialforum");
} else {
    print_error('missingparameter');
}

$context = context_module::instance($cm->id);
$PAGE->set_context($context);

if (!empty($CFG->enablerssfeeds) && !empty($CFG->socialforum_enablerssfeeds) && $socialforum->rsstype && $socialforum->rssarticles) {
    require_once("$CFG->libdir/rsslib.php");

    $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($socialforum->name);
    rss_add_http_header($context, 'mod_socialforum', $socialforum, $rsstitle);
}

/// Print header.

$PAGE->set_title($socialforum->name);
$PAGE->add_body_class('socialforumtype-' . $socialforum->type);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('noheader');

/// Some capability checks.
if (empty($cm->visible) and ! has_capability('moodle/course:viewhiddenactivities', $context)) {
    notice(get_string("activityiscurrentlyhidden"));
}

if (!has_capability('mod/socialforum:viewdiscussion', $context)) {
    notice(get_string('noviewdiscussionspermission', 'mod_socialforum'));
}

// Mark viewed and trigger the course_module_viewed event.
socialforum_view($socialforum, $course, $cm, $context);

echo $OUTPUT->header();
echo html_writer::start_div('page-name');
echo $OUTPUT->heading(format_string($socialforum->name), 2, 'container');
echo html_writer::end_div();
if (!empty($socialforum->intro) && $socialforum->type != 'single' && $socialforum->type != 'teacher') {
    echo $OUTPUT->box(format_module_intro('socialforum', $socialforum, $cm->id), 'generalbox', 'intro');
}

/// find out current groups mode
groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/socialforum/view.php?id=' . $cm->id);

$SESSION->fromdiscussion = qualified_me();   // Return here if we post or set subscription etc
/// Print settings and things across the top
// If it's a simple single discussion forum, we need to print the display
// mode control.
if ($socialforum->type == 'single') {
    $discussion = NULL;
    $discussions = $DB->get_records('socialforum_discussions', array('socialforum' => $socialforum->id), 'timemodified ASC');
    if (!empty($discussions)) {
        $discussion = array_pop($discussions);
    }
    if ($discussion) {
        if ($mode) {
            set_user_preference("socialforum_displaymode", $mode);
        }
        $displaymode = get_user_preferences("socialforum_displaymode", $CFG->socialforum_displaymode);
        socialforum_print_mode_form($socialforum->id, $displaymode, $socialforum->type);
    }
}

if (!empty($socialforum->blockafter) && !empty($socialforum->blockperiod)) {
    $a = new stdClass();
    $a->blockafter = $socialforum->blockafter;
    $a->blockperiod = get_string('secondstotime' . $socialforum->blockperiod);
    echo $OUTPUT->notification(get_string('thisforumisthrottled', 'mod_socialforum', $a));
}

echo socialforum_print_banner($cm);

echo html_writer::start_div('narrow-page discussions container');
switch ($socialforum->type) {
    case 'single':
        if (!empty($discussions) && count($discussions) > 1) {
            echo $OUTPUT->notification(get_string('warnformorepost', 'mod_socialforum'));
        }
        if (!$post = socialforum_get_post_full($discussion->firstpost)) {
            print_error('cannotfindfirstpost', 'mod_socialforum');
        }
        if ($mode) {
            set_user_preference("socialforum_displaymode", $mode);
        }

        $canreply = socialforum_user_can_post($socialforum, $discussion, $USER, $cm, $course, $context);
        $canrate = has_capability('mod/socialforum:rate', $context);
        $displaymode = get_user_preferences("socialforum_displaymode", $CFG->socialforum_displaymode);

        echo '&nbsp;'; // this should fix the floating in FF
        socialforum_print_discussion($course, $cm, $socialforum, $discussion, $post, $displaymode, $canreply, $canrate);
        break;

    case 'eachuser':
        echo '<p class="mdl-align">';
        if (socialforum_user_can_post_discussion($socialforum, null, -1, $cm)) {
            print_string("allowsdiscussions", "socialforum");
        } else {
            echo '&nbsp;';
        }
        echo '</p>';
        if (!empty($showall)) {
            socialforum_print_latest_discussions($course, $socialforum, 0, 'header', '', -1, -1, -1, 0, $cm);
        } else {
            socialforum_print_latest_discussions($course, $socialforum, -1, 'header', '', -1, -1, $page, $CFG->socialforum_manydiscussions, $cm);
        }
        break;

    case 'teacher':
        if (!empty($showall)) {
            socialforum_print_latest_discussions($course, $socialforum, 0, 'header', '', -1, -1, -1, 0, $cm);
        } else {
            socialforum_print_latest_discussions($course, $socialforum, -1, 'header', '', -1, -1, $page, $CFG->socialforum_manydiscussions, $cm);
        }
        break;

    case 'blog':
        if (!empty($showall)) {
            socialforum_print_latest_discussions($course, $socialforum, 0, 'plain', 'd.pinned DESC, p.created DESC', -1, -1, -1, 0, $cm);
        } else {
            socialforum_print_latest_discussions($course, $socialforum, -1, 'plain', 'd.pinned DESC, p.created DESC', -1, -1, $page, $CFG->socialforum_manydiscussions, $cm);
        }
        break;

    default:
        if (!empty($showall)) {
            socialforum_print_latest_discussions($course, $socialforum, 0, 'header', '', -1, -1, -1, 0, $cm);
        } else {
            socialforum_print_latest_discussions($course, $socialforum, -1, 'header', '', -1, -1, $page, $CFG->socialforum_manydiscussions, $cm);
        }


        break;
}
echo html_writer::end_div();

// Add the subscription toggle JS.
$PAGE->requires->yui_module('moodle-mod_socialforum-subscriptiontoggle', 'Y.M.mod_socialforum.subscriptiontoggle.init');

echo $OUTPUT->footer();

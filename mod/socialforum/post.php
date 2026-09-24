<?php

/**
 * Edit and save a new post to a discussion
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/socialforum/lib.php');
require_once($CFG->libdir . '/completionlib.php');

use \mod_socialforum\socialforum_votes;

$reply = optional_param('reply', 0, PARAM_INT);
$socialforum = optional_param('socialforum', 0, PARAM_INT);
$edit = optional_param('edit', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$relevant = optional_param('relevant', 0, PARAM_INT);
$irrelevant = optional_param('irrelevant', 0, PARAM_INT);
$prune = optional_param('prune', 0, PARAM_INT);
$name = optional_param('name', '', PARAM_CLEAN);
$confirm = optional_param('confirm', 0, PARAM_INT);
$groupid = optional_param('groupid', null, PARAM_INT);
$proper = optional_param('proper', 0, PARAM_INT);
$improper = optional_param('improper', 0, PARAM_INT);
$ajax = optional_param('ajax', false, PARAM_BOOL);

$PAGE->set_url('/mod/socialforum/post.php', array(
    'reply' => $reply,
    'socialforum' => $socialforum,
    'edit' => $edit,
    'delete' => $delete,
    'relevant' => $relevant,
    'irrelevant' => $irrelevant,
    'prune' => $prune,
    'name' => $name,
    'confirm' => $confirm,
    'groupid' => $groupid,
));
//these page_params will be passed as hidden variables later in the form.
$page_params = array('reply' => $reply, 'socialforum' => $socialforum, 'edit' => $edit);

$sitecontext = context_system::instance();

if (!isloggedin() or isguestuser()) {

    if (!isloggedin() and ! get_local_referer()) {
        // No referer+not logged in - probably coming in via email  See MDL-9052
        require_login();
    }

    if (!empty($socialforum)) {      // User is starting a new discussion in a forum
        if (!$socialforum = $DB->get_record('socialforum', array('id' => $socialforum))) {
            print_error('invalidforumid', 'socialforum');
        }
    } else if (!empty($reply)) {      // User is writing a new reply
        if (!$parent = socialforum_get_post_full($reply)) {
            print_error('invalidparentpostid', 'socialforum');
        }
        if (!$discussion = $DB->get_record('socialforum_discussions', array('id' => $parent->discussion))) {
            print_error('notpartofdiscussion', 'socialforum');
        }
        if (!$socialforum = $DB->get_record('socialforum', array('id' => $discussion->socialforum))) {
            print_error('invalidforumid');
        }
    }
    if (!$course = $DB->get_record('course', array('id' => $socialforum->course))) {
        print_error('invalidcourseid');
    }

    if (!$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $course->id)) { // For the logs
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $socialforum);
    $PAGE->set_context($modcontext);
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    $PAGE->set_pagelayout('noheader');
    $referer = get_local_referer(false);

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('noguestpost', 'mod_socialforum') . '<br /><br />' . get_string('liketologin'), get_login_url(), $referer);
    echo $OUTPUT->footer();
    exit;
}

require_login(0, false);   // Script is useless unless they're logged in

if (!empty($socialforum)) {      // User is starting a new discussion in a forum
    if (!$socialforum = $DB->get_record("socialforum", array("id" => $socialforum))) {
        print_error('invalidforumid', 'socialforum');
    }
    if (!$course = $DB->get_record("course", array("id" => $socialforum->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("socialforum", $socialforum->id, $course->id)) {
        print_error("invalidcoursemodule");
    }

    // Retrieve the contexts.
    $modcontext = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (!socialforum_user_can_post_discussion($socialforum, $groupid, -1, $cm)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {
                if (enrol_selfenrol_available($course->id)) {
                    $SESSION->wantsurl = qualified_me();
                    $SESSION->enrolcancel = get_local_referer(false);
                    redirect(new moodle_url('/enrol/index.php', array('id' => $course->id,
                        'returnurl' => '/mod/socialforum/view.php?f=' . $socialforum->id)), get_string('youneedtoenrol'));
                }
            }
        }
        print_error('nopostforum', 'socialforum');
    }

    if (!$cm->visible and ! has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error("activityiscurrentlyhidden");
    }

    $SESSION->fromurl = get_local_referer(false);

    // Load up the $post variable.

    $post = new stdClass();
    $post->course = $course->id;
    $post->socialforum = $socialforum->id;
    $post->discussion = 0;           // ie discussion # not defined yet
    $post->parent = 0;
    $post->subject = '';
    $post->userid = $USER->id;
    $post->message = '';
    $post->messageformat = editors_get_preferred_format();
    $post->messagetrust = 0;

    if (isset($groupid)) {
        $post->groupid = $groupid;
    } else {
        $post->groupid = groups_get_activity_group($cm);
    }

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);
} else if (!empty($reply)) {      // User is writing a new reply
    if (!$parent = socialforum_get_post_full($reply)) {
        print_error('invalidparentpostid', 'socialforum');
    }
    if (!$discussion = $DB->get_record("socialforum_discussions", array("id" => $parent->discussion))) {
        print_error('notpartofdiscussion', 'socialforum');
    }
    if (!$socialforum = $DB->get_record("socialforum", array("id" => $discussion->socialforum))) {
        print_error('invalidforumid', 'socialforum');
    }
    if (!$course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("socialforum", $socialforum->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    // Ensure lang, theme, etc. is set up properly. MDL-6926
    $PAGE->set_cm($cm, $course, $socialforum);

    // Retrieve the contexts.
    $modcontext = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (!socialforum_user_can_post($socialforum, $discussion, $USER, $cm, $course, $modcontext)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {  // User is a guest here!
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = get_local_referer(false);
                redirect(new moodle_url('/enrol/index.php', array('id' => $course->id,
                    'returnurl' => '/mod/socialforum/view.php?f=' . $socialforum->id)), get_string('youneedtoenrol'));
            }
        }
        print_error('nopostforum', 'socialforum');
    }

    // Make sure user can post here
    if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
        $groupmode = $cm->groupmode;
    } else {
        $groupmode = $course->groupmode;
    }
    if ($groupmode == SEPARATEGROUPS and ! has_capability('moodle/site:accessallgroups', $modcontext)) {
        if ($discussion->groupid == -1) {
            print_error('nopostforum', 'socialforum');
        } else {
            if (!groups_is_member($discussion->groupid)) {
                print_error('nopostforum', 'socialforum');
            }
        }
    }

    if (!$cm->visible and ! has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error("activityiscurrentlyhidden");
    }

    // Load up the $post variable.

    $post = new stdClass();
    $post->course = $course->id;
    $post->socialforum = $socialforum->id;
    $post->discussion = $parent->discussion;
    $post->parent = $parent->id;
    $post->subject = $parent->subject;
    $post->userid = $USER->id;
    $post->message = '';

    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $strre = get_string('re', 'mod_socialforum');
    if (!(substr($post->subject, 0, strlen($strre)) == $strre)) {
        $post->subject = $strre . ' ' . $post->subject;
    }

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);
} else if (!empty($edit)) {  // User is editing their own post
    if (!$post = socialforum_get_post_full($edit)) {
        print_error('invalidpostid', 'socialforum');
    }
    if ($post->parent) {
        if (!$parent = socialforum_get_post_full($post->parent)) {
            print_error('invalidparentpostid', 'socialforum');
        }
    }

    if (!$discussion = $DB->get_record("socialforum_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'socialforum');
    }
    if (!$socialforum = $DB->get_record("socialforum", array("id" => $discussion->socialforum))) {
        print_error('invalidforumid', 'socialforum');
    }
    if (!$course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("socialforum", $socialforum->id, $course->id)) {
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $socialforum);

    if (!($socialforum->type == 'news' && !$post->parent && $discussion->timestart > time())) {
        if (((time() - $post->created) > $CFG->maxeditingtime) and ! has_capability('mod/socialforum:editanypost', $modcontext)) {
            print_error('maxtimehaspassed', 'socialforum', '', format_time($CFG->maxeditingtime));
        }
    }
    if (($post->userid <> $USER->id) and ! has_capability('mod/socialforum:editanypost', $modcontext)) {
        print_error('cannoteditposts', 'socialforum');
    }


    // Load up the $post variable.
    $post->edit = $edit;
    $post->course = $course->id;
    $post->socialforum = $socialforum->id;
    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $post = trusttext_pre_edit($post, 'message', $modcontext);

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);
} else if (!empty($delete)) {  // User is deleting a post
    if (!$post = socialforum_get_post_full($delete)) {
        print_error('invalidpostid', 'socialforum');
    }
    if (!$discussion = $DB->get_record("socialforum_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'socialforum');
    }
    if (!$socialforum = $DB->get_record("socialforum", array("id" => $discussion->socialforum))) {
        print_error('invalidforumid', 'socialforum');
    }
    if (!$cm = get_coursemodule_from_instance("socialforum", $socialforum->id, $socialforum->course)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $socialforum->course))) {
        print_error('invalidcourseid');
    }

    require_login($course, false, $cm);
    $modcontext = context_module::instance($cm->id);

    if (!(($post->userid == $USER->id && has_capability('mod/socialforum:deleteownpost', $modcontext)) || has_capability('mod/socialforum:deleteanypost', $modcontext))) {
        print_error('cannotdeletepost', 'socialforum');
    }


    $replycount = socialforum_count_replies($post);

    if (!empty($confirm) && confirm_sesskey()) {    // User has confirmed the delete
        //check user capability to delete post.
        $timepassed = time() - $post->created;
        if (($timepassed > $CFG->maxeditingtime) && !has_capability('mod/socialforum:deleteanypost', $modcontext)) {
            print_error("cannotdeletepost", "socialforum", socialforum_go_back_to(new moodle_url("/mod/socialforum/discuss.php", array('d' => $post->discussion))));
        }

        if ($post->totalscore) {
            notice(get_string('couldnotdeleteratings', 'rating'), socialforum_go_back_to(new moodle_url("/mod/socialforum/discuss.php", array('d' => $post->discussion))));
        } else if ($replycount && !has_capability('mod/socialforum:deleteanypost', $modcontext)) {
            print_error("couldnotdeletereplies", "socialforum", socialforum_go_back_to(new moodle_url("/mod/socialforum/discuss.php", array('d' => $post->discussion))));
        } else {
            if (!$post->parent) {  // post is a discussion topic as well, so delete discussion
                if ($socialforum->type == 'single') {
                    notice("Sorry, but you are not allowed to delete that discussion!", socialforum_go_back_to(new moodle_url("/mod/socialforum/discuss.php", array('d' => $post->discussion))));
                }
                socialforum_delete_discussion($discussion, false, $course, $cm, $socialforum);

                $params = array(
                    'objectid' => $discussion->id,
                    'context' => $modcontext,
                    'other' => array(
                        'socialforumid' => $socialforum->id,
                    )
                );

                $event = \mod_socialforum\event\discussion_deleted::create($params);
                $event->add_record_snapshot('socialforum_discussions', $discussion);
                $event->trigger();

                redirect("view.php?f=$discussion->socialforum");
            } else if (socialforum_delete_post($post, has_capability('mod/socialforum:deleteanypost', $modcontext), $course, $cm, $socialforum)) {

                if ($socialforum->type == 'single') {
                    // Single discussion forums are an exception. We show
                    // the forum itself since it only has one discussion
                    // thread.
                    $discussionurl = new moodle_url("/mod/socialforum/view.php", array('f' => $socialforum->id));
                } else {
                    $discussionurl = new moodle_url("/mod/socialforum/discuss.php", array('d' => $discussion->id));
                }

                redirect(socialforum_go_back_to($discussionurl));
            } else {
                print_error('errorwhiledelete', 'socialforum');
            }
        }
    } else { // User just asked to delete something
        socialforum_set_return();
        $PAGE->navbar->add(get_string('delete', 'mod_socialforum'));
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        $PAGE->set_pagelayout('noheader');

        if ($replycount) {
            if (!has_capability('mod/socialforum:deleteanypost', $modcontext)) {
                print_error("couldnotdeletereplies", "socialforum", socialforum_go_back_to(new moodle_url('/mod/socialforum/discuss.php', array('d' => $post->discussion), 'p' . $post->id)));
            }
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($socialforum->name), 2);
            echo $OUTPUT->confirm(get_string("deletesureplural", "socialforum", $replycount + 1), "post.php?delete=$delete&confirm=$delete", $CFG->wwwroot . '/mod/socialforum/discuss.php?d=' . $post->discussion . '#p' . $post->id);

            socialforum_print_post($post, $discussion, $socialforum, $cm, $course, false, false, false);

            if (empty($post->edit)) {
                $socialforumtracked = socialforum_tp_is_tracked($socialforum);
                $posts = socialforum_get_all_discussion_posts($discussion->id, "created ASC", $socialforumtracked);
                socialforum_print_posts_nested($course, $cm, $socialforum, $discussion, $post, false, false, $socialforumtracked, $posts);
            }
        } else {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($socialforum->name), 2);
            echo $OUTPUT->confirm(get_string("deletesure", "socialforum", $replycount), "post.php?delete=$delete&confirm=$delete", $CFG->wwwroot . '/mod/socialforum/discuss.php?d=' . $post->discussion . '#p' . $post->id);
            socialforum_print_post($post, $discussion, $socialforum, $cm, $course, false, false, false);
        }
    }
    echo $OUTPUT->footer();
    die;
} else if (!empty($prune)) {  // Pruning
    if (!$post = socialforum_get_post_full($prune)) {
        print_error('invalidpostid', 'socialforum');
    }
    if (!$discussion = $DB->get_record("socialforum_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'socialforum');
    }
    if (!$socialforum = $DB->get_record("socialforum", array("id" => $discussion->socialforum))) {
        print_error('invalidforumid', 'socialforum');
    }
    if ($socialforum->type == 'single') {
        print_error('cannotsplit', 'socialforum');
    }
    if (!$post->parent) {
        print_error('alreadyfirstpost', 'socialforum');
    }
    if (!$cm = get_coursemodule_from_instance("socialforum", $socialforum->id, $socialforum->course)) { // For the logs
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }
    if (!has_capability('mod/socialforum:splitdiscussions', $modcontext)) {
        print_error('cannotsplit', 'socialforum');
    }

    $PAGE->set_cm($cm);
    $PAGE->set_context($modcontext);

    $prunemform = new mod_socialforum_prune_form(null, array('prune' => $prune, 'confirm' => $prune));


    if ($prunemform->is_cancelled()) {
        redirect(socialforum_go_back_to(new moodle_url("/mod/socialforum/discuss.php", array('d' => $post->discussion))));
    } else if ($fromform = $prunemform->get_data()) {
        // User submits the data.
        $newdiscussion = new stdClass();
        $newdiscussion->course = $discussion->course;
        $newdiscussion->socialforum = $discussion->socialforum;
        $newdiscussion->name = $name;
        $newdiscussion->firstpost = $post->id;
        $newdiscussion->userid = $discussion->userid;
        $newdiscussion->groupid = $discussion->groupid;
        $newdiscussion->assessed = $discussion->assessed;
        $newdiscussion->usermodified = $post->userid;
        $newdiscussion->timestart = $discussion->timestart;
        $newdiscussion->timeend = $discussion->timeend;

        $newid = $DB->insert_record('socialforum_discussions', $newdiscussion);

        $newpost = new stdClass();
        $newpost->id = $post->id;
        $newpost->parent = 0;
        $newpost->subject = $name;

        $DB->update_record("socialforum_posts", $newpost);

        socialforum_change_discussionid($post->id, $newid);

        // Update last post in each discussion.
        socialforum_discussion_update_last_post($discussion->id);
        socialforum_discussion_update_last_post($newid);

        // Fire events to reflect the split..
        $params = array(
            'context' => $modcontext,
            'objectid' => $discussion->id,
            'other' => array(
                'socialforumid' => $socialforum->id,
            )
        );
        $event = \mod_socialforum\event\discussion_updated::create($params);
        $event->trigger();

        $params = array(
            'context' => $modcontext,
            'objectid' => $newid,
            'other' => array(
                'socialforumid' => $socialforum->id,
            )
        );
        $event = \mod_socialforum\event\discussion_created::create($params);
        $event->trigger();

        $params = array(
            'context' => $modcontext,
            'objectid' => $post->id,
            'other' => array(
                'discussionid' => $newid,
                'socialforumid' => $socialforum->id,
                'socialforumtype' => $socialforum->type,
            )
        );
        $event = \mod_socialforum\event\post_updated::create($params);
        $event->add_record_snapshot('socialforum_discussions', $discussion);
        $event->trigger();

        redirect(socialforum_go_back_to(new moodle_url("/mod/socialforum/discuss.php", array('d' => $newid))));
    } else {
        // Display the prune form.
        $course = $DB->get_record('course', array('id' => $socialforum->course));
        $PAGE->navbar->add(format_string($post->subject, true), new moodle_url('/mod/socialforum/discuss.php', array('d' => $discussion->id)));
        $PAGE->navbar->add(get_string("prune", "mod_socialforum"));
        $PAGE->set_title(format_string($discussion->name) . ": " . format_string($post->subject));
        $PAGE->set_heading($course->fullname);
        $PAGE->set_pagelayout('noheader');
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($socialforum->name), 2);
        echo $OUTPUT->heading(get_string('pruneheading', 'mod_socialforum'), 3);

        $prunemform->display();

        socialforum_print_post($post, $discussion, $socialforum, $cm, $course, false, false, false);
    }

    echo $OUTPUT->footer();
    die;
} else if (!empty($relevant)) {

    if (!$post = socialforum_get_post_full($relevant)) {
        print_error('invalidparentpostid', 'socialforum');
    }
    if (!$discussion = $DB->get_record('socialforum_discussions', array('id' => $post->discussion))) {
        print_error('notpartofdiscussion', 'socialforum');
    }
    if (!$socialforum = $DB->get_record('socialforum', array('id' => $discussion->socialforum))) {
        print_error('invalidforumid');
    }
    if (!$course = $DB->get_record('course', array('id' => $socialforum->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $course->id)) { // For the logs
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $socialforum);
    $PAGE->set_context($modcontext);

    if (!$post = socialforum_get_post_full($relevant)) {
        print_error('invalidpostid', 'socialforum');
    }

    if ($post->parent == 0) {
        // Compute votes on discussion posts to discussion itself
        $votes = new socialforum_votes($post->socialforum, $post->discussion, null, $post->userid);
    } else {
        $votes = new socialforum_votes($post->socialforum, null, $post->id, $post->userid);
    }

    $previousvote = $votes->get_user_votes($USER->id);
    // Cancel previous vote it existent
    if ($previousvote) {
        // User has already voted in post relevancy. Cancel his/her vote
        $votes->remove_votes($USER->id);
    }
    if ($previousvote <= 0) {
        // Compute his/her vote only if different from previous vote
        $votes->add_votes($USER->id, 1);
    }

    if (!$ajax) {
        $url = new moodle_url('/mod/socialforum/discuss.php', array('d' => $post->discussion)) . '#p' . $post->id;
        redirect($url);
    } else {
        echo socialforum_print_votes($socialforum, $post, $discussion, true);
        die();
    }
} else if (!empty($irrelevant)) {

    if (!$post = socialforum_get_post_full($irrelevant)) {
        print_error('invalidpostid', 'socialforum');
    }
    if (!$discussion = $DB->get_record('socialforum_discussions', array('id' => $post->discussion))) {
        print_error('notpartofdiscussion', 'socialforum');
    }
    if (!$socialforum = $DB->get_record('socialforum', array('id' => $discussion->socialforum))) {
        print_error('invalidforumid');
    }
    if (!$course = $DB->get_record('course', array('id' => $socialforum->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $course->id)) { // For the logs
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $socialforum);
    $PAGE->set_context($modcontext);

    if ($post->parent == 0) {
        // Compute votes on discussion posts to discussion itself
        $votes = new socialforum_votes($post->socialforum, $post->discussion, null, $post->userid);
    } else {
        $votes = new socialforum_votes($post->socialforum, null, $post->id, $post->userid);
    }

    $previousvote = $votes->get_user_votes($USER->id);
    // Cancel previous vote it existent
    if ($previousvote) {
        // User has already voted in post relevancy. Cancel his/her vote
        $votes->remove_votes($USER->id);
    }
    if ($previousvote >= 0) {
        // Compute his/her vote only if different from previous vote
        $votes->add_votes($USER->id, -1);
    }

    if (!$ajax) {
        $url = new moodle_url('/mod/socialforum/discuss.php', array('d' => $post->discussion)) . '#p' . $post->id;
        redirect($url);
    } else {
        echo socialforum_print_votes($socialforum, $post, $discussion, true);
        die();
    }
} elseif (!empty($improper)) {

    if (!$post = socialforum_get_post_full($improper)) {
        print_error('invalidpostid', 'socialforum');
    }
    if (!$discussion = $DB->get_record('socialforum_discussions', array('id' => $post->discussion))) {
        print_error('notpartofdiscussion', 'socialforum');
    }
    if (!$socialforum = $DB->get_record('socialforum', array('id' => $discussion->socialforum))) {
        print_error('invalidforumid');
    }
    if (!$course = $DB->get_record('course', array('id' => $socialforum->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $course->id)) { // For the logs
        print_error('invalidcoursemodule');
    }

    socialforum_set_improper($cm, $improper, $post->userid);

    $url = new moodle_url('/mod/socialforum/discuss.php', array('d' => $post->discussion)) . '#p' . $post->id;
    redirect($url);
} elseif (!empty($proper)) {

    if (!$post = socialforum_get_post_full($proper)) {
        print_error('invalidpostid', 'socialforum');
    }
    if (!$discussion = $DB->get_record('socialforum_discussions', array('id' => $post->discussion))) {
        print_error('notpartofdiscussion', 'socialforum');
    }
    if (!$socialforum = $DB->get_record('socialforum', array('id' => $discussion->socialforum))) {
        print_error('invalidforumid');
    }
    if (!$course = $DB->get_record('course', array('id' => $socialforum->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $course->id)) { // For the logs
        print_error('invalidcoursemodule');
    }

    socialforum_reset_improper($cm, $proper, $post->userid);

    $url = new moodle_url('/mod/socialforum/discuss.php', array('d' => $post->discussion)) . '#p' . $post->id;
    redirect($url);
} else {
    print_error('unknowaction');
}

if (!isset($coursecontext)) {
    // Has not yet been set by post.php.
    $coursecontext = context_course::instance($socialforum->course);
}

// from now on user must be logged on properly

if (!$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $course->id)) { // For the logs
    print_error('invalidcoursemodule');
}
$modcontext = context_module::instance($cm->id);
require_login($course, false, $cm);

if (isguestuser()) {
    // just in case
    print_error('noguest');
}

if (!isset($socialforum->maxattachments)) {  // TODO - delete this once we add a field to the forum table
    $socialforum->maxattachments = 3;
}

$thresholdwarning = socialforum_check_throttling($socialforum, $cm);
$mform_post = new mod_socialforum_post_form('post.php', array('course' => $course,
    'cm' => $cm,
    'coursecontext' => $coursecontext,
    'modcontext' => $modcontext,
    'socialforum' => $socialforum,
    'post' => $post,
    'subscribe' => \mod_socialforum\subscriptions::is_subscribed($USER->id, $socialforum, null, $cm),
    'thresholdwarning' => $thresholdwarning,
    'edit' => $edit), 'post', '', array('id' => 'mformforum'));

$draftitemid = file_get_submitted_draft_itemid('attachments');
file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_socialforum', 'attachment', empty($post->id) ? null : $post->id, mod_socialforum_post_form::attachment_options($socialforum));

//load data into form NOW!

if ($USER->id != $post->userid) {   // Not the original author, so add a message to the end
    $data = new stdClass();
    $data->date = userdate($post->modified);
    if ($post->messageformat == FORMAT_HTML) {
        $data->name = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $USER->id . '&course=' . $post->course . '">' .
                fullname($USER) . '</a>';
        $post->message .= '<p><span class="edited">(' . get_string('editedby', 'socialforum', $data) . ')</span></p>';
    } else {
        $data->name = fullname($USER);
        $post->message .= "\n\n(" . get_string('editedby', 'socialforum', $data) . ')';
    }
    unset($data);
}

$formheading = '';
if (!empty($parent)) {
    $heading = get_string("yourreply", "mod_socialforum");
    $formheading = get_string('reply', 'mod_socialforum');
} else {
    if ($socialforum->type == 'qanda') {
        $heading = get_string('yournewquestion', 'mod_socialforum');
    } else {
        $heading = get_string('yournewtopic', 'mod_socialforum');
    }
}

$postid = empty($post->id) ? null : $post->id;
$draftid_editor = file_get_submitted_draft_itemid('message');
$currenttext = file_prepare_draft_area($draftid_editor, $modcontext->id, 'mod_socialforum', 'post', $postid, mod_socialforum_post_form::editor_options($modcontext, $postid), $post->message);

$manageactivities = has_capability('moodle/course:manageactivities', $coursecontext);
if (\mod_socialforum\subscriptions::subscription_disabled($socialforum) && !$manageactivities) {
    // User does not have permission to subscribe to this discussion at all.
    $discussionsubscribe = false;
} else if (\mod_socialforum\subscriptions::is_forcesubscribed($socialforum)) {
    // User does not have permission to unsubscribe from this discussion at all.
    $discussionsubscribe = true;
} else {
    if (isset($discussion) && \mod_socialforum\subscriptions::is_subscribed($USER->id, $socialforum, $discussion->id, $cm)) {
        // User is subscribed to the discussion - continue the subscription.
        $discussionsubscribe = true;
    } else if (!isset($discussion) && \mod_socialforum\subscriptions::is_subscribed($USER->id, $socialforum, null, $cm)) {
        // Starting a new discussion, and the user is subscribed to the forum - subscribe to the discussion.
        $discussionsubscribe = true;
    } else {
        // User is not subscribed to either forum or discussion. Follow user preference.
        $discussionsubscribe = $USER->autosubscribe;
    }
}

$mform_post->set_data(array('attachments' => $draftitemid,
    'general' => $heading,
    'subject' => $post->subject,
    'message' => array(
        'text' => $currenttext,
        'format' => empty($post->messageformat) ? editors_get_preferred_format() : $post->messageformat,
        'itemid' => $draftid_editor
    ),
    'discussionsubscribe' => $discussionsubscribe,
    'mailnow' => !empty($post->mailnow),
    'userid' => $post->userid,
    'parent' => $post->parent,
    'discussion' => $post->discussion,
    'course' => $course->id) +
        $page_params +
        (isset($post->format) ? array(
            'format' => $post->format) :
                array()) +
        (isset($discussion->timestart) ? array(
            'timestart' => $discussion->timestart) :
                array()) +
        (isset($discussion->timeend) ? array(
            'timeend' => $discussion->timeend) :
                array()) +
        (isset($discussion->pinned) ? array(
            'pinned' => $discussion->pinned) :
                array()) +
        (isset($post->groupid) ? array(
            'groupid' => $post->groupid) :
                array()) +
        (isset($discussion->id) ?
                array('discussion' => $discussion->id) :
                array()));

if ($mform_post->is_cancelled()) {
    if (!isset($discussion->id) || $socialforum->type === 'qanda') {
        // Q and A forums don't have a discussion page, so treat them like a new thread..
        redirect(new moodle_url('/mod/socialforum/view.php', array('f' => $socialforum->id)));
    } else {
        redirect(new moodle_url('/mod/socialforum/discuss.php', array('d' => $discussion->id)));
    }
} else if ($fromform = $mform_post->get_data()) {

    if (empty($SESSION->fromurl)) {
        $errordestination = "$CFG->wwwroot/mod/socialforum/view.php?f=$socialforum->id";
    } else {
        $errordestination = $SESSION->fromurl;
    }

    $fromform->itemid = $fromform->message['itemid'];
    $fromform->messageformat = $fromform->message['format'];
    $fromform->message = $fromform->message['text'];
    // WARNING: the $fromform->message array has been overwritten, do not use it anymore!
    $fromform->messagetrust = trusttext_trusted($modcontext);

    if ($fromform->edit) {           // Updating a post
        unset($fromform->groupid);
        $fromform->id = $fromform->edit;
        $message = '';

        //fix for bug #4314
        if (!$realpost = $DB->get_record('socialforum_posts', array('id' => $fromform->id))) {
            $realpost = new stdClass();
            $realpost->userid = -1;
        }


        // if user has edit any post capability
        // or has either startnewdiscussion or reply capability and is editting own post
        // then he can proceed
        // MDL-7066
        if (!(($realpost->userid == $USER->id && (has_capability('mod/socialforum:replypost', $modcontext) || has_capability('mod/socialforum:startdiscussion', $modcontext))) ||
                has_capability('mod/socialforum:editanypost', $modcontext))) {
            print_error('cannotupdatepost', 'socialforum');
        }

        // If the user has access to all groups and they are changing the group, then update the post.
        if (isset($fromform->groupinfo) && has_capability('mod/socialforum:movediscussions', $modcontext)) {
            if (empty($fromform->groupinfo)) {
                $fromform->groupinfo = -1;
            }

            if (!socialforum_user_can_post_discussion($socialforum, $fromform->groupinfo, null, $cm, $modcontext)) {
                print_error('cannotupdatepost', 'socialforum');
            }

            $DB->set_field('socialforum_discussions', 'groupid', $fromform->groupinfo, array('firstpost' => $fromform->id));
        }
        // When editing first post/discussion.
        if (!$fromform->parent) {
            if (has_capability('mod/socialforum:pindiscussions', $modcontext)) {
                // Can change pinned if we have capability.
                $fromform->pinned = !empty($fromform->pinned) ? SOCIALFORUM_DISCUSSION_PINNED : SOCIALFORUM_DISCUSSION_UNPINNED;
            } else {
                // We don't have the capability to change so keep to previous value.
                unset($fromform->pinned);
            }
        }
        $updatepost = $fromform; //realpost
        $updatepost->socialforum = $socialforum->id;
        if (!socialforum_update_post($updatepost, $mform_post, $message)) {
            print_error("couldnotupdate", "socialforum", $errordestination);
        }

        // MDL-11818
        if (($socialforum->type == 'single') && ($updatepost->parent == '0')) { // updating first post of single discussion type -> updating forum intro
            $socialforum->intro = $updatepost->message;
            $socialforum->timemodified = time();
            $DB->update_record("socialforum", $socialforum);
        }

        if ($realpost->userid == $USER->id) {
            $message .= '<br />' . get_string("postupdated", "mod_socialforum");
        } else {
            $realuser = $DB->get_record('user', array('id' => $realpost->userid));
            $message .= '<br />' . get_string("editedpostupdated", "socialforum", fullname($realuser));
        }

        $subscribemessage = socialforum_post_subscription($fromform, $socialforum, $discussion);
        if ($socialforum->type == 'single') {
            // Single discussion forums are an exception. We show
            // the forum itself since it only has one discussion
            // thread.
            $discussionurl = new moodle_url("/mod/socialforum/view.php", array('f' => $socialforum->id));
        } else {
            $discussionurl = new moodle_url("/mod/socialforum/discuss.php", array('d' => $discussion->id), 'p' . $fromform->id);
        }

        $params = array(
            'context' => $modcontext,
            'objectid' => $fromform->id,
            'other' => array(
                'discussionid' => $discussion->id,
                'socialforumid' => $socialforum->id,
                'socialforumtype' => $socialforum->type,
            )
        );

        if ($realpost->userid !== $USER->id) {
            $params['relateduserid'] = $realpost->userid;
        }

        $event = \mod_socialforum\event\post_updated::create($params);
        $event->add_record_snapshot('socialforum_discussions', $discussion);
        $event->trigger();

        redirect(
                socialforum_go_back_to($discussionurl), $message . $subscribemessage, null, \core\output\notification::NOTIFY_SUCCESS
        );
    } else if ($fromform->discussion) { // Adding a new post to an existing discussion
        // Before we add this we must check that the user will not exceed the blocking threshold.
        socialforum_check_blocking_threshold($thresholdwarning);

        unset($fromform->groupid);
        $message = '';
        $addpost = $fromform;
        $addpost->socialforum = $socialforum->id;
        if ($fromform->id = socialforum_add_new_post($addpost, $mform_post, $message)) {
            $subscribemessage = socialforum_post_subscription($fromform, $socialforum, $discussion);

            if (!empty($fromform->mailnow)) {
                $message .= get_string("postmailnow", "mod_socialforum");
            } else {
                $message .= '<p>' . get_string("postaddedsuccess", "mod_socialforum") . '</p>';
                $message .= '<p>' . get_string("postaddedtimeleft", "mod_socialforum", format_time($CFG->maxeditingtime)) . '</p>';
            }

            if ($socialforum->type == 'single') {
                // Single discussion forums are an exception. We show
                // the forum itself since it only has one discussion
                // thread.
                $discussionurl = new moodle_url("/mod/socialforum/view.php", array('f' => $socialforum->id), 'p' . $fromform->id);
            } else {
                $discussionurl = new moodle_url("/mod/socialforum/discuss.php", array('d' => $discussion->id), 'p' . $fromform->id);
            }

            $params = array(
                'context' => $modcontext,
                'objectid' => $fromform->id,
                'other' => array(
                    'discussionid' => $discussion->id,
                    'socialforumid' => $socialforum->id,
                    'socialforumtype' => $socialforum->type,
                )
            );
            $eventtriggered = 0;
            if ($reply > 0) {
                $parent = socialforum_get_post_full($reply);
                $posthasimage = strpos($addpost->message, "<img ");
                $replyto = $parent->userid;
                $iscourseteacher = socialforum_is_course_teacher($replyto, $course);
                if ($posthasimage && $iscourseteacher) {
                    $event = \mod_socialforum\event\teacher_post_replied::create($params);
                    $eventtriggered = 1;
                }
            }
            if (!$eventtriggered) {
                $event = \mod_socialforum\event\post_created::create($params);
            }
            $event->add_record_snapshot('socialforum_posts', $fromform);
            $event->add_record_snapshot('socialforum_discussions', $discussion);
            $event->trigger();

            // Update completion state
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) &&
                    ($socialforum->completionreplies || $socialforum->completionposts)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }

            redirect(
                    socialforum_go_back_to($discussionurl), $message . $subscribemessage, null, \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            print_error("couldnotadd", "socialforum", $errordestination);
        }
        exit;
    } else { // Adding a new discussion.
        // The location to redirect to after successfully posting.
        $redirectto = new moodle_url('view.php', array('f' => $fromform->socialforum));

        $fromform->mailnow = empty($fromform->mailnow) ? 0 : 1;

        $discussion = $fromform;
        $discussion->name = $fromform->subject;

        $newstopic = false;
        if ($socialforum->type == 'news' && !$fromform->parent) {
            $newstopic = true;
        }
        $discussion->timestart = $fromform->timestart;
        $discussion->timeend = $fromform->timeend;

        if (has_capability('mod/socialforum:pindiscussions', $modcontext) && !empty($fromform->pinned)) {
            $discussion->pinned = SOCIALFORUM_DISCUSSION_PINNED;
        } else {
            $discussion->pinned = SOCIALFORUM_DISCUSSION_UNPINNED;
        }

        $allowedgroups = array();
        $groupstopostto = array();

        // If we are posting a copy to all groups the user has access to.
        if (isset($fromform->posttomygroups)) {
            // Post to each of my groups.
            require_capability('mod/socialforum:canposttomygroups', $modcontext);

            // Fetch all of this user's groups.
            // Note: all groups are returned when in visible groups mode so we must manually filter.
            $allowedgroups = groups_get_activity_allowed_groups($cm);
            foreach ($allowedgroups as $groupid => $group) {
                if (socialforum_user_can_post_discussion($socialforum, $groupid, -1, $cm, $modcontext)) {
                    $groupstopostto[] = $groupid;
                }
            }
        } else if (isset($fromform->groupinfo)) {
            // Use the value provided in the dropdown group selection.
            $groupstopostto[] = $fromform->groupinfo;
            $redirectto->param('group', $fromform->groupinfo);
        } else if (isset($fromform->groupid) && !empty($fromform->groupid)) {
            // Use the value provided in the hidden form element instead.
            $groupstopostto[] = $fromform->groupid;
            $redirectto->param('group', $fromform->groupid);
        } else {
            // Use the value for all participants instead.
            $groupstopostto[] = -1;
        }

        // Before we post this we must check that the user will not exceed the blocking threshold.
        socialforum_check_blocking_threshold($thresholdwarning);

        foreach ($groupstopostto as $group) {
            if (!socialforum_user_can_post_discussion($socialforum, $group, -1, $cm, $modcontext)) {
                print_error('cannotcreatediscussion', 'socialforum');
            }

            $discussion->groupid = $group;
            $message = '';
            if ($discussion->id = socialforum_add_discussion($discussion, $mform_post, $message)) {

                $params = array(
                    'context' => $modcontext,
                    'objectid' => $discussion->id,
                    'other' => array(
                        'socialforumid' => $socialforum->id,
                    )
                );
                $event = \mod_socialforum\event\discussion_created::create($params);
                $event->add_record_snapshot('socialforum_discussions', $discussion);
                $event->trigger();

                if ($fromform->mailnow) {
                    $message .= get_string("postmailnow", "mod_socialforum");
                } else {
                    $message .= '<p>' . get_string("postaddedsuccess", "mod_socialforum") . '</p>';
                    $message .= '<p>' . get_string("postaddedtimeleft", "mod_socialforum", format_time($CFG->maxeditingtime)) . '</p>';
                }

                $subscribemessage = socialforum_post_subscription($fromform, $socialforum, $discussion);
            } else {
                print_error("couldnotadd", "socialforum", $errordestination);
            }
        }

        // Update completion status.
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) &&
                ($socialforum->completiondiscussions || $socialforum->completionposts)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        // Redirect back to the discussion.
        redirect(
                socialforum_go_back_to($redirectto->out()), $message . $subscribemessage, null, \core\output\notification::NOTIFY_SUCCESS
        );
    }
}



// To get here they need to edit a post, and the $post
// variable will be loaded with all the particulars,
// so bring up the form.
// $course, $socialforum are defined.  $discussion is for edit and reply only.

if ($post->discussion) {
    if (!$toppost = $DB->get_record("socialforum_posts", array("discussion" => $post->discussion, "parent" => 0))) {
        print_error('cannotfindparentpost', 'socialforum', '', $post->id);
    }
} else {
    $toppost = new stdClass();
    $toppost->subject = ($socialforum->type == "news") ? get_string("addanewtopic", "mod_socialforum") :
            get_string("addanewdiscussion", "mod_socialforum");
}

if (empty($post->edit)) {
    $post->edit = '';
}

if (empty($discussion->name)) {
    if (empty($discussion)) {
        $discussion = new stdClass();
    }
    $discussion->name = $socialforum->name;
}
if ($socialforum->type == 'single') {
    // There is only one discussion thread for this forum type. We should
    // not show the discussion name (same as forum name in this case) in
    // the breadcrumbs.
    $strdiscussionname = '';
} else {
    // Show the discussion name in the breadcrumbs.
    $strdiscussionname = format_string($discussion->name) . ':';
}

$forcefocus = empty($reply) ? NULL : 'message';

if (!empty($discussion->id)) {
    $PAGE->navbar->add(format_string($toppost->subject, true), "discuss.php?d=$discussion->id");
}

if ($post->parent) {
    $PAGE->navbar->add(get_string('reply', 'mod_socialforum'));
}

if ($edit) {
    $PAGE->navbar->add(get_string('edit', 'mod_socialforum'));
}

$PAGE->set_title("$strdiscussionname " . format_string($toppost->subject));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('noheader');

echo $OUTPUT->header();

echo socialforum_print_banner($cm);

echo html_writer::start_div('narrow-page post container mt-32pt');
// checkup
if (!empty($parent) && !socialforum_user_can_see_post($socialforum, $discussion, $post, null, $cm)) {
    print_error('cannotreply', 'socialforum');
}
if (empty($parent) && empty($edit) && !socialforum_user_can_post_discussion($socialforum, $groupid, -1, $cm, $modcontext)) {
    print_error('cannotcreatediscussion', 'socialforum');
}

// If there is a warning message and we are not editing a post we need to handle the warning.
if (!empty($thresholdwarning) && !$edit) {
    // Here we want to throw an exception if they are no longer allowed to post.
    socialforum_check_blocking_threshold($thresholdwarning);
}

if (!empty($parent)) {
    if (!$discussion = $DB->get_record('socialforum_discussions', array('id' => $parent->discussion))) {
        print_error('notpartofdiscussion', 'socialforum');
    }

    echo html_writer::start_div('mb-24pt mb-sm-0 mr-sm-24pt', array(
        'style' => 'padding-bottom: 2rem;'
    ));
    $discussionurl = new moodle_url('/mod/socialforum/discuss.php', array(
        'd' => $discussion->id
    ));
    echo html_writer::start_tag('a', array(
        'href' => $discussionurl
    ));
    echo html_writer::tag('h2', get_string('topic', 'mod_socialforum') . ': ' . $discussion->name, array('class' => 'mb-0'));
    echo html_writer::end_tag('a');
    echo html_writer::end_div();

    socialforum_print_post($parent, $discussion, $socialforum, $cm, $course, false, false, false);
    if (empty($post->edit)) {
        if ($socialforum->type != 'qanda' || socialforum_user_can_see_discussion($socialforum, $discussion, $modcontext)) {
            $socialforumtracked = socialforum_tp_is_tracked($socialforum);
            $posts = socialforum_get_all_discussion_posts($discussion->id, "created ASC", $socialforumtracked);
            socialforum_print_posts_threaded($course, $cm, $socialforum, $discussion, $parent, 0, false, $socialforumtracked, $posts);
        }
    }
} else {
    if (!empty($socialforum->intro)) {
        echo $OUTPUT->box(format_module_intro('socialforum', $socialforum, $cm->id), 'generalbox', 'intro');

        if (!empty($CFG->enableplagiarism)) {
            require_once($CFG->libdir . '/plagiarismlib.php');
            echo plagiarism_print_disclosure($cm->id);
        }
    }
}

if (!empty($formheading)) {
    echo $OUTPUT->heading($formheading, 2, array('class' => 'accesshide'));
}

if (!$reply) {
    echo html_writer::start_div('row');
}
$mform_post->display();
if (!$reply) {
    echo socialforum_print_search_first($cm);
    echo html_writer::end_div();
}

echo html_writer::end_div();

echo $OUTPUT->footer();

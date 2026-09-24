<?php

/**
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once(__DIR__ . '/deprecatedlib.php');
require_once($CFG->libdir . '/filelib.php');

/// USES ///////////////////////////////////////////////////////////

use \mod_socialforum\socialforum_votes;

/// CONSTANTS ///////////////////////////////////////////////////////////

define('SOCIALFORUM_MODE_NESTED', 3);

define('SOCIALFORUM_CHOOSESUBSCRIBE', 0);
define('SOCIALFORUM_FORCESUBSCRIBE', 1);
define('SOCIALFORUM_INITIALSUBSCRIBE', 2);
define('SOCIALFORUM_DISALLOWSUBSCRIBE', 3);

/**
 * SOCIALFORUM_TRACKING_OFF - Tracking is not available for this social forum.
 */
define('SOCIALFORUM_TRACKING_OFF', 0);

/**
 * SOCIALFORUM_TRACKING_OPTIONAL - Tracking is based on user preference.
 */
define('SOCIALFORUM_TRACKING_OPTIONAL', 1);

/**
 * SOCIALFORUM_TRACKING_FORCED - Tracking is on, regardless of user setting.
 * Treated as SOCIALFORUM_TRACKING_OPTIONAL if $CFG->socialforum_allowforcedreadtracking is off.
 */
define('SOCIALFORUM_TRACKING_FORCED', 2);

define('SOCIALFORUM_MAILED_PENDING', 0);
define('SOCIALFORUM_MAILED_SUCCESS', 1);
define('SOCIALFORUM_MAILED_ERROR', 2);

if (!defined('SOCIALFORUM_CRON_USER_CACHE')) {
    /** Defines how many full user records are cached in social forum cron. */
    define('SOCIALFORUM_CRON_USER_CACHE', 5000);
}

/**
 * SOCIALFORUM_POSTS_ALL_USER_GROUPS - All the posts in groups where the user is enrolled.
 */
define('SOCIALFORUM_POSTS_ALL_USER_GROUPS', -2);

define('SOCIALFORUM_DISCUSSION_PINNED', 1);
define('SOCIALFORUM_DISCUSSION_UNPINNED', 0);

/// STANDARD FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $socialforum add social forum instance
 * @param mod_socialforum_mod_form $mform
 * @return int intance id
 */
function socialforum_add_instance($socialforum, $mform = null) {
    global $DB;

    $socialforum->timemodified = time();

    if (empty($socialforum->assessed)) {
        $socialforum->assessed = 0;
    }

    if (empty($socialforum->ratingtime) or empty($socialforum->assessed)) {
        $socialforum->assesstimestart = 0;
        $socialforum->assesstimefinish = 0;
    }

    $socialforum->id = $DB->insert_record('socialforum', $socialforum);
    $modcontext = context_module::instance($socialforum->coursemodule);

    if ($socialforum->type == 'single') {  // Create related discussion.
        $discussion = new stdClass();
        $discussion->course = $socialforum->course;
        $discussion->socialforum = $socialforum->id;
        $discussion->name = $socialforum->name;
        $discussion->assessed = $socialforum->assessed;
        $discussion->message = $socialforum->intro;
        $discussion->messageformat = $socialforum->introformat;
        $discussion->messagetrust = trusttext_trusted(context_course::instance($socialforum->course));
        $discussion->mailnow = false;
        $discussion->groupid = -1;

        $message = '';

        $discussion->id = socialforum_add_discussion($discussion, null, $message);

        if ($mform and $draftid = file_get_submitted_draft_itemid('introeditor')) {
            // Ugly hack - we need to copy the files somehow.
            $discussion = $DB->get_record('socialforum_discussions', array('id' => $discussion->id), '*', MUST_EXIST);
            $post = $DB->get_record('socialforum_posts', array('id' => $discussion->firstpost), '*', MUST_EXIST);

            $options = array('subdirs' => true); // Use the same options as intro field!
            $post->message = file_save_draft_area_files($draftid, $modcontext->id, 'mod_socialforum', 'post', $post->id, $options, $post->message);
            $DB->set_field('socialforum_posts', 'message', $post->message, array('id' => $post->id));
        }
    }

    socialforum_grade_item_update($socialforum);

    return $socialforum->id;
}

/**
 * Handle changes following the creation of a social forum instance.
 * This function is typically called by the course_module_created observer.
 *
 * @param object $context the social forum context
 * @param stdClass $socialforum The social forum object
 * @return void
 */
function socialforum_instance_created($context, $socialforum) {
    if ($socialforum->forcesubscribe == SOCIALFORUM_INITIALSUBSCRIBE) {
        $users = \mod_socialforum\subscriptions::get_potential_subscribers($context, 0, 'u.id, u.email');
        foreach ($users as $user) {
            \mod_socialforum\subscriptions::subscribe_user($user->id, $socialforum, $context);
        }
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $socialforum social forum instance (with magic quotes)
 * @return bool success
 */
function socialforum_update_instance($socialforum, $mform) {
    global $DB, $OUTPUT, $USER;

    $socialforum->timemodified = time();
    $socialforum->id = $socialforum->instance;

    if (empty($socialforum->assessed)) {
        $socialforum->assessed = 0;
    }

    if (empty($socialforum->ratingtime) or empty($socialforum->assessed)) {
        $socialforum->assesstimestart = 0;
        $socialforum->assesstimefinish = 0;
    }

    $oldforum = $DB->get_record('socialforum', array('id' => $socialforum->id));

    // MDL-3942 - if the aggregation type or scale (i.e. max grade) changes then recalculate the grades for the entire social forum
    // if  scale changes - do we need to recheck the ratings, if ratings higher than scale how do we want to respond?
    // for count and sum aggregation types the grade we check to make sure they do not exceed the scale (i.e. max score) when calculating the grade
    if (($oldforum->assessed <> $socialforum->assessed) or ( $oldforum->scale <> $socialforum->scale)) {
        socialforum_update_grades($socialforum); // recalculate grades for the social forum
    }

    if ($socialforum->type == 'single') {  // Update related discussion and post.
        $discussions = $DB->get_records('socialforum_discussions', array('socialforum' => $socialforum->id), 'timemodified ASC');
        if (!empty($discussions)) {
            if (count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'mod_socialforum'));
            }
            $discussion = array_pop($discussions);
        } else {
            // try to recover by creating initial discussion - MDL-16262
            $discussion = new stdClass();
            $discussion->course = $socialforum->course;
            $discussion->socialforum = $socialforum->id;
            $discussion->name = $socialforum->name;
            $discussion->assessed = $socialforum->assessed;
            $discussion->message = $socialforum->intro;
            $discussion->messageformat = $socialforum->introformat;
            $discussion->messagetrust = true;
            $discussion->mailnow = false;
            $discussion->groupid = -1;

            $message = '';

            socialforum_add_discussion($discussion, null, $message);

            if (!$discussion = $DB->get_record('socialforum_discussions', array('socialforum' => $socialforum->id))) {
                print_error('cannotadd', 'socialforum');
            }
        }
        if (!$post = $DB->get_record('socialforum_posts', array('id' => $discussion->firstpost))) {
            print_error('cannotfindfirstpost', 'socialforum');
        }

        $cm = get_coursemodule_from_instance('socialforum', $socialforum->id);
        $modcontext = context_module::instance($cm->id, MUST_EXIST);

        $post = $DB->get_record('socialforum_posts', array('id' => $discussion->firstpost), '*', MUST_EXIST);
        $post->subject = $socialforum->name;
        $post->message = $socialforum->intro;
        $post->messageformat = $socialforum->introformat;
        $post->messagetrust = trusttext_trusted($modcontext);
        $post->modified = $socialforum->timemodified;
        $post->userid = $USER->id;    // MDL-18599, so that current teacher can take ownership of activities.

        if ($mform and $draftid = file_get_submitted_draft_itemid('introeditor')) {
            // Ugly hack - we need to copy the files somehow.
            $options = array('subdirs' => true); // Use the same options as intro field!
            $post->message = file_save_draft_area_files($draftid, $modcontext->id, 'mod_socialforum', 'post', $post->id, $options, $post->message);
        }

        $DB->update_record('socialforum_posts', $post);
        $discussion->name = $socialforum->name;
        $DB->update_record('socialforum_discussions', $discussion);
    }

    $DB->update_record('socialforum', $socialforum);

    $modcontext = context_module::instance($socialforum->coursemodule);
    if (($socialforum->forcesubscribe == SOCIALFORUM_INITIALSUBSCRIBE) && ($oldforum->forcesubscribe <> $socialforum->forcesubscribe)) {
        $users = \mod_socialforum\subscriptions::get_potential_subscribers($modcontext, 0, 'u.id, u.email', '');
        foreach ($users as $user) {
            \mod_socialforum\subscriptions::subscribe_user($user->id, $socialforum, $modcontext);
        }
    }

    socialforum_grade_item_update($socialforum);

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id social forum instance id
 * @return bool success
 */
function socialforum_delete_instance($id) {
    global $DB;

    if (!$socialforum = $DB->get_record('socialforum', array('id' => $id))) {
        return false;
    }
    if (!$cm = get_coursemodule_from_instance('socialforum', $socialforum->id)) {
        return false;
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        return false;
    }

    $context = context_module::instance($cm->id);

    // now get rid of all files
    $fs = get_file_storage();
    $fs->delete_area_files($context->id);

    $result = true;

    // Delete digest and subscription preferences.
    $DB->delete_records('socialforum_digests', array('socialforum' => $socialforum->id));
    $DB->delete_records('socialforum_subscriptions', array('socialforum' => $socialforum->id));
    $DB->delete_records('socialforum_discussion_subs', array('socialforum' => $socialforum->id));

    if ($discussions = $DB->get_records('socialforum_discussions', array('socialforum' => $socialforum->id))) {
        foreach ($discussions as $discussion) {
            if (!socialforum_delete_discussion($discussion, true, $course, $cm, $socialforum)) {
                $result = false;
            }
        }
    }

    socialforum_tp_delete_read_records(-1, -1, -1, $socialforum->id);

    if (!$DB->delete_records('socialforum', array('id' => $socialforum->id))) {
        $result = false;
    }

    socialforum_grade_item_delete($socialforum);

    return $result;
}

/**
 * Indicates API features that the social forum supports.
 *
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_COMPLETION_HAS_RULES
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature
 * @return mixed True if yes (some features may use other values)
 */
function socialforum_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS: return true;
        case FEATURE_GROUPINGS: return true;
        case FEATURE_MOD_INTRO: return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES: return true;
        case FEATURE_GRADE_HAS_GRADE: return true;
        case FEATURE_GRADE_OUTCOMES: return true;
        case FEATURE_RATE: return true;
        case FEATURE_BACKUP_MOODLE2: return true;
        case FEATURE_SHOW_DESCRIPTION: return true;
        case FEATURE_PLAGIARISM: return true;

        default: return null;
    }
}

/**
 * Obtains the automatic completion state for this social forum based on any conditions
 * in social forum settings.
 *
 * @global object
 * @global object
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function socialforum_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;

    // Get social forum details
    if (!($socialforum = $DB->get_record('socialforum', array('id' => $cm->instance)))) {
        throw new Exception("Can't find social forum {$cm->instance}");
    }

    $result = $type; // Default return value

    $postcountparams = array('userid' => $userid, 'socialforumid' => $socialforum->id);
    $postcountsql = "
SELECT
    COUNT(1)
FROM
    {socialforum_posts} fp
    INNER JOIN {socialforum_discussions} fd ON fp.discussion=fd.id
WHERE
    fp.userid=:userid AND fd.socialforum=:socialforumid";

    if ($socialforum->completiondiscussions) {
        $value = $socialforum->completiondiscussions <= $DB->count_records('socialforum_discussions', array('socialforum' => $socialforum->id, 'userid' => $userid));
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($socialforum->completionreplies) {
        $value = $socialforum->completionreplies <= $DB->get_field_sql($postcountsql . ' AND fp.parent<>0', $postcountparams);
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($socialforum->completionposts) {
        $value = $socialforum->completionposts <= $DB->get_field_sql($postcountsql, $postcountparams);
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }

    return $result;
}

/**
 * Create a message-id string to use in the custom headers of social forum notification emails
 *
 * message-id is used by email clients to identify emails and to nest conversations
 *
 * @param int $postid The ID of the social forum post we are notifying the user about
 * @param int $usertoid The ID of the user being notified
 * @return string A unique message-id
 */
function socialforum_get_email_message_id($postid, $usertoid) {
    return generate_email_messageid(hash('sha256', $postid . 'to' . $usertoid));
}

/**
 * Removes properties from user record that are not necessary
 * for sending post notifications.
 * @param stdClass $user
 * @return void, $user parameter is modified
 */
function socialforum_cron_minimise_user_record(stdClass $user) {

    // We store large amount of users in one huge array,
    // make sure we do not store info there we do not actually need
    // in mail generation code or messaging.

    unset($user->institution);
    unset($user->department);
    unset($user->address);
    unset($user->city);
    unset($user->url);
    unset($user->currentlogin);
    unset($user->description);
    unset($user->descriptionformat);
}

/**
 * Function to be run periodically according to the scheduled task.
 *
 * Finds all posts that have yet to be mailed out, and mails them
 * out to all subscribers as well as other maintance tasks.
 *
 * NOTE: Since 2.7.2 this function is run by scheduled task rather
 * than standard cron.
 *
 * @todo MDL-44734 The function will be split up into seperate tasks.
 */
function socialforum_cron() {
    global $CFG, $USER, $DB, $PAGE;

    $site = get_site();

    // The main renderers.
    $htmlout = $PAGE->get_renderer('mod_socialforum', 'email', 'htmlemail');
    $textout = $PAGE->get_renderer('mod_socialforum', 'email', 'textemail');
    $htmldigestfullout = $PAGE->get_renderer('mod_socialforum', 'emaildigestfull', 'htmlemail');
    $textdigestfullout = $PAGE->get_renderer('mod_socialforum', 'emaildigestfull', 'textemail');
    $htmldigestbasicout = $PAGE->get_renderer('mod_socialforum', 'emaildigestbasic', 'htmlemail');
    $textdigestbasicout = $PAGE->get_renderer('mod_socialforum', 'emaildigestbasic', 'textemail');

    // All users that are subscribed to any post that needs sending,
    // please increase $CFG->extramemorylimit on large sites that
    // send notifications to a large number of users.
    $users = array();
    $userscount = 0; // Cached user counter - count($users) in PHP is horribly slow!!!
    // Status arrays.
    $mailcount = array();
    $errorcount = array();

    // caches
    $discussions = array();
    $socialforums = array();
    $courses = array();
    $coursemodules = array();
    $subscribedusers = array();
    $messageinboundhandlers = array();

    // Posts older than 2 days will not be mailed.  This is to avoid the problem where
    // cron has not been running for a long time, and then suddenly people are flooded
    // with mail from the past few weeks or months
    $timenow = time();
    $endtime = $timenow - $CFG->maxeditingtime;
    $starttime = $endtime - 48 * 3600;   // Two days earlier
    // Get the list of social forum subscriptions for per-user per-forum maildigest settings.
    $digestsset = $DB->get_recordset('socialforum_digests', null, '', 'id, userid, socialforum, maildigest');
    $digests = array();
    foreach ($digestsset as $thisrow) {
        if (!isset($digests[$thisrow->socialforum])) {
            $digests[$thisrow->socialforum] = array();
        }
        $digests[$thisrow->socialforum][$thisrow->userid] = $thisrow->maildigest;
    }
    $digestsset->close();

    // Create the generic messageinboundgenerator.
    $messageinboundgenerator = new \core\message\inbound\address_manager();
    $messageinboundgenerator->set_handler('\mod_socialforum\message\inbound\reply_handler');

    if ($posts = socialforum_get_unmailed_posts($starttime, $endtime, $timenow)) {
        // Mark them all now as being mailed.  It's unlikely but possible there
        // might be an error later so that a post is NOT actually mailed out,
        // but since mail isn't crucial, we can accept this risk.  Doing it now
        // prevents the risk of duplicated mails, which is a worse problem.

        if (!socialforum_mark_old_posts_as_mailed($endtime)) {
            mtrace('Errors occurred while trying to mark some posts as being mailed.');
            return false;  // Don't continue trying to mail them, in case we are in a cron loop
        }

        // checking post validity, and adding users to loop through later
        foreach ($posts as $pid => $post) {

            $discussionid = $post->discussion;
            if (!isset($discussions[$discussionid])) {
                if ($discussion = $DB->get_record('socialforum_discussions', array('id' => $post->discussion))) {
                    $discussions[$discussionid] = $discussion;
                    \mod_socialforum\subscriptions::fill_subscription_cache($discussion->socialforum);
                    \mod_socialforum\subscriptions::fill_discussion_subscription_cache($discussion->socialforum);
                } else {
                    mtrace('Could not find discussion ' . $discussionid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $socialforumid = $discussions[$discussionid]->socialforum;
            if (!isset($socialforums[$socialforumid])) {
                if ($socialforum = $DB->get_record('socialforum', array('id' => $socialforumid))) {
                    $socialforums[$socialforumid] = $socialforum;
                } else {
                    mtrace('Could not find social forum ' . $socialforumid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $courseid = $socialforums[$socialforumid]->course;
            if (!isset($courses[$courseid])) {
                if ($course = $DB->get_record('course', array('id' => $courseid))) {
                    $courses[$courseid] = $course;
                } else {
                    mtrace('Could not find course ' . $courseid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            if (!isset($coursemodules[$socialforumid])) {
                if ($cm = get_coursemodule_from_instance('socialforum', $socialforumid, $courseid)) {
                    $coursemodules[$socialforumid] = $cm;
                } else {
                    mtrace('Could not find course module for social forum ' . $socialforumid);
                    unset($posts[$pid]);
                    continue;
                }
            }

            // Save the Inbound Message datakey here to reduce DB queries later.
            $messageinboundgenerator->set_data($pid);
            $messageinboundhandlers[$pid] = $messageinboundgenerator->fetch_data_key();

            // Caching subscribed users of each social forum.
            if (!isset($subscribedusers[$socialforumid])) {
                $modcontext = context_module::instance($coursemodules[$socialforumid]->id);
                if ($subusers = \mod_socialforum\subscriptions::fetch_subscribed_users($socialforums[$socialforumid], 0, $modcontext, 'u.*', true)) {

                    foreach ($subusers as $postuser) {
                        // this user is subscribed to this social forum
                        $subscribedusers[$socialforumid][$postuser->id] = $postuser->id;
                        $userscount++;
                        if ($userscount > SOCIALFORUM_CRON_USER_CACHE) {
                            // Store minimal user info.
                            $minuser = new stdClass();
                            $minuser->id = $postuser->id;
                            $users[$postuser->id] = $minuser;
                        } else {
                            // Cache full user record.
                            socialforum_cron_minimise_user_record($postuser);
                            $users[$postuser->id] = $postuser;
                        }
                    }
                    // Release memory.
                    unset($subusers);
                    unset($postuser);
                }
            }
            $mailcount[$pid] = 0;
            $errorcount[$pid] = 0;
        }
    }

    if ($users && $posts) {

        foreach ($users as $userto) {
            // Terminate if processing of any account takes longer than 2 minutes.
            core_php_time_limit::raise(120);

            mtrace('Processing user ' . $userto->id);

            // Init user caches - we keep the cache for one cycle only, otherwise it could consume too much memory.
            if (isset($userto->username)) {
                $userto = clone($userto);
            } else {
                $userto = $DB->get_record('user', array('id' => $userto->id));
                socialforum_cron_minimise_user_record($userto);
            }
            $userto->viewfullnames = array();
            $userto->canpost = array();
            $userto->markposts = array();

            // Setup this user so that the capabilities are cached, and environment matches receiving user.
            cron_setup_user($userto);

            // Reset the caches.
            foreach ($coursemodules as $socialforumid => $unused) {
                $coursemodules[$socialforumid]->cache = new stdClass();
                $coursemodules[$socialforumid]->cache->caps = array();
                unset($coursemodules[$socialforumid]->uservisible);
            }

            foreach ($posts as $pid => $post) {
                $discussion = $discussions[$post->discussion];
                $socialforum = $socialforums[$discussion->socialforum];
                $course = $courses[$socialforum->course];
                $cm = & $coursemodules[$socialforum->id];

                // Do some checks to see if we can bail out now.
                // Only active enrolled users are in the list of subscribers.
                // This does not necessarily mean that the user is subscribed to the social forum or to the discussion though.
                if (!isset($subscribedusers[$socialforum->id][$userto->id])) {
                    // The user does not subscribe to this social forum.
                    continue;
                }

                if (!\mod_socialforum\subscriptions::is_subscribed($userto->id, $socialforum, $post->discussion, $coursemodules[$socialforum->id])) {
                    // The user does not subscribe to this social forum, or to this specific discussion.
                    continue;
                }

                if ($subscriptiontime = \mod_socialforum\subscriptions::fetch_discussion_subscription($socialforum->id, $userto->id)) {
                    // Skip posts if the user subscribed to the discussion after it was created.
                    if (isset($subscriptiontime[$post->discussion]) && ($subscriptiontime[$post->discussion] > $post->created)) {
                        continue;
                    }
                }

                // Don't send email if the social forum is Q&A and the user has not posted.
                // Initial topics are still mailed.
                if ($socialforum->type == 'qanda' && !socialforum_get_user_posted_time($discussion->id, $userto->id) && $pid != $discussion->firstpost) {
                    mtrace('Did not email ' . $userto->id . ' because user has not posted in discussion');
                    continue;
                }

                // Get info about the sending user.
                if (array_key_exists($post->userid, $users)) {
                    // We might know the user already.
                    $userfrom = $users[$post->userid];
                    if (!isset($userfrom->idnumber)) {
                        // Minimalised user info, fetch full record.
                        $userfrom = $DB->get_record('user', array('id' => $userfrom->id));
                        socialforum_cron_minimise_user_record($userfrom);
                    }
                } else if ($userfrom = $DB->get_record('user', array('id' => $post->userid))) {
                    socialforum_cron_minimise_user_record($userfrom);
                    // Fetch only once if possible, we can add it to user list, it will be skipped anyway.
                    if ($userscount <= SOCIALFORUM_CRON_USER_CACHE) {
                        $userscount++;
                        $users[$userfrom->id] = $userfrom;
                    }
                } else {
                    mtrace('Could not find user ' . $post->userid . ', author of post ' . $post->id . '. Unable to send message.');
                    continue;
                }

                // Note: If we want to check that userto and userfrom are not the same person this is probably the spot to do it.
                // Setup global $COURSE properly - needed for roles and languages.
                cron_setup_user($userto, $course);

                // Fill caches.
                if (!isset($userto->viewfullnames[$socialforum->id])) {
                    $modcontext = context_module::instance($cm->id);
                    $userto->viewfullnames[$socialforum->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                }
                if (!isset($userto->canpost[$discussion->id])) {
                    $modcontext = context_module::instance($cm->id);
                    $userto->canpost[$discussion->id] = socialforum_user_can_post($socialforum, $discussion, $userto, $cm, $course, $modcontext);
                }
                if (!isset($userfrom->groups[$socialforum->id])) {
                    if (!isset($userfrom->groups)) {
                        $userfrom->groups = array();
                        if (isset($users[$userfrom->id])) {
                            $users[$userfrom->id]->groups = array();
                        }
                    }
                    $userfrom->groups[$socialforum->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                    if (isset($users[$userfrom->id])) {
                        $users[$userfrom->id]->groups[$socialforum->id] = $userfrom->groups[$socialforum->id];
                    }
                }

                // Make sure groups allow this user to see this email.
                if ($discussion->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {
                    // Groups are being used.
                    if (!groups_group_exists($discussion->groupid)) {
                        // Can't find group - be safe and don't this message.
                        continue;
                    }

                    if (!groups_is_member($discussion->groupid) and ! has_capability('moodle/site:accessallgroups', $modcontext)) {
                        // Do not send posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS.
                        continue;
                    }
                }

                // Make sure we're allowed to see the post.
                if (!socialforum_user_can_see_post($socialforum, $discussion, $post, null, $cm)) {
                    mtrace('User ' . $userto->id . ' can not see ' . $post->id . '. Not sending message.');
                    continue;
                }

                // OK so we need to send the email.
                // Does the user want this post in a digest?  If so postpone it for now.
                $maildigest = socialforum_get_user_maildigest_bulk($digests, $userto, $socialforum->id);

                if ($maildigest > 0) {
                    // This user wants the mails to be in digest form.
                    $queue = new stdClass();
                    $queue->userid = $userto->id;
                    $queue->discussionid = $discussion->id;
                    $queue->postid = $post->id;
                    $queue->timemodified = $post->created;
                    $DB->insert_record('socialforum_queue', $queue);
                    continue;
                }

                // Prepare to actually send the post now, and build up the content.

                $cleanforumname = str_replace('"', "'", strip_tags(format_string($socialforum->name)));

                $userfrom->customheaders = array(
                    // Headers to make emails easier to track.
                    'List-Id: "' . $cleanforumname . '" ' . generate_email_messageid('moodleforum' . $socialforum->id),
                    'List-Help: ' . $CFG->wwwroot . '/mod/socialforum/view.php?f=' . $socialforum->id,
                    'Message-ID: ' . socialforum_get_email_message_id($post->id, $userto->id),
                    'X-Course-Id: ' . $course->id,
                    'X-Course-Name: ' . format_string($course->fullname, true),
                    // Headers to help prevent auto-responders.
                    'Precedence: Bulk',
                    'X-Auto-Response-Suppress: All',
                    'Auto-Submitted: auto-generated',
                );

                $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

                // Generate a reply-to address from using the Inbound Message handler.
                $replyaddress = null;
                if ($userto->canpost[$discussion->id] && array_key_exists($post->id, $messageinboundhandlers)) {
                    $messageinboundgenerator->set_data($post->id, $messageinboundhandlers[$post->id]);
                    $replyaddress = $messageinboundgenerator->generate($userto->id);
                }

                if (!isset($userto->canpost[$discussion->id])) {
                    $canreply = socialforum_user_can_post($socialforum, $discussion, $userto, $cm, $course, $modcontext);
                } else {
                    $canreply = $userto->canpost[$discussion->id];
                }

                $data = new \mod_socialforum\output\socialforum_post_email(
                        $course, $cm, $socialforum, $discussion, $post, $userfrom, $userto, $canreply
                );

                $userfrom->customheaders[] = sprintf('List-Unsubscribe: <%s>', $data->get_unsubscribediscussionlink());

                if (!isset($userto->viewfullnames[$socialforum->id])) {
                    $data->viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
                } else {
                    $data->viewfullnames = $userto->viewfullnames[$socialforum->id];
                }

                // Not all of these variables are used in the default language
                // string but are made available to support custom subjects.
                $a = new stdClass();
                $a->subject = $data->get_subject();
                $a->socialforumname = $cleanforumname;
                $a->sitefullname = format_string($site->fullname);
                $a->siteshortname = format_string($site->shortname);
                $a->courseidnumber = $data->get_courseidnumber();
                $a->coursefullname = $data->get_coursefullname();
                $a->courseshortname = $data->get_coursename();
                $postsubject = html_to_text(get_string('postmailsubject', 'socialforum', $a), 0);

                $rootid = socialforum_get_email_message_id($discussion->firstpost, $userto->id);

                if ($post->parent) {
                    // This post is a reply, so add reply header (RFC 2822).
                    $parentid = socialforum_get_email_message_id($post->parent, $userto->id);
                    $userfrom->customheaders[] = "In-Reply-To: $parentid";

                    // If the post is deeply nested we also reference the parent message id and
                    // the root message id (if different) to aid threading when parts of the email
                    // conversation have been deleted (RFC1036).
                    if ($post->parent != $discussion->firstpost) {
                        $userfrom->customheaders[] = "References: $rootid $parentid";
                    } else {
                        $userfrom->customheaders[] = "References: $parentid";
                    }
                }

                // MS Outlook / Office uses poorly documented and non standard headers, including
                // Thread-Topic which overrides the Subject and shouldn't contain Re: or Fwd: etc.
                $a->subject = $discussion->name;
                $threadtopic = html_to_text(get_string('postmailsubject', 'socialforum', $a), 0);
                $userfrom->customheaders[] = "Thread-Topic: $threadtopic";
                $userfrom->customheaders[] = "Thread-Index: " . substr($rootid, 1, 28);

                // Send the post now!
                mtrace('Sending ', '');

                $eventdata = new \core\message\message();
                $eventdata->component = 'mod_socialforum';
                $eventdata->name = 'posts';
                $eventdata->userfrom = $userfrom;
                $eventdata->userto = $userto;
                $eventdata->subject = $postsubject;
                $eventdata->fullmessage = $textout->render($data);
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml = $htmlout->render($data);
                $eventdata->notification = 1;
                $eventdata->replyto = $replyaddress;
                if (!empty($replyaddress)) {
                    // Add extra text to email messages if they can reply back.
                    $textfooter = "\n\n" . get_string('replytopostbyemail', 'mod_socialforum');
                    $htmlfooter = html_writer::tag('p', get_string('replytopostbyemail', 'mod_socialforum'));
                    $additionalcontent = array('fullmessage' => array('footer' => $textfooter),
                        'fullmessagehtml' => array('footer' => $htmlfooter));
                    $eventdata->set_additional_content('email', $additionalcontent);
                }

                // If socialforum_replytouser is not set then send mail using the noreplyaddress.
                if (empty($CFG->socialforum_replytouser)) {
                    $eventdata->userfrom = core_user::get_noreply_user();
                }

                $smallmessagestrings = new stdClass();
                $smallmessagestrings->user = fullname($userfrom);
                $smallmessagestrings->socialforumname = "$shortname: " . format_string($socialforum->name, true) . ": " . $discussion->name;
                $smallmessagestrings->message = $post->message;

                // Make sure strings are in message recipients language.
                $eventdata->smallmessage = get_string_manager()->get_string('smallmessage', 'socialforum', $smallmessagestrings, $userto->lang);

                $contexturl = new moodle_url('/mod/socialforum/discuss.php', array('d' => $discussion->id), 'p' . $post->id);
                $eventdata->contexturl = $contexturl->out();
                $eventdata->contexturlname = $discussion->name;

                $mailresult = message_send($eventdata);
                if (!$mailresult) {
                    mtrace("Error: mod/socialforum/lib.php socialforum_cron(): Could not send out mail for id $post->id to user $userto->id" .
                            " ($userto->email) .. not trying again.");
                    $errorcount[$post->id] ++;
                } else {
                    $mailcount[$post->id] ++;

                    // Mark post as read if socialforum_usermarksread is set off.
                    if (!$CFG->socialforum_usermarksread) {
                        $userto->markposts[$post->id] = $post->id;
                    }
                }

                mtrace('post ' . $post->id . ': ' . $post->subject);
            }

            // Mark processed posts as read.
            socialforum_tp_mark_posts_read($userto, $userto->markposts);
            unset($userto);
        }
    }

    if ($posts) {
        foreach ($posts as $post) {
            mtrace($mailcount[$post->id] . " users were sent post $post->id, '$post->subject'");
            if ($errorcount[$post->id]) {
                $DB->set_field('socialforum_posts', 'mailed', SOCIALFORUM_MAILED_ERROR, array('id' => $post->id));
            }
        }
    }

    // release some memory
    unset($subscribedusers);
    unset($mailcount);
    unset($errorcount);

    cron_setup_user();

    $sitetimezone = core_date::get_server_timezone();

    // Now see if there are any digest mails waiting to be sent, and if we should send them

    mtrace('Starting digest processing...');

    core_php_time_limit::raise(300); // terminate if not able to fetch all digests in 5 minutes

    if (!isset($CFG->digestmailtimelast)) {    // To catch the first time
        set_config('digestmailtimelast', 0);
    }

    $timenow = time();
    $digesttime = usergetmidnight($timenow, $sitetimezone) + ($CFG->digestmailtime * 3600);

    // Delete any really old ones (normally there shouldn't be any)
    $weekago = $timenow - (7 * 24 * 3600);
    $DB->delete_records_select('socialforum_queue', "timemodified < ?", array($weekago));
    mtrace('Cleaned old digest records');

    if ($CFG->digestmailtimelast < $digesttime and $timenow > $digesttime) {

        mtrace('Sending social social forum digests: ' . userdate($timenow, '', $sitetimezone));

        $digestposts_rs = $DB->get_recordset_select('socialforum_queue', "timemodified < ?", array($digesttime));

        if ($digestposts_rs->valid()) {

            // We have work to do
            $usermailcount = 0;

            //caches - reuse the those filled before too
            $discussionposts = array();
            $userdiscussions = array();

            foreach ($digestposts_rs as $digestpost) {
                if (!isset($posts[$digestpost->postid])) {
                    if ($post = $DB->get_record('socialforum_posts', array('id' => $digestpost->postid))) {
                        $posts[$digestpost->postid] = $post;
                    } else {
                        continue;
                    }
                }
                $discussionid = $digestpost->discussionid;
                if (!isset($discussions[$discussionid])) {
                    if ($discussion = $DB->get_record('socialforum_discussions', array('id' => $discussionid))) {
                        $discussions[$discussionid] = $discussion;
                    } else {
                        continue;
                    }
                }
                $socialforumid = $discussions[$discussionid]->socialforum;
                if (!isset($socialforums[$socialforumid])) {
                    if ($socialforum = $DB->get_record('socialforum', array('id' => $socialforumid))) {
                        $socialforums[$socialforumid] = $socialforum;
                    } else {
                        continue;
                    }
                }

                $courseid = $socialforums[$socialforumid]->course;
                if (!isset($courses[$courseid])) {
                    if ($course = $DB->get_record('course', array('id' => $courseid))) {
                        $courses[$courseid] = $course;
                    } else {
                        continue;
                    }
                }

                if (!isset($coursemodules[$socialforumid])) {
                    if ($cm = get_coursemodule_from_instance('socialforum', $socialforumid, $courseid)) {
                        $coursemodules[$socialforumid] = $cm;
                    } else {
                        continue;
                    }
                }
                $userdiscussions[$digestpost->userid][$digestpost->discussionid] = $digestpost->discussionid;
                $discussionposts[$digestpost->discussionid][$digestpost->postid] = $digestpost->postid;
            }
            $digestposts_rs->close(); /// Finished iteration, let's close the resultset
            // Data collected, start sending out emails to each user
            foreach ($userdiscussions as $userid => $thesediscussions) {

                core_php_time_limit::raise(120); // terminate if processing of any account takes longer than 2 minutes

                cron_setup_user();

                mtrace(get_string('processingdigest', 'socialforum', $userid), '... ');

                // First of all delete all the queue entries for this user
                $DB->delete_records_select('socialforum_queue', "userid = ? AND timemodified < ?", array($userid, $digesttime));

                // Init user caches - we keep the cache for one cycle only,
                // otherwise it would unnecessarily consume memory.
                if (array_key_exists($userid, $users) and isset($users[$userid]->username)) {
                    $userto = clone($users[$userid]);
                } else {
                    $userto = $DB->get_record('user', array('id' => $userid));
                    socialforum_cron_minimise_user_record($userto);
                }
                $userto->viewfullnames = array();
                $userto->canpost = array();
                $userto->markposts = array();

                // Override the language and timezone of the "current" user, so that
                // mail is customised for the receiver.
                cron_setup_user($userto);

                $postsubject = get_string('digestmailsubject', 'socialforum', format_string($site->shortname, true));

                $headerdata = new stdClass();
                $headerdata->sitename = format_string($site->fullname, true);
                $headerdata->userprefs = $CFG->wwwroot . '/user/edit.php?id=' . $userid . '&amp;course=' . $site->id;

                $posttext = get_string('digestmailheader', 'socialforum', $headerdata) . "\n\n";
                $headerdata->userprefs = '<a target="_blank" href="' . $headerdata->userprefs . '">' . get_string('digestmailprefs', 'mod_socialforum') . '</a>';

                $posthtml = '<p>' . get_string('digestmailheader', 'socialforum', $headerdata) . '</p>'
                        . '<br /><hr size="1" noshade="noshade" />';

                foreach ($thesediscussions as $discussionid) {

                    core_php_time_limit::raise(120);   // to be reset for each post

                    $discussion = $discussions[$discussionid];
                    $socialforum = $socialforums[$discussion->socialforum];
                    $course = $courses[$socialforum->course];
                    $cm = $coursemodules[$socialforum->id];

                    //override language
                    cron_setup_user($userto, $course);

                    // Fill caches
                    if (!isset($userto->viewfullnames[$socialforum->id])) {
                        $modcontext = context_module::instance($cm->id);
                        $userto->viewfullnames[$socialforum->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                    }
                    if (!isset($userto->canpost[$discussion->id])) {
                        $modcontext = context_module::instance($cm->id);
                        $userto->canpost[$discussion->id] = socialforum_user_can_post($socialforum, $discussion, $userto, $cm, $course, $modcontext);
                    }

                    $strforums = get_string('socialforums', 'mod_socialforum');
                    $canunsubscribe = !\mod_socialforum\subscriptions::is_forcesubscribed($socialforum);
                    $canreply = $userto->canpost[$discussion->id];
                    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

                    $posttext .= "\n \n";
                    $posttext .= '=====================================================================';
                    $posttext .= "\n \n";
                    $posttext .= "$shortname -> $strforums -> " . format_string($socialforum->name, true);
                    if ($discussion->name != $socialforum->name) {
                        $posttext .= " -> " . format_string($discussion->name, true);
                    }
                    $posttext .= "\n";
                    $posttext .= $CFG->wwwroot . '/mod/socialforum/discuss.php?d=' . $discussion->id;
                    $posttext .= "\n";

                    $posthtml .= "<p><font face=\"sans-serif\">" .
                            "<a target=\"_blank\" href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$shortname</a> -> " .
                            "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/socialforum/index.php?id=$course->id\">$strforums</a> -> " .
                            "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/socialforum/view.php?f=$socialforum->id\">" . format_string($socialforum->name, true) . "</a>";
                    if ($discussion->name == $socialforum->name) {
                        $posthtml .= "</font></p>";
                    } else {
                        $posthtml .= " -> <a target=\"_blank\" href=\"$CFG->wwwroot/mod/socialforum/discuss.php?d=$discussion->id\">" . format_string($discussion->name, true) . "</a></font></p>";
                    }
                    $posthtml .= '<p>';

                    $postsarray = $discussionposts[$discussionid];
                    sort($postsarray);
                    $sentcount = 0;

                    foreach ($postsarray as $postid) {
                        $post = $posts[$postid];

                        if (array_key_exists($post->userid, $users)) { // we might know him/her already
                            $userfrom = $users[$post->userid];
                            if (!isset($userfrom->idnumber)) {
                                $userfrom = $DB->get_record('user', array('id' => $userfrom->id));
                                socialforum_cron_minimise_user_record($userfrom);
                            }
                        } else if ($userfrom = $DB->get_record('user', array('id' => $post->userid))) {
                            socialforum_cron_minimise_user_record($userfrom);
                            if ($userscount <= SOCIALFORUM_CRON_USER_CACHE) {
                                $userscount++;
                                $users[$userfrom->id] = $userfrom;
                            }
                        } else {
                            mtrace('Could not find user ' . $post->userid);
                            continue;
                        }

                        if (!isset($userfrom->groups[$socialforum->id])) {
                            if (!isset($userfrom->groups)) {
                                $userfrom->groups = array();
                                if (isset($users[$userfrom->id])) {
                                    $users[$userfrom->id]->groups = array();
                                }
                            }
                            $userfrom->groups[$socialforum->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                            if (isset($users[$userfrom->id])) {
                                $users[$userfrom->id]->groups[$socialforum->id] = $userfrom->groups[$socialforum->id];
                            }
                        }

                        // Headers to help prevent auto-responders.
                        $userfrom->customheaders = array(
                            "Precedence: Bulk",
                            'X-Auto-Response-Suppress: All',
                            'Auto-Submitted: auto-generated',
                        );

                        $maildigest = socialforum_get_user_maildigest_bulk($digests, $userto, $socialforum->id);
                        if (!isset($userto->canpost[$discussion->id])) {
                            $canreply = socialforum_user_can_post($socialforum, $discussion, $userto, $cm, $course, $modcontext);
                        } else {
                            $canreply = $userto->canpost[$discussion->id];
                        }

                        $data = new \mod_socialforum\output\socialforum_post_email(
                                $course, $cm, $socialforum, $discussion, $post, $userfrom, $userto, $canreply
                        );

                        if (!isset($userto->viewfullnames[$socialforum->id])) {
                            $data->viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
                        } else {
                            $data->viewfullnames = $userto->viewfullnames[$socialforum->id];
                        }

                        if ($maildigest == 2) {
                            // Subjects and link only.
                            $posttext .= $textdigestbasicout->render($data);
                            $posthtml .= $htmldigestbasicout->render($data);
                        } else {
                            // The full treatment.
                            $posttext .= $textdigestfullout->render($data);
                            $posthtml .= $htmldigestfullout->render($data);

                            // Create an array of postid's for this user to mark as read.
                            if (!$CFG->socialforum_usermarksread) {
                                $userto->markposts[$post->id] = $post->id;
                            }
                        }
                        $sentcount++;
                    }
                    $footerlinks = array();
                    if ($canunsubscribe) {
                        $footerlinks[] = "<a href=\"$CFG->wwwroot/mod/socialforum/subscribe.php?id=$socialforum->id\">" . get_string("unsubscribe", "mod_socialforum") . "</a>";
                    } else {
                        $footerlinks[] = get_string("everyoneissubscribed", "mod_socialforum");
                    }
                    $footerlinks[] = "<a href='{$CFG->wwwroot}/mod/socialforum/index.php?id={$socialforum->course}'>" . get_string("digestmailpost", "mod_socialforum") . '</a>';
                    $posthtml .= "\n<div class='mdl-right'><font size=\"1\">" . implode('&nbsp;', $footerlinks) . '</font></div>';
                    $posthtml .= '<hr size="1" noshade="noshade" /></p>';
                }

                if (empty($userto->mailformat) || $userto->mailformat != 1) {
                    // This user DOESN'T want to receive HTML
                    $posthtml = '';
                }

                $eventdata = new \core\message\message();
                $eventdata->component = 'mod_socialforum';
                $eventdata->name = 'digests';
                $eventdata->userfrom = core_user::get_noreply_user();
                $eventdata->userto = $userto;
                $eventdata->subject = $postsubject;
                $eventdata->fullmessage = $posttext;
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml = $posthtml;
                $eventdata->notification = 1;
                $eventdata->smallmessage = get_string('smallmessagedigest', 'socialforum', $sentcount);
                $mailresult = message_send($eventdata);

                if (!$mailresult) {
                    mtrace("ERROR: mod/socialforum/cron.php: Could not send out digest mail to user $userto->id " .
                            "($userto->email)... not trying again.");
                } else {
                    mtrace("success.");
                    $usermailcount++;

                    // Mark post as read if socialforum_usermarksread is set off
                    socialforum_tp_mark_posts_read($userto, $userto->markposts);
                }
            }
        }
        /// We have finishied all digest emails, update $CFG->digestmailtimelast
        set_config('digestmailtimelast', $timenow);
    }

    cron_setup_user();

    if (!empty($usermailcount)) {
        mtrace(get_string('digestsentusers', 'socialforum', $usermailcount));
    }

    if (!empty($CFG->socialforum_lastreadclean)) {
        $timenow = time();
        if ($CFG->socialforum_lastreadclean + (24 * 3600) < $timenow) {
            set_config('socialforum_lastreadclean', $timenow);
            mtrace('Removing old social forum read tracking info...');
            socialforum_tp_clean_read_records();
        }
    } else {
        set_config('socialforum_lastreadclean', time());
    }

    return true;
}

/**
 *
 * @param object $course
 * @param object $user
 * @param object $mod TODO this is not used in this function, refactor
 * @param object $socialforum
 * @return object A standard object with 2 variables: info (number of posts for this user) and time (last modified)
 */
function socialforum_user_outline($course, $user, $mod, $socialforum) {
    global $CFG;
    require_once("$CFG->libdir/gradelib.php");
    $grades = grade_get_grades($course->id, 'mod', 'socialforum', $socialforum->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
    }

    $count = socialforum_count_user_posts($socialforum->id, $user->id);

    if ($count && $count->postcount > 0) {
        $result = new stdClass();
        $result->info = get_string("numposts", "socialforum", $count->postcount);
        $result->time = $count->lastpost;
        if ($grade) {
            $result->info .= ', ' . get_string('grade') . ': ' . $grade->str_long_grade;
        }
        return $result;
    } else if ($grade) {
        $result = new stdClass();
        $result->info = get_string('grade') . ': ' . $grade->str_long_grade;

        //datesubmitted == time created. dategraded == time modified or time overridden
        //if grade was last modified by the user themselves use date graded. Otherwise use date submitted
        //TODO: move this copied & pasted code somewhere in the grades API. See MDL-26704
        if ($grade->usermodified == $user->id || empty($grade->datesubmitted)) {
            $result->time = $grade->dategraded;
        } else {
            $result->time = $grade->datesubmitted;
        }

        return $result;
    }
    return NULL;
}

/**
 * @global object
 * @global object
 * @param object $coure
 * @param object $user
 * @param object $mod
 * @param object $socialforum
 */
function socialforum_user_complete($course, $user, $mod, $socialforum) {
    global $CFG, $USER, $OUTPUT;
    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'socialforum', $socialforum->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        echo $OUTPUT->container(get_string('grade') . ': ' . $grade->str_long_grade);
        if ($grade->str_feedback) {
            echo $OUTPUT->container(get_string('feedback') . ': ' . $grade->str_feedback);
        }
    }

    if ($posts = socialforum_get_user_posts($socialforum->id, $user->id)) {

        if (!$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $course->id)) {
            print_error('invalidcoursemodule');
        }
        $discussions = socialforum_get_user_involved_discussions($socialforum->id, $user->id);

        foreach ($posts as $post) {
            if (!isset($discussions[$post->discussion])) {
                continue;
            }
            $discussion = $discussions[$post->discussion];

            socialforum_print_post($post, $discussion, $socialforum, $cm, $course, false, false, false);
        }
    } else {
        echo "<p>" . get_string("noposts", "mod_socialforum") . "</p>";
    }
}

/**
 * Filters the social forum discussions according to groups membership and config.
 *
 * @since  Moodle 2.8, 2.7.1, 2.6.4
 * @param  array $discussions Discussions with new posts array
 * @return array Forums with the number of new posts
 */
function socialforum_filter_user_groups_discussions($discussions) {

    // Group the remaining discussions posts by their socialforumid.
    $filteredforums = array();

    // Discard not visible groups.
    foreach ($discussions as $discussion) {

        // Course data is already cached.
        $instances = get_fast_modinfo($discussion->course)->get_instances();
        $socialforum = $instances['socialforum'][$discussion->socialforum];

        // Continue if the user should not see this discussion.
        if (!socialforum_is_user_group_discussion($socialforum, $discussion->groupid)) {
            continue;
        }

        // Grouping results by social forum.
        if (empty($filteredforums[$socialforum->instance])) {
            $filteredforums[$socialforum->instance] = new stdClass();
            $filteredforums[$socialforum->instance]->id = $socialforum->id;
            $filteredforums[$socialforum->instance]->count = 0;
        }
        $filteredforums[$socialforum->instance]->count += $discussion->count;
    }

    return $filteredforums;
}

/**
 * Returns whether the discussion group is visible by the current user or not.
 *
 * @since Moodle 2.8, 2.7.1, 2.6.4
 * @param cm_info $cm The discussion course module
 * @param int $discussiongroupid The discussion groupid
 * @return bool
 */
function socialforum_is_user_group_discussion(cm_info $cm, $discussiongroupid) {

    if ($discussiongroupid == -1 || $cm->effectivegroupmode != SEPARATEGROUPS) {
        return true;
    }

    if (isguestuser()) {
        return false;
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id)) ||
            in_array($discussiongroupid, $cm->get_modinfo()->get_groups($cm->groupingid))) {
        return true;
    }

    return false;
}

/**
 * @global object
 * @global object
 * @global object
 * @param array $courses
 * @param array $htmlarray
 */
function socialforum_print_overview($courses, &$htmlarray) {
    global $USER, $CFG, $DB, $SESSION;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$socialforums = get_all_instances_in_courses('socialforum', $courses)) {
        return;
    }

    // Courses to search for new posts
    $coursessqls = array();
    $params = array();
    foreach ($courses as $course) {

        // If the user has never entered into the course all posts are pending
        if ($course->lastaccess == 0) {
            $coursessqls[] = '(d.course = ?)';
            $params[] = $course->id;

            // Only posts created after the course last access
        } else {
            $coursessqls[] = '(d.course = ? AND p.created > ?)';
            $params[] = $course->id;
            $params[] = $course->lastaccess;
        }
    }
    $params[] = $USER->id;
    $coursessql = implode(' OR ', $coursessqls);

    $sql = "SELECT d.id, d.socialforum, d.course, d.groupid, COUNT(*) as count "
            . 'FROM {socialforum_discussions} d '
            . 'JOIN {socialforum_posts} p ON p.discussion = d.id '
            . "WHERE ($coursessql) "
            . 'AND p.userid != ? '
            . 'AND (d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?)) '
            . 'GROUP BY d.id, d.socialforum, d.course, d.groupid '
            . 'ORDER BY d.course, d.socialforum';
    $params[] = time();
    $params[] = time();

    // Avoid warnings.
    if (!$discussions = $DB->get_records_sql($sql, $params)) {
        $discussions = array();
    }

    $socialforumsnewposts = socialforum_filter_user_groups_discussions($discussions);

    // also get all social forum tracking stuff ONCE.
    $trackingforums = array();
    foreach ($socialforums as $socialforum) {
        if (socialforum_tp_can_track_socialforums($socialforum)) {
            $trackingforums[$socialforum->id] = $socialforum;
        }
    }

    if (count($trackingforums) > 0) {
        $cutoffdate = isset($CFG->socialforum_oldpostdays) ? (time() - ($CFG->socialforum_oldpostdays * 24 * 60 * 60)) : 0;
        $sql = 'SELECT d.socialforum,d.course,COUNT(p.id) AS count ' .
                ' FROM {socialforum_posts} p ' .
                ' JOIN {socialforum_discussions} d ON p.discussion = d.id ' .
                ' LEFT JOIN {socialforum_read} r ON r.postid = p.id AND r.userid = ? WHERE (';
        $params = array($USER->id);

        foreach ($trackingforums as $track) {
            $sql .= '(d.socialforum = ? AND (d.groupid = -1 OR d.groupid = 0 OR d.groupid = ?)) OR ';
            $params[] = $track->id;
            if (isset($SESSION->currentgroup[$track->course])) {
                $groupid = $SESSION->currentgroup[$track->course];
            } else {
                // get first groupid
                $groupids = groups_get_all_groups($track->course, $USER->id);
                if ($groupids) {
                    reset($groupids);
                    $groupid = key($groupids);
                    $SESSION->currentgroup[$track->course] = $groupid;
                } else {
                    $groupid = 0;
                }
                unset($groupids);
            }
            $params[] = $groupid;
        }
        $sql = substr($sql, 0, -3); // take off the last OR
        $sql .= ') AND p.modified >= ? AND r.id is NULL ';
        $sql .= 'AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)) ';
        $sql .= 'GROUP BY d.socialforum,d.course';
        $params[] = $cutoffdate;
        $params[] = time();
        $params[] = time();

        if (!$unread = $DB->get_records_sql($sql, $params)) {
            $unread = array();
        }
    } else {
        $unread = array();
    }

    if (empty($unread) and empty($socialforumsnewposts)) {
        return;
    }

    $strforum = get_string('modulename', 'mod_socialforum');

    foreach ($socialforums as $socialforum) {
        $str = '';
        $count = 0;
        $thisunread = 0;
        $showunread = false;
        // either we have something from logs, or trackposts, or nothing.
        if (array_key_exists($socialforum->id, $socialforumsnewposts) && !empty($socialforumsnewposts[$socialforum->id])) {
            $count = $socialforumsnewposts[$socialforum->id]->count;
        }
        if (array_key_exists($socialforum->id, $unread)) {
            $thisunread = $unread[$socialforum->id]->count;
            $showunread = true;
        }
        if ($count > 0 || $thisunread > 0) {
            $str .= '<div class="overview socialforum"><div class="name">' . $strforum . ': <a title="' . $strforum . '" href="' . $CFG->wwwroot . '/mod/socialforum/view.php?f=' . $socialforum->id . '">' .
                    $socialforum->name . '</a></div>';
            $str .= '<div class="info"><span class="postsincelogin">';
            $str .= get_string('overviewnumpostssince', 'socialforum', $count) . "</span>";
            if (!empty($showunread)) {
                $str .= '<div class="unreadposts">' . get_string('overviewnumunread', 'socialforum', $thisunread) . '</div>';
            }
            $str .= '</div></div>';
        }
        if (!empty($str)) {
            if (!array_key_exists($socialforum->course, $htmlarray)) {
                $htmlarray[$socialforum->course] = array();
            }
            if (!array_key_exists('socialforum', $htmlarray[$socialforum->course])) {
                $htmlarray[$socialforum->course]['socialforum'] = ''; // initialize, avoid warnings
            }
            $htmlarray[$socialforum->course]['socialforum'] .= $str;
        }
    }
}

/**
 * Given a course and a date, prints a summary of all the new
 * messages posted in the course since that date
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $course
 * @param bool $viewfullnames capability
 * @param int $timestart
 * @return bool success
 */
function socialforum_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;

    // do not use log table if possible, it may be huge and is expensive to join with other tables

    $allnamefields = user_picture::fields('u', null, 'duserid');
    if (!$posts = $DB->get_records_sql("SELECT p.*, f.type AS socialforumtype, d.socialforum, d.groupid,
                                              d.timestart, d.timeend, $allnamefields
                                         FROM {socialforum_posts} p
                                              JOIN {socialforum_discussions} d ON d.id = p.discussion
                                              JOIN {socialforum} f             ON f.id = d.socialforum
                                              JOIN {user} u              ON u.id = p.userid
                                        WHERE p.created > ? AND f.course = ?
                                     ORDER BY p.id ASC", array($timestart, $course->id))) { // order by initial posting date
        return false;
    }

    $modinfo = get_fast_modinfo($course);

    $groupmodes = array();
    $cms = array();

    $strftimerecent = get_string('strftimerecent');

    $printposts = array();
    foreach ($posts as $post) {
        if (!isset($modinfo->instances['socialforum'][$post->socialforum])) {
            // not visible
            continue;
        }
        $cm = $modinfo->instances['socialforum'][$post->socialforum];
        if (!$cm->uservisible) {
            continue;
        }
        $context = context_module::instance($cm->id);

        if (!has_capability('mod/socialforum:viewdiscussion', $context)) {
            continue;
        }

        if (!empty($CFG->socialforum_enabletimedposts) and $USER->id != $post->duserid
                and ( ($post->timestart > 0 and $post->timestart > time()) or ( $post->timeend > 0 and $post->timeend < time()))) {
            if (!has_capability('mod/socialforum:viewhiddentimedposts', $context)) {
                continue;
            }
        }

        // Check that the user can see the discussion.
        if (socialforum_is_user_group_discussion($cm, $post->groupid)) {
            $printposts[] = $post;
        }
    }
    unset($posts);

    if (!$printposts) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newsocialforumposts', 'mod_socialforum') . ':', 3);
    echo "\n<ul class='unlist'>\n";

    foreach ($printposts as $post) {
        $subjectclass = empty($post->parent) ? ' bold' : '';

        echo '<li><div class="head">' .
        '<div class="date">' . userdate($post->modified, $strftimerecent) . '</div>' .
        '<div class="name">' . fullname($post, $viewfullnames) . '</div>' .
        '</div>';
        echo '<div class="info' . $subjectclass . '">';
        if (empty($post->parent)) {
            echo '"<a href="' . $CFG->wwwroot . '/mod/socialforum/discuss.php?d=' . $post->discussion . '">';
        } else {
            echo '"<a href="' . $CFG->wwwroot . '/mod/socialforum/discuss.php?d=' . $post->discussion . '&amp;parent=' . $post->parent . '#p' . $post->id . '">';
        }
        $post->subject = break_up_long_words(format_string($post->subject, true));
        echo $post->subject;
        echo "</a>\"</div></li>\n";
    }

    echo "</ul>\n";

    return true;
}

/**
 * Return grade for given user or all users.
 *
 * @global object
 * @global object
 * @param object $socialforum
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function socialforum_get_user_grades($socialforum, $userid = 0) {
    global $CFG;

    require_once($CFG->dirroot . '/rating/lib.php');

    $ratingoptions = new stdClass;
    $ratingoptions->component = 'mod_socialforum';
    $ratingoptions->ratingarea = 'post';

    //need these to work backwards to get a context id. Is there a better way to get contextid from a module instance?
    $ratingoptions->modulename = 'socialforum';
    $ratingoptions->moduleid = $socialforum->id;
    $ratingoptions->userid = $userid;
    $ratingoptions->aggregationmethod = $socialforum->assessed;
    $ratingoptions->scaleid = $socialforum->scale;
    $ratingoptions->itemtable = 'socialforum_posts';
    $ratingoptions->itemtableusercolumn = 'userid';

    $rm = new rating_manager();
    return $rm->get_user_grades($ratingoptions);
}

/**
 * Update activity grades
 *
 * @category grade
 * @param object $socialforum
 * @param int $userid specific user only, 0 means all
 * @param boolean $nullifnone return null if grade does not exist
 * @return void
 */
function socialforum_update_grades($socialforum, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if (!$socialforum->assessed) {
        socialforum_grade_item_update($socialforum);
    } else if ($grades = socialforum_get_user_grades($socialforum, $userid)) {
        socialforum_grade_item_update($socialforum, $grades);
    } else if ($userid and $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = NULL;
        socialforum_grade_item_update($socialforum, $grade);
    } else {
        socialforum_grade_item_update($socialforum);
    }
}

/**
 * Create/update grade item for given social forum
 *
 * @category grade
 * @uses GRADE_TYPE_NONE
 * @uses GRADE_TYPE_VALUE
 * @uses GRADE_TYPE_SCALE
 * @param stdClass $socialforum Forum object with extra cmidnumber
 * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok
 */
function socialforum_grade_item_update($socialforum, $grades = NULL) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir . '/gradelib.php');
    }

    $params = array('itemname' => $socialforum->name, 'idnumber' => $socialforum->cmidnumber);

    if (!$socialforum->assessed or $socialforum->scale == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;
    } else if ($socialforum->scale > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $socialforum->scale;
        $params['grademin'] = 0;
    } else if ($socialforum->scale < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid'] = -$socialforum->scale;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/socialforum', $socialforum->course, 'mod', 'socialforum', $socialforum->id, 0, $grades, $params);
}

/**
 * Delete grade item for given social forum
 *
 * @category grade
 * @param stdClass $socialforum Forum object
 * @return grade_item
 */
function socialforum_grade_item_delete($socialforum) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/socialforum', $socialforum->course, 'mod', 'socialforum', $socialforum->id, 0, NULL, array('deleted' => 1));
}

/**
 * This function returns if a scale is being used by one social forum
 *
 * @global object
 * @param int $socialforumid
 * @param int $scaleid negative number
 * @return bool
 */
function socialforum_scale_used($socialforumid, $scaleid) {
    global $DB;
    $return = false;

    $rec = $DB->get_record("socialforum", array("id" => "$socialforumid", "scale" => "-$scaleid"));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of social forum
 *
 * This is used to find out if scale used anywhere
 *
 * @global object
 * @param $scaleid int
 * @return boolean True if the scale is used by any social forum
 */
function socialforum_scale_used_anywhere($scaleid) {
    global $DB;
    if ($scaleid and $DB->record_exists('socialforum', array('scale' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

// SQL FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Gets a post with all info ready for socialforum_print_post
 * Most of these joins are just to get the social forum id
 *
 * @global object
 * @global object
 * @param int $postid
 * @return mixed array of posts or false
 */
function socialforum_get_post_full($postid) {
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_record_sql("SELECT p.*, d.socialforum, $allnames, u.email, u.picture, u.imagealt
                             FROM {socialforum_posts} p
                                  JOIN {socialforum_discussions} d ON p.discussion = d.id
                                  LEFT JOIN {user} u ON p.userid = u.id
                            WHERE p.id = ?", array($postid));
}

/**
 * Gets all posts in discussion including top parent.
 *
 * @global object
 * @global object
 * @global object
 * @param int $discussionid
 * @param string $sort
 * @param bool $tracking does user track the social forum?
 * @return array of posts
 */
function socialforum_get_all_discussion_posts($discussionid, $sort, $tracking = false) {
    global $CFG, $DB, $USER;

    $tr_sel = "";
    $tr_join = "";
    $params = array();

    if ($tracking) {
        $tr_sel = ", fr.id AS postread";
        $tr_join = "LEFT JOIN {socialforum_read} fr ON (fr.postid = p.id AND fr.userid = ?)";
        $params[] = $USER->id;
    }

    $allnames = get_all_user_name_fields(true, 'u');
    $params[] = $discussionid;
    if (!$posts = $DB->get_records_sql("SELECT p.*, $allnames, u.email, u.picture, u.imagealt $tr_sel
                                     FROM {socialforum_posts} p
                                          LEFT JOIN {user} u ON p.userid = u.id
                                          $tr_join
                                    WHERE p.discussion = ?
                                 ORDER BY $sort", $params)) {
        return array();
    }

    foreach ($posts as $pid => $p) {
        if ($tracking) {
            if (socialforum_tp_is_post_old($p)) {
                $posts[$pid]->postread = true;
            }
        }
        if (!$p->parent) {
            continue;
        }
        if (!isset($posts[$p->parent])) {
            continue; // parent does not exist??
        }
        if (!isset($posts[$p->parent]->children)) {
            $posts[$p->parent]->children = array();
        }
        $posts[$p->parent]->children[$pid] = & $posts[$pid];
    }

    // Start with the last child of the first post.
    $post = &$posts[reset($posts)->id];

    $lastpost = false;
    while (!$lastpost) {
        if (!isset($post->children)) {
            $post->lastpost = true;
            $lastpost = true;
        } else {
            // Go to the last child of this post.
            $post = &$posts[end($post->children)->id];
        }
    }

    return $posts;
}

/**
 * An array of social forum objects that the user is allowed to read/search through.
 *
 * @global object
 * @global object
 * @global object
 * @param int $userid
 * @param int $courseid if 0, we look for social forums throughout the whole site.
 * @return array of social forum objects, or false if no matches
 *         Forum objects have the following attributes:
 *         id, type, course, cmid, cmvisible, cmgroupmode, accessallgroups,
 *         viewhiddentimedposts
 */
function socialforum_get_readable_socialforums($userid, $courseid = 0) {

    global $CFG, $DB, $USER;
    require_once($CFG->dirroot . '/course/lib.php');

    if (!$socialforummod = $DB->get_record('modules', array('name' => 'socialforum'))) {
        print_error('notinstalled', 'socialforum');
    }

    if ($courseid) {
        $courses = $DB->get_records('course', array('id' => $courseid));
    } else {
        // If no course is specified, then the user can see SITE + his courses.
        $courses1 = $DB->get_records('course', array('id' => SITEID));
        $courses2 = enrol_get_users_courses($userid, true, array('modinfo'));
        $courses = array_merge($courses1, $courses2);
    }
    if (!$courses) {
        return array();
    }

    $readableforums = array();

    foreach ($courses as $course) {

        $modinfo = get_fast_modinfo($course);

        if (empty($modinfo->instances['socialforum'])) {
            // hmm, no social forums?
            continue;
        }

        $courseforums = $DB->get_records('socialforum', array('course' => $course->id));

        foreach ($modinfo->instances['socialforum'] as $socialforumid => $cm) {
            if (!$cm->uservisible or ! isset($courseforums[$socialforumid])) {
                continue;
            }
            $context = context_module::instance($cm->id);
            $socialforum = $courseforums[$socialforumid];
            $socialforum->context = $context;
            $socialforum->cm = $cm;

            if (!has_capability('mod/socialforum:viewdiscussion', $context)) {
                continue;
            }

            /// group access
            if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS and ! has_capability('moodle/site:accessallgroups', $context)) {

                $socialforum->onlygroups = $modinfo->get_groups($cm->groupingid);
                $socialforum->onlygroups[] = -1;
            }

            /// hidden timed discussions
            $socialforum->viewhiddentimedposts = true;
            if (!empty($CFG->socialforum_enabletimedposts)) {
                if (!has_capability('mod/socialforum:viewhiddentimedposts', $context)) {
                    $socialforum->viewhiddentimedposts = false;
                }
            }

            $readableforums[$socialforum->id] = $socialforum;
        }

        unset($modinfo);
    } // End foreach $courses

    return $readableforums;
}

/**
 * Returns a list of posts found using an array of search terms.
 *
 * @global object
 * @global object
 * @global object
 * @param array $searchterms array of search terms, e.g. word +word -word
 * @param int $courseid if 0, we search through the whole site
 * @param int $limitfrom
 * @param int $limitnum
 * @param int &$totalcount
 * @param string $extrasql
 * @return array|bool Array of posts found or false
 */
function socialforum_search_posts($searchterms, $courseid, $limitfrom, $limitnum, &$totalcount, $extrasql = '') {
    global $CFG, $DB, $USER;
    require_once($CFG->libdir . '/searchlib.php');

    $socialforums = socialforum_get_readable_socialforums($USER->id, $courseid);

    if (count($socialforums) == 0) {
        $totalcount = 0;
        return false;
    }

    $now = round(time(), -2); // db friendly

    $fullaccess = array();
    $where = array();
    $params = array();

    foreach ($socialforums as $socialforumid => $socialforum) {
        $select = array();

        if (!$socialforum->viewhiddentimedposts) {
            $select[] = "(d.userid = :userid{$socialforumid} OR (d.timestart < :timestart{$socialforumid} AND (d.timeend = 0 OR d.timeend > :timeend{$socialforumid})))";
            $params = array_merge($params, array('userid' . $socialforumid => $USER->id, 'timestart' . $socialforumid => $now, 'timeend' . $socialforumid => $now));
        }

        $cm = $socialforum->cm;
        $context = $socialforum->context;

        if (!empty($socialforum->onlygroups)) {
            list($groupid_sql, $groupid_params) = $DB->get_in_or_equal($socialforum->onlygroups, SQL_PARAMS_NAMED, 'grps' . $socialforumid . '_');
            $params = array_merge($params, $groupid_params);
            $select[] = "d.groupid $groupid_sql";
        }

        if ($select) {
            $selects = implode(" AND ", $select);
            $where[] = "(d.socialforum = :socialforum{$socialforumid} AND $selects)";
            $params['socialforum' . $socialforumid] = $socialforumid;
        } else {
            $fullaccess[] = $socialforumid;
        }
    }

    if ($fullaccess) {
        list($fullid_sql, $fullid_params) = $DB->get_in_or_equal($fullaccess, SQL_PARAMS_NAMED, 'fula');
        $params = array_merge($params, $fullid_params);
        $where[] = "(d.socialforum $fullid_sql)";
    }

    $selectdiscussion = "(" . implode(" OR ", $where) . ")";

    $messagesearch = '';
    $searchstring = '';

    // Need to concat these back together for parser to work.
    foreach ($searchterms as $searchterm) {
        if ($searchstring != '') {
            $searchstring .= ' ';
        }
        $searchstring .= $searchterm;
    }

    // We need to allow quoted strings for the search. The quotes *should* be stripped
    // by the parser, but this should be examined carefully for security implications.
    $searchstring = str_replace("\\\"", "\"", $searchstring);
    $parser = new search_parser();
    $lexer = new search_lexer($parser);

    if ($lexer->parse($searchstring)) {
        $parsearray = $parser->get_parsed_array();
        list($messagesearch, $msparams) = search_generate_SQL($parsearray, 'p.message', 'p.subject', 'p.userid', 'u.id', 'u.firstname', 'u.lastname', 'p.modified', 'd.socialforum');
        $params = array_merge($params, $msparams);
    }

    $fromsql = "{socialforum_posts} p,
                  {socialforum_discussions} d,
                  {user} u";

    $selectsql = " $messagesearch
               AND p.discussion = d.id
               AND p.userid = u.id
               AND $selectdiscussion
                   $extrasql";

    $countsql = "SELECT COUNT(*)
                   FROM $fromsql
                  WHERE $selectsql";

    $allnames = get_all_user_name_fields(true, 'u');
    $searchsql = "SELECT p.*,
                         d.socialforum,
                         $allnames,
                         u.email,
                         u.picture,
                         u.imagealt
                    FROM $fromsql
                   WHERE $selectsql
                ORDER BY p.modified DESC";

    $totalcount = $DB->count_records_sql($countsql, $params);

    return $DB->get_records_sql($searchsql, $params, $limitfrom, $limitnum);
}

/**
 * Returns a list of all new posts that have not been mailed yet
 *
 * @param int $starttime posts created after this time
 * @param int $endtime posts created before this
 * @param int $now used for timed discussions only
 * @return array
 */
function socialforum_get_unmailed_posts($starttime, $endtime, $now = null) {
    global $CFG, $DB;

    $params = array();
    $params['mailed'] = SOCIALFORUM_MAILED_PENDING;
    $params['ptimestart'] = $starttime;
    $params['ptimeend'] = $endtime;
    $params['mailnow'] = 1;

    if (!empty($CFG->socialforum_enabletimedposts)) {
        if (empty($now)) {
            $now = time();
        }
        $selectsql = "AND (p.created >= :ptimestart OR d.timestart >= :pptimestart)";
        $params['pptimestart'] = $starttime;
        $timedsql = "AND (d.timestart < :dtimestart AND (d.timeend = 0 OR d.timeend > :dtimeend))";
        $params['dtimestart'] = $now;
        $params['dtimeend'] = $now;
    } else {
        $timedsql = "";
        $selectsql = "AND p.created >= :ptimestart";
    }

    return $DB->get_records_sql("SELECT p.*, d.course, d.socialforum
                                 FROM {socialforum_posts} p
                                 JOIN {socialforum_discussions} d ON d.id = p.discussion
                                 WHERE p.mailed = :mailed
                                 $selectsql
                                 AND (p.created < :ptimeend OR p.mailnow = :mailnow)
                                 $timedsql
                                 ORDER BY p.modified ASC", $params);
}

/**
 * Marks posts before a certain time as being mailed already
 *
 * @global object
 * @global object
 * @param int $endtime
 * @param int $now Defaults to time()
 * @return bool
 */
function socialforum_mark_old_posts_as_mailed($endtime, $now = null) {
    global $CFG, $DB;

    if (empty($now)) {
        $now = time();
    }

    $params = array();
    $params['mailedsuccess'] = SOCIALFORUM_MAILED_SUCCESS;
    $params['now'] = $now;
    $params['endtime'] = $endtime;
    $params['mailnow'] = 1;
    $params['mailedpending'] = SOCIALFORUM_MAILED_PENDING;

    if (empty($CFG->socialforum_enabletimedposts)) {
        return $DB->execute("UPDATE {socialforum_posts}
                             SET mailed = :mailedsuccess
                             WHERE (created < :endtime OR mailnow = :mailnow)
                             AND mailed = :mailedpending", $params);
    } else {
        return $DB->execute("UPDATE {socialforum_posts}
                             SET mailed = :mailedsuccess
                             WHERE discussion NOT IN (SELECT d.id
                                                      FROM {socialforum_discussions} d
                                                      WHERE d.timestart > :now)
                             AND (created < :endtime OR mailnow = :mailnow)
                             AND mailed = :mailedpending", $params);
    }
}

/**
 * Get all the posts for a user in a social forum suitable for socialforum_print_post
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @return array
 */
function socialforum_get_user_posts($socialforumid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($socialforumid, $userid);

    if (!empty($CFG->socialforum_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('socialforum', $socialforumid);
        if (!has_capability('mod/socialforum:viewhiddentimedposts', context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, d.socialforum, $allnames, u.email, u.picture, u.imagealt
                              FROM {socialforum} f
                                   JOIN {socialforum_discussions} d ON d.socialforum = f.id
                                   JOIN {socialforum_posts} p       ON p.discussion = d.id
                                   JOIN {user} u              ON u.id = p.userid
                             WHERE f.id = ?
                                   AND p.userid = ?
                                   $timedsql
                          ORDER BY p.modified ASC", $params);
}

/**
 * Get all the discussions user participated in
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param int $socialforumid
 * @param int $userid
 * @return array Array or false
 */
function socialforum_get_user_involved_discussions($socialforumid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($socialforumid, $userid);
    if (!empty($CFG->socialforum_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('socialforum', $socialforumid);
        if (!has_capability('mod/socialforum:viewhiddentimedposts', context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB->get_records_sql("SELECT DISTINCT d.*
                              FROM {socialforum} f
                                   JOIN {socialforum_discussions} d ON d.socialforum = f.id
                                   JOIN {socialforum_posts} p       ON p.discussion = d.id
                             WHERE f.id = ?
                                   AND p.userid = ?
                                   $timedsql", $params);
}

/**
 * Get all the posts for a user in a social forum suitable for socialforum_print_post
 *
 * @global object
 * @global object
 * @param int $socialforumid
 * @param int $userid
 * @return array of counts or false
 */
function socialforum_count_user_posts($socialforumid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($socialforumid, $userid);
    if (!empty($CFG->socialforum_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('socialforum', $socialforumid);
        if (!has_capability('mod/socialforum:viewhiddentimedposts', context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB->get_record_sql("SELECT COUNT(p.id) AS postcount, MAX(p.modified) AS lastpost
                             FROM {socialforum} f
                                  JOIN {socialforum_discussions} d ON d.socialforum = f.id
                                  JOIN {socialforum_posts} p       ON p.discussion = d.id
                                  JOIN {user} u              ON u.id = p.userid
                            WHERE f.id = ?
                                  AND p.userid = ?
                                  $timedsql", $params);
}

/**
 * Given a log entry, return the social forum post details for it.
 *
 * @global object
 * @global object
 * @param object $log
 * @return array|null
 */
function socialforum_get_post_from_log($log) {
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    if ($log->action == "add post") {

        return $DB->get_record_sql("SELECT p.*, f.type AS socialforumtype, d.socialforum, d.groupid, $allnames, u.email, u.picture
                                 FROM {socialforum_discussions} d,
                                      {socialforum_posts} p,
                                      {socialforum} f,
                                      {user} u
                                WHERE p.id = ?
                                  AND d.id = p.discussion
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.socialforum", array($log->info));
    } else if ($log->action == "add discussion") {

        return $DB->get_record_sql("SELECT p.*, f.type AS socialforumtype, d.socialforum, d.groupid, $allnames, u.email, u.picture
                                 FROM {socialforum_discussions} d,
                                      {socialforum_posts} p,
                                      {socialforum} f,
                                      {user} u
                                WHERE d.id = ?
                                  AND d.firstpost = p.id
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.socialforum", array($log->info));
    }
    return NULL;
}

/**
 * Given a discussion id, return the first post from the discussion
 *
 * @global object
 * @global object
 * @param int $dicsussionid
 * @return array
 */
function socialforum_get_firstpost_from_discussion($discussionid) {
    global $CFG, $DB;

    return $DB->get_record_sql("SELECT p.*
                             FROM {socialforum_discussions} d,
                                  {socialforum_posts} p
                            WHERE d.id = ?
                              AND d.firstpost = p.id ", array($discussionid));
}

/**
 * Returns an array of counts of replies to each discussion
 *
 * @global object
 * @global object
 * @param int $socialforumid
 * @param string $socialforumsort
 * @param int $limit
 * @param int $page
 * @param int $perpage
 * @return array
 */
function socialforum_count_discussion_replies($socialforumid, $socialforumsort = "", $limit = -1, $page = -1, $perpage = 0) {
    global $CFG, $DB;

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum = $limit;
    } else if ($page != -1) {
        $limitfrom = $page * $perpage;
        $limitnum = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum = 0;
    }

    if ($socialforumsort == "") {
        $orderby = "";
        $groupby = "";
    } else {
        $orderby = "ORDER BY $socialforumsort";
        $groupby = ", " . strtolower($socialforumsort);
        $groupby = str_replace('desc', '', $groupby);
        $groupby = str_replace('asc', '', $groupby);
    }

    if (($limitfrom == 0 and $limitnum == 0) or $socialforumsort == "") {
        $sql = "SELECT p.discussion, COUNT(p.id) AS replies, MAX(p.id) AS lastpostid
                  FROM {socialforum_posts} p
                       JOIN {socialforum_discussions} d ON p.discussion = d.id
                 WHERE p.parent > 0 AND d.socialforum = ?
              GROUP BY p.discussion";
        return $DB->get_records_sql($sql, array($socialforumid));
    } else {
        $sql = "SELECT p.discussion, (COUNT(p.id) - 1) AS replies, MAX(p.id) AS lastpostid
                  FROM {socialforum_posts} p
                       JOIN {socialforum_discussions} d ON p.discussion = d.id
                 WHERE d.socialforum = ?
              GROUP BY p.discussion $groupby $orderby";
        return $DB->get_records_sql($sql, array($socialforumid), $limitfrom, $limitnum);
    }
}

/**
 * @global object
 * @global object
 * @global object
 * @staticvar array $cache
 * @param object $socialforum
 * @param object $cm
 * @param object $course
 * @return mixed
 */
function socialforum_count_discussions($socialforum, $cm, $course) {
    global $CFG, $DB, $USER;

    static $cache = array();

    $now = round(time(), -2); // db cache friendliness

    $params = array($course->id);

    if (!isset($cache[$course->id])) {
        if (!empty($CFG->socialforum_enabletimedposts)) {
            $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
            $params[] = $now;
            $params[] = $now;
        } else {
            $timedsql = "";
        }

        $sql = "SELECT f.id, COUNT(d.id) as dcount
                  FROM {socialforum} f
                       JOIN {socialforum_discussions} d ON d.socialforum = f.id
                 WHERE f.course = ?
                       $timedsql
              GROUP BY f.id";

        if ($counts = $DB->get_records_sql($sql, $params)) {
            foreach ($counts as $count) {
                $counts[$count->id] = $count->dcount;
            }
            $cache[$course->id] = $counts;
        } else {
            $cache[$course->id] = array();
        }
    }

    if (empty($cache[$course->id][$socialforum->id])) {
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $cache[$course->id][$socialforum->id];
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
        return $cache[$course->id][$socialforum->id];
    }

    require_once($CFG->dirroot . '/course/lib.php');

    $modinfo = get_fast_modinfo($course);

    $mygroups = $modinfo->get_groups($cm->groupingid);

    // add all groups posts
    $mygroups[-1] = -1;

    list($mygroups_sql, $params) = $DB->get_in_or_equal($mygroups);
    $params[] = $socialforum->id;

    if (!empty($CFG->socialforum_enabletimedposts)) {
        $timedsql = "AND d.timestart < $now AND (d.timeend = 0 OR d.timeend > $now)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    $sql = "SELECT COUNT(d.id)
              FROM {socialforum_discussions} d
             WHERE d.groupid $mygroups_sql AND d.socialforum = ?
                   $timedsql";

    return $DB->get_field_sql($sql, $params);
}

/**
 * Get all discussions in a social forum
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @param string $socialforumsort
 * @param bool $fullpost
 * @param int $unused
 * @param int $limit
 * @param bool $userlastmodified
 * @param int $page
 * @param int $perpage
 * @param int $groupid if groups enabled, get discussions for this group overriding the current group.
 *                     Use SOCIALFORUM_POSTS_ALL_USER_GROUPS for all the user groups
 * @return array
 */
function socialforum_get_discussions($cm, $socialforumsort = "", $fullpost = true, $unused = -1, $limit = -1, $userlastmodified = false, $page = -1, $perpage = 0, $groupid = -1) {
    global $CFG, $DB, $USER;

    $timelimit = '';

    $now = round(time(), -2);
    $params = array($cm->instance);

    $modcontext = context_module::instance($cm->id);

    if (!has_capability('mod/socialforum:viewdiscussion', $modcontext)) { /// User must have perms to view discussions
        return array();
    }

    if (!empty($CFG->socialforum_enabletimedposts)) { /// Users must fulfill timed posts
        if (!has_capability('mod/socialforum:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= " OR d.userid = ?";
                $params[] = $USER->id;
            }
            $timelimit .= ")";
        }
    }

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum = $limit;
    } else if ($page != -1) {
        $limitfrom = $page * $perpage;
        $limitnum = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum = 0;
    }

    $groupmode = groups_get_activity_groupmode($cm);

    if ($groupmode) {

        if (empty($modcontext)) {
            $modcontext = context_module::instance($cm->id);
        }

        // Special case, we received a groupid to override currentgroup.
        if ($groupid > 0) {
            $course = get_course($cm->course);
            if (!groups_group_visible($groupid, $course, $cm)) {
                // User doesn't belong to this group, return nothing.
                return array();
            }
            $currentgroup = $groupid;
        } else if ($groupid === -1) {
            $currentgroup = groups_get_activity_group($cm);
        } else {
            // Get discussions for all groups current user can see.
            $currentgroup = null;
        }

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "";
            }
        } else {
            // Separate groups.
            // Get discussions for all groups current user can see.
            if ($currentgroup === null) {
                $mygroups = array_keys(groups_get_all_groups($cm->course, $USER->id, $cm->groupingid, 'g.id'));
                if (empty($mygroups)) {
                    $groupselect = "AND d.groupid = -1";
                } else {
                    list($insqlgroups, $inparamsgroups) = $DB->get_in_or_equal($mygroups);
                    $groupselect = "AND (d.groupid = -1 OR d.groupid $insqlgroups)";
                    $params = array_merge($params, $inparamsgroups);
                }
            } else if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }
    if (empty($socialforumsort)) {
        $socialforumsort = socialforum_get_default_sort_order();
    }
    if (empty($fullpost)) {
        $postdata = "p.id,p.subject,p.modified,p.discussion,p.userid";
    } else {
        $postdata = "p.*";
    }

    if (empty($userlastmodified)) {  // We don't need to know this
        $umfields = "";
        $umtable = "";
    } else {
        $umfields = ', ' . get_all_user_name_fields(true, 'um', null, 'um') . ', um.email AS umemail, um.picture AS umpicture,
                        um.imagealt AS umimagealt';
        $umtable = " LEFT JOIN {user} um ON (d.usermodified = um.id)";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    $sql = "SELECT $postdata, d.name, d.timemodified, d.usermodified, d.groupid, d.timestart, d.timeend, d.pinned, $allnames,
                   u.email, u.picture, u.imagealt $umfields
              FROM {socialforum_discussions} d
                   JOIN {socialforum_posts} p ON p.discussion = d.id
                   JOIN {user} u ON p.userid = u.id
                   $umtable
             WHERE d.socialforum = ? AND p.parent = 0
                   $timelimit $groupselect
          ORDER BY $socialforumsort, d.id DESC";
    return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
}

/**
 * Gets the neighbours (previous and next) of a discussion.
 *
 * The calculation is based on the timemodified when time modified or time created is identical
 * It will revert to using the ID to sort consistently. This is better tha skipping a discussion.
 *
 * For blog-style social forums, the calculation is based on the original creation time of the
 * blog post.
 *
 * Please note that this does not check whether or not the discussion passed is accessible
 * by the user, it simply uses it as a reference to find the neighbours. On the other hand,
 * the returned neighbours are checked and are accessible to the current user.
 *
 * @param object $cm The CM record.
 * @param object $discussion The discussion record.
 * @param object $socialforum The social forum instance record.
 * @return array That always contains the keys 'prev' and 'next'. When there is a result
 *               they contain the record with minimal information such as 'id' and 'name'.
 *               When the neighbour is not found the value is false.
 */
function socialforum_get_discussion_neighbours($cm, $discussion, $socialforum) {
    global $CFG, $DB, $USER;

    if ($cm->instance != $discussion->socialforum or $discussion->socialforum != $socialforum->id or $socialforum->id != $cm->instance) {
        throw new coding_exception('Discussion is not part of the same social forum.');
    }

    $neighbours = array('prev' => false, 'next' => false);
    $now = round(time(), -2);
    $params = array();

    $modcontext = context_module::instance($cm->id);
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    // Users must fulfill timed posts.
    $timelimit = '';
    if (!empty($CFG->socialforum_enabletimedposts)) {
        if (!has_capability('mod/socialforum:viewhiddentimedposts', $modcontext)) {
            $timelimit = ' AND ((d.timestart <= :tltimestart AND (d.timeend = 0 OR d.timeend > :tltimeend))';
            $params['tltimestart'] = $now;
            $params['tltimeend'] = $now;
            if (isloggedin()) {
                $timelimit .= ' OR d.userid = :tluserid';
                $params['tluserid'] = $USER->id;
            }
            $timelimit .= ')';
        }
    }

    // Limiting to posts accessible according to groups.
    $groupselect = '';
    if ($groupmode) {
        if ($groupmode == VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = :groupid OR d.groupid = -1)';
                $params['groupid'] = $currentgroup;
            }
        } else {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = :groupid OR d.groupid = -1)';
                $params['groupid'] = $currentgroup;
            } else {
                $groupselect = 'AND d.groupid = -1';
            }
        }
    }

    $params['socialforumid'] = $cm->instance;
    $params['discid1'] = $discussion->id;
    $params['discid2'] = $discussion->id;
    $params['discid3'] = $discussion->id;
    $params['discid4'] = $discussion->id;
    $params['disctimecompare1'] = $discussion->timemodified;
    $params['disctimecompare2'] = $discussion->timemodified;
    $params['pinnedstate1'] = (int) $discussion->pinned;
    $params['pinnedstate2'] = (int) $discussion->pinned;
    $params['pinnedstate3'] = (int) $discussion->pinned;
    $params['pinnedstate4'] = (int) $discussion->pinned;

    $sql = "SELECT d.id, d.name, d.timemodified, d.groupid, d.timestart, d.timeend
              FROM {socialforum_discussions} d
              JOIN {socialforum_posts} p ON d.firstpost = p.id
             WHERE d.socialforum = :socialforumid
               AND d.id <> :discid1
                   $timelimit
                   $groupselect";
    $comparefield = "d.timemodified";
    $comparevalue = ":disctimecompare1";
    $comparevalue2 = ":disctimecompare2";
    if (!empty($CFG->socialforum_enabletimedposts)) {
        // Here we need to take into account the release time (timestart)
        // if one is set, of the neighbouring posts and compare it to the
        // timestart or timemodified of *this* post depending on if the
        // release date of this post is in the future or not.
        // This stops discussions that appear later because of the
        // timestart value from being buried under discussions that were
        // made afterwards.
        $comparefield = "CASE WHEN d.timemodified < d.timestart
                                THEN d.timestart ELSE d.timemodified END";
        if ($discussion->timemodified < $discussion->timestart) {
            // Normally we would just use the timemodified for sorting
            // discussion posts. However, when timed discussions are enabled,
            // then posts need to be sorted base on the later of timemodified
            // or the release date of the post (timestart).
            $params['disctimecompare1'] = $discussion->timestart;
            $params['disctimecompare2'] = $discussion->timestart;
        }
    }
    $orderbydesc = socialforum_get_default_sort_order(true, $comparefield, 'd', false);
    $orderbyasc = socialforum_get_default_sort_order(false, $comparefield, 'd', false);

    if ($socialforum->type === 'blog') {
        $subselect = "SELECT pp.created
                   FROM {socialforum_discussions} dd
                   JOIN {socialforum_posts} pp ON dd.firstpost = pp.id ";

        $subselectwhere1 = " WHERE dd.id = :discid3";
        $subselectwhere2 = " WHERE dd.id = :discid4";

        $comparefield = "p.created";

        $sub1 = $subselect . $subselectwhere1;
        $comparevalue = "($sub1)";

        $sub2 = $subselect . $subselectwhere2;
        $comparevalue2 = "($sub2)";

        $orderbydesc = "d.pinned, p.created DESC";
        $orderbyasc = "d.pinned, p.created ASC";
    }

    $prevsql = $sql . " AND ( (($comparefield < $comparevalue) AND :pinnedstate1 = d.pinned)
                         OR ($comparefield = $comparevalue2 AND (d.pinned = 0 OR d.pinned = :pinnedstate4) AND d.id < :discid2)
                         OR (d.pinned = 0 AND d.pinned <> :pinnedstate2))
                   ORDER BY CASE WHEN d.pinned = :pinnedstate3 THEN 1 ELSE 0 END DESC, $orderbydesc, d.id DESC";

    $nextsql = $sql . " AND ( (($comparefield > $comparevalue) AND :pinnedstate1 = d.pinned)
                         OR ($comparefield = $comparevalue2 AND (d.pinned = 1 OR d.pinned = :pinnedstate4) AND d.id > :discid2)
                         OR (d.pinned = 1 AND d.pinned <> :pinnedstate2))
                   ORDER BY CASE WHEN d.pinned = :pinnedstate3 THEN 1 ELSE 0 END DESC, $orderbyasc, d.id ASC";

    $neighbours['prev'] = $DB->get_record_sql($prevsql, $params, IGNORE_MULTIPLE);
    $neighbours['next'] = $DB->get_record_sql($nextsql, $params, IGNORE_MULTIPLE);
    return $neighbours;
}

/**
 * Get the sql to use in the ORDER BY clause for social forum discussions.
 *
 * This has the ordering take timed discussion windows into account.
 *
 * @param bool $desc True for DESC, False for ASC.
 * @param string $compare The field in the SQL to compare to normally sort by.
 * @param string $prefix The prefix being used for the discussion table.
 * @param bool $pinned sort pinned posts to the top
 * @return string
 */
function socialforum_get_default_sort_order($desc = true, $compare = 'd.timemodified', $prefix = 'd', $pinned = true) {
    global $CFG;

    if (!empty($prefix)) {
        $prefix .= '.';
    }

    $dir = $desc ? 'DESC' : 'ASC';

    if ($pinned == true) {
        $pinned = "{$prefix}pinned DESC,";
    } else {
        $pinned = '';
    }

    $sort = "{$prefix}timemodified";
    if (!empty($CFG->socialforum_enabletimedposts)) {
        $sort = "CASE WHEN {$compare} < {$prefix}timestart
                 THEN {$prefix}timestart
                 ELSE {$compare}
                 END";
    }
    return "$pinned $sort $dir";
}

/**
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @return array
 */
function socialforum_get_discussions_unread($cm) {
    global $CFG, $DB, $USER;

    $now = round(time(), -2);
    $cutoffdate = $now - ($CFG->socialforum_oldpostdays * 24 * 60 * 60);

    $params = array();
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = context_module::instance($cm->id);

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = :currentgroup OR d.groupid = -1)";
                $params['currentgroup'] = $currentgroup;
            } else {
                $groupselect = "";
            }
        } else {
            //separate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = :currentgroup OR d.groupid = -1)";
                $params['currentgroup'] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }

    if (!empty($CFG->socialforum_enabletimedposts)) {
        $timedsql = "AND d.timestart < :now1 AND (d.timeend = 0 OR d.timeend > :now2)";
        $params['now1'] = $now;
        $params['now2'] = $now;
    } else {
        $timedsql = "";
    }

    $sql = "SELECT d.id, COUNT(p.id) AS unread
              FROM {socialforum_discussions} d
                   JOIN {socialforum_posts} p     ON p.discussion = d.id
                   LEFT JOIN {socialforum_read} r ON (r.postid = p.id AND r.userid = $USER->id)
             WHERE d.socialforum = {$cm->instance}
                   AND p.modified >= :cutoffdate AND r.id is NULL
                   $groupselect
                   $timedsql
          GROUP BY d.id";
    $params['cutoffdate'] = $cutoffdate;

    if ($unreads = $DB->get_records_sql($sql, $params)) {
        foreach ($unreads as $unread) {
            $unreads[$unread->id] = $unread->unread;
        }
        return $unreads;
    } else {
        return array();
    }
}

/**
 * @global object
 * @global object
 * @global object
 * @uses CONEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @return array
 */
function socialforum_get_discussions_count($cm) {
    global $CFG, $DB, $USER;

    $now = round(time(), -2);
    $params = array($cm->instance);
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = context_module::instance($cm->id);

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "";
            }
        } else {
            //seprate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }

    $timelimit = "";

    if (!empty($CFG->socialforum_enabletimedposts)) {

        $modcontext = context_module::instance($cm->id);

        if (!has_capability('mod/socialforum:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= " OR d.userid = ?";
                $params[] = $USER->id;
            }
            $timelimit .= ")";
        }
    }

    $sql = "SELECT COUNT(d.id)
              FROM {socialforum_discussions} d
                   JOIN {socialforum_posts} p ON p.discussion = d.id
             WHERE d.socialforum = ? AND p.parent = 0
                   $groupselect $timelimit";

    return $DB->get_field_sql($sql, $params);
}

// OTHER FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * @global object
 * @global object
 * @param int $courseid
 * @param string $type
 */
function socialforum_get_course_socialforum($courseid, $type) {
// How to set up special 1-per-course social forums
    global $CFG, $DB, $OUTPUT, $USER;

    if ($socialforums = $DB->get_records_select("socialforum", "course = ? AND type = ?", array($courseid, $type), "id ASC")) {
        // There should always only be ONE, but with the right combination of
        // errors there might be more.  In this case, just return the oldest one (lowest ID).
        foreach ($socialforums as $socialforum) {
            return $socialforum;   // ie the first one
        }
    }

    // Doesn't exist, so create one now.
    $socialforum = new stdClass();
    $socialforum->course = $courseid;
    $socialforum->type = "$type";
    if (!empty($USER->htmleditor)) {
        $socialforum->introformat = $USER->htmleditor;
    }
    switch ($socialforum->type) {
        case "news":
            $socialforum->name = get_string("namenews", "mod_socialforum");
            $socialforum->intro = get_string("intronews", "mod_socialforum");
            $socialforum->forcesubscribe = SOCIALFORUM_FORCESUBSCRIBE;
            $socialforum->assessed = 0;
            if ($courseid == SITEID) {
                $socialforum->name = get_string("sitenews");
                $socialforum->forcesubscribe = 0;
            }
            break;
        case "social":
            $socialforum->name = get_string("namesocial", "mod_socialforum");
            $socialforum->intro = get_string("introsocial", "mod_socialforum");
            $socialforum->assessed = 0;
            $socialforum->forcesubscribe = 0;
            break;
        case "blog":
            $socialforum->name = get_string('blogsocialforum', 'mod_socialforum');
            $socialforum->intro = get_string('introblog', 'mod_socialforum');
            $socialforum->assessed = 0;
            $socialforum->forcesubscribe = 0;
            break;
        default:
            echo $OUTPUT->notification("That social forum type doesn't exist!");
            return false;
            break;
    }

    $socialforum->timemodified = time();
    $socialforum->id = $DB->insert_record("socialforum", $socialforum);

    if (!$module = $DB->get_record("modules", array("name" => "socialforum"))) {
        echo $OUTPUT->notification("Could not find social forum module!!");
        return false;
    }
    $mod = new stdClass();
    $mod->course = $courseid;
    $mod->module = $module->id;
    $mod->instance = $socialforum->id;
    $mod->section = 0;
    include_once("$CFG->dirroot/course/lib.php");
    if (!$mod->coursemodule = add_course_module($mod)) {
        echo $OUTPUT->notification("Could not add a new course module to the course '" . $courseid . "'");
        return false;
    }
    $sectionid = course_add_cm_to_section($courseid, $mod->coursemodule, 0);
    return $DB->get_record("socialforum", array("id" => "$socialforum->id"));
}

/**
 * Print a social forum post
 *
 * @global object
 * @global object
 * @uses SOCIALFORUM_MODE_NESTED
 * @uses PORTFOLIO_FORMAT_PLAINHTML
 * @uses PORTFOLIO_FORMAT_FILE
 * @uses PORTFOLIO_FORMAT_RICHHTML
 * @uses PORTFOLIO_ADD_TEXT_LINK
 * @uses CONTEXT_MODULE
 * @param object $post The post to print.
 * @param object $discussion
 * @param object $socialforum
 * @param object $cm
 * @param object $course
 * @param boolean $ownpost Whether this post belongs to the current user.
 * @param boolean $reply Whether to print a 'reply' link at the bottom of the message.
 * @param boolean $link Just print a shortened version of the post as a link to the full post.
 * @param string $footer Extra stuff to print after the message.
 * @param string $highlight Space-separated list of terms to highlight.
 * @param int $post_read true, false or -99. If we already know whether this user
 *          has read this post, pass that in, otherwise, pass in -99, and this
 *          function will work it out.
 * @param boolean $dummyifcantsee When socialforum_user_can_see_post says that
 *          the current user can't see this post, if this argument is true
 *          (the default) then print a dummy 'you can't see this post' post.
 *          If false, don't output anything at all.
 * @param bool|null $istracked
 * @return void
 */
function socialforum_print_post($post, $discussion, $socialforum, &$cm, $course, $ownpost = false, $reply = false, $link = false, $footer = "", $highlight = "", $postisread = null, $dummyifcantsee = true, $istracked = null, $return = false) {
    global $USER, $CFG;

    echo html_writer::start_div('post card card-body');
    echo html_writer::start_div('d-flex');
    $postuser = core_user::get_user($post->userid);
    $userinitials = strtoupper(substr($postuser->firstname, 0, 1) . substr($postuser->lastname, 0, 1));
    echo html_writer::tag('span', $userinitials, array(
        'class' => 'avatar-sm avatar-title rounded-circle dark mr-12pt',
    ));
    echo html_writer::start_div('flex');
    echo html_writer::start_tag('p', array(
        'class' => 'd-flex align-items-center mb-2',
    ));
    $postusername = $postuser->firstname . ' ' . $postuser->lastname;
    echo html_writer::span(html_writer::tag('strong', $postusername), 'text-body mr-2');
    $posttime = date('Y/m/d H:i', $post->created);
    echo html_writer::tag('small', $posttime, array(
        'class' => 'text-muted',
    ));
    echo html_writer::end_tag('p');
    $modcontext = context_module::instance($cm->id);
    $posttext = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id, 'mod_socialforum', 'post', $post->id);
    echo html_writer::tag('p', $posttext, array(
        'class' => 'post-text'
    ));
    if ($reply) {
        echo html_writer::start_div('d-flex align-items-center');
        echo html_writer::tag('a', html_writer::tag('i', 'reply', array('class' => 'material-icons mr-1', 'style' => 'font-size: inherit;')) .
                get_string('reply', 'mod_socialforum'), array(
            'class' => '',
            'href' => new moodle_url('/mod/socialforum/post.php#mformforum', array('reply' => $post->id)),
        ));
        echo socialforum_print_votes($socialforum, $post, $discussion, false);
        echo html_writer::end_tag('a');
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Mark the forum post as read if required
    if ($istracked && !$CFG->forum_usermarksread && !$postisread) {
        socialforum_tp_mark_post_read($USER->id, $post);
    }
}

/**
 * Print an improper flag fot post: a red flag link if post has been considered
 * as improper by current user or a transparent flag otherwise. A clink in flag
 * toggles proper/improper choice by current user
 * 
 * @param object $post Post to be considered
 * @return string HTML representing improper flag
 */
function socialforum_print_improper_flag($post) {
    global $OUTPUT, $USER;

    // No flags if post belongs to current user
    if ($post->userid == $USER->id) {
        return '';
    }

    // Check if user has marked post as improper
    $improper = socialforum_is_improper($post->id, $USER->id);

    if ($improper) {
        $href = new moodle_url('/mod/socialforum/post.php', array('proper' => $post->id));
        $icon = $OUTPUT->pix_icon('i/flagged', get_string('unmarkasimproper', 'mod_socialforum'), '', array('class' => 'smallicon improper'));
    } else {
        $href = new moodle_url('/mod/socialforum/post.php', array('improper' => $post->id));
        $icon = $OUTPUT->pix_icon('i/unflagged', get_string('markasimproper', 'mod_socialforum'), '', array('class' => 'smallicon proper'));
    }
    $html = html_writer::tag('a', $icon, array('href' => $href));
    return $html;
}

/**
 * Check is an user has considered a post improper
 * 
 * @param int $postid Id of post to be checked
 * @param int $userid Id of user to be checked
 * @return bool True if considered improper false otherwise
 */
function socialforum_is_improper($postid) {
    global $DB, $USER;

    $record = $DB->get_record('socialforum_improper', array('postid' => $postid, 'userid' => $USER->id));

    return ($record != null);
}

/**
 * Mark a post as considered improper by an user
 * 
 * @param object $cm Course module to which post belongs
 * @param int $postid Id of post to be marked as improper
 * @param int $userid Id of user who posted
 */
function socialforum_set_improper($cm, $postid, $userid) {
    global $DB, $USER;

    $record = $DB->get_record('socialforum_improper', array('postid' => $postid, 'userid' => $USER->id));
    if (!$record) {
        $record = new stdClass();
        $record->postid = $postid;
        $record->userid = $USER->id;
        $DB->insert_record('socialforum_improper', $record);

        // Trigger event
        $params = array(
            'objectid' => $postid,
            'userid' => $USER->id,
            'courseid' => $cm->course,
            'relateduserid' => $userid,
            'context' => \context_module::instance($cm->id),
            'other' => null,
        );
        $event = \mod_socialforum\event\post_markedimproper::create($params);
        $event->trigger();
    }
}

/**
 * Unmark a post as considered improper by an user
 * 
 * @param object $cm Course module to which post belongs
 * @param int $postid Id of post to be marked as improper
 * @param int $userid Id of user who posted
 */
function socialforum_reset_improper($cm, $postid, $userid) {
    global $DB, $USER;

    $success = $DB->delete_records('socialforum_improper', array('postid' => $postid, 'userid' => $USER->id));

    if ($success) {
        // Trigger event
        $params = array(
            'objectid' => $postid,
            'userid' => $USER->id,
            'relateduserid' => $userid,
            'courseid' => $cm->course,
            'context' => \context_module::instance($cm->id),
            'other' => null,
        );
        $event = \mod_socialforum\event\post_unmarkedimproper::create($params);
        $event->trigger();
    }
}

function socialforum_print_votes($socialforum, $post, $discussion, $nolinks = false) {
    global $USER, $OUTPUT;

    if (!isloggedin()) {
        return;
    }

    // Output votes and icons for posts
    // Only first level posts can receive votes
    $votecount = null;
    if ($post->parent == $discussion->firstpost) {
        $votecount = new socialforum_votes($socialforum->id, null, $post->id, $post->userid);
    } else {
        if ($post->parent == 0) {
            $votecount = new socialforum_votes($socialforum->id, $post->discussion, null, $post->userid);
        }
    }
    if ($votecount) {

        $uservotes = $votecount->get_user_votes($USER->id);

        $voteshtml = '';
        // Do not show icons as links in own posts
        if ($post->userid != $USER->id && !$nolinks) {
            $relevantlink = new moodle_url('/mod/socialforum/post.php', array('relevant' => $post->id));
            $voteshtml .= html_writer::start_tag('a', array('href' => $relevantlink));
        }
        // Display appropriated relevant icon
        $uId = 'relevant-' . $post->id;
        if ($uservotes > 0) {
            $voteshtml .= html_writer::tag('i', 'thumb_up', array(
                        'class' => 'material-icons mr-1 ml-1',
                        'style' => 'font-size: inherit;',
            ));
        } else {
            $voteshtml .= html_writer::tag('i', 'thumb_up', array(
                        'class' => 'material-icons mr-1 ml-1',
                        'style' => 'font-size: inherit; opacity: .3;',
            ));
        }
        // Do not show icons as links in own posts
        if ($post->userid != $USER->id && !$nolinks) {
            $voteshtml .= html_writer::end_tag('a');
        }

        // Display vote count
        $voteshtml .= html_writer::start_tag('div', array('class' => 'inline numvotes'));
        $voteshtml .= $votecount->votes;
        $voteshtml .= html_writer::end_tag('div'); // numvotes
        // Do not show icons as links in own posts
        if ($post->userid != $USER->id && !$nolinks) {
            $irrelevantlink = new moodle_url('/mod/socialforum/post.php', array('irrelevant' => $post->id));
            $voteshtml .= html_writer::start_tag('a', array('href' => $irrelevantlink));
        }
        // Display appropriated irrelevant icon
        $dId = 'irrelevant-' . $post->id;
        if ($uservotes < 0) {
            $voteshtml .= html_writer::tag('i', 'thumb_down', array(
                        'class' => 'material-icons mr-1 ml-1',
                        'style' => 'font-size: inherit;',
            ));
        } else {
            $voteshtml .= html_writer::tag('i', 'thumb_down', array(
                        'class' => 'material-icons mr-1 ml-1',
                        'style' => 'font-size: inherit; opacity: .3;',
            ));
        }
        // Do not show icons as links in own posts
        if ($post->userid != $USER->id && !$nolinks) {
            $voteshtml .= html_writer::end_tag('a');
        }

        return html_writer::tag('div', $voteshtml, array('class' => 'votes'));
    }
}

/**
 * Return rating related permissions
 *
 * @param string $options the context id
 * @return array an associative array of the user's rating permissions
 */
function socialforum_rating_permissions($contextid, $component, $ratingarea) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($component != 'mod_socialforum' || $ratingarea != 'post') {
        // We don't know about this component/ratingarea so just return null to get the
        // default restrictive permissions.
        return null;
    }
    return array(
        'view' => has_capability('mod/socialforum:viewrating', $context),
        'viewany' => has_capability('mod/socialforum:viewanyrating', $context),
        'viewall' => has_capability('mod/socialforum:viewallratings', $context),
        'rate' => has_capability('mod/socialforum:rate', $context)
    );
}

/**
 * Validates a submitted rating
 * @param array $params submitted data
 *            context => object the context in which the rated items exists [required]
 *            component => The component for this module - should always be mod_socialforum [required]
 *            ratingarea => object the context in which the rated items exists [required]
 *            itemid => int the ID of the object being rated [required]
 *            scaleid => int the scale from which the user can select a rating. Used for bounds checking. [required]
 *            rating => int the submitted rating [required]
 *            rateduserid => int the id of the user whose items have been rated. NOT the user who submitted the ratings. 0 to update all. [required]
 *            aggregation => int the aggregation method to apply when calculating grades ie RATING_AGGREGATE_AVERAGE [required]
 * @return boolean true if the rating is valid. Will throw rating_exception if not
 */
function socialforum_rating_validate($params) {
    global $DB, $USER;

    // Check the component is mod_socialforum
    if ($params['component'] != 'mod_socialforum') {
        throw new rating_exception('invalidcomponent');
    }

    // Check the ratingarea is post (the only rating area in social forum)
    if ($params['ratingarea'] != 'post') {
        throw new rating_exception('invalidratingarea');
    }

    // Check the rateduserid is not the current user .. you can't rate your own posts
    if ($params['rateduserid'] == $USER->id) {
        throw new rating_exception('nopermissiontorate');
    }

    // Fetch all the related records ... we need to do this anyway to call socialforum_user_can_see_post
    $post = $DB->get_record('socialforum_posts', array('id' => $params['itemid'], 'userid' => $params['rateduserid']), '*', MUST_EXIST);
    $discussion = $DB->get_record('socialforum_discussions', array('id' => $post->discussion), '*', MUST_EXIST);
    $socialforum = $DB->get_record('socialforum', array('id' => $discussion->socialforum), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $socialforum->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $course->id, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // Make sure the context provided is the context of the social forum
    if ($context->id != $params['context']->id) {
        throw new rating_exception('invalidcontext');
    }

    if ($socialforum->scale != $params['scaleid']) {
        //the scale being submitted doesnt match the one in the database
        throw new rating_exception('invalidscaleid');
    }

    // check the item we're rating was created in the assessable time window
    if (!empty($socialforum->assesstimestart) && !empty($socialforum->assesstimefinish)) {
        if ($post->created < $socialforum->assesstimestart || $post->created > $socialforum->assesstimefinish) {
            throw new rating_exception('notavailable');
        }
    }

    //check that the submitted rating is valid for the scale
    // lower limit
    if ($params['rating'] < 0 && $params['rating'] != RATING_UNSET_RATING) {
        throw new rating_exception('invalidnum');
    }

    // upper limit
    if ($socialforum->scale < 0) {
        //its a custom scale
        $scalerecord = $DB->get_record('scale', array('id' => -$socialforum->scale));
        if ($scalerecord) {
            $scalearray = explode(',', $scalerecord->scale);
            if ($params['rating'] > count($scalearray)) {
                throw new rating_exception('invalidnum');
            }
        } else {
            throw new rating_exception('invalidscaleid');
        }
    } else if ($params['rating'] > $socialforum->scale) {
        //if its numeric and submitted rating is above maximum
        throw new rating_exception('invalidnum');
    }

    // Make sure groups allow this user to see the item they're rating
    if ($discussion->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {   // Groups are being used
        if (!groups_group_exists($discussion->groupid)) { // Can't find group
            throw new rating_exception('cannotfindgroup'); //something is wrong
        }

        if (!groups_is_member($discussion->groupid) and ! has_capability('moodle/site:accessallgroups', $context)) {
            // do not allow rating of posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS
            throw new rating_exception('notmemberofgroup');
        }
    }

    // perform some final capability checks
    if (!socialforum_user_can_see_post($socialforum, $discussion, $post, $USER, $cm)) {
        throw new rating_exception('nopermissiontorate');
    }

    return true;
}

/**
 * Can the current user see ratings for a given itemid?
 *
 * @param array $params submitted data
 *            contextid => int contextid [required]
 *            component => The component for this module - should always be mod_socialforum [required]
 *            ratingarea => object the context in which the rated items exists [required]
 *            itemid => int the ID of the object being rated [required]
 *            scaleid => int scale id [optional]
 * @return bool
 * @throws coding_exception
 * @throws rating_exception
 */
function mod_socialforum_rating_can_see_item_ratings($params) {
    global $DB, $USER;

    // Check the component is mod_socialforum.
    if (!isset($params['component']) || $params['component'] != 'mod_socialforum') {
        throw new rating_exception('invalidcomponent');
    }

    // Check the ratingarea is post (the only rating area in social forum).
    if (!isset($params['ratingarea']) || $params['ratingarea'] != 'post') {
        throw new rating_exception('invalidratingarea');
    }

    if (!isset($params['itemid'])) {
        throw new rating_exception('invaliditemid');
    }

    $post = $DB->get_record('socialforum_posts', array('id' => $params['itemid']), '*', MUST_EXIST);
    $discussion = $DB->get_record('socialforum_discussions', array('id' => $post->discussion), '*', MUST_EXIST);
    $socialforum = $DB->get_record('socialforum', array('id' => $discussion->socialforum), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $socialforum->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $course->id, false, MUST_EXIST);

    // Perform some final capability checks.
    if (!socialforum_user_can_see_post($socialforum, $discussion, $post, $USER, $cm)) {
        return false;
    }
    return true;
}

/**
 * This function prints the overview of a discussion in the social forum listing.
 * It needs some discussion information and some post information, these
 * happen to be combined for efficiency in the $post parameter by the function
 * that calls this one: socialforum_print_latest_discussions()
 *
 * @global object
 * @global object
 * @param object $post The post object (passed by reference for speed).
 * @param object $socialforum The social forum object.
 * @param int $group Current group.
 * @param string $datestring Format to use for the dates.
 * @param boolean $cantrack Is tracking enabled for this social forum.
 * @param boolean $socialforumtracked Is the user tracking this social forum.
 * @param boolean $canviewparticipants True if user has the viewparticipants permission for this course
 * @param boolean $canviewhiddentimedposts True if user has the viewhiddentimedposts permission for this social forum
 */
function socialforum_print_discussion_header(&$post, $socialforum, $group = -1, $datestring = "", $cantrack = true, $socialforumtracked = true, $canviewparticipants = true, $modcontext = null, $canviewhiddentimedposts = false) {
    global $COURSE, $USER, $CFG, $OUTPUT, $PAGE;

    static $rowcount;
    static $strmarkalldread;
    if (empty($modcontext)) {
        if (!$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $socialforum->course)) {
            print_error('invalidcoursemodule');
        }
        $modcontext = context_module::instance($cm->id);
    }
    if (!isset($rowcount)) {
        $rowcount = 0;
        $strmarkalldread = get_string('markalldread', 'mod_socialforum');
    } else {
        $rowcount = ($rowcount + 1) % 2;
    }
    $post->subject = format_string($post->subject, true);
    $timeddiscussion = !empty($CFG->socialforum_enabletimedposts) && ($post->timestart || $post->timeend);
    $timedoutsidewindow = '';
    if ($timeddiscussion && ($post->timestart > time() || ($post->timeend != 0 && $post->timeend < time()))) {
        $timedoutsidewindow = ' dimmed_text';
    }
    $votes = new socialforum_votes($socialforum->id, $post->discussion, null, $post->userid);
    if ($votes->votes > 0) {
        $relevancyclass = ' relevant';
    } else {
        if ($votes->votes < 0) {
            $relevancyclass = ' irrelevant';
        } else {
            $relevancyclass = '';
        }
    }
    echo html_writer::start_tag('tr', array(
        'class' => 'discussion' . $relevancyclass . ' r' . $rowcount . $timedoutsidewindow,
    ));
    $topicclass = 'topic starter';
    if (SOCIALFORUM_DISCUSSION_PINNED == $post->pinned) {
        $topicclass .= ' pinned';
    }
    echo html_writer::start_tag('td', array(
        'class' => $topicclass,
    ));
    if (SOCIALFORUM_DISCUSSION_PINNED == $post->pinned) {
        echo $OUTPUT->pix_icon('i/pinned', get_string('discussionpinned', 'mod_socialforum'), 'mod_socialforum');
    }
    $canalwaysseetimedpost = $USER->id == $post->userid || $canviewhiddentimedposts;
    if ($timeddiscussion && $canalwaysseetimedpost) {
        echo $PAGE->get_renderer('mod_socialforum')->timed_discussion_tooltip($post, empty($timedoutsidewindow));
    }
    echo html_writer::start_tag('p', array(
        'class' => 'mb-0',
    ));
    echo html_writer::tag('a', html_writer::tag('strong', $post->subject), array(
        'href' => '/mod/socialforum/discuss.php?d=' . $post->discussion,
        'class' => 'text-body'
    ));
    echo html_writer::end_tag('p');
    $lastupdate = (empty($post->timemodified)) ? $post->modified : $post->timemodified;  // Just in case
    echo html_writer::tag('small', get_string('lastreply', 'mod_socialforum') . ': ' .
            userdate($lastupdate, $datestring), array(
        'class' => 'text-muted',
    ));
    echo html_writer::end_tag('td');
    if (has_capability('mod/socialforum:viewdiscussion', $modcontext)) {
        // Generate html code for discussion replies
        echo html_writer::start_tag('td', array(
            'class' => 'replies',
        ));
        echo html_writer::tag('h5', $post->replies, array(
            'class' => 'm-0'
        ));
        echo html_writer::tag('p', html_writer::tag('small', get_string('replies', 'mod_socialforum'), array(
                    'class' => 'text-70'
                )), array(
            'class' => 'lh-1 mb-0'
        ));
        echo html_writer::end_tag('td');
        if ($cantrack) {
            echo html_writer::start_tag('td', array(
                'class' => 'replies',
            ));
            if ($socialforumtracked) {
                if ($post->unread > 0) {
                    echo html_writer::start_tag('span', array(
                        'class' => 'unread'
                    ));
                    echo html_writer::tag('a', $post->unread, array(
                        'href' => '/mod/socialforum/discuss.php?d=' . $post->discussion . '#unread',
                    ));
                    echo html_writer::end_tag('span');
                } else {
                    echo html_writer::start_tag('span', array(
                        'class' => 'read'
                    ));
                    echo $post->unread;
                    echo html_writer::end_tag('span');
                }
            } else {
                echo html_writer::start_tag('span', array(
                    'class' => 'read'
                ));
                echo '-';
                echo html_writer::end_tag('span');
            }
            echo html_writer::end_tag('td');
        }
    }
    echo html_writer::end_tag('tr');
}

/**
 * Retrieve a post from its id
 * 
 * @param int $postid Id of post to be retrieved
 * @return object Post with supplied id or null if no post with that
 *          id could be found
 * @throws invalid_parameter_exception In case of invalid post id
 */
function socialforum_get_post($postid) {
    global $DB;

    if (!is_numeric($postid) || ($postid < 0)) {
        throw new invalid_parameter_exception('post id must be a positive integer');
    }

    return $DB->get_record('socialforum_posts', array('id' => $postid));
}

/**
 * Retrieve a discussion from its id
 * 
 * @param int $discussionid Id of discussion to be retrieved
 * @return object Discussion with supplied id or null if no discussion with that
 *          id could be found
 * @throws invalid_parameter_exception In case of invalid discussion id
 */
function socialforum_get_discussion($discussionid) {
    global $DB;

    if (!is_numeric($discussionid) || ($discussionid < 0)) {
        throw new invalid_parameter_exception('discussion id must be a positive integer');
    }

    return $DB->get_record('socialforum_discussions', array('id' => $discussionid));
}

/**
 * Return the markup for the discussion subscription toggling icon.
 *
 * @param stdClass $socialforum The social forum object.
 * @param int $discussionid The discussion to create an icon for.
 * @return string The generated markup.
 */
function socialforum_get_discussion_subscription_icon($socialforum, $discussionid, $returnurl = null, $includetext = false) {
    global $USER, $OUTPUT, $PAGE;

    if ($returnurl === null && $PAGE->url) {
        $returnurl = $PAGE->url->out();
    }

    $o = '';
    $subscriptionstatus = \mod_socialforum\subscriptions::is_subscribed($USER->id, $socialforum, $discussionid);
    $subscriptionlink = new moodle_url('/mod/socialforum/subscribe.php', array(
        'sesskey' => sesskey(),
        'id' => $socialforum->id,
        'd' => $discussionid,
        'returnurl' => $returnurl,
    ));

    if ($includetext) {
        $o .= $subscriptionstatus ? get_string('subscribed', 'mod_socialforum') : get_string('notsubscribed', 'mod_socialforum');
    }

    if ($subscriptionstatus) {
        $output = $OUTPUT->pix_icon('t/subscribed', get_string('clicktounsubscribe', 'mod_socialforum'), 'mod_socialforum');
        if ($includetext) {
            $output .= get_string('subscribed', 'mod_socialforum');
        }

        return html_writer::link($subscriptionlink, $output, array(
                    'title' => get_string('clicktounsubscribe', 'mod_socialforum'),
                    'class' => 'discussiontoggle iconsmall',
                    'data-forumid' => $socialforum->id,
                    'data-discussionid' => $discussionid,
                    'data-includetext' => $includetext,
        ));
    } else {
        $output = $OUTPUT->pix_icon('t/unsubscribed', get_string('clicktosubscribe', 'mod_socialforum'), 'mod_socialforum');
        if ($includetext) {
            $output .= get_string('notsubscribed', 'mod_socialforum');
        }

        return html_writer::link($subscriptionlink, $output, array(
                    'title' => get_string('clicktosubscribe', 'mod_socialforum'),
                    'class' => 'discussiontoggle iconsmall',
                    'data-forumid' => $socialforum->id,
                    'data-discussionid' => $discussionid,
                    'data-includetext' => $includetext,
        ));
    }
}

/**
 * Return a pair of spans containing classes to allow the subscribe and
 * unsubscribe icons to be pre-loaded by a browser.
 *
 * @return string The generated markup
 */
function socialforum_get_discussion_subscription_icon_preloaders() {
    $o = '';
    $o .= html_writer::span('&nbsp;', 'preload-subscribe');
    $o .= html_writer::span('&nbsp;', 'preload-unsubscribe');
    return $o;
}

/**
 * Print the drop down that allows the user to select how they want to have
 * the discussion displayed.
 *
 * @param int $id social forum id if $socialforumtype is 'single',
 *              discussion id for any other social forum type
 * @param mixed $mode social forum layout mode
 * @param string $socialforumtype optional
 */
function socialforum_print_mode_form($id, $mode, $socialforumtype = '') {
    global $OUTPUT;
    if ($socialforumtype == 'single') {
        $select = new single_select(new moodle_url("/mod/socialforum/view.php", array('f' => $id)), 'mode', socialforum_get_layout_modes(), $mode, null, "mode");
        $select->set_label(get_string('displaymode', 'mod_socialforum'), array('class' => 'accesshide'));
        $select->class = "socialforummode";
    } else {
        $select = new single_select(new moodle_url("/mod/socialforum/discuss.php", array('d' => $id)), 'mode', socialforum_get_layout_modes(), $mode, null, "mode");
        $select->set_label(get_string('displaymode', 'mod_socialforum'), array('class' => 'accesshide'));
    }
    echo $OUTPUT->render($select);
}

/**
 * Render search form
 * 
 * @param object $socialforum
 * @return string HTML
 */
function socialforum_search_form($socialforum) {
    $html = '';
    $html .= html_writer::start_tag('form ', array(
                'class' => 'search-form  d-lg-inline-flex mb-8pt mb-lg-0',
                'action' => '/mod/socialforum/search.php',
    ));
    $html .= html_writer::empty_tag('input', array(
                'type' => 'text',
                'name' => 'search',
                'class' => 'form-control w-lg-auto',
                'placeholder' => get_string('search') . '...',
    ));
    $html .= html_writer::start_tag('button', array(
                'class' => 'btn',
                'type' => 'submit',
    ));
    $html .= html_writer::tag('i', 'search', array(
                'class' => 'material-icons',
    ));
    $html .= html_writer::end_tag('button');
    $html .= html_writer::empty_tag('input', array(
                'name' => 'id',
                'type' => 'hidden',
                'value' => $socialforum->course,
    ));
    $html .= html_writer::empty_tag('input', array(
                'name' => 'socialforumid',
                'type' => 'hidden',
                'value' => $socialforum->id,
    ));
    $html .= html_writer::end_tag('form');
    return $html;
}

/**
 * @global object
 * @global object
 */
function socialforum_set_return() {
    global $CFG, $SESSION;

    if (!isset($SESSION->fromdiscussion)) {
        $referer = get_local_referer(false);
        // If the referer is NOT a login screen then save it.
        if (!strncasecmp("$CFG->wwwroot/login", $referer, 300)) {
            $SESSION->fromdiscussion = $referer;
        }
    }
}

/**
 * @global object
 * @param string|\moodle_url $default
 * @return string
 */
function socialforum_go_back_to($default) {
    global $SESSION;

    if (!empty($SESSION->fromdiscussion)) {
        $returnto = $SESSION->fromdiscussion;
        unset($SESSION->fromdiscussion);
        return $returnto;
    } else {
        return $default;
    }
}

/**
 * Given a discussion object that is being moved to $socialforumto,
 * this function checks all posts in that discussion
 * for attachments, and if any are found, these are
 * moved to the new social forum directory.
 *
 * @global object
 * @param object $discussion
 * @param int $socialforumfrom source social forum id
 * @param int $socialforumto target social forum id
 * @return bool success
 */
function socialforum_move_attachments($discussion, $socialforumfrom, $socialforumto) {
    global $DB;

    $fs = get_file_storage();

    $newcm = get_coursemodule_from_instance('socialforum', $socialforumto);
    $oldcm = get_coursemodule_from_instance('socialforum', $socialforumfrom);

    $newcontext = context_module::instance($newcm->id);
    $oldcontext = context_module::instance($oldcm->id);

    // loop through all posts, better not use attachment flag ;-)
    if ($posts = $DB->get_records('socialforum_posts', array('discussion' => $discussion->id), '', 'id, attachment')) {
        foreach ($posts as $post) {
            $fs->move_area_files_to_new_context($oldcontext->id, $newcontext->id, 'mod_socialforum', 'post', $post->id);
            $attachmentsmoved = $fs->move_area_files_to_new_context($oldcontext->id, $newcontext->id, 'mod_socialforum', 'attachment', $post->id);
            if ($attachmentsmoved > 0 && $post->attachment != '1') {
                // Weird - let's fix it
                $post->attachment = '1';
                $DB->update_record('socialforum_posts', $post);
            } else if ($attachmentsmoved == 0 && $post->attachment != '') {
                // Weird - let's fix it
                $post->attachment = '';
                $DB->update_record('socialforum_posts', $post);
            }
        }
    }

    return true;
}

/**
 * Returns attachments as formated text/html optionally with separate images
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param object $cm
 * @param string $type html/text/separateimages
 * @return mixed string or array of (html text withouth images and image HTML)
 */
function socialforum_print_attachments($post, $cm, $type) {
    global $CFG, $DB, $USER, $OUTPUT;

    if (empty($post->attachment)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!in_array($type, array('separateimages', 'html', 'text'))) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!$context = context_module::instance($cm->id)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }
    $strattachment = get_string('attachment', 'mod_socialforum');

    $fs = get_file_storage();

    $imagereturn = '';
    $output = '';

    $canexport = !empty($CFG->enableportfolios) && (has_capability('mod/socialforum:exportpost', $context) || ($post->userid == $USER->id && has_capability('mod/socialforum:exportownpost', $context)));

    if ($canexport) {
        require_once($CFG->libdir . '/portfoliolib.php');
    }

    // We retrieve all files according to the time that they were created.  In the case that several files were uploaded
    // at the sametime (e.g. in the case of drag/drop upload) we revert to using the filename.
    $files = $fs->get_area_files($context->id, 'mod_socialforum', 'attachment', $post->id, "filename", false);
    if ($files) {
        if ($canexport) {
            $button = new portfolio_add_button();
        }
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $mimetype = $file->get_mimetype();
            $iconimage = $OUTPUT->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon'));
            $path = file_encode_url($CFG->wwwroot . '/pluginfile.php', '/' . $context->id . '/mod_socialforum/attachment/' . $post->id . '/' . $filename);

            if ($type == 'html') {
                $output .= "<a href=\"$path\">$iconimage</a> ";
                $output .= "<a href=\"$path\">" . s($filename) . "</a>";
                if ($canexport) {
                    $button->set_callback_options('socialforum_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_socialforum');
                    $button->set_format_by_file($file);
                    $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                }
                $output .= "<br />";
            } else if ($type == 'text') {
                $output .= "$strattachment " . s($filename) . ":\n$path\n";
            } else { //'returnimages'
                if (in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png'))) {
                    // Image attachments don't get printed as links
                    $imagereturn .= "<br /><img src=\"$path\" alt=\"\" />";
                    if ($canexport) {
                        $button->set_callback_options('socialforum_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_socialforum');
                        $button->set_format_by_file($file);
                        $imagereturn .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                    }
                } else {
                    $output .= "<a href=\"$path\">$iconimage</a> ";
                    $output .= format_text("<a href=\"$path\">" . s($filename) . "</a>", FORMAT_HTML, array('context' => $context));
                    if ($canexport) {
                        $button->set_callback_options('socialforum_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_socialforum');
                        $button->set_format_by_file($file);
                        $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                    }
                    $output .= '<br />';
                }
            }

            if (!empty($CFG->enableplagiarism)) {
                require_once($CFG->libdir . '/plagiarismlib.php');
                $output .= plagiarism_get_links(array('userid' => $post->userid,
                    'file' => $file,
                    'cmid' => $cm->id,
                    'course' => $cm->course,
                    'socialforum' => $cm->instance));
                $output .= '<br />';
            }
        }
    }

    if ($type !== 'separateimages') {
        return $output;
    } else {
        return array($output, $imagereturn);
    }
}

////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Lists all browsable file areas
 *
 * @package  mod_socialforum
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return array
 */
function socialforum_get_file_areas($course, $cm, $context) {
    return array(
        'attachment' => get_string('areaattachment', 'mod_socialforum'),
        'post' => get_string('areapost', 'mod_socialforum'),
    );
}

/**
 * File browsing support for social forum module.
 *
 * @package  mod_socialforum
 * @category files
 * @param stdClass $browser file browser object
 * @param stdClass $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module
 * @param stdClass $context context module
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info instance or null if not found
 */
function socialforum_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }

    // filearea must contain a real area
    if (!isset($areas[$filearea])) {
        return null;
    }

    // Note that socialforum_user_can_see_post() additionally allows access for parent roles
    // and it explicitly checks qanda social forum type, too. One day, when we stop requiring
    // course:managefiles, we will need to extend this.
    if (!has_capability('mod/socialforum:viewdiscussion', $context)) {
        return null;
    }

    if (is_null($itemid)) {
        require_once($CFG->dirroot . '/mod/socialforum/locallib.php');
        return new socialforum_file_info_container($browser, $course, $cm, $context, $areas, $filearea);
    }

    static $cached = array();
    // $cached will store last retrieved post, discussion and social forum. To make sure that the cache
    // is cleared between unit tests we check if this is the same session
    if (!isset($cached['sesskey']) || $cached['sesskey'] != sesskey()) {
        $cached = array('sesskey' => sesskey());
    }

    if (isset($cached['post']) && $cached['post']->id == $itemid) {
        $post = $cached['post'];
    } else if ($post = $DB->get_record('socialforum_posts', array('id' => $itemid))) {
        $cached['post'] = $post;
    } else {
        return null;
    }

    if (isset($cached['discussion']) && $cached['discussion']->id == $post->discussion) {
        $discussion = $cached['discussion'];
    } else if ($discussion = $DB->get_record('socialforum_discussions', array('id' => $post->discussion))) {
        $cached['discussion'] = $discussion;
    } else {
        return null;
    }

    if (isset($cached['socialforum']) && $cached['socialforum']->id == $cm->instance) {
        $socialforum = $cached['socialforum'];
    } else if ($socialforum = $DB->get_record('socialforum', array('id' => $cm->instance))) {
        $cached['socialforum'] = $socialforum;
    } else {
        return null;
    }

    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;
    if (!($storedfile = $fs->get_file($context->id, 'mod_socialforum', $filearea, $itemid, $filepath, $filename))) {
        return null;
    }

    // Checks to see if the user can manage files or is the owner.
    // TODO MDL-33805 - Do not use userid here and move the capability check above.
    if (!has_capability('moodle/course:managefiles', $context) && $storedfile->get_userid() != $USER->id) {
        return null;
    }
    // Make sure groups allow this user to see this file
    if ($discussion->groupid > 0 && !has_capability('moodle/site:accessallgroups', $context)) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS && !groups_is_member($discussion->groupid)) {
            return null;
        }
    }

    // Make sure we're allowed to see it...
    if (!socialforum_user_can_see_post($socialforum, $discussion, $post, NULL, $cm)) {
        return null;
    }

    $urlbase = $CFG->wwwroot . '/pluginfile.php';
    return new file_info_stored($browser, $context, $storedfile, $urlbase, $itemid, true, true, false, false);
}

/**
 * Serves the social forum attachments. Implements needed access control ;-)
 *
 * @package  mod_socialforum
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function socialforum_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $areas = socialforum_get_file_areas($course, $cm, $context);

    // filearea must contain a real area
    if (!isset($areas[$filearea])) {
        return false;
    }

    $postid = (int) array_shift($args);

    if (!$post = $DB->get_record('socialforum_posts', array('id' => $postid))) {
        return false;
    }

    if (!$discussion = $DB->get_record('socialforum_discussions', array('id' => $post->discussion))) {
        return false;
    }

    if (!$socialforum = $DB->get_record('socialforum', array('id' => $cm->instance))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_socialforum/$filearea/$postid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // Make sure groups allow this user to see this file
    if ($discussion->groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS) {
            if (!groups_is_member($discussion->groupid) and ! has_capability('moodle/site:accessallgroups', $context)) {
                return false;
            }
        }
    }

    // Make sure we're allowed to see it...
    if (!socialforum_user_can_see_post($socialforum, $discussion, $post, NULL, $cm)) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, true, $options); // download MUST be forced - security!
}

/**
 * If successful, this function returns the name of the file
 *
 * @global object
 * @param object $post is a full post record, including course and social forum
 * @param object $socialforum
 * @param object $cm
 * @param mixed $mform
 * @param string $unused
 * @return bool
 */
function socialforum_add_attachment($post, $socialforum, $cm, $mform = null, $unused = null) {
    global $DB;

    if (empty($mform)) {
        return false;
    }

    if (empty($post->attachments)) {
        return true;   // Nothing to do
    }

    $context = context_module::instance($cm->id);

    $info = file_get_draft_area_info($post->attachments);
    $present = ($info['filecount'] > 0) ? '1' : '';
    file_save_draft_area_files($post->attachments, $context->id, 'mod_socialforum', 'attachment', $post->id, mod_socialforum_post_form::attachment_options($socialforum));

    $DB->set_field('socialforum_posts', 'attachment', $present, array('id' => $post->id));

    return true;
}

/**
 * Add a new post in an existing discussion.
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param mixed $mform
 * @param string $unused formerly $message, renamed in 2.8 as it was unused.
 * @return int
 */
function socialforum_add_new_post($post, $mform, $unused = null) {
    global $USER, $DB;

    $discussion = $DB->get_record('socialforum_discussions', array('id' => $post->discussion));
    $socialforum = $DB->get_record('socialforum', array('id' => $discussion->socialforum));
    $cm = get_coursemodule_from_instance('socialforum', $socialforum->id);
    $context = context_module::instance($cm->id);

    $post->created = $post->modified = time();
    $post->mailed = SOCIALFORUM_MAILED_PENDING;
    $post->userid = $USER->id;
    $post->attachment = "";
    if (!isset($post->totalscore)) {
        $post->totalscore = 0;
    }
    if (!isset($post->mailnow)) {
        $post->mailnow = 0;
    }

    $post->id = $DB->insert_record("socialforum_posts", $post);
    $post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_socialforum', 'post', $post->id, mod_socialforum_post_form::editor_options($context, null), $post->message);
    $DB->set_field('socialforum_posts', 'message', $post->message, array('id' => $post->id));
    socialforum_add_attachment($post, $socialforum, $cm, $mform);

    // Update discussion modified date
    $DB->set_field("socialforum_discussions", "timemodified", $post->modified, array("id" => $post->discussion));
    $DB->set_field("socialforum_discussions", "usermodified", $post->userid, array("id" => $post->discussion));

    if (socialforum_tp_can_track_socialforums($socialforum) && socialforum_tp_is_tracked($socialforum)) {
        socialforum_tp_mark_post_read($post->userid, $post, $post->socialforum);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    socialforum_trigger_content_uploaded_event($post, $cm, 'socialforum_add_new_post');

    return $post->id;
}

/**
 * Update a post
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param mixed $mform
 * @param string $message
 * @return bool
 */
function socialforum_update_post($post, $mform, &$message) {
    global $USER, $CFG, $DB;

    $discussion = $DB->get_record('socialforum_discussions', array('id' => $post->discussion));
    $socialforum = $DB->get_record('socialforum', array('id' => $discussion->socialforum));
    $cm = get_coursemodule_from_instance('socialforum', $socialforum->id);
    $context = context_module::instance($cm->id);

    $post->modified = time();

    $DB->update_record('socialforum_posts', $post);

    $discussion->timemodified = $post->modified; // last modified tracking
    $discussion->usermodified = $post->userid;   // last modified tracking

    if (!$post->parent) {   // Post is a discussion starter - update discussion title and times too
        $discussion->name = $post->subject;
        $discussion->timestart = $post->timestart;
        $discussion->timeend = $post->timeend;

        if (isset($post->pinned)) {
            $discussion->pinned = $post->pinned;
        }
    }
    $post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_socialforum', 'post', $post->id, mod_socialforum_post_form::editor_options($context, $post->id), $post->message);
    $DB->set_field('socialforum_posts', 'message', $post->message, array('id' => $post->id));

    $DB->update_record('socialforum_discussions', $discussion);

    socialforum_add_attachment($post, $socialforum, $cm, $mform, $message);

    if (socialforum_tp_can_track_socialforums($socialforum) && socialforum_tp_is_tracked($socialforum)) {
        socialforum_tp_mark_post_read($post->userid, $post, $post->socialforum);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    socialforum_trigger_content_uploaded_event($post, $cm, 'socialforum_update_post');

    return true;
}

/**
 * Given an object containing all the necessary data,
 * create a new discussion and return the id
 *
 * @param object $post
 * @param mixed $mform
 * @param string $unused
 * @param int $userid
 * @return object
 */
function socialforum_add_discussion($discussion, $mform = null, $unused = null, $userid = null) {
    global $USER, $DB;

    $timenow = isset($discussion->timenow) ? $discussion->timenow : time();

    if (is_null($userid)) {
        $userid = $USER->id;
    }

    // The first post is stored as a real post, and linked
    // to from the discuss entry.

    $socialforum = $DB->get_record('socialforum', array('id' => $discussion->socialforum));
    $cm = get_coursemodule_from_instance('socialforum', $socialforum->id);

    $post = new stdClass();
    $post->discussion = 0;
    $post->parent = 0;
    $post->userid = $userid;
    $post->created = $timenow;
    $post->modified = $timenow;
    $post->mailed = SOCIALFORUM_MAILED_PENDING;
    $post->subject = $discussion->name;
    $post->message = $discussion->message;
    $post->messageformat = $discussion->messageformat;
    $post->messagetrust = $discussion->messagetrust;
    $post->attachments = isset($discussion->attachments) ? $discussion->attachments : null;
    $post->socialforum = $socialforum->id;     // speedup
    $post->course = $socialforum->course; // speedup
    $post->mailnow = $discussion->mailnow;

    $post->id = $DB->insert_record("socialforum_posts", $post);

    // TODO: Fix the calling code so that there always is a $cm when this function is called
    if (!empty($cm->id) && !empty($discussion->itemid)) {   // In "single simple discussions" this may not exist yet
        $context = context_module::instance($cm->id);
        $text = file_save_draft_area_files($discussion->itemid, $context->id, 'mod_socialforum', 'post', $post->id, mod_socialforum_post_form::editor_options($context, null), $post->message);
        $DB->set_field('socialforum_posts', 'message', $text, array('id' => $post->id));
    }

    // Now do the main entry for the discussion, linking to this first post

    $discussion->firstpost = $post->id;
    $discussion->timemodified = $timenow;
    $discussion->usermodified = $post->userid;
    $discussion->userid = $userid;
    $discussion->assessed = 0;

    $post->discussion = $DB->insert_record("socialforum_discussions", $discussion);

    // Finally, set the pointer on the post.
    $DB->set_field("socialforum_posts", "discussion", $post->discussion, array("id" => $post->id));

    if (!empty($cm->id)) {
        socialforum_add_attachment($post, $socialforum, $cm, $mform, $unused);
    }

    if (socialforum_tp_can_track_socialforums($socialforum) && socialforum_tp_is_tracked($socialforum)) {
        socialforum_tp_mark_post_read($post->userid, $post, $post->socialforum);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    if (!empty($cm->id)) {
        socialforum_trigger_content_uploaded_event($post, $cm, 'socialforum_add_discussion');
    }

    return $post->discussion;
}

/**
 * Deletes a discussion and handles all associated cleanup.
 *
 * @global object
 * @param object $discussion Discussion to delete
 * @param bool $fulldelete True when deleting entire social forum
 * @param object $course Course
 * @param object $cm Course-module
 * @param object $socialforum Forum
 * @return bool
 */
function socialforum_delete_discussion($discussion, $fulldelete, $course, $cm, $socialforum) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/completionlib.php');

    $result = true;

    if ($posts = $DB->get_records("socialforum_posts", array("discussion" => $discussion->id))) {
        foreach ($posts as $post) {
            $post->course = $discussion->course;
            $post->socialforum = $discussion->socialforum;
            if (!socialforum_delete_post($post, 'ignore', $course, $cm, $socialforum, $fulldelete)) {
                $result = false;
            }
        }
    }

    socialforum_tp_delete_read_records(-1, -1, $discussion->id);

    // Discussion subscriptions must be removed before discussions because of key constraints.
    $DB->delete_records('socialforum_discussion_subs', array('discussion' => $discussion->id));
    if (!$DB->delete_records("socialforum_discussions", array("id" => $discussion->id))) {
        $result = false;
    }

    // Update completion state if we are tracking completion based on number of posts
    // But don't bother when deleting whole thing
    if (!$fulldelete) {
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC &&
                ($socialforum->completiondiscussions || $socialforum->completionreplies || $socialforum->completionposts)) {
            $completion->update_state($cm, COMPLETION_INCOMPLETE, $discussion->userid);
        }
    }

    return $result;
}

/**
 * Deletes a single social forum post.
 *
 * @global object
 * @param object $post Forum post object
 * @param mixed $children Whether to delete children. If false, returns false
 *   if there are any children (without deleting the post). If true,
 *   recursively deletes all children. If set to special value 'ignore', deletes
 *   post regardless of children (this is for use only when deleting all posts
 *   in a disussion).
 * @param object $course Course
 * @param object $cm Course-module
 * @param object $socialforum Forum
 * @param bool $skipcompletion True to skip updating completion state if it
 *   would otherwise be updated, i.e. when deleting entire social forum anyway.
 * @return bool
 */
function socialforum_delete_post($post, $children, $course, $cm, $socialforum, $skipcompletion = false) {
    global $DB, $CFG, $USER;
    require_once($CFG->libdir . '/completionlib.php');

    $context = context_module::instance($cm->id);

    if ($children !== 'ignore' && ($childposts = $DB->get_records('socialforum_posts', array('parent' => $post->id)))) {
        if ($children) {
            foreach ($childposts as $childpost) {
                socialforum_delete_post($childpost, true, $course, $cm, $socialforum, $skipcompletion);
            }
        } else {
            return false;
        }
    }

    // Delete ratings.
    require_once($CFG->dirroot . '/rating/lib.php');
    $delopt = new stdClass;
    $delopt->contextid = $context->id;
    $delopt->component = 'mod_socialforum';
    $delopt->ratingarea = 'post';
    $delopt->itemid = $post->id;
    $rm = new rating_manager();
    $rm->delete_ratings($delopt);

    // Delete attachments.
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_socialforum', 'attachment', $post->id);
    $fs->delete_area_files($context->id, 'mod_socialforum', 'post', $post->id);

    // Delete cached RSS feeds.
    if (!empty($CFG->enablerssfeeds)) {
        require_once($CFG->dirroot . '/mod/socialforum/rsslib.php');
        socialforum_rss_delete_file($socialforum);
    }

    if ($DB->delete_records("socialforum_posts", array("id" => $post->id))) {

        socialforum_tp_delete_read_records(-1, $post->id);

        // Just in case we are deleting the last post
        socialforum_discussion_update_last_post($post->discussion);

        // Update completion state if we are tracking completion based on number of posts
        // But don't bother when deleting whole thing

        if (!$skipcompletion) {
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC &&
                    ($socialforum->completiondiscussions || $socialforum->completionreplies || $socialforum->completionposts)) {
                $completion->update_state($cm, COMPLETION_INCOMPLETE, $post->userid);
            }
        }

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array(
                'discussionid' => $post->discussion,
                'socialforumid' => $socialforum->id,
                'socialforumtype' => $socialforum->type,
            )
        );
        if ($post->userid !== $USER->id) {
            $params['relateduserid'] = $post->userid;
        }
        $event = \mod_socialforum\event\post_deleted::create($params);
        $event->add_record_snapshot('socialforum_posts', $post);
        $event->trigger();

        return true;
    }
    return false;
}

/**
 * Sends post content to plagiarism plugin
 * @param object $post Forum post object
 * @param object $cm Course-module
 * @param string $name
 * @return bool
 */
function socialforum_trigger_content_uploaded_event($post, $cm, $name) {
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_socialforum', 'attachment', $post->id, "timemodified", false);
    $params = array(
        'context' => $context,
        'objectid' => $post->id,
        'other' => array(
            'content' => $post->message,
            'pathnamehashes' => array_keys($files),
            'discussionid' => $post->discussion,
            'triggeredfrom' => $name,
        )
    );
    $event = \mod_socialforum\event\assessable_uploaded::create($params);
    $event->trigger();
    return true;
}

/**
 * @global object
 * @param object $post
 * @param bool $children
 * @return int
 */
function socialforum_count_replies($post, $children = true) {
    global $DB;
    $count = 0;

    if ($children) {
        if ($childposts = $DB->get_records('socialforum_posts', array('parent' => $post->id))) {
            foreach ($childposts as $childpost) {
                $count ++;                   // For this child
                $count += socialforum_count_replies($childpost, true);
            }
        }
    } else {
        $count += $DB->count_records('socialforum_posts', array('parent' => $post->id));
    }

    return $count;
}

/**
 * Given a new post, subscribes or unsubscribes as appropriate.
 * Returns some text which describes what happened.
 *
 * @param object $fromform The submitted form
 * @param stdClass $socialforum The social forum record
 * @param stdClass $discussion The social forum discussion record
 * @return string
 */
function socialforum_post_subscription($fromform, $socialforum, $discussion) {
    global $USER;

    if (\mod_socialforum\subscriptions::is_forcesubscribed($socialforum)) {
        return "";
    } else if (\mod_socialforum\subscriptions::subscription_disabled($socialforum)) {
        $subscribed = \mod_socialforum\subscriptions::is_subscribed($USER->id, $socialforum);
        if ($subscribed && !has_capability('moodle/course:manageactivities', context_course::instance($socialforum->course), $USER->id)) {
            // This user should not be subscribed to the social forum.
            \mod_socialforum\subscriptions::unsubscribe_user($USER->id, $socialforum);
        }
        return "";
    }

    $info = new stdClass();
    $info->name = fullname($USER);
    $info->discussion = format_string($discussion->name);
    $info->socialforum = format_string($socialforum->name);

    if (isset($fromform->discussionsubscribe) && $fromform->discussionsubscribe) {
        if ($result = \mod_socialforum\subscriptions::subscribe_user_to_discussion($USER->id, $discussion)) {
            return html_writer::tag('p', get_string('discussionnowsubscribed', 'socialforum', $info));
        }
    } else {
        if ($result = \mod_socialforum\subscriptions::unsubscribe_user_from_discussion($USER->id, $discussion)) {
            return html_writer::tag('p', get_string('discussionnownotsubscribed', 'socialforum', $info));
        }
    }

    return '';
}

/**
 * Generate and return the subscribe or unsubscribe link for a social forum.
 *
 * @param object $socialforum the social forum. Fields used are $socialforum->id and $socialforum->forcesubscribe.
 * @param object $context the context object for this social forum.
 * @param array $messages text used for the link in its various states
 *      (subscribed, unsubscribed, forcesubscribed or cantsubscribe).
 *      Any strings not passed in are taken from the $defaultmessages array
 *      at the top of the function.
 * @param bool $cantaccessagroup
 * @param bool $fakelink
 * @param bool $backtoindex
 * @param array $subscribed_socialforums
 * @return string
 */
function socialforum_get_subscribe_link($socialforum, $context, $messages = array(), $cantaccessagroup = false, $fakelink = true, $backtoindex = false, $subscribed_socialforums = null) {
    global $CFG, $USER, $PAGE, $OUTPUT;
    $defaultmessages = array(
        'subscribed' => get_string('unsubscribe', 'mod_socialforum'),
        'unsubscribed' => get_string('subscribe', 'mod_socialforum'),
        'cantaccessgroup' => get_string('no'),
        'forcesubscribed' => get_string('everyoneissubscribed', 'mod_socialforum'),
        'cantsubscribe' => get_string('disallowsubscribe', 'mod_socialforum')
    );
    $messages = $messages + $defaultmessages;

    if (\mod_socialforum\subscriptions::is_forcesubscribed($socialforum)) {
        return $messages['forcesubscribed'];
    } else if (\mod_socialforum\subscriptions::subscription_disabled($socialforum) &&
            !has_capability('mod/socialforum:managesubscriptions', $context)) {
        return $messages['cantsubscribe'];
    } else if ($cantaccessagroup) {
        return $messages['cantaccessgroup'];
    } else {
        if (!is_enrolled($context, $USER, '', true)) {
            return '';
        }

        $subscribed = \mod_socialforum\subscriptions::is_subscribed($USER->id, $socialforum);
        if ($subscribed) {
            $linktext = $messages['subscribed'];
            $linktitle = get_string('subscribestop', 'mod_socialforum');
        } else {
            $linktext = $messages['unsubscribed'];
            $linktitle = get_string('subscribestart', 'mod_socialforum');
        }

        $options = array();
        if ($backtoindex) {
            $backtoindexlink = '&amp;backtoindex=1';
            $options['backtoindex'] = 1;
        } else {
            $backtoindexlink = '';
        }
        $link = '';

        if ($fakelink) {
            $PAGE->requires->js('/mod/socialforum/socialforum.js');
            $PAGE->requires->js_function_call('socialforum_produce_subscribe_link', array($socialforum->id, $backtoindexlink, $linktext, $linktitle));
            $link = "<noscript>";
        }
        $options['id'] = $socialforum->id;
        $options['sesskey'] = sesskey();
        $url = new moodle_url('/mod/socialforum/subscribe.php', $options);
        $link .= $OUTPUT->single_button($url, $linktext, 'get', array('title' => $linktitle));
        if ($fakelink) {
            $link .= '</noscript>';
        }

        return $link;
    }
}

/**
 * Returns true if user created new discussion already.
 *
 * @param int $socialforumid  The social forum to check for postings
 * @param int $userid   The user to check for postings
 * @param int $groupid  The group to restrict the check to
 * @return bool
 */
function socialforum_user_has_posted_discussion($socialforumid, $userid, $groupid = null) {
    global $CFG, $DB;

    $sql = "SELECT 'x'
              FROM {socialforum_discussions} d, {socialforum_posts} p
             WHERE d.socialforum = ? AND p.discussion = d.id AND p.parent = 0 AND p.userid = ?";

    $params = [$socialforumid, $userid];

    if ($groupid) {
        $sql .= " AND d.groupid = ?";
        $params[] = $groupid;
    }

    return $DB->record_exists_sql($sql, $params);
}

/**
 * @global object
 * @global object
 * @param int $socialforumid
 * @param int $userid
 * @return array
 */
function socialforum_discussions_user_has_posted_in($socialforumid, $userid) {
    global $CFG, $DB;

    $haspostedsql = "SELECT d.id AS id,
                            d.*
                       FROM {socialforum_posts} p,
                            {socialforum_discussions} d
                      WHERE p.discussion = d.id
                        AND d.socialforum = ?
                        AND p.userid = ?";

    return $DB->get_records_sql($haspostedsql, array($socialforumid, $userid));
}

/**
 * @global object
 * @global object
 * @param int $socialforumid
 * @param int $did
 * @param int $userid
 * @return bool
 */
function socialforum_user_has_posted($socialforumid, $did, $userid) {
    global $DB;

    if (empty($did)) {
        // posted in any social forum discussion?
        $sql = "SELECT 'x'
                  FROM {socialforum_posts} p
                  JOIN {socialforum_discussions} d ON d.id = p.discussion
                 WHERE p.userid = :userid AND d.socialforum = :socialforumid";
        return $DB->record_exists_sql($sql, array('socialforumid' => $socialforumid, 'userid' => $userid));
    } else {
        return $DB->record_exists('socialforum_posts', array('discussion' => $did, 'userid' => $userid));
    }
}

/**
 * Returns creation time of the first user's post in given discussion
 * @global object $DB
 * @param int $did Discussion id
 * @param int $userid User id
 * @return int|bool post creation time stamp or return false
 */
function socialforum_get_user_posted_time($did, $userid) {
    global $DB;

    $posttime = $DB->get_field('socialforum_posts', 'MIN(created)', array('userid' => $userid, 'discussion' => $did));
    if (empty($posttime)) {
        return false;
    }
    return $posttime;
}

/**
 * @global object
 * @param object $socialforum
 * @param object $currentgroup
 * @param int $unused
 * @param object $cm
 * @param object $context
 * @return bool
 */
function socialforum_user_can_post_discussion($socialforum, $currentgroup = null, $unused = -1, $cm = NULL, $context = NULL) {
    global $USER;

    // shortcut - guest and not-logged-in users can not post
    if (isguestuser() or ! isloggedin()) {
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $socialforum->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (!$context) {
        $context = context_module::instance($cm->id);
    }

    if ($currentgroup === null) {
        $currentgroup = groups_get_activity_group($cm);
    }

    $groupmode = groups_get_activity_groupmode($cm);

    if ($socialforum->type == 'news') {
        $capname = 'mod/socialforum:addnews';
    } else if ($socialforum->type == 'qanda') {
        $capname = 'mod/socialforum:addquestion';
    } else {
        $capname = 'mod/socialforum:startdiscussion';
    }

    if (!has_capability($capname, $context)) {
        return false;
    }

    if ($socialforum->type == 'single') {
        return false;
    }

    if ($socialforum->type == 'eachuser') {
        if (socialforum_user_has_posted_discussion($socialforum->id, $USER->id, $currentgroup)) {
            return false;
        }
    }

    if (!$groupmode or has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($currentgroup) {
        return groups_is_member($currentgroup);
    } else {
        // no group membership and no accessallgroups means no new discussions
        // reverted to 1.7 behaviour in 1.9+,  buggy in 1.8.0-1.9.0
        return false;
    }
}

/**
 * This function checks whether the user can reply to posts in a social forum
 * discussion. Use socialforum_user_can_post_discussion() to check whether the user
 * can start discussions.
 *
 * @global object
 * @global object
 * @uses DEBUG_DEVELOPER
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $socialforum social forum object
 * @param object $discussion
 * @param object $user
 * @param object $cm
 * @param object $course
 * @param object $context
 * @return bool
 */
function socialforum_user_can_post($socialforum, $discussion, $user = NULL, $cm = NULL, $course = NULL, $context = NULL) {
    global $USER, $DB;
    if (empty($user)) {
        $user = $USER;
    }

    // shortcut - guest and not-logged-in users can not post
    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    if (!isset($discussion->groupid)) {
        debugging('incorrect discussion parameter', DEBUG_DEVELOPER);
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $socialforum->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (!$course) {
        debugging('missing course', DEBUG_DEVELOPER);
        if (!$course = $DB->get_record('course', array('id' => $socialforum->course))) {
            print_error('invalidcourseid');
        }
    }

    if (!$context) {
        $context = context_module::instance($cm->id);
    }

    // normal users with temporary guest access can not post, suspended users can not post either
    if (!is_viewing($context, $user->id) and ! is_enrolled($context, $user->id, '', true)) {
        return false;
    }

    if ($socialforum->type == 'news') {
        $capname = 'mod/socialforum:replynews';
    } else {
        $capname = 'mod/socialforum:replypost';
    }

    if (!has_capability($capname, $context, $user->id)) {
        return false;
    }

    if (!$groupmode = groups_get_activity_groupmode($cm, $course)) {
        return true;
    }

    if (has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($groupmode == VISIBLEGROUPS) {
        if ($discussion->groupid == -1) {
            // allow students to reply to all participants discussions - this was not possible in Moodle <1.8
            return true;
        }
        return groups_is_member($discussion->groupid);
    } else {
        //separate groups
        if ($discussion->groupid == -1) {
            return false;
        }
        return groups_is_member($discussion->groupid);
    }
}

/**
 * Check to ensure a user can view a timed discussion.
 *
 * @param object $discussion
 * @param object $user
 * @param object $context
 * @return boolean returns true if they can view post, false otherwise
 */
function socialforum_user_can_see_timed_discussion($discussion, $user, $context) {
    global $CFG;

    // Check that the user can view a discussion that is normally hidden due to access times.
    if (!empty($CFG->socialforum_enabletimedposts)) {
        $time = time();
        if (($discussion->timestart != 0 && $discussion->timestart > $time) || ($discussion->timeend != 0 && $discussion->timeend < $time)) {
            if (!has_capability('mod/socialforum:viewhiddentimedposts', $context, $user->id)) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Check to ensure a user can view a group discussion.
 *
 * @param object $discussion
 * @param object $cm
 * @param object $context
 * @return boolean returns true if they can view post, false otherwise
 */
function socialforum_user_can_see_group_discussion($discussion, $cm, $context) {

    // If it's a grouped discussion, make sure the user is a member.
    if ($discussion->groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == SEPARATEGROUPS) {
            return groups_is_member($discussion->groupid) || has_capability('moodle/site:accessallgroups', $context);
        }
    }

    return true;
}

/**
 * @global object
 * @global object
 * @uses DEBUG_DEVELOPER
 * @param object $socialforum
 * @param object $discussion
 * @param object $context
 * @param object $user
 * @return bool
 */
function socialforum_user_can_see_discussion($socialforum, $discussion, $context, $user = NULL) {
    global $USER, $DB;

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    // retrieve objects (yuk)
    if (is_numeric($socialforum)) {
        debugging('missing full social forum', DEBUG_DEVELOPER);
        if (!$socialforum = $DB->get_record('socialforum', array('id' => $socialforum))) {
            return false;
        }
    }
    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('socialforum_discussions', array('id' => $discussion))) {
            return false;
        }
    }
    if (!$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $socialforum->course)) {
        print_error('invalidcoursemodule');
    }

    if (!has_capability('mod/socialforum:viewdiscussion', $context)) {
        return false;
    }

    if (!socialforum_user_can_see_timed_discussion($discussion, $user, $context)) {
        return false;
    }

    if (!socialforum_user_can_see_group_discussion($discussion, $cm, $context)) {
        return false;
    }

    return true;
}

/**
 * @global object
 * @global object
 * @param object $socialforum
 * @param object $discussion
 * @param object $post
 * @param object $user
 * @param object $cm
 * @return bool
 */
function socialforum_user_can_see_post($socialforum, $discussion, $post, $user = NULL, $cm = NULL) {
    global $CFG, $USER, $DB;

    // Context used throughout function.
    $modcontext = context_module::instance($cm->id);

    // retrieve objects (yuk)
    if (is_numeric($socialforum)) {
        debugging('missing full social forum', DEBUG_DEVELOPER);
        if (!$socialforum = $DB->get_record('socialforum', array('id' => $socialforum))) {
            return false;
        }
    }

    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('socialforum_discussions', array('id' => $discussion))) {
            return false;
        }
    }
    if (is_numeric($post)) {
        debugging('missing full post', DEBUG_DEVELOPER);
        if (!$post = $DB->get_record('socialforum_posts', array('id' => $post))) {
            return false;
        }
    }

    if (!isset($post->id) && isset($post->parent)) {
        $post->id = $post->parent;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $socialforum->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    $canviewdiscussion = !empty($cm->cache->caps['mod/socialforum:viewdiscussion']) || has_capability('mod/socialforum:viewdiscussion', $modcontext, $user->id);
    if (!$canviewdiscussion && !has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), context_user::instance($post->userid))) {
        return false;
    }

    if (isset($cm->uservisible)) {
        if (!$cm->uservisible) {
            return false;
        }
    } else {
        if (!\core_availability\info_module::is_user_visible($cm, $user->id, false)) {
            return false;
        }
    }

    if (!socialforum_user_can_see_timed_discussion($discussion, $user, $modcontext)) {
        return false;
    }

    if (!socialforum_user_can_see_group_discussion($discussion, $cm, $modcontext)) {
        return false;
    }

    return true;
}

/**
 * Prints the discussion view screen for a social forum.
 *
 * @global object
 * @global object
 * @param object $course The current course object.
 * @param object $socialforum Forum to be printed.
 * @param int $maxdiscussions .
 * @param string $displayformat The display format to use (optional).
 * @param string $sort Sort arguments for database query (optional).
 * @param int $groupmode Group mode of the social forum (optional).
 * @param void $unused (originally current group)
 * @param int $page Page mode, page to display (optional).
 * @param int $perpage The maximum number of discussions per page(optional)
 * @param boolean $subscriptionstatus Whether the user is currently subscribed to the discussion in some fashion.
 *
 */
function socialforum_print_latest_discussions($course, $socialforum, $maxdiscussions = -1, $displayformat = 'plain', $sort = '', $currentgroup = -1, $groupmode = -1, $page = -1, $perpage = 100, $cm = null) {
    global $CFG, $USER, $OUTPUT;
    if (!$cm) {
        if (!$cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $socialforum->course)) {
            print_error('invalidcoursemodule');
        }
    }
    $context = context_module::instance($cm->id);

    if (empty($sort)) {
        $sort = socialforum_get_default_sort_order();
    }

    $olddiscussionlink = false;

    // Sort out some defaults
    if ($perpage <= 0) {
        $perpage = 0;
        $page = -1;
    }

    if ($maxdiscussions == 0) {
        // all discussions - backwards compatibility
        $page = -1;
        $perpage = 0;
        if ($displayformat == 'plain') {
            $displayformat = 'header';  // Abbreviate display by default
        }
    } else if ($maxdiscussions > 0) {
        $page = -1;
        $perpage = $maxdiscussions;
    }

    $fullpost = false;
    if ($displayformat == 'plain') {
        $fullpost = true;
    }


    // Decide if current user is allowed to see ALL the current discussions or not
    // First check the group stuff
    if ($currentgroup == -1 or $groupmode == -1) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        $currentgroup = groups_get_activity_group($cm);
    }

    $groups = array(); //cache
    // If the user can post discussions, then this is a good place to put the
    // button for it. We do not show the button if we are showing site news
    // and the current user is a guest.

    $canstart = socialforum_user_can_post_discussion($socialforum, $currentgroup, $groupmode, $cm, $context);
    if (!$canstart and $socialforum->type !== 'news') {
        if (isguestuser() or ! isloggedin()) {
            $canstart = true;
        }
        if (!is_enrolled($context) and ! is_viewing($context)) {
            // allow guests and not-logged-in to see the button - they are prompted to log in after clicking the link
            // normal users with temporary guest access see this button too, they are asked to enrol instead
            // do not show the button to users with suspended enrolments here
            $canstart = enrol_selfenrol_available($course->id);
        }
    }

    // Get all the recent discussions we're allowed to see
    $getuserlastmodified = ($displayformat == 'header');
    if (!$discussions = socialforum_get_discussions($cm, $sort, $fullpost, null, $maxdiscussions, $getuserlastmodified, $page, $perpage)) {
        echo html_writer::start_div('socialforumnodiscuss');
        if ($socialforum->type == 'news') {
            echo '(' . get_string('nonews', 'mod_socialforum') . ')';
        } else if ($socialforum->type == 'qanda') {
            echo '(' . get_string('noquestions', 'mod_socialforum') . ')';
        } else {
            echo '(' . get_string('nodiscussions', 'mod_socialforum') . ')';
        }
        echo html_writer::end_div();
        return;
    }

    // If we want paging
    if ($page != -1) {
        ///Get the number of discussions found
        $numdiscussions = socialforum_get_discussions_count($cm);

        ///Show the paging bar
        echo $OUTPUT->paging_bar($numdiscussions, $page, $perpage, "view.php?f=$socialforum->id");
        if ($numdiscussions > 1000) {
            // saves some memory on sites with very large social forums
            $replies = socialforum_count_discussion_replies($socialforum->id, $sort, $maxdiscussions, $page, $perpage);
        } else {
            $replies = socialforum_count_discussion_replies($socialforum->id);
        }
    } else {
        $replies = socialforum_count_discussion_replies($socialforum->id);

        if ($maxdiscussions > 0 and $maxdiscussions <= count($discussions)) {
            $olddiscussionlink = true;
        }
    }

    $canviewparticipants = has_capability('moodle/course:viewparticipants', $context);
    $canviewhiddentimedposts = has_capability('mod/socialforum:viewhiddentimedposts', $context);

    $strdatestring = get_string('strftimerecentfull');

    // Check if the social forum is tracked.
    if ($cantrack = socialforum_tp_can_track_socialforums($socialforum)) {
        $socialforumtracked = socialforum_tp_is_tracked($socialforum);
    } else {
        $socialforumtracked = false;
    }

    if ($socialforumtracked) {
        $unreads = socialforum_get_discussions_unread($cm);
    } else {
        $unreads = array();
    }
    echo html_writer::start_div('page-separator');
    echo html_writer::div(get_string('topics', 'mod_socialforum'), 'page-separator__text');
    echo html_writer::end_div();
    if ($displayformat == 'header') {
        echo html_writer::start_tag('table', array(
            'cellspacing' => '0',
            'class' => 'generaltable forumheaderlist',
        ));
        echo html_writer::start_tag('thead');
        // Show controls header
        echo html_writer::start_tag('tr');
        echo html_writer::start_tag('th');
        echo socialforum_search_form($socialforum);
        echo html_writer::end_tag('th');
        echo html_writer::start_tag('th', array(
            'style' => 'text-align: right;',
        ));
        if ($canstart) {
            echo html_writer::start_div('singlebutton socialforumaddnew');
            echo html_writer::start_tag('form', array(
                'id' => 'newdiscussionform',
                'method' => 'get',
                'action' => '/mod/socialforum/post.php'
            ));
            echo html_writer::start_div();
            echo html_writer::empty_tag('input', array(
                'type' => 'hidden',
                'name' => 'socialforum',
                'value' => $socialforum->id,
            ));
            switch ($socialforum->type) {
                case 'news':
                case 'blog':
                    $buttonadd = get_string('addanewtopic', 'mod_socialforum');
                    break;
                case 'qanda':
                    $buttonadd = get_string('addanewquestion', 'mod_socialforum');
                    break;
                default:
                    $buttonadd = get_string('addanewdiscussion', 'mod_socialforum');
                    break;
            }
            echo html_writer::empty_tag('input', array(
                'type' => 'submit',
                'value' => $buttonadd,
            ));
            echo html_writer::end_div();
            echo html_writer::end_tag('form');
            echo html_writer::end_div();
        } else if (isguestuser() or ! isloggedin() or $socialforum->type == 'news' or
                $socialforum->type == 'qanda' and ! has_capability('mod/socialforum:addquestion', $context) or
                $socialforum->type != 'qanda' and ! has_capability('mod/socialforum:startdiscussion', $context)) {
            // no button and no info
        } else if ($groupmode and ! has_capability('moodle/site:accessallgroups', $context)) {
            // inform users why they can not post new discussion
            if (!$currentgroup) {
                echo $OUTPUT->notification(get_string('cannotadddiscussionall', 'mod_socialforum'));
            } else if (!groups_is_member($currentgroup)) {
                echo $OUTPUT->notification(get_string('cannotadddiscussion', 'mod_socialforum'));
            }
        }
        echo html_writer::end_tag('th');
        echo html_writer::end_tag('tr');
        // Show discussion headers
        /*
          echo html_writer::start_tag('tr');
          // Show topic header
          echo html_writer::tag('th', get_string('discussion', 'mod_socialforum'), array(
          'class' => 'header topic',
          'scope' => 'col',
          ));
          // Show group header
          if ($groupmode > 0) {
          echo html_writer::tag('th', get_string('group'), array(
          'class' => 'header group',
          'scope' => 'col',
          ));
          }
          if (has_capability('mod/socialforum:viewdiscussion', $context)) {
          // Show replies header
          echo html_writer::tag('th', get_string('replies', 'mod_socialforum'), array(
          'class' => 'header replies',
          'scope' => 'col',
          ));
          // If the social forum can be tracked, display the unread column.
          if ($cantrack) {
          // Show tracking header
          echo html_writer::start_tag('th', array(
          'class' => 'header tracking',
          'scope' => 'col',
          ));
          echo get_string('unread', 'mod_socialforum');
          $markasreadicon = html_writer::empty_tag('img', array(
          'src' => $OUTPUT->image_url('t/markasread'),
          'class' => 'iconsmall',
          'alt' => get_string('markallread', 'mod_socialforum'),
          ));
          if ($socialforumtracked) {
          echo html_writer::tag('a', $markasreadicon, array(
          'title' => get_string('markallread', 'mod_socialforum'),
          'href' => '/mod/socialforum/markposts.php?f=' .
          $socialforum->id . '&amp;mark=read&amp;returnpage=view.php&amp;sesskey=' . sesskey(),
          ));
          }
          echo html_writer::end_tag('th');
          }
          }
          echo html_writer::end_tag('tr');
         */
        echo html_writer::end_tag('thead');
    }

    foreach ($discussions as $discussion) {

        if (!empty($replies[$discussion->discussion])) {
            $discussion->replies = $replies[$discussion->discussion]->replies;
            $discussion->lastpostid = $replies[$discussion->discussion]->lastpostid;
        } else {
            $discussion->replies = 0;
        }

        // SPECIAL CASE: The front page can display a news item post to non-logged in users.
        // All posts are read in this case.
        if (!$socialforumtracked) {
            $discussion->unread = '-';
        } else if (empty($USER)) {
            $discussion->unread = 0;
        } else {
            if (empty($unreads[$discussion->discussion])) {
                $discussion->unread = 0;
            } else {
                $discussion->unread = $unreads[$discussion->discussion];
            }
        }

        if (isloggedin()) {
            $ownpost = ($discussion->userid == $USER->id);
        } else {
            $ownpost = false;
        }
        // Use discussion name instead of subject of first post.
        $discussion->subject = $discussion->name;

        switch ($displayformat) {
            case 'header':
                if ($groupmode > 0) {
                    if (isset($groups[$discussion->groupid])) {
                        $group = $groups[$discussion->groupid];
                    } else {
                        $group = $groups[$discussion->groupid] = groups_get_group($discussion->groupid);
                    }
                } else {
                    $group = -1;
                }
                socialforum_print_discussion_header($discussion, $socialforum, $group, $strdatestring, $cantrack, $socialforumtracked, $canviewparticipants, $context, $canviewhiddentimedposts);
                break;
            default:
                $link = false;

                if ($discussion->replies) {
                    $link = true;
                } else {
                    $modcontext = context_module::instance($cm->id);
                    $link = socialforum_user_can_see_discussion($socialforum, $discussion, $modcontext, $USER);
                }

                $discussion->socialforum = $socialforum->id;

                socialforum_print_post($discussion, $discussion, $socialforum, $cm, $course, $ownpost, 0, $link, false, '', null, true, $socialforumtracked);
                break;
        }
    }

    if ($displayformat == "header") {
        echo html_writer::end_tag('tbody');
        echo html_writer::start_tag('tfoot');
        echo html_writer::start_tag('tr', array(
            'style' => 'border: none !important;'
        ));
        echo html_writer::start_tag('td');
        echo html_writer::tag('span', '');
        echo html_writer::end_tag('td');
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('tfoot');
        echo html_writer::end_tag('table');
    }

    if ($olddiscussionlink) {
        if ($socialforum->type == 'news') {
            $strolder = get_string('oldertopics', 'mod_socialforum');
        } else {
            $strolder = get_string('olderdiscussions', 'mod_socialforum');
        }
        echo html_writer::start_div('forumolddiscuss');
        echo html_writer::tag('a', $strolder, array(
            'href' => '/mod/socialforum/view.php?f=' . $socialforum->id . '&amp;showall=1',
            'class' => 'btn',
        ));
        echo html_writer::end_div();
    }

    if ($page != -1) { ///Show the paging bar
        echo $OUTPUT->paging_bar($numdiscussions, $page, $perpage, "view.php?f=$socialforum->id");
    }
}

/**
 * Prints a social forum discussion
 *
 * @uses CONTEXT_MODULE
 * @uses SOCIALFORUM_MODE_NESTED
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $socialforum
 * @param stdClass $discussion
 * @param stdClass $post
 * @param int $mode
 * @param mixed $canreply
 * @param bool $canrate
 */
function socialforum_print_discussion($course, $cm, $socialforum, $discussion, $post, $mode, $canreply = NULL, $canrate = false) {
    global $USER, $CFG;

    require_once($CFG->dirroot . '/rating/lib.php');

    $ownpost = (isloggedin() && $USER->id == $post->userid);

    $modcontext = context_module::instance($cm->id);
    if ($canreply === NULL) {
        $reply = socialforum_user_can_post($socialforum, $discussion, $USER, $cm, $course, $modcontext);
    } else {
        $reply = $canreply;
    }

    // $cm holds general cache for social forum functions
    $cm->cache = new stdClass;
    $cm->cache->groups = groups_get_all_groups($course->id, 0, $cm->groupingid);
    $cm->cache->usersgroups = array();

    $posters = array();

    $sort = "p.created DESC";

    $socialforumtracked = socialforum_tp_is_tracked($socialforum);
    $posts = socialforum_get_all_discussion_posts($discussion->id, $sort, $socialforumtracked);
    $post = $posts[$post->id];

    foreach ($posts as $pid => $p) {
        $posters[$p->userid] = $p->userid;
    }

    // preload all groups of ppl that posted in this discussion
    if ($postersgroups = groups_get_all_groups($course->id, $posters, $cm->groupingid, 'gm.id, gm.groupid, gm.userid')) {
        foreach ($postersgroups as $pg) {
            if (!isset($cm->cache->usersgroups[$pg->userid])) {
                $cm->cache->usersgroups[$pg->userid] = array();
            }
            $cm->cache->usersgroups[$pg->userid][$pg->groupid] = $pg->groupid;
        }
        unset($postersgroups);
    }

    //load ratings
    if ($socialforum->assessed != RATING_AGGREGATE_NONE) {
        $ratingoptions = new stdClass;
        $ratingoptions->context = $modcontext;
        $ratingoptions->component = 'mod_socialforum';
        $ratingoptions->ratingarea = 'post';
        $ratingoptions->items = $posts;
        $ratingoptions->aggregate = $socialforum->assessed; //the aggregation method
        $ratingoptions->scaleid = $socialforum->scale;
        $ratingoptions->userid = $USER->id;
        if ($socialforum->type == 'single' or ! $discussion->id) {
            $ratingoptions->returnurl = "$CFG->wwwroot/mod/socialforum/view.php?id=$cm->id";
        } else {
            $ratingoptions->returnurl = "$CFG->wwwroot/mod/socialforum/discuss.php?d=$discussion->id";
        }
        $ratingoptions->assesstimestart = $socialforum->assesstimestart;
        $ratingoptions->assesstimefinish = $socialforum->assesstimefinish;

        $rm = new rating_manager();
        $posts = $rm->get_ratings($ratingoptions);
    }


    $post->socialforum = $socialforum->id;   // Add the social forum id to the post object, later used by socialforum_print_post
    $post->socialforumtype = $socialforum->type;

    $post->subject = format_string($post->subject);

    $postread = !empty($post->postread);
    socialforum_print_post($post, $discussion, $socialforum, $cm, $course, $ownpost, $reply, false, '', '', $postread, true, $socialforumtracked);
    socialforum_print_posts_nested($course, $cm, $socialforum, $discussion, $post, $reply, $socialforumtracked, $posts, 1);
}

/**
 * @todo Document this function
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @return void
 */
function socialforum_print_posts_threaded($course, &$cm, $socialforum, $discussion, $parent, $depth, $reply, $socialforumtracked, $posts) {
    global $USER, $CFG;

    $link = false;

    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;

        $modcontext = context_module::instance($cm->id);
        $canviewfullnames = has_capability('moodle/site:viewfullnames', $modcontext);

        foreach ($posts as $post) {

            echo '<div class="indent">';
            if ($depth > 0) {
                $ownpost = ($USER->id == $post->userid);
                $post->subject = format_string($post->subject);

                $postread = !empty($post->postread);

                socialforum_print_post($post, $discussion, $socialforum, $cm, $course, $ownpost, $reply, $link, '', '', $postread, true, $socialforumtracked);
            } else {
                if (!socialforum_user_can_see_post($socialforum, $discussion, $post, NULL, $cm)) {
                    echo "</div>\n";
                    continue;
                }
                $by = new stdClass();
                $by->name = fullname($post, $canviewfullnames);
                $by->date = userdate($post->modified);

                if ($socialforumtracked) {
                    if (!empty($post->postread)) {
                        $style = '<span class="socialforumthread read">';
                    } else {
                        $style = '<span class="socialforumthread unread">';
                    }
                } else {
                    $style = '<span class="socialforumthread">';
                }
                echo $style . "<a name=\"$post->id\"></a>" .
                "<a href=\"discuss.php?d=$post->discussion&amp;parent=$post->id\">" . format_string($post->subject, true) . "</a> ";
                print_string("bynameondate", "socialforum", $by);
                echo "</span>";
            }

            socialforum_print_posts_threaded($course, $cm, $socialforum, $discussion, $post, $depth - 1, $reply, $socialforumtracked, $posts);
            echo "</div>\n";
        }
    }
}

/**
 * @todo Document this function
 * @global object
 * @global object
 * @return void
 */
function socialforum_print_posts_nested($course, &$cm, $socialforum, $discussion, $parent, $reply, $socialforumtracked, $posts, $level = 0) {
    global $USER, $CFG;

    $link = false;
    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;
        foreach ($posts as $post) {
            $indentlevel = 32 * $level;
            $indentclass = 'ml-' . $indentlevel . 'pt';
            echo html_writer::start_div('reply-post indent ' . $indentclass);
            if (!isloggedin()) {
                $ownpost = false;
            } else {
                $ownpost = ($USER->id == $post->userid);
            }
            $post->subject = format_string($post->subject);
            $postread = !empty($post->postread);
            socialforum_print_post($post, $discussion, $socialforum, $cm, $course, $ownpost, $reply, $link, '', '', $postread, true, $socialforumtracked);
            socialforum_print_posts_nested($course, $cm, $socialforum, $discussion, $post, $reply, $socialforumtracked, $posts, $level + 1);
            echo html_writer::end_div();
        }
    }
}

/**
 * Returns all social forum posts since a given time in specified social forum.
 *
 * @todo Document this functions args
 * @global object
 * @global object
 * @global object
 * @global object
 */
function socialforum_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid = 0, $groupid = 0) {
    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id' => $courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];
    $params = array($timestart, $cm->instance);

    if ($userid) {
        $userselect = "AND u.id = ?";
        $params[] = $userid;
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = "AND d.groupid = ?";
        $params[] = $groupid;
    } else {
        $groupselect = "";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    if (!$posts = $DB->get_records_sql("SELECT p.*, f.type AS socialforumtype, d.socialforum, d.groupid,
                                              d.timestart, d.timeend, d.userid AS duserid,
                                              $allnames, u.email, u.picture, u.imagealt, u.email
                                         FROM {socialforum_posts} p
                                              JOIN {socialforum_discussions} d ON d.id = p.discussion
                                              JOIN {socialforum} f             ON f.id = d.socialforum
                                              JOIN {user} u              ON u.id = p.userid
                                        WHERE p.created > ? AND f.id = ?
                                              $userselect $groupselect
                                     ORDER BY p.id ASC", $params)) { // order by initial posting date
        return;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);
    $cm_context = context_module::instance($cm->id);
    $viewhiddentimed = has_capability('mod/socialforum:viewhiddentimedposts', $cm_context);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cm_context);

    $printposts = array();
    foreach ($posts as $post) {

        if (!empty($CFG->socialforum_enabletimedposts) and $USER->id != $post->duserid
                and ( ($post->timestart > 0 and $post->timestart > time()) or ( $post->timeend > 0 and $post->timeend < time()))) {
            if (!$viewhiddentimed) {
                continue;
            }
        }

        if ($groupmode) {
            if ($post->groupid == -1 or $groupmode == VISIBLEGROUPS or $accessallgroups) {
                // oki (Open discussions have groupid -1)
            } else {
                // separate mode
                if (isguestuser()) {
                    // shortcut
                    continue;
                }

                if (!in_array($post->groupid, $modinfo->get_groups($cm->groupingid))) {
                    continue;
                }
            }
        }

        $printposts[] = $post;
    }

    if (!$printposts) {
        return;
    }

    $aname = format_string($cm->name, true);

    foreach ($printposts as $post) {
        $tmpactivity = new stdClass();

        $tmpactivity->type = 'socialforum';
        $tmpactivity->cmid = $cm->id;
        $tmpactivity->name = $aname;
        $tmpactivity->sectionnum = $cm->sectionnum;
        $tmpactivity->timestamp = $post->modified;

        $tmpactivity->content = new stdClass();
        $tmpactivity->content->id = $post->id;
        $tmpactivity->content->discussion = $post->discussion;
        $tmpactivity->content->subject = format_string($post->subject);
        $tmpactivity->content->parent = $post->parent;

        $tmpactivity->user = new stdClass();
        $additionalfields = array('id' => 'userid', 'picture', 'imagealt', 'email');
        $additionalfields = explode(',', user_picture::fields());
        $tmpactivity->user = username_load_fields_from_object($tmpactivity->user, $post, null, $additionalfields);
        $tmpactivity->user->id = $post->userid;

        $activities[$index++] = $tmpactivity;
    }

    return;
}

/**
 * @todo Document this function
 * @global object
 */
function socialforum_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    global $CFG, $OUTPUT;

    if ($activity->content->parent) {
        $class = 'reply';
    } else {
        $class = 'discussion';
    }

    echo '<table border="0" cellpadding="3" cellspacing="0" class="socialforum-recent">';

    echo "<tr><td class=\"userpicture\" valign=\"top\">";
    echo $OUTPUT->user_picture($activity->user, array('courseid' => $courseid));
    echo "</td><td class=\"$class\">";

    if ($activity->content->parent) {
        $class = 'title';
    } else {
        // Bold the title of new discussions so they stand out.
        $class = 'title bold';
    }
    echo "<div class=\"{$class}\">";
    if ($detail) {
        $aname = s($activity->name);
        echo "<img src=\"" . $OUTPUT->image_url('icon', $activity->type) . "\" " .
        "class=\"icon\" alt=\"{$aname}\" />";
    }
    echo "<a href=\"$CFG->wwwroot/mod/socialforum/discuss.php?d={$activity->content->discussion}"
    . "#p{$activity->content->id}\">{$activity->content->subject}</a>";
    echo '</div>';

    echo '<div class="user">';
    $fullname = fullname($activity->user, $viewfullnames);
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">"
    . "{$fullname}</a> - " . userdate($activity->timestamp);
    echo '</div>';
    echo "</td></tr></table>";

    return;
}

/**
 * recursively sets the discussion field to $discussionid on $postid and all its children
 * used when pruning a post
 *
 * @global object
 * @param int $postid
 * @param int $discussionid
 * @return bool
 */
function socialforum_change_discussionid($postid, $discussionid) {
    global $DB;
    $DB->set_field('socialforum_posts', 'discussion', $discussionid, array('id' => $postid));
    if ($posts = $DB->get_records('socialforum_posts', array('parent' => $postid))) {
        foreach ($posts as $post) {
            socialforum_change_discussionid($post->id, $discussionid);
        }
    }
    return true;
}

/**
 * Prints the editing button on subscribers page
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param int $socialforumid
 * @return string
 */
function socialforum_update_subscriptions_button($courseid, $socialforumid) {
    global $CFG, $USER;

    if (!empty($USER->subscriptionsediting)) {
        $string = get_string('turneditingoff');
        $edit = "off";
    } else {
        $string = get_string('turneditingon');
        $edit = "on";
    }

    return "<form method=\"get\" action=\"$CFG->wwwroot/mod/socialforum/subscribers.php\">" .
            "<input type=\"hidden\" name=\"id\" value=\"$socialforumid\" />" .
            "<input type=\"hidden\" name=\"edit\" value=\"$edit\" />" .
            "<input type=\"submit\" value=\"$string\" /></form>";
}

// Functions to do with read tracking.

/**
 * Mark posts as read.
 *
 * @global object
 * @global object
 * @param object $user object
 * @param array $postids array of post ids
 * @return boolean success
 */
function socialforum_tp_mark_posts_read($user, $postids) {
    global $CFG, $DB;

    if (!socialforum_tp_can_track_socialforums(false, $user)) {
        return true;
    }

    $status = true;

    $now = time();
    $cutoffdate = $now - ($CFG->socialforum_oldpostdays * 24 * 3600);

    if (empty($postids)) {
        return true;
    } else if (count($postids) > 200) {
        while ($part = array_splice($postids, 0, 200)) {
            $status = socialforum_tp_mark_posts_read($user, $part) && $status;
        }
        return $status;
    }

    list($usql, $postidparams) = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED, 'postid');

    $insertparams = array(
        'userid1' => $user->id,
        'userid2' => $user->id,
        'userid3' => $user->id,
        'firstread' => $now,
        'lastread' => $now,
        'cutoffdate' => $cutoffdate,
    );
    $params = array_merge($postidparams, $insertparams);

    if ($CFG->socialforum_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = " . SOCIALFORUM_TRACKING_FORCED . "
                        OR (f.trackingtype = " . SOCIALFORUM_TRACKING_OPTIONAL . " AND tf.id IS NULL))";
    } else {
        $trackingsql = "AND ((f.trackingtype = " . SOCIALFORUM_TRACKING_OPTIONAL . "  OR f.trackingtype = " . SOCIALFORUM_TRACKING_FORCED . ")
                            AND tf.id IS NULL)";
    }

    // First insert any new entries.
    $sql = "INSERT INTO {socialforum_read} (userid, postid, discussionid, socialforumid, firstread, lastread)

            SELECT :userid1, p.id, p.discussion, d.socialforum, :firstread, :lastread
                FROM {socialforum_posts} p
                    JOIN {socialforum_discussions} d       ON d.id = p.discussion
                    JOIN {socialforum} f                   ON f.id = d.socialforum
                    LEFT JOIN {socialforum_track_prefs} tf ON (tf.userid = :userid2 AND tf.socialforumid = f.id)
                    LEFT JOIN {socialforum_read} fr        ON (
                            fr.userid = :userid3
                        AND fr.postid = p.id
                        AND fr.discussionid = d.id
                        AND fr.socialforumid = f.id
                    )
                WHERE p.id $usql
                    AND p.modified >= :cutoffdate
                    $trackingsql
                    AND fr.id IS NULL";

    $status = $DB->execute($sql, $params) && $status;

    // Then update all records.
    $updateparams = array(
        'userid' => $user->id,
        'lastread' => $now,
    );
    $params = array_merge($postidparams, $updateparams);
    $status = $DB->set_field_select('socialforum_read', 'lastread', $now, '
                userid      =  :userid
            AND lastread    <> :lastread
            AND postid      ' . $usql, $params) && $status;

    return $status;
}

/**
 * Mark post as read.
 * @global object
 * @global object
 * @param int $userid
 * @param int $postid
 */
function socialforum_tp_add_read_record($userid, $postid) {
    global $CFG, $DB;

    $now = time();
    $cutoffdate = $now - ($CFG->socialforum_oldpostdays * 24 * 3600);

    if (!$DB->record_exists('socialforum_read', array('userid' => $userid, 'postid' => $postid))) {
        $sql = "INSERT INTO {socialforum_read} (userid, postid, discussionid, socialforumid, firstread, lastread)

                SELECT ?, p.id, p.discussion, d.socialforum, ?, ?
                  FROM {socialforum_posts} p
                       JOIN {socialforum_discussions} d ON d.id = p.discussion
                 WHERE p.id = ? AND p.modified >= ?";
        return $DB->execute($sql, array($userid, $now, $now, $postid, $cutoffdate));
    } else {
        $sql = "UPDATE {socialforum_read}
                   SET lastread = ?
                 WHERE userid = ? AND postid = ?";
        return $DB->execute($sql, array($now, $userid, $userid));
    }
}

/**
 * If its an old post, do nothing. If the record exists, the maintenance will clear it up later.
 *
 * @return bool
 */
function socialforum_tp_mark_post_read($userid, $post, $socialforumid) {
    if (!socialforum_tp_is_post_old($post)) {
        return socialforum_tp_add_read_record($userid, $post->id);
    } else {
        return true;
    }
}

/**
 * Marks a whole social forum as read, for a given user
 *
 * @global object
 * @global object
 * @param object $user
 * @param int $socialforumid
 * @param int|bool $groupid
 * @return bool
 */
function socialforum_tp_mark_socialforum_read($user, $socialforumid, $groupid = false) {
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->socialforum_oldpostdays * 24 * 60 * 60);

    $groupsel = "";
    $params = array($user->id, $socialforumid, $cutoffdate);

    if ($groupid !== false) {
        $groupsel = " AND (d.groupid = ? OR d.groupid = -1)";
        $params[] = $groupid;
    }

    $sql = "SELECT p.id
              FROM {socialforum_posts} p
                   LEFT JOIN {socialforum_discussions} d ON d.id = p.discussion
                   LEFT JOIN {socialforum_read} r        ON (r.postid = p.id AND r.userid = ?)
             WHERE d.socialforum = ?
                   AND p.modified >= ? AND r.id is NULL
                   $groupsel";

    if ($posts = $DB->get_records_sql($sql, $params)) {
        $postids = array_keys($posts);
        return socialforum_tp_mark_posts_read($user, $postids);
    }

    return true;
}

/**
 * Marks a whole discussion as read, for a given user
 *
 * @global object
 * @global object
 * @param object $user
 * @param int $discussionid
 * @return bool
 */
function socialforum_tp_mark_discussion_read($user, $discussionid) {
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->socialforum_oldpostdays * 24 * 60 * 60);

    $sql = "SELECT p.id
              FROM {socialforum_posts} p
                   LEFT JOIN {socialforum_read} r ON (r.postid = p.id AND r.userid = ?)
             WHERE p.discussion = ?
                   AND p.modified >= ? AND r.id is NULL";

    if ($posts = $DB->get_records_sql($sql, array($user->id, $discussionid, $cutoffdate))) {
        $postids = array_keys($posts);
        return socialforum_tp_mark_posts_read($user, $postids);
    }

    return true;
}

/**
 * @global object
 * @param int $userid
 * @param object $post
 */
function socialforum_tp_is_post_read($userid, $post) {
    global $DB;
    return (socialforum_tp_is_post_old($post) ||
            $DB->record_exists('socialforum_read', array('userid' => $userid, 'postid' => $post->id)));
}

/**
 * @global object
 * @param object $post
 * @param int $time Defautls to time()
 */
function socialforum_tp_is_post_old($post, $time = null) {
    global $CFG;

    if (is_null($time)) {
        $time = time();
    }
    return ($post->modified < ($time - ($CFG->socialforum_oldpostdays * 24 * 3600)));
}

/**
 * Returns the count of records for the provided user and course.
 * Please note that group access is ignored!
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $courseid
 * @return array
 */
function socialforum_tp_get_course_unread_posts($userid, $courseid) {
    global $CFG, $DB;

    $now = round(time(), -2); // DB cache friendliness.
    $cutoffdate = $now - ($CFG->socialforum_oldpostdays * 24 * 60 * 60);
    $params = array($userid, $userid, $courseid, $cutoffdate, $userid);

    if (!empty($CFG->socialforum_enabletimedposts)) {
        $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    if ($CFG->socialforum_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = " . SOCIALFORUM_TRACKING_FORCED . "
                            OR (f.trackingtype = " . SOCIALFORUM_TRACKING_OPTIONAL . " AND tf.id IS NULL
                                AND (SELECT trackforums FROM {user} WHERE id = ?) = 1))";
    } else {
        $trackingsql = "AND ((f.trackingtype = " . SOCIALFORUM_TRACKING_OPTIONAL . " OR f.trackingtype = " . SOCIALFORUM_TRACKING_FORCED . ")
                            AND tf.id IS NULL
                            AND (SELECT trackforums FROM {user} WHERE id = ?) = 1)";
    }

    $sql = "SELECT f.id, COUNT(p.id) AS unread
              FROM {socialforum_posts} p
                   JOIN {socialforum_discussions} d       ON d.id = p.discussion
                   JOIN {socialforum} f                   ON f.id = d.socialforum
                   JOIN {course} c                  ON c.id = f.course
                   LEFT JOIN {socialforum_read} r         ON (r.postid = p.id AND r.userid = ?)
                   LEFT JOIN {socialforum_track_prefs} tf ON (tf.userid = ? AND tf.socialforumid = f.id)
             WHERE f.course = ?
                   AND p.modified >= ? AND r.id is NULL
                   $trackingsql
                   $timedsql
          GROUP BY f.id";

    if ($return = $DB->get_records_sql($sql, $params)) {
        return $return;
    }

    return array();
}

/**
 * Returns the count of records for the provided user and social forum and [optionally] group.
 *
 * @global object
 * @global object
 * @global object
 * @param object $cm
 * @param object $course
 * @return int
 */
function socialforum_tp_count_socialforum_unread_posts($cm, $course) {
    global $CFG, $USER, $DB;

    static $readcache = array();

    $socialforumid = $cm->instance;

    if (!isset($readcache[$course->id])) {
        $readcache[$course->id] = array();
        if ($counts = socialforum_tp_get_course_unread_posts($USER->id, $course->id)) {
            foreach ($counts as $count) {
                $readcache[$course->id][$count->id] = $count->unread;
            }
        }
    }

    if (empty($readcache[$course->id][$socialforumid])) {
        // no need to check group mode ;-)
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $readcache[$course->id][$socialforumid];
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
        return $readcache[$course->id][$socialforumid];
    }

    require_once($CFG->dirroot . '/course/lib.php');

    $modinfo = get_fast_modinfo($course);

    $mygroups = $modinfo->get_groups($cm->groupingid);

    // add all groups posts
    $mygroups[-1] = -1;

    list ($groups_sql, $groups_params) = $DB->get_in_or_equal($mygroups);

    $now = round(time(), -2); // db cache friendliness
    $cutoffdate = $now - ($CFG->socialforum_oldpostdays * 24 * 60 * 60);
    $params = array($USER->id, $socialforumid, $cutoffdate);

    if (!empty($CFG->socialforum_enabletimedposts)) {
        $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    $params = array_merge($params, $groups_params);

    $sql = "SELECT COUNT(p.id)
              FROM {socialforum_posts} p
                   JOIN {socialforum_discussions} d ON p.discussion = d.id
                   LEFT JOIN {socialforum_read} r   ON (r.postid = p.id AND r.userid = ?)
             WHERE d.socialforum = ?
                   AND p.modified >= ? AND r.id is NULL
                   $timedsql
                   AND d.groupid $groups_sql";

    return $DB->get_field_sql($sql, $params);
}

/**
 * Deletes read records for the specified index. At least one parameter must be specified.
 *
 * @global object
 * @param int $userid
 * @param int $postid
 * @param int $discussionid
 * @param int $socialforumid
 * @return bool
 */
function socialforum_tp_delete_read_records($userid = -1, $postid = -1, $discussionid = -1, $socialforumid = -1) {
    global $DB;
    $params = array();

    $select = '';
    if ($userid > -1) {
        if ($select != '')
            $select .= ' AND ';
        $select .= 'userid = ?';
        $params[] = $userid;
    }
    if ($postid > -1) {
        if ($select != '')
            $select .= ' AND ';
        $select .= 'postid = ?';
        $params[] = $postid;
    }
    if ($discussionid > -1) {
        if ($select != '')
            $select .= ' AND ';
        $select .= 'discussionid = ?';
        $params[] = $discussionid;
    }
    if ($socialforumid > -1) {
        if ($select != '')
            $select .= ' AND ';
        $select .= 'socialforumid = ?';
        $params[] = $socialforumid;
    }
    if ($select == '') {
        return false;
    } else {
        return $DB->delete_records_select('socialforum_read', $select, $params);
    }
}

/**
 * Get a list of social forums not tracked by the user.
 *
 * @global object
 * @global object
 * @param int $userid The id of the user to use.
 * @param int $courseid The id of the course being checked.
 * @return mixed An array indexed by social forum id, or false.
 */
function socialforum_tp_get_untracked_socialforums($userid, $courseid) {
    global $CFG, $DB;

    if ($CFG->socialforum_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = " . SOCIALFORUM_TRACKING_OFF . "
                            OR (f.trackingtype = " . SOCIALFORUM_TRACKING_OPTIONAL . " AND (ft.id IS NOT NULL
                                OR (SELECT trackforums FROM {user} WHERE id = ?) = 0)))";
    } else {
        $trackingsql = "AND (f.trackingtype = " . SOCIALFORUM_TRACKING_OFF . "
                            OR ((f.trackingtype = " . SOCIALFORUM_TRACKING_OPTIONAL . " OR f.trackingtype = " . SOCIALFORUM_TRACKING_FORCED . ")
                                AND (ft.id IS NOT NULL
                                    OR (SELECT trackforums FROM {user} WHERE id = ?) = 0)))";
    }

    $sql = "SELECT f.id
              FROM {socialforum} f
                   LEFT JOIN {socialforum_track_prefs} ft ON (ft.socialforumid = f.id AND ft.userid = ?)
             WHERE f.course = ?
                   $trackingsql";

    if ($socialforums = $DB->get_records_sql($sql, array($userid, $courseid, $userid))) {
        foreach ($socialforums as $socialforum) {
            $socialforums[$socialforum->id] = $socialforum;
        }
        return $socialforums;
    } else {
        return array();
    }
}

/**
 * Determine if a user can track social forums and optionally a particular social forum.
 * Checks the site settings, the user settings and the social forum settings (if
 * requested).
 *
 * @global object
 * @global object
 * @global object
 * @param mixed $socialforum The social forum object to test, or the int id (optional).
 * @param mixed $userid The user object to check for (optional).
 * @return boolean
 */
function socialforum_tp_can_track_socialforums($socialforum = false, $user = false) {
    global $USER, $CFG, $DB;

    // if possible, avoid expensive
    // queries
    if (empty($CFG->socialforum_trackreadposts)) {
        return false;
    }

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    if ($socialforum === false) {
        if ($CFG->socialforum_allowforcedreadtracking) {
            // Since we can force tracking, assume yes without a specific social forum.
            return true;
        } else {
            return (bool) $user->trackforums;
        }
    }

    // Work toward always passing an object...
    if (is_numeric($socialforum)) {
        debugging('Better use proper social forum object.', DEBUG_DEVELOPER);
        $socialforum = $DB->get_record('socialforum', array('id' => $socialforum), '', 'id,trackingtype');
    }

    $socialforumallows = ($socialforum->trackingtype == SOCIALFORUM_TRACKING_OPTIONAL);
    $socialforumforced = ($socialforum->trackingtype == SOCIALFORUM_TRACKING_FORCED);

    if ($CFG->socialforum_allowforcedreadtracking) {
        // If we allow forcing, then forced social forums takes procidence over user setting.
        return ($socialforumforced || ($socialforumallows && (!empty($user->trackforums) && (bool) $user->trackforums)));
    } else {
        // If we don't allow forcing, user setting trumps.
        return ($socialforumforced || $socialforumallows) && !empty($user->trackforums);
    }
}

/**
 * Tells whether a specific social forum is tracked by the user. A user can optionally
 * be specified. If not specified, the current user is assumed.
 *
 * @global object
 * @global object
 * @global object
 * @param mixed $socialforum If int, the id of the social forum being checked; if object, the social forum object
 * @param int $userid The id of the user being checked (optional).
 * @return boolean
 */
function socialforum_tp_is_tracked($socialforum, $user = false) {
    global $USER, $CFG, $DB;

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    // Work toward always passing an object...
    if (is_numeric($socialforum)) {
        debugging('Better use proper social forum object.', DEBUG_DEVELOPER);
        $socialforum = $DB->get_record('socialforum', array('id' => $socialforum));
    }

    if (!socialforum_tp_can_track_socialforums($socialforum, $user)) {
        return false;
    }

    $socialforumallows = ($socialforum->trackingtype == SOCIALFORUM_TRACKING_OPTIONAL);
    $socialforumforced = ($socialforum->trackingtype == SOCIALFORUM_TRACKING_FORCED);
    $userpref = $DB->get_record('socialforum_track_prefs', array('userid' => $user->id, 'socialforumid' => $socialforum->id));

    if ($CFG->socialforum_allowforcedreadtracking) {
        return $socialforumforced || ($socialforumallows && $userpref === false);
    } else {
        return ($socialforumallows || $socialforumforced) && $userpref === false;
    }
}

/**
 * @global object
 * @global object
 * @param int $socialforumid
 * @param int $userid
 */
function socialforum_tp_start_tracking($socialforumid, $userid = false) {
    global $USER, $DB;

    if ($userid === false) {
        $userid = $USER->id;
    }

    return $DB->delete_records('socialforum_track_prefs', array('userid' => $userid, 'socialforumid' => $socialforumid));
}

/**
 * @global object
 * @global object
 * @param int $socialforumid
 * @param int $userid
 */
function socialforum_tp_stop_tracking($socialforumid, $userid = false) {
    global $USER, $DB;

    if ($userid === false) {
        $userid = $USER->id;
    }

    if (!$DB->record_exists('socialforum_track_prefs', array('userid' => $userid, 'socialforumid' => $socialforumid))) {
        $track_prefs = new stdClass();
        $track_prefs->userid = $userid;
        $track_prefs->socialforumid = $socialforumid;
        $DB->insert_record('socialforum_track_prefs', $track_prefs);
    }

    return socialforum_tp_delete_read_records($userid, -1, -1, $socialforumid);
}

/**
 * Clean old records from the socialforum_read table.
 * @global object
 * @global object
 * @return void
 */
function socialforum_tp_clean_read_records() {
    global $CFG, $DB;

    if (!isset($CFG->socialforum_oldpostdays)) {
        return;
    }
// Look for records older than the cutoffdate that are still in the socialforum_read table.
    $cutoffdate = time() - ($CFG->socialforum_oldpostdays * 24 * 60 * 60);

    //first get the oldest tracking present - we need tis to speedup the next delete query
    $sql = "SELECT MIN(fp.modified) AS first
              FROM {socialforum_posts} fp
                   JOIN {socialforum_read} fr ON fr.postid=fp.id";
    if (!$first = $DB->get_field_sql($sql)) {
        // nothing to delete;
        return;
    }

    // now delete old tracking info
    $sql = "DELETE
              FROM {socialforum_read}
             WHERE postid IN (SELECT fp.id
                                FROM {socialforum_posts} fp
                               WHERE fp.modified >= ? AND fp.modified < ?)";
    $DB->execute($sql, array($first, $cutoffdate));
}

/**
 * Sets the last post for a given discussion
 *
 * @global object
 * @global object
 * @param into $discussionid
 * @return bool|int
 * */
function socialforum_discussion_update_last_post($discussionid) {
    global $CFG, $DB;

// Check the given discussion exists
    if (!$DB->record_exists('socialforum_discussions', array('id' => $discussionid))) {
        return false;
    }

// Use SQL to find the last post for this discussion
    $sql = "SELECT id, userid, modified
              FROM {socialforum_posts}
             WHERE discussion=?
             ORDER BY modified DESC";

// Lets go find the last post
    if (($lastposts = $DB->get_records_sql($sql, array($discussionid), 0, 1))) {
        $lastpost = reset($lastposts);
        $discussionobject = new stdClass();
        $discussionobject->id = $discussionid;
        $discussionobject->usermodified = $lastpost->userid;
        $discussionobject->timemodified = $lastpost->modified;
        $DB->update_record('socialforum_discussions', $discussionobject);
        return $lastpost->id;
    }

// To get here either we couldn't find a post for the discussion (weird)
// or we couldn't update the discussion record (weird x2)
    return false;
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function socialforum_get_view_actions() {
    return array('view discussion', 'search', 'socialforum', 'socialforums', 'subscribers', 'view social forum');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function socialforum_get_post_actions() {
    return array('add discussion', 'add post', 'delete discussion', 'delete post', 'move discussion', 'prune post', 'update post');
}

/**
 * Returns a warning object if a user has reached the number of posts equal to
 * the warning/blocking setting, or false if there is no warning to show.
 *
 * @param int|stdClass $socialforum the social forum id or the social forum object
 * @param stdClass $cm the course module
 * @return stdClass|bool returns an object with the warning information, else
 *         returns false if no warning is required.
 */
function socialforum_check_throttling($socialforum, $cm = null) {
    global $CFG, $DB, $USER;

    if (is_numeric($socialforum)) {
        $socialforum = $DB->get_record('socialforum', array('id' => $socialforum), '*', MUST_EXIST);
    }

    if (!is_object($socialforum)) {
        return false; // This is broken.
    }

    if (!$cm) {
        $cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $socialforum->course, false, MUST_EXIST);
    }

    if (empty($socialforum->blockafter)) {
        return false;
    }

    if (empty($socialforum->blockperiod)) {
        return false;
    }

    $modcontext = context_module::instance($cm->id);
    if (has_capability('mod/socialforum:postwithoutthrottling', $modcontext)) {
        return false;
    }

    // Get the number of posts in the last period we care about.
    $timenow = time();
    $timeafter = $timenow - $socialforum->blockperiod;
    $numposts = $DB->count_records_sql('SELECT COUNT(p.id) FROM {socialforum_posts} p
                                        JOIN {socialforum_discussions} d
                                        ON p.discussion = d.id WHERE d.socialforum = ?
                                        AND p.userid = ? AND p.created > ?', array($socialforum->id, $USER->id, $timeafter));

    $a = new stdClass();
    $a->blockafter = $socialforum->blockafter;
    $a->numposts = $numposts;
    $a->blockperiod = get_string('secondstotime' . $socialforum->blockperiod);

    if ($socialforum->blockafter <= $numposts) {
        $warning = new stdClass();
        $warning->canpost = false;
        $warning->errorcode = 'socialforumblockingtoomanyposts';
        $warning->module = 'error';
        $warning->additional = $a;
        $warning->link = $CFG->wwwroot . '/mod/socialforum/view.php?f=' . $socialforum->id;

        return $warning;
    }

    if ($socialforum->warnafter <= $numposts) {
        $warning = new stdClass();
        $warning->canpost = true;
        $warning->errorcode = 'socialforumblockingalmosttoomanyposts';
        $warning->module = 'socialforum';
        $warning->additional = $a;
        $warning->link = null;

        return $warning;
    }
}

/**
 * Throws an error if the user is no longer allowed to post due to having reached
 * or exceeded the number of posts specified in 'Post threshold for blocking'
 * setting.
 *
 * @since Moodle 2.5
 * @param stdClass $thresholdwarning the warning information returned
 *        from the function socialforum_check_throttling.
 */
function socialforum_check_blocking_threshold($thresholdwarning) {
    if (!empty($thresholdwarning) && !$thresholdwarning->canpost) {
        print_error($thresholdwarning->errorcode, $thresholdwarning->module, $thresholdwarning->link, $thresholdwarning->additional);
    }
}

/**
 * Removes all grades from gradebook
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param string $type optional
 */
function socialforum_reset_gradebook($courseid, $type = '') {
    global $CFG, $DB;

    $wheresql = '';
    $params = array($courseid);
    if ($type) {
        $wheresql = "AND f.type=?";
        $params[] = $type;
    }

    $sql = "SELECT f.*, cm.idnumber as cmidnumber, f.course as courseid
              FROM {socialforum} f, {course_modules} cm, {modules} m
             WHERE m.name='socialforum' AND m.id=cm.module AND cm.instance=f.id AND f.course=? $wheresql";

    if ($socialforums = $DB->get_records_sql($sql, $params)) {
        foreach ($socialforums as $socialforum) {
            socialforum_grade_item_update($socialforum, 'reset');
        }
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified social forum
 * and clean up any related data.
 *
 * @global object
 * @global object
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function socialforum_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/rating/lib.php');

    $componentstr = get_string('modulenameplural', 'mod_socialforum');
    $status = array();

    $params = array($data->courseid);

    $removeposts = false;
    $typesql = "";
    if (!empty($data->reset_socialforum_all)) {
        $removeposts = true;
        $typesstr = get_string('resetsocialforumsall', 'mod_socialforum');
        $types = array();
    } else if (!empty($data->reset_socialforum_types)) {
        $removeposts = true;
        $types = array();
        $sqltypes = array();
        $socialforum_types_all = socialforum_get_socialforum_types_all();
        foreach ($data->reset_socialforum_types as $type) {
            if (!array_key_exists($type, $socialforum_types_all)) {
                continue;
            }
            $types[] = $socialforum_types_all[$type];
            $sqltypes[] = $type;
        }
        if (!empty($sqltypes)) {
            list($typesql, $typeparams) = $DB->get_in_or_equal($sqltypes);
            $typesql = " AND f.type " . $typesql;
            $params = array_merge($params, $typeparams);
        }
        $typesstr = get_string('resetsocialforums', 'mod_socialforum') . ': ' . implode(', ', $types);
    }
    $alldiscussionssql = "SELECT fd.id
                            FROM {socialforum_discussions} fd, {socialforum} f
                           WHERE f.course=? AND f.id=fd.socialforum";

    $allforumssql = "SELECT f.id
                            FROM {socialforum} f
                           WHERE f.course=?";

    $allpostssql = "SELECT fp.id
                            FROM {socialforum_posts} fp, {socialforum_discussions} fd, {socialforum} f
                           WHERE f.course=? AND f.id=fd.socialforum AND fd.id=fp.discussion";

    $socialforumssql = $socialforums = $rm = null;

    if ($removeposts || !empty($data->reset_socialforum_ratings)) {
        $socialforumssql = "$allforumssql $typesql";
        $socialforums = $socialforums = $DB->get_records_sql($socialforumssql, $params);
        $rm = new rating_manager();
        $ratingdeloptions = new stdClass;
        $ratingdeloptions->component = 'mod_socialforum';
        $ratingdeloptions->ratingarea = 'post';
    }

    if ($removeposts) {
        $discussionssql = "$alldiscussionssql $typesql";
        $postssql = "$allpostssql $typesql";

        // now get rid of all attachments
        $fs = get_file_storage();
        if ($socialforums) {
            foreach ($socialforums as $socialforumid => $unused) {
                if (!$cm = get_coursemodule_from_instance('socialforum', $socialforumid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);
                $fs->delete_area_files($context->id, 'mod_socialforum', 'attachment');
                $fs->delete_area_files($context->id, 'mod_socialforum', 'post');

                //remove ratings
                $ratingdeloptions->contextid = $context->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        // first delete all read flags
        $DB->delete_records_select('socialforum_read', "socialforumid IN ($socialforumssql)", $params);

        // remove tracking prefs
        $DB->delete_records_select('socialforum_track_prefs', "socialforumid IN ($socialforumssql)", $params);

        // remove posts from queue
        $DB->delete_records_select('socialforum_queue', "discussionid IN ($discussionssql)", $params);

        // all posts - initial posts must be kept in single simple discussion social forums
        $DB->delete_records_select('socialforum_posts', "discussion IN ($discussionssql) AND parent <> 0", $params); // first all children
        $DB->delete_records_select('socialforum_posts', "discussion IN ($discussionssql AND f.type <> 'single') AND parent = 0", $params); // now the initial posts for non single simple
        // finally all discussions except single simple social forums
        $DB->delete_records_select('socialforum_discussions', "socialforum IN ($socialforumssql AND f.type <> 'single')", $params);

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            if (empty($types)) {
                socialforum_reset_gradebook($data->courseid);
            } else {
                foreach ($types as $type) {
                    socialforum_reset_gradebook($data->courseid, $type);
                }
            }
        }

        // Remove votes
        $DB->delete_records_select('socialforum_vote', "socialforumid IN ($socialforumssql AND f.type <> 'single')", $params);

        $DB->delete_records_select('socialforum_vote', "socialforumid IN ($socialforumssql AND f.type <> 'single')", $params);

        $status[] = array('component' => $componentstr, 'item' => $typesstr, 'error' => false);
    }

    // remove all ratings in this course's social forums
    if (!empty($data->reset_socialforum_ratings)) {
        if ($socialforums) {
            foreach ($socialforums as $socialforumid => $unused) {
                if (!$cm = get_coursemodule_from_instance('socialforum', $socialforumid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);

                //remove ratings
                $ratingdeloptions->contextid = $context->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            socialforum_reset_gradebook($data->courseid);
        }
    }

    // remove all digest settings unconditionally - even for users still enrolled in course.
    if (!empty($data->reset_socialforum_digests)) {
        $DB->delete_records_select('socialforum_digests', "socialforum IN ($allforumssql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('resetdigests', 'mod_socialforum'), 'error' => false);
    }

    // remove all subscriptions unconditionally - even for users still enrolled in course
    if (!empty($data->reset_socialforum_subscriptions)) {
        $DB->delete_records_select('socialforum_subscriptions', "socialforum IN ($allforumssql)", $params);
        $DB->delete_records_select('socialforum_discussion_subs', "socialforum IN ($allforumssql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('resetsubscriptions', 'mod_socialforum'), 'error' => false);
    }

    // remove all tracking prefs unconditionally - even for users still enrolled in course
    if (!empty($data->reset_socialforum_track_prefs)) {
        $DB->delete_records_select('socialforum_track_prefs', "socialforumid IN ($allforumssql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('resettrackprefs', 'mod_socialforum'), 'error' => false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('socialforum', array('assesstimestart', 'assesstimefinish'), $data->timeshift, $data->courseid);
        $status[] = array('component' => $componentstr, 'item' => get_string('datechanged'), 'error' => false);
    }

    return $status;
}

/**
 * Called by course/reset.php
 *
 * @param $mform form passed by reference
 */
function socialforum_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'socialforumheader', get_string('modulenameplural', 'mod_socialforum'));

    $mform->addElement('checkbox', 'reset_socialforum_all', get_string('resetsocialforumsall', 'mod_socialforum'));

    $mform->addElement('select', 'reset_socialforum_types', get_string('resetsocialforums', 'mod_socialforum'), socialforum_get_socialforum_types_all(), array('multiple' => 'multiple'));
    $mform->setAdvanced('reset_socialforum_types');
    $mform->disabledIf('reset_socialforum_types', 'reset_socialforum_all', 'checked');

    $mform->addElement('checkbox', 'reset_socialforum_digests', get_string('resetdigests', 'mod_socialforum'));
    $mform->setAdvanced('reset_socialforum_digests');

    $mform->addElement('checkbox', 'reset_socialforum_subscriptions', get_string('resetsubscriptions', 'mod_socialforum'));
    $mform->setAdvanced('reset_socialforum_subscriptions');

    $mform->addElement('checkbox', 'reset_socialforum_track_prefs', get_string('resettrackprefs', 'mod_socialforum'));
    $mform->setAdvanced('reset_socialforum_track_prefs');
    $mform->disabledIf('reset_socialforum_track_prefs', 'reset_socialforum_all', 'checked');

    $mform->addElement('checkbox', 'reset_socialforum_ratings', get_string('deleteallratings'));
    $mform->disabledIf('reset_socialforum_ratings', 'reset_socialforum_all', 'checked');
}

/**
 * Course reset form defaults.
 * @return array
 */
function socialforum_reset_course_form_defaults($course) {
    return array('reset_socialforum_all' => 1, 'reset_socialforum_digests' => 0, 'reset_socialforum_subscriptions' => 0, 'reset_socialforum_track_prefs' => 0, 'reset_socialforum_ratings' => 1);
}

/**
 * Returns array of social forum layout modes
 *
 * @return array
 */
function socialforum_get_layout_modes() {
    return array(
        SOCIALFORUM_MODE_NESTED => get_string('modenested', 'mod_socialforum'));
}

/**
 * Returns array of social forum types chooseable on the social forum editing form
 *
 * @return array
 */
function socialforum_get_socialforum_types() {
    return array('general' => get_string('generalsocialforum', 'mod_socialforum'),
        'eachuser' => get_string('eachusersocialforum', 'mod_socialforum'),
        'single' => get_string('singlesocialforum', 'mod_socialforum'),
        'qanda' => get_string('qandasocialforum', 'mod_socialforum'),
        'blog' => get_string('blogsocialforum', 'mod_socialforum'));
}

/**
 * Returns array of all social forum layout modes
 *
 * @return array
 */
function socialforum_get_socialforum_types_all() {
    return array('news' => get_string('namenews', 'mod_socialforum'),
        'social' => get_string('namesocial', 'mod_socialforum'),
        'general' => get_string('generalsocialforum', 'mod_socialforum'),
        'eachuser' => get_string('eachusersocialforum', 'mod_socialforum'),
        'single' => get_string('singlesocialforum', 'mod_socialforum'),
        'qanda' => get_string('qandasocialforum', 'mod_socialforum'),
        'blog' => get_string('blogsocialforum', 'mod_socialforum'));
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function socialforum_get_extra_capabilities() {
    return array('moodle/site:accessallgroups', 'moodle/site:viewfullnames', 'moodle/site:trustcontent', 'moodle/rating:view', 'moodle/rating:viewany', 'moodle/rating:viewall', 'moodle/rating:rate');
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $socialforumnode The node to add module settings to
 */
function socialforum_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $socialforumnode) {
    global $USER, $PAGE, $CFG, $DB, $OUTPUT;

    $socialforumobject = $DB->get_record("socialforum", array("id" => $PAGE->cm->instance));
    if (empty($PAGE->cm->context)) {
        $PAGE->cm->context = context_module::instance($PAGE->cm->instance);
    }

    $params = $PAGE->url->params();
    if (!empty($params['d'])) {
        $discussionid = $params['d'];
    }

    // for some actions you need to be enrolled, beiing admin is not enough sometimes here
    $enrolled = is_enrolled($PAGE->cm->context, $USER, '', false);
    $activeenrolled = is_enrolled($PAGE->cm->context, $USER, '', true);

    $canmanage = has_capability('mod/socialforum:managesubscriptions', $PAGE->cm->context);
    $subscriptionmode = \mod_socialforum\subscriptions::get_subscription_mode($socialforumobject);
    $cansubscribe = $activeenrolled && !\mod_socialforum\subscriptions::is_forcesubscribed($socialforumobject) &&
            (!\mod_socialforum\subscriptions::subscription_disabled($socialforumobject) || $canmanage);

    if ($canmanage) {
        $mode = $socialforumnode->add(get_string('subscriptionmode', 'mod_socialforum'), null, navigation_node::TYPE_CONTAINER);

        $allowchoice = $mode->add(get_string('subscriptionoptional', 'mod_socialforum'), new moodle_url('/mod/socialforum/subscribe.php', array('id' => $socialforumobject->id, 'mode' => SOCIALFORUM_CHOOSESUBSCRIBE, 'sesskey' => sesskey())), navigation_node::TYPE_SETTING);
        $forceforever = $mode->add(get_string("subscriptionforced", "mod_socialforum"), new moodle_url('/mod/socialforum/subscribe.php', array('id' => $socialforumobject->id, 'mode' => SOCIALFORUM_FORCESUBSCRIBE, 'sesskey' => sesskey())), navigation_node::TYPE_SETTING);
        $forceinitially = $mode->add(get_string("subscriptionauto", "mod_socialforum"), new moodle_url('/mod/socialforum/subscribe.php', array('id' => $socialforumobject->id, 'mode' => SOCIALFORUM_INITIALSUBSCRIBE, 'sesskey' => sesskey())), navigation_node::TYPE_SETTING);
        $disallowchoice = $mode->add(get_string('subscriptiondisabled', 'mod_socialforum'), new moodle_url('/mod/socialforum/subscribe.php', array('id' => $socialforumobject->id, 'mode' => SOCIALFORUM_DISALLOWSUBSCRIBE, 'sesskey' => sesskey())), navigation_node::TYPE_SETTING);

        switch ($subscriptionmode) {
            case SOCIALFORUM_CHOOSESUBSCRIBE : // 0
                $allowchoice->action = null;
                $allowchoice->add_class('activesetting');
                break;
            case SOCIALFORUM_FORCESUBSCRIBE : // 1
                $forceforever->action = null;
                $forceforever->add_class('activesetting');
                break;
            case SOCIALFORUM_INITIALSUBSCRIBE : // 2
                $forceinitially->action = null;
                $forceinitially->add_class('activesetting');
                break;
            case SOCIALFORUM_DISALLOWSUBSCRIBE : // 3
                $disallowchoice->action = null;
                $disallowchoice->add_class('activesetting');
                break;
        }
    } else if ($activeenrolled) {

        switch ($subscriptionmode) {
            case SOCIALFORUM_CHOOSESUBSCRIBE : // 0
                $notenode = $socialforumnode->add(get_string('subscriptionoptional', 'mod_socialforum'));
                break;
            case SOCIALFORUM_FORCESUBSCRIBE : // 1
                $notenode = $socialforumnode->add(get_string('subscriptionforced', 'mod_socialforum'));
                break;
            case SOCIALFORUM_INITIALSUBSCRIBE : // 2
                $notenode = $socialforumnode->add(get_string('subscriptionauto', 'mod_socialforum'));
                break;
            case SOCIALFORUM_DISALLOWSUBSCRIBE : // 3
                $notenode = $socialforumnode->add(get_string('subscriptiondisabled', 'mod_socialforum'));
                break;
        }
    }

    if ($cansubscribe) {
        if (\mod_socialforum\subscriptions::is_subscribed($USER->id, $socialforumobject, null, $PAGE->cm)) {
            $linktext = get_string('unsubscribe', 'mod_socialforum');
        } else {
            $linktext = get_string('subscribe', 'mod_socialforum');
        }
        $url = new moodle_url('/mod/socialforum/subscribe.php', array('id' => $socialforumobject->id, 'sesskey' => sesskey()));
        $socialforumnode->add($linktext, $url, navigation_node::TYPE_SETTING);

        if (isset($discussionid)) {
            if (\mod_socialforum\subscriptions::is_subscribed($USER->id, $socialforumobject, $discussionid, $PAGE->cm)) {
                $linktext = get_string('unsubscribediscussion', 'mod_socialforum');
            } else {
                $linktext = get_string('subscribediscussion', 'mod_socialforum');
            }
            $url = new moodle_url('/mod/socialforum/subscribe.php', array(
                'id' => $socialforumobject->id,
                'sesskey' => sesskey(),
                'd' => $discussionid,
                'returnurl' => $PAGE->url->out(),
            ));
            $socialforumnode->add($linktext, $url, navigation_node::TYPE_SETTING);
        }
    }

    if (has_capability('mod/socialforum:viewsubscribers', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/socialforum/subscribers.php', array('id' => $socialforumobject->id));
        $socialforumnode->add(get_string('showsubscribers', 'mod_socialforum'), $url, navigation_node::TYPE_SETTING);
    }

    if ($enrolled && socialforum_tp_can_track_socialforums($socialforumobject)) { // keep tracking info for users with suspended enrolments
        if ($socialforumobject->trackingtype == SOCIALFORUM_TRACKING_OPTIONAL || ((!$CFG->socialforum_allowforcedreadtracking) && $socialforumobject->trackingtype == SOCIALFORUM_TRACKING_FORCED)) {
            if (socialforum_tp_is_tracked($socialforumobject)) {
                $linktext = get_string('notracksocialforum', 'mod_socialforum');
            } else {
                $linktext = get_string('tracksocialforum', 'mod_socialforum');
            }
            $url = new moodle_url('/mod/socialforum/settracking.php', array(
                'id' => $socialforumobject->id,
                'sesskey' => sesskey(),
            ));
            $socialforumnode->add($linktext, $url, navigation_node::TYPE_SETTING);
        }
    }

    if (!isloggedin() && $PAGE->course->id == SITEID) {
        $userid = guest_user()->id;
    } else {
        $userid = $USER->id;
    }

    $hascourseaccess = ($PAGE->course->id == SITEID) || can_access_course($PAGE->course, $userid);
    $enablerssfeeds = !empty($CFG->enablerssfeeds) && !empty($CFG->socialforum_enablerssfeeds);

    if ($enablerssfeeds && $socialforumobject->rsstype && $socialforumobject->rssarticles && $hascourseaccess) {

        if (!function_exists('rss_get_url')) {
            require_once("$CFG->libdir/rsslib.php");
        }

        if ($socialforumobject->rsstype == 1) {
            $string = get_string('rsssubscriberssdiscussions', 'mod_socialforum');
        } else {
            $string = get_string('rsssubscriberssposts', 'mod_socialforum');
        }

        $url = new moodle_url(rss_get_url($PAGE->cm->context->id, $userid, "mod_socialforum", $socialforumobject->id));
        $socialforumnode->add($string, $url, settings_navigation::TYPE_SETTING, null, null, new pix_icon('i/rss', ''));
    }
}

/**
 * Adds information about unread messages, that is only required for the course view page (and
 * similar), to the course-module object.
 * @param cm_info $cm Course-module object
 */
function socialforum_cm_info_view(cm_info $cm) {
    global $CFG;

    if (socialforum_tp_can_track_socialforums()) {
        if ($unread = socialforum_tp_count_socialforum_unread_posts($cm, $cm->get_course())) {
            $out = '<span class="unread"> <a href="' . $cm->url . '">';
            if ($unread == 1) {
                $out .= get_string('unreadpostsone', 'mod_socialforum');
            } else {
                $out .= get_string('unreadpostsnumber', 'mod_socialforum', $unread);
            }
            $out .= '</a></span>';
            $cm->set_after_link($out);
        }
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function socialforum_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $socialforum_pagetype = array(
        'mod-forum-*' => get_string('page-mod-forum-x', 'mod_socialforum'),
        'mod-forum-view' => get_string('page-mod-forum-view', 'mod_socialforum'),
        'mod-forum-discuss' => get_string('page-mod-forum-discuss', 'mod_socialforum')
    );
    return $socialforum_pagetype;
}

/**
 * Gets all of the courses where the provided user has posted in a social forum.
 *
 * @global moodle_database $DB The database connection
 * @param stdClass $user The user who's posts we are looking for
 * @param bool $discussionsonly If true only look for discussions started by the user
 * @param bool $includecontexts If set to trye contexts for the courses will be preloaded
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return array An array of courses
 */
function socialforum_get_courses_user_posted_in($user, $discussionsonly = false, $includecontexts = true, $limitfrom = null, $limitnum = null) {
    global $DB;

    // If we are only after discussions we need only look at the socialforum_discussions
    // table and join to the userid there. If we are looking for posts then we need
    // to join to the socialforum_posts table.
    if (!$discussionsonly) {
        $subquery = "(SELECT DISTINCT fd.course
                         FROM {socialforum_discussions} fd
                         JOIN {socialforum_posts} fp ON fp.discussion = fd.id
                        WHERE fp.userid = :userid )";
    } else {
        $subquery = "(SELECT DISTINCT fd.course
                         FROM {socialforum_discussions} fd
                        WHERE fd.userid = :userid )";
    }

    $params = array('userid' => $user->id);

    // Join to the context table so that we can preload contexts if required.
    if ($includecontexts) {
        $ctxselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ctxjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
        $params['contextlevel'] = CONTEXT_COURSE;
    } else {
        $ctxselect = '';
        $ctxjoin = '';
    }

    // Now we need to get all of the courses to search.
    // All courses where the user has posted within a social forum will be returned.
    $sql = "SELECT c.* $ctxselect
            FROM {course} c
            $ctxjoin
            WHERE c.id IN ($subquery)";
    $courses = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    if ($includecontexts) {
        array_map('context_helper::preload_from_record', $courses);
    }
    return $courses;
}

/**
 * Gets all of the social forums a user has posted in for one or more courses.
 *
 * @global moodle_database $DB
 * @param stdClass $user
 * @param array $courseids An array of courseids to search or if not provided
 *                       all courses the user has posted within
 * @param bool $discussionsonly If true then only social forums where the user has started
 *                       a discussion will be returned.
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return array An array of social forums the user has posted within in the provided courses
 */
function socialforum_get_socialforums_user_posted_in($user, array $courseids = null, $discussionsonly = false, $limitfrom = null, $limitnum = null) {
    global $DB;

    if (!is_null($courseids)) {
        list($coursewhere, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
        $coursewhere = ' AND f.course ' . $coursewhere;
    } else {
        $coursewhere = '';
        $params = array();
    }
    $params['userid'] = $user->id;
    $params['socialforum'] = 'socialforum';

    if ($discussionsonly) {
        $join = 'JOIN {socialforum_discussions} ff ON ff.socialforum = f.id';
    } else {
        $join = 'JOIN {socialforum_discussions} fd ON fd.socialforum = f.id
                 JOIN {socialforum_posts} ff ON ff.discussion = fd.id';
    }

    $sql = "SELECT f.*, cm.id AS cmid
              FROM {socialforum} f
              JOIN {course_modules} cm ON cm.instance = f.id
              JOIN {modules} m ON m.id = cm.module
              JOIN (
                  SELECT f.id
                    FROM {socialforum} f
                    {$join}
                   WHERE ff.userid = :userid
                GROUP BY f.id
                   ) j ON j.id = f.id
             WHERE m.name = :socialforum
                 {$coursewhere}";

    $courseforums = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    return $courseforums;
}

/**
 * Returns posts made by the selected user in the requested courses.
 *
 * This method can be used to return all of the posts made by the requested user
 * within the given courses.
 * For each course the access of the current user and requested user is checked
 * and then for each post access to the post and social forum is checked as well.
 *
 * This function is safe to use with usercapabilities.
 *
 * @global moodle_database $DB
 * @param stdClass $user The user whose posts we want to get
 * @param array $courses The courses to search
 * @param bool $musthaveaccess If set to true errors will be thrown if the user
 *                             cannot access one or more of the courses to search
 * @param bool $discussionsonly If set to true only discussion starting posts
 *                              will be returned.
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return stdClass An object the following properties
 *               ->totalcount: the total number of posts made by the requested user
 *                             that the current user can see.
 *               ->courses: An array of courses the current user can see that the
 *                          requested user has posted in.
 *               ->socialforums: An array of social forums relating to the posts returned in the
 *                         property below.
 *               ->posts: An array containing the posts to show for this request.
 */
function socialforum_get_posts_by_user($user, array $courses, $musthaveaccess = false, $discussionsonly = false, $limitfrom = 0, $limitnum = 50) {
    global $DB, $USER, $CFG;

    $return = new stdClass;
    $return->totalcount = 0;    // The total number of posts that the current user is able to view
    $return->courses = array(); // The courses the current user can access
    $return->socialforums = array();  // The social forums that the current user can access that contain posts
    $return->posts = array();   // The posts to display
    // First up a small sanity check. If there are no courses to check we can
    // return immediately, there is obviously nothing to search.
    if (empty($courses)) {
        return $return;
    }

    // A couple of quick setups
    $isloggedin = isloggedin();
    $isguestuser = $isloggedin && isguestuser();
    $iscurrentuser = $isloggedin && $USER->id == $user->id;

    // Checkout whether or not the current user has capabilities over the requested
    // user and if so they have the capabilities required to view the requested
    // users content.
    $usercontext = context_user::instance($user->id, MUST_EXIST);
    $hascapsonuser = !$iscurrentuser && $DB->record_exists('role_assignments', array('userid' => $USER->id, 'contextid' => $usercontext->id));
    $hascapsonuser = $hascapsonuser && has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), $usercontext);

    // Before we actually search each course we need to check the user's access to the
    // course. If the user doesn't have the appropraite access then we either throw an
    // error if a particular course was requested or we just skip over the course.
    foreach ($courses as $course) {
        $coursecontext = context_course::instance($course->id, MUST_EXIST);
        if ($iscurrentuser || $hascapsonuser) {
            // If it is the current user, or the current user has capabilities to the
            // requested user then all we need to do is check the requested users
            // current access to the course.
            // Note: There is no need to check group access or anything of the like
            // as either the current user is the requested user, or has granted
            // capabilities on the requested user. Either way they can see what the
            // requested user posted, although its VERY unlikely in the `parent` situation
            // that the current user will be able to view the posts in context.
            if (!is_viewing($coursecontext, $user) && !is_enrolled($coursecontext, $user)) {
                // Need to have full access to a course to see the rest of own info
                if ($musthaveaccess) {
                    print_error('errorenrolmentrequired', 'socialforum');
                }
                continue;
            }
        } else {
            // Check whether the current user is enrolled or has access to view the course
            // if they don't we immediately have a problem.
            if (!can_access_course($course)) {
                if ($musthaveaccess) {
                    print_error('errorenrolmentrequired', 'socialforum');
                }
                continue;
            }

            // Check whether the requested user is enrolled or has access to view the course
            // if they don't we immediately have a problem.
            if (!can_access_course($course, $user) && !is_enrolled($coursecontext, $user)) {
                if ($musthaveaccess) {
                    print_error('notenrolled', 'socialforum');
                }
                continue;
            }

            // If groups are in use and enforced throughout the course then make sure
            // we can meet in at least one course level group.
            // Note that we check if either the current user or the requested user have
            // the capability to access all groups. This is because with that capability
            // a user in group A could post in the group B social forum. Grrrr.
            if (groups_get_course_groupmode($course) == SEPARATEGROUPS && $course->groupmodeforce && !has_capability('moodle/site:accessallgroups', $coursecontext) && !has_capability('moodle/site:accessallgroups', $coursecontext, $user->id)) {
                // If its the guest user to bad... the guest user cannot access groups
                if (!$isloggedin or $isguestuser) {
                    // do not use require_login() here because we might have already used require_login($course)
                    if ($musthaveaccess) {
                        redirect(get_login_url());
                    }
                    continue;
                }
                // Get the groups of the current user
                $mygroups = array_keys(groups_get_all_groups($course->id, $USER->id, $course->defaultgroupingid, 'g.id, g.name'));
                // Get the groups the requested user is a member of
                $usergroups = array_keys(groups_get_all_groups($course->id, $user->id, $course->defaultgroupingid, 'g.id, g.name'));
                // Check whether they are members of the same group. If they are great.
                $intersect = array_intersect($mygroups, $usergroups);
                if (empty($intersect)) {
                    // But they're not... if it was a specific course throw an error otherwise
                    // just skip this course so that it is not searched.
                    if ($musthaveaccess) {
                        print_error("groupnotamember", '', $CFG->wwwroot . "/course/view.php?id=$course->id");
                    }
                    continue;
                }
            }
        }
        // Woo hoo we got this far which means the current user can search this
        // this course for the requested user. Although this is only the course accessibility
        // handling that is complete, the social forum accessibility tests are yet to come.
        $return->courses[$course->id] = $course;
    }
    // No longer beed $courses array - lose it not it may be big
    unset($courses);

    // Make sure that we have some courses to search
    if (empty($return->courses)) {
        // If we don't have any courses to search then the reality is that the current
        // user doesn't have access to any courses is which the requested user has posted.
        // Although we do know at this point that the requested user has posts.
        if ($musthaveaccess) {
            print_error('permissiondenied');
        } else {
            return $return;
        }
    }

    // Next step: Collect all of the social forums that we will want to search.
    // It is important to note that this step isn't actually about searching, it is
    // about determining which social forums we can search by testing accessibility.
    $socialforums = socialforum_get_socialforums_user_posted_in($user, array_keys($return->courses), $discussionsonly);

    // Will be used to build the where conditions for the search
    $socialforumsearchwhere = array();
    // Will be used to store the where condition params for the search
    $socialforumsearchparams = array();
    // Will record social forums where the user can freely access everything
    $socialforumsearchfullaccess = array();
    // DB caching friendly
    $now = round(time(), -2);
    // For each course to search we want to find the social forums the user has posted in
    // and providing the current user can access the social forum create a search condition
    // for the social forum to get the requested users posts.
    foreach ($return->courses as $course) {
        // Now we need to get the social forums
        $modinfo = get_fast_modinfo($course);
        if (empty($modinfo->instances['socialforum'])) {
            // hmmm, no social forums? well at least its easy... skip!
            continue;
        }
        // Iterate
        foreach ($modinfo->get_instances_of('socialforum') as $socialforumid => $cm) {
            if (!$cm->uservisible or ! isset($socialforums[$socialforumid])) {
                continue;
            }
            // Get the social forum in question
            $socialforum = $socialforums[$socialforumid];

            // This is needed for functionality later on in the social forum code. It is converted to an object
            // because the cm_info is readonly from 2.6. This is a dirty hack because some other parts of the
            // code were expecting an writeable object. See {@link socialforum_print_post()}.
            $socialforum->cm = new stdClass();
            foreach ($cm as $key => $value) {
                $socialforum->cm->$key = $value;
            }

            // Check that either the current user can view the social forum, or that the
            // current user has capabilities over the requested user and the requested
            // user can view the discussion
            if (!has_capability('mod/socialforum:viewdiscussion', $cm->context) && !($hascapsonuser && has_capability('mod/socialforum:viewdiscussion', $cm->context, $user->id))) {
                continue;
            }

            // This will contain social forum specific where clauses
            $socialforumsearchselect = array();
            if (!$iscurrentuser && !$hascapsonuser) {
                // Make sure we check group access
                if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS and ! has_capability('moodle/site:accessallgroups', $cm->context)) {
                    $groups = $modinfo->get_groups($cm->groupingid);
                    $groups[] = -1;
                    list($groupid_sql, $groupid_params) = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'grps' . $socialforumid . '_');
                    $socialforumsearchparams = array_merge($socialforumsearchparams, $groupid_params);
                    $socialforumsearchselect[] = "d.groupid $groupid_sql";
                }

                // hidden timed discussions
                if (!empty($CFG->socialforum_enabletimedposts) && !has_capability('mod/socialforum:viewhiddentimedposts', $cm->context)) {
                    $socialforumsearchselect[] = "(d.userid = :userid{$socialforumid} OR (d.timestart < :timestart{$socialforumid} AND (d.timeend = 0 OR d.timeend > :timeend{$socialforumid})))";
                    $socialforumsearchparams['userid' . $socialforumid] = $user->id;
                    $socialforumsearchparams['timestart' . $socialforumid] = $now;
                    $socialforumsearchparams['timeend' . $socialforumid] = $now;
                }

                if (count($socialforumsearchselect) > 0) {
                    $socialforumsearchwhere[] = "(d.socialforum = :socialforum{$socialforumid} AND " . implode(" AND ", $socialforumsearchselect) . ")";
                    $socialforumsearchparams['socialforum' . $socialforumid] = $socialforumid;
                } else {
                    $socialforumsearchfullaccess[] = $socialforumid;
                }
            } else {
                // The current user/parent can see all of their own posts
                $socialforumsearchfullaccess[] = $socialforumid;
            }
        }
    }

    // If we dont have any search conditions, and we don't have any social forums where
    // the user has full access then we just return the default.
    if (empty($socialforumsearchwhere) && empty($socialforumsearchfullaccess)) {
        return $return;
    }

    // Prepare a where condition for the full access social forums.
    if (count($socialforumsearchfullaccess) > 0) {
        list($fullidsql, $fullidparams) = $DB->get_in_or_equal($socialforumsearchfullaccess, SQL_PARAMS_NAMED, 'fula');
        $socialforumsearchparams = array_merge($socialforumsearchparams, $fullidparams);
        $socialforumsearchwhere[] = "(d.socialforum $fullidsql)";
    }

    // Prepare SQL to both count and search.
    // We alias user.id to useridx because we socialforum_posts already has a userid field and not aliasing this would break
    // oracle and mssql.
    $userfields = user_picture::fields('u', null, 'useridx');
    $countsql = 'SELECT COUNT(*) ';
    $selectsql = 'SELECT p.*, d.socialforum, d.name AS discussionname, ' . $userfields . ' ';
    $wheresql = implode(" OR ", $socialforumsearchwhere);

    if ($discussionsonly) {
        if ($wheresql == '') {
            $wheresql = 'p.parent = 0';
        } else {
            $wheresql = 'p.parent = 0 AND (' . $wheresql . ')';
        }
    }

    $sql = "FROM {socialforum_posts} p
            JOIN {socialforum_discussions} d ON d.id = p.discussion
            JOIN {user} u ON u.id = p.userid
           WHERE ($wheresql)
             AND p.userid = :userid ";
    $orderby = "ORDER BY p.modified DESC";
    $socialforumsearchparams['userid'] = $user->id;

    // Set the total number posts made by the requested user that the current user can see
    $return->totalcount = $DB->count_records_sql($countsql . $sql, $socialforumsearchparams);
    // Set the collection of posts that has been requested
    $return->posts = $DB->get_records_sql($selectsql . $sql . $orderby, $socialforumsearchparams, $limitfrom, $limitnum);

    // We need to build an array of social forums for which posts will be displayed.
    // We do this here to save the caller needing to retrieve them themselves before
    // printing these social forums posts. Given we have the social forums already there is
    // practically no overhead here.
    foreach ($return->posts as $post) {
        if (!array_key_exists($post->socialforum, $return->socialforums)) {
            $return->socialforums[$post->socialforum] = $socialforums[$post->socialforum];
        }
    }

    return $return;
}

/**
 * Set the per-forum maildigest option for the specified user.
 *
 * @param stdClass $socialforum The social forum to set the option for.
 * @param int $maildigest The maildigest option.
 * @param stdClass $user The user object. This defaults to the global $USER object.
 * @throws invalid_digest_setting thrown if an invalid maildigest option is provided.
 */
function socialforum_set_user_maildigest($socialforum, $maildigest, $user = null) {
    global $DB, $USER;

    if (is_number($socialforum)) {
        $socialforum = $DB->get_record('socialforum', array('id' => $socialforum));
    }

    if ($user === null) {
        $user = $USER;
    }

    $course = $DB->get_record('course', array('id' => $socialforum->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $course->id, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // User must be allowed to see this social forum.
    require_capability('mod/socialforum:viewdiscussion', $context, $user->id);

    // Validate the maildigest setting.
    $digestoptions = socialforum_get_user_digest_options($user);

    if (!isset($digestoptions[$maildigest])) {
        throw new moodle_exception('invaliddigestsetting', 'mod_socialforum');
    }

    // Attempt to retrieve any existing social forum digest record.
    $subscription = $DB->get_record('socialforum_digests', array(
        'userid' => $user->id,
        'socialforum' => $socialforum->id,
    ));

    // Create or Update the existing maildigest setting.
    if ($subscription) {
        if ($maildigest == -1) {
            $DB->delete_records('socialforum_digests', array('socialforum' => $socialforum->id, 'userid' => $user->id));
        } else if ($maildigest !== $subscription->maildigest) {
            // Only update the maildigest setting if it's changed.

            $subscription->maildigest = $maildigest;
            $DB->update_record('socialforum_digests', $subscription);
        }
    } else {
        if ($maildigest != -1) {
            // Only insert the maildigest setting if it's non-default.

            $subscription = new stdClass();
            $subscription->socialforum = $socialforum->id;
            $subscription->userid = $user->id;
            $subscription->maildigest = $maildigest;
            $subscription->id = $DB->insert_record('socialforum_digests', $subscription);
        }
    }
}

/**
 * Determine the maildigest setting for the specified user against the
 * specified social forum.
 *
 * @param Array $digests An array of social forums and user digest settings.
 * @param stdClass $user The user object containing the id and maildigest default.
 * @param int $socialforumid The ID of the social forum to check.
 * @return int The calculated maildigest setting for this user and social forum.
 */
function socialforum_get_user_maildigest_bulk($digests, $user, $socialforumid) {
    if (isset($digests[$socialforumid]) && isset($digests[$socialforumid][$user->id])) {
        $maildigest = $digests[$socialforumid][$user->id];
        if ($maildigest === -1) {
            $maildigest = $user->maildigest;
        }
    } else {
        $maildigest = $user->maildigest;
    }
    return $maildigest;
}

/**
 * Retrieve the list of available user digest options.
 *
 * @param stdClass $user The user object. This defaults to the global $USER object.
 * @return array The mapping of values to digest options.
 */
function socialforum_get_user_digest_options($user = null) {
    global $USER;

    // Revert to the global user object.
    if ($user === null) {
        $user = $USER;
    }

    $digestoptions = array();
    $digestoptions['0'] = get_string('emaildigestoffshort', 'mod_socialforum');
    $digestoptions['1'] = get_string('emaildigestcompleteshort', 'mod_socialforum');
    $digestoptions['2'] = get_string('emaildigestsubjectsshort', 'mod_socialforum');

    // We need to add the default digest option at the end - it relies on
    // the contents of the existing values.
    $digestoptions['-1'] = get_string('emaildigestdefault', 'mod_socialforum', $digestoptions[$user->maildigest]);

    // Resort the options to be in a sensible order.
    ksort($digestoptions);

    return $digestoptions;
}

/**
 * Determine the current context if one was not already specified.
 *
 * If a context of type context_module is specified, it is immediately
 * returned and not checked.
 *
 * @param int $socialforumid The ID of the social forum
 * @param context_module $context The current context.
 * @return context_module The context determined
 */
function socialforum_get_context($socialforumid, $context = null) {
    global $PAGE;

    if (!$context || !($context instanceof context_module)) {
        // Find out social forum context. First try to take current page context to save on DB query.
        if ($PAGE->cm && $PAGE->cm->modname === 'socialforum' && $PAGE->cm->instance == $socialforumid && $PAGE->context->contextlevel == CONTEXT_MODULE && $PAGE->context->instanceid == $PAGE->cm->id) {
            $context = $PAGE->context;
        } else {
            $cm = get_coursemodule_from_instance('socialforum', $socialforumid);
            $context = \context_module::instance($cm->id);
        }
    }

    return $context;
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $socialforum   social forum object
 * @param  stdClass $course  course object
 * @param  stdClass $cm      course module object
 * @param  stdClass $context context object
 * @since Moodle 2.9
 */
function socialforum_view($socialforum, $course, $cm, $context) {

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

    // Trigger course_module_viewed event.

    $params = array(
        'context' => $context,
        'objectid' => $socialforum->id
    );

    $event = \mod_socialforum\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('socialforum', $socialforum);
    $event->trigger();
}

/**
 * Trigger the discussion viewed event
 *
 * @param  stdClass $modcontext module context object
 * @param  stdClass $socialforum      social forum object
 * @param  stdClass $discussion discussion object
 * @since Moodle 2.9
 */
function socialforum_discussion_view($modcontext, $socialforum, $discussion) {
    $params = array(
        'context' => $modcontext,
        'objectid' => $discussion->id,
    );

    $event = \mod_socialforum\event\discussion_viewed::create($params);
    $event->add_record_snapshot('socialforum_discussions', $discussion);
    $event->add_record_snapshot('socialforum', $socialforum);
    $event->trigger();
}

/**
 * Set the discussion to pinned and trigger the discussion pinned event
 *
 * @param  stdClass $modcontext module context object
 * @param  stdClass $socialforum      social forum object
 * @param  stdClass $discussion discussion object
 * @since Moodle 3.1
 */
function socialforum_discussion_pin($modcontext, $socialforum, $discussion) {
    global $DB;

    $DB->set_field('socialforum_discussions', 'pinned', SOCIALFORUM_DISCUSSION_PINNED, array('id' => $discussion->id));

    $params = array(
        'context' => $modcontext,
        'objectid' => $discussion->id,
        'other' => array('socialforumid' => $socialforum->id)
    );

    $event = \mod_socialforum\event\discussion_pinned::create($params);
    $event->add_record_snapshot('socialforum_discussions', $discussion);
    $event->trigger();
}

/**
 * Set discussion to unpinned and trigger the discussion unpin event
 *
 * @param  stdClass $modcontext module context object
 * @param  stdClass $socialforum      social forum object
 * @param  stdClass $discussion discussion object
 * @since Moodle 3.1
 */
function socialforum_discussion_unpin($modcontext, $socialforum, $discussion) {
    global $DB;

    $DB->set_field('socialforum_discussions', 'pinned', SOCIALFORUM_DISCUSSION_UNPINNED, array('id' => $discussion->id));

    $params = array(
        'context' => $modcontext,
        'objectid' => $discussion->id,
        'other' => array('socialforumid' => $socialforum->id)
    );

    $event = \mod_socialforum\event\discussion_unpinned::create($params);
    $event->add_record_snapshot('socialforum_discussions', $discussion);
    $event->trigger();
}

/**
 * Add nodes to myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 *
 * @return bool
 */
function mod_socialforum_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (isguestuser($user)) {
        // The guest user cannot post, so it is not possible to view any posts.
        // May as well just bail aggressively here.
        return false;
    }
    $postsurl = new moodle_url('/mod/socialforum/user.php', array('id' => $user->id));
    if (!empty($course)) {
        $postsurl->param('course', $course->id);
    }
    $string = get_string('socialforumposts', 'mod_socialforum');
    $node = new core_user\output\myprofile\node('miscellaneous', 'socialforumposts', $string, null, $postsurl);
    $tree->add_node($node);

    $discussionssurl = new moodle_url('/mod/socialforum/user.php', array('id' => $user->id, 'mode' => 'discussions'));
    if (!empty($course)) {
        $discussionssurl->param('course', $course->id);
    }
    $string = get_string('myprofileotherdis', 'mod_socialforum');
    $node = new core_user\output\myprofile\node('miscellaneous', 'socialforumdiscussions', $string, null, $discussionssurl);
    $tree->add_node($node);

    return true;
}

function socialforum_print_associated_video($socialforum, $discussion) {
    $videocm = video_get_cm_from_socialforum($socialforum, $discussion);
    if ($videocm) {
        $video = video_get_from_id($videocm->instance);
        echo html_writer::start_div('associated-video');
        echo html_writer::start_div('page-separator');
        echo html_writer::div(get_string('related', 'mod_socialforum'), 'page-separator__text');
        echo html_writer::end_div();
        echo html_writer::start_div('card');
        echo html_writer::start_div('card-head');
        echo video_print_player($videocm);
        echo html_writer::end_div();
        echo html_writer::start_div('card-body');
        $videourl = new moodle_url('/mod/video/view.php', array(
            'id' => $videocm->id,
        ));
        echo html_writer::tag('a', html_writer::tag('span', $video->name, array('class' => 'h5')), array(
            'href' => $videourl,
        ));
        echo html_writer::start_tag('small', array(
            'class' => 'form-text text-muted',
        ));
        echo html_writer::tag('p', get_string('relateddesc', 'mod_socialforum'), array(
            'class' => 'text-70',
        ));
        echo html_writer::end_tag('small');
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

function socialforum_print_banner($cm) {
    global $COURSE;

    $html = '';
    $html .= html_writer::start_div('mdk-box mb-0 py-32pt', array(
                'id' => 'banner'
    ));
    $html .= html_writer::start_div('mdk-box__bg', array(
                'style' => 'visibility: visible;'
    ));
    $html .= html_writer::start_div('mdk-box__bg-front', array(
                'id' => 'banner-background'
    ));
    $html .= html_writer::end_div();
    $html .= html_writer::div('', 'mdk-box__bg-rear', array(
                'style' => 'transform: translate3d(0px, 0px, 0px); will-change: opacity; opacity: 0; margin-top: -21.3416px;'
    ));
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('narrow-page mdk-box__content justify-content-center');
    $html .= html_writer::start_div('hero container page__container text-center');
    $socialforumurl = new moodle_url('/mod/socialforum/view.php', array(
        'id' => $cm->id
    ));
    $html .= html_writer::start_div('page-section', array(
                'style' => 'padding-top: 2rem; padding-bottom: 2rem;'
    ));
    $html .= html_writer::start_div('container page__container d-flex flex-column flex-md-row align-items-center text-center text-md-left');
    $html .= html_writer::start_tag('a', array(
                'href' => $socialforumurl,
                'id' => 'icon'));
    $html .= html_writer::tag('img', '', array(
                'src' => '/mod/socialforum/pix/banner-icon.png',
                'width' => '104',
                'class' => 'mr-md-32pt mb-32pt mb-md-0',
                'alt' => 'socialforumicon'
    ));
    $html .= html_writer::end_tag('a');
    $html .= html_writer::start_div('flex mb-32pt mb-md-0');
    $html .= html_writer::start_tag('a', array(
                'href' => $socialforumurl,
                'id' => 'socialforumlink'
    ));
    $html .= html_writer::tag('h2', $cm->name, array(
                'class' => 'mb-0'
    ));
    $html .= html_writer::end_tag('a');
    $courseurl = new moodle_url('/course/view.php', array(
        'id' => $cm->course
    ));
    $html .= html_writer::start_tag('a', array(
                'href' => $courseurl,
                'id' => 'courselink'
    ));
    $html .= html_writer::tag('strong', $COURSE->fullname, array(
                'class' => 'text-70'
    ));
    $html .= html_writer::end_tag('a');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

function socialforum_print_search_first($cm) {
    $html = '';
    $html .= html_writer::start_div('col-lg-3 page-nav');
    $html .= html_writer::start_div('ps', array(
                'data-perfect-scrollbar' => '',
                'data-perfect-scrollbar-wheel-propagation' => 'true'
    ));
    $html .= html_writer::start_div('page-section');
    $html .= html_writer::start_div('nav page-nav__menu');
    $html .= html_writer::tag('h6', get_string('beforeposting', 'mod_socialforum'), array(
                'style' => 'margin-bottom: 0;'
    ));
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('page-nav__content');
    $html .= html_writer::tag('p', get_string('beforepostingtext', 'mod_socialforum'), array(
                'class' => 'text-70'
    ));
    $html .= html_writer::end_div();
    $socialforumurl = new moodle_url('/mod/socialforum/view.php', array(
        'id' => $cm->id
    ));
    $html .= html_writer::start_tag('a', array(
                'href' => $socialforumurl,
                'class' => 'chip chip-outline-secondary',
                'style' => 'margin-left: 21px;'
    ));
    $html .= html_writer::tag('i', 'search', array(
                'class' => 'material-icons',
                'style' => 'font-size: 0.9rem;'
    ));
    $html .= get_string('searchinforum', 'mod_socialforum');
    $html .= html_writer::end_tag('a');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

function socialforum_is_course_teacher($userid, $course) {
    $courseinlist = new course_in_list($course);
    $courseteachers = $courseinlist->get_course_contacts();
    return array_key_exists($userid, $courseteachers);
}

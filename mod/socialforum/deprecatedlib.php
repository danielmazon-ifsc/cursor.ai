<?php

/**
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

// Deprecated a very long time ago.

/**
 * How many posts by other users are unrated by a given user in the given discussion?
 *
 * @param int $discussionid
 * @param int $userid
 * @return mixed
 * @deprecated since Moodle 1.1 - please do not use this function any more.
 */
function socialforum_count_unrated_posts($discussionid, $userid) {
    global $CFG, $DB;
    debugging('socialforum_count_unrated_posts() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    $sql = "SELECT COUNT(*) as num
              FROM {socialforum_posts}
             WHERE parent > 0
               AND discussion = :discussionid
               AND userid <> :userid";
    $params = array('discussionid' => $discussionid, 'userid' => $userid);
    $posts = $DB->get_record_sql($sql, $params);
    if ($posts) {
        $sql = "SELECT count(*) as num
                  FROM {socialforum_posts} p,
                       {rating} r
                 WHERE p.discussion = :discussionid AND
                       p.id = r.itemid AND
                       r.userid = userid AND
                       r.component = 'mod_socialforum' AND
                       r.ratingarea = 'post'";
        $rated = $DB->get_record_sql($sql, $params);
        if ($rated) {
            if ($posts->num > $rated->num) {
                return $posts->num - $rated->num;
            } else {
                return 0;    // Just in case there was a counting error
            }
        } else {
            return $posts->num;
        }
    } else {
        return 0;
    }
}

// Since Moodle 1.5.

/**
 * Returns the count of records for the provided user and discussion.
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $discussionid
 * @return bool
 * @deprecated since Moodle 1.5 - please do not use this function any more.
 */
function socialforum_tp_count_discussion_read_records($userid, $discussionid) {
    debugging('socialforum_tp_count_discussion_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $cutoffdate = isset($CFG->socialforum_oldpostdays) ? (time() - ($CFG->socialforum_oldpostdays * 24 * 60 * 60)) : 0;

    $sql = 'SELECT COUNT(DISTINCT p.id) ' .
            'FROM {socialforum_discussions} d ' .
            'LEFT JOIN {socialforum_read} r ON d.id = r.discussionid AND r.userid = ? ' .
            'LEFT JOIN {socialforum_posts} p ON p.discussion = d.id ' .
            'AND (p.modified < ? OR p.id = r.postid) ' .
            'WHERE d.id = ? ';

    return ($DB->count_records_sql($sql, array($userid, $cutoffdate, $discussionid)));
}

/**
 * Get all discussions started by a particular user in a course (or group)
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param int $userid
 * @param int $groupid
 * @return array
 * @deprecated since Moodle 1.5 - please do not use this function any more.
 */
function socialforum_get_user_discussions($courseid, $userid, $groupid = 0) {
    debugging('socialforum_get_user_discussions() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;
    $params = array($courseid, $userid);
    if ($groupid) {
        $groupselect = " AND d.groupid = ? ";
        $params[] = $groupid;
    } else {
        $groupselect = "";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, d.groupid, $allnames, u.email, u.picture, u.imagealt,
                                   f.type as socialforumtype, f.name as socialforumname, f.id as socialforumid
                              FROM {socialforum_discussions} d,
                                   {socialforum_posts} p,
                                   {user} u,
                                   {socialforum} f
                             WHERE d.course = ?
                               AND p.discussion = d.id
                               AND p.parent = 0
                               AND p.userid = u.id
                               AND u.id = ?
                               AND d.socialforum = f.id $groupselect
                          ORDER BY p.created DESC", $params);
}

// Since Moodle 1.6.

/**
 * Returns the count of posts for the provided socialforum and [optionally] group.
 * @global object
 * @global object
 * @param int $socialforumid
 * @param int|bool $groupid
 * @return int
 * @deprecated since Moodle 1.6 - please do not use this function any more.
 */
function socialforum_tp_count_socialforum_posts($socialforumid, $groupid = false) {
    debugging('socialforum_tp_count_socialforum_posts() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;
    $params = array($socialforumid);
    $sql = 'SELECT COUNT(*) ' .
            'FROM {socialforum_posts} fp,{socialforum_discussions} fd ' .
            'WHERE fd.socialforum = ? AND fp.discussion = fd.id';
    if ($groupid !== false) {
        $sql .= ' AND (fd.groupid = ? OR fd.groupid = -1)';
        $params[] = $groupid;
    }
    $count = $DB->count_records_sql($sql, $params);


    return $count;
}

/**
 * Returns the count of records for the provided user and socialforum and [optionally] group.
 * @global object
 * @global object
 * @param int $userid
 * @param int $socialforumid
 * @param int|bool $groupid
 * @return int
 * @deprecated since Moodle 1.6 - please do not use this function any more.
 */
function socialforum_tp_count_socialforum_read_records($userid, $socialforumid, $groupid = false) {
    debugging('socialforum_tp_count_socialforum_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->socialforum_oldpostdays * 24 * 60 * 60);

    $groupsel = '';
    $params = array($userid, $socialforumid, $cutoffdate);
    if ($groupid !== false) {
        $groupsel = "AND (d.groupid = ? OR d.groupid = -1)";
        $params[] = $groupid;
    }

    $sql = "SELECT COUNT(p.id)
              FROM  {socialforum_posts} p
                    JOIN {socialforum_discussions} d ON d.id = p.discussion
                    LEFT JOIN {socialforum_read} r   ON (r.postid = p.id AND r.userid= ?)
              WHERE d.socialforum = ?
                    AND (p.modified < $cutoffdate OR (p.modified >= ? AND r.id IS NOT NULL))
                    $groupsel";

    return $DB->get_field_sql($sql, $params);
}

// Since Moodle 1.7.

/**
 * Returns array of socialforum open modes.
 *
 * @return array
 * @deprecated since Moodle 1.7 - please do not use this function any more.
 */
function socialforum_get_open_modes() {
    debugging('socialforum_get_open_modes() is deprecated and will not be replaced.', DEBUG_DEVELOPER);
    return array();
}

// Since Moodle 1.9.

/**
 * Gets posts with all info ready for socialforum_print_post
 * We pass socialforumid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @param int $parent
 * @param int $socialforumid
 * @return array
 * @deprecated since Moodle 1.9 MDL-13303 - please do not use this function any more.
 */
function socialforum_get_child_posts($parent, $socialforumid) {
    debugging('socialforum_get_child_posts() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, $socialforumid AS socialforum, $allnames, u.email, u.picture, u.imagealt
                              FROM {socialforum_posts} p
                         LEFT JOIN {user} u ON p.userid = u.id
                             WHERE p.parent = ?
                          ORDER BY p.created ASC", array($parent));
}

/**
 * Gets posts with all info ready for socialforum_print_post
 * We pass socialforumid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @return mixed array of posts or false
 * @deprecated since Moodle 1.9 MDL-13303 - please do not use this function any more.
 */
function socialforum_get_discussion_posts($discussion, $sort, $socialforumid) {
    debugging('socialforum_get_discussion_posts() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, $socialforumid AS socialforum, $allnames, u.email, u.picture, u.imagealt
                              FROM {socialforum_posts} p
                         LEFT JOIN {user} u ON p.userid = u.id
                             WHERE p.discussion = ?
                               AND p.parent > 0 $sort", array($discussion));
}

// Since Moodle 2.0.

/**
 * Returns a list of ratings for a particular post - sorted.
 *
 * @param stdClass $context
 * @param int $postid
 * @param string $sort
 * @return array Array of ratings or false
 * @deprecated since Moodle 2.0 MDL-21657 - please do not use this function any more.
 */
function socialforum_get_ratings($context, $postid, $sort = "u.firstname ASC") {
    debugging('socialforum_get_ratings() is deprecated.', DEBUG_DEVELOPER);
    $options = new stdClass;
    $options->context = $context;
    $options->component = 'mod_socialforum';
    $options->ratingarea = 'post';
    $options->itemid = $postid;
    $options->sort = "ORDER BY $sort";

    $rm = new rating_manager();
    return $rm->get_all_ratings_for_item($options);
}

/**
 * Generate and return the track or no track link for a socialforum.
 *
 * @global object
 * @global object
 * @global object
 * @param object $socialforum the socialforum. Fields used are $socialforum->id and $socialforum->forcesubscribe.
 * @param array $messages
 * @param bool $fakelink
 * @return string
 * @deprecated since Moodle 2.0 MDL-14632 - please do not use this function any more.
 */
function socialforum_get_tracking_link($socialforum, $messages = array(), $fakelink = true) {
    debugging('socialforum_get_tracking_link() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $USER, $PAGE, $OUTPUT;

    static $strnotracksocialforum, $strtracksocialforum;

    if (isset($messages['tracksocialforum'])) {
        $strtracksocialforum = $messages['tracksocialforum'];
    }
    if (isset($messages['notracksocialforum'])) {
        $strnotracksocialforum = $messages['notracksocialforum'];
    }
    if (empty($strtracksocialforum)) {
        $strtracksocialforum = get_string('tracksocialforum', 'mod_socialforum');
    }
    if (empty($strnotracksocialforum)) {
        $strnotracksocialforum = get_string('notracksocialforum', 'mod_socialforum');
    }

    if (socialforum_tp_is_tracked($socialforum)) {
        $linktitle = $strnotracksocialforum;
        $linktext = $strnotracksocialforum;
    } else {
        $linktitle = $strtracksocialforum;
        $linktext = $strtracksocialforum;
    }

    $link = '';
    if ($fakelink) {
        $PAGE->requires->js('/mod/socialforum/socialforum.js');
        $PAGE->requires->js_function_call('socialforum_produce_tracking_link', Array($socialforum->id, $linktext, $linktitle));
        // use <noscript> to print button in case javascript is not enabled
        $link .= '<noscript>';
    }
    $url = new moodle_url('/mod/socialforum/settracking.php', array(
        'id' => $socialforum->id,
        'sesskey' => sesskey(),
    ));
    $link .= $OUTPUT->single_button($url, $linktext, 'get', array('title' => $linktitle));

    if ($fakelink) {
        $link .= '</noscript>';
    }

    return $link;
}

/**
 * Returns the count of records for the provided user and discussion.
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $discussionid
 * @return int
 * @deprecated since Moodle 2.0 MDL-14113 - please do not use this function any more.
 */
function socialforum_tp_count_discussion_unread_posts($userid, $discussionid) {
    debugging('socialforum_tp_count_discussion_unread_posts() is deprecated.', DEBUG_DEVELOPER);
    global $CFG, $DB;

    $cutoffdate = isset($CFG->socialforum_oldpostdays) ? (time() - ($CFG->socialforum_oldpostdays * 24 * 60 * 60)) : 0;

    $sql = 'SELECT COUNT(p.id) ' .
            'FROM {socialforum_posts} p ' .
            'LEFT JOIN {socialforum_read} r ON r.postid = p.id AND r.userid = ? ' .
            'WHERE p.discussion = ? ' .
            'AND p.modified >= ? AND r.id is NULL';

    return $DB->count_records_sql($sql, array($userid, $discussionid, $cutoffdate));
}

/**
 * Converts a socialforum to use the Roles System
 *
 * @deprecated since Moodle 2.0 MDL-23479 - please do not use this function any more.
 */
function socialforum_convert_to_roles() {
    debugging('socialforum_convert_to_roles() is deprecated and will not be replaced.', DEBUG_DEVELOPER);
}

/**
 * Returns all records in the 'socialforum_read' table matching the passed keys, indexed
 * by userid.
 *
 * @global object
 * @param int $userid
 * @param int $postid
 * @param int $discussionid
 * @param int $socialforumid
 * @return array
 * @deprecated since Moodle 2.0 MDL-14113 - please do not use this function any more.
 */
function socialforum_tp_get_read_records($userid = -1, $postid = -1, $discussionid = -1, $socialforumid = -1) {
    debugging('socialforum_tp_get_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $DB;
    $select = '';
    $params = array();

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

    return $DB->get_records_select('socialforum_read', $select, $params);
}

/**
 * Returns all read records for the provided user and discussion, indexed by postid.
 *
 * @global object
 * @param inti $userid
 * @param int $discussionid
 * @deprecated since Moodle 2.0 MDL-14113 - please do not use this function any more.
 */
function socialforum_tp_get_discussion_read_records($userid, $discussionid) {
    debugging('socialforum_tp_get_discussion_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $DB;
    $select = 'userid = ? AND discussionid = ?';
    $fields = 'postid, firstread, lastread';
    return $DB->get_records_select('socialforum_read', $select, array($userid, $discussionid), '', $fields);
}

// Deprecated in 2.3.

/**
 * This function gets run whenever user is enrolled into course
 *
 * @deprecated since Moodle 2.3 MDL-33166 - please do not use this function any more.
 * @param stdClass $cp
 * @return void
 */
function socialforum_user_enrolled($cp) {
    debugging('socialforum_user_enrolled() is deprecated. Please use socialforum_user_role_assigned instead.', DEBUG_DEVELOPER);
    global $DB;

    // NOTE: this has to be as fast as possible - we do not want to slow down enrolments!
    //       Originally there used to be 'mod/socialforum:initialsubscriptions' which was
    //       introduced because we did not have enrolment information in earlier versions...

    $sql = "SELECT f.id
              FROM {socialforum} f
         LEFT JOIN {socialforum_subscriptions} fs ON (fs.socialforum = f.id AND fs.userid = :userid)
             WHERE f.course = :courseid AND f.forcesubscribe = :initial AND fs.id IS NULL";
    $params = array('courseid' => $cp->courseid, 'userid' => $cp->userid, 'initial' => SOCIALFORUM_INITIALSUBSCRIBE);

    $socialforums = $DB->get_records_sql($sql, $params);
    foreach ($socialforums as $socialforum) {
        \mod_socialforum\subscriptions::subscribe_user($cp->userid, $socialforum);
    }
}

// Deprecated in 2.4.

/**
 * Checks to see if a user can view a particular post.
 *
 * @deprecated since Moodle 2.4 use socialforum_user_can_see_post() instead
 *
 * @param object $post
 * @param object $course
 * @param object $cm
 * @param object $socialforum
 * @param object $discussion
 * @param object $user
 * @return boolean
 */
function socialforum_user_can_view_post($post, $course, $cm, $socialforum, $discussion, $user = null) {
    debugging('socialforum_user_can_view_post() is deprecated. Please use socialforum_user_can_see_post() instead.', DEBUG_DEVELOPER);
    return socialforum_user_can_see_post($socialforum, $discussion, $post, $user, $cm);
}

// Deprecated in 2.6.

/**
 * SOCIALFORUM_TRACKING_ON - deprecated alias for SOCIALFORUM_TRACKING_FORCED.
 * @deprecated since 2.6
 */
define('SOCIALFORUM_TRACKING_ON', 2);

/**
 * @deprecated since Moodle 2.6
 * @see shorten_text()
 */
function socialforum_shorten_post($message) {
    throw new coding_exception('socialforum_shorten_post() can not be used any more. Please use shorten_text($message, $CFG->socialforum_shortpost) instead.');
}

// Deprecated in 2.8.

/**
 * @global object
 * @param int $userid
 * @param object $socialforum
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_socialforum\subscriptions::is_subscribed() instead
 */
function socialforum_is_subscribed($userid, $socialforum) {
    global $DB;
    debugging("socialforum_is_subscribed() has been deprecated, please use \\mod_socialforum\\subscriptions::is_subscribed() instead.", DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of socialforum.
    if (is_numeric($socialforum)) {
        $socialforum = $DB->get_record('socialforum', array('id' => $socialforum));
    }

    return mod_socialforum\subscriptions::is_subscribed($userid, $socialforum);
}

/**
 * Adds user to the subscriber list
 *
 * @param int $userid
 * @param int $socialforumid
 * @param context_module|null $context Module context, may be omitted if not known or if called for the current module set in page.
 * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
 * discussion subscriptions are removed too.
 * @deprecated since Moodle 2.8 use \mod_socialforum\subscriptions::subscribe_user() instead
 */
function socialforum_subscribe($userid, $socialforumid, $context = null, $userrequest = false) {
    global $DB;
    debugging("socialforum_subscribe() has been deprecated, please use \\mod_socialforum\\subscriptions::subscribe_user() instead.", DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of socialforum.
    $socialforum = $DB->get_record('socialforum', array('id' => $socialforumid));
    \mod_socialforum\subscriptions::subscribe_user($userid, $socialforum, $context, $userrequest);
}

/**
 * Removes user from the subscriber list
 *
 * @param int $userid
 * @param int $socialforumid
 * @param context_module|null $context Module context, may be omitted if not known or if called for the current module set in page.
 * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
 * discussion subscriptions are removed too.
 * @deprecated since Moodle 2.8 use \mod_socialforum\subscriptions::unsubscribe_user() instead
 */
function socialforum_unsubscribe($userid, $socialforumid, $context = null, $userrequest = false) {
    global $DB;
    debugging("socialforum_unsubscribe() has been deprecated, please use \\mod_socialforum\\subscriptions::unsubscribe_user() instead.", DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of socialforum.
    $socialforum = $DB->get_record('socialforum', array('id' => $socialforumid));
    \mod_socialforum\subscriptions::unsubscribe_user($userid, $socialforum, $context, $userrequest);
}

/**
 * Returns list of user objects that are subscribed to this socialforum.
 *
 * @param stdClass $course the course
 * @param stdClass $socialforum the socialforum
 * @param int $groupid group id, or 0 for all.
 * @param context_module $context the socialforum context, to save re-fetching it where possible.
 * @param string $fields requested user fields (with "u." table prefix)
 * @param boolean $considerdiscussions Whether to take discussion subscriptions and unsubscriptions into consideration.
 * @return array list of users.
 * @deprecated since Moodle 2.8 use \mod_socialforum\subscriptions::fetch_subscribed_users() instead
 */
function socialforum_subscribed_users($course, $socialforum, $groupid = 0, $context = null, $fields = null) {
    debugging("socialforum_subscribed_users() has been deprecated, please use \\mod_socialforum\\subscriptions::fetch_subscribed_users() instead.", DEBUG_DEVELOPER);

    \mod_socialforum\subscriptions::fetch_subscribed_users($socialforum, $groupid, $context, $fields);
}

/**
 * Determine whether the socialforum is force subscribed.
 *
 * @param object $socialforum
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_socialforum\subscriptions::is_forcesubscribed() instead
 */
function socialforum_is_forcesubscribed($socialforum) {
    debugging("socialforum_is_forcesubscribed() has been deprecated, please use \\mod_socialforum\\subscriptions::is_forcesubscribed() instead.", DEBUG_DEVELOPER);

    global $DB;
    if (!isset($socialforum->forcesubscribe)) {
        $socialforum = $DB->get_field('socialforum', 'forcesubscribe', array('id' => $socialforum));
    }

    return \mod_socialforum\subscriptions::is_forcesubscribed($socialforum);
}

/**
 * Set the subscription mode for a socialforum.
 *
 * @param int $socialforumid
 * @param mixed $value
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_socialforum\subscriptions::set_subscription_mode() instead
 */
function SOCIALFORUM_FORCESUBSCRIBE($socialforumid, $value = 1) {
    debugging("SOCIALFORUM_FORCESUBSCRIBE() has been deprecated, please use \\mod_socialforum\\subscriptions::set_subscription_mode() instead.", DEBUG_DEVELOPER);

    return \mod_socialforum\subscriptions::set_subscription_mode($socialforumid, $value);
}

/**
 * Get the current subscription mode for the socialforum.
 *
 * @param int|stdClass $socialforumid
 * @param mixed $value
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_socialforum\subscriptions::get_subscription_mode() instead
 */
function socialforum_get_forcesubscribed($socialforum) {
    debugging("socialforum_get_forcesubscribed() has been deprecated, please use \\mod_socialforum\\subscriptions::get_subscription_mode() instead.", DEBUG_DEVELOPER);

    global $DB;
    if (!isset($socialforum->forcesubscribe)) {
        $socialforum = $DB->get_field('socialforum', 'forcesubscribe', array('id' => $socialforum));
    }

    return \mod_socialforum\subscriptions::get_subscription_mode($socialforumid, $value);
}

/**
 * Get a list of socialforums in the specified course in which a user can change
 * their subscription preferences.
 *
 * @param stdClass $course The course from which to find subscribable socialforums.
 * @return array
 * @deprecated since Moodle 2.8 use \mod_socialforum\subscriptions::is_subscribed in combination wtih
 * \mod_socialforum\subscriptions::fill_subscription_cache_for_course instead.
 */
function socialforum_get_subscribed_socialforums($course) {
    debugging("socialforum_get_subscribed_socialforums() has been deprecated, please see " .
            "\\mod_socialforum\\subscriptions::is_subscribed::() " .
            " and \\mod_socialforum\\subscriptions::fill_subscription_cache_for_course instead.", DEBUG_DEVELOPER);

    global $USER, $CFG, $DB;
    $sql = "SELECT f.id
              FROM {socialforum} f
                   LEFT JOIN {socialforum_subscriptions} fs ON (fs.socialforum = f.id AND fs.userid = ?)
             WHERE f.course = ?
                   AND f.forcesubscribe <> " . SOCIALFORUM_DISALLOWSUBSCRIBE . "
                   AND (f.forcesubscribe = " . SOCIALFORUM_FORCESUBSCRIBE . " OR fs.id IS NOT NULL)";
    if ($subscribed = $DB->get_records_sql($sql, array($USER->id, $course->id))) {
        foreach ($subscribed as $s) {
            $subscribed[$s->id] = $s->id;
        }
        return $subscribed;
    } else {
        return array();
    }
}

/**
 * Returns an array of socialforums that the current user is subscribed to and is allowed to unsubscribe from
 *
 * @return array An array of unsubscribable socialforums
 * @deprecated since Moodle 2.8 use \mod_socialforum\subscriptions::get_unsubscribable_socialforums() instead
 */
function socialforum_get_optional_subscribed_socialforums() {
    debugging("socialforum_get_optional_subscribed_socialforums() has been deprecated, please use \\mod_socialforum\\subscriptions::get_unsubscribable_socialforums() instead.", DEBUG_DEVELOPER);

    return \mod_socialforum\subscriptions::get_unsubscribable_socialforums();
}

/**
 * Get the list of potential subscribers to a socialforum.
 *
 * @param object $socialforumcontext the socialforum context.
 * @param integer $groupid the id of a group, or 0 for all groups.
 * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
 * @param string $sort sort order. As for get_users_by_capability.
 * @return array list of users.
 * @deprecated since Moodle 2.8 use \mod_socialforum\subscriptions::get_potential_subscribers() instead
 */
function socialforum_get_potential_subscribers($socialforumcontext, $groupid, $fields, $sort = '') {
    debugging("socialforum_get_potential_subscribers() has been deprecated, please use \\mod_socialforum\\subscriptions::get_potential_subscribers() instead.", DEBUG_DEVELOPER);

    \mod_socialforum\subscriptions::get_potential_subscribers($socialforumcontext, $groupid, $fields, $sort);
}

/**
 * Builds and returns the body of the email notification in plain text.
 *
 * @uses CONTEXT_MODULE
 * @param object $course
 * @param object $cm
 * @param object $socialforum
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @param boolean $bare
 * @param string $replyaddress The inbound address that a user can reply to the generated e-mail with. [Since 2.8].
 * @return string The email body in plain text format.
 * @deprecated since Moodle 3.0 use \mod_socialforum\output\socialforum_post_email instead
 */
function socialforum_make_mail_text($course, $cm, $socialforum, $discussion, $post, $userfrom, $userto, $bare = false, $replyaddress = null) {
    global $PAGE;
    $renderable = new \mod_socialforum\output\socialforum_post_email(
            $course, $cm, $socialforum, $discussion, $post, $userfrom, $userto, socialforum_user_can_post($socialforum, $discussion, $userto, $cm, $course)
    );

    $modcontext = context_module::instance($cm->id);
    $renderable->viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);

    if ($bare) {
        $renderer = $PAGE->get_renderer('mod_socialforum', 'emaildigestfull', 'textemail');
    } else {
        $renderer = $PAGE->get_renderer('mod_socialforum', 'email', 'textemail');
    }

    debugging("socialforum_make_mail_text() has been deprecated, please use the \mod_socialforum\output\socialforum_post_email renderable instead.", DEBUG_DEVELOPER);

    return $renderer->render($renderable);
}

/**
 * Builds and returns the body of the email notification in html format.
 *
 * @param object $course
 * @param object $cm
 * @param object $socialforum
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @param string $replyaddress The inbound address that a user can reply to the generated e-mail with. [Since 2.8].
 * @return string The email text in HTML format
 * @deprecated since Moodle 3.0 use \mod_socialforum\output\socialforum_post_email instead
 */
function socialforum_make_mail_html($course, $cm, $socialforum, $discussion, $post, $userfrom, $userto, $replyaddress = null) {
    return socialforum_make_mail_post($course, $cm, $socialforum, $discussion, $post, $userfrom, $userto, socialforum_user_can_post($socialforum, $discussion, $userto, $cm, $course)
    );
}

/**
 * Given the data about a posting, builds up the HTML to display it and
 * returns the HTML in a string.  This is designed for sending via HTML email.
 *
 * @param object $course
 * @param object $cm
 * @param object $socialforum
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @param bool $ownpost
 * @param bool $reply
 * @param bool $link
 * @param bool $rate
 * @param string $footer
 * @return string
 * @deprecated since Moodle 3.0 use \mod_socialforum\output\socialforum_post_email instead
 */
function socialforum_make_mail_post($course, $cm, $socialforum, $discussion, $post, $userfrom, $userto, $ownpost = false, $reply = false, $link = false, $rate = false, $footer = "") {
    global $PAGE;
    $renderable = new \mod_socialforum\output\socialforum_post_email(
            $course, $cm, $socialforum, $discussion, $post, $userfrom, $userto, $reply);

    $modcontext = context_module::instance($cm->id);
    $renderable->viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);

    // Assume that this is being used as a standard socialforum email.
    $renderer = $PAGE->get_renderer('mod_socialforum', 'email', 'htmlemail');

    debugging("socialforum_make_mail_post() has been deprecated, please use the \mod_socialforum\output\socialforum_post_email renderable instead.", DEBUG_DEVELOPER);

    return $renderer->render($renderable);
}

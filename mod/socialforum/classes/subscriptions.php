<?php

/**
 * Social Forum subscription manager.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace mod_socialforum;

defined('MOODLE_INTERNAL') || die();

/**
 * Social Forum subscription manager.
 *
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class subscriptions {

    /**
     * The status value for an unsubscribed discussion.
     *
     * @var int
     */
    const SOCIALFORUM_DISCUSSION_UNSUBSCRIBED = -1;

    /**
     * The subscription cache for socialforums.
     *
     * The first level key is the user ID
     * The second level is the socialforum ID
     * The Value then is bool for subscribed of not.
     *
     * @var array[] An array of arrays.
     */
    protected static $socialforumcache = array();

    /**
     * The list of socialforums which have been wholly retrieved for the socialforum subscription cache.
     *
     * This allows for prior caching of an entire socialforum to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $fetchedsocialforums = array();

    /**
     * The subscription cache for socialforum discussions.
     *
     * The first level key is the user ID
     * The second level is the socialforum ID
     * The third level key is the discussion ID
     * The value is then the users preference (int)
     *
     * @var array[]
     */
    protected static $socialforumdiscussioncache = array();

    /**
     * The list of socialforums which have been wholly retrieved for the socialforum discussion subscription cache.
     *
     * This allows for prior caching of an entire socialforum to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $discussionfetchedsocialforums = array();

    /**
     * Whether a user is subscribed to this socialforum, or a discussion within
     * the socialforum.
     *
     * If a discussion is specified, then report whether the user is
     * subscribed to posts to this particular discussion, taking into
     * account the socialforum preference.
     *
     * If it is not specified then only the socialforum preference is considered.
     *
     * @param int $userid The user ID
     * @param \stdClass $socialforum The record of the socialforum to test
     * @param int $discussionid The ID of the discussion to check
     * @param $cm The coursemodule record. If not supplied, this will be calculated using get_fast_modinfo instead.
     * @return boolean
     */
    public static function is_subscribed($userid, $socialforum, $discussionid = null, $cm = null) {
        // If socialforum is force subscribed and has allowforcesubscribe, then user is subscribed.
        if (self::is_forcesubscribed($socialforum)) {
            if (!$cm) {
                $cm = get_fast_modinfo($socialforum->course)->instances['socialforum'][$socialforum->id];
            }
            if (has_capability('mod/socialforum:allowforcesubscribe', \context_module::instance($cm->id), $userid)) {
                return true;
            }
        }

        if ($discussionid === null) {
            return self::is_subscribed_to_socialforum($userid, $socialforum);
        }

        $subscriptions = self::fetch_discussion_subscription($socialforum->id, $userid);

        // Check whether there is a record for this discussion subscription.
        if (isset($subscriptions[$discussionid])) {
            return ($subscriptions[$discussionid] != self::SOCIALFORUM_DISCUSSION_UNSUBSCRIBED);
        }

        return self::is_subscribed_to_socialforum($userid, $socialforum);
    }

    /**
     * Whether a user is subscribed to this socialforum.
     *
     * @param int $userid The user ID
     * @param \stdClass $socialforum The record of the socialforum to test
     * @return boolean
     */
    protected static function is_subscribed_to_socialforum($userid, $socialforum) {
        return self::fetch_subscription_cache($socialforum->id, $userid);
    }

    /**
     * Helper to determine whether a socialforum has it's subscription mode set
     * to forced subscription.
     *
     * @param \stdClass $socialforum The record of the socialforum to test
     * @return bool
     */
    public static function is_forcesubscribed($socialforum) {
        return ($socialforum->forcesubscribe == SOCIALFORUM_FORCESUBSCRIBE);
    }

    /**
     * Helper to determine whether a socialforum has it's subscription mode set to disabled.
     *
     * @param \stdClass $socialforum The record of the socialforum to test
     * @return bool
     */
    public static function subscription_disabled($socialforum) {
        return ($socialforum->forcesubscribe == SOCIALFORUM_DISALLOWSUBSCRIBE);
    }

    /**
     * Helper to determine whether the specified socialforum can be subscribed to.
     *
     * @param \stdClass $socialforum The record of the socialforum to test
     * @return bool
     */
    public static function is_subscribable($socialforum) {
        return (!\mod_socialforum\subscriptions::is_forcesubscribed($socialforum) &&
                !\mod_socialforum\subscriptions::subscription_disabled($socialforum));
    }

    /**
     * Set the socialforum subscription mode.
     *
     * By default when called without options, this is set to SOCIALFORUM_FORCESUBSCRIBE.
     *
     * @param \stdClass $socialforum The record of the socialforum to set
     * @param int $status The new subscription state
     * @return bool
     */
    public static function set_subscription_mode($socialforumid, $status = 1) {
        global $DB;
        return $DB->set_field("socialforum", "forcesubscribe", $status, array("id" => $socialforumid));
    }

    /**
     * Returns the current subscription mode for the socialforum.
     *
     * @param \stdClass $socialforum The record of the socialforum to set
     * @return int The socialforum subscription mode
     */
    public static function get_subscription_mode($socialforum) {
        return $socialforum->forcesubscribe;
    }

    /**
     * Returns an array of socialforums that the current user is subscribed to and is allowed to unsubscribe from
     *
     * @return array An array of unsubscribable socialforums
     */
    public static function get_unsubscribable_socialforums() {
        global $USER, $DB;

        // Get courses that $USER is enrolled in and can see.
        $courses = enrol_get_my_courses();
        if (empty($courses)) {
            return array();
        }

        $courseids = array();
        foreach ($courses as $course) {
            $courseids[] = $course->id;
        }
        list($coursesql, $courseparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');

        // Get all socialforums from the user's courses that they are subscribed to and which are not set to forced.
        // It is possible for users to be subscribed to a socialforum in subscription disallowed mode so they must be listed
        // here so that that can be unsubscribed from.
        $sql = "SELECT f.id, cm.id as cm, cm.visible, f.course
                FROM {socialforum} f
                JOIN {course_modules} cm ON cm.instance = f.id
                JOIN {modules} m ON m.name = :modulename AND m.id = cm.module
                LEFT JOIN {socialforum_subscriptions} fs ON (fs.socialforum = f.id AND fs.userid = :userid)
                WHERE f.forcesubscribe <> :forcesubscribe
                AND fs.id IS NOT NULL
                AND cm.course
                $coursesql";
        $params = array_merge($courseparams, array(
            'modulename' => 'socialforum',
            'userid' => $USER->id,
            'forcesubscribe' => SOCIALFORUM_FORCESUBSCRIBE,
        ));
        $socialforums = $DB->get_recordset_sql($sql, $params);

        $unsubscribablesocialforums = array();
        foreach ($socialforums as $socialforum) {
            if (empty($socialforum->visible)) {
                // The socialforum is hidden - check if the user can view the socialforum.
                $context = \context_module::instance($socialforum->cm);
                if (!has_capability('moodle/course:viewhiddenactivities', $context)) {
                    // The user can't see the hidden socialforum to cannot unsubscribe.
                    continue;
                }
            }

            $unsubscribablesocialforums[] = $socialforum;
        }
        $socialforums->close();

        return $unsubscribablesocialforums;
    }

    /**
     * Get the list of potential subscribers to a socialforum.
     *
     * @param context_module $context the socialforum context.
     * @param integer $groupid the id of a group, or 0 for all groups.
     * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
     * @param string $sort sort order. As for get_users_by_capability.
     * @return array list of users.
     */
    public static function get_potential_subscribers($context, $groupid, $fields, $sort = '') {
        global $DB;

        // Only active enrolled users or everybody on the frontpage.
        list($esql, $params) = get_enrolled_sql($context, 'mod/socialforum:allowforcesubscribe', $groupid, true);
        if (!$sort) {
            list($sort, $sortparams) = users_order_by_sql('u');
            $params = array_merge($params, $sortparams);
        }

        $sql = "SELECT $fields
                FROM {user} u
                JOIN ($esql) je ON je.id = u.id
            ORDER BY $sort";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Fetch the socialforum subscription data for the specified userid and socialforum.
     *
     * @param int $socialforumid The socialforum to retrieve a cache for
     * @param int $userid The user ID
     * @return boolean
     */
    public static function fetch_subscription_cache($socialforumid, $userid) {
        if (isset(self::$socialforumcache[$userid]) && isset(self::$socialforumcache[$userid][$socialforumid])) {
            return self::$socialforumcache[$userid][$socialforumid];
        }
        self::fill_subscription_cache($socialforumid, $userid);

        if (!isset(self::$socialforumcache[$userid]) || !isset(self::$socialforumcache[$userid][$socialforumid])) {
            return false;
        }

        return self::$socialforumcache[$userid][$socialforumid];
    }

    /**
     * Fill the socialforum subscription data for the specified userid and socialforum.
     *
     * If the userid is not specified, then all subscription data for that socialforum is fetched in a single query and used
     * for subsequent lookups without requiring further database queries.
     *
     * @param int $socialforumid The socialforum to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache($socialforumid, $userid = null) {
        global $DB;

        if (!isset(self::$fetchedsocialforums[$socialforumid])) {
            // This socialforum has not been fetched as a whole.
            if (isset($userid)) {
                if (!isset(self::$socialforumcache[$userid])) {
                    self::$socialforumcache[$userid] = array();
                }

                if (!isset(self::$socialforumcache[$userid][$socialforumid])) {
                    if ($DB->record_exists('socialforum_subscriptions', array(
                                'userid' => $userid,
                                'socialforum' => $socialforumid,
                            ))) {
                        self::$socialforumcache[$userid][$socialforumid] = true;
                    } else {
                        self::$socialforumcache[$userid][$socialforumid] = false;
                    }
                }
            } else {
                $subscriptions = $DB->get_recordset('socialforum_subscriptions', array(
                    'socialforum' => $socialforumid,
                        ), '', 'id, userid');
                foreach ($subscriptions as $id => $data) {
                    if (!isset(self::$socialforumcache[$data->userid])) {
                        self::$socialforumcache[$data->userid] = array();
                    }
                    self::$socialforumcache[$data->userid][$socialforumid] = true;
                }
                self::$fetchedsocialforums[$socialforumid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Fill the socialforum subscription data for all socialforums that the specified userid can subscribe to in the specified course.
     *
     * @param int $courseid The course to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache_for_course($courseid, $userid) {
        global $DB;

        if (!isset(self::$socialforumcache[$userid])) {
            self::$socialforumcache[$userid] = array();
        }

        $sql = "SELECT
                    f.id AS socialforumid,
                    s.id AS subscriptionid
                FROM {socialforum} f
                LEFT JOIN {socialforum_subscriptions} s ON (s.socialforum = f.id AND s.userid = :userid)
                WHERE f.course = :course
                AND f.forcesubscribe <> :subscriptionforced";

        $subscriptions = $DB->get_recordset_sql($sql, array(
            'course' => $courseid,
            'userid' => $userid,
            'subscriptionforced' => SOCIALFORUM_FORCESUBSCRIBE,
        ));

        foreach ($subscriptions as $id => $data) {
            self::$socialforumcache[$userid][$id] = !empty($data->subscriptionid);
        }
        $subscriptions->close();
    }

    /**
     * Returns a list of user objects who are subscribed to this socialforum.
     *
     * @param stdClass $socialforum The socialforum record.
     * @param int $groupid The group id if restricting subscriptions to a group of users, or 0 for all.
     * @param context_module $context the socialforum context, to save re-fetching it where possible.
     * @param string $fields requested user fields (with "u." table prefix).
     * @param boolean $includediscussionsubscriptions Whether to take discussion subscriptions and unsubscriptions into consideration.
     * @return array list of users.
     */
    public static function fetch_subscribed_users($socialforum, $groupid = 0, $context = null, $fields = null, $includediscussionsubscriptions = false) {
        global $CFG, $DB;

        if (empty($fields)) {
            $allnames = get_all_user_name_fields(true, 'u');
            $fields = "u.id,
                      u.username,
                      $allnames,
                      u.maildisplay,
                      u.mailformat,
                      u.maildigest,
                      u.imagealt,
                      u.email,
                      u.emailstop,
                      u.city,
                      u.country,
                      u.lastaccess,
                      u.lastlogin,
                      u.picture,
                      u.timezone,
                      u.theme,
                      u.lang,
                      u.trackforums,
                      u.mnethostid";
        }

        // Retrieve the socialforum context if it wasn't specified.
        $context = socialforum_get_context($socialforum->id, $context);

        if (self::is_forcesubscribed($socialforum)) {
            $results = \mod_socialforum\subscriptions::get_potential_subscribers($context, $groupid, $fields, "u.email ASC");
        } else {
            // Only active enrolled users or everybody on the frontpage.
            list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
            $params['socialforumid'] = $socialforum->id;

            if ($includediscussionsubscriptions) {
                $params['ssocialforumid'] = $socialforum->id;
                $params['dssocialforumid'] = $socialforum->id;
                $params['unsubscribed'] = self::SOCIALFORUM_DISCUSSION_UNSUBSCRIBED;

                $sql = "SELECT $fields
                        FROM (
                            SELECT userid FROM {socialforum_subscriptions} s
                            WHERE
                                s.socialforum = :ssocialforumid
                                UNION
                            SELECT userid FROM {socialforum_discussion_subs} ds
                            WHERE
                                ds.socialforum = :dssocialforumid AND ds.preference <> :unsubscribed
                        ) subscriptions
                        JOIN {user} u ON u.id = subscriptions.userid
                        JOIN ($esql) je ON je.id = u.id
                        ORDER BY u.email ASC";
            } else {
                $sql = "SELECT $fields
                        FROM {user} u
                        JOIN ($esql) je ON je.id = u.id
                        JOIN {socialforum_subscriptions} s ON s.userid = u.id
                        WHERE
                          s.socialforum = :socialforumid
                        ORDER BY u.email ASC";
            }
            $results = $DB->get_records_sql($sql, $params);
        }

        // Guest user should never be subscribed to a socialforum.
        unset($results[$CFG->siteguest]);

        // Apply the activity module availability resetrictions.
        $cm = get_coursemodule_from_instance('socialforum', $socialforum->id, $socialforum->course);
        $modinfo = get_fast_modinfo($socialforum->course);
        $info = new \core_availability\info_module($modinfo->get_cm($cm->id));
        $results = $info->filter_user_list($results);

        return $results;
    }

    /**
     * Retrieve the discussion subscription data for the specified userid and socialforum.
     *
     * This is returned as an array of discussions for that socialforum which contain the preference in a stdClass.
     *
     * @param int $socialforumid The socialforum to retrieve a cache for
     * @param int $userid The user ID
     * @return array of stdClass objects with one per discussion in the socialforum.
     */
    public static function fetch_discussion_subscription($socialforumid, $userid = null) {
        self::fill_discussion_subscription_cache($socialforumid, $userid);

        if (!isset(self::$socialforumdiscussioncache[$userid]) || !isset(self::$socialforumdiscussioncache[$userid][$socialforumid])) {
            return array();
        }

        return self::$socialforumdiscussioncache[$userid][$socialforumid];
    }

    /**
     * Fill the discussion subscription data for the specified userid and socialforum.
     *
     * If the userid is not specified, then all discussion subscription data for that socialforum is fetched in a single query
     * and used for subsequent lookups without requiring further database queries.
     *
     * @param int $socialforumid The socialforum to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_discussion_subscription_cache($socialforumid, $userid = null) {
        global $DB;

        if (!isset(self::$discussionfetchedsocialforums[$socialforumid])) {
            // This socialforum hasn't been fetched as a whole yet.
            if (isset($userid)) {
                if (!isset(self::$socialforumdiscussioncache[$userid])) {
                    self::$socialforumdiscussioncache[$userid] = array();
                }

                if (!isset(self::$socialforumdiscussioncache[$userid][$socialforumid])) {
                    $subscriptions = $DB->get_recordset('socialforum_discussion_subs', array(
                        'userid' => $userid,
                        'socialforum' => $socialforumid,
                            ), null, 'id, discussion, preference');
                    foreach ($subscriptions as $id => $data) {
                        self::add_to_discussion_cache($socialforumid, $userid, $data->discussion, $data->preference);
                    }
                    $subscriptions->close();
                }
            } else {
                $subscriptions = $DB->get_recordset('socialforum_discussion_subs', array(
                    'socialforum' => $socialforumid,
                        ), null, 'id, userid, discussion, preference');
                foreach ($subscriptions as $id => $data) {
                    self::add_to_discussion_cache($socialforumid, $data->userid, $data->discussion, $data->preference);
                }
                self::$discussionfetchedsocialforums[$socialforumid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Add the specified discussion and user preference to the discussion
     * subscription cache.
     *
     * @param int $socialforumid The ID of the socialforum that this preference belongs to
     * @param int $userid The ID of the user that this preference belongs to
     * @param int $discussion The ID of the discussion that this preference relates to
     * @param int $preference The preference to store
     */
    protected static function add_to_discussion_cache($socialforumid, $userid, $discussion, $preference) {
        if (!isset(self::$socialforumdiscussioncache[$userid])) {
            self::$socialforumdiscussioncache[$userid] = array();
        }

        if (!isset(self::$socialforumdiscussioncache[$userid][$socialforumid])) {
            self::$socialforumdiscussioncache[$userid][$socialforumid] = array();
        }

        self::$socialforumdiscussioncache[$userid][$socialforumid][$discussion] = $preference;
    }

    /**
     * Reset the discussion cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking socialforum discussion subscription states.
     */
    public static function reset_discussion_cache() {
        self::$socialforumdiscussioncache = array();
        self::$discussionfetchedsocialforums = array();
    }

    /**
     * Reset the socialforum cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking socialforum subscription states.
     */
    public static function reset_socialforum_cache() {
        self::$socialforumcache = array();
        self::$fetchedsocialforums = array();
    }

    /**
     * Adds user to the subscriber list.
     *
     * @param int $userid The ID of the user to subscribe
     * @param \stdClass $socialforum The socialforum record for this socialforum.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *      module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return bool|int Returns true if the user is already subscribed, or the socialforum_subscriptions ID if the user was
     *     successfully subscribed.
     */
    public static function subscribe_user($userid, $socialforum, $context = null, $userrequest = false) {
        global $DB;

        if (self::is_subscribed($userid, $socialforum)) {
            return true;
        }

        $sub = new \stdClass();
        $sub->userid = $userid;
        $sub->socialforum = $socialforum->id;

        $result = $DB->insert_record("socialforum_subscriptions", $sub);

        if ($userrequest) {
            $discussionsubscriptions = $DB->get_recordset('socialforum_discussion_subs', array('userid' => $userid, 'socialforum' => $socialforum->id));
            $DB->delete_records_select('socialforum_discussion_subs', 'userid = :userid AND socialforum = :socialforumid AND preference <> :preference', array(
                'userid' => $userid,
                'socialforumid' => $socialforum->id,
                'preference' => self::SOCIALFORUM_DISCUSSION_UNSUBSCRIBED,
            ));

            // Reset the subscription caches for this socialforum.
            // We know that the there were previously entries and there aren't any more.
            if (isset(self::$socialforumdiscussioncache[$userid]) && isset(self::$socialforumdiscussioncache[$userid][$socialforum->id])) {
                foreach (self::$socialforumdiscussioncache[$userid][$socialforum->id] as $discussionid => $preference) {
                    if ($preference != self::SOCIALFORUM_DISCUSSION_UNSUBSCRIBED) {
                        unset(self::$socialforumdiscussioncache[$userid][$socialforum->id][$discussionid]);
                    }
                }
            }
        }

        // Reset the cache for this socialforum.
        self::$socialforumcache[$userid][$socialforum->id] = true;

        $context = socialforum_get_context($socialforum->id, $context);
        $params = array(
            'context' => $context,
            'objectid' => $result,
            'relateduserid' => $userid,
            'other' => array('socialforumid' => $socialforum->id),
        );
        $event = event\subscription_created::create($params);
        if ($userrequest && $discussionsubscriptions) {
            foreach ($discussionsubscriptions as $subscription) {
                $event->add_record_snapshot('socialforum_discussion_subs', $subscription);
            }
            $discussionsubscriptions->close();
        }
        $event->trigger();

        return $result;
    }

    /**
     * Removes user from the subscriber list
     *
     * @param int $userid The ID of the user to unsubscribe
     * @param \stdClass $socialforum The socialforum record for this socialforum.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return boolean Always returns true.
     */
    public static function unsubscribe_user($userid, $socialforum, $context = null, $userrequest = false) {
        global $DB;

        $sqlparams = array(
            'userid' => $userid,
            'socialforum' => $socialforum->id,
        );
        $DB->delete_records('socialforum_digests', $sqlparams);

        if ($socialforumsubscription = $DB->get_record('socialforum_subscriptions', $sqlparams)) {
            $DB->delete_records('socialforum_subscriptions', array('id' => $socialforumsubscription->id));

            if ($userrequest) {
                $discussionsubscriptions = $DB->get_recordset('socialforum_discussion_subs', $sqlparams);
                $DB->delete_records('socialforum_discussion_subs', array('userid' => $userid, 'socialforum' => $socialforum->id, 'preference' => self::SOCIALFORUM_DISCUSSION_UNSUBSCRIBED));

                // We know that the there were previously entries and there aren't any more.
                if (isset(self::$socialforumdiscussioncache[$userid]) && isset(self::$socialforumdiscussioncache[$userid][$socialforum->id])) {
                    self::$socialforumdiscussioncache[$userid][$socialforum->id] = array();
                }
            }

            // Reset the cache for this socialforum.
            self::$socialforumcache[$userid][$socialforum->id] = false;

            $context = socialforum_get_context($socialforum->id, $context);
            $params = array(
                'context' => $context,
                'objectid' => $socialforumsubscription->id,
                'relateduserid' => $userid,
                'other' => array('socialforumid' => $socialforum->id),
            );
            $event = event\subscription_deleted::create($params);
            $event->add_record_snapshot('socialforum_subscriptions', $socialforumsubscription);
            if ($userrequest && $discussionsubscriptions) {
                foreach ($discussionsubscriptions as $subscription) {
                    $event->add_record_snapshot('socialforum_discussion_subs', $subscription);
                }
                $discussionsubscriptions->close();
            }
            $event->trigger();
        }

        return true;
    }

    /**
     * Subscribes the user to the specified discussion.
     *
     * @param int $userid The userid of the user being subscribed
     * @param \stdClass $discussion The discussion to subscribe to
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @return boolean Whether a change was made
     */
    public static function subscribe_user_to_discussion($userid, $discussion, $context = null) {
        global $DB;

        // First check whether the user is subscribed to the discussion already.
        $subscription = $DB->get_record('socialforum_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference != self::SOCIALFORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is already subscribed to the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level subscription. Check for a socialforum level subscription.
        if ($DB->record_exists('socialforum_subscriptions', array('userid' => $userid, 'socialforum' => $discussion->socialforum))) {
            if ($subscription && $subscription->preference == self::SOCIALFORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is subscribed to the socialforum, but unsubscribed from the discussion, delete the discussion preference.
                $DB->delete_records('socialforum_discussion_subs', array('id' => $subscription->id));
                unset(self::$socialforumdiscussioncache[$userid][$discussion->socialforum][$discussion->id]);
            } else {
                // The user is already subscribed to the socialforum. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = time();
                $DB->update_record('socialforum_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid = $userid;
                $subscription->socialforum = $discussion->socialforum;
                $subscription->discussion = $discussion->id;
                $subscription->preference = time();

                $subscription->id = $DB->insert_record('socialforum_discussion_subs', $subscription);
                self::$socialforumdiscussioncache[$userid][$discussion->socialforum][$discussion->id] = $subscription->preference;
            }
        }

        $context = socialforum_get_context($discussion->socialforum, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'socialforumid' => $discussion->socialforum,
                'discussion' => $discussion->id,
            ),
        );
        $event = event\discussion_subscription_created::create($params);
        $event->trigger();

        return true;
    }

    /**
     * Unsubscribes the user from the specified discussion.
     *
     * @param int $userid The userid of the user being unsubscribed
     * @param \stdClass $discussion The discussion to unsubscribe from
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @return boolean Whether a change was made
     */
    public static function unsubscribe_user_from_discussion($userid, $discussion, $context = null) {
        global $DB;

        if (is_object($discussion) && is_object($DB)) {
            // First check whether the user's subscription preference for this discussion.
            $subscription = $DB->get_record('socialforum_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
            if ($subscription) {
                if ($subscription->preference == self::SOCIALFORUM_DISCUSSION_UNSUBSCRIBED) {
                    // The user is already unsubscribed from the discussion. Ignore.
                    return false;
                }
            }
            // No discussion-level preference. Check for a socialforum level subscription.
            if (!$DB->record_exists('socialforum_subscriptions', array('userid' => $userid, 'socialforum' => $discussion->socialforum))) {
                if ($subscription && $subscription->preference != self::SOCIALFORUM_DISCUSSION_UNSUBSCRIBED) {
                    // The user is not subscribed to the socialforum, but subscribed from the discussion, delete the discussion subscription.
                    $DB->delete_records('socialforum_discussion_subs', array('id' => $subscription->id));
                    unset(self::$socialforumdiscussioncache[$userid][$discussion->socialforum][$discussion->id]);
                } else {
                    // The user is not subscribed from the socialforum. Ignore.
                    return false;
                }
            } else {
                if ($subscription) {
                    $subscription->preference = self::SOCIALFORUM_DISCUSSION_UNSUBSCRIBED;
                    $DB->update_record('socialforum_discussion_subs', $subscription);
                } else {
                    $subscription = new \stdClass();
                    $subscription->userid = $userid;
                    $subscription->socialforum = $discussion->socialforum;
                    $subscription->discussion = $discussion->id;
                    $subscription->preference = self::SOCIALFORUM_DISCUSSION_UNSUBSCRIBED;

                    $subscription->id = $DB->insert_record('socialforum_discussion_subs', $subscription);
                }
                self::$socialforumdiscussioncache[$userid][$discussion->socialforum][$discussion->id] = $subscription->preference;
            }

            $context = socialforum_get_context($discussion->socialforum, $context);
            $params = array(
                'context' => $context,
                'objectid' => $subscription->id,
                'relateduserid' => $userid,
                'other' => array(
                    'socialforumid' => $discussion->socialforum,
                    'discussion' => $discussion->id,
                ),
            );
            $event = event\discussion_subscription_deleted::create($params);
            $event->trigger();

            return true;
        }
        return false;
    }

}

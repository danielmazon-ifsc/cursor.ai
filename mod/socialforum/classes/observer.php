<?php

/**
 * Event observers used in socialforum.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

use mod_socialforum\subscriptions;

/**
 * Event observer for mod_socialforum.
 */
class mod_socialforum_observer {

    /**
     * Triggered via user_enrolment_deleted event.
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        global $DB;

        // NOTE: this has to be as fast as possible.
        // Get user enrolment info from event.
        $cp = (object) $event->other['userenrolment'];
        if ($cp->lastenrol) {
            if (!$socialforums = $DB->get_records('socialforum', array('course' => $cp->courseid), '', 'id')) {
                return;
            }
            list($socialforumselect, $params) = $DB->get_in_or_equal(array_keys($socialforums), SQL_PARAMS_NAMED);
            $params['userid'] = $cp->userid;

            $DB->delete_records_select('socialforum_digests', 'userid = :userid AND socialforum ' . $socialforumselect, $params);
            $DB->delete_records_select('socialforum_subscriptions', 'userid = :userid AND socialforum ' . $socialforumselect, $params);
            $DB->delete_records_select('socialforum_track_prefs', 'userid = :userid AND socialforumid ' . $socialforumselect, $params);
            $DB->delete_records_select('socialforum_read', 'userid = :userid AND socialforumid ' . $socialforumselect, $params);
        }
    }

    /**
     * Observer for role_assigned event.
     *
     * @param \core\event\role_assigned $event
     * @return void
     */
    public static function role_assigned(\core\event\role_assigned $event) {
        global $CFG, $DB;

        $context = context::instance_by_id($event->contextid, MUST_EXIST);

        // If contextlevel is course then only subscribe user. Role assignment
        // at course level means user is enroled in course and can subscribe to socialforum.
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        // Social Forum lib required for the constant used below.
        require_once($CFG->dirroot . '/mod/socialforum/lib.php');

        $userid = $event->relateduserid;
        $sql = "SELECT f.id, f.course as course, cm.id AS cmid, f.forcesubscribe
                  FROM {socialforum} f
                  JOIN {course_modules} cm ON (cm.instance = f.id)
                  JOIN {modules} m ON (m.id = cm.module)
             LEFT JOIN {socialforum_subscriptions} fs ON (fs.socialforum = f.id AND fs.userid = :userid)
                 WHERE f.course = :courseid
                   AND f.forcesubscribe = :initial
                   AND m.name = 'socialforum'
                   AND fs.id IS NULL";
        $params = array('courseid' => $context->instanceid, 'userid' => $userid, 'initial' => SOCIALFORUM_INITIALSUBSCRIBE);

        $socialforums = $DB->get_records_sql($sql, $params);
        foreach ($socialforums as $socialforum) {
            // If user doesn't have allowforcesubscribe capability then don't subscribe.
            $modcontext = context_module::instance($socialforum->cmid);
            if (has_capability('mod/socialforum:allowforcesubscribe', $modcontext, $userid)) {
                \mod_socialforum\subscriptions::subscribe_user($userid, $socialforum, $modcontext);
            }
        }
    }

    /**
     * Observer for \core\event\course_module_created event.
     *
     * @param \core\event\course_module_created $event
     * @return void
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        global $CFG;

        if ($event->other['modulename'] === 'socialforum') {
            // Include the socialforum library to make use of the socialforum_instance_created function.
            require_once($CFG->dirroot . '/mod/socialforum/lib.php');

            $socialforum = $event->get_record_snapshot('socialforum', $event->other['instanceid']);
            socialforum_instance_created($event->get_context(), $socialforum);
        }
    }

    /**
     * Observer for \core\event\course_completed event.
     *
     * @param \core\event\course_completed $event
     * @return void
     */
    public static function course_completed(\core\event\course_completed $event) {
        global $DB;
        // Unsubscribe user from all social forums of a course
        $userid = $event->relateduserid;
        $courseid = $event->courseid;
        $socialforums = $DB->get_records('socialforum', array('course' => $courseid));
        foreach ($socialforums as $socialforum) {
            $discussions = subscriptions::fetch_discussion_subscription($socialforum->id, $userid);
            foreach ($discussions as $discussion) {
                subscriptions::unsubscribe_user_from_discussion($userid, $discussion);
            }
            subscriptions::unsubscribe_user($userid, $socialforum);
        }
    }

}

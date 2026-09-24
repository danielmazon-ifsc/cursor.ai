<?php

/**
 * Group observers.
 *
 * @package    mod_cquiz
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */

namespace mod_cquiz;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/cquiz/locallib.php');

/**
 * Group observers class.
 *
 * @package    mod_cquiz
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class group_observers {

    /**
     * Flag whether a course reset is in progress or not.
     *
     * @var int The course ID.
     */
    protected static $resetinprogress = false;

    /**
     * A course reset has started.
     *
     * @param \core\event\base $event The event.
     * @return void
     */
    public static function course_reset_started($event) {
        self::$resetinprogress = $event->courseid;
    }

    /**
     * A course reset has ended.
     *
     * @param \core\event\base $event The event.
     * @return void
     */
    public static function course_reset_ended($event) {
        if (!empty(self::$resetinprogress)) {
            if (!empty($event->other['reset_options']['reset_groups_remove'])) {
                cquiz_process_group_deleted_in_course($event->courseid);
            }
            if (!empty($event->other['reset_options']['reset_groups_members'])) {
                cquiz_update_open_attempts(array('courseid' => $event->courseid));
            }
        }

        self::$resetinprogress = null;
    }

    /**
     * A group was deleted.
     *
     * @param \core\event\base $event The event.
     * @return void
     */
    public static function group_deleted($event) {
        if (!empty(self::$resetinprogress)) {
            // We will take care of that once the course reset ends.
            return;
        }
        cquiz_process_group_deleted_in_course($event->courseid);
    }

    /**
     * A group member was removed.
     *
     * @param \core\event\base $event The event.
     * @return void
     */
    public static function group_member_added($event) {
        cquiz_update_open_attempts(array('userid' => $event->relateduserid, 'groupid' => $event->objectid));
    }

    /**
     * A group member was deleted.
     *
     * @param \core\event\base $event The event.
     * @return void
     */
    public static function group_member_removed($event) {
        if (!empty(self::$resetinprogress)) {
            // We will take care of that once the course reset ends.
            return;
        }
        cquiz_update_open_attempts(array('userid' => $event->relateduserid, 'groupid' => $event->objectid));
    }

}

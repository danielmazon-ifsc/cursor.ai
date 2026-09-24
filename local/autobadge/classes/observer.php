<?php

namespace local_autobadge;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * Process course completion events
     * 
     */
    public static function completion_changed($event) {
        $courseid = (int) $event->courseid;
        $userid = (int) $event->relateduserid;
        if ($courseid <= 0 || $userid <= 0) {
            return;
        }
        autobadge::check_badge_awarding($courseid, $userid);
    }

}

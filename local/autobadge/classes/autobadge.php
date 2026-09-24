<?php

namespace local_autobadge;

defined('MOODLE_INTERNAL') || die();

// See coin_stack.php: pull $CFG into scope so these top-level require_once
// calls don't collapse to "/badgeslib.php" (a fatal, uncatchable failure)
// when this class file is first autoloaded from inside a function/method that
// didn't declare `global $CFG`.
global $CFG;

require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->libdir . '/modinfolib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/datalib.php');

use core_badges\badge;
use moodle_url;
use html_writer;

/**
 * Class autobadge manages automatic badges awarding
 *
 * @package local_autobadge
 */
class autobadge {

    /**
     * Process course completions
     *
     * @param int $courseid Course which completion status has changed
     * @param int $userid Student who completed the course
     */
    public static function check_badge_awarding($courseid, $userid) {
        if (!is_numeric($courseid) || $courseid <= 0) {
            throw new \invalid_parameter_exception('courseid must be positive integer: ' . $courseid);
        }
        if (!is_numeric($userid) || $userid <= 0) {
            throw new \invalid_parameter_exception('userid must be positive integer: ' . $userid);
        }

        autobadge::award_badge($userid, $courseid);
    }

    /**
     * Resolve a badge row by expected name, preferring the course-scoped badge.
     *
     * @param int $courseid
     * @param string $name Badge name (shortname or shortnamemaster)
     * @return \stdClass|null
     */
    public static function resolve_badge_record(int $courseid, string $name): ?\stdClass {
        global $DB;

        if ($courseid > 0) {
            $badge = $DB->get_record('badge', ['name' => $name, 'courseid' => $courseid]);
            if ($badge) {
                return $badge;
            }
        }

        // Legacy sites: single badge with this name (avoid ambiguity when multiple exist).
        $candidates = $DB->get_records('badge', ['name' => $name], '', 'id,courseid,name');
        if (count($candidates) === 1) {
            return reset($candidates);
        }

        return null;
    }

    /**
     * @param int $userid
     * @param int $courseid
     * @return float
     */
    protected static function get_student_grade(int $userid, int $courseid): float {
        if (class_exists('\\local_studypace\\studypace')
                && method_exists('\\local_studypace\\studypace', 'get_course_grade_for_gamification')) {
            return (float) \local_studypace\studypace::get_course_grade_for_gamification($userid, $courseid);
        }
        if (class_exists('\\format_specialization')
                && method_exists('\\format_specialization', 'get_student_grade')) {
            return (float) \format_specialization::get_student_grade($userid, $courseid);
        }
        return 0.0;
    }

    /**
     * @param int $userid
     * @param int $courseid
     * @return \stdClass|null
     */
    protected static function get_badge_to_award($userid, $courseid) {
        global $DB;

        $studentgrade = self::get_student_grade($userid, $courseid);
        $badgeextension = ($studentgrade >= 90) ? 'master' : '';
        $course = $DB->get_record('course', ['id' => $courseid], 'id, shortname', MUST_EXIST);
        $badgename = $course->shortname . $badgeextension;

        $badge = self::resolve_badge_record((int) $courseid, $badgename);

        // High-grade students (>= 90) expect the "<shortname>master" badge, but
        // some Eixos only have the standard "<shortname>" badge defined. Without
        // this fallback those students would receive NO badge at all (resolve
        // returns null → award_badge() aborts), which is strictly worse than
        // giving them the standard badge they also qualify for.
        if (!$badge && $badgeextension !== '') {
            $badge = self::resolve_badge_record((int) $courseid, $course->shortname);
        }

        return $badge;
    }

    /**
     * Send a message to an user informing a badge award
     *
     * @param int $userid Id of the user to whom the message will be sent
     * @param int $courseid Course which badge is related
     * @param int $badgeid Badge awarded
     */
    protected static function send_message($userid, $courseid, $badgeid) {
        global $CFG, $DB;

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$user) {
            debugging('user not found: ' . $userid);
            return;
        }
        $firstname = $user->firstname;
        $fullname = $user->firstname . ' ' . $user->lastname;
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            debugging('course not found: ' . $courseid);
            return;
        }
        $coursename = $course->fullname;
        $badge = $DB->get_record('badge', ['id' => $badgeid]);
        if (!$badge) {
            debugging('badge not found: ' . $badgeid);
            return;
        }
        $badgename = $badge->name;
        $badgedescription = $badge->description;

        $message = get_config('local_autobadge', 'awardmessage');
        $messagesubject = get_config('local_autobadge', 'awardsubject');

        $key = ['{$a->fullname}', '{$a->firstname}', '{$a->coursename}', '{$a->badgename}', '{$a->badgedescription}'];
        $value = [$fullname, $firstname, $coursename, $badgename, $badgedescription];
        $message = str_replace($key, $value, $message);
        $messagesubject = str_replace($key, $value, $messagesubject);
        if (strpos($message, '<') === false) {
            $messagetext = $message;
            $messagehtml = text_to_html($messagetext, null, false, true);
        } else {
            $messagehtml = format_text($message, FORMAT_HTML, ['para' => false, 'newlines' => true, 'filter' => true]);
            $messagetext = html_to_text($messagehtml);
        }

        $adminids = explode(',', $CFG->siteadmins);
        $sender = get_complete_user_data('id', reset($adminids));

        $update = new \core\message\message();
        $update->component = 'local_autobadge';
        $update->name = 'autobadgecontrol';
        $update->notification = 1;
        $update->courseid = $courseid;
        $update->userfrom = $sender;
        $update->userto = $user;
        $update->subject = $messagesubject;
        $update->fullmessage = $messagetext;
        $update->fullmessageformat = FORMAT_PLAIN;
        $update->fullmessagehtml = $messagehtml;
        $update->smallmessage = $messagetext;
        $update->contexturl = null;
        $update->contexturlname = null;

        message_send($update);
    }

    public static function get_badge_name_url($badgeid) {
        $badge = new badge($badgeid);
        $context = $badge->courseid > 0 ? \context_course::instance($badge->courseid) : \context_system::instance();
        $badgeimageurl = moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f3', false);
        return [$badge->name, $badgeimageurl];
    }

    public static function print_badge_image($badgeid, $size = 'small') {
        unset($size);
        list($badgename, $badgeurl) = autobadge::get_badge_name_url($badgeid);
        $badgeurl->param('refresh', rand(1, 10000));
        $attributes = [
            'src' => $badgeurl,
            'alt' => s($badgename),
            'class' => 'activatebadge',
            'data-toggle' => 'tooltip',
            'data-placement' => 'bottom',
            'title' => $badgename,
        ];
        return html_writer::empty_tag('img', $attributes);
    }

    /**
     * @param int $userid
     * @param int $courseid
     * @param bool $notify
     */
    public static function award_badge($userid, $courseid, $notify = true) {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'id, shortname');
        if (!$course) {
            return;
        }
        $badgerecord = autobadge::get_badge_to_award($userid, $courseid);
        if (!$badgerecord) {
            debugging('Badge not found for course ' . $course->shortname . ' (id ' . $courseid . ')', DEBUG_NORMAL);
            return;
        }
        $badge = new badge($badgerecord->id);
        if (!$badge->is_issued($userid)) {
            // When called quietly (background heal / backfill, $notify === false),
            // issue the badge WITHOUT baking. core_badges\badge::issue() bakes by
            // default: it re-reads the badge PNG and rewrites it through GD /
            // PNG-chunk surgery (badges_bake), which can hard-crash the PHP worker
            // (segfault or OOM) on a malformed/oversized badge image — taking the
            // whole scheduled heal task down with it before Moodle can even log
            // the failure (the "task log shows only the header, 0 writes, retries
            // forever" symptom). The badge_issued row created either way is all the
            // gamification gap scanner needs; the portable baked image is
            // regenerated on demand when the user downloads the badge.
            $nobake = ($notify === false);
            $badge->issue($userid, $nobake);
            if ($notify) {
                autobadge::send_message($userid, $courseid, $badgerecord->id);
            }
        }
    }

    public static function get_user_badges($userid) {
        global $DB;

        $userbadges = [];
        $sql = 'SELECT bi.badgeid, b.name, b.description '
                . 'FROM {badge_issued} bi, {badge} b '
                . 'WHERE bi.badgeid = b.id '
                . 'AND bi.userid = :userid';
        $badgerecords = $DB->get_records_sql($sql, ['userid' => $userid]);
        $seq = 0;
        foreach ($badgerecords as $badgerecord) {
            $badge = new \stdClass();
            $badge->seq = $seq;
            $badge->id = $badgerecord->badgeid;
            list($badge->name, $badge->imageurl) = autobadge::get_badge_name_url($badge->id);
            $badge->badgedesc = $badgerecord->description;
            $userbadges[] = $badge;
            ++$seq;
        }
        return $userbadges;
    }
}

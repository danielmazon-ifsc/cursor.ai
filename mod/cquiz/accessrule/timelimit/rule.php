<?php

/**
 * Implementaton of the cquizaccess_timelimit plugin.
 *
 * @package    cquizaccess
 * @subpackage timelimit
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/cquiz/accessrule/accessrulebase.php');

/**
 * A rule representing the time limit. It does not actually restrict access, but we use this
 * class to encapsulate some of the relevant code.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquizaccess_timelimit extends cquiz_access_rule_base {

    public static function make(cquiz $cquizobj, $timenow, $canignoretimelimits) {

        if (empty($cquizobj->get_cquiz()->timelimit) || $canignoretimelimits) {
            return null;
        }

        return new self($cquizobj, $timenow);
    }

    public function description() {
        return get_string('cquiztimelimit', 'cquizaccess_timelimit', format_time($this->cquiz->timelimit));
    }

    public function end_time($attempt) {
        return $attempt->timestart + $this->cquiz->timelimit;
    }

    public function time_left_display($attempt, $timenow) {
        // If this is a teacher preview after the time limit expires, don't show the time_left
        $endtime = $this->end_time($attempt);
        if ($attempt->preview && $timenow > $endtime) {
            return false;
        }
        return $endtime - $timenow;
    }

    public function is_preflight_check_required($attemptid) {
        // Warning only required if the attempt is not already started.
        return $attemptid === null;
    }

    public function add_preflight_check_form_fields(mod_cquiz_preflight_check_form $cquizform, MoodleQuickForm $mform, $attemptid) {
        $mform->addElement('header', 'honestycheckheader', get_string('confirmstartheader', 'cquizaccess_timelimit'));
        $mform->addElement('static', 'honestycheckmessage', '', get_string('confirmstart', 'cquizaccess_timelimit', format_time($this->cquiz->timelimit)));
    }

}

<?php

/**
 * Implementaton of the cquizaccess_numattempts plugin.
 *
 * @package    cquizaccess
 * @subpackage numattempts
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/cquiz/accessrule/accessrulebase.php');

/**
 * A rule controlling the number of attempts allowed.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquizaccess_numattempts extends cquiz_access_rule_base {

    public static function make(cquiz $cquizobj, $timenow, $canignoretimelimits) {

        if ($cquizobj->get_num_attempts_allowed() == 0) {
            return null;
        }

        return new self($cquizobj, $timenow);
    }

    public function description() {
        return get_string('attemptsallowedn', 'cquizaccess_numattempts', $this->cquiz->attempts);
    }

    public function prevent_new_attempt($numprevattempts, $lastattempt) {
        if ($numprevattempts >= $this->cquiz->attempts) {
            return get_string('nomoreattempts', 'cquiz');
        }
        return false;
    }

    public function is_finished($numprevattempts, $lastattempt) {
        return $numprevattempts >= $this->cquiz->attempts;
    }

}

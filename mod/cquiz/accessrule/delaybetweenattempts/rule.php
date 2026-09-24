<?php

/**
 * Implementaton of the cquizaccess_delaybetweenattempts plugin.
 *
 * @package    cquizaccess
 * @subpackage delaybetweenattempts
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/cquiz/accessrule/accessrulebase.php');

/**
 * A rule imposing the delay between attempts settings.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquizaccess_delaybetweenattempts extends cquiz_access_rule_base {

    public static function make(cquiz $cquizobj, $timenow, $canignoretimelimits) {
        if (empty($cquizobj->get_cquiz()->delay1) && empty($cquizobj->get_cquiz()->delay2) && empty($cquizobj->get_cquiz()->attemptsmaxdelay)) {
            return null;
        }

        return new self($cquizobj, $timenow);
    }

    public function prevent_new_attempt($numprevattempts, $lastattempt) {
        if ($this->cquiz->attempts > 0 && $numprevattempts >= $this->cquiz->attempts) {
            // No more attempts allowed anyway.
            return false;
        }
        if ($this->cquiz->timeclose != 0 && $this->timenow > $this->cquiz->timeclose) {
            // No more attempts allowed anyway.
            return false;
        }
        $nextstarttime = $this->compute_next_start_time($numprevattempts, $lastattempt);
        if ($this->timenow < $nextstarttime) {
            if ($this->cquiz->timeclose == 0 || $nextstarttime <= $this->cquiz->timeclose) {
                return get_string('youmustwait', 'cquizaccess_delaybetweenattempts', userdate($nextstarttime));
            } else {
                return get_string('youcannotwait', 'cquizaccess_delaybetweenattempts');
            }
        }
        if (isset($this->cquiz->attemptsmaxdelay)) {
            $maxtstarttime = $this->compute_max_next_attempt_time($numprevattempts, $lastattempt);
            if ($maxtstarttime > 0 && $this->timenow > $maxtstarttime) {
                return get_string('waitedtoolong', 'cquizaccess_delaybetweenattempts', userdate($maxtstarttime));
            }
        }
        return false;
    }

    /**
     * Compute the next time a student would be allowed to start an attempt,
     * according to this rule.
     * @param int $numprevattempts number of previous attempts.
     * @param object $lastattempt information about the previous attempt.
     * @return number the time.
     */
    protected function compute_next_start_time($numprevattempts, $lastattempt) {
        if ($numprevattempts == 0) {
            return 0;
        }

        $lastattemptfinish = $lastattempt->timefinish;
        if ($this->cquiz->timelimit > 0) {
            $lastattemptfinish = min($lastattemptfinish, $lastattempt->timestart + $this->cquiz->timelimit);
        }

        if ($numprevattempts == 1 && $this->cquiz->delay1) {
            return $lastattemptfinish + $this->cquiz->delay1;
        } else if ($numprevattempts > 1 && $this->cquiz->delay2) {
            return $lastattemptfinish + $this->cquiz->delay2;
        }
        return 0;
    }

    /**
     * Compute the maximum time for new attempts
     * 
     * @param int $numprevattempts number of previous attempts.
     * @param object $lastattempt information about the first attempt.
     * @return number the time.
     */
    protected function compute_max_next_attempt_time($numprevattempts, $lastattempt) {
        if ($numprevattempts == 0) {
            return 0;
        }

        $firstattemptfinish = $lastattempt->timefinish;
        return $firstattemptfinish + $this->cquiz->attemptsmaxdelay;
    }

    public function is_finished($numprevattempts, $lastattempt) {
        $nextstarttime = $this->compute_next_start_time($numprevattempts, $lastattempt);
        return $this->timenow <= $nextstarttime &&
                $this->cquiz->timeclose != 0 && $nextstarttime >= $this->cquiz->timeclose;
    }

}

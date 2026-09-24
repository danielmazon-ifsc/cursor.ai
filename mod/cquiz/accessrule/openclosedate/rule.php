<?php

/**
 * Implementaton of the cquizaccess_openclosedate plugin.
 *
 * @package    cquizaccess
 * @subpackage openclosedate
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/cquiz/accessrule/accessrulebase.php');

/**
 * A rule enforcing open and close dates.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquizaccess_openclosedate extends cquiz_access_rule_base {

    public static function make(cquiz $cquizobj, $timenow, $canignoretimelimits) {
        // This rule is always used, even if the cquiz has no open or close date.
        return new self($cquizobj, $timenow);
    }

    public function description() {
        $result = array();
        if ($this->timenow < $this->cquiz->timeopen) {
            $result[] = get_string('cquiznotavailable', 'cquizaccess_openclosedate', userdate($this->cquiz->timeopen));
            if ($this->cquiz->timeclose) {
                $result[] = get_string('cquizcloseson', 'cquiz', userdate($this->cquiz->timeclose));
            }
        } else if ($this->cquiz->timeclose && $this->timenow > $this->cquiz->timeclose) {
            $result[] = get_string('cquizclosed', 'cquiz', userdate($this->cquiz->timeclose));
        } else {
            if ($this->cquiz->timeopen) {
                $result[] = get_string('cquizopenedon', 'cquiz', userdate($this->cquiz->timeopen));
            }
            if ($this->cquiz->timeclose) {
                $result[] = get_string('cquizcloseson', 'cquiz', userdate($this->cquiz->timeclose));
            }
        }

        return $result;
    }

    public function prevent_access() {
        $message = get_string('notavailable', 'cquizaccess_openclosedate');

        if ($this->timenow < $this->cquiz->timeopen) {
            return $message;
        }

        if (!$this->cquiz->timeclose) {
            return false;
        }

        if ($this->timenow <= $this->cquiz->timeclose) {
            return false;
        }

        if ($this->cquiz->overduehandling != 'graceperiod') {
            return $message;
        }

        if ($this->timenow <= $this->cquiz->timeclose + $this->cquiz->graceperiod) {
            return false;
        }

        return $message;
    }

    public function is_finished($numprevattempts, $lastattempt) {
        return $this->cquiz->timeclose && $this->timenow > $this->cquiz->timeclose;
    }

    public function end_time($attempt) {
        if ($this->cquiz->timeclose) {
            return $this->cquiz->timeclose;
        }
        return false;
    }

    public function time_left_display($attempt, $timenow) {
        // If this is a teacher preview after the close date, do not show
        // the time.
        if ($attempt->preview && $timenow > $this->cquiz->timeclose) {
            return false;
        }
        // Otherwise, return to the time left until the close date, providing that is
        // less than CQUIZ_SHOW_TIME_BEFORE_DEADLINE.
        $endtime = $this->end_time($attempt);
        if ($endtime !== false && $timenow > $endtime - CQUIZ_SHOW_TIME_BEFORE_DEADLINE) {
            return $endtime - $timenow;
        }
        return false;
    }

}

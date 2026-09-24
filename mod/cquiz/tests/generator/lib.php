<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Cquiz module test data generator class
 *
 * @package mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class mod_cquiz_generator extends testing_module_generator {

    public function create_instance($record = null, array $options = null) {
        global $CFG;

        require_once($CFG->dirroot . '/mod/cquiz/locallib.php');
        $record = (object) (array) $record;

        $defaultcquizsettings = array(
            'timeopen' => 0,
            'timeclose' => 0,
            'preferredbehaviour' => 'deferredfeedback',
            'attempts' => 0,
            'attemptonlast' => 0,
            'grademethod' => CQUIZ_GRADEHIGHEST,
            'decimalpoints' => 2,
            'questiondecimalpoints' => -1,
            'attemptduring' => 1,
            'correctnessduring' => 1,
            'marksduring' => 1,
            'specificfeedbackduring' => 1,
            'generalfeedbackduring' => 1,
            'rightanswerduring' => 1,
            'overallfeedbackduring' => 0,
            'attemptimmediately' => 1,
            'correctnessimmediately' => 1,
            'marksimmediately' => 1,
            'specificfeedbackimmediately' => 1,
            'generalfeedbackimmediately' => 1,
            'rightanswerimmediately' => 1,
            'overallfeedbackimmediately' => 1,
            'attemptopen' => 1,
            'correctnessopen' => 1,
            'marksopen' => 1,
            'specificfeedbackopen' => 1,
            'generalfeedbackopen' => 1,
            'rightansweropen' => 1,
            'overallfeedbackopen' => 1,
            'attemptclosed' => 1,
            'correctnessclosed' => 1,
            'marksclosed' => 1,
            'specificfeedbackclosed' => 1,
            'generalfeedbackclosed' => 1,
            'rightanswerclosed' => 1,
            'overallfeedbackclosed' => 1,
            'questionsperpage' => 1,
            'shuffleanswers' => 1,
            'sumgrades' => 0,
            'grade' => 100,
            'timecreated' => time(),
            'timemodified' => time(),
            'timelimit' => 0,
            'overduehandling' => 'autoabandon',
            'graceperiod' => 86400,
            'cquizpassword' => '',
            'subnet' => '',
            'browsersecurity' => '',
            'delay1' => 0,
            'delay2' => 0,
            'showuserpicture' => 0,
            'showblocks' => 0,
            'navmethod' => CQUIZ_NAVMETHOD_FREE,
        );

        foreach ($defaultcquizsettings as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        return parent::create_instance($record, (array) $options);
    }

}

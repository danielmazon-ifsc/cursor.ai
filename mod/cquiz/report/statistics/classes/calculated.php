<?php

namespace cquiz_statistics;

defined('MOODLE_INTERNAL') || die();

/**
 * The statistics calculator returns an instance of this class which contains the calculated statistics.
 *
 * These cquiz statistics calculations are described here :
 *
 * http://docs.moodle.org/dev/Cquiz_statistics_calculations#Test_statistics
 *
 * @package    cquiz_statistics
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     James Pratt me@jamiep.org
 * @author     Ricardo Drummond
 */
class calculated {

    /**
     * @param  string $whichattempts which attempts to use, represented internally as one of the constants as used in
     *                                   $cquiz->grademethod ie.
     *                                   CQUIZ_GRADEAVERAGE, CQUIZ_GRADEHIGHEST, CQUIZ_ATTEMPTLAST or CQUIZ_ATTEMPTFIRST
     *                                   we calculate stats based on which attempts would affect the grade for each student,
     *                                   the default null value is used when constructing an instance whose values will be
     *                                   populated from a db record.
     */
    public function __construct($whichattempts = null) {
        if ($whichattempts !== null) {
            $this->whichattempts = $whichattempts;
        }
    }

    /**
     * @var int which attempts we are calculating calculate stats from.
     */
    public $whichattempts;

    /* Following stats all described here : http://docs.moodle.org/dev/Cquiz_statistics_calculations#Test_statistics  */
    public $firstattemptscount = 0;
    public $allattemptscount = 0;
    public $lastattemptscount = 0;
    public $highestattemptscount = 0;
    public $firstattemptsavg;
    public $allattemptsavg;
    public $lastattemptsavg;
    public $highestattemptsavg;
    public $median;
    public $standarddeviation;
    public $skewness;
    public $kurtosis;
    public $cic;
    public $errorratio;
    public $standarderror;

    /**
     * @var int time these stats where calculated and cached.
     */
    public $timemodified;

    /**
     * Count of attempts selected by $this->whichattempts
     *
     * @return int
     */
    public function s() {
        return $this->get_field('count');
    }

    /**
     * Average grade for the attempts selected by $this->whichattempts
     *
     * @return float
     */
    public function avg() {
        return $this->get_field('avg');
    }

    /**
     * Get the right field name to fetch a stat for these attempts that is calculated for more than one $whichattempts (count or
     * avg).
     *
     * @param string $field name of field
     * @return int|float
     */
    protected function get_field($field) {
        $fieldname = calculator::using_attempts_string_id($this->whichattempts) . $field;
        return $this->{$fieldname};
    }

    /**
     * @param $course
     * @param $cm
     * @param $cquiz
     * @return array to display in table or spreadsheet.
     */
    public function get_formatted_cquiz_info_data($course, $cm, $cquiz) {

        // You can edit this array to control which statistics are displayed.
        $todisplay = array('firstattemptscount' => 'number',
            'allattemptscount' => 'number',
            'firstattemptsavg' => 'summarks_as_percentage',
            'allattemptsavg' => 'summarks_as_percentage',
            'lastattemptsavg' => 'summarks_as_percentage',
            'highestattemptsavg' => 'summarks_as_percentage',
            'median' => 'summarks_as_percentage',
            'standarddeviation' => 'summarks_as_percentage',
            'skewness' => 'number_format',
            'kurtosis' => 'number_format',
            'cic' => 'number_format_percent',
            'errorratio' => 'number_format_percent',
            'standarderror' => 'summarks_as_percentage');

        // General information about the cquiz.
        $cquizinfo = array();
        $cquizinfo[get_string('cquizname', 'cquiz_statistics')] = format_string($cquiz->name);
        $cquizinfo[get_string('coursename', 'cquiz_statistics')] = format_string($course->fullname);
        if ($cm->idnumber) {
            $cquizinfo[get_string('idnumbermod')] = $cm->idnumber;
        }
        if ($cquiz->timeopen) {
            $cquizinfo[get_string('cquizopen', 'cquiz')] = userdate($cquiz->timeopen);
        }
        if ($cquiz->timeclose) {
            $cquizinfo[get_string('cquizclose', 'cquiz')] = userdate($cquiz->timeclose);
        }
        if ($cquiz->timeopen && $cquiz->timeclose) {
            $cquizinfo[get_string('duration', 'cquiz_statistics')] = format_time($cquiz->timeclose - $cquiz->timeopen);
        }

        // The statistics.
        foreach ($todisplay as $property => $format) {
            if (!isset($this->$property) || !$format) {
                continue;
            }
            $value = $this->$property;

            switch ($format) {
                case 'summarks_as_percentage':
                    $formattedvalue = cquiz_report_scale_summarks_as_percentage($value, $cquiz);
                    break;
                case 'number_format_percent':
                    $formattedvalue = cquiz_format_grade($cquiz, $value) . '%';
                    break;
                case 'number_format':
                    // 2 extra decimal places, since not a percentage,
                    // and we want the same number of sig figs.
                    $formattedvalue = format_float($value, $cquiz->decimalpoints + 2);
                    break;
                case 'number':
                    $formattedvalue = $value + 0;
                    break;
                default:
                    $formattedvalue = $value;
            }

            $cquizinfo[get_string($property, 'cquiz_statistics', calculator::using_attempts_lang_string($this->whichattempts))] = $formattedvalue;
        }

        return $cquizinfo;
    }

    /**
     * @var array of names of properties of this class that are cached in db record.
     */
    protected $fieldsindb = array('whichattempts', 'firstattemptscount', 'allattemptscount', 'firstattemptsavg', 'allattemptsavg',
        'lastattemptscount', 'highestattemptscount', 'lastattemptsavg', 'highestattemptsavg',
        'median', 'standarddeviation', 'skewness',
        'kurtosis', 'cic', 'errorratio', 'standarderror');

    /**
     * Cache the stats contained in this class.
     *
     * @param $qubaids \qubaid_condition
     */
    public function cache($qubaids) {
        global $DB;

        $toinsert = new \stdClass();

        foreach ($this->fieldsindb as $field) {
            $toinsert->{$field} = $this->{$field};
        }

        $toinsert->hashcode = $qubaids->get_hash_code();
        $toinsert->timemodified = time();

        // Fix up some dodgy data.
        if (isset($toinsert->errorratio) && is_nan($toinsert->errorratio)) {
            $toinsert->errorratio = null;
        }
        if (isset($toinsert->standarderror) && is_nan($toinsert->standarderror)) {
            $toinsert->standarderror = null;
        }

        // Store the data.
        $DB->insert_record('cquiz_statistics', $toinsert);
    }

    /**
     * Given a record from 'cquiz_statistics' table load the data into the properties of this class.
     *
     * @param $record \stdClass from db.
     */
    public function populate_from_record($record) {
        foreach ($this->fieldsindb as $field) {
            $this->$field = $record->$field;
        }
        $this->timemodified = $record->timemodified;
    }

}

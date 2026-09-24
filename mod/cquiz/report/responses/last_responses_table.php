<?php

/**
 * This file defines the cquiz responses table for showing last try at question.
 *
 * @package   cquiz_responses
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/cquiz/report/attemptsreport_table.php');

/**
 * This is a table subclass for displaying the cquiz responses report.
 *
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquiz_last_responses_table extends cquiz_attempts_report_table {

    /**
     * Constructor
     * @param object $cquiz
     * @param context $context
     * @param string $qmsubselect
     * @param cquiz_responses_options $options
     * @param array $groupstudents
     * @param array $students
     * @param array $questions
     * @param moodle_url $reporturl
     */
    public function __construct($cquiz, $context, $qmsubselect, cquiz_responses_options $options, $groupstudents, $students, $questions, $reporturl) {
        parent::__construct('mod-cquiz-report-responses-report', $cquiz, $context, $qmsubselect, $options, $groupstudents, $students, $questions, $reporturl);
    }

    public function build_table() {
        if (!$this->rawdata) {
            return;
        }

        $this->strtimeformat = str_replace(',', ' ', get_string('strftimedatetime'));
        parent::build_table();
    }

    public function col_sumgrades($attempt) {
        if ($attempt->state != cquiz_attempt::FINISHED) {
            return '-';
        }

        $grade = cquiz_rescale_grade($attempt->sumgrades, $this->cquiz);
        if ($this->is_downloading()) {
            return $grade;
        }

        $gradehtml = '<a href="review.php?q=' . $this->cquiz->id . '&amp;attempt=' .
                $attempt->attempt . '">' . $grade . '</a>';
        return $gradehtml;
    }

    public function data_col($slot, $field, $attempt) {
        if ($attempt->usageid == 0) {
            return '-';
        }

        $value = $this->field_from_extra_data($attempt, $slot, $field);

        if (is_null($value)) {
            $summary = '-';
        } else {
            $summary = trim($value);
        }

        if ($this->is_downloading() && $this->is_downloading() != 'xhtml') {
            return $summary;
        }
        $summary = s($summary);

        if ($this->is_downloading() || $field != 'responsesummary') {
            return $summary;
        }

        return $this->make_review_link($summary, $attempt, $slot);
    }

    /**
     * Column text from the extra data loaded in load_extra_data(), before html formatting etc.
     *
     * @param object $attempt
     * @param int $slot
     * @param string $field
     * @return string
     */
    protected function field_from_extra_data($attempt, $slot, $field) {
        if (!isset($this->lateststeps[$attempt->usageid][$slot])) {
            return '-';
        }
        $stepdata = $this->lateststeps[$attempt->usageid][$slot];

        if (property_exists($stepdata, $field . 'full')) {
            $value = $stepdata->{$field . 'full'};
        } else {
            $value = $stepdata->$field;
        }
        return $value;
    }

    public function other_cols($colname, $attempt) {
        if (preg_match('/^question(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'questionsummary', $attempt);
        } else if (preg_match('/^response(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'responsesummary', $attempt);
        } else if (preg_match('/^right(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'rightanswer', $attempt);
        } else {
            return null;
        }
    }

    protected function requires_extra_data() {
        return true;
    }

    protected function is_latest_step_column($column) {
        if (preg_match('/^(?:question|response|right)([0-9]+)/', $column, $matches)) {
            return $matches[1];
        }
        return false;
    }

    /**
     * Get any fields that might be needed when sorting on date for a particular slot.
     * @param int    $slot  the slot for the column we want.
     * @param string $alias the table alias for latest state information relating to that slot.
     * @return string sql fragment to alias fields.
     */
    protected function get_required_latest_state_fields($slot, $alias) {
        global $DB;
        $sortableresponse = $DB->sql_order_by_text("{$alias}.questionsummary");
        if ($sortableresponse === "{$alias}.questionsummary") {
            // Can just order by text columns. No complexity needed.
            return "{$alias}.questionsummary AS question{$slot},
                    {$alias}.rightanswer AS right{$slot},
                    {$alias}.responsesummary AS response{$slot}";
        } else {
            // Work-around required.
            return $DB->sql_order_by_text("{$alias}.questionsummary") . " AS question{$slot},
                    {$alias}.questionsummary AS question{$slot}full,
                    " . $DB->sql_order_by_text("{$alias}.rightanswer") . " AS right{$slot},
                    {$alias}.rightanswer AS right{$slot}full,
                    " . $DB->sql_order_by_text("{$alias}.responsesummary") . " AS response{$slot},
                    {$alias}.responsesummary AS response{$slot}full";
        }
    }

}

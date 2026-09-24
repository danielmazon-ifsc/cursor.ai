<?php

/**
 * The mod_cquiz report viewed event.
 *
 * @package    mod_cquiz
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */

namespace mod_cquiz\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_cquiz report viewed event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int cquizid: the id of the cquiz.
 *      - string reportname: the name of the report.
 * }
 *
 * @package    mod_cquiz
 * @since      Moodle 2.7
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class report_viewed extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventreportviewed', 'mod_cquiz');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' viewed the report '" . s($this->other['reportname']) . "' for the cquiz with " .
                "course module id '$this->contextinstanceid'.";
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/cquiz/report.php', array('id' => $this->contextinstanceid,
            'mode' => $this->other['reportname']));
    }

    /**
     * Return the legacy event log data.
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, 'cquiz', 'report', 'report.php?id=' . $this->contextinstanceid . '&mode=' .
            $this->other['reportname'], $this->other['cquizid'], $this->contextinstanceid);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['cquizid'])) {
            throw new \coding_exception('The \'cquizid\' value must be set in other.');
        }

        if (!isset($this->other['reportname'])) {
            throw new \coding_exception('The \'reportname\' value must be set in other.');
        }
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['cquizid'] = array('db' => 'cquiz', 'restore' => 'cquiz');

        return $othermapped;
    }

}

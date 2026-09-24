<?php

/**
 * The mod_cquiz group override deleted event.
 *
 * @package    mod_cquiz
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */

namespace mod_cquiz\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_cquiz group override deleted event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int cquizid: the id of the cquiz.
 *      - int groupid: the id of the group.
 * }
 *
 * @package    mod_cquiz
 * @since      Moodle 2.7
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class group_override_deleted extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'cquiz_overrides';
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventoverridedeleted', 'mod_cquiz');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' deleted the override with id '$this->objectid' for the cquiz with " .
                "course module id '$this->contextinstanceid' for the group with id '{$this->other['groupid']}'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/cquiz/overrides.php', array('cmid' => $this->contextinstanceid));
    }

    /**
     * Return the legacy event log data.
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, 'cquiz', 'delete override', 'overrides.php?cmid=' . $this->contextinstanceid,
            $this->other['cquizid'], $this->contextinstanceid);
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

        if (!isset($this->other['groupid'])) {
            throw new \coding_exception('The \'groupid\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'cquiz_overrides', 'restore' => 'cquiz_override');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['cquizid'] = array('db' => 'cquiz', 'restore' => 'cquiz');
        $othermapped['groupid'] = array('db' => 'groups', 'restore' => 'group');

        return $othermapped;
    }

}

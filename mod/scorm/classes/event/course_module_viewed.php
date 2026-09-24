<?php

/**
 * The mod_scorm course module viewed event.
 *
 * @package    mod_scorm
 */

namespace mod_scorm\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_scorm course module viewed event class.
 *
 * @package    mod_scorm
 */
class course_module_viewed extends \core\event\course_module_viewed {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'scorm';
    }

    /**
     * Replace add_to_log() statement.
     *
     * @return array of parameters to be passed to legacy add_to_log() function.
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, 'scorm', 'pre-view', 'view.php?id=' . $this->contextinstanceid, $this->objectid,
            $this->contextinstanceid);
    }

    public static function get_objectid_mapping() {
        return array('db' => 'scorm', 'restore' => 'scorm');
    }

}

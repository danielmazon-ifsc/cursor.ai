<?php

/**
 * The mod_video course module viewed event.
 *
 * @package   mod_video
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace mod_video\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_video course module viewed event class.
 *
 * @package   mod_video
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class course_module_viewed extends \core\event\course_module_viewed {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'video';
    }

    public static function get_objectid_mapping() {
        return array('db' => 'video', 'restore' => 'video');
    }

}

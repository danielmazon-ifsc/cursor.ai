<?php

namespace local_studypace\event;

defined('MOODLE_INTERNAL') || die();

class planned_completion_high_grade extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_studypace';
        $this->data['contextlevel'] = CONTEXT_COURSE;
        $this->data['anonymous'] = 0;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' completed course '$this->courseid' in time and with high grade";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('completionintimehighgrade', 'local_studypace');
    }

}

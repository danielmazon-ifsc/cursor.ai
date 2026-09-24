<?php

/**
 * Admin settings class for the cquiz grading method.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Admin settings class for the cquiz grading method.
 *
 * Just so we can lazy-load the choices.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class mod_cquiz_admin_setting_grademethod extends admin_setting_configselect_with_advanced {

    public function load_choices() {
        global $CFG;

        if (is_array($this->choices)) {
            return true;
        }

        require_once($CFG->dirroot . '/mod/cquiz/locallib.php');
        $this->choices = cquiz_get_grading_options();

        return true;
    }

}

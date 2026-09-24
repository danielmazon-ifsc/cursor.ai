<?php

/**
 * Local coin plugin admin setting classes
 *
 * @package    local_coin
 * @subpackage coin
 * @copyright  2016 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

class admin_setting_configjsonarray extends admin_setting_configtextarea {

    /**
     * Constructor: uses parent::__construct
     *
     * @param string $name unique ascii name, either 'mysetting' for settings that in config, or 'myplugin/mysetting' for ones in config_plugins.
     * @param string $visiblename localised
     * @param string $description long localised info
     * @param string $defaultsetting default value for the setting
     * @param mixed $paramtype
     * @param string $cols The number of columns to make the editor
     * @param string $rows The number of rows to make the editor
     */
    public function __construct($name, $visiblename, $description, $defaultsetting, $paramtype = PARAM_RAW, $cols = '60', $rows = '8') {
        parent::__construct($name, $visiblename, $description, $defaultsetting, $paramtype, $cols, $rows);
    }

    /**
     * Validate data before storage
     * @param string data
     * @return mixed true if ok string if error found
     */
    public function validate($data) {
        json_decode($data, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            return json_last_error_msg();
        }
        return true;
    }

}

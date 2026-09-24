<?php

/**
 * Subplugin info class.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */

namespace mod_cquiz\plugininfo;

use core\plugininfo\base;

defined('MOODLE_INTERNAL') || die();

class cquizaccess extends base {

    public function is_uninstall_allowed() {
        // Only allow uninstall of non-core access rules.
        return !$this->is_standard();
    }

}

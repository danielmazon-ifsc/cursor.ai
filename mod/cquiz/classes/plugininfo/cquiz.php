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

class cquiz extends base {

    public function is_uninstall_allowed() {
        return true;
    }

    /**
     * Pre-uninstall hook.
     *
     * This is intended for disabling of plugin, some DB table purging, etc.
     *
     * NOTE: to be called from uninstall_plugin() only.
     * @private
     */
    public function uninstall_cleanup() {
        global $DB;

        // Do the opposite of db/install.php scripts - deregister the report.

        $DB->delete_records('cquiz_reports', array('name' => $this->name));

        parent::uninstall_cleanup();
    }

}

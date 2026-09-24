<?php

/**
 * Restore code for the cquizaccess_honestycheck plugin.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Base class for restoring up all the cquiz settings and attempt data for an
 * access rule cquiz sub-plugin.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class restore_mod_cquiz_access_subplugin extends restore_subplugin {

    /**
     * Use this method to describe the XML paths that store your sub-plugin's
     * settings for a particular cquiz.
     */
    protected function define_cquiz_subplugin_structure() {
        // Do nothing by default.
    }

    /**
     * Use this method to describe the XML paths that store your sub-plugin's
     * settings for a particular cquiz attempt.
     */
    protected function define_attempt_subplugin_structure() {
        // Do nothing by default.
    }

}

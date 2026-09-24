<?php

/**
 * Defines the base class for cquiz access plugins backup code.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Base class for backing up all the cquiz settings and attempt data for an
 * access rule cquiz sub-plugin.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class backup_mod_cquiz_access_subplugin extends backup_subplugin {

    /**
     * Use this method to describe the XML structure required to store your
     * sub-plugin's settings for a particular cquiz, and how that data is stored
     * in the database.
     */
    protected function define_cquiz_subplugin_structure() {
        // Do nothing by default.
    }

    /**
     * Use this method to describe the XML structure required to store your
     * sub-plugin's settings for a particular cquiz attempt, and how that data
     * is stored in the database.
     */
    protected function define_attempt_subplugin_structure() {
        // Do nothing by default.
    }

}

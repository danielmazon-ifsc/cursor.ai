<?php

/**
 * Video module post install function
 *
 * This file replaces:
 *  - STATEMENTS section in db/install.xml
 *  - lib.php/modulename_install() post installation hook
 *  - partially defaults.php
 *
 * @package   mod_video
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die;

function xmldb_video_install() {
    global $CFG;
}

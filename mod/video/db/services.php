<?php

/**
 * Video external functions and service definitions.
 *
 * @package   mod_video
 * @category  external
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die;

$functions = array(
    'mod_video_view_video' => array(
        'classname' => 'mod_video_external',
        'methodname' => 'view_video',
        'description' => 'Simulate the view.php web interface video: trigger events, completion, etc...',
        'type' => 'write',
        'capabilities' => 'mod/video:view',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);

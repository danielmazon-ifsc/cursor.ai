<?php

/**
 * Definition of video scheduled tasks.
 *
 * @package   mod_video
 * @category  task
 * @copyright 2023 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

$tasks = array(
    array(
        'classname' => 'mod_video\cron_task',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '0',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    )
);

<?php

/**
 * Definition of Forum scheduled tasks.
 *
 * @package   mod_scorm
 * @category  task
 */
defined('MOODLE_INTERNAL') || die();

$tasks = array(
    array(
        'classname' => 'mod_scorm\task\cron_task',
        'blocking' => 0,
        'minute' => '*/5',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*'
    )
);

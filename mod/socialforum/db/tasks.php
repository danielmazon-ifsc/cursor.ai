<?php

/**
 * Definition of Social Forum scheduled tasks.
 *
 * @package   mod_socialforum
 * @category  task
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

$tasks = array(
    array(
        'classname' => 'mod_socialforum\task\cron_task',
        'blocking' => 0,
        'minute' => '*',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*'
    )
);

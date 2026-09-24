<?php

/**
 * Definition of Coin local plugin scheduled tasks.
 *
 * @package   local_coin
 * @category  task
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

// It will run on a daily basis
$tasks = array(
    array(
        'classname' => 'local_coin\cron_task',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '0',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*'
    )
);

<?php

/**
 * Definition of log events
 *
 * @package   mod_video
 * @category  log
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

$logs = array(
    array('module' => 'video', 'action' => 'view', 'mtable' => 'video', 'field' => 'name'),
    array('module' => 'video', 'action' => 'view all', 'mtable' => 'video', 'field' => 'name'),
    array('module' => 'video', 'action' => 'update', 'mtable' => 'video', 'field' => 'name'),
    array('module' => 'video', 'action' => 'add', 'mtable' => 'video', 'field' => 'name'),
);

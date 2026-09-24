<?php

/**
 * Definition of log events for the cquiz module.
 *
 * @package    mod_cquiz
 * @category   log
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

$logs = array(
    array('module' => 'cquiz', 'action' => 'add', 'mtable' => 'cquiz', 'field' => 'name'),
    array('module' => 'cquiz', 'action' => 'update', 'mtable' => 'cquiz', 'field' => 'name'),
    array('module' => 'cquiz', 'action' => 'view', 'mtable' => 'cquiz', 'field' => 'name'),
    array('module' => 'cquiz', 'action' => 'report', 'mtable' => 'cquiz', 'field' => 'name'),
    array('module' => 'cquiz', 'action' => 'attempt', 'mtable' => 'cquiz', 'field' => 'name'),
    array('module' => 'cquiz', 'action' => 'submit', 'mtable' => 'cquiz', 'field' => 'name'),
    array('module' => 'cquiz', 'action' => 'review', 'mtable' => 'cquiz', 'field' => 'name'),
    array('module' => 'cquiz', 'action' => 'editquestions', 'mtable' => 'cquiz', 'field' => 'name'),
    array('module' => 'cquiz', 'action' => 'preview', 'mtable' => 'cquiz', 'field' => 'name'),
    array('module' => 'cquiz', 'action' => 'start attempt', 'mtable' => 'cquiz', 'field' => 'name'),
    array('module' => 'cquiz', 'action' => 'close attempt', 'mtable' => 'cquiz', 'field' => 'name'),
    array('module' => 'cquiz', 'action' => 'continue attempt', 'mtable' => 'cquiz', 'field' => 'name'),
    array('module' => 'cquiz', 'action' => 'edit override', 'mtable' => 'cquiz', 'field' => 'name'),
    array('module' => 'cquiz', 'action' => 'delete override', 'mtable' => 'cquiz', 'field' => 'name'),
    array('module' => 'cquiz', 'action' => 'view summary', 'mtable' => 'cquiz', 'field' => 'name'),
);

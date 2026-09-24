<?php

/**
 * Definition of log events
 *
 * @package   mod_socialforum
 * @category  log
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $DB; // TODO: this is a hack, we should really do something with the SQL in SQL tables.

$logs = array(
    array('module' => 'socialforum', 'action' => 'add', 'mtable' => 'socialforum', 'field' => 'name'),
    array('module' => 'socialforum', 'action' => 'update', 'mtable' => 'socialforum', 'field' => 'name'),
    array('module' => 'socialforum', 'action' => 'add discussion', 'mtable' => 'socialforum_discussions', 'field' => 'name'),
    array('module' => 'socialforum', 'action' => 'add post', 'mtable' => 'socialforum_posts', 'field' => 'subject'),
    array('module' => 'socialforum', 'action' => 'update post', 'mtable' => 'socialforum_posts', 'field' => 'subject'),
    array('module' => 'socialforum', 'action' => 'user report', 'mtable' => 'user',
        'field' => $DB->sql_concat('firstname', "' '", 'lastname')),
    array('module' => 'socialforum', 'action' => 'move discussion', 'mtable' => 'socialforum_discussions', 'field' => 'name'),
    array('module' => 'socialforum', 'action' => 'view subscribers', 'mtable' => 'socialforum', 'field' => 'name'),
    array('module' => 'socialforum', 'action' => 'view discussion', 'mtable' => 'socialforum_discussions', 'field' => 'name'),
    array('module' => 'socialforum', 'action' => 'view socialforum', 'mtable' => 'socialforum', 'field' => 'name'),
    array('module' => 'socialforum', 'action' => 'subscribe', 'mtable' => 'socialforum', 'field' => 'name'),
    array('module' => 'socialforum', 'action' => 'unsubscribe', 'mtable' => 'socialforum', 'field' => 'name'),
    array('module' => 'socialforum', 'action' => 'pin discussion', 'mtable' => 'socialforum_discussions', 'field' => 'name'),
    array('module' => 'socialforum', 'action' => 'unpin discussion', 'mtable' => 'socialforum_discussions', 'field' => 'name'),
);

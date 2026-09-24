<?php

/**
 * Message Inbound Handlers for mod_socialforum.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

$handlers = array(
    array(
        'classname' => '\mod_socialforum\message\inbound\reply_handler',
    ),
);

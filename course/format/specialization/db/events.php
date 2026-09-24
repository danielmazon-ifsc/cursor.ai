<?php

/**
 * Format specialization event handler definition.
 *
 * @package   format_specialization
 * @category  event
 * @copyright 2019 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

defined('MOODLE_INTERNAL') || die();

$observers = array(
    array(
        'eventname' => '\core\event\course_created',
        'callback'  => '\format_specialization\observer::course_created',
    ),
    array(
        'eventname' => '\core\event\course_updated',
        'callback'  => '\format_specialization\observer::course_updated',
    ),
    array(
        'eventname' => '\core\event\course_deleted',
        'callback'  => '\format_specialization\observer::course_deleted',
    ),
);

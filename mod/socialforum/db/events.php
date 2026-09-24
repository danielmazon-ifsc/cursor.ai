<?php

/**
 * Social Forum event handler definition.
 *
 * @package   mod_socialforum
 * @category  event
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
// List of observers.
$observers = array(
    array(
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback' => 'mod_socialforum_observer::user_enrolment_deleted',
    ),
    array(
        'eventname' => '\core\event\role_assigned',
        'callback' => 'mod_socialforum_observer::role_assigned'
    ),
    array(
        'eventname' => '\core\event\course_module_created',
        'callback' => 'mod_socialforum_observer::course_module_created',
    ),
    array(
        'eventname' => '\core\event\course_completed',
        'callback' => 'mod_socialforum_observer::course_completed',
    ),
);

<?php

/**
 * Add event handlers for the cquiz
 *
 * @package    mod_cquiz
 * @category   event
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

$observers = array(
    // Handle group events, so that open cquiz attempts with group overrides get updated check times.
    array(
        'eventname' => '\core\event\course_reset_started',
        'callback' => '\mod_cquiz\group_observers::course_reset_started',
    ),
    array(
        'eventname' => '\core\event\course_reset_ended',
        'callback' => '\mod_cquiz\group_observers::course_reset_ended',
    ),
    array(
        'eventname' => '\core\event\group_deleted',
        'callback' => '\mod_cquiz\group_observers::group_deleted'
    ),
    array(
        'eventname' => '\core\event\group_member_added',
        'callback' => '\mod_cquiz\group_observers::group_member_added',
    ),
    array(
        'eventname' => '\core\event\group_member_removed',
        'callback' => '\mod_cquiz\group_observers::group_member_removed',
    ),
    // Handle our own \mod_cquiz\event\attempt_submitted event, as a way to
    // send confirmation messages asynchronously.
    array(
        'eventname' => '\mod_cquiz\event\attempt_submitted',
        'includefile' => '/mod/cquiz/locallib.php',
        'callback' => 'cquiz_attempt_submitted_handler',
        'internal' => false
    ),
);

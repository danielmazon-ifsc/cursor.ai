<?php

/**
 * Local profile event observer definitions.
 *
 * Hooks the auto-progression workflow onto the local_studypace
 * "course completed" signal. local_studypace fires this exactly once per
 * (user, course) the moment it records the completion, so the observer is
 * guaranteed not to double-enrol on duplicate CM-completion events.
 *
 * @package   local_profile
 * @category  event
 */

defined('MOODLE_INTERNAL') || die();

$observers = array(
    array(
        'eventname' => '\local_studypace\event\course_completed',
        'callback'  => '\local_profile\observer::course_completed',
    ),
);

<?php

defined('MOODLE_INTERNAL') || die();

$observers = array(
    array(
        'eventname' => '\core\event\user_enrolment_created',
        'callback' => '\local_studypace\observer::process_user_enrolment',
    ),
    array(
        'eventname' => '\core\event\user_enrolment_updated',
        'callback' => '\local_studypace\observer::process_user_enrolment',
    ),
    array(
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback' => '\local_studypace\observer::process_user_enrolment',
    ),
    array(
        'eventname' => '\core\event\course_module_completion_updated',
        'callback' => '\local_studypace\observer::process_course_module_completion',
    ),
    // Clean up rows that would otherwise outlive their owners. Without these,
    // local_studypace silently keeps paces for deleted courses, deleted CMs
    // and deleted users, polluting ranking + dashboard queries forever.
    array(
        'eventname' => '\core\event\course_deleted',
        'callback' => '\local_studypace\observer::course_deleted',
    ),
    array(
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\local_studypace\observer::course_module_deleted',
    ),
    array(
        'eventname' => '\core\event\user_deleted',
        'callback' => '\local_studypace\observer::user_deleted',
    ),
    array(
        // Re-evaluate tracking when an admin switches a course's format
        // to/from one of the tracked formats. Without this, paces stay
        // attached to courses whose format no longer wants tracking.
        'eventname' => '\core\event\course_updated',
        'callback' => '\local_studypace\observer::course_updated',
    ),
    array(
        'eventname' => '\core\event\course_module_created',
        'callback' => '\local_studypace\observer::course_module_created',
        'priority' => 100,
    ),
);

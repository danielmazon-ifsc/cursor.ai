<?php

$observers = array(
    array(
        'eventname' => '\local_studypace\event\planned_completion',
        'callback' => '\local_coin\observer::process_event',
    ),
    array(
        'eventname' => '\local_studypace\event\planned_completion_high_grade',
        'callback' => '\local_coin\observer::process_event',
    ),
    array(
        'eventname' => '\core\event\user_deleted',
        'callback' => '\local_coin\observer::user_deleted',
    ),
    array(
        'eventname' => '\core\event\course_deleted',
        'callback' => '\local_coin\observer::course_deleted',
    ),
);

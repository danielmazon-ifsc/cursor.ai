<?php

$observers = array(
    array(
        'eventname' => '\local_studypace\event\planned_completion',
        'callback' => '\local_autobadge\observer::completion_changed',
    ),
    array(
        'eventname' => '\local_studypace\event\planned_completion_high_grade',
        'callback' => '\local_autobadge\observer::completion_changed',
    ),
);

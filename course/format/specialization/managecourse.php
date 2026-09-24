<?php

/**
 * Perform course operations in specialization format
 *
 * @package   format_specialization
 * @copyright 2018 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require_once('../../../config.php');
require_once('./lib.php');

$specializationid = required_param('id', PARAM_INT);
$courseid         = required_param('component', PARAM_INT);
$operation        = required_param('op', PARAM_ALPHA);
$position         = optional_param('pos', 0, PARAM_INT);

// Authentication, CSRF and capability checks. Without these any authenticated
// user could mutate the course composition of any specialization via a crafted
// link or img-tag.
require_login($specializationid);
require_sesskey();
require_capability('moodle/course:update', context_course::instance($specializationid));

if ($operation == 'add') {
    if (!format_specialization::add_course($specializationid, $courseid, $position)) {
        debugging("Course $courseid couldn't be added to specialization $specializationid");
        exit();
    }
} else if ($operation == 'del') {
    if (!format_specialization::remove_course($specializationid, $courseid)) {
        debugging("Course $courseid couldn't be removed from specialization $specializationid");
        exit();
    }
} else {
    debugging('Invalid operation: ' . $operation);
    exit();
}

redirect(new moodle_url('/course/view.php', array('id' => $specializationid)));

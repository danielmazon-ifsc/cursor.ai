<?php

/**
 * Defines message providers (types of message sent) for the cquiz module.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

$messageproviders = array(
    // Notify teacher that a student has submitted a cquiz attempt.
    'submission' => array(
        'capability' => 'mod/cquiz:emailnotifysubmission'
    ),
    // Confirm a student's cquiz attempt.
    'confirmation' => array(
        'capability' => 'mod/cquiz:emailconfirmsubmission'
    ),
    // Warning to the student that their cquiz attempt is now overdue, if the cquiz
    // has a grace period.
    'attempt_overdue' => array(
        'capability' => 'mod/cquiz:emailwarnoverdue'
    ),
    // Inform student that a grade has been eaarned
    'grade_earned' => array(),
);

<?php

/**
 * Capability definitions for the cquiz manual grading report.
 *
 * @package   cquiz_grading
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

$capabilities = array(
    // Is the user allowed to see the student's real names while grading?
    'cquiz/grading:viewstudentnames' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'mod/cquiz:viewreports'
    ),
    // Is the user allowed to see the student's idnumber while grading?
    'cquiz/grading:viewidnumber' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'mod/cquiz:viewreports'
    )
);

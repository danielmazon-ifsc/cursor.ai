<?php

/**
 * Cquiz external functions and service definitions.
 *
 * @package    mod_cquiz
 * @category   external
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 * @since      Moodle 3.1
 */
defined('MOODLE_INTERNAL') || die;

$functions = array(
    'mod_cquiz_get_cquizzes_by_courses' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'get_cquizzes_by_courses',
        'description' => 'Returns a list of cquizzes in a provided list of courses,
                            if no list is provided all cquizzes that the user can view will be returned.',
        'type' => 'read',
        'capabilities' => 'mod/cquiz:view',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_view_cquiz' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'view_cquiz',
        'description' => 'Trigger the course module viewed event and update the module completion status.',
        'type' => 'write',
        'capabilities' => 'mod/cquiz:view',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_get_user_attempts' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'get_user_attempts',
        'description' => 'Return a list of attempts for the given cquiz and user.',
        'type' => 'read',
        'capabilities' => 'mod/cquiz:view',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_get_user_best_grade' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'get_user_best_grade',
        'description' => 'Get the best current grade for the given user on a cquiz.',
        'type' => 'read',
        'capabilities' => 'mod/cquiz:view',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_get_combined_review_options' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'get_combined_review_options',
        'description' => 'Combines the review options from a number of different cquiz attempts.',
        'type' => 'read',
        'capabilities' => 'mod/cquiz:view',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_start_attempt' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'start_attempt',
        'description' => 'Starts a new attempt at a cquiz.',
        'type' => 'write',
        'capabilities' => 'mod/cquiz:attempt',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_get_attempt_data' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'get_attempt_data',
        'description' => 'Returns information for the given attempt page for a cquiz attempt in progress.',
        'type' => 'read',
        'capabilities' => 'mod/cquiz:attempt',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_get_attempt_summary' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'get_attempt_summary',
        'description' => 'Returns a summary of a cquiz attempt before it is submitted.',
        'type' => 'read',
        'capabilities' => 'mod/cquiz:attempt',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_save_attempt' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'save_attempt',
        'description' => 'Processes save requests during the cquiz.
                            This function is intended for the cquiz auto-save feature.',
        'type' => 'write',
        'capabilities' => 'mod/cquiz:attempt',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_process_attempt' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'process_attempt',
        'description' => 'Process responses during an attempt at a cquiz and also deals with attempts finishing.',
        'type' => 'write',
        'capabilities' => 'mod/cquiz:attempt',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_get_attempt_review' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'get_attempt_review',
        'description' => 'Returns review information for the given finished attempt, can be used by users or teachers.',
        'type' => 'read',
        'capabilities' => 'mod/cquiz:reviewmyattempts',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_view_attempt' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'view_attempt',
        'description' => 'Trigger the attempt viewed event.',
        'type' => 'write',
        'capabilities' => 'mod/cquiz:attempt',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_view_attempt_summary' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'view_attempt_summary',
        'description' => 'Trigger the attempt summary viewed event.',
        'type' => 'write',
        'capabilities' => 'mod/cquiz:attempt',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_view_attempt_review' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'view_attempt_review',
        'description' => 'Trigger the attempt reviewed event.',
        'type' => 'write',
        'capabilities' => 'mod/cquiz:reviewmyattempts',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_get_cquiz_feedback_for_grade' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'get_cquiz_feedback_for_grade',
        'description' => 'Get the feedback text that should be show to a student who got the given grade in the given cquiz.',
        'type' => 'read',
        'capabilities' => 'mod/cquiz:view',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_get_cquiz_access_information' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'get_cquiz_access_information',
        'description' => 'Return access information for a given cquiz.',
        'type' => 'read',
        'capabilities' => 'mod/cquiz:view',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_get_attempt_access_information' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'get_attempt_access_information',
        'description' => 'Return access information for a given attempt in a cquiz.',
        'type' => 'read',
        'capabilities' => 'mod/cquiz:view',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_cquiz_get_cquiz_required_qtypes' => array(
        'classname' => 'mod_cquiz_external',
        'methodname' => 'get_cquiz_required_qtypes',
        'description' => 'Return the potential question types that would be required for a given cquiz.',
        'type' => 'read',
        'capabilities' => 'mod/cquiz:view',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);

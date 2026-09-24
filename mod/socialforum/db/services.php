<?php

/**
 * Social Forum external functions and service definitions.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
$functions = array(
    'mod_socialforum_get_socialforums_by_courses' => array(
        'classname' => 'mod_socialforum_external',
        'methodname' => 'get_socialforums_by_courses',
        'classpath' => 'mod/socialforum/externallib.php',
        'description' => 'Returns a list of socialforum instances in a provided set of courses, if
            no courses are provided then all the socialforum instances the user has access to will be
            returned.',
        'type' => 'read',
        'capabilities' => 'mod/socialforum:viewdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_socialforum_get_socialforum_discussion_posts' => array(
        'classname' => 'mod_socialforum_external',
        'methodname' => 'get_socialforum_discussion_posts',
        'classpath' => 'mod/socialforum/externallib.php',
        'description' => 'Returns a list of socialforum posts for a discussion.',
        'type' => 'read',
        'capabilities' => 'mod/socialforum:viewdiscussion, mod/socialforum:viewqandawithoutposting',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_socialforum_get_socialforum_discussions_paginated' => array(
        'classname' => 'mod_socialforum_external',
        'methodname' => 'get_socialforum_discussions_paginated',
        'classpath' => 'mod/socialforum/externallib.php',
        'description' => 'Returns a list of socialforum discussions optionally sorted and paginated.',
        'type' => 'read',
        'capabilities' => 'mod/socialforum:viewdiscussion, mod/socialforum:viewqandawithoutposting',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_socialforum_view_socialforum' => array(
        'classname' => 'mod_socialforum_external',
        'methodname' => 'view_socialforum',
        'classpath' => 'mod/socialforum/externallib.php',
        'description' => 'Trigger the course module viewed event and update the module completion status.',
        'type' => 'write',
        'capabilities' => 'mod/socialforum:viewdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_socialforum_view_socialforum_discussion' => array(
        'classname' => 'mod_socialforum_external',
        'methodname' => 'view_socialforum_discussion',
        'classpath' => 'mod/socialforum/externallib.php',
        'description' => 'Trigger the socialforum discussion viewed event.',
        'type' => 'write',
        'capabilities' => 'mod/socialforum:viewdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_socialforum_add_discussion_post' => array(
        'classname' => 'mod_socialforum_external',
        'methodname' => 'add_discussion_post',
        'classpath' => 'mod/socialforum/externallib.php',
        'description' => 'Create new posts into an existing discussion.',
        'type' => 'write',
        'capabilities' => 'mod/socialforum:replypost',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_socialforum_add_discussion' => array(
        'classname' => 'mod_socialforum_external',
        'methodname' => 'add_discussion',
        'classpath' => 'mod/socialforum/externallib.php',
        'description' => 'Add a new discussion into an existing socialforum.',
        'type' => 'write',
        'capabilities' => 'mod/socialforum:startdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_socialforum_can_add_discussion' => array(
        'classname' => 'mod_socialforum_external',
        'methodname' => 'can_add_discussion',
        'classpath' => 'mod/socialforum/externallib.php',
        'description' => 'Check if the current user can add discussions in the given socialforum (and optionally for the given group).',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);

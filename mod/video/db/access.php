<?php

/**
 * Video module capability definition
 *
 * @package   mod_video
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die;

$capabilities = array(
    'mod/video:view' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'guest' => CAP_ALLOW,
            'user' => CAP_ALLOW,
        )
    ),
    'mod/video:addinstance' => array(
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'moodle/course:manageactivities'
    ),
        /* TODO: review public portfolio API first!
          'mod/video:portfolioexport' => array(

          'captype' => 'read',
          'contextlevel' => CONTEXT_MODULE,
          'archetypes' => array(
          'teacher' => CAP_ALLOW,
          'editingteacher' => CAP_ALLOW,
          )
          ),
         */
);

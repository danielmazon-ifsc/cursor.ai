<?php
// This file is part of Moodle - http://moodle.org/
//
// @package   local_coin
// @copyright 2026 Viddia (http://viddia.com.br)
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/coin:viewledger' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_USER,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/user:viewdetails',
    ],
];

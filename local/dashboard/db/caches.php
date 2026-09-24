<?php
// This file is part of Moodle - http://moodle.org/
//
// @package   local_dashboard
// @copyright 2026 Viddia (http://viddia.com.br)
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // Site-wide coin-total distribution (a single 'distribution' key holding an
    // array of every active student's total, highest first). Shared by every
    // viewer, so a short-ish TTL keeps ranking fresh without each dashboard
    // load re-aggregating {coin_stack}. See local_dashboard_get_coin_distribution().
    'coinranking' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 2,
        'ttl' => 300,
    ],
    'studentids' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 1,
        'ttl' => 300,
    ],
];

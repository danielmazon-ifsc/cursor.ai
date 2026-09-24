<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Event observers for format_saladeconferencias.
 *
 * @package    format_saladeconferencias
 * @copyright  2025 Viddia (http://viddia.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => '\format_saladeconferencias\observer::course_module_created',
        'priority' => 200,
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\format_saladeconferencias\observer::course_module_updated',
        'priority' => 200,
    ],
];

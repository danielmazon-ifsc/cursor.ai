<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Event observers for format_saladeconferencias.
 *
 * @package    format_saladeconferencias
 * @copyright  2025 Viddia (http://viddia.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_saladeconferencias;

defined('MOODLE_INTERNAL') || die();

/**
 * Keeps Sala de Conferências videos on the course completion criteria list.
 */
class observer {

    /**
     * @param \core\event\course_module_created $event
     */
    public static function course_module_created(\core\event\course_module_created $event): void {
        global $CFG;
        if (($event->other['modulename'] ?? '') !== 'video') {
            return;
        }
        require_once($CFG->dirroot . '/course/format/saladeconferencias/lib.php');
        format_saladeconferencias_ensure_video_completion_criteria((int) $event->contextinstanceid);
    }

    /**
     * @param \core\event\course_module_updated $event
     */
    public static function course_module_updated(\core\event\course_module_updated $event): void {
        global $CFG;
        if (($event->other['modulename'] ?? '') !== 'video') {
            return;
        }
        require_once($CFG->dirroot . '/course/format/saladeconferencias/lib.php');
        format_saladeconferencias_ensure_video_completion_criteria((int) $event->contextinstanceid);
    }
}

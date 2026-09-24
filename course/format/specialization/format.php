<?php

/**
 * Specialization course format.  Display the whole course as topics made of modules,
 *
 * @package   format_specialization
 * @copyright 2018 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

// JS/CSS for this format are registered in format_specialization::page_set_course().

$renderer = $PAGE->get_renderer('format_specialization');
$renderer->render_specialization($COURSE);

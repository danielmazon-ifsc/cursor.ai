<?php

/**
 * Core Report class of graphs reporting plugin
 *
 * @package    scormreport_graphs
 */
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/filelib.php');

debugging('This way of generating the chart is deprecated, refer to \\scormreport_graphs\\report::display().', DEBUG_DEVELOPER);
send_file_not_found();

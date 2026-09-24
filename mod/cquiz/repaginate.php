<?php

/**
 * Rest endpoint for ajax editing for paging operations on the cquiz structure.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/cquiz/locallib.php');

$cquizid = required_param('cquizid', PARAM_INT);
$slotnumber = required_param('slot', PARAM_INT);
$repagtype = required_param('repag', PARAM_INT);

require_sesskey();
$cquizobj = cquiz::create($cquizid);
require_login($cquizobj->get_course(), false, $cquizobj->get_cm());
require_capability('mod/cquiz:manage', $cquizobj->get_context());
if (cquiz_has_attempts($cquizid)) {
    $reportlink = cquiz_attempt_summary_link_to_reports($cquizobj->get_cquiz(), $cquizobj->get_cm(), $cquizobj->get_context());
    throw new \moodle_exception('cannoteditafterattempts', 'cquiz', new moodle_url('/mod/cquiz/edit.php', array('cmid' => $cquizobj->get_cmid())), $reportlink);
}

$slotnumber++;
$repage = new \mod_cquiz\repaginate($cquizid);
$repage->repaginate_slots($slotnumber, $repagtype);

$structure = $cquizobj->get_structure();
$slots = $structure->refresh_page_numbers_and_update_db();

redirect(new moodle_url('edit.php', array('cmid' => $cquizobj->get_cmid())));

<?php

/**
 * Ajax script to update the contents of the question bank dialogue.
 *
 * @package    mod_cquiz
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/cquiz/locallib.php');
require_once($CFG->dirroot . '/question/editlib.php');

list($thispageurl, $contexts, $cmid, $cm, $cquiz, $pagevars) = question_edit_setup('editq', '/mod/cquiz/edit.php', true);

// Get the course object and related bits.
$course = $DB->get_record('course', array('id' => $cquiz->course), '*', MUST_EXIST);
require_capability('mod/cquiz:manage', $contexts->lowest());

// Create cquiz question bank view.
$questionbank = new mod_cquiz\question\bank\custom_view($contexts, $thispageurl, $course, $cm, $cquiz);
$questionbank->set_cquiz_has_attempts(cquiz_has_attempts($cquiz->id));

// Output.
$output = $PAGE->get_renderer('mod_cquiz', 'edit');
$contents = $output->question_bank_contents($questionbank, $pagevars);
echo json_encode(array(
    'status' => 'OK',
    'contents' => $contents,
));

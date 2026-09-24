<?php

/**
 * Page to edit cquizzes
 *
 * This page generally has two columns:
 * The right column lists all available questions in a chosen category and
 * allows them to be edited or more to be added. This column is only there if
 * the cquiz does not already have student attempts
 * The left column lists all questions that have been added to the current cquiz.
 * The lecturer can add questions from the right hand list to the cquiz or remove them
 *
 * The script also processes a number of actions:
 * Actions affecting a cquiz:
 * up and down  Changes the order of questions and page breaks
 * addquestion  Adds a single question to the cquiz
 * add          Adds several selected questions to the cquiz
 * addrandom    Adds a certain number of random questions to the cquiz
 * repaginate   Re-paginates the cquiz
 * delete       Removes a question from the cquiz
 * savechanges  Saves the order and grades for questions in the cquiz
 *
 * @package    mod_cquiz
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/cquiz/locallib.php');
require_once($CFG->dirroot . '/mod/cquiz/addrandomform.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/category_class.php');

// These params are only passed from page request to request while we stay on
// this page otherwise they would go in question_edit_setup.
$scrollpos = optional_param('scrollpos', '', PARAM_INT);

list($thispageurl, $contexts, $cmid, $cm, $cquiz, $pagevars) = question_edit_setup('editq', '/mod/cquiz/edit.php', true);

$defaultcategoryobj = question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;

$cquizhasattempts = cquiz_has_attempts($cquiz->id);

$PAGE->set_url($thispageurl);

// Get the course object and related bits.
$course = $DB->get_record('course', array('id' => $cquiz->course), '*', MUST_EXIST);
$cquizobj = new cquiz($cquiz, $cm, $course);
$structure = $cquizobj->get_structure();

// You need mod/cquiz:manage in addition to question capabilities to access this page.
require_capability('mod/cquiz:manage', $contexts->lowest());

// Log this visit.
$params = array(
    'courseid' => $course->id,
    'context' => $contexts->lowest(),
    'other' => array(
        'cquizid' => $cquiz->id
    )
);
$event = \mod_cquiz\event\edit_page_viewed::create($params);
$event->trigger();

// Process commands ============================================================.
// Get the list of question ids had their check-boxes ticked.
$selectedslots = array();
$params = (array) data_submitted();
foreach ($params as $key => $value) {
    if (preg_match('!^s([0-9]+)$!', $key, $matches)) {
        $selectedslots[] = $matches[1];
    }
}

$afteractionurl = new moodle_url($thispageurl);
if ($scrollpos) {
    $afteractionurl->param('scrollpos', $scrollpos);
}

if (optional_param('repaginate', false, PARAM_BOOL) && confirm_sesskey()) {
    // Re-paginate the cquiz.
    $structure->check_can_be_edited();
    $questionsperpage = optional_param('questionsperpage', $cquiz->questionsperpage, PARAM_INT);
    cquiz_repaginate_questions($cquiz->id, $questionsperpage);
    cquiz_delete_previews($cquiz);
    redirect($afteractionurl);
}

if (($addquestion = optional_param('addquestion', 0, PARAM_INT)) && confirm_sesskey()) {
    // Add a single question to the current cquiz.
    $structure->check_can_be_edited();
    cquiz_require_question_use($addquestion);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    cquiz_add_cquiz_question($addquestion, $cquiz, $addonpage);
    cquiz_delete_previews($cquiz);
    cquiz_update_sumgrades($cquiz);
    $thispageurl->param('lastchanged', $addquestion);
    redirect($afteractionurl);
}

if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    $structure->check_can_be_edited();
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    // Add selected questions to the current cquiz.
    $rawdata = (array) data_submitted();
    foreach ($rawdata as $key => $value) { // Parse input for question ids.
        if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
            $key = $matches[1];
            cquiz_require_question_use($key);
            cquiz_add_cquiz_question($key, $cquiz, $addonpage);
        }
    }
    cquiz_delete_previews($cquiz);
    cquiz_update_sumgrades($cquiz);
    redirect($afteractionurl);
}

if ($addsectionatpage = optional_param('addsectionatpage', false, PARAM_INT)) {
    // Add a section to the cquiz.
    $structure->check_can_be_edited();
    $structure->add_section_heading($addsectionatpage);
    cquiz_delete_previews($cquiz);
    redirect($afteractionurl);
}

if ((optional_param('addrandom', false, PARAM_BOOL)) && confirm_sesskey()) {
    // Add random questions to the cquiz.
    $structure->check_can_be_edited();
    $recurse = optional_param('recurse', 0, PARAM_BOOL);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    $categoryid = required_param('categoryid', PARAM_INT);
    $randomcount = required_param('randomcount', PARAM_INT);
    cquiz_add_random_questions($cquiz, $addonpage, $categoryid, $randomcount, $recurse);

    cquiz_delete_previews($cquiz);
    cquiz_update_sumgrades($cquiz);
    redirect($afteractionurl);
}

if (optional_param('savechanges', false, PARAM_BOOL) && confirm_sesskey()) {

    // If rescaling is required save the new maximum.
    $maxgrade = unformat_float(optional_param('maxgrade', -1, PARAM_RAW));
    if ($maxgrade >= 0) {
        cquiz_set_grade($maxgrade, $cquiz);
        cquiz_update_all_final_grades($cquiz);
        cquiz_update_grades($cquiz, 0, true);
    }

    redirect($afteractionurl);
}


// Get the question bank view.
$questionbank = new mod_cquiz\question\bank\custom_view($contexts, $thispageurl, $course, $cm, $cquiz);
$questionbank->set_cquiz_has_attempts($cquizhasattempts);
$questionbank->process_actions($thispageurl, $cm);

// End of process commands =====================================================.

$PAGE->set_pagelayout('incourse');
$PAGE->set_pagetype('mod-cquiz-edit');

$output = $PAGE->get_renderer('mod_cquiz', 'edit');

$PAGE->set_title(get_string('editingcquizx', 'cquiz', format_string($cquiz->name)));
$PAGE->set_heading($course->fullname);
$node = $PAGE->settingsnav->find('mod_cquiz_edit', navigation_node::TYPE_SETTING);
if ($node) {
    $node->make_active();
}
echo $OUTPUT->header();

// Initialise the JavaScript.
$cquizeditconfig = new stdClass();
$cquizeditconfig->url = $thispageurl->out(true, array('qbanktool' => '0'));
$cquizeditconfig->dialoglisteners = array();
$numberoflisteners = $DB->get_field_sql("
    SELECT COALESCE(MAX(page), 1)
      FROM {cquiz_slots}
     WHERE cquizid = ?", array($cquiz->id));

for ($pageiter = 1; $pageiter <= $numberoflisteners; $pageiter++) {
    $cquizeditconfig->dialoglisteners[] = 'addrandomdialoglaunch_' . $pageiter;
}

$PAGE->requires->data_for_js('cquiz_edit_config', $cquizeditconfig);
$PAGE->requires->js('/question/qengine.js');

// Questions wrapper start.
echo html_writer::start_tag('div', array('class' => 'mod-cquiz-edit-content'));

echo $output->edit_page($cquizobj, $structure, $contexts, $thispageurl, $pagevars);

// Questions wrapper end.
echo html_writer::end_tag('div');


echo $OUTPUT->footer();

<?php

/**
 * Fallback page of /mod/cquiz/edit.php add random question dialog,
 * for users who do not use javascript.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/cquiz/locallib.php');
require_once($CFG->dirroot . '/mod/cquiz/addrandomform.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/category_class.php');

list($thispageurl, $contexts, $cmid, $cm, $cquiz, $pagevars) = question_edit_setup('editq', '/mod/cquiz/addrandom.php', true);

// These params are only passed from page request to request while we stay on
// this page otherwise they would go in question_edit_setup.
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$addonpage = optional_param('addonpage', 0, PARAM_INT);
$category = optional_param('category', 0, PARAM_INT);
$scrollpos = optional_param('scrollpos', 0, PARAM_INT);

// Get the course object and related bits.
if (!$course = $DB->get_record('course', array('id' => $cquiz->course))) {
    print_error('invalidcourseid');
}
// You need mod/cquiz:manage in addition to question capabilities to access this page.
// You also need the moodle/question:useall capability somewhere.
require_capability('mod/cquiz:manage', $contexts->lowest());
if (!$contexts->having_cap('moodle/question:useall')) {
    print_error('nopermissions', '', '', 'use');
}

$PAGE->set_url($thispageurl);

if ($returnurl) {
    $returnurl = new moodle_url($returnurl);
} else {
    $returnurl = new moodle_url('/mod/cquiz/edit.php', array('cmid' => $cmid));
}
if ($scrollpos) {
    $returnurl->param('scrollpos', $scrollpos);
}

$defaultcategoryobj = question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;

$qcobject = new question_category_object(
        $pagevars['cpage'], $thispageurl, $contexts->having_one_edit_tab_cap('categories'), $defaultcategoryobj->id, $defaultcategory, null, $contexts->having_cap('moodle/question:add'));

$mform = new cquiz_add_random_form(new moodle_url('/mod/cquiz/addrandom.php'), array('contexts' => $contexts, 'cat' => $pagevars['cat']));

if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $mform->get_data()) {
    if (!empty($data->existingcategory)) {
        list($categoryid) = explode(',', $data->category);
        $includesubcategories = !empty($data->includesubcategories);
        $returnurl->param('cat', $data->category);
    } else if (!empty($data->newcategory)) {
        list($parentid, $contextid) = explode(',', $data->parent);
        $categoryid = $qcobject->add_category($data->parent, $data->name, '', true);
        $includesubcategories = 0;

        $returnurl->param('cat', $categoryid . ',' . $contextid);
    } else {
        throw new coding_exception(
        'It seems a form was submitted without any button being pressed???');
    }

    cquiz_add_random_questions($cquiz, $addonpage, $categoryid, $data->numbertoadd, $includesubcategories);
    cquiz_delete_previews($cquiz);
    cquiz_update_sumgrades($cquiz);
    redirect($returnurl);
}

$mform->set_data(array(
    'addonpage' => $addonpage,
    'returnurl' => $returnurl,
    'cmid' => $cm->id,
    'category' => $category,
));

// Setup $PAGE.
$streditingcquiz = get_string('editinga', 'moodle', get_string('modulename', 'cquiz'));
$PAGE->navbar->add($streditingcquiz);
$PAGE->set_title($streditingcquiz);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

if (!$cquizname = $DB->get_field($cm->modname, 'name', array('id' => $cm->instance))) {
    print_error('invalidcoursemodule');
}

echo $OUTPUT->heading(get_string('addrandomquestiontocquiz', 'cquiz', $cquizname), 2);
$mform->display();
echo $OUTPUT->footer();


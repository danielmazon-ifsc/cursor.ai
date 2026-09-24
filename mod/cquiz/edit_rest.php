<?php

/**
 * Rest endpoint for ajax editing of cquiz structure.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/cquiz/locallib.php');

// Initialise ALL the incoming parameters here, up front.
$cquizid = required_param('cquizid', PARAM_INT);
$class = required_param('class', PARAM_ALPHA);
$field = optional_param('field', '', PARAM_ALPHA);
$instanceid = optional_param('instanceId', 0, PARAM_INT);
$sectionid = optional_param('sectionId', 0, PARAM_INT);
$previousid = optional_param('previousid', 0, PARAM_INT);
$value = optional_param('value', 0, PARAM_INT);
$column = optional_param('column', 0, PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$summary = optional_param('summary', '', PARAM_RAW);
$sequence = optional_param('sequence', '', PARAM_SEQUENCE);
$visible = optional_param('visible', 0, PARAM_INT);
$pageaction = optional_param('action', '', PARAM_ALPHA); // Used to simulate a DELETE command.
$maxmark = optional_param('maxmark', '', PARAM_FLOAT);
$newheading = optional_param('newheading', '', PARAM_TEXT);
$shuffle = optional_param('newshuffle', 0, PARAM_INT);
$page = optional_param('page', '', PARAM_INT);
$PAGE->set_url('/mod/cquiz/edit-rest.php', array('cquizid' => $cquizid, 'class' => $class));

require_sesskey();
$cquiz = $DB->get_record('cquiz', array('id' => $cquizid), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('cquiz', $cquiz->id, $cquiz->course);
$course = $DB->get_record('course', array('id' => $cquiz->course), '*', MUST_EXIST);
require_login($course, false, $cm);

$cquizobj = new cquiz($cquiz, $cm, $course);
$structure = $cquizobj->get_structure();
$modcontext = context_module::instance($cm->id);

echo $OUTPUT->header(); // Send headers.
// OK, now let's process the parameters and do stuff
// MDL-10221 the DELETE method is not allowed on some web servers,
// so we simulate it with the action URL param.
$requestmethod = $_SERVER['REQUEST_METHOD'];
if ($pageaction == 'DELETE') {
    $requestmethod = 'DELETE';
}

switch ($requestmethod) {
    case 'POST':
    case 'GET': // For debugging.
        switch ($class) {
            case 'section':
                $table = 'cquiz_sections';
                switch ($field) {
                    case 'getsectiontitle':
                        require_capability('mod/cquiz:manage', $modcontext);
                        $section = $structure->get_section_by_id($id);
                        echo json_encode(array('instancesection' => $section->heading));
                        break;
                    case 'updatesectiontitle':
                        require_capability('mod/cquiz:manage', $modcontext);
                        $structure->set_section_heading($id, $newheading);
                        echo json_encode(array('instancesection' => format_string($newheading)));
                        break;
                    case 'updateshufflequestions':
                        require_capability('mod/cquiz:manage', $modcontext);
                        $structure->set_section_shuffle($id, $shuffle);
                        echo json_encode(array('instanceshuffle' => $section->shufflequestions));
                        break;
                }
                break;

            case 'resource':
                switch ($field) {
                    case 'move':
                        require_capability('mod/cquiz:manage', $modcontext);
                        if (!$previousid) {
                            $section = $structure->get_section_by_id($sectionid);
                            if ($section->firstslot > 1) {
                                $previousid = $structure->get_slot_id_for_slot($section->firstslot - 1);
                                $page = $structure->get_page_number_for_slot($section->firstslot);
                            }
                        }
                        $structure->move_slot($id, $previousid, $page);
                        cquiz_delete_previews($cquiz);
                        echo json_encode(array('visible' => true));
                        break;

                    case 'getmaxmark':
                        require_capability('mod/cquiz:manage', $modcontext);
                        $slot = $DB->get_record('cquiz_slots', array('id' => $id), '*', MUST_EXIST);
                        echo json_encode(array('instancemaxmark' =>
                            cquiz_format_question_grade($cquiz, $slot->maxmark)));
                        break;

                    case 'updatemaxmark':
                        require_capability('mod/cquiz:manage', $modcontext);
                        $slot = $structure->get_slot_by_id($id);
                        if ($structure->update_slot_maxmark($slot, $maxmark)) {
                            // Grade has really changed.
                            cquiz_delete_previews($cquiz);
                            cquiz_update_sumgrades($cquiz);
                            cquiz_update_all_attempt_sumgrades($cquiz);
                            cquiz_update_all_final_grades($cquiz);
                            cquiz_update_grades($cquiz, 0, true);
                        }
                        echo json_encode(array('instancemaxmark' => cquiz_format_question_grade($cquiz, $maxmark),
                            'newsummarks' => cquiz_format_grade($cquiz, $cquiz->sumgrades)));
                        break;

                    case 'updatepagebreak':
                        require_capability('mod/cquiz:manage', $modcontext);
                        $slots = $structure->update_page_break($id, $value);
                        $json = array();
                        foreach ($slots as $slot) {
                            $json[$slot->slot] = array('id' => $slot->id, 'slot' => $slot->slot,
                                'page' => $slot->page);
                        }
                        echo json_encode(array('slots' => $json));
                        break;

                    case 'updatedependency':
                        require_capability('mod/cquiz:manage', $modcontext);
                        $slot = $structure->get_slot_by_id($id);
                        $value = (bool) $value;
                        $structure->update_question_dependency($slot->id, $value);
                        echo json_encode(array('requireprevious' => $value));
                        break;
                }
                break;
        }
        break;

    case 'DELETE':
        switch ($class) {
            case 'section':
                require_capability('mod/cquiz:manage', $modcontext);
                $structure->remove_section_heading($id);
                echo json_encode(array('deleted' => true));
                break;

            case 'resource':
                require_capability('mod/cquiz:manage', $modcontext);
                if (!$slot = $DB->get_record('cquiz_slots', array('cquizid' => $cquiz->id, 'id' => $id))) {
                    throw new moodle_exception('AJAX commands.php: Bad slot ID ' . $id);
                }
                $structure->remove_slot($slot->slot);
                cquiz_delete_previews($cquiz);
                cquiz_update_sumgrades($cquiz);
                echo json_encode(array('newsummarks' => cquiz_format_grade($cquiz, $cquiz->sumgrades),
                    'deleted' => true, 'newnumquestions' => $structure->get_question_count()));
                break;
        }
        break;
}

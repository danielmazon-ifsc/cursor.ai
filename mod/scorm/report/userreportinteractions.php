<?php

/**
 * This page displays the user data from a single attempt
 *
 * @package mod_scorm
 */
require_once("../../../config.php");
require_once($CFG->dirroot . '/mod/scorm/locallib.php');
require_once($CFG->dirroot . '/mod/scorm/report/reportlib.php');
require_once($CFG->libdir . '/tablelib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.
$userid = required_param('user', PARAM_INT); // User ID.
$attempt = optional_param('attempt', 1, PARAM_INT); // attempt number.
$download = optional_param('download', '', PARAM_ALPHA);

// Building the url to use for links.+ data details buildup.
$url = new moodle_url('/mod/scorm/report/userreportinteractions.php', array('id' => $id,
    'user' => $userid,
    'attempt' => $attempt));

$cm = get_coursemodule_from_id('scorm', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$scorm = $DB->get_record('scorm', array('id' => $cm->instance), '*', MUST_EXIST);
$user = $DB->get_record('user', array('id' => $userid), user_picture::fields(), MUST_EXIST);
// Get list of attempts this user has made.
$attemptids = scorm_get_all_attempts($scorm->id, $userid);

$PAGE->set_url($url);
// END of url setting + data buildup.
// Checking login +logging +getting context.
require_login($course, false, $cm);
$contextmodule = context_module::instance($cm->id);
require_capability('mod/scorm:viewreport', $contextmodule);

// Check user has group access.
if (!groups_user_groups_visible($course, $userid, $cm)) {
    throw new moodle_exception('nopermissiontoshow');
}

// Trigger a user interactions viewed event.
$event = \mod_scorm\event\interactions_viewed::create(array(
            'context' => $contextmodule,
            'relateduserid' => $userid,
            'other' => array('attemptid' => $attempt, 'instanceid' => $scorm->id)
        ));
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('scorm', $scorm);
$event->trigger();

$trackdata = $DB->get_records('scorm_scoes_track', array('userid' => $user->id, 'scormid' => $scorm->id,
    'attempt' => $attempt));
$usertrack = scorm_format_interactions($trackdata);

$questioncount = get_scorm_question_count($scorm->id);

$courseshortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
$exportfilename = $courseshortname . '-' . format_string($scorm->name, true) . '-' . get_string('interactions', 'scorm');


// Set up the table.
$table = new flexible_table('mod-scorm-userreport-interactions');
if (!$table->is_downloading($download, $exportfilename)) {

    // Print the page header.
    $strattempt = get_string('attempt', 'scorm');
    $strreport = get_string('report', 'scorm');

    $PAGE->set_title("$course->shortname: " . format_string($scorm->name));
    $PAGE->set_heading($course->fullname);
    $PAGE->navbar->add($strreport, new moodle_url('/mod/scorm/report.php', array('id' => $cm->id)));

    $PAGE->navbar->add(fullname($user) . " - $strattempt $attempt");

    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($scorm->name));
    // End of Print the page header.
    $currenttab = 'interactions';
    require($CFG->dirroot . '/mod/scorm/report/userreporttabs.php');

    // Printing user details.
    $output = $PAGE->get_renderer('mod_scorm');
    echo $output->view_user_heading($user, $course, $PAGE->url, $attempt, $attemptids);
}
$table->define_baseurl($PAGE->url);
$table->define_columns(array('id', 'studentanswer', 'correctanswer', 'result', 'calcweight'));
$table->define_headers(array(get_string('trackid', 'scorm'), get_string('response', 'scorm'),
    get_string('rightanswer', 'scorm'), get_string('result', 'scorm'),
    get_string('calculatedweight', 'scorm')));
$table->set_attribute('class', 'generaltable generalbox boxaligncenter boxwidthwide');

$table->show_download_buttons_at(array(TABLE_P_BOTTOM));
$table->setup();

for ($i = 0; $i < $questioncount; $i++) {
    $row = array();
    $element = 'cmi.interactions_' . $i . '.id';
    if (isset($usertrack->$element)) {
        $row[] = s($usertrack->$element);

        $element = 'cmi.interactions_' . $i . '.student_response';
        if (isset($usertrack->$element)) {
            $row[] = s($usertrack->$element);
        } else {
            $row[] = '&nbsp;';
        }

        $j = 0;
        $element = 'cmi.interactions_' . $i . '.correct_responses_' . $j . '.pattern';
        $rightans = '';
        if (isset($usertrack->$element)) {
            while (isset($usertrack->$element)) {
                if ($j > 0) {
                    $rightans .= ',';
                }
                $rightans .= s($usertrack->$element);
                $j++;
                $element = 'cmi.interactions_' . $i . '.correct_responses_' . $j . '.pattern';
            }
            $row[] = $rightans;
        } else {
            $row[] = '&nbsp;';
        }
        $element = 'cmi.interactions_' . $i . '.result';
        $weighting = 'cmi.interactions_' . $i . '.weighting';
        if (isset($usertrack->$element)) {
            $row[] = s($usertrack->$element);
            if ($usertrack->$element == 'correct' &&
                    isset($usertrack->$weighting)) {
                $row[] = s($usertrack->$weighting);
            } else {
                $row[] = '0';
            }
        } else {
            $row[] = '&nbsp;';
        }
        $table->add_data($row);
    }
}

$table->finish_output();

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}


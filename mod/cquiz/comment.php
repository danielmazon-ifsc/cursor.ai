<?php
/**
 * This page allows the teacher to enter a manual grade for a particular question.
 * This page is expected to only be used in a popup window.
 *
 * @package   mod_cquiz
 * @copyright 2017 viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
require_once('../../config.php');
require_once('locallib.php');

$attemptid = required_param('attempt', PARAM_INT);
$slot = required_param('slot', PARAM_INT); // The question number in the attempt.

$PAGE->set_url('/mod/cquiz/comment.php', array('attempt' => $attemptid, 'slot' => $slot));

$attemptobj = cquiz_attempt::create($attemptid);
$student = $DB->get_record('user', array('id' => $attemptobj->get_userid()));

// Can only grade finished attempts.
if (!$attemptobj->is_finished()) {
    print_error('attemptclosed', 'cquiz');
}

// Check login and permissions.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());
$attemptobj->require_capability('mod/cquiz:grade');

// Print the page header.
$PAGE->set_pagelayout('popup');
$PAGE->set_title(get_string('manualgradequestion', 'cquiz', array(
    'question' => format_string($attemptobj->get_question_name($slot)),
    'cquiz' => format_string($attemptobj->get_cquiz_name()), 'user' => fullname($student))));
$PAGE->set_heading($attemptobj->get_course()->fullname);
$output = $PAGE->get_renderer('mod_cquiz');
echo $output->header();

// Prepare summary information about this question attempt.
$summarydata = array();

// Student name.
$userpicture = new user_picture($student);
$userpicture->courseid = $attemptobj->get_courseid();
$summarydata['user'] = array(
    'title' => $userpicture,
    'content' => new action_link(new moodle_url('/user/view.php', array(
        'id' => $student->id, 'course' => $attemptobj->get_courseid())), fullname($student, true)),
);

// Cquiz name.
$summarydata['cquizname'] = array(
    'title' => get_string('modulename', 'cquiz'),
    'content' => format_string($attemptobj->get_cquiz_name()),
);

// Question name.
$summarydata['questionname'] = array(
    'title' => get_string('question', 'cquiz'),
    'content' => $attemptobj->get_question_name($slot),
);

// Process any data that was submitted.
if (data_submitted() && confirm_sesskey()) {
    if (optional_param('submit', false, PARAM_BOOL) && question_engine::is_manual_grade_in_range($attemptobj->get_uniqueid(), $slot)) {
        $transaction = $DB->start_delegated_transaction();
        $attemptobj->process_submitted_actions(time());
        $transaction->allow_commit();

        // Log this action.
        $params = array(
            'objectid' => $attemptobj->get_question_attempt($slot)->get_question()->id,
            'courseid' => $attemptobj->get_courseid(),
            'context' => context_module::instance($attemptobj->get_cmid()),
            'other' => array(
                'cquizid' => $attemptobj->get_cquizid(),
                'attemptid' => $attemptobj->get_attemptid(),
                'slot' => $slot
            )
        );
        $event = \mod_cquiz\event\question_manually_graded::create($params);
        $event->trigger();

        echo $output->notification(get_string('changessaved'), 'notifysuccess');
        close_window(2, true);
        die;
    }
}

// Print cquiz information.
echo $output->review_summary_table($summarydata, 0);

// Print the comment form.
echo '<form method="post" class="mform" id="manualgradingform" action="' .
 $CFG->wwwroot . '/mod/cquiz/comment.php">';
echo $attemptobj->render_question_for_commenting($slot);
?>
<div>
    <input type="hidden" name="attempt" value="<?php echo $attemptobj->get_attemptid(); ?>" />
    <input type="hidden" name="slot" value="<?php echo $slot; ?>" />
    <input type="hidden" name="slots" value="<?php echo $slot; ?>" />
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>" />
</div>
<fieldset class="hidden">
    <div>
        <div class="fitem fitem_actionbuttons fitem_fsubmit">
            <fieldset class="felement fsubmit">
                <input id="id_submitbutton" type="submit" name="submit" value="<?php print_string('save', 'cquiz'); ?>"/>
            </fieldset>
        </div>
    </div>
</fieldset>
<?php
echo '</form>';
$PAGE->requires->js_init_call('M.mod_cquiz.init_comment_popup', null, false, cquiz_get_js_module());

// End of the page.
echo $output->footer();

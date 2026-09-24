<?php

/**
 * Video module version information
 *
 * @package   mod_video
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require('../../config.php');
require_once($CFG->dirroot . '/mod/video/lib.php');
require_once($CFG->dirroot . '/mod/video/locallib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/sessionlib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID
$p = optional_param('p', 0, PARAM_INT);  // Video instance ID
$inpopup = optional_param('inpopup', 0, PARAM_BOOL);
$post = optional_param('post', '', PARAM_TEXT);

if ($p) {
    if (!$video = $DB->get_record('video', array('id' => $p))) {
        print_error('invalidaccessparameter');
    }
    $cm = get_coursemodule_from_instance('video', $video->id, $video->course, false, MUST_EXIST);
} else {
    if (!$cm = get_coursemodule_from_id('video', $id)) {
        print_error('invalidcoursemodule');
    }
    $video = $DB->get_record('video', array('id' => $cm->instance), '*', MUST_EXIST);
}

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/video:view', $context);

// Completion and trigger events.
video_view($video, $course, $cm, $context);

$modinfo = get_fast_modinfo($course);
$cminfo = $modinfo->get_cm($cm->id);

// YouTube / embed URL: mark automatic view completion when the student opens the page.
video_mark_view_completion_on_open($video, $course, $cminfo);

if (!video_complete_on_view($video) && !empty(get_config('video', 'completeimmediately'))
        && !video_is_complete($course, $cminfo, $USER->id)) {
    video_set_completed($course, $cminfo);
}

$PAGE->set_url('/mod/video/view.php', array('id' => $cm->id));

$PAGE->activityheader->set_attrs([
    'hidecompletion' => false,
    'description' => '',
]);

$options = empty($video->displayoptions) ? array() : unserialize($video->displayoptions);

if ($inpopup and $video->display == RESOURCELIB_DISPLAY_POPUP) {
    $PAGE->set_videolayout('popup');
    $PAGE->set_title($course->fullname . ': ' . $video->name);
    $PAGE->set_heading($course->fullname);
} else {
    $PAGE->set_title($course->fullname . ': ' . $video->name);
    $PAGE->set_heading($course->fullname);
    $PAGE->set_activity_record($video);
}

// Load Javascripts for video control.
$completionjsconfig = [
    'wwwroot' => $CFG->wwwroot,
    'cmid' => $cm->id,
    'sesskey' => sesskey(),
    'completed' => video_is_complete($course, $cminfo, $USER->id),
    'completeimmediately' => !empty(get_config('video', 'completeimmediately')),
    'secondstocomplete' => (int) get_config('video', 'secondstocomplete'),
    'completeonview' => video_complete_on_view($video),
];
if ((int) $video->contentformat === FORMAT_VIMEO && !video_complete_on_view($video)) {
    $PAGE->requires->js('/mod/video/player.js', true);
    $PAGE->requires->js('/mod/video/vimeocontrol.js', true);
}
if (video_get_caption_url((int) $cm->id)) {
    if ((int) $video->contentformat === FORMAT_VIMEO) {
        $PAGE->requires->js('/mod/video/player.js', true);
    }
    $PAGE->requires->js('/mod/video/captions.js', true);
}
$PAGE->requires->js('/mod/video/preventresubmit.js', true);

if (video_is_complete($course, $cminfo, $USER->id)) {
    $PAGE->requires->js_init_code(
        'try{sessionStorage.setItem("saladeconferencias_completed_cmid",' . (int) $cm->id . ');}catch(e){}'
    );
}

if ($post !== '') {
    require_sesskey();
    video_add_new_post($post, $video, $cm);
    redirect(new moodle_url('/mod/video/view.php', ['id' => $cm->id]));
}

echo $OUTPUT->header();

// Upper content
echo html_writer::start_div('container page__container');
echo html_writer::start_div('narrow-page');
$section = video_get_course_module_section($cm);
if ($CFG->theme != "pdi") {
    $renderer = $PAGE->get_renderer('format_' . $COURSE->format);
    if ($renderer) {
        echo $renderer->print_section_navbar($section, $cm);
    }
}
echo video_print_video_content($video, $cm);
echo video_print_video_data($video, $cm);
echo html_writer::end_div();
echo html_writer::end_div();

// Lower content
echo html_writer::start_div('page-section lower-section', array(
    'id' => 'videoextradata'
));
echo html_writer::start_div('container page__container');
echo video_render_completion_controls($cminfo);
echo video_display_completion_section($cm);
echo html_writer::start_div('row');
echo video_display_comments_section($cm);
echo video_display_files_to_download($cm, $video);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
if ($CFG->theme == "pdi") {
    echo video_print_video_details($video, $cm);
}

$sesskey = sesskey();
echo html_writer::script('window.VIDEO_COMPLETION_CFG = ' . json_encode($completionjsconfig) . ';');
echo '<input type="hidden" id="sesskey" name="sesskey" value="' . $sesskey . '" />';
echo '<input type="hidden" id="cmid" name="cmid" value="' . $cm->id . '" />';
echo '<input type="hidden" id="secondstocomplete" name="secondstocomplete" value="' . get_config('video', 'secondstocomplete') . '" />';
echo '<input type="hidden" id="completeimmediately" name="completeimmediately" value="' . get_config('video', 'completeimmediately') . '" />';
echo '<input type="hidden" id="completed" name="completed" value="' .
    (video_is_complete($course, $cminfo, $USER->id) ? '1' : '0') . '" />';
echo $OUTPUT->footer();

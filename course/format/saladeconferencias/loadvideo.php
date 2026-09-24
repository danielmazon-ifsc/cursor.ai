<?php
// This file is part of Moodle - http://moodle.org/

/**
 * AJAX endpoint: load video player HTML for the featured area.
 *
 * @package    format_saladeconferencias
 * @copyright  2025 Viddia (http://viddia.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot . '/mod/video/lib.php');
require_once($CFG->dirroot . '/mod/video/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

$cmid = required_param('cmid', PARAM_INT);
$autoplay = optional_param('autoplay', 0, PARAM_BOOL);
$completiononly = optional_param('completiononly', 0, PARAM_BOOL);

// Marking view/completion is a state change — require POST so a crafted link
// cannot complete activities via GET even with a valid sesskey.
if ($autoplay && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new moodle_exception('invalidrequest');
}

require_sesskey();

$cmrecord = get_coursemodule_from_id('video', $cmid, 0, true, MUST_EXIST);
$course = get_course($cmrecord->course);
$modinfo = get_fast_modinfo($course);
$cminfo = $modinfo->get_cm($cmid);

require_login($course, false, $cminfo);
$context = context_module::instance($cmid);
require_capability('mod/video:view', $context);

if (!format_saladeconferencias::is_video_playable($cminfo)) {
    throw new moodle_exception('activityiscurrentlyhidden');
}

$video = $DB->get_record('video', ['id' => $cminfo->instance], '*', MUST_EXIST);

if ($autoplay) {
    video_view($video, $course, $cmrecord, $context);
    video_mark_view_completion_on_open($video, $course, $cminfo);

    if (!video_complete_on_view($video) && !empty(get_config('video', 'completeimmediately'))
            && !video_is_complete($course, $cminfo, $USER->id)) {
        video_set_completed($course, $cminfo);
    }
}

list($title,) = video_extract_duration_str_from_title($video);
$completed = video_is_complete($course, $cminfo, $USER->id);
$playeroptions = $autoplay ? ['autoplay' => true] : [];

$completionconfig = [
    'wwwroot' => $CFG->wwwroot,
    'cmid' => $cmid,
    'sesskey' => sesskey(),
    'completed' => $completed,
    'completeimmediately' => !empty(get_config('video', 'completeimmediately')),
    'secondstocomplete' => (int) get_config('video', 'secondstocomplete'),
    'completeonview' => video_complete_on_view($video),
];

header('Content-Type: application/json; charset=utf-8');

if ($completiononly) {
    echo json_encode([
        'ok' => true,
        'cmid' => $cmid,
        'completed' => $completed,
        'completion' => $completionconfig,
    ]);
    exit;
}

$useposter = video_uses_featured_poster($video);
$poster = $useposter ? video_get_player_poster_url($video->id, $cmid) : null;

echo json_encode([
    'player' => video_print_player($cminfo, $playeroptions),
    'poster' => $poster,
    'useposter' => $useposter,
    'title' => format_string($title),
    'cmid' => $cmid,
    'completed' => $completed,
    'autoplay' => $autoplay,
    'completion' => $completionconfig,
]);

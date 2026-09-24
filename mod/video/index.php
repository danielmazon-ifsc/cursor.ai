<?php

/**
 * List of all videos in course
 *
 * @package   mod_video
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require('../../config.php');

$id = required_param('id', PARAM_INT); // course id

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_course_login($course, true);
$VIDEO->set_videolayout('incourse');

// Trigger instances list viewed event.
$event = \mod_video\event\course_module_instance_list_viewed::create(array('context' => context_course::instance($course->id)));
$event->add_record_snapshot('course', $course);
$event->trigger();

$strvideo = get_string('modulename', 'video');
$strvideos = get_string('modulenameplural', 'video');
$strname = get_string('name');
$strintro = get_string('moduleintro');
$strlastmodified = get_string('lastmodified');

$PAGE->set_url('/mod/video/index.php', array('id' => $course->id));
$PAGE->set_title($course->shortname . ': ' . $strvideos);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strvideos);
echo $OUTPUT->header();
echo $OUTPUT->heading($strvideos);
if (!$videos = get_all_instances_in_course('video', $course)) {
    notice(get_string('thereareno', 'moodle', $strvideos), "$CFG->wwwroot/course/view.php?id=$course->id");
    exit;
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_' . $course->format);
    $table->head = array($strsectionname, $strname, $strintro);
    $table->align = array('center', 'left', 'left');
} else {
    $table->head = array($strlastmodified, $strname, $strintro);
    $table->align = array('left', 'left', 'left');
}

$modinfo = get_fast_modinfo($course);
$currentsection = '';
foreach ($videos as $video) {
    $cm = $modinfo->cms[$video->coursemodule];
    if ($usesections) {
        $printsection = '';
        if ($video->section !== $currentsection) {
            if ($video->section) {
                $printsection = get_section_name($course, $video->section);
            }
            if ($currentsection !== '') {
                $table->data[] = 'hr';
            }
            $currentsection = $video->section;
        }
    } else {
        $printsection = '<span class="smallinfo">' . userdate($video->timemodified) . "</span>";
    }

    $class = $video->visible ? '' : 'class="dimmed"'; // hidden modules are dimmed

    $table->data[] = array(
        $printsection,
        "<a $class href=\"view.php?id=$cm->id\">" . format_string($video->name) . "</a>",
        format_module_intro('video', $video, $cm->id));
}

echo html_writer::table($table);

echo $OUTPUT->footer();

<?php

/**
 * @package   mod_video
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die;

define('FORMAT_VIMEO', 1024);
define('FORMAT_YOUTUBE', 1025);
define('FORMAT_EMBEDED', 1026);
define('FORMAT_STORED', 1027);

require_once 'lib/vimeo/autoload.php';

use Vimeo\Vimeo;
use Vimeo\Exceptions\VimeoUploadException;

/**
 * List of features supported in Video module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function video_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE: return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS: return false;
        case FEATURE_GROUPINGS: return false;
        case FEATURE_MOD_INTRO: return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE: return false;
        case FEATURE_GRADE_OUTCOMES: return false;
        case FEATURE_BACKUP_MOODLE2: return true;
        case FEATURE_SHOW_DESCRIPTION: return true;

        default: return null;
    }
}

/**
 * Returns all other caps used in module
 * @return array
 */
function video_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function video_reset_userdata($data) {
    return array();
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function video_get_view_actions() {
    return array('view', 'view all');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function video_get_post_actions() {
    return array('update', 'add');
}

/**
 * Add video instance.
 * @param stdClass $data
 * @param mod_video_mod_form $mform
 * @return int new video instance id
 */
function video_add_instance($data, $mform = null) {
    global $CFG, $DB, $USER;
    require_once("$CFG->libdir/resourcelib.php");

    $cmid = $data->coursemodule;

    $data->timemodified = time();
    $displayoptions = array();
    if ($data->display == RESOURCELIB_DISPLAY_POPUP) {
        $displayoptions['popupwidth'] = $data->popupwidth;
        $displayoptions['popupheight'] = $data->popupheight;
    }
    $displayoptions['printheading'] = $data->printheading;
    $displayoptions['printintro'] = $data->printintro;
    $data->displayoptions = serialize($displayoptions);

    $usercontext = context_user::instance($USER->id);
    $context = context_module::instance($cmid);
    if ($mform) {
        switch ($data->contentformat) {
            case FORMAT_VIMEO:
                $data->content = strip_tags($data->vimeoid);
                break;
            case FORMAT_YOUTUBE:
                $data->content = strip_tags($data->youtubeid);
                break;
            case FORMAT_EMBEDED:
                $data->content = strip_tags($data->embededurl);
                break;
            case FORMAT_STORED:
            default:
                $data->content = null;
                break;
        }
    }

    // Save video files
    $videofiles = file_get_submitted_draft_itemid('storedvideo');
    if ($videofiles) {
        file_save_draft_area_files($videofiles, $context->id, 'mod_video', 'storedvideo', 0);
    }

    // Save downloadable files
    $downloadablefiles = file_get_submitted_draft_itemid('files');
    if ($downloadablefiles) {
        file_save_draft_area_files($downloadablefiles, $context->id, 'mod_video', 'download', 0);
    }

    $newdata = video_set_thumbnail_url($data);

    $newdata->id = $DB->insert_record('video', $newdata);

    // We need to use context now, so we need to make sure all needed info is already in db
    $DB->set_field('course_modules', 'instance', $newdata->id, array('id' => $cmid));

    video_save_cardthumbnail_from_draft($context, $data);
    video_save_caption_from_draft($context, $data);

    $completiontimeexpected = !empty($newdata->completionexpected) ? $newdata->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'video', $newdata->id, $completiontimeexpected);

    return $newdata->id;
}

/**
 * Update video instance.
 * @param object $data
 * @param object $mform
 * @return bool true
 */
function video_update_instance($data, $mform) {
    global $USER, $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    $cmid = $data->coursemodule;

    $data->timemodified = time();
    $data->id = $data->instance;
    $data->revision++;

    $displayoptions = array();
    if ($data->display == RESOURCELIB_DISPLAY_POPUP) {
        $displayoptions['popupwidth'] = $data->popupwidth;
        $displayoptions['popupheight'] = $data->popupheight;
    }
    $displayoptions['printheading'] = $data->printheading;
    $displayoptions['printintro'] = $data->printintro;
    $data->displayoptions = serialize($displayoptions);

    $usercontext = context_user::instance($USER->id);
    $context = context_module::instance($cmid);
    switch ($data->contentformat) {
        case FORMAT_VIMEO:
            $data->content = strip_tags($data->vimeoid);
            break;
        case FORMAT_YOUTUBE:
            $data->content = strip_tags($data->youtubeid);
            break;
        case FORMAT_EMBEDED:
            $data->content = strip_tags($data->embededurl);
            break;
        case FORMAT_STORED:
        default:
            $data->content = null;
            break;
    }

    // Save video files
    $videofiles = file_get_submitted_draft_itemid('storedvideo');
    if ($videofiles) {
        file_save_draft_area_files($videofiles, $context->id, 'mod_video', 'storedvideo', 0);
    }

    // Save downloadable files
    $downloadablefiles = file_get_submitted_draft_itemid('files');
    if ($downloadablefiles) {
        file_save_draft_area_files($downloadablefiles, $context->id, 'mod_video', 'download', 0);
    }

    video_save_cardthumbnail_from_draft($context, $data);
    video_save_caption_from_draft($context, $data);

    $newdata = video_set_thumbnail_url($data);

    $DB->update_record('video', $data);

    $completiontimeexpected = !empty($newdata->completionexpected) ? $newdata->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'video', $newdata->id, $completiontimeexpected);

    return true;
}

/**
 * Delete video instance.
 * @param int $id
 * @return bool true
 */
function video_delete_instance($id) {
    global $DB;

    if (!$video = $DB->get_record('video', array('id' => $id))) {
        return false;
    }

// note: all context files are deleted automatically

    $DB->delete_records('video', array('id' => $video->id));

    return true;
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 *
 * See {@link get_array_of_activities()} in course/lib.php
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info Info to customise main video display
 */
function video_get_coursemodule_info($coursemodule) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    if (!$video = $DB->get_record('video', array('id' => $coursemodule->instance), 'id, name, display, displayoptions, intro, introformat')) {
        return NULL;
    }

    $info = new cached_cm_info();
    $info->name = $video->name;

    if ($coursemodule->showdescription) {
// Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('video', $video, $coursemodule->id, false);
    }

    if ($video->display != RESOURCELIB_DISPLAY_POPUP) {
        return $info;
    }

    $fullurl = "$CFG->wwwroot/mod/video/view.php?id=$coursemodule->id&amp;inpopup=1";
    $options = empty($video->displayoptions) ? array() : unserialize($video->displayoptions);
    $width = empty($options['popupwidth']) ? 620 : $options['popupwidth'];
    $height = empty($options['popupheight']) ? 450 : $options['popupheight'];
    $wh = "width=$width,height=$height,toolbar=no,location=no,menubar=no,copyhistory=no,status=no,directories=no,scrollbars=yes,resizable=yes";
    $info->onclick = "window.open('$fullurl', '', '$wh'); return false;";

    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_video', 'content', 0, 'sortorder DESC, id ASC', false); // TODO: this is not very efficient!!
    if (count($files) >= 1) {
        $mainfile = reset($files);
        $info->icon = file_file_icon($mainfile, 24);
        $video->mainfile = $mainfile->get_filename();
    }

    $display = video_get_final_display_type($video);

    if ($display == RESOURCELIB_DISPLAY_POPUP) {
        $fullurl = "$CFG->wwwroot/mod/video/view.php?id=$coursemodule->id&amp;redirect=1";
        $options = empty($video->displayoptions) ? array() : unserialize($video->displayoptions);
        $width = empty($options['popupwidth']) ? 620 : $options['popupwidth'];
        $height = empty($options['popupheight']) ? 450 : $options['popupheight'];
        $wh = "width=$width,height=$height,toolbar=no,location=no,menubar=no,copyhistory=no,status=no,directories=no,scrollbars=yes,resizable=yes";
        $info->onclick = "window.open('$fullurl', '', '$wh'); return false;";
    } else if ($display == RESOURCELIB_DISPLAY_NEW) {
        $fullurl = "$CFG->wwwroot/mod/video/view.php?id=$coursemodule->id&amp;redirect=1";
        $info->onclick = "window.open('$fullurl'); return false;";
    }

    $info->customdata = $video->displayoptions;

    return $info;
}

/**
 * Lists all browsable file areas
 *
 * @package  mod_video
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return array
 */
function video_get_file_areas($course, $cm, $context) {
    $areas = array();
    $areas['content'] = get_string('content', 'video');
    $areas['caption'] = get_string('caption', 'video');
    return $areas;
}

/**
 * File browsing support for video module content area.
 *
 * @package  mod_video
 * @category files
 * @param stdClass $browser file browser instance
 * @param stdClass $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info instance or null if not found
 */
function video_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG;

    if (!has_capability('moodle/course:managefiles', $context)) {
// students can not peak here!
        return null;
    }

    $fs = get_file_storage();

    if ($filearea === 'content') {
        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        $urlbase = $CFG->wwwroot . '/pluginfile.php';
        if (!$storedfile = $fs->get_file($context->id, 'mod_video', 'content', 0, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_video', 'content', 0);
            } else {
// not found
                return null;
            }
        }
        require_once("$CFG->dirroot/mod/video/locallib.php");
        return new video_content_file_info($browser, $context, $storedfile, $urlbase, $areas[$filearea], true, true, true, false);
    }

// note: video_intro handled in file_browser automatically

    return null;
}

/**
 * Serves the video files.
 *
 * @package  mod_video
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function video_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);
    if (!has_capability('mod/video:view', $context)) {
        return false;
    }

    if ($filearea === 'cardthumbnail' || $filearea === 'caption') {
        array_shift($args); // revision / itemid placeholder used by make_pluginfile_url
        $filename = array_pop($args);
        if (!$filename) {
            return false;
        }
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'mod_video', $filearea, 0, $filepath, $filename);
        if (!$file || $file->is_directory()) {
            return false;
        }
        send_stored_file($file, 0, 0, $forcedownload, $options);
    }

    if ($filearea !== 'storedvideo' && $filearea !== 'download') {
        // intro is handled automatically in pluginfile.php
        return false;
    }

    // $arg could be revision number or index.html
    $arg = array_shift($args);
    if ($arg == 'index.html' || $arg == 'index.htm') {
        // serve video content
        $filename = $arg;

        if (!$video = $DB->get_record('video', array('id' => $cm->instance), '*', MUST_EXIST)) {
            return false;
        }

        // We need to rewrite the pluginfile URLs so the media filters can work.
        $content = file_rewrite_pluginfile_urls($video->content, 'webservice/pluginfile.php', $context->id, 'mod_video', $filearea, $video->revision);
        $formatoptions = new stdClass;
        $formatoptions->noclean = true;
        $formatoptions->overflowdiv = true;
        $formatoptions->context = $context;
        $content = format_text($content, $video->contentformat, $formatoptions);

        // Remove @@PLUGINFILE@@/.
        $options = array('reverse' => true);
        $content = file_rewrite_pluginfile_urls($content, 'webservice/pluginfile.php', $context->id, 'mod_video', $filearea, $video->revision, $options);
        $content = str_replace('@@PLUGINFILE@@/', '', $content);

        send_file($content, $filename, 0, 0, true, true);
    } else {
        $fs = get_file_storage();
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_video/$filearea/0/$relativepath";

        do {
            if (!$file = $fs->get_file_by_hash(sha1($fullpath))) {
                if ($fs->get_file_by_hash(sha1("$fullpath/."))) {
                    if ($file = $fs->get_file_by_hash(sha1("$fullpath/index.htm"))) {
                        break;
                    }
                    if ($file = $fs->get_file_by_hash(sha1("$fullpath/index.html"))) {
                        break;
                    }
                    if ($file = $fs->get_file_by_hash(sha1("$fullpath/Default.htm"))) {
                        break;
                    }
                }
            }
        } while (false);

        // finally send the file
        send_stored_file($file, null, 0, $forcedownload, $options);
    }
}

/**
 * Return a list of video types
 * @param string $videotype current video type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function video_video_type_list($videotype, $parentcontext, $currentcontext) {
    $module_videotype = array('mod-video-*' => get_string('video-mod-video-x', 'video'));
    return $module_videotype;
}

/**
 * Export video resource contents
 *
 * @return array of file content
 */
function video_export_contents($cm, $baseurl) {
    global $CFG, $DB;
    $contents = array();
    $context = context_module::instance($cm->id);

    $video = $DB->get_record('video', array('id' => $cm->instance), '*', MUST_EXIST);

// video contents
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_video', 'content', 0, 'sortorder DESC, id ASC', false);
    foreach ($files as $fileinfo) {
        $file = array();
        $file['type'] = 'file';
        $file['filename'] = $fileinfo->get_filename();
        $file['filepath'] = $fileinfo->get_filepath();
        $file['filesize'] = $fileinfo->get_filesize();
        $file['fileurl'] = file_encode_url("$CFG->wwwroot/" . $baseurl, '/' . $context->id . '/mod_video/content/' . $video->revision . $fileinfo->get_filepath() . $fileinfo->get_filename(), true);
        $file['timecreated'] = $fileinfo->get_timecreated();
        $file['timemodified'] = $fileinfo->get_timemodified();
        $file['sortorder'] = $fileinfo->get_sortorder();
        $file['userid'] = $fileinfo->get_userid();
        $file['author'] = $fileinfo->get_author();
        $file['license'] = $fileinfo->get_license();
        $contents[] = $file;
    }

// video html content
    $filename = 'index.html';
    $videofile = array();
    $videofile['type'] = 'file';
    $videofile['filename'] = $filename;
    $videofile['filepath'] = '/';
    $videofile['filesize'] = 0;
    $videofile['fileurl'] = file_encode_url("$CFG->wwwroot/" . $baseurl, '/' . $context->id . '/mod_video/content/' . $filename, true);
    $videofile['timecreated'] = null;
    $videofile['timemodified'] = $video->timemodified;
// make this file as main file
    $videofile['sortorder'] = 1;
    $videofile['userid'] = null;
    $videofile['author'] = null;
    $videofile['license'] = null;
    $contents[] = $videofile;

    return $contents;
}

/**
 * Register the ability to handle drag and drop file uploads
 * @return array containing details of the files / types the mod can handle
 */
function video_dndupload_register() {
    return array('types' => array(
            array('identifier' => 'text/html', 'message' => get_string('createvideo', 'video')),
            array('identifier' => 'text', 'message' => get_string('createvideo', 'video'))
        ),
        'files' => array(
            array('extension' => '*', 'message' => get_string('dnduploadresource', 'mod_resource'))
    ));
}

/**
 * Handle a file that has been uploaded
 * @param object $uploadinfo details of the file / content that has been uploaded
 * @return int instance id of the newly created mod
 */
function video_dndupload_handle($uploadinfo) {
// Gather the required info.
    $data = new stdClass();
    $data->course = $uploadinfo->course->id;
    $data->name = $uploadinfo->displayname;
    $data->intro = '<p>' . $uploadinfo->displayname . '</p>';
    $data->introformat = FORMAT_HTML;
    if ($uploadinfo->type == 'text/html') {
        $data->contentformat = FORMAT_HTML;
        $data->content = clean_param($uploadinfo->content, PARAM_CLEANHTML);
    } else {
        $data->contentformat = FORMAT_PLAIN;
        $data->content = clean_param($uploadinfo->content, PARAM_TEXT);
    }
    $data->coursemodule = $uploadinfo->coursemodule;

// Set the display options to the site defaults.
    $config = get_config('video');
    $data->display = $config->display;
    $data->popupheight = $config->popupheight;
    $data->popupwidth = $config->popupwidth;
    $data->printheading = $config->printheading;
    $data->printintro = $config->printintro;
    return video_add_instance($data, null);
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $video       video object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @param bool $setcompletion True is completion is to be set 
 * @since Moodle 3.0
 */
function video_view($video, $course, $cm, $context, $setcompletion = false) {

// Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $video->id
    );

    $event = \mod_video\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('video', $video);
    $event->trigger();

// Completion is set when the student finishes watching (mod/video/togglecompletion.php).
    if ($setcompletion) {
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);
    }
}

/**
 * Extract external video id from module description
 * 
 * @param object $video Video course module
 * @return string Video id or null if id could not be extracted
 */
function video_get_external_id($video) {
    if (!$video) {
        error_log('Null video object');
        return null;
    }
    if ($video->contentformat == FORMAT_VIMEO || $video->contentformat == FORMAT_YOUTUBE) {
        return $video->content;
    } else {
        return null;
    }
}

/**
 * Retrieve a video record from its instance id
 * 
 * @param int $id Video instance id
 * @return object Video database record associated with instance id or null if none
 */
function video_get_from_id($id) {
    global $DB;
    $video = $DB->get_record('video', array('id' => $id));
    return $video;
}

function video_print_stored_player($cm, $video) {
    global $CFG, $PAGE, $OUTPUT;

    $file = video_get_storedfile($video->id);
    if (!$file) {
        error_log("Stored video file for video id $video->id not found");
        return "";
    }
    $context = context_module::instance($cm->id);
    $path = '/' . $context->id . '/mod_video/storedvideo/1' . $file->get_filepath() . $file->get_filename();
    $fullurl = file_encode_url($CFG->wwwroot . '/pluginfile.php', $path, false);
    $moodleurl = new moodle_url('/pluginfile.php' . $path);
    $mimetype = $file->get_mimetype();
    $title = $video->name;
    $extension = resourcelib_get_extension($file->get_filename());
    $mediamanager = core_media_manager::instance($PAGE);
    $embedoptions = array(
        core_media_manager::OPTION_TRUSTED => true,
        core_media_manager::OPTION_BLOCK => true,
    );
    if (!$mediamanager->can_embed_url($moodleurl, $embedoptions)) {
        error_log("Stored video file for video id $video->id cannot be played");
        return "";
    }
    $html = $mediamanager->embed_url($moodleurl, $title, 4096 /* maximum width */, 0, $embedoptions);
    $captionurl = video_get_caption_url((int) $cm->id);
    if ($captionurl) {
        $html = video_inject_html5_caption_track($html, $captionurl, get_string('captionlabel', 'video'));
    }
    return $html;
}

function video_print_player($cm, array $options = []) {
    $video = video_get_from_id($cm->instance);
    if (!$video) {
        return '';
    }
    switch ($video->contentformat) {
        case FORMAT_STORED:
            $html = video_print_stored_player($cm, $video);
            if (!empty($options['autoplay'])) {
                $html = video_apply_autoplay_to_player($html);
            }
            return video_wrap_player_with_captions($html, (int) $cm->id);
        case FORMAT_VIMEO:
            $format = get_config('video', 'vimeoformat');
            break;
        case FORMAT_YOUTUBE:
            $format = get_config('video', 'youtubeformat');
            break;
        case FORMAT_EMBEDED:
            // When the embedded URL is a direct media file (mp4/webm/...) and a
            // caption was uploaded, render a native HTML5 <video> with a <track>
            // instead of the plain iframe embed: iframes to a raw media URL are
            // cross-origin and cannot carry captions, but a native player can.
            $captionurl = video_get_caption_url((int) $cm->id);
            $mediaurl = $captionurl ? video_embed_direct_media_url((string) $video->content) : '';
            if ($captionurl && $mediaurl) {
                $html = video_build_native_embed_player($mediaurl, $captionurl, (string) $video->name);
                if (!empty($options['autoplay'])) {
                    $html = video_apply_autoplay_to_player($html);
                }
                return video_wrap_player_with_captions($html, (int) $cm->id);
            }
            $format = get_config('video', 'embededformat');
            break;
        default:
            return '';
    }
    $html = '';
    $html .= html_writer::start_div('js-player embed-responsive embed-responsive-16by9');
    $videocontent = str_replace("{\$a}", $video->content, $format);
    $context = context_module::instance($cm->id);
    $unformattedcontent = file_rewrite_pluginfile_urls($videocontent, 'pluginfile.php', $context->id, 'mod_video', 'content', $video->revision);
    $formatoptions = new stdClass;
    $formatoptions->noclean = true;
    $formatoptions->overflowdiv = true;
    $formatoptions->context = $context;
    $html .= format_text($unformattedcontent, $video->contentformat, $formatoptions);
    if ($video->contentformat == FORMAT_YOUTUBE) {
        $html = video_prepare_youtube_embed($html);
    }
    $html .= html_writer::end_div();
    if (!empty($options['autoplay'])) {
        $html = video_apply_autoplay_to_player($html);
    }
    return video_wrap_player_with_captions($html, (int) $cm->id);
}

/**
 * If the embedded content URL points to a direct media file, return it; else ''.
 *
 * @param string $content Stored embed URL
 * @return string Direct media URL or empty string
 */
function video_embed_direct_media_url(string $content): string {
    $url = trim($content);
    if ($url === '') {
        return '';
    }
    if (preg_match('/\.(mp4|webm|ogg|m4v|mov)(\?|#|$)/i', $url)) {
        return $url;
    }
    return '';
}

/**
 * Build a native HTML5 <video> player (with caption <track>) for a direct media URL.
 *
 * The markup mirrors the js-player wrapper used elsewhere so both the module
 * view page and the Sala de Conferências featured player size it consistently.
 *
 * @param string $mediaurl Direct media file URL
 * @param string $captionurl Pluginfile URL of the .vtt caption
 * @param string $title Video name (used as aria-label)
 * @return string
 */
function video_build_native_embed_player(string $mediaurl, string $captionurl, string $title): string {
    $ext = strtolower((string) pathinfo(parse_url($mediaurl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    $type = 'video/mp4';
    if ($ext === 'webm') {
        $type = 'video/webm';
    } else if ($ext === 'ogg') {
        $type = 'video/ogg';
    }

    $video = html_writer::start_tag('video', [
        'class' => 'saladeconferencias-featured__native-video mod-video-native embed-responsive-item',
        'controls' => 'controls',
        'playsinline' => 'playsinline',
        'preload' => 'auto',
        'aria-label' => $title,
        'style' => 'width:100%;',
    ]);
    $video .= html_writer::empty_tag('source', [
        'src' => $mediaurl,
        'type' => $type,
    ]);
    $video .= html_writer::empty_tag('track', [
        'kind' => 'captions',
        'srclang' => 'pt',
        'label' => get_string('captionlabel', 'video'),
        'src' => $captionurl,
    ]);
    $video .= html_writer::end_tag('video');

    $html = html_writer::start_div('js-player embed-responsive embed-responsive-16by9');
    $html .= html_writer::start_div('js-videobody');
    $html .= html_writer::start_div('js-videowrapper');
    $html .= $video;
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

/**
 * Adds autoplay parameters to embedded players.
 *
 * @param string $html Player HTML
 * @return string
 */
function video_apply_autoplay_to_player(string $html): string {
    if (strpos($html, '<video') !== false) {
        $html = preg_replace('/(<video\b(?![^>]*\bautoplay\b)[^>]*)>/i', '$1 autoplay>', $html) ?? $html;
    }
    $html = preg_replace_callback(
        '/(<iframe[^>]+src=")([^"]*)(")/i',
        function(array $matches): string {
            $src = $matches[2];
            if (strpos($src, 'autoplay=1') !== false) {
                return $matches[0];
            }
            $separator = (strpos($src, '?') === false) ? '?' : '&';
            return $matches[1] . $src . $separator . 'autoplay=1' . $matches[3];
        },
        $html
    ) ?? $html;
    return $html;
}

/**
 * Adds YouTube IFrame API parameters required for completion tracking.
 *
 * @param string $html Embed HTML
 * @return string
 */
function video_prepare_youtube_embed(string $html): string {
    global $CFG;

    if (strpos($html, 'youtube.com') === false || strpos($html, 'enablejsapi=1') !== false) {
        return $html;
    }

    $parts = parse_url($CFG->wwwroot);
    $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
    if (!empty($parts['port'])) {
        $origin .= ':' . $parts['port'];
    }
    $origin = rawurlencode($origin);

    return preg_replace_callback(
        '/(<iframe[^>]+src=")([^"]*youtube\.com\/embed\/[^"]*)(")/i',
        function(array $matches) use ($origin): string {
            $src = $matches[2];
            $separator = (strpos($src, '?') === false) ? '?' : '&';
            if (strpos($src, 'ecver=1') !== false) {
                $src = str_replace('ecver=1', 'enablejsapi=1', $src);
            } else {
                $src .= $separator . 'enablejsapi=1';
            }
            if (strpos($src, 'origin=') === false) {
                $src .= '&origin=' . $origin;
            }
            return $matches[1] . $src . $matches[3];
        },
        $html
    ) ?? $html;
}

function video_get_cm_from_socialforum($socialforum, $discussion) {
    global $DB;
    $sql = 'SELECT cm.* '
            . 'FROM {course_modules} cm, {modules} m '
            . 'WHERE cm.module = m.id '
            . "AND m.name = 'video' "
            . 'AND cm.instance IN ('
            . '     SELECT id '
            . '     FROM {video} v '
            . '     WHERE v.socialforumid = :socialforumid '
            . '     AND v.name = :videoname);';
    $videocm = $DB->get_record_sql($sql, array(
        'socialforumid' => $socialforum->id,
        'videoname' => $discussion->name));
    return $videocm;
}

function video_get_lib() {
    $clientid = get_config('video', 'vimeoclientid');
    $clientsecret = get_config('video', 'vimeoclientsecret');
    $clienttoken = get_config('video', 'vimeoclienttoken');
    $lib = new Vimeo($clientid, $clientsecret, $clienttoken);
    return $lib;
}

function video_get_album_id(string $vimeouserid, string $albumname) {
    $MAX_PAGES = 10;
    $ALBUMS_PER_PAGE = 100;
    $lib = video_get_lib();
    // Retrieve all albuns, page per page
    $uri = "/users/$vimeouserid/albums";
    $page = 1;
    for ($page = 1; $page <= $MAX_PAGES; ++$page) {
        $video_data = $lib->request($uri, array(
            'page' => $page,
            'per_page' => $ALBUMS_PER_PAGE,
            'fields' => 'name,uri'
        ));
        if ($video_data['status'] != '200') {
            return null;
        }
        foreach ($video_data['body']['data'] as $album) {
            if ($album['name'] == $albumname) {
                $resulturi = $album['uri'];
                $resulturiparts = explode('/', $resulturi);
                return $resulturiparts[count($resulturiparts) - 1];
            }
        }
    }
    return null;
}

function video_create_album(string $vimeouserid, string $albumname) {
    $lib = video_get_lib();
    // Create album
    $uri = "/users/$vimeouserid/albums";
    $video_data = $lib->request($uri, array(
        'name' => $albumname,
        'privacy' => 'embed_only',
            ), 'POST');
    // Process result
    if ($video_data['status'] != '201') {
        return null;
    }
    $resulturi = $video_data['body']['uri'];
    $resulturiparts = explode('/', $resulturi);
    return $resulturiparts[count($resulturiparts) - 1];
}

function video_include_in_album(string $vimeouserid, string $videovideoid, string $albumid) {
    $lib = video_get_lib();
    // Add video to album
    $uri = "/users/$vimeouserid/albums/$albumid/videos/$videovideoid";
    $video_data = $lib->request($uri, array(), 'PUT');
    if ($video_data['status'] != '204') {
        return false;
    }
    return true;
}

function video_process_attribute(&$arr, $path, $value, $separator = '.') {
    $keys = explode($separator, $path);
    foreach ($keys as $key) {
        $arr = &$arr[$key];
    }
    $arr = $value;
}

function video_set_attribute(string $vimeovideoid, string $attname, string $attvalue) {
    // Change . hierarchy to multilevel array
    $params = [];
    video_process_attribute($params, $attname, $attvalue);
    // Set attribute
    $lib = video_get_lib();
    $atturi = "/videos/$vimeovideoid";
    $video_data = $lib->request($atturi, $params, 'PATCH');
    if ($video_data['status'] != '200') {
        error_log("Failed to set attribute $attname of video with vimeo id $vimeovideoid to $attvalue.");
        return false;
    }
    return true;
}

function video_access_attribute(array $a, $path, $default = null) {
    $current = $a;
    $p = strtok($path, '.');
    while ($p !== false) {
        if (!isset($current[$p])) {
            return $default;
        }
        $current = $current[$p];
        $p = strtok('.');
    }
    return $current;
}

function video_get_attribute(string $vimeovideoid, string $attname) {
    // Get attribute
    $lib = video_get_lib();
    $atturi = "/videos/$vimeovideoid";
    $video_data = $lib->request($atturi, array("fields" => $attname));
    if ($video_data['status'] != '200') {
        error_log("Failed to get attribute $attname of video with vimeo id $vimeovideoid.");
        return null;
    }
    return video_access_attribute($video_data, "body." . $attname);
}

function video_get_storedfile(int $videoid) {
    $cm = get_coursemodule_from_instance('video', $videoid);
    if (!$cm) {
        error_log("Course module for video id $videoid not found.");
        return null;
    }
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    if (!$fs) {
        error_log("Cannot access file storage.");
        return null;
    }
    $files = $fs->get_area_files($context->id, 'mod_video', 'storedvideo', 0, 'sortorder', false);
    if (count($files) != 1) {
        error_log("Video with id $videoid not found for uploading or more than one file found.");
        return null;
    }
    $file = reset($files);
    if ($file->get_filesize() == 0) {
        error_log("Video with id $videoid has an empty file.");
        return null;
    }
    return $file;
}

function video_remove_storedfile(int $videoid) {
    $cm = get_coursemodule_from_instance('video', $videoid);
    if (!$cm) {
        error_log("Course module for video id $videoid not found.");
        return false;
    }
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    if (!$fs) {
        error_log("Cannot access file storage.");
        return false;
    }
    if (!$fs->delete_area_files($context->id, 'mod_video', 'storedvideo')) {
        error_log("Cannot delete files of video $videoid from file storage.");
        return false;
    }
    return true;
}

function video_get_tmp_file_path(int $videoid) {
    $file = video_get_storedfile($videoid);
    if (!$file) {
        error_log("Fail to retrieve stored file for video with id $videoid.");
        return null;
    }
    $tmpfilepath = $file->copy_content_to_temp();
    return $tmpfilepath;
}

function video_upload_video(stdClass $video) {
    if ($video->contentformat != FORMAT_STORED) {
        error_log("Video with id $video->id is not stored.");
        return null;
    }
    $filepath = video_get_tmp_file_path($video->id);
    if (!$filepath) {
        error_log("Fail to generate temporary file for video with id $video->id.");
        return null;
    }
    $lib = video_get_lib();
    try {
        // Upload video file
        $uri = $lib->upload($filepath, array(
            'name' => $video->name,
        ));
        if (!unlink($filepath)) {
            error_log("Fail to remove video temporary file $filepath.");
            return null;
        }
        if (!$uri) {
            error_log("Fail to upload video with id $video->id.");
            return null;
        }
        $resulturiparts = explode('/', $uri);
        $vimeovideoid = $resulturiparts[count($resulturiparts) - 1];
        // Viddia Set Vimeo video name as Moodle video name
        if (!video_set_attribute($vimeovideoid, 'name', $video->name)) {
            error_log("Failed to set name of video id $video->id in Vimeo.");
            return null;
        }
        // Set video privacy to hidden from vimeo
        if (!video_set_attribute($vimeovideoid, 'privacy.view', 'disable')) {
            error_log("Failed to set privacy of video id $video->id to hidden.");
            return null;
        }
        return $vimeovideoid;
    } catch (VimeoUploadException $e) {
        error_log("Error while uploading video with id $video->id.");
        return null;
    } catch (VimeoRequestException $e) {
        error_log("Error while uploading video with id $video->id.");
        return null;
    }
}

function video_get_album_name(stdClass $video) {
    $site = get_site();
    $course = get_course($video->course);
    return $site->shortname . " - " . $course->shortname;
}

function video_upload_videos() {
    global $DB;

    echo "Starting to upload stored videos to Vimeo<br>";
    $storedvideos = $DB->get_records('video', array('contentformat' => FORMAT_STORED));
    foreach ($storedvideos as $storedvideo) {
        if (!strpos($storedvideo->content, "/")) {
            $status = "NONE";
        } else {
            $parts = explode("/", $storedvideo->content);
            $status = $parts[0];
            $vimeovideoid = $parts[1];
        }
        $WAITSTATUS_STR = "TRANSCODING";
        if ($status != $WAITSTATUS_STR) {
            // Upload video
            echo "Uploading video with id $storedvideo->id to Vimeo<br>";
            $vimeovideoid = video_upload_video($storedvideo);
            if (!$vimeovideoid) {
                echo "Fail to upload video with id $storedvideo->id to Vimeo<br>";
                continue;
            }
            // Update content with uploading status and vimeo video id
            $storedvideo->content = $WAITSTATUS_STR . "/" . $vimeovideoid;
            $DB->update_record('video', $storedvideo);
            echo "Video with id $storedvideo->id uploaded to Vimeo with vimeo id $vimeovideoid<br>";
            // Associate video with album
            $vimeouserid = get_config('video', 'vimeouserid');
            $albumname = video_get_album_name($storedvideo);
            $albumid = video_get_album_id($vimeouserid, $albumname);
            if (!$albumid) {
                $albumid = video_create_album($vimeouserid, $albumname);
                if (!$albumid) {
                    echo "Fail to create album in Vimeo with name $albumname<br>";
                    continue;
                }
                echo "Album with name $albumname created in Vimeo with id $albumid<br>";
            }
            $result = video_include_in_album($vimeouserid, $vimeovideoid, $albumid);
            if (!$result) {
                echo "Fail to add video with id $storedvideo->id to album $albumname in Vimeo<br>";
                continue;
            }
            echo "Video with id $storedvideo->id added to album $albumname in Vimeo<br>";
        } else {
            // Check if the video is ready in vimeo
            // Transcode status will change from in_progress to complete
            echo "Checking transcoding status of video with id $storedvideo->id<br>";
            $status = video_get_attribute($vimeovideoid, "transcode.status");
            if ($status != "complete") {
                echo "Transcoding status for video with id $storedvideo->id is $status. It will be checked again later<br>";
                continue;
            }
            echo "Transcoding for video with id $storedvideo->id is complete<br>";
            // Update video status
            $storedvideo->contentformat = FORMAT_VIMEO;
            $storedvideo->content = $vimeovideoid;
            $DB->update_record('video', $storedvideo);
            echo "Video with id $storedvideo->id set as type Vimeo with Vimeo id $vimeovideoid<br>";
            // Remove video file
            if (!video_remove_storedfile($storedvideo->id)) {
                echo "Fail to remove video file of video $storedvideo->id<br>";
                continue;
            }
            echo "Video file of video $storedvideo->id removed<br>";
        }
    }
    echo "Finished  to upload stored videos to Vimeo<br>";
}

/**
 * Set a video thumbnail URL
 * 
 * @param stdClass $video Video which thumbnail URL will be set
 * @return stdClass Video with thumbnailurl set to thumbnail URL or to null if
 *                      cannot be done
 */
function video_set_thumbnail_url($video) {
    if (!$video) {
        error_log('Null video object');
        return null;
    }
    $video_id = video_get_external_id($video);
    if (!$video_id) {
        error_log('No external video id');
        return $video;
    }
    if ($video->contentformat == FORMAT_VIMEO) {
        $video->thumbnailurl = video_get_vimeo_thumbnail_url($video_id);
    } elseif ($video->contentformat == FORMAT_YOUTUBE) {
        $video->thumbnailurl = video_get_youtube_thumbnail_url($video_id);
    }
    return $video;
}

/**
 * Persist card thumbnail files from the activity form draft area.
 *
 * @param context $context Module context
 * @param stdClass|null $data Form data (uses cardthumbnail draft item id when present)
 */
function video_save_cardthumbnail_from_draft(context $context, $data = null): void {
    if ($data && isset($data->contentformat) && (int) $data->contentformat !== FORMAT_EMBEDED) {
        return;
    }

    $draftitemid = 0;
    if ($data && !empty($data->cardthumbnail)) {
        $draftitemid = (int) $data->cardthumbnail;
    }
    if (!$draftitemid) {
        $draftitemid = file_get_submitted_draft_itemid('cardthumbnail');
    }
    if (!$draftitemid) {
        return;
    }

    file_save_draft_area_files($draftitemid, $context->id, 'mod_video', 'cardthumbnail', 0);
}

/**
 * Persist WebVTT caption files from the activity form draft area.
 *
 * @param context $context Module context
 * @param stdClass|null $data Form data (uses caption draft item id when present)
 */
function video_save_caption_from_draft(context $context, $data = null): void {
    $draftitemid = 0;
    if ($data && !empty($data->caption)) {
        $draftitemid = (int) $data->caption;
    }
    if (!$draftitemid) {
        $draftitemid = file_get_submitted_draft_itemid('caption');
    }
    if (!$draftitemid) {
        return;
    }

    file_save_draft_area_files($draftitemid, $context->id, 'mod_video', 'caption', 0);
}

/**
 * First non-directory file in the caption file area, if any.
 *
 * @param context $context Module context
 * @return stored_file|null
 */
function video_get_caption_file(context $context): ?stored_file {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_video', 'caption', 0, 'itemid, filepath, filename', false);
    foreach ($files as $file) {
        if ($file->is_directory()) {
            continue;
        }
        return $file;
    }
    return null;
}

/**
 * Pluginfile URL for the activity WebVTT caption, or null when none uploaded.
 *
 * @param int $cmid Course module id
 * @return string|null
 */
function video_get_caption_url(int $cmid): ?string {
    $context = context_module::instance($cmid);
    $file = video_get_caption_file($context);
    if (!$file) {
        return null;
    }
    return moodle_url::make_pluginfile_url(
        $context->id,
        'mod_video',
        'caption',
        0,
        $file->get_filepath(),
        $file->get_filename()
    )->out(false);
}

/**
 * Wrap player HTML with caption metadata and overlay container when a VTT exists.
 *
 * @param string $html Player markup
 * @param int $cmid Course module id
 * @return string
 */
function video_wrap_player_with_captions(string $html, int $cmid): string {
    if ($html === '') {
        return '';
    }
    $captionurl = video_get_caption_url($cmid);
    if (!$captionurl) {
        return $html;
    }

    $label = get_string('captionlabel', 'video');
    $attrs = [
        'class' => 'mod-video-player',
        'data-caption-url' => $captionurl,
        'data-caption-lang' => 'pt',
        'data-caption-label' => $label,
    ];
    $out = html_writer::start_tag('div', $attrs);
    $out .= $html;
    $out .= html_writer::div('', 'mod-video-captions', [
        'aria-live' => 'polite',
        'hidden' => 'hidden',
    ]);
    $out .= html_writer::tag('button', get_string('captiontoggle', 'video'), [
        'type' => 'button',
        'class' => 'mod-video-captions-toggle btn btn-sm btn-secondary is-off',
        'aria-pressed' => 'false',
        'title' => get_string('captiontoggle', 'video'),
    ]);
    $out .= html_writer::end_tag('div');
    return $out;
}

/**
 * Inject a native HTML5 captions track into the first video element.
 *
 * @param string $html Player markup
 * @param string $captionurl Pluginfile URL of the .vtt
 * @param string $label Track label
 * @return string
 */
function video_inject_html5_caption_track(string $html, string $captionurl, string $label): string {
    if (stripos($html, '<video') === false || stripos($html, '<track') !== false) {
        return $html;
    }
    $track = html_writer::empty_tag('track', [
        'kind' => 'captions',
        'srclang' => 'pt',
        'label' => $label,
        'src' => $captionurl,
    ]);
    $replaced = preg_replace('/(<video\b[^>]*>)/i', '$1' . $track, $html, 1);
    return $replaced ?? $html;
}

/**
 * Default thumbnail when no custom image is available.
 *
 * @return string
 */
function video_get_default_thumbnail_url(): string {
    return (new moodle_url('/mod/video/icon'))->out(false);
}

/**
 * Retrieve the custom card thumbnail URL for embed videos.
 *
 * @param int $videoid Video instance id
 * @param int|null $cmid Course module id (preferred when available)
 * @return string|null Pluginfile URL or null when no image was uploaded
 */
function video_get_card_thumbnail_url($videoid, $cmid = null) {
    if ($cmid) {
        $context = context_module::instance($cmid);
    } else {
        $cm = get_coursemodule_from_instance('video', $videoid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }
        $context = context_module::instance($cm->id);
    }

    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_video', 'cardthumbnail', 0, 'itemid, filepath, filename', false);
    foreach ($files as $file) {
        if ($file->is_directory()) {
            continue;
        }
        return moodle_url::make_pluginfile_url(
            $context->id,
            'mod_video',
            'cardthumbnail',
            0,
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }

    return null;
}

/**
 * Whether the featured player should show a poster before play (embed URL videos only).
 *
 * @param stdClass $video Video instance
 * @return bool
 */
function video_uses_featured_poster(stdClass $video): bool {
    return (int) $video->contentformat === FORMAT_EMBEDED;
}

/**
 * Poster image for the featured player (embed URL videos with card image).
 *
 * @param int $videoid Video instance id
 * @param int|null $cmid Course module id (preferred when available)
 * @return string|null Image URL or null when poster is not used
 */
function video_get_player_poster_url($videoid, $cmid = null) {
    $video = video_get_from_id($videoid);
    if (!$video || !video_uses_featured_poster($video)) {
        return null;
    }
    $cardthumbnail = video_get_card_thumbnail_url($videoid, $cmid);
    if ($cardthumbnail) {
        return $cardthumbnail;
    }
    return video_get_thumbnail_url($videoid, $cmid);
}

/**
 * Retrieve a video thumbnail URL. It it's not set yet, try to set it
 * 
 * @param int $videoid Video id to which thumbnail url should be retrieved
 * @param int|null $cmid Course module id (preferred when available)
 * @return string Video url or null if url cannot be retrieved
 */
function video_get_thumbnail_url($videoid, $cmid = null) {
    global $DB;

    if (!$videoid) {
        error_log('Null video id');
        return video_get_default_thumbnail_url();
    }
    $video = video_get_from_id($videoid);
    if (!$video) {
        error_log('Video not found');
        return video_get_default_thumbnail_url();
    }
    if ((int) $video->contentformat === FORMAT_EMBEDED) {
        $cardthumbnail = video_get_card_thumbnail_url($videoid, $cmid);
        if ($cardthumbnail) {
            return $cardthumbnail;
        }
    }
    // Check if thumbnnail URL is already set
    if ($video->thumbnailurl) {
        return $video->thumbnailurl;
    }
    // Do not call Vimeo/API during page render — use default until save/cron sets thumbnail.
    return video_get_default_thumbnail_url();
}

function video_extract_duration_str_from_cm_title(cm_info $mod) {
    global $DB;

    $video = $DB->get_record('video', array('id' => $mod->instance));
    if ($video) {
        return video_extract_duration_str_from_title($video);
    }
    return array('', $mod->get_formatted_name());
}

/**
 * Try to extract video duration from video title
 * 
 * @param $video
 * @return array of two strings: title with no duration and extracted duration
 */
function video_extract_duration_str_from_title($video) {
    list($extractedtitle, $durationtime) = video_extract_duration_from_title($video);
    $timesplit = explode(':', $durationtime);
    if (empty($timesplit)) {
        return array($extractedtitle, '');
    }
    $durationstr = '';
    if (intval($timesplit[0]) > 0) {
        $durationstr .= $timesplit[0] . ' ' . get_string('minutes', 'mod_video');
    }
    if (!isset($timesplit[1]) || intval($timesplit[1]) == 0) {
        return array($extractedtitle, $durationstr);
    }
    if (!empty($durationstr)) {
        $durationstr .= ' ' . get_string('and', 'mod_video') . ' ' . $timesplit[1] . ' ' . get_string('seconds', 'mod_video');
    } else {
        $durationstr .= $timesplit[1] . ' ' . get_string('seconds', 'mod_video');
    }
    return array($extractedtitle, $durationstr);
}

function video_extract_duration_from_title($video) {
    $title = $video->name;
    $titlesplit = explode('(', $title);
    if (empty($titlesplit)) {
        return array($title, '');
    }
    $extractedtitle = trim($titlesplit[0]);
    if (!isset($titlesplit[1])) {
        return array($extractedtitle, '');
    }
    $durationsplit = explode(')', $titlesplit[1]);
    if (empty($durationsplit)) {
        return array($extractedtitle, '');
    }
    $durationtime = $durationsplit[0];
    return array($extractedtitle, $durationtime);
}

/**
 * Retrieve video thumbnail url in Vimeo
 * 
 * @param string $youtube_id Video id in Youtube
 * @return string Video url or null if url cannot be retrieved
 */
function video_get_youtube_thumbnail_url($youtube_id) {
    if (empty($youtube_id)) {
        error_log('Empty video id');
        return null;
    }
    return 'https://img.youtube.com/vi/' . $youtube_id . '/0.jpg';
}

/**
 * Retrieve video thumbnail url in Vimeo
 * 
 * @param string $vimeo_id Video id in Vimeo
 * @return string Video url or null if url cannot be retrieved
 */
function video_get_vimeo_thumbnail_url($vimeo_id) {
    if (empty($vimeo_id)) {
        error_log('Empty video id');
        return null;
    }
    // Set initial parameters
    $client_id = get_config('theme_flexible', 'vimeoclientid');
    $client_secrets = get_config('theme_flexible', 'vimeoclientsecrets');
    $token = get_config('theme_flexible', 'vimeotoken');
    // Where $client_id and $client_secrets are variables with your vimeo client id 
    // and secrets that you copied from the app you created on Vimeo.
    $vimeoLib = new Vimeo($client_id, $client_secrets);
    // Where $token is the variable containing the token you generated earlier
    $vimeoLib->setToken($token);
    // Retrieve video info
    try {
        $response = $vimeoLib->request("/me/videos/" . $vimeo_id);
        //Check to see if the request didn't fail
        if ($response["status"] === 200) {
            // Get the pictures object
            $thumbnailsObject = $response["body"]["pictures"];
            // Set smallest one as default
            $thumbnail = $thumbnailsObject["sizes"][0]["link"];
            // Find suitable size
            foreach ($thumbnailsObject["sizes"] as $thumbnailObject) {
                if ($thumbnailObject["width"] < 640) {
                    $thumbnail = $thumbnailObject["link"];
                }
            }
            return $thumbnail;
        } else {
            error_log('Thumbnail URL request HTTP response: ' . print_r($response, true));
            return null;
        }
    } catch (\Exception $e) {
        error_log($e->getMessage());
        return null;
    }
}

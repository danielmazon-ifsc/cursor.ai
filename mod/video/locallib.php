<?php

/**
 * Private video module utility functions
 *
 * @package   mod_video
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/filelib.php");
require_once("$CFG->libdir/resourcelib.php");
require_once("$CFG->dirroot/mod/video/lib.php");

/**
 * File browsing support class
 */
class video_content_file_info extends file_info_stored {

    public function get_parent() {
        if ($this->lf->get_filepath() === '/' and $this->lf->get_filename() === '.') {
            return $this->browser->get_file_info($this->context);
        }
        return parent::get_parent();
    }

    public function get_visible_name() {
        if ($this->lf->get_filepath() === '/' and $this->lf->get_filename() === '.') {
            return $this->topvisiblename;
        }
        return parent::get_visible_name();
    }

}

function video_get_editor_options($context) {
    global $CFG;
    return array('subdirs' => 1, 'maxbytes' => $CFG->maxbytes, 'maxfiles' => -1, 'changeformat' => 1, 'context' => $context, 'noclean' => 1, 'trusttext' => 0);
}

function video_set_mainfile($data) {
    $fs = get_file_storage();
    $cmid = $data->coursemodule;
    $draftitemid = $data->files;

    $context = context_module::instance($cmid);
    if ($draftitemid) {
        file_save_draft_area_files($draftitemid, $context->id, 'mod_video', 'content', 0, array('subdirs' => true));
    }
    $files = $fs->get_area_files($context->id, 'mod_video', 'content', 0, 'sortorder', false);
    if (count($files) == 1) {
        // only one file attached, set it as main file automatically
        $file = reset($files);
        file_set_sortorder($context->id, 'mod_video', 'content', 0, $file->get_filepath(), $file->get_filename(), 1);
    }
}

/**
 * Decide the best display format.
 * @param object $video
 * @return int display type constant
 */
function video_get_final_display_type($video) {

    if ($video->display != RESOURCELIB_DISPLAY_AUTO) {
        return $video->display;
    }

    if (empty($video->mainfile)) {
        return RESOURCELIB_DISPLAY_DOWNLOAD;
    } else {
        $mimetype = mimeinfo('type', $video->mainfile);
    }

    if (file_mimetype_in_typegroup($mimetype, 'archive')) {
        return RESOURCELIB_DISPLAY_DOWNLOAD;
    }
    if (file_mimetype_in_typegroup($mimetype, array('web_image', '.htm', 'web_video', 'web_audio'))) {
        return RESOURCELIB_DISPLAY_EMBED;
    }

    // let the browser deal with it somehow
    return RESOURCELIB_DISPLAY_OPEN;
}

/**
 * Split a file path in primary path and uppercase extension
 * 
 * @param type $path
 * @return array of (primarypath, extension)
 */
function video_split_file_path($path) {
    $parts = explode('.', $path);
    $primarypath = $parts[0];
    for ($i = 1; $i < count($parts) - 1; ++$i) {
        $primarypath .= '.' . $parts[$i];
    }
    $extension = count($parts) == 1 ? '' : strtoupper($parts[count($parts) - 1]);
    return array($primarypath, $extension);
}

/**
 * Generate HTML code to represent files to be downloaded
 * 
 * @param object $cm Video course module
 * @param object $video Video
 * @return string HTML representing downloadable files
 */
function video_display_files_to_download($cm, $video) {
    global $CFG;

    $html = '';
    $cminfo = get_fast_modinfo($cm->course)->get_cm($cm->id);
    $context = context_module::instance($cminfo->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_video', 'download', 0, 'sortorder DESC, id ASC', false);
    if (sizeof($files) > 0) {
        $html .= html_writer::start_div('col-md-4');
        $html .= html_writer::start_div('card card-group-row__card bg-roxo-espacial-50', array(
                    'id' => 'downloads'
        ));
        $html .= html_writer::start_div('card-body');
        $html .= html_writer::tag('h6', 'Materiais desta aula', array(
                    'class' => 'text-white'
        ));
        foreach ($files as $file) {
            list($primpath, $fileextension) = video_split_file_path($file->get_source());
            $filename = $file->get_filename();
            $path = '/' . $file->get_contextid() . '/mod_video/' . $file->get_filearea() . '/' . $video->revision . $file->get_filepath() . $file->get_filename();
            $fileurl = file_encode_url($CFG->wwwroot . '/pluginfile.php', $path, true);
            $filesize = round($file->get_filesize() / 1048576, 1) . ' MB';
            $html .= html_writer::start_div('d-flex align-items-center mb-8pt');
            $html .= html_writer::start_div('mr-8pt');
            $html .= html_writer::start_div('avatar avatar-sm avatar-4by4', array(
                        'id' => 'downloadbox'
            ));
            $html .= html_writer::tag('span', 'download', array(
                        'class' => 'material-icons text-white',
                        'style' => 'font-size: 30px; margin-left: 5px; margin-top: 5px;'
            ));
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::start_div('flex d-flex flex-column');
            $html .= html_writer::tag('a', $filename, array('class' => 'card-title text-white', 'style' => 'min-height: 0 !important;', 'href' => $fileurl));
            $html .= html_writer::tag('small', $filesize . ' | ' . get_string('file', 'mod_video') . ' ' . $fileextension, array('class' => 'text-white-50'));
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
    }
    return $html;
}

/**
 * Renders core activity completion UI (manual "Marcar como feito" and automatic conditions).
 *
 * @param cm_info $cm Course module info
 * @return string HTML
 */
function video_render_completion_controls(cm_info $cm): string {
    global $OUTPUT, $USER;

    if (!isloggedin() || isguestuser()) {
        return '';
    }

    $completiondetails = \core_completion\cm_completion_details::get_instance($cm, $USER->id);
    $activitydates = \core\activity_dates::get_dates_for_module($cm, $USER->id);

    if (!$completiondetails->has_completion() && empty($activitydates)) {
        return '';
    }

    $content = $OUTPUT->activity_information($cm, $completiondetails, $activitydates);
    if ($content === '') {
        return '';
    }

    return html_writer::div($content, 'video-completion-ui');
}

/**
 * Generate HTML code to completion section
 * 
 * @param type $cm
 */
function video_display_completion_section($cm) {
    global $COURSE, $USER;

    $html = '';
    $coursecompletion = new completion_info($COURSE);
    $cmcompletion = $coursecompletion->get_data($cm, false, $USER->id);
    if ($cmcompletion->completionstate == COMPLETION_COMPLETE) {
        $completiontime = $cmcompletion->timemodified;
        $html .= html_writer::start_div('navbar navbar-expand-sm navbar-dark  navbar-list p-0 m-0 align-items-center', array('style' => 'padding-bottom: 1.5rem !important;'));
        $html .= html_writer::start_div('container page__container');
        $html .= html_writer::start_tag('ul', array('class' => 'nav navbar-nav flex align-items-sm-center'));
        $html .= html_writer::start_tag('li', array('class' => 'nav-item navbar-list__item'));
        $html .= html_writer::start_div('media align-items-center');
        $html .= html_writer::start_span('media-left mr-16pt');
        $html .= html_writer::tag('img', '', array('src' => '/theme/anave/layout/images/completed.png', 'width' => '40', 'alt' => 'check', 'class' => 'rounded-circle'));
        $html .= html_writer::end_span();
        $html .= html_writer::start_div('media-body');
        $html .= html_writer::tag('h3', get_string('alreadywatched', 'mod_video'), array('class' => 'card-title text-white m-0'));
        $html .= html_writer::tag('p', get_string('completedat', 'mod_video') . ' ' . date(get_string('datetimeformat', 'mod_video'), $completiontime), array('class' => 'text-white-50 lh-1 mb-0'));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_tag('li');
        $html .= html_writer::end_tag('ul');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
    }
    return $html;
}

function video_transform_past_date($datetime) {
    $elapsed = time() - $datetime;
    if (floor($elapsed / 86400) > 0) {
        $days = floor($elapsed / 86400);
        return $days . ' ' . ($days == 1 ? get_string('dayago', 'mod_video') : get_string('daysago', 'mod_video'));
    }
    if (floor($elapsed / 3600) > 0) {
        $hours = floor($elapsed / 3600);
        return $hours . ' ' . ($hours == 1 ? get_string('hourago', 'mod_video') : get_string('hoursago', 'mod_video'));
    }
    if (floor($elapsed / 60) > 0) {
        $minutes = floor($elapsed / 60);
        return $minutes . ' ' . ($minutes == 1 ? get_string('minuteago', 'mod_video') : get_string('minutesago', 'mod_video'));
    }
    return $elapsed . ' ' . ($elapsed == 1 ? get_string('secondago', 'mod_video') : get_string('secondsago', 'mod_video'));
}

/**
 * Generate HTML code to comments section
 * 
 * @param object $cm Video course module
 * @return string HTML representing comments section
 */
function video_display_comments_section($cm) {
    global $USER, $PAGE, $DB;

    $html = '';

    $video = $DB->get_record('video', array('id' => $cm->instance));
    if (!$video || empty($video->socialforumid)) {
        return $html;
    }
    if ($video->socialforumid) {
        $socialforum = video_get_socialforum($video->socialforumid);
        $userpicture = new user_picture($USER);
        $html .= html_writer::start_div('col-md-8');
        $html .= html_writer::start_div('d-flex align-items-center mb-32pt');
        $html .= html_writer::tag('h4', get_string('comments'), array('class' => 'm-0'));
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('mb-32pt');
        $html .= html_writer::start_div('d-flex mb-4');
        $userinitials = strtoupper(substr($USER->firstname, 0, 1) . substr($USER->lastname, 0, 1));
        $html .= html_writer::tag('img', '', array('src' => $userpicture->get_url($PAGE), 'alt' => $userinitials, 'class' => 'avatar-img avatar-sm rounded-circle', 'style' => 'margin-right: .5rem;'));
        $html .= html_writer::start_tag('form', array(
                    'id' => 'newcomment',
                    'autocomplete' => 'off',
                    'method' => 'post',
                    'accept-charset' => 'utf-8',
                    'class' => 'mform',
                    'style' => 'width: 100%;',
        ));
        $html .= html_writer::empty_tag('input', array(
                    'type' => 'hidden',
                    'name' => 'sesskey',
                    'value' => sesskey(),
        ));
        $html .= html_writer::start_div('flex search-form form-control-appended navbar-search', array('style' => 'width: 100%; background-color: #272c33; border-color: #272c33;'));
        $html .= html_writer::tag('textarea', '', array(
                    'class' => 'form-control',
                    'name' => 'post',
                    'id' => 'post',
                    'rows' => '2',
                    'style' => 'color: #fff;',
                    'placeholder' => get_string('commenthere', 'mod_video'),
        ));
        $html .= html_writer::start_tag('button', array(
                    'class' => 'btn btn-accent submit-btn',
                    'style' => 'border: none !important;',
        ));
        $html .= html_writer::tag('i', 'send', array('class' => 'material-icons', 'style' => 'color: #6E0AE8; font-size: x-large;'));
        $html .= html_writer::end_tag('button');
        $html .= html_writer::end_div();
        $html .= html_writer::end_tag('form');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('list-group list-group-flush');

        $discussion = video_get_discussion($cm);
        if (!$discussion) {
            $comments = array();
        } else {
            $comments = video_get_posts($socialforum, $discussion->id);
        }
        $MAXCOMMENTS = 5;
        $commentscount = 0;
        foreach ($comments as $comment) {
            if ($commentscount < $MAXCOMMENTS) {
                $html .= html_writer::start_div('list-group-item p-3', array('style' => 'border: none;'));
                $html .= html_writer::start_div('row align-items-start');
                $html .= html_writer::start_div('mb-8pt mb-md-0');
                $html .= html_writer::start_div('media align-items-center');
                $html .= html_writer::start_div('media-left mr-12pt');
                $commentuser = core_user::get_user($comment->userid);
                $commentuserpicture = new user_picture($commentuser);
                $commentuserpictureurl = $commentuserpicture->get_url($PAGE);
                $commentuserinitials = strtoupper(substr($commentuser->firstname, 0, 1) . substr($commentuser->lastname, 0, 1));
                $html .= html_writer::tag('img', '', array('src' => $commentuserpictureurl, 'alt' => $commentuserinitials, 'class' => 'avatar-img avatar-sm rounded-circle', 'style' => 'margin-right: .5rem;'));
                $html .= html_writer::end_div();
                $html .= html_writer::start_div('d-flex flex-column media-body media-middle');
                $html .= html_writer::tag('span', s(fullname($commentuser)), array('class' => 'card-title text-white', 'style' => 'min-height: 0 !important; font-size: math;'));
                $commentdatetime = video_transform_past_date($comment->created);
                $html .= html_writer::tag('small', $commentdatetime, array('class' => 'text-muted text-white-50'));
                $html .= html_writer::end_div();
                $html .= html_writer::end_div();
                $html .= html_writer::end_div();
                $html .= html_writer::start_div('col mb-8pt mb-md-0');
                $html .= html_writer::start_tag('p', array('class' => 'mb-8pt'));
                $html .= html_writer::start_tag('span', array('class' => 'text-body'));
                $commenttext = strip_tags($comment->message);
                $html .= html_writer::tag('strong', $commenttext);
                $html .= html_writer::end_tag('span');
                $html .= html_writer::end_tag('p');
                $html .= html_writer::end_div();
                $html .= html_writer::start_div('col-auto d-flex flex-column align-items-center justify-content-center');
                $html .= html_writer::end_div();
                $html .= html_writer::end_div();
                $html .= html_writer::end_div();
            }
            ++$commentscount;
        }
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        if (count($comments) > $MAXCOMMENTS) {
            $forumurl = new moodle_url('/mod/socialforum/discuss.php', array(
                'd' => $discussion->id,
            ));
            $html .= html_writer::tag('a', get_string('seeallcomments', 'mod_video'), array('href' => $forumurl, 'class' => 'btn btn-accent'));
        }
        $html .= html_writer::end_div();
    }
    return $html;
}

/**
 * Get all posts in a forum discussion but top most and second level ones
 * 
 * @param stdClass $socialforum Forum to which this discussion belongs
 * @param int $discussionid Id of discussion from which get posts
 * @return array of object Posts in discussion
 */
function video_get_posts($socialforum, $discussionid) {
    global $DB;

    $sql = 'SELECT * '
            . 'FROM {socialforum_posts} '
            . 'WHERE discussion = :discussionid1 '
            . 'AND parent <> 0 '
            . 'AND parent IN ('
            . '     SELECT id '
            . '         FROM {socialforum_posts} '
            . '         WHERE discussion = :discussionid2 '
            . '         AND parent = 0 '
            . ') '
            . 'ORDER BY created DESC';
    $posts = $DB->get_records_sql($sql, array('discussionid1' => $discussionid, 'discussionid2' => $discussionid));

    return $posts;
}

/**
 * Get all answers to a post in a forum discussion but top most and second level ones
 * 
 * @param stdClass $socialforum Forum to which this discussion belongs
 * @param int $postid Id of post from which get answers
 * @return array of object Answers to the post
 */
function video_get_answers($postid) {
    global $DB;

    $sql = 'SELECT * '
            . 'FROM {socialforum_posts} '
            . 'WHERE parent = :postid '
            . 'ORDER BY created DESC';
    $posts = $DB->get_records_sql($sql, array('postid' => $postid));

    return $posts;
}

/**
 * Get discussion associated with this video
 * 
 * @param object $cm
 * @return int Discussion id or null if no discussion exists
 */
function video_get_discussion($cm) {
    global $DB;

    $video = $DB->get_record('video', array('id' => $cm->instance));
    $sfid = $video->socialforumid;
    $discussion = $DB->get_record('socialforum_discussions', array('socialforum' => $sfid, 'name' => $video->name));
    return $discussion;
}

/**
 * Add a post to video discussion. Create discussion if needed
 * 
 * @param string $post Post text
 * @param object $video Video to post about
 * @param object $cm Video course module
 */
function video_add_new_post($post, $video, $cm) {
    global $USER;

    $discussion = video_get_discussion($cm);
    if (!$discussion) {
        // Create video discussion
        $newdiscussion = new stdClass();
        $newdiscussion->socialforum = $video->socialforumid;
        $newdiscussion->course = $video->course;
        $newdiscussion->name = $video->name;
        $newdiscussion->message = '<p>' . $video->name . '</p>';
        $newdiscussion->messageformat = FORMAT_HTML;
        $newdiscussion->messagetrust = false;
        $newdiscussion->mailnow = false;
        $discussionid = socialforum_add_discussion($newdiscussion);
        $discussion = socialforum_get_discussion($discussionid);
    }

    // Add post
    $postrec = new stdClass();
    $postrec->message = '<p>' . $post . '</p>';
    $postrec->messageformat = FORMAT_HTML;
    $postrec->subject = get_string('commentfrom', 'mod_video') . ' ' . $USER->firstname . ' ' . $USER->lastname;
    $postrec->discussion = $discussion->id;
    $postrec->socialforum = $discussion->socialforum;
    $postrec->parent = $discussion->firstpost;
    $postrec->itemid = file_get_unused_draft_itemid();
    $postid = socialforum_add_new_post($postrec, null);

    $socialforum = video_get_socialforum($video->socialforumid);
    $modcontext = context_module::instance($cm->id);
    // Register event
    $params = array(
        'context' => $modcontext,
        'objectid' => $postid,
        'other' => array(
            'discussionid' => $discussion->id,
            'socialforumid' => $socialforum->id,
            'socialforumtype' => $socialforum->type,
        )
    );
    $event = \mod_socialforum\event\post_created::create($params);
    $event->trigger();
}

/**
 * Internal function - create click to open text with link.
 */
function video_get_clicktodownload($file, $revision) {
    global $OUTPUT, $CFG;

    $html = '';
    $filename = $file->get_filename();
    $path = '/' . $file->get_contextid() . '/mod_video/' . $file->get_filearea() . '/' . $revision . $file->get_filepath() . $file->get_filename();
    $fileurl = file_encode_url($CFG->wwwroot . '/pluginfile.php', $path, true);
    $html .= html_writer::start_tag('a', array(
                'href' => $fileurl,
                'class' => 'd-flex align-items-center border-1 rounded mb-2 p-8pt',
    ));
    $html .= html_writer::start_tag('span', array(
                'class' => 'mr-8pt p-0pt bg-dark rounded',
                'style' => 'width: 40px; height: 40px;',
    ));
    $iconurl = $OUTPUT->image_url(file_file_icon($file, 256));
    $html .= html_writer::empty_tag('img', array(
                'src' => $iconurl,
                'alt' => 'Avatar',
                'class' => 'avatar-img',
    ));
    $html .= html_writer::end_tag('span');
    $html .= html_writer::start_tag('span', array(
                'class' => 'flex d-flex flex-column',
    ));
    $html .= html_writer::tag('span', $filename, array(
                'class' => 'filename',
    ));
    $filesize = round($file->get_filesize() / 1048576, 1) . ' MB';
    $html .= html_writer::tag('span', $filesize, array(
                'class' => 'filesize lh-1',
    ));
    $html .= html_writer::end_tag('span');
    $html .= html_writer::tag('span', get_string('download', 'mod_video'), array(
                'class' => 'download text-underline mr-8pt',
    ));
    $html .= html_writer::end_tag('a');

    return $html;
}

/**
 * Renders an HTML representing competion mark
 * 
 * @return string HTML rendering completion mark
 */
function video_get_completion_mark($course, $cm, $userid) {
    global $CFG, $OUTPUT;

    $style = '';
    if (!video_is_complete($course, $cm, $userid)) {
        $style = 'display: none;';
    }

    if ($CFG->version < '2017051500') {
        $attributes = array(
            'src' => $OUTPUT->pix_url('i/grade_correct'),
            'alt' => '',
            'class' => 'completionmark',
            'id' => 'completionmark',
            'style' => $style,
        );
    } else {
        $attributes = array(
            'src' => $OUTPUT->image_url('i/grade_correct'),
            'alt' => '',
            'class' => 'completionmark',
            'id' => 'completionmark',
            'style' => $style,
        );
    }
    return html_writer::empty_tag('img', $attributes);
}

/**
 * Mark video as completed for the current (or given) user.
 *
 * Works for manual and automatic completion tracking. For automatic view-based
 * completion, records the view before updating the overall state.
 *
 * @param stdClass $course course object
 * @param stdClass|cm_info $cm course module object
 * @param int $userid user id (0 = current user)
 * @return bool true if completion was updated or already complete
 */
function video_set_completed($course, $cm, $userid = 0) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $completion = new completion_info($course);
    if (!$completion->is_enabled($cm)) {
        return false;
    }

    if ($cm->completion == COMPLETION_TRACKING_MANUAL) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
        return video_is_complete($course, $cm, $userid);
    }

    if ($cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return false;
    }

    // Use core API so course_modules_viewed and completion state stay in sync.
    if ($cm->completionview == COMPLETION_VIEW_REQUIRED) {
        $completion->set_module_viewed($cm, $userid);
    } else {
        $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
    }

    return video_is_complete($course, $cm, $userid);
}

/**
 * Check is a video is complete
 */
function video_is_complete($course, $cm, $userid) {
    $completion = new completion_info($course);
    $current = $completion->get_data($cm, false, $userid);
    return $current && in_array((int) $current->completionstate, [
        COMPLETION_COMPLETE,
        COMPLETION_COMPLETE_PASS,
    ], true);
}

/**
 * Whether automatic completion is recorded when the student opens the video page.
 *
 * @param stdClass $video Video instance
 * @return bool
 */
function video_complete_on_view(stdClass $video): bool {
    return in_array((int) $video->contentformat, [FORMAT_YOUTUBE, FORMAT_EMBEDED], true);
}

/**
 * Mark automatic view completion on page open (YouTube and embed URL videos).
 *
 * @param stdClass $video Video instance
 * @param stdClass $course Course
 * @param stdClass|cm_info $cm Course module
 * @param int $userid User id (0 = current user)
 */
function video_mark_view_completion_on_open($video, $course, $cm, $userid = 0): void {
    global $USER;

    if (!$userid) {
        $userid = $USER->id;
    }
    if (!video_complete_on_view($video)) {
        return;
    }

    if ($cm instanceof cm_info) {
        $cminfo = $cm;
    } else {
        $cminfo = get_fast_modinfo($course)->get_cm($cm->id);
    }

    $completioninfo = new completion_info($course);
    if ($completioninfo->is_enabled($cminfo) == COMPLETION_TRACKING_AUTOMATIC
            && !video_is_complete($course, $cminfo, $userid)) {
        video_set_completed($course, $cminfo, $userid);
    }
}

/**
 * Get a social forum record
 * 
 * @param int $socialforumid Social forum id
 */
function video_get_socialforum($socialforumid) {
    global $DB;
    return $DB->get_record('socialforum', array('id' => $socialforumid));
}

/**
 * Return HTML for navigation bar
 *
 * @param stdClass $section
 * @param stdClass $cm
 * @return string HTML
 */
function video_print_section_navigation_bar($section, $cm) {
    global $COURSE, $PAGE;

    $modinfo = get_fast_modinfo($COURSE);
    if (is_object($section)) {
        $section = $modinfo->get_section_info($section->section);
    } else {
        $section = $modinfo->get_section_info($section);
    }
    $html = '';
    $html .= html_writer::start_tag('nav', array(
                'class' => 'course-nav',
    ));
    if (!empty($modinfo->sections[$section->section])) {
        foreach ($modinfo->sections[$section->section] as $modnumber) {
            $mod = $modinfo->cms[$modnumber];
            if ($mod->available || $PAGE->user_allowed_editing()) {
                $html .= html_writer::start_tag('a', array(
                            'data-toggle' => 'tooltip',
                            'data-placement' => 'bottom',
                            'data-title' => $mod->name,
                            'href' => $mod->url,
                            'class' => 'iconplaceholder' .
                            ($cm->id == $mod->id ? ' selected' : ''),
                            'data-original-title' => '',
                            'title' => $mod->name,
                ));
                $completioninfo = new completion_info($mod->get_course());
                $completion = $completioninfo->is_enabled($mod);
                if ($completion == COMPLETION_TRACKING_NONE) {
                    $html .= html_writer::empty_tag('img', array(
                                'class' => 'cm-icon',
                                'src' => $mod->get_icon_url()
                    ));
                } else {
                    $completiondata = $completioninfo->get_data($mod, true);
                    switch ($completiondata->completionstate) {
                        case COMPLETION_COMPLETE:
                        case COMPLETION_COMPLETE_PASS:
                            $html .= html_writer::tag('span', 'check_circle', array(
                                        'class' => 'material-icons text-success',
                            ));
                            break;
                        default:
                            $html .= html_writer::empty_tag('img', array(
                                        'class' => 'cm-icon',
                                        'src' => $mod->get_icon_url()
                            ));
                    }
                }
                $html .= html_writer::end_tag('a');
            } else {
                $html .= html_writer::start_tag('span', array(
                            'class' => 'iconplaceholder' .
                            ($cm->id == $mod->id ? ' selected' : ''),
                            'data-toggle' => 'tooltip',
                            'data-placement' => 'bottom',
                            'data-title' => $mod->name,
                            'title' => $mod->name,
                ));
                $html .= html_writer::empty_tag('img', array(
                            'class' => 'cm-icon unavailable',
                            'src' => $mod->get_icon_url(),
                ));
                $html .= html_writer::end_tag('span');
            }
        }
    }
    $html .= html_writer::end_tag('nav');
    return $html;
}

function video_print_video_content($video, $cm) {
    $html = '';
    $html .= html_writer::start_div('mb-32pt', array(
                'id' => 'videoplayer'
    ));
    $html .= video_print_player($cm);
    $html .= html_writer::end_div();
    return $html;
}

/**
 * Return HTML for video data
 * 
 * @param stdClass $cm
 * @return string
 */
function video_print_video_data($video, $cm) {
    $html = '';
    $namedata = video_split_module_name($cm->name);
    $html .= html_writer::start_div('d-flex flex-wrap align-items-end mb-16pt', array(
                'id' => 'videodata'
    ));
    $html .= html_writer::tag('h1', $namedata['title'], array(
                'class' => 'text-white flex m-0',
                'id' => 'videotitle'
    ));
    if (!empty($namedata['duration'])) {
        $html .= html_writer::start_tag('h1', array(
                    'class' => 'text-white-50 font-weight-light m-0 duration',
                    'id' => 'videoduration'
        ));
        $html .= $namedata['duration'];
        $html .= html_writer::end_tag('h1');
    }
    $html .= html_writer::end_div();
    if (!empty($video->intro)) {
        $videodescription = strip_tags($video->intro);
        $html .= html_writer::tag('p', $videodescription, array(
                    'class' => 'hero__lead measure-hero-lead text-white-50 mb-24pt',
                    'id' => 'videodescription'
        ));
    }

    return $html;
}

/**
 * Get the section of a course module
 * 
 * @param stdClass $cm
 * @return stdClass section
 */
function video_get_course_module_section($cm) {
    global $DB;
    return $DB->get_record('course_sections', array('id' => $cm->section));
}

/**
 * Split course module name extrating title and duration
 * Duration needs to be in format (MM:SS) at the
 * end of course module name
 * 
 * @param string $modulename
 * @return associative array with keys title and duration
 */
function video_split_module_name($modulename) {
    $posstart = strpos($modulename, '(');
    $namedata = array();
    if (!$posstart) {
        $namedata['title'] = $modulename;
        $namedata['duration'] = '';
    } else {
        $namedata['title'] = substr($modulename, 0, $posstart);
        $start = $posstart + 1;
        $posend = strpos($modulename, ')');
        $end = $posend ? $posend - 1 : strlen($modulename) - 1;
        $durationstr = substr($modulename, $start, $end - $start + 1);
        $namedata['duration'] = $durationstr;
    }
    return $namedata;
}

function video_get_section_name($cmid, $courseid) {
    global $DB;

    $coursesections = $DB->get_records('course_sections', array(
        'course' => $courseid
    ));
    foreach ($coursesections as $coursesection) {
        $sectioncms = explode(',', $coursesection->sequence);
        if (in_array($cmid, $sectioncms)) {
            return $coursesection->name;
        }
    }
    return '';
}

function video_print_video_details($video, $cm) {
    global $OUTPUT;
    echo '<!-- Video details BEGIN -->';
    echo html_writer::start_div('navbar navbar-expand-sm navbar-light bg-primary200 border-bottom-0 navbar-list pb-32pt pt-32pt m-0 align-items-center', array(
        'id' => 'videodetails',
        'style' => 'background-color: rgb(205, 254, 253);'
    ));
    echo html_writer::start_div('narrow-page container page__container', array(
        'style' => 'width: 100%; padding-bottom: 0;'
    ));
    echo html_writer::start_div('container page__container', array(
        'style' => 'width: 100%; padding-bottom: 0;'
    ));
    echo html_writer::start_tag('ul', array(
        'class' => 'nav navbar-nav flex align-items-sm-center',
        'style' => 'justify-content: space-between;'
    ));
    echo html_writer::start_tag('li', array(
        'class' => 'nav-item navbar-list__item'
    ));
    echo html_writer::start_div('media align-items-center');
    echo html_writer::start_span('media-left mr-16pt');
    echo html_writer::tag('img', '', array(
        'src' => $OUTPUT->image_url('speach-icon', 'mod_video')->out(false),
        'width' => '40',
        'alt' => 'speachlogo',
        'class' => ''
    ));
    echo html_writer::end_span();
    echo html_writer::start_div('media-body');
    $course = get_course($video->course);
    $coursename = $course->fullname;
    $videocoursetxt = get_string('videocourse', 'mod_video', $coursename);
    $videocourseurl = new moodle_url('/course/view.php', array(
        'id' => $course->id,
    ));
    echo html_writer::tag('a', $videocoursetxt, array(
        'class' => 'card-title m-0',
        'href' => $videocourseurl,
        'style' => 'min-height: 0 !important; line-height: 1.6;'
    ));
    $sectionname = video_get_section_name($cm->id, $course->id);
    if (!empty($sectionname)) {
        $sectionnametxt = get_string('videosection', 'mod_video', $sectionname);
        echo html_writer::tag('p', $sectionnametxt, array(
            'class' => 'text-50 lh-1 mb-0'
        ));
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_tag('li');
    $namedata = video_split_module_name($cm->name);
    $duration = $namedata['duration'];
    if (!empty($duration)) {
        echo html_writer::start_tag('li', array(
            'class' => 'nav-item navbar-list__item ml-5',
            'style' => 'min-height: 66px;'
        ));
        echo html_writer::start_tag('i', array(
            'class' => 'icon fa fa-clock-o text-primary500',
            'style' => 'font-size: 1.5rem; margin-right: 0.8rem;'
        ));
        echo html_writer::end_tag('i');
        echo html_writer::start_div('media-body', array(
            'style' => 'margin-top: 6px;'
        ));
        $durationtxt = get_string('duration', 'mod_video');
        echo html_writer::tag('p', $durationtxt, array(
            'class' => 'card-title mb-0',
            'style' => 'font-family: Typo-Round-Bold\ 2,Helvetica Neue,Arial,sans-serif; font-weight: 500;'
        ));
        echo html_writer::tag('strong', $duration, array(
            'class' => 'text-50 lh-1'
        ));
        echo html_writer::end_div();
    }
    echo html_writer::end_tag('li');
    echo html_writer::start_tag('li', array(
        'class' => 'nav-item navbar-list__item ml-5',
        'style' => 'min-height: 66px;'
    ));
    echo html_writer::start_tag('i', array(
        'class' => 'icon fa fa-calendar text-primary500',
        'style' => 'font-size: 1.5rem; margin-right: 1rem;'
    ));
    echo html_writer::end_tag('i');
    echo html_writer::start_div('media-body', array(
        'style' => 'margin-top: 6px;'
    ));
    $postdatetxt = get_string('postdate', 'mod_video');
    echo html_writer::tag('p', $postdatetxt, array(
        'class' => 'card-title mb-0',
        'style' => 'font-family: Typo-Round-Bold\ 2,Helvetica Neue,Arial,sans-serif; font-weight: 500;'
    ));
    $postdate = date(get_string('dateformat', 'mod_video'), $cm->added);
    echo html_writer::tag('strong', $postdate, array(
        'class' => 'text-50 lh-1'
    ));
    echo html_writer::end_div();
    echo html_writer::end_tag('li');
    echo html_writer::start_tag('li', array(
        'class' => 'nav-item navbar-list__item ml-5',
        'style' => 'min-height: 66px;'
    ));
    echo html_writer::start_tag('a', array(
        'class' => 'btn btn-clear btn-rounded mb-16pt mb-sm-0 mr-sm-16pt',
        'href' => 'javascript:void(0);',
        'onclick' => 'window.history.back();'
    ));
    echo html_writer::start_tag('i', array(
        'class' => 'icon fa fa-chevron-left'
    ));
    echo html_writer::end_tag('i');
    echo get_string('back');
    echo html_writer::end_tag('a');
    echo html_writer::end_tag('li');
    echo html_writer::end_tag('ul');
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo '<!-- Video details END -->';
}

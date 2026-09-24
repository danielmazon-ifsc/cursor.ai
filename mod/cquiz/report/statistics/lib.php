<?php

/**
 * Standard plugin entry points of the cquiz statistics report.
 *
 * @package   cquiz_statistics
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Serve questiontext files in the question text when they are displayed in this report.
 *
 * @package  cquiz_statistics
 * @category files
 * @param context $previewcontext the cquiz context
 * @param int $questionid the question id.
 * @param context $filecontext the file (question) context
 * @param string $filecomponent the component the file belongs to.
 * @param string $filearea the file area.
 * @param array $args remaining file args.
 * @param bool $forcedownload.
 * @param array $options additional options affecting the file serving.
 */
function cquiz_statistics_question_preview_pluginfile($previewcontext, $questionid, $filecontext, $filecomponent, $filearea, $args, $forcedownload, $options = array()) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/cquiz/locallib.php');

    list($context, $course, $cm) = get_context_info_array($previewcontext->id);
    require_login($course, false, $cm);

    // Assume only trusted people can see this report. There is no real way to
    // validate questionid, becuase of the complexity of random quetsions.
    require_capability('cquiz/statistics:view', $context);

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/{$filecontext->id}/{$filecomponent}/{$filearea}/{$relativepath}";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Cquiz statistics report cron code. Deletes cached data more than a certain age.
 */
function cquiz_statistics_cron() {
    global $DB;

    mtrace("\n  Cleaning up old cquiz statistics cache records...", '');

    $expiretime = time() - 5 * HOURSECS;
    $DB->delete_records_select('cquiz_statistics', 'timemodified < ?', array($expiretime));

    return true;
}

<?php

/**
 * Video module upgrade code
 *
 * This file keeps track of upgrades to
 * the resource module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package   mod_video
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/video/lib.php');

function video_get_coursemodule($video) {
    global $DB;

    $module = $DB->get_record('modules', array('name' => 'video'));
    $sql = 'select * from {course_modules} cm '
            . 'where cm.course = :courseid '
            . 'and cm.module = :moduleid '
            . 'and cm.instance = :videoid';
    $records = $DB->get_records_sql($sql, array('courseid' => $video->course,
        'moduleid' => $module->id, 'videoid' => $video->id));
    return reset($records);
}

function video_update_pathnamehashs($video) {
    global $DB;

    $cm = video_get_coursemodule($video);
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_video', 'download', 0, 'sortorder DESC, id ASC', false);
    foreach ($files as $fileinfo) {
        $filerecord = $DB->get_record('files', array(
            'id' => $fileinfo->get_id()));
        $filerecord->pathnamehash = $fs->get_pathname_hash(
                $fileinfo->get_contextid(), $fileinfo->get_component(), $fileinfo->get_filearea(), $fileinfo->get_itemid(), $fileinfo->get_filepath(), $fileinfo->get_filename());
        $DB->update_record('files', $filerecord);
    }
}

function xmldb_video_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2017081001) {

        $dbman = $DB->get_manager();

        // Define field socialforumid to be added to video.
        $table = new xmldb_table('video');
        $field = new xmldb_field('socialforumid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'timemodified');

        // Conditionally launch add field socialforumid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Video savepoint reached.
        upgrade_mod_savepoint(true, 2017081001, 'video');
    }

    if ($oldversion < 2017081003) {

        // Set video socialforum ids
        $videos = $DB->get_records('video');
        foreach ($videos as $video) {
            $socialforums = $DB->get_records('socialforum', array('course' => $video->course));
            $socialforum = reset($socialforums);
            if ($socialforum) {
                $video->socialforumid = $socialforum->id;
                $DB->update_record('video', $video);
            }
        }

        // Video savepoint reached.
        upgrade_mod_savepoint(true, 2017081003, 'video');
    }

    if ($oldversion < 2018022001) {

        $dbman = $DB->get_manager();

        // Define field thumbnailurl to be added to video.
        $table = new xmldb_table('video');
        $field = new xmldb_field('thumbnailurl', XMLDB_TYPE_CHAR, '256', null, null, null, null, 'socialforumid');

        // Conditionally launch add field thumbnailurl.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Set all video thumbnails
        $videos = $DB->get_records('video', array());
        foreach ($videos as $video) {
            $set = video_set_thumbnail_url($video);
            $DB->update_record('video', $set);
        }

        // Video savepoint reached.
        upgrade_mod_savepoint(true, 2018022001, 'video');
    }

    if ($oldversion < 2020063001) {
        // Change video content and video format
        $videos = $DB->get_records('video');
        foreach ($videos as $video) {
            if (strpos($video->content, 'vimeo')) {
                $possrc = strpos($video->content, 'src="');
                $posnextquote = strpos($video->content, '"', $possrc + 5);
                $link = substr($video->content, $possrc + 5, $posnextquote - $possrc - 5);
                $pieces = explode("/", $link);
                $id = $pieces[sizeof($pieces) - 1];
                $video->intro .= strip_tags($video->content);
                $video->content = $id;
                $video->contentformat = FORMAT_VIMEO;
            } elseif (strpos($video->content, 'youtube')) {
                $possrc = strpos($video->content, 'src="');
                $posnextquote = strpos($video->content, '"', $possrc + 5);
                $link = substr($video->content, $possrc + 5, $posnextquote - $possrc - 5);
                $pieces = explode("/", $link);
                $id = $pieces[sizeof($pieces) - 1];
                $video->intro .= strip_tags($video->content);
                $video->content = $id;
                $video->contentformat = FORMAT_YOUTUBE;
            } elseif (strpos($video->content, 'http')) {
                $possrc = strpos($video->content, 'src="');
                $posnextquote = strpos($video->content, '"', $possrc + 5);
                $link = substr($video->content, $possrc + 5, $posnextquote - $possrc - 5);
                $video->intro .= strip_tags($video->content);
                $video->content = $link;
                $video->contentformat = FORMAT_EMBEDED;
            }
            $DB->update_record('video', $video);
            // Video savepoint reached.
        }
        upgrade_mod_savepoint(true, 2020063001, 'video');
    }

    if ($oldversion < 2020071604) {
        // Fix pathnamehash for video download files 
        $videos = $DB->get_records('video');
        foreach ($videos as $video) {
            video_update_pathnamehashs($video);
        }
        upgrade_mod_savepoint(true, 2020071604, 'video');
    }

    if ($oldversion < 2022052701) {
        // Set secondstocomplete configuration to 0 
        set_config('secondstocomplete', 0, 'video');
        upgrade_mod_savepoint(true, 2022052701, 'video');
    }

    if ($oldversion < 2023011201) {

        $dbman = $DB->get_manager();

        // Define field thumbnailurl to be dropped from video.
        $table = new xmldb_table('video');
        $field = new xmldb_field('thumbnailurl');

        // Conditionally launch drop field thumbnailurl.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Video savepoint reached.
        upgrade_mod_savepoint(true, 2023011201, 'video');
    }

    if ($oldversion < 2024040401) {

        $dbman = $DB->get_manager();

        // Define field thumbnailurl to be added to video.
        $table = new xmldb_table('video');
        $field = new xmldb_field('thumbnailurl', XMLDB_TYPE_CHAR, '256', null, null, null, null, 'socialforumid');

        // Conditionally launch add field thumbnailurl.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Set all video thumbnails
        $videos = $DB->get_records('video', array());
        foreach ($videos as $video) {
            $set = video_set_thumbnail_url($video);
            $DB->update_record('video', $set);
        }

        // Video savepoint reached.
        upgrade_mod_savepoint(true, 2024040401, 'video');
    }

    if ($oldversion < 2025052301) {
        // Automatic video completion requires "student must view" on existing activities.
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'video']);
        if ($moduleid) {
            $DB->set_field_select(
                'course_modules',
                'completionview',
                COMPLETION_VIEW_REQUIRED,
                'module = :moduleid AND completion = :autotracking AND completionview = 0',
                [
                    'moduleid' => $moduleid,
                    'autotracking' => COMPLETION_TRACKING_AUTOMATIC,
                ]
            );
        }

        upgrade_mod_savepoint(true, 2025052301, 'video');
    }

    if ($oldversion < 2025052302) {
        // Users enrolled via manual/self enrol without a course role cannot be tracked for completion UI.
        $sql = "SELECT ue.userid, e.courseid, e.roleid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.status = :active
                   AND e.roleid > 0
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {role_assignments} ra
                         JOIN {context} ctx ON ctx.id = ra.contextid
                        WHERE ra.userid = ue.userid
                          AND ctx.contextlevel = :courselevel
                          AND ctx.instanceid = e.courseid
                   )";

        $broken = $DB->get_records_sql($sql, [
            'active' => ENROL_USER_ACTIVE,
            'courselevel' => CONTEXT_COURSE,
        ]);

        foreach ($broken as $record) {
            $context = context_course::instance($record->courseid);
            role_assign($record->roleid, $record->userid, $context->id);
        }

        upgrade_mod_savepoint(true, 2025052302, 'video');
    }

    if ($oldversion < 2025052305) {
        $format = get_config('video', 'youtubeformat');
        if (!empty($format) && strpos($format, 'enablejsapi=1') === false) {
            $format = str_replace('?ecver=1', '?enablejsapi=1', $format);
            if (strpos($format, 'enablejsapi=1') === false) {
                $format = str_replace('{$a}"', '{$a}?enablejsapi=1"', $format);
            }
            set_config('youtubeformat', $format, 'video');
        }

        upgrade_mod_savepoint(true, 2025052305, 'video');
    }

    return true;
}

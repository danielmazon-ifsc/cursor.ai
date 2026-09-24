<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Main class for the Sala de Conferências course format.
 *
 * @package    format_saladeconferencias
 * @copyright  2025 Viddia (http://viddia.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/topics/lib.php');

/**
 * Sala de Conferências format — extends topics for section behaviour and course options.
 *
 * @package    format_saladeconferencias
 * @copyright  2025 Viddia (http://viddia.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_saladeconferencias extends format_topics {

    /** @var int Video is available to the user. */
    const AVAILABLE = 0;

    /** @var int Video is not yet available to the user. */
    const UNAVAILABLE = 1;

    /** @var int No availability filter. */
    const BOTH = 2;

    /**
     * Whether a video may be played or completed on the Sala de Conferências page.
     *
     * Activities in the Hidden (Oculto) state are shown to every enrolled student as
     * a non-playable "coming soon" card and must not award completion.
     *
     * @param cm_info $mod
     * @return bool
     */
    public static function is_video_playable(cm_info $mod): bool {
        if ($mod->modname !== 'video') {
            return false;
        }
        if (empty($mod->visible)) {
            return false;
        }
        if (!$mod->available || !$mod->uservisible) {
            return false;
        }
        return true;
    }

    /**
     * Placeholder poster for videos in the Hidden (Oculto) state.
     *
     * @return string Plugin-relative image URL
     */
    public static function get_hidden_video_poster_url(): string {
        global $CFG;

        $pluginmanager = \core_plugin_manager::instance();
        $plugininfo = $pluginmanager->get_plugin_info('format_saladeconferencias');
        $rev = $plugininfo ? (int) $plugininfo->versiondb : 0;

        return (new \moodle_url('/course/format/saladeconferencias/pix/em_breve.png', ['v' => $rev]))->out(false);
    }

    /**
     * True when the activity is a video in Moodle's Hidden (Oculto) state.
     *
     * @param cm_info $mod
     * @return bool
     */
    public static function is_video_coming_soon(cm_info $mod): bool {
        return $mod->modname === 'video' && empty($mod->visible);
    }

    /**
     * Whether a video card should appear in the section grid for the current user.
     *
     * @param cm_info $mod
     * @param bool $editing
     * @return bool
     */
    public static function should_show_video_card(cm_info $mod, bool $editing): bool {
        if ($mod->modname !== 'video' || !empty($mod->deletioninprogress)) {
            return false;
        }
        if ($editing) {
            return true;
        }
        if (self::is_video_coming_soon($mod)) {
            if (!isloggedin() || isguestuser()) {
                return false;
            }
            return is_enrolled(\context_course::instance($mod->course), null, '', true);
        }
        if (!$mod->available && !is_siteadmin()) {
            return false;
        }
        return true;
    }

    /**
     * All video modules in a section, including Hidden (Oculto) ones.
     *
     * @param stdClass $course
     * @param int $sectionnum
     * @return array<int, cm_info> cmid => cm_info
     */
    public static function get_section_video_modules(stdClass $course, int $sectionnum): array {
        global $DB;

        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info($sectionnum);
        if (!$section || $section->section <= 0 || empty($section->sequence)) {
            return [];
        }

        $videomoduleid = (int) $DB->get_field('modules', 'id', ['name' => 'video']);
        if (!$videomoduleid) {
            return [];
        }

        $modules = [];
        foreach (array_filter(array_map('trim', explode(',', $section->sequence))) as $cmidraw) {
            $cmid = (int) $cmidraw;
            if ($cmid <= 0 || !isset($modinfo->cms[$cmid])) {
                continue;
            }
            $mod = $modinfo->cms[$cmid];
            if ((int) $mod->module !== $videomoduleid) {
                continue;
            }
            $modules[$cmid] = $mod;
        }
        return $modules;
    }

    /**
     * Courses that use this format.
     *
     * @return array<int, stdClass>
     */
    public static function get_courses_in_format(): array {
        global $DB;
        return $DB->get_records_sql(
            "SELECT * FROM {course} WHERE format = :format ORDER BY shortname ASC",
            ['format' => 'saladeconferencias']
        );
    }

    /**
     * @param int|stdClass $section
     * @return string
     */
    public function get_section_name($section): string {
        $section = $this->get_section($section);
        if ((string) $section->name !== '') {
            return format_string(
                $section->name,
                true,
                ['context' => context_course::instance($this->courseid)]
            );
        }
        return $this->get_default_section_name($section);
    }

    /**
     * @param stdClass $section
     * @return string
     */
    public function get_default_section_name($section): string {
        if ($section->section == 0) {
            return get_string('section0name', 'format_saladeconferencias');
        }
        return parent::get_default_section_name($section);
    }

    /**
     * Custom renderer is used instead of the topics component output.
     *
     * @return bool
     */
    public function supports_components(): bool {
        return false;
    }

    /**
     * The Sala de Conferências layout has its own banner + featured player and does
     * not benefit from the boost course index. Returning false hides the drawer-left
     * (course index) while preserving the drawer-right used for admin blocks.
     *
     * @return bool
     */
    public function uses_course_index() {
        return false;
    }

    /**
     * Register format CSS/JS before the page header is rendered.
     *
     * @param moodle_page $page
     */
    public function page_set_course(moodle_page $page): void {
        global $CFG, $USER, $OUTPUT;

        parent::page_set_course($page);

        if ((int) $page->course->id !== SITEID) {
            self::ensure_course_block_region($page);
            format_saladeconferencias_ensure_settings_block((int) $page->course->id);
        }

        if (!$page->has_set_url() ||
                !$page->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
            return;
        }

        $pluginmanager = core_plugin_manager::instance();
        $plugininfo = $pluginmanager->get_plugin_info('format_saladeconferencias');
        $pluginrev = $plugininfo ? (int) $plugininfo->versiondb : 0;

        // Avoid core settingsblock init() fatal when "Administração do curso" has no <a>.
        $page->requires->js_amd_inline(
            'require.config({map:{"*":{"block_settings/settingsblock":"format_saladeconferencias/settingsblock"}}});'
        );

        $page->requires->css(new moodle_url(
            '/course/format/saladeconferencias/settingsblock-tree.css',
            ['v' => $pluginrev]
        ));
        $page->requires->js(new moodle_url(
            '/course/format/saladeconferencias/settingsblock-tree.js',
            ['v' => $pluginrev]
        ));

        $page->requires->css('/course/format/saladeconferencias/styles.css');
        $page->requires->js('/course/format/saladeconferencias/format.js');
        $page->requires->js('/course/format/saladeconferencias/completion_refresh.js');
        $page->requires->js('/mod/video/player.js', true);
        $page->requires->js('/mod/video/captions.js', true);
        $page->requires->js('/course/format/saladeconferencias/featured_player.js');

        if (isloggedin() && !isguestuser()) {
            $completionconfig = [
                'courseid' => $page->course->id,
                'userid' => $USER->id,
                'checkiconurl' => $OUTPUT->image_url('i/grade_correct', 'moodle')->out(false),
            ];
            $page->requires->js_init_code(
                'M.format_saladeconferencias.completion_refresh.init(' .
                json_encode($completionconfig) . ');'
            );

            $featuredconfig = [
                'wwwroot' => $CFG->wwwroot,
                'sesskey' => sesskey(),
                'videoparam' => optional_param('video', 0, PARAM_INT),
            ];
            $page->requires->js_init_code(
                'M.format_saladeconferencias.featured_player.init(' .
                json_encode($featuredconfig) . ');'
            );
        }
    }

    /**
     * @param \section_info|\stdClass $section
     * @param bool $linkifneeded
     * @param bool|null $editable
     * @param null|\lang_string|string $edithint
     * @param null|\lang_string|string $editlabel
     * @return \core\output\inplace_editable
     */
    public function inplace_editable_render_section_name(
        $section,
        $linkifneeded = true,
        $editable = null,
        $edithint = null,
        $editlabel = null
    ) {
        if (empty($edithint)) {
            $edithint = new lang_string('editsectionname', 'format_saladeconferencias');
        }
        if (empty($editlabel)) {
            $title = get_section_name($section->course, $section);
            $editlabel = new lang_string('newsectionname', 'format_saladeconferencias', $title);
        }
        return parent::inplace_editable_render_section_name($section, $linkifneeded, $editable, $edithint, $editlabel);
    }

    /**
     * Register side-pre before blocks are loaded (needed for drawer-right / Administração).
     *
     * @param moodle_page $page
     */
    public static function ensure_course_block_region(moodle_page $page): void {
        try {
            $page->blocks->add_region('side-pre', false);
            $page->blocks->set_default_region('side-pre');
        } catch (\Throwable $e) {
            // Regions may already be fixed for this page.
        }
    }

    /**
     * @return array
     */
    public function get_default_blocks(): array {
        return [
            BLOCK_POS_LEFT => [],
            BLOCK_POS_RIGHT => ['settings'],
        ];
    }
}

/**
 * Ensure the settings (Administração) block exists on a Sala de Conferências course.
 *
 * @param int $courseid
 * @return bool true if the block is present (or was just created)
 */
function format_saladeconferencias_ensure_settings_block(int $courseid): bool {
    global $DB, $CFG;

    if ($courseid <= 1) {
        return false;
    }

    require_once($CFG->dirroot . '/lib/blocklib.php');

    $context = context_course::instance($courseid);
    if ($DB->record_exists('block_instances', [
        'parentcontextid' => $context->id,
        'blockname' => 'settings',
        'pagetypepattern' => 'course-view-*',
    ])) {
        return true;
    }

    $page = new moodle_page();
    $page->set_context($context);
    $page->set_url(new moodle_url('/course/view.php', ['id' => $courseid]));
    $page->set_pagelayout('course');
    $page->set_pagetype('course-view-saladeconferencias');
    $page->blocks->add_region('side-pre');
    $page->blocks->set_default_region('side-pre');
    $page->blocks->add_block('settings', 'side-pre', 0, false, 'course-view-*', null);

    return true;
}

/**
 * In-place editable callback for section names.
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable|null
 */
function format_saladeconferencias_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s
               JOIN {course} c ON s.course = c.id
              WHERE s.id = ? AND c.format = ?',
            [$itemid, 'saladeconferencias'],
            MUST_EXIST
        );
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
    return null;
}

/**
 * Register a Sala de Conferências video as mandatory course completion criteria.
 *
 * Progress (local_studypace) and is_cm_monitorable() only count activities listed
 * at /course/completion.php. New videos were not added there automatically, so
 * the dashboard kept 100% after admins published more episodes.
 *
 * @param int $cmid Course module id
 * @return bool True when a new criteria row was inserted
 */
function format_saladeconferencias_ensure_video_completion_criteria(int $cmid): bool {
    global $DB, $CFG;

    if ($cmid <= 0) {
        return false;
    }

    require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');
    require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');

    $cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, course, module, completion, visible, deletioninprogress', IGNORE_MISSING);
    if (!$cm || !empty($cm->deletioninprogress)) {
        return false;
    }

    $modname = $DB->get_field('modules', 'name', ['id' => $cm->module]);
    if ($modname !== 'video') {
        return false;
    }

    $course = $DB->get_record('course', ['id' => $cm->course], 'id, format, enablecompletion', IGNORE_MISSING);
    if (!$course || $course->format !== 'saladeconferencias' || empty($course->enablecompletion)) {
        return false;
    }

    if ((int) $cm->completion === COMPLETION_TRACKING_NONE) {
        return false;
    }

    if ($DB->record_exists('course_completion_criteria', [
        'course' => $course->id,
        'moduleinstance' => $cmid,
        'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
    ])) {
        return false;
    }

    $criteria = new \completion_criteria_activity();
    $criteria->course = (int) $course->id;
    $criteria->module = 'video';
    $criteria->moduleinstance = $cmid;
    $criteria->insert();

    return true;
}

/**
 * Backfill completion criteria for existing Sala videos missing from the course list.
 *
 * @return int Number of criteria rows inserted
 */
function format_saladeconferencias_backfill_video_completion_criteria(): int {
    global $DB;

    // Must not call get_fast_modinfo() here: this backfill runs from db/upgrade.php
    // and Moodle blocks modinfo/cache rebuild during upgrade (cannotexecduringupgrade).
    $added = 0;
    $cms = $DB->get_records_sql(
        'SELECT cm.id
           FROM {course_modules} cm
           JOIN {course} c ON c.id = cm.course
           JOIN {modules} m ON m.id = cm.module
          WHERE c.format = :format
            AND c.id > :siteid
            AND c.enablecompletion = 1
            AND m.name = :modname
            AND cm.completion <> :none
            AND (cm.deletioninprogress = 0 OR cm.deletioninprogress IS NULL)',
        [
            'format' => 'saladeconferencias',
            'siteid' => SITEID,
            'modname' => 'video',
            'none' => COMPLETION_TRACKING_NONE,
        ]
    );

    foreach ($cms as $cm) {
        if (format_saladeconferencias_ensure_video_completion_criteria((int) $cm->id)) {
            $added++;
        }
    }

    return $added;
}

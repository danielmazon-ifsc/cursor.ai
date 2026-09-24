<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Renderer for the Sala de Conferências course format.
 *
 * @package    format_saladeconferencias
 * @copyright  2025 Viddia (http://viddia.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/renderer.php');
require_once($CFG->libdir . '/completionlib.php');

use core_courseformat\output\local\content\cm\controlmenu;
use core_courseformat\output\section_renderer;

/**
 * Renderer for saladeconferencias format.
 *
 * @package    format_saladeconferencias
 * @copyright  2025 Viddia (http://viddia.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_saladeconferencias_renderer extends section_renderer {

    /** @var int|null Featured video course-module id for active card styling. */
    protected $featuredcmid = null;

    /**
     * @param moodle_page $page
     * @param string $target
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    /**
     * @param stdClass $course
     * @param array|null $sections
     * @param array|null $mods
     * @param array|null $modnames
     * @param array|null $modnamesused
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused): void {
        global $PAGE;

        $formattedcourse = course_get_format($course)->get_course();
        $html = '';
        $html .= $this->render_banner($formattedcourse);
        $html .= $this->render_featured_area($formattedcourse);
        $html .= $this->render_sections_area($formattedcourse);
        $html .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'id' => 'sesskey',
            'name' => 'sesskey',
            'value' => sesskey(),
        ]);
        echo $html;
    }

    /**
     * @param stdClass $course
     * @return string
     */
    protected function render_banner(stdClass $course): string {
        global $CFG, $PAGE, $USER, $OUTPUT;

        $summary = format_text(
            $course->summary,
            $course->summaryformat,
            ['context' => context_course::instance($course->id)]
        );
        $iconurl = $OUTPUT->image_url('icon', 'format_saladeconferencias');
        $newcount = count($this->get_unwatched_videos($course));

        $html = '';
        $html .= '<!-- Sala de Conferências banner BEGIN -->';
        $html .= html_writer::start_div('page-section border-bottom-0 bg-primary800', [
            'id' => 'saladeconferencias-banner',
            'style' => 'height: 320px; background-color: rgb(5, 43, 45);',
        ]);
        $html .= html_writer::start_div('narrow-page container page__container h-100 justify-content-center align-items-center');
        $html .= html_writer::start_div('d-flex flex-column flex-lg-row align-items-center h-100');
        $html .= html_writer::start_div('d-flex flex-column flex-md-row align-items-center  flex mb-16pt mb-lg-0 text-center text-md-left');

        $html .= html_writer::start_div('avatar avatar-xxl mr-5');
        $html .= html_writer::empty_tag('img', [
            'src' => $iconurl,
            'class' => 'avatar avatar-xxl rounded',
            'alt' => 'saladeconferencias',
        ]);
        $html .= html_writer::div('', 'overlay__content');
        $html .= html_writer::end_div();

        $html .= html_writer::start_div('flex', ['style' => 'max-width: 40rem;']);
        $html .= html_writer::start_div('flex d-flex flex-row align-items-center');
        $html .= html_writer::tag('h1', format_string($course->fullname), [
            'style' => 'max-width: 616px; color: #F5FEFF !important; line-height: 1.0;',
        ]);
        if ($newcount > 0) {
            $unwatchedvideostxt = get_string('unwatchedvideos', 'format_saladeconferencias', $newcount);
            $html .= html_writer::start_div('ml-4');
            $html .= html_writer::start_tag('span', ['class' => 'badge-pill p-1 bg-purple100 pl-2 pr-2']);
            $html .= html_writer::tag('b', $unwatchedvideostxt, ['style' => 'color: rgb(29, 0, 57);']);
            $html .= html_writer::end_tag('span');
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div();
        $html .= html_writer::tag('small', $summary, ['class' => 'w-25 text-white-70']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        $html .= html_writer::start_div('d-flex flex-column flex-sm-row align-items-center justify-content-start');
        $html .= html_writer::start_tag('a', [
            'href' => 'javascript:void(0);',
            'onclick' => 'window.history.back();',
            'class' => 'btn btn-outline-white btn-rounded mb-16pt mb-sm-0 mr-sm-16pt',
        ]);
        $html .= html_writer::tag('i', '', ['class' => 'icon fa fa-chevron-left fa-fw navicon mr-1']);
        $html .= get_string('back');
        $html .= html_writer::end_tag('a');

        $coursecontext = context_course::instance($course->id);
        if (has_capability('moodle/course:update', $coursecontext, $USER)) {
            $isediting = $PAGE->user_is_editing();
            $edittxt = $isediting ? get_string('turneditingoff') : get_string('turneditingon');
            $editurl = new moodle_url('/course/view.php', [
                'id' => $course->id,
                'edit' => $isediting ? 'off' : 'on',
                'sesskey' => sesskey(),
            ]);
            $html .= html_writer::tag('a', $edittxt, [
                'href' => $editurl,
                'class' => 'btn btn-clear btn-rounded ml-2',
                'style' => 'text-wrap: nowrap;',
                'title' => $edittxt,
            ]);
        }
        $html .= html_writer::end_div();

        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Sala de Conferências banner END -->';
        return $html;
    }

    /**
     * @param stdClass $course
     * @return string
     */
    protected function render_featured_area(stdClass $course): string {
        global $CFG, $USER;

        list($currentcm, $currentvideo) = $this->get_featured_video($course);
        if ($currentcm) {
            $this->featuredcmid = (int) $currentcm->id;
        }
        $html = html_writer::start_div('saladeconferencias-featured container-xl');
        if ($currentvideo && $currentcm) {
            require_once($CFG->dirroot . '/mod/video/lib.php');
            require_once($CFG->dirroot . '/mod/video/locallib.php');
            $cminfo = get_fast_modinfo($course)->get_cm($currentcm->id);
            $useposter = video_uses_featured_poster($currentvideo);
            $html .= html_writer::start_div('saladeconferencias-featured__player-card', [
                'id' => 'saladeconferencias-currentvideo',
                'data-cmid' => $currentcm->id,
                'data-use-poster' => $useposter ? '1' : '0',
            ]);
            $html .= $this->render_featured_player($currentvideo, $cminfo, $course);
            list($title,) = video_extract_duration_str_from_title($currentvideo);
            $html .= html_writer::tag('h2', s($title), ['class' => 'saladeconferencias-featured__video-title']);
            $html .= html_writer::end_div();
        } else {
            $html .= html_writer::div(
                get_string('novideosavailable', 'format_saladeconferencias'),
                'saladeconferencias-featured__empty alert alert-info'
            );
        }
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Featured player: poster for embed URL videos, inline player for others (e.g. YouTube).
     *
     * @param stdClass $video Video instance
     * @param cm_info $cminfo Course module
     * @param stdClass $course Course
     * @return string
     */
    protected function render_featured_player(stdClass $video, cm_info $cminfo, stdClass $course): string {
        global $CFG, $USER;

        if (!format_saladeconferencias::is_video_playable($cminfo)) {
            return html_writer::div(
                get_string('novideosavailable', 'format_saladeconferencias'),
                'saladeconferencias-featured__empty alert alert-info'
            );
        }

        if (video_uses_featured_poster($video)) {
            return $this->render_featured_player_poster($cminfo, $video->id);
        }

        if (isloggedin() && !isguestuser()) {
            $context = context_module::instance($cminfo->id);
            $cmrecord = (object) ['id' => $cminfo->id, 'course' => $course->id, 'instance' => $cminfo->instance];
            video_view($video, $course, $cmrecord, $context);
            video_mark_view_completion_on_open($video, $course, $cminfo);
        }

        $html = html_writer::start_div('saladeconferencias-featured__player');
        $html .= video_print_player($cminfo);
        $html .= html_writer::end_div();

        if (isloggedin() && !isguestuser()) {
            $completionjsconfig = [
                'wwwroot' => $CFG->wwwroot,
                'cmid' => $cminfo->id,
                'sesskey' => sesskey(),
                'completed' => video_is_complete($course, $cminfo, $USER->id),
                'completeimmediately' => !empty(get_config('video', 'completeimmediately')),
                'secondstocomplete' => (int) get_config('video', 'secondstocomplete'),
                'completeonview' => video_complete_on_view($video),
            ];
            $html .= html_writer::script('window.VIDEO_COMPLETION_CFG = ' . json_encode($completionjsconfig) . ';');
        }

        return $html;
    }

    /**
     * Featured player placeholder with poster image (before play).
     *
     * @param cm_info $cminfo Course module
     * @param int $videoid Video instance id
     * @return string
     */
    protected function render_featured_player_poster(cm_info $cminfo, int $videoid): string {
        $poster = video_get_player_poster_url($videoid, $cminfo->id);
        if (!$poster) {
            $poster = video_get_thumbnail_url($videoid, $cminfo->id);
        }

        $html = html_writer::start_div('saladeconferencias-featured__player');
        $html .= html_writer::start_div('saladeconferencias-featured__poster');
        $html .= html_writer::empty_tag('img', [
            'src' => $poster,
            'alt' => '',
            'class' => 'saladeconferencias-featured__poster-img',
        ]);
        $html .= html_writer::tag('button', '', [
            'type' => 'button',
            'class' => 'saladeconferencias-featured__play-btn',
            'aria-label' => get_string('playfeatured', 'format_saladeconferencias'),
        ]);
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('saladeconferencias-featured__player-inner', ['style' => 'display:none']);
        $html .= video_print_player($cminfo);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * @param stdClass $course
     * @return string
     */
    protected function render_sections_area(stdClass $course): string {
        global $PAGE;

        $html = html_writer::start_div('saladeconferencias-sections', ['id' => 'saladeconferencias-content']);
        $html .= html_writer::start_div('container-xl saladeconferencias-sections__inner');

        $modinfo = get_fast_modinfo($course);
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section == 0) {
                continue;
            }
            $showsection = $section->uservisible ||
                ($section->visible && !$section->available && !empty($section->availableinfo));
            if ($showsection) {
                $html .= $this->render_section($course, $section);
            }
        }

        $context = context_course::instance($course->id);
        if ($PAGE->user_is_editing() && has_capability('moodle/course:update', $context)) {
            $html .= $this->render_section_admin_controls($course);
        }

        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * @param stdClass $course
     * @param section_info $section
     * @return string
     */
    protected function render_section(stdClass $course, section_info $section): string {
        global $PAGE;

        if (!$section->uservisible) {
            return '';
        }

        $sectionname = get_section_name($course, $section);
        $summary = '';
        if (!empty($section->summary)) {
            $summary = format_text($section->summary, $section->summaryformat, [
                'context' => context_course::instance($course->id),
            ]);
        }

        $html = html_writer::start_div('saladeconferencias-section', ['id' => 'section-' . $section->section]);
        $html .= html_writer::start_div('saladeconferencias-section__header');
        if ($PAGE->user_is_editing()) {
            $rightcontent = $this->section_right_content($section, $course, true);
            $html .= html_writer::div($rightcontent, 'saladeconferencias-section__controls side');
        }
        $html .= html_writer::tag('span', '', ['class' => 'saladeconferencias-section__play-icon fa fa-play', 'aria-hidden' => 'true']);
        $html .= html_writer::tag('h2', $sectionname, ['class' => 'saladeconferencias-section__title']);
        $html .= html_writer::end_div();
        if (trim(strip_tags($summary)) !== '') {
            $html .= html_writer::div($summary, 'saladeconferencias-section__summary');
        }

        $html .= $this->course_section_cm_list($course, $section);
        if ($PAGE->user_is_editing()) {
            $html .= html_writer::div(
                $this->courserenderer->course_section_add_cm_control($course, $section->section, 0),
                'saladeconferencias-section__addcm'
            );
        }
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * @param stdClass $course
     * @return string
     */
    protected function render_section_admin_controls(stdClass $course): string {
        $html = html_writer::start_div('saladeconferencias-changenumsections mdl-right', ['id' => 'changenumsections']);
        $straddsection = get_string('increasesections', 'moodle');
        $url = new moodle_url('/course/changenumsections.php', [
            'courseid' => $course->id,
            'increase' => true,
            'sesskey' => sesskey(),
        ]);
        $icon = $this->output->pix_icon('t/switch_plus', $straddsection);
        $html .= html_writer::link($url, $icon . get_accesshide($straddsection), ['class' => 'increase-sections']);
        if (!empty($course->numsections)) {
            $strremovesection = get_string('reducesections', 'moodle');
            $url = new moodle_url('/course/changenumsections.php', [
                'courseid' => $course->id,
                'increase' => false,
                'sesskey' => sesskey(),
            ]);
            $icon = $this->output->pix_icon('t/switch_minus', $strremovesection);
            $html .= html_writer::link($url, $icon . get_accesshide($strremovesection), ['class' => 'reduce-sections']);
        }
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * @param stdClass $course
     * @param section_info|int $section
     * @param int|null $sectionreturn
     * @param array $displayoptions
     * @return string
     */
    public function course_section_cm_list($course, $section, $sectionreturn = null, $displayoptions = []): string {
        global $USER, $PAGE;

        $format = course_get_format($course);
        $modinfo = $format->get_modinfo();

        if (is_object($section)) {
            $section = $modinfo->get_section_info($section->section);
        } else {
            $section = $modinfo->get_section_info($section);
        }

        $completioninfo = new completion_info($course);
        $ismoving = $format->show_editor() && ismoving($course->id);
        if ($ismoving) {
            $strmovefull = strip_tags(get_string('movefull', '', "'$USER->activitycopyname'"));
        }

        $moduleshtml = [];
        $editing = $PAGE->user_is_editing();
        if ($editing && !empty($modinfo->sections[$section->section])) {
            $modcandidates = [];
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $modcandidates[$modnumber] = $modinfo->cms[$modnumber];
            }
        } else {
            $modcandidates = format_saladeconferencias::get_section_video_modules($course, $section->section);
            $modcandidates = array_filter($modcandidates, function (cm_info $mod) use ($editing): bool {
                return format_saladeconferencias::should_show_video_card($mod, $editing);
            });
        }

        foreach ($modcandidates as $modnumber => $mod) {
            if (!$mod->available && !$editing && !is_siteadmin()
                    && !format_saladeconferencias::is_video_coming_soon($mod)) {
                continue;
            }
            if ($ismoving && $mod->id == $USER->activitycopy) {
                continue;
            }
            if ($mod->modname !== 'video' && !$editing) {
                continue;
            }
            $itemhtml = ($mod->modname === 'video')
                ? $this->render_video_card($mod)
                : $this->courserenderer->course_section_cm_list_item($course, $completioninfo, $mod, $sectionreturn, $displayoptions);
            if ($itemhtml) {
                $moduleshtml[$modnumber] = $itemhtml;
            }
        }

        if (empty($moduleshtml) && !$ismoving) {
            return '';
        }

        $listhtml = '';
        foreach ($moduleshtml as $modnumber => $modulehtml) {
            if ($ismoving) {
                $movingurl = new moodle_url('/course/mod.php', ['moveto' => $modnumber, 'sesskey' => sesskey()]);
                $listhtml .= html_writer::tag('li', html_writer::link($movingurl, '', [
                    'title' => $strmovefull,
                    'class' => 'movehere',
                ]), ['class' => 'movehere']);
            }
            $listhtml .= html_writer::div($modulehtml, 'saladeconferencias-section__video-item');
        }
        if ($ismoving) {
            $movingurl = new moodle_url('/course/mod.php', ['movetosection' => $section->id, 'sesskey' => sesskey()]);
            $listhtml .= html_writer::tag('li', html_writer::link($movingurl, '', [
                'title' => $strmovefull,
                'class' => 'movehere',
            ]), ['class' => 'movehere']);
        }

        return html_writer::div($listhtml, 'saladeconferencias-section__videos');
    }

    /**
     * @param cm_info $mod
     * @return string
     */
    protected function render_video_card(cm_info $mod): string {
        global $CFG, $PAGE;

        if ($mod->modname !== 'video') {
            return '';
        }
        require_once($CFG->dirroot . '/mod/video/lib.php');

        list($title,) = video_extract_duration_str_from_cm_title($mod);
        if (format_saladeconferencias::is_video_coming_soon($mod)) {
            $thumbnail = format_saladeconferencias::get_hidden_video_poster_url();
            $thumbnailalt = get_string('hiddenvideoposteralt', 'format_saladeconferencias');
        } else {
            $thumbnail = video_get_thumbnail_url($mod->instance, $mod->id);
            $thumbnailalt = '';
        }
        $playable = format_saladeconferencias::is_video_playable($mod);
        $classes = 'saladeconferencias-video-card';
        if (!$playable) {
            $classes .= ' saladeconferencias-video-card--locked';
        }
        if (format_saladeconferencias::is_video_coming_soon($mod)) {
            $classes .= ' saladeconferencias-video-card--hidden';
        }
        if ($this->has_user_completed_video($mod)) {
            $classes .= ' saladeconferencias-video-card--complete';
        }
        if ($this->featuredcmid && (int) $mod->id === $this->featuredcmid) {
            $classes .= ' saladeconferencias-video-card--active';
        }
        if ($PAGE->user_is_editing()) {
            $classes .= ' editing';
        }

        $html = html_writer::start_div($classes, ['id' => 'cm-' . $mod->id]);
        if ($PAGE->user_is_editing()) {
            $html .= course_get_cm_move($mod, 0);
            $html .= $this->render_video_card_edit_menu($mod);
        }

        $canplay = $playable && !$PAGE->user_is_editing();
        $canlink = $playable && $mod->url && $PAGE->user_is_editing();
        $tag = ($canlink || $canplay) ? 'a' : 'div';
        $attrs = ['class' => 'saladeconferencias-video-card__link'];
        if ($canplay) {
            $attrs['href'] = '#';
            $attrs['data-cmid'] = $mod->id;
            $attrs['role'] = 'button';
            $attrs['aria-label'] = get_string('playvideo', 'format_saladeconferencias', $title);
            $videorecord = video_get_from_id($mod->instance);
            if ($videorecord && video_uses_featured_poster($videorecord)) {
                $attrs['data-use-poster'] = '1';
            }
        } else if ($canlink) {
            $attrs['href'] = $mod->url->out(false);
            $tag = 'a';
        } else {
            $attrs['class'] .= ' saladeconferencias-video-card__link--disabled';
            $tag = 'div';
        }
        if ($tag === 'div' && strpos($attrs['class'], '--disabled') !== false) {
            unset($attrs['href'], $attrs['role'], $attrs['data-cmid']);
        }
        $html .= html_writer::start_tag($tag, $attrs);
        $html .= html_writer::start_div('saladeconferencias-video-card__thumb');
        $html .= html_writer::empty_tag('img', ['src' => $thumbnail, 'alt' => $thumbnailalt]);
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('saladeconferencias-video-card__titlebar');
        $html .= html_writer::span(s($title), 'saladeconferencias-video-card__title');
        $html .= $this->render_video_card_status($mod);
        $html .= html_writer::end_div();
        $html .= html_writer::div('', 'saladeconferencias-video-card__base');
        $html .= html_writer::end_tag($tag);
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Activity edit menu (Edit, Hide, Duplicate, etc.) for editing mode.
     *
     * @param cm_info $mod
     * @return string
     */
    protected function render_video_card_edit_menu(cm_info $mod): string {
        $format = course_get_format($mod->get_course());
        $controlmenu = new controlmenu(
            $format,
            $mod->get_section_info(),
            $mod,
            [
                'ownerselector' => '#cm-' . $mod->id,
                'constraintselector' => '#saladeconferencias-content',
            ]
        );
        $menuhtml = $this->render($controlmenu);
        if ($menuhtml === '') {
            return '';
        }
        return html_writer::div($menuhtml, 'saladeconferencias-video-card__editbar');
    }

    /**
     * Status area: play (pending) or check (completed) for the current user.
     *
     * @param cm_info $mod
     * @return string
     */
    protected function render_video_card_status(cm_info $mod): string {
        global $PAGE;

        $iscomplete = $this->has_user_completed_video($mod);
        $statusclass = 'saladeconferencias-video-card__status ' .
            ($iscomplete ? 'saladeconferencias-video-card__status--complete' : 'saladeconferencias-video-card__status--pending');

        $html = html_writer::start_div($statusclass);
        if ($iscomplete) {
            $html .= $this->output->pix_icon('i/grade_correct', get_string('completed', 'completion'), 'moodle', [
                'class' => 'saladeconferencias-video-card__check-icon iconsmall',
            ]);
        } else if (isloggedin() && !isguestuser()
                && format_saladeconferencias::is_video_playable($mod)) {
            $html .= html_writer::tag('span', '', [
                'class' => 'saladeconferencias-video-card__play fa fa-play-circle-o',
                'aria-hidden' => 'true',
            ]);
        }
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * True only when this user has a saved COMPLETION_COMPLETE state for this video.
     *
     * @param cm_info $mod
     * @return bool
     */
    protected function has_user_completed_video(cm_info $mod): bool {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return false;
        }

        $course = $mod->get_course();
        $completioninfo = new completion_info($course);
        if ($completioninfo->is_enabled($mod) == COMPLETION_TRACKING_NONE) {
            return false;
        }

        // Show the user's own completion state even when they are not in completion reports
        // (e.g. enrolled without a role, or teacher previewing the course).
        $completiondata = $completioninfo->get_data($mod, false, $USER->id);
        return in_array((int) $completiondata->completionstate, [
            COMPLETION_COMPLETE,
            COMPLETION_COMPLETE_PASS,
        ], true);
    }

    /**
     * @param stdClass $course
     * @param int $availability
     * @return array<int, stdClass> cmid => video record
     */
    protected function get_course_videos(stdClass $course, int $availability = format_saladeconferencias::BOTH): array {
        global $DB;

        $videomodule = $DB->get_record('modules', ['name' => 'video'], '*', MUST_EXIST);
        $videos = [];
        $instanceids = [];
        $modinfo = get_fast_modinfo($course);
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section <= 0 || empty($modinfo->sections[$section->section])) {
                continue;
            }
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];
                if ((int) $mod->module !== (int) $videomodule->id) {
                    continue;
                }
                $match = ($availability === format_saladeconferencias::BOTH)
                    || ($availability === format_saladeconferencias::UNAVAILABLE && !$mod->available)
                    || ($availability === format_saladeconferencias::AVAILABLE && $mod->available);
                if (!$match || empty($mod->visible) || !$mod->uservisible) {
                    continue;
                }
                if ($availability === format_saladeconferencias::AVAILABLE
                        && !format_saladeconferencias::is_video_playable($mod)) {
                    continue;
                }
                $instanceids[(int) $mod->instance] = (int) $mod->id;
            }
        }
        if (!empty($instanceids)) {
            list($insql, $params) = $DB->get_in_or_equal(array_keys($instanceids), SQL_PARAMS_NAMED, 'vid');
            $records = $DB->get_records_select('video', "id $insql", $params);
            foreach ($records as $video) {
                $cmid = $instanceids[(int) $video->id] ?? null;
                if ($cmid) {
                    $videos[$cmid] = $video;
                }
            }
        }
        return $videos;
    }

    /**
     * @param int $userid
     * @param int $cmid
     * @return bool
     */
    protected function is_cm_completed(int $userid, int $cmid): bool {
        global $DB;
        $record = $DB->get_record('course_modules_completion', [
            'coursemoduleid' => $cmid,
            'userid' => $userid,
        ]);
        if (!$record) {
            return false;
        }
        return in_array((int) $record->completionstate, [
            COMPLETION_COMPLETE,
            COMPLETION_COMPLETE_FAIL,
            COMPLETION_COMPLETE_PASS,
        ], true);
    }

    /**
     * @param stdClass $course
     * @return array<int, stdClass>
     */
    protected function get_unwatched_videos(stdClass $course): array {
        global $USER;
        $unwatched = [];
        $modinfo = get_fast_modinfo($course);
        foreach ($this->get_course_videos($course, format_saladeconferencias::AVAILABLE) as $cmid => $video) {
            if (isset($modinfo->cms[$cmid]) && !$this->has_user_completed_video($modinfo->cms[$cmid])) {
                $unwatched[] = $video;
            }
        }
        return $unwatched;
    }

    /**
     * Featured video: optional ?video=cmid URL param, otherwise first available.
     *
     * @param stdClass $course
     * @return array{0: stdClass|null, 1: stdClass|null}
     */
    protected function get_featured_video(stdClass $course): array {
        global $DB;

        $cmid = optional_param('video', 0, PARAM_INT);
        if ($cmid) {
            $modinfo = get_fast_modinfo($course);
            if (isset($modinfo->cms[$cmid])) {
                $mod = $modinfo->cms[$cmid];
                if ($mod->modname === 'video' && format_saladeconferencias::is_video_playable($mod)) {
                    $cm = $DB->get_record('course_modules', ['id' => $cmid]);
                    $video = $DB->get_record('video', ['id' => $mod->instance]);
                    if ($cm && $video) {
                        return [$cm, $video];
                    }
                }
            }
        }
        return $this->get_first_available_video($course);
    }

    /**
     * @param stdClass $course
     * @return array{0: stdClass|null, 1: stdClass|null}
     */
    protected function get_first_available_video(stdClass $course): array {
        global $DB;
        foreach ($this->get_course_videos($course, format_saladeconferencias::AVAILABLE) as $cmid => $video) {
            $cm = $DB->get_record('course_modules', ['id' => $cmid]);
            return [$cm, $video];
        }
        return [null, null];
    }

    /**
     * @param stdClass $course
     * @param array|null $sections
     * @param array|null $mods
     * @param array|null $modnames
     * @param array|null $modnamesused
     * @param int $displaysection
     */
    public function print_single_section_page($course, $sections, $mods, $modnames, $modnamesused, $displaysection): void {
        $this->print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused);
    }

    /**
     * @param stdClass $section
     * @param stdClass $course
     * @return string
     */
    public function section_title($section, $course): string {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }
}

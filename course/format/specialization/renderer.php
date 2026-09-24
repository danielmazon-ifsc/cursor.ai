<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/renderer.php');

use mod_courseevaluation\courseevaluation;
use core_courseformat\output\section_renderer;

class format_specialization_renderer extends section_renderer {

    protected function start_section_list() {
        global $COURSE;

        $html = '';
        $html .= html_writer::start_div('page-section flex d-flex align-items-center justify-content-center pb-112pt', array(
                    'id' => 'specialization-body',
                    'style' => 'padding-bottom: 0rem !important;'
        ));
        $eixonum = $this->normalize_eixo_number($COURSE->shortname);
        if ($eixonum > 0) {
            $html .= html_writer::start_div('containereixo' . $eixonum);
        } else {
            // Fallback for non-standard shortnames.
            $safeshort = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $COURSE->shortname);
            $html .= html_writer::start_div('container' . $safeshort);
        }
        return $html;
    }

    private function all_prev_cms_in_section_completed(cm_info $mod, section_info $section) {
        global $USER;

        $sectioncms = array_filter(array_map('intval', explode(',', $section->sequence)));
        foreach ($sectioncms as $sectioncm) {
            if ($sectioncm === (int) $mod->id) {
                return true;
            }
            if (!format_specialization::is_cm_completed($USER->id, $sectioncm)) {
                return false;
            }
        }
        return true;
    }

    protected function end_section_list() {
        $html = '';
        $html .= '<!-- Modal/Popup Begin -->';
        $html .= html_writer::start_div('custom-modal', array(
                    'id' => 'section-modal'
        ));
        $html .= html_writer::start_div('custom-modal-content');
        $modaltitle = get_string('whichcontent', 'format_specialization');
        $html .= html_writer::tag('h3', $modaltitle, array(
                    'class' => 'text-center mb-4'
        ));
        $attentiontitle = get_string('attentiontitle', 'format_specialization');
        $html .= html_writer::start_tag('p', array(
                    'class' => 'text-center mb-0'
        ));
        $html .= html_writer::tag('span', $attentiontitle, array(
                    'style' => 'font-weight: 900;'
        ));
        $html .= ' ' . get_string('evaluationattentiontext', 'format_specialization');
        $html .= html_writer::end_tag('p');
        $html .= html_writer::start_tag('p', array(
                    'class' => 'text-center mb-4'
        ));
        $html .= html_writer::tag('span', $attentiontitle, array(
                    'style' => 'font-weight: 900;'
        ));
        $html .= ' ' . get_string('secondchanceattentiontext', 'format_specialization');
        $html .= html_writer::end_tag('p');
        $html .= html_writer::start_div('d-flex justify-content-between', array(
                    'id' => 'buttons'
        ));
        // Content access button (handler attached in format-specialization.js,
        // no inline onclick so CSP can stay strict).
        $html .= html_writer::start_tag('button', array(
                    'id' => 'content',
                    'type' => 'button',
                    'class' => 'btn btn-primary btn-rounded',
                    'data-url' => '',
                    'data-action' => 'access-content'
        ));
        $html .= get_string('content', 'format_specialization');
        $html .= html_writer::end_tag('button');
        // Evaluation button
        $contentevaluation = get_string('evaluation', 'format_specialization');
        $html .= html_writer::start_tag('button', array(
                    'id' => 'evaluation',
                    'type' => 'button',
                    'class' => 'btn btn-primary btn-rounded',
                    'data-title' => get_string('evaluationattentiontext', 'format_specialization'),
                    'data-original-title' => '',
                    'title' => '',
                    'data-placement' => 'top',
                    'data-boundary' => 'window',
                    'data-url' => '',
                    'data-action' => 'access-evaluation'
        ));
        $html .= $contentevaluation;
        $html .= html_writer::end_tag('button');
        // Second chance button
        $secondchancetxt = get_string('secondchance', 'format_specialization');
        $html .= html_writer::start_tag('button', array(
                    'id' => 'secondchance',
                    'type' => 'button',
                    'class' => 'btn btn-primary btn-rounded',
                    'data-title' => get_string('secondchanceattentiontext', 'format_specialization'),
                    'data-original-title' => '',
                    'title' => '',
                    'data-placement' => 'top',
                    'data-boundary' => 'window',
                    'data-url' => '',
                    'data-action' => 'access-secondchance'
        ));
        $html .= $secondchancetxt;
        $html .= html_writer::end_tag('button');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Modal/Popup End -->';
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        return $html;
    }

    private function print_conference_room() {
        global $CFG, $COURSE;

        $html = '';
        $confererenceroomtxt = get_string('conferenceroom', 'format_specialization');
        $conferenceroom = $this->find_conference_room_course();

        $coursecontext = context_course::instance($COURSE->id);
        $safeshort = format_string($COURSE->shortname, true, ['context' => $coursecontext]);
        if ($conferenceroom) {
            $conferenceroomurl = new moodle_url('/course/view.php', array(
                'id' => $conferenceroom->id
            ));
            $specializationnumber = $this->normalize_eixo_number($COURSE->shortname);
            $html .= html_writer::div('', 'interactive-area', array(
                        'id' => 'course-' . $specializationnumber . '-saladeconferencias',
                        'data-tooltip' => $confererenceroomtxt,
                        'data-url' => $conferenceroomurl->out(false),
                        'data-course' => $safeshort,
                        'data-isediting' => $this->page->user_is_editing() ? '1' : '0',
            ));
        } else {
            $html .= html_writer::div('', 'interactive-area locked-area', array(
                        'id' => 'saladeconferencias',
                        'data-tooltip' => $confererenceroomtxt,
                        'data-url' => '#',
                        'data-course' => $safeshort,
                        'data-isediting' => '0',
            ));
        }
        return $html;
    }

    /**
     * Find a course that can serve as the conference room for the current specialization.
     *
     * Prefers the newer "saladeconferencias" format, falls back to the legacy
     * "conferenceroom" format. Either or both plugins may be installed.
     *
     * @return stdClass|null Course record or null when no conference room is available.
     */
    private function find_conference_room_course() {
        global $CFG, $USER;

        // Hidden / archived conference room courses should not leak to
        // students through the interactive area. Site admins still see them
        // (they bypass visibility checks), so the link is still useful while
        // an admin is preparing the course.
        $filter = function($courses) {
            $out = [];
            foreach ($courses as $c) {
                if (!empty($c->visible) || is_siteadmin()) {
                    $out[] = $c;
                }
            }
            return $out;
        };

        $saladelib = $CFG->dirroot . '/course/format/saladeconferencias/lib.php';
        if (file_exists($saladelib)) {
            require_once($saladelib);
            if (class_exists('format_saladeconferencias')) {
                $courses = $filter(format_saladeconferencias::get_courses_in_format());
                if (!empty($courses)) {
                    return reset($courses);
                }
            }
        }

        $conflib = $CFG->dirroot . '/course/format/conferenceroom/lib.php';
        if (file_exists($conflib)) {
            require_once($conflib);
            if (class_exists('format_conferenceroom')) {
                $courses = $filter(format_conferenceroom::get_courses_in_format());
                if (!empty($courses)) {
                    return reset($courses);
                }
            }
        }

        return null;
    }

    protected function print_course_sections($formattedcourse) {
        global $PAGE;

        $html = '';
        $html .= "<!-- Course sections BEGIN -->";
        $html .= $this->start_section_list();
        $modinfo = get_fast_modinfo($formattedcourse);
        foreach ($modinfo->get_section_info_all() as $sectionnum => $section) {
            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display.
            $showsection = ($sectionnum != 0) && ($section->uservisible ||
                    ($section->visible && !$section->available &&
                    !empty($section->availableinfo)));
            if ($showsection) {
                $html .= $this->print_section($formattedcourse, $section);
            }
        }
        $html .= $this->print_conference_room();
        $html .= $this->end_section_list();
        $html .= "<!-- Course sections END -->";

        $context = context_course::instance($formattedcourse->id);
        if ($PAGE->user_is_editing() and has_capability('moodle/course:update', $context)) {
            $html .= "<!-- Sections control BEGIN -->";

            $html .= html_writer::start_tag('div', array('id' => 'changenumsections', 'class' => 'mdl-right'));

            // Increase number of sections.
            $straddsection = get_string('increasesections', 'moodle');
            $url = new moodle_url('/course/changenumsections.php', array('courseid' => $formattedcourse->id,
                'increase' => true,
                'sesskey' => sesskey()));
            $icon = $this->output->pix_icon('t/switch_plus', $straddsection);
            $html .= html_writer::link($url, $icon . get_accesshide($straddsection), array('class' => 'increase-sections'));

            if ($formattedcourse->numsections > 0) {
                // Reduce number of sections sections.
                $strremovesection = get_string('reducesections', 'moodle');
                $url = new moodle_url('/course/changenumsections.php', array('courseid' => $formattedcourse->id,
                    'increase' => false,
                    'sesskey' => sesskey()));
                $icon = $this->output->pix_icon('t/switch_minus', $strremovesection);
                $html .= html_writer::link($url, $icon . get_accesshide($strremovesection), array('class' => 'reduce-sections'));
            }

            $html .= html_writer::end_tag('div');
            $html .= "<!-- Sections control END -->";
        }

        return $html;
    }

    protected function print_section($formattedcourse, section_info $section) {
        global $PAGE, $USER;

        $html = '';
        $modinfo = get_fast_modinfo($formattedcourse);
        if (is_object($section)) {
            $section = $modinfo->get_section_info($section->section);
        } else {
            $section = $modinfo->get_section_info($section);
        }
        $html .= html_writer::start_div('', array(
                    'id' => 'section-' . $section->section
        ));
        $specializationnumber = $this->normalize_eixo_number($formattedcourse->shortname);
        $coursectx = context_course::instance($formattedcourse->id);
        // Resolve a useful tooltip label. Order of preference:
        // 1) The section's own name (set by the admin in edit mode).
        // 2) The first module's formatted name — what the user is about
        //    to open when they click the card, so it's a meaningful proxy
        //    for the "discipline" title.
        // 3) get_section_name() fallback (e.g. "Disciplina N") — only used
        //    when the section is genuinely empty.
        $sectionname = '';
        if (!empty($section->name)) {
            $sectionname = $section->name;
        } else if (!empty($modinfo->sections[$section->section])) {
            $firstcmid = reset($modinfo->sections[$section->section]);
            if ($firstcmid && isset($modinfo->cms[$firstcmid])) {
                $sectionname = $modinfo->cms[$firstcmid]->get_formatted_name();
            }
        }
        if ($sectionname === '') {
            $sectionname = get_section_name($formattedcourse, $section);
        }
        $tooltiplabel = format_string($sectionname, true, ['context' => $coursectx]);
        // Eixos with two disciplines store the evaluation page in an earlier
        // section (e.g. 3 or 4) while avaliacao.css positions the hotspot at
        // #course-N-window-5/6. Decouple the Moodle section index from the
        // DOM window id when this section holds the reaction evaluation.
        $windowid = (int) $section->section;
        $evalsection = $this->get_avaliacao_reacao_section_number($formattedcourse);
        $hotspotwindow = $this->get_avaliacao_hotspot_window($specializationnumber);
        if ($evalsection !== null && $evalsection === $windowid && $hotspotwindow > 0) {
            $windowid = $hotspotwindow;
        }
        $options = array(
            'id' => 'course-' . $specializationnumber . '-window-' . $windowid,
            // Use data-* + format_string-cleaned name so HTML in section names
            // is escaped and so the attribute follows web standards.
            'data-tooltip' => $tooltiplabel,
            'data-course' => format_string($formattedcourse->shortname, true, ['context' => $coursectx]),
            'data-isediting' => $this->page->user_is_editing() ? '1' : '0',
        );
        if (!empty($modinfo->sections[$section->section])) {
            $evaluationcmid = 0;
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];
                // cm_info already carries the module name as a string in its
                // 'modname' property — querying {modules} per CM was a hard
                // N+1: an Eixo with 5 sections * 3 CMs each ran 15 extra
                // SELECTs every page render, just to recover information we
                // already had in memory.
                if ($mod->modname === 'cquiz') {
                    if (!isset($options['data-evaluationurl'])) {
                        $evaluationcmid = $mod->id;
                        $options['data-evaluationurl'] = (string) $mod->url;
                        $options['data-contentcompleted'] = $this->all_prev_cms_in_section_completed($mod, $section) ? '1' : '0';
                    } else {
                        $options['data-secondchanceurl'] = (string) $mod->url;
                        $options['data-evaluationfailed'] = ($evaluationcmid == 0 ? '0' : format_specialization::is_cm_failed($USER->id, $evaluationcmid));
                    }
                } else {
                    $options['data-contenturl'] = (string) $mod->url;
                }
            }
        }
        $isblocked = !format_specialization::all_previous_sections_completed($section->course, $section->section);
        $blockedclass = (($isblocked && !$PAGE->user_is_editing() && !is_siteadmin()) ? ' locked-area' : '');
        $html .= html_writer::start_div('interactive-area' . $blockedclass . (!$PAGE->user_is_editing() ? ' active' : ''), $options);
        // "Avaliação de Reação" banner. Injected server-side as the first
        // child of the interactive-area, matching the original
        // insertAdjacentHTML("afterbegin", ...) JS behaviour that used to
        // live in theme/pdi/js/avaliacao.js. Returns '' when the
        // (specializationnumber, section) pair has no mapping or when the
        // page is in editing mode.
        $html .= $this->build_avaliacao_reacao_mural($specializationnumber, $section, $formattedcourse);
        if ($PAGE->user_is_editing()) {
            $html .= html_writer::start_div('section-item');
            $html .= html_writer::span($tooltiplabel, 'section-name');
            $html .= $this->section_right_content($section, $formattedcourse, true /* $onsectionpage */);
            $html .= html_writer::end_div();
            if (!empty($modinfo->sections[$section->section])) {
                foreach ($modinfo->sections[$section->section] as $modnumber) {
                    $mod = $modinfo->cms[$modnumber];
                    $html .= html_writer::start_div('section-item');
                    $html .= html_writer::span($mod->get_formatted_name(), 'course-module-name');
                    $editactions = course_get_cm_edit_actions($mod);
                    $html .= $this->courserenderer->course_section_cm_edit_actions($editactions, $mod);
                    $html .= html_writer::end_div();
                }
            }
            $html .= $this->courserenderer->course_section_add_cm_control($formattedcourse, $section->section, 0);
        }
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Build the "Avaliação de Reação" banner injected into specific Eixo
     * windows.
     *
     * Hotspot positions live in theme/pdi/style/avaliacao.css
     * (#course-N-window-5/6). The banner URL is resolved from the first
     * visible page/survey/questionnaire in the section so production and
     * homologation share the same code path.
     *
     * @param int $eixonumber Normalized Eixo index (1–6).
     * @param section_info $section Section within the Eixo course.
     * @param stdClass $course Eixo course record.
     * @return string HTML for the banner or '' when not applicable.
     */
    private function build_avaliacao_reacao_mural(int $eixonumber, section_info $section, stdClass $course) {
        global $PAGE;

        if ($PAGE->user_is_editing()) {
            return '';
        }

        $sectionnum = (int) $section->section;
        $evalsection = $this->get_avaliacao_reacao_section_number($course);
        if ($evalsection === null || $evalsection !== $sectionnum) {
            return '';
        }

        $url = $this->resolve_avaliacao_reacao_url($course, $section);
        if ($url === null || $url === '') {
            return '';
        }

        $img = html_writer::empty_tag('img', array(
            'src' => 'https://cdn.evg.gov.br/cursos/pdi/ns/doc/img-avaliacao-pdi.png',
            'alt' => get_string('avaliacaoreacao', 'format_specialization'),
            'class' => 'mural-aviso-imagem',
        ));
        $card = html_writer::div($img, 'mural-aviso');
        return html_writer::link($url, $card, array(
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
        ));
    }

    /**
     * DOM window suffix for the reaction-evaluation hotspot on each Eixo.
     *
     * Must stay in sync with theme/pdi/style/avaliacao.css
     * (#course-N-window-5/6). This is independent of the Moodle section
     * number where the evaluation activity actually lives.
     *
     * @param int $eixonumber
     * @return int 0 when the Eixo has no configured hotspot.
     */
    private function get_avaliacao_hotspot_window(int $eixonumber): int {
        static $map = [
            1 => 5,
            2 => 6,
            3 => 5,
            4 => 5,
            5 => 5,
            6 => 5,
        ];
        return $map[$eixonumber] ?? 0;
    }

    /**
     * Moodle section that contains the reaction-evaluation activity.
     *
     * @param stdClass $course
     * @return int|null
     */
    private function get_avaliacao_reacao_section_number(stdClass $course): ?int {
        static $cache = [];

        $courseid = (int) $course->id;
        if (!array_key_exists($courseid, $cache)) {
            $cache[$courseid] = $this->find_avaliacao_reacao_section_number($course);
        }

        return $cache[$courseid];
    }

    /**
     * Scan Eixo sections for the reaction-evaluation page/survey.
     *
     * @param stdClass $course
     * @return int|null
     */
    private function find_avaliacao_reacao_section_number(stdClass $course): ?int {
        $modinfo = get_fast_modinfo($course);
        foreach ($modinfo->get_section_info_all() as $sectionnum => $section) {
            if ($sectionnum === 0) {
                continue;
            }
            if ($this->resolve_avaliacao_reacao_url($course, $section) !== null) {
                return (int) $sectionnum;
            }
        }
        return null;
    }

    /**
     * Whether a course module is the reaction-evaluation activity.
     *
     * @param cm_info $cm
     * @param section_info|null $section
     * @return bool
     */
    private function is_avaliacao_reacao_cm(cm_info $cm, ?section_info $section = null): bool {
        if (!in_array($cm->modname, ['page', 'survey', 'questionnaire'], true)) {
            return false;
        }

        $name = core_text::strtolower(strip_tags($cm->get_formatted_name()));
        foreach (['reação', 'reacao', 'avaliação de reação', 'avaliacao de reacao'] as $needle) {
            if (strpos($name, $needle) !== false) {
                return true;
            }
        }

        if ($section !== null && !empty($section->name)) {
            $sectionname = core_text::strtolower($section->name);
            foreach (['reação', 'reacao', 'avaliação', 'avaliacao'] as $needle) {
                if (strpos($sectionname, $needle) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * URL of the reaction-evaluation activity inside an Eixo section.
     *
     * @param stdClass $course
     * @param section_info $section
     * @return string|null
     */
    private function resolve_avaliacao_reacao_url(stdClass $course, section_info $section): ?string {
        $modinfo = get_fast_modinfo($course);
        if (empty($modinfo->sections[$section->section])) {
            return null;
        }
        foreach ($modinfo->sections[$section->section] as $cmid) {
            if (empty($modinfo->cms[$cmid])) {
                continue;
            }
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible || empty($cm->url)) {
                continue;
            }
            if ($this->is_avaliacao_reacao_cm($cm, $section)) {
                return (string) $cm->url;
            }
        }
        return null;
    }

    /**
     * Numeric Eixo index used in DOM ids (course-1-window-5) and CSS selectors.
     *
     * Casting to int collapses leading zeros ("01" → 1) so IDs match
     * theme/pdi/style/avaliacao.css and format_specialization.css.
     *
     * @param string $shortname
     * @return int
     */
    private function normalize_eixo_number(string $shortname): int {
        return (int) $this->strip_letters($shortname);
    }

    /**
     * Reduce a course shortname down to the digits portion so it can be used
     * as the "<N>" in DOM ids like course-<N>-window-<M>.
     *
     * Strips ASCII letters AND whitespace — earlier versions only stripped
     * letters, so a shortname containing a space (e.g. "Eixo 1") produced
     * " 1" with a leading space. That space then leaked into the generated
     * DOM id, which is invalid in HTML/CSS (spaces are not allowed in id
     * attributes and silently break #id selectors). Stripping whitespace
     * makes the function robust to any naming convention: "Eixo1", "Eixo 1",
     * "EIXO_1" and "Eixo - 1" all collapse to "1".
     *
     * Non-ASCII letters (accents) aren't stripped, but Moodle's shortname
     * UI generally rejects those anyway; matching that loose contract keeps
     * the function predictable for the production data set.
     *
     * @param string $str
     * @return string
     */
    private function strip_letters(string $str) {
        $nolower = str_replace(range('a', 'z'), '', $str);
        $noupper = str_replace(range('A', 'Z'), '', $nolower);
        // preg_replace covers ASCII whitespace, tabs and non-breaking-space
        // glyphs that sometimes sneak in via copy/paste from spreadsheets.
        return preg_replace('/\s+/u', '', $noupper);
    }

    private function print_profile_card() {
        global $PAGE, $USER, $COURSE, $CFG;

        $html = '';
        $html .= '<!-- Profile card BEGIN -->';
        $html .= html_writer::start_div('card-body flex d-flex card position-sticy fixed-bottom justify-content-end  ml-auto mr-5 bg-black text-white border-0', array(
                    'style' => 'z-index: 2; width: 350px; transition: width 0.3s ease-in-out;',
                    'id' => 'profileCard'
        ));
        $html .= html_writer::start_div('flex d-flex d-block align-items-center mb-2');
        $userlink = new moodle_url('/local/dashboard/view.php');
        $html .= html_writer::start_tag('a', array(
                    'href' => $userlink,
                    'class' => 'avatar avatar avatar-md avatar-online mr-12pt'
        ));
        $userpicture = new user_picture($USER);
        $userpicture->size = 140;
        $userimagelink = $userpicture->get_url($PAGE);
        $html .= html_writer::tag('img', '', array(
                    'src' => $userimagelink,
                    'alt' => 'student',
                    'class' => 'avatar rounded-circle bg-white'
        ));
        $html .= html_writer::end_tag('a');
        $html .= html_writer::start_div('w-100 d-flex justify-content-between align-items-start');
        $html .= html_writer::start_div();
        // fullname() handles formatting + escaping; passing $USER directly so
        // privacy settings (alternativefullnameformat, etc.) are respected.
        $username = fullname($USER);
        $html .= html_writer::tag('a', s($username), array(
                    'href' => $userlink,
                    'class' => 'card-title',
                    'style' => 'min-height: 0 !important; color: white !important; font-weight: 100;'
        ));
        $html .= html_writer::start_div('d-flex align-items-center mt-2');
        $html .= html_writer::start_div('d-flex align-items-center mb-2');
        require_once($CFG->dirroot . '/local/dashboard/lib.php');
        $level = local_dashboard_get_current_level($USER->id, $COURSE->id);
        $coursename = format_string($COURSE->fullname, true, ['context' => context_course::instance($COURSE->id)]);
        $statustext = get_string('leveldesc', 'format_specialization', array(
            'level' => $level,
            'coursename' => $coursename
        ));
        $html .= html_writer::start_tag('span', array(
                    'class' => 'chip chip-light bg-primary100 text-primary600 d-inline-flex align-items-center shine-effect border-0',
                    'data-toggle' => 'tooltip',
                    'data-title' => $statustext,
                    'data-placement' => 'bottom',
                    'style' => 'padding: 2px 15px; font-size: 12px; position: relative; overflow: hidden;',
                    'data-original-title' => '',
                    'title' => ''
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-bar-chart'
        ));
        $html .= html_writer::end_tag('i');
        $html .= get_string('level', 'format_specialization', $level);
        $html .= html_writer::end_tag('span');
        $coursetext = get_string('of', 'format_specialization', $coursename);
        $html .= html_writer::tag('span', $coursetext, array(
                    'class' => 'chip chip-outline-secondary text-white-50 ml-2',
                    'style' => 'padding: 2px 15px; font-size: 12px; position: relative; overflow: hidden;'
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_tag('button', array(
                    'id' => 'toggleProfileCard',
                    'class' => 'btn btn-sm p-0 text-white'
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-minus'
        ));
        $html .= html_writer::end_tag('i');
        $html .= html_writer::end_tag('button');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('transition: opacity 0.3s ease-in-out, max-height 0.3s ease-in-out; overflow: hidden; max-height: 500px; opacity: 1;', array(
                    'id' => 'profileCardContent',
        ));
        $html .= html_writer::start_div('d-flex align-items-center mb-1', array(
                    'id' => 'progress'
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-line-chart'
        ));
        $html .= html_writer::end_tag('i');
        // Integer percent only in the profileCard bar/label (no 60.66666%).
        $completion = min(100, max(0, (int) round(100 * format_specialization::get_course_completion($USER, $COURSE))));
        $progresstext = get_string('progressdesc', 'format_specialization', array(
            'level' => $level,
            'coursename' => $coursename,
            'completion' => $completion
        ));
        $html .= html_writer::start_div('progress flex-grow-1 ml-2', array(
                    'style' => 'margin-bottom: 0; height: 8px; margin-left: 5px;'
        ));
        $completiontext = $completion . '%';
        $html .= html_writer::start_div('progress-bar progress-bar-striped bg-primary400', array(
                    'role' => 'progressbar',
                    'data-toggle' => 'tooltip',
                    'data-title' => $progresstext,
                    'data-placement' => 'bottom',
                    'style' => 'width: ' . $completiontext . ';',
                    'aria-valuenow' => $completion,
                    'aria-valuemin' => '0',
                    'aria-valuemax' => '100',
                    'data-original-title' => '',
                    'title' => ''
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::tag('span', $completiontext, array(
                    'class' => 'page-color ml-2 h5 text-primary300 mb-0',
                    'style' => 'vertical-align: middle;  font-size: 15px; font-family: \'Exo 2\',\'Sans-Serif\';'
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('border-bottom-1 border-dark mt-2');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('flex d-flex flex-column mt-3', array(
                    'id' => 'numbers'
        ));
        $html .= html_writer::start_div('d-flex align-items-center mb-2', array(
                    'id' => 'dashboardcoins'
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-key'
        ));
        $html .= html_writer::end_tag('i');
        $keystext = get_string('keys', 'format_specialization');
        $html .= html_writer::tag('strong', $keystext, array(
                    'class' => 'text-white-70'
        ));
        require_once($CFG->dirroot . '/local/dashboard/lib.php');
        $numkeys = local_dashboard_get_num_keys($USER);
        $html .= html_writer::tag('span', $numkeys, array(
                    'class' => 'ml-2 mb-0 h5 page-color',
                    'style' => "vertical-align: middle;  font-size: 15px; font-family: 'Exo 2','Sans-Serif';"
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('d-flex align-items-center mb-2', array(
                    'id' => 'dashboardgrade'
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-bar-chart'
        ));
        $html .= html_writer::end_tag('i');
        $gradetext = get_string('grade', 'format_specialization');
        $html .= html_writer::tag('strong', $gradetext, array(
                    'class' => 'text-white-70'
        ));
        $gradepercentage = format_specialization::get_student_grade($USER->id, $COURSE->id);
        $html .= html_writer::tag('span', $gradepercentage . '%', array(
                    'class' => 'ml-2 h5 page-color mb-0',
                    'style' => "vertical-align: middle;  font-size: 15px; font-family: 'Exo 2','Sans-Serif';"
        ));
        $html .= html_writer::end_div();
        $userbadges = local_dashboard_get_user_badges($USER->id);
        $html .= html_writer::start_div('d-flex align-items-center mb-2', array(
                    'id' => 'dashboardbadges'
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-star'
        ));
        $html .= html_writer::end_tag('i');
        $badgestext = get_string('badges', 'format_specialization');
        $html .= html_writer::tag('strong', $badgestext, array(
                    'class' => 'text-white-70'
        ));
        $html .= html_writer::start_div('avatar-group ml-2');
        foreach ($userbadges as $userbadge) {
            // Validate the badge image URL through clean_param(PARAM_URL) so
            // anything like "javascript:" or unexpected schemes is rejected.
            $rawurl = isset($userbadge->imageurl) ? (string) $userbadge->imageurl : '';
            $imageurl = clean_param($rawurl, PARAM_URL);
            $badgedesc = isset($userbadge->badgedesc)
                ? format_string($userbadge->badgedesc, true, ['context' => context_system::instance()])
                : '';
            $html .= html_writer::start_div('avatar avatar-sm', array(
                        'data-toggle' => 'tooltip',
                        'data-title' => $badgedesc,
                        'data-placement' => 'bottom',
                        'data-original-title' => '',
                        'title' => ''
            ));
            if ($imageurl !== '') {
                $html .= html_writer::tag('img', '', array(
                            'src' => $imageurl,
                            'alt' => 'badge',
                            'class' => 'avatar-img rounded-circle border-dark border-1 bg-primary900'
                ));
            }
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Profile card END -->';
        return $html;
    }

    /**
     * Render course in specialization format
     * 
     * @param stdClass $course Specializatkion course to be displayed
     */
    function render_specialization(stdClass $course) {
        global $PAGE, $USER;

        $html = '<!-- Start course content -->';
        $safedomid = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $course->shortname);
        $html .= html_writer::start_div('page-content', array(
                    'id' => 'course-' . $safedomid
        ));
        $html .= html_writer::start_div('page-section  mt-64pt', array(
                    'id' => 'specialization-header',
                    'style' => 'height: 320px; background-color: inherit;'
        ));
        $html .= html_writer::start_div('container page__container h-100 justify-content-center align-items-center narrow-page');
        $html .= html_writer::start_div('d-flex flex-column flex-lg-row align-items-center h-100');
        $html .= html_writer::start_div('d-flex flex-column flex-md-row align-items-center  flex  mb-lg-0 text-center text-md-left');
        $html .= html_writer::start_div('avatar avatar-xxl   mb-md-0 mr-md-32pt rounded-circle', array(
                    'style' => 'transform: scale(1.3);'
        ));
        $specializationnumber = $this->normalize_eixo_number($course->shortname);
        $html .= html_writer::tag('img', '', array(
                    'src' => $this->output->image_url($specializationnumber . 'number', 'format_specialization'),
                    'class' => 'avatar avatar-xxl rounded rounded-circle',
                    'alt' => 'lesson'
        ));
        $html .= html_writer::start_div('overlay__content');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('flex measure-lead-max', array(
                    'style' => 'max-width: 40rem; margin-right: 5rem;'
        ));
        $html .= html_writer::start_div('flex d-flex flex-row align-items-center', array(
                    'id' => 'specialization-title'
        ));
        $coursecontext = context_course::instance($course->id);
        $html .= html_writer::start_tag('h1', array(
                    'class' => 'measure-lead-max mb-0pt text-primary200',
                    'style' => 'padding-bottom: 1rem;'
        ));
        $html .= get_string('youarein', 'format_specialization') . " "
                . format_string($course->fullname, true, ['context' => $coursecontext]);
        $html .= html_writer::end_tag('h1');
        $html .= html_writer::end_div();
        $html .= html_writer::start_tag('small', array(
                    'class' => 'w-25 text-white-70',
                    'id' => 'specialization-summary',
                    'style' => 'font-size: .8125rem; font-weight: 400;'
        ));
        $summaryformat = isset($course->summaryformat) ? $course->summaryformat : FORMAT_HTML;
        $html .= format_text($course->summary, $summaryformat, ['context' => $coursecontext, 'noclean' => false]);
        $html .= html_writer::end_tag('small');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('d-flex flex-column flex-sm-row align-items-center justify-content-start');
        // Use a real button + data attribute instead of "javascript:void(0)" so
        // a strict CSP (script-src 'self') doesn't block the back action.
        $html .= html_writer::start_tag('button', array(
                    'type' => 'button',
                    'class' => 'btn btn-clear btn-rounded mb-16pt mb-sm-0 mr-sm-16pt',
                    'data-action' => 'history-back'
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-chevron-left fa-fw navicon mr-1'
        ));
        $html .= html_writer::end_tag('i');
        $html .= get_string('back');
        $html .= html_writer::end_tag('button');
        if (has_capability('moodle/course:update', $coursecontext, $USER)) {
            $isediting = $PAGE->user_is_editing();
            $edittxt = ($isediting ? get_string('turneditingoff') : get_string('turneditingon'));
            $editurl = new moodle_url('/course/view.php', array(
                'id' => $course->id,
                'edit' => ($isediting ? 'off' : 'on'),
                'sesskey' => sesskey()
            ));
            $html .= html_writer::tag('a', $edittxt, array(
                        'href' => $editurl,
                        'class' => 'btn btn-clear btn-rounded ml-2',
                        'style' => 'text-wrap: nowrap;',
                        'title' => $edittxt
            ));
        }
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $formattedcourse = course_get_format($course)->get_course();
        $html .= $this->print_course_sections($formattedcourse);
        $html .= $this->print_profile_card();
        $html .= html_writer::end_div();
        $html .= '<!-- Finish course content -->';
        echo $html;

        return;
    }

    /**
     * Render a control to add a course to this specialization
     * 
     * @param stdClass $specialization Current specialization course
     * @param int $pos Position of course to be added
     */
    function render_add_control(stdClass $specialization, int $pos) {
        echo html_writer::start_div('addcourse');
        echo html_writer::start_tag('form', array(
            'method' => 'post',
            'class' => 'form-inline',
            'id' => 'addcourse-' . $pos,
            'action' => new moodle_url('/course/format/specialization/managecourse.php'),
            'data-autosubmit' => '1',
        ));
        echo html_writer::tag('input', '', array('name' => 'id', 'type' => 'hidden', 'value' => $specialization->id));
        echo html_writer::tag('input', '', array('name' => 'op', 'type' => 'hidden', 'value' => 'add'));
        echo html_writer::tag('input', '', array('name' => 'pos', 'type' => 'hidden', 'value' => $pos));
        echo html_writer::tag('input', '', array('name' => 'sesskey', 'type' => 'hidden', 'value' => sesskey()));
        echo html_writer::tag('span', get_string('addcourse', 'format_specialization'), array('class' => 'addtitle'));
        echo html_writer::start_tag('select', array(
            'id' => 'selection-' . $pos,
            'name' => 'component',
            'class' => 'custom-select singleselect courseselection',
            'data-action' => 'autosubmit',
        ));
        echo html_writer::tag('option', get_string('add', 'format_specialization'), array('data-ignore' => '', 'selected' => ''));
        $courses = format_specialization::get_non_specialization_courses($specialization);
        $coursecontext = context_course::instance($specialization->id);
        foreach ($courses as $course) {
            echo html_writer::tag(
                'option',
                format_string($course->fullname, true, ['context' => $coursecontext]),
                array('value' => $course->id)
            );
        }
        echo html_writer::end_tag('select');
        echo html_writer::end_tag('form');
        echo html_writer::end_div();
    }

    /**
     * Render a specialization component
     * 
     * @param stdClass $component Course to be rendered
     * @param bool $showstatus Show or hide course status
     * @param bool $aslink Show as link or not
     */
    protected function render_course(stdClass $component, stdClass $specialization, $showstatus = true, $aslink = true) {
        global $CFG, $OUTPUT, $USER;
        // course_in_list was deprecated in Moodle 3.6 in favour of
        // core_course_list_element. Both still resolve via class alias today,
        // but the alias will be removed; use the canonical class name.
        $course = new core_course_list_element($component);
        echo html_writer::start_tag('div', array('class' => 'course'));
        if ($this->page->user_is_editing() && $aslink) {
            $this->render_remove_control($specialization, $component);
        }
        $courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
        $enrolinstances = enrol_get_instances($course->id, true);
        $activeinstance = null;
        foreach ($enrolinstances as $enrolinstance) {
            if ($enrolinstance->status == ENROL_INSTANCE_ENABLED && $enrolinstance->enrol != 'manual') {
                $activeinstance = $enrolinstance;
                break;
            }
        }
        if ($activeinstance) {
            $coursedetailsurl = new moodle_url('/enrol/' . $activeinstance->enrol . '/view.php', array('id' => $course->id));
        }
        // Check if we should go to course or to view course details
        $context = context_course::instance($course->id);
        if ($aslink) {
            $gotocourse = $aslink && (is_enrolled($context) || has_capability('moodle/course:view', $context));
            // Link to course
            if ($gotocourse) {
                echo html_writer::start_tag('a', array('href' => $courseurl, 'class' => 'quick_link'));
            } else {
                if ($activeinstance) {
                    echo html_writer::start_tag('a', array('href' => $coursedetailsurl, 'class' => 'quick_link'));
                } else {
                    echo html_writer::start_tag('a', array('href' => "#", 'data-toggle' => 'modal', 'data-target' => '#preview' . $course->id, 'class' => 'quick_link'));
                }
            }
        }
        echo html_writer::start_tag('div', array('class' => 'coursedata'));
        // Image
        $files = $course->get_course_overviewfiles();
        $file = reset($files);
        if ($file) {
            echo html_writer::start_tag('div', array('class' => 'image'));
            $isimage = $file->is_valid_image();
            $imageurl = file_encode_url("$CFG->wwwroot/pluginfile.php", '/' . $file->get_contextid() . '/' . $file->get_component() . '/' .
                    $file->get_filearea() . $file->get_filepath() . $file->get_filename(), !$isimage);
            echo html_writer::empty_tag('img', array('src' => $imageurl, 'alt' => $course->fullname));
            echo html_writer::end_tag('div'); // Image
        }
        // Details
        echo html_writer::start_tag('div', array('class' => 'details'));
        // Name
        echo html_writer::tag('span', format_string($course->fullname, true, ['context' => $context]), array('class' => 'name'));
        // Teachers
        if ($course->has_course_contacts()) {
            echo html_writer::start_tag('ul', array('class' => 'teachers'));
            foreach ($course->get_course_contacts() as $coursecontact) {
                $rolename = isset($coursecontact['rolename']) ? $coursecontact['rolename'] : '';
                $username = isset($coursecontact['username']) ? $coursecontact['username'] : '';
                $name = format_string($rolename) . ': ' . format_string($username);
                echo html_writer::tag('li', $name);
            }
            echo html_writer::end_tag('ul');
        }
        // Summary
        if ($course->has_summary()) {
            $summs = strip_tags(format_text($course->summary, $course->summaryformat ?? FORMAT_HTML, ['context' => $context]));
            $truncsum = mb_strimwidth($summs, 0, 512, "...", 'utf-8');
            echo html_writer::tag('span', $truncsum, array('class' => 'summary'));
        }
        // Info
        echo html_writer::start_tag('div', array('class' => 'info'));
        $workload = $this->get_course_workload($course->id);
        if ($workload) {
            echo html_writer::tag('span', $workload . ' ' . get_string('hours'), array('class' => 'hours'));
        }
        echo html_writer::start_tag('span', array('class' => 'review'));
        $evalvalue = round(courseevaluation::calculate_evaluation($course->id));
        echo courseevaluation::render_evaluation($evalvalue, 'text-accent');
        echo html_writer::end_tag('span');
        echo html_writer::end_tag('div'); // info
        echo html_writer::end_tag('div'); // details
        echo html_writer::end_tag('div'); // coursedata
        // Status
        if (is_enrolled($context) && $showstatus) {
            $this->render_user_status($component, $USER);
        }
        if ($aslink) {
            echo html_writer::end_tag('a'); // Link to course
        }
        echo html_writer::end_tag('div'); // component
    }

    /**
     * Render a remove control associated to a course
     * 
     * @param stdClass $specialization Specialization to which course belongs
     * @param stdClass $component Course to be removed
     */
    protected function render_remove_control(stdClass $specialization, stdClass $component) {
        echo html_writer::start_div('removecourse');
        echo html_writer::start_tag('form', array(
            'method' => 'post',
            'class' => 'form-inline',
            'id' => 'removecourse-' . $component->id,
            'action' => new moodle_url('/course/format/specialization/managecourse.php'),
        ));
        echo html_writer::tag('input', '', array('name' => 'id', 'type' => 'hidden', 'value' => $specialization->id));
        echo html_writer::tag('input', '', array('name' => 'op', 'type' => 'hidden', 'value' => 'del'));
        echo html_writer::tag('input', '', array('name' => 'component', 'type' => 'hidden', 'value' => $component->id));
        echo html_writer::tag('input', '', array('name' => 'sesskey', 'type' => 'hidden', 'value' => sesskey()));
        echo html_writer::tag('button', get_string('removecourse', 'format_specialization'), array('type' => 'submit'));
        echo html_writer::end_tag('form');
        echo html_writer::end_div();
    }

    /**
     * Render user status in a course
     * 
     * @param stdClass $course Course to be considered
     * @param stdClass $user User to be considered
     */
    protected function render_user_status(stdClass $course, stdClass $user) {
        // local_engagement is an optional sibling plugin. Treat it as absent
        // when the class isn't loaded so the renderer can run on installations
        // that don't include it (the dependency is intentionally soft).
        $hasengagement = class_exists('\\Engagement') || class_exists('Engagement');
        echo html_writer::start_tag('div', array('class' => 'userstatus'));
        $table = new html_table();
        if ($hasengagement && Engagement::isEnabled()) {
            $table->head = array(get_string('progress', 'completion'), get_string('engagement', 'local_engagement'), get_string('enrollmentdate', 'format_specialization'), get_string('startdate', 'format_specialization'), get_string('finishdate', 'format_specialization'));
        } else {
            $table->head = array(get_string('progress', 'completion'), get_string('enrollmentdate', 'format_specialization'), get_string('startdate', 'format_specialization'), get_string('finishdate', 'format_specialization'));
        }
        $row = new html_table_row();
        $completionpct = $this->calc_course_completion($user, $course);
        $completionstr = format_float($completionpct * 100, 0);
        $progresshtml = html_writer::start_div('progress-bar bg-success progress-bar-striped', array('style' => 'width: ' . $completionstr . '%'));
        $progresshtml .= $completionstr . '%';
        $progresshtml .= html_writer::end_div();
        $row->cells[] = $progresshtml;
        if ($hasengagement && Engagement::isEnabled()) {
            $engagement = new Engagement($user->id, $course->id);
            $engagementpct = $engagement->indexvalue;
            $engagementstr = format_float($engagementpct * 100, 0);
            $engegamenthtml = html_writer::start_div('progress-bar bg-success progress-bar-striped', array('style' => 'width: ' . $engagementstr . '%'));
            $engegamenthtml .= $engagementstr . '%';
            $engegamenthtml .= html_writer::end_div();
            $row->cells[] = $engegamenthtml;
        }
        $completion = new completion_completion(array('userid' => $user->id, 'course' => $course->id));
        if ($completion->timeenrolled) {
            $timeenrolled = $completion->timeenrolled;
        } else {
            $enrolment = $this->get_last_enrolment($user->id, $course->id);
            $timeenrolled = $enrolment ? $enrolment->timecreated : null;
        }
        $row->cells[] = new html_table_cell($timeenrolled ? userdate($timeenrolled, get_string('strftimedatetimeshort', 'langconfig')) : '');
        $row->cells[] = new html_table_cell($completion->timestarted ? userdate($completion->timestarted, get_string('strftimedatetimeshort', 'langconfig')) : '');
        $row->cells[] = new html_table_cell($completion->timecompleted ? userdate($completion->timecompleted, get_string('strftimedatetimeshort', 'langconfig')) : '');
        $table->data[] = $row;
        echo html_writer::table($table);
        echo html_writer::end_tag('div'); // userstatus        
    }

    /**
     * Retrieve last enrolment of an user in a course
     * 
     * @param int $userid User id
     * @param int $courseid Course id
     * @return stdClass Enrolment or null if no enrolment was found
     */
    public static function get_last_enrolment(int $userid, int $courseid) {
        global $DB;
        // Resolve the student role defensively: if the site renamed/removed
        // the archetype, fall back to the canonical student archetype, and if
        // even that fails, just bail out instead of crashing on $row->id.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        if (!$studentrole) {
            $studentroles = get_archetype_roles('student');
            $studentrole = $studentroles ? reset($studentroles) : null;
        }
        if (!$studentrole) {
            return null;
        }
        $sql = 'SELECT ue.* '
                . 'FROM {user_enrolments} ue '
                . 'WHERE ue.userid = :userid '
                . 'AND ue.enrolid IN ('
                . '     SELECT id '
                . '     FROM {enrol} e '
                . '     WHERE e.courseid = :courseid '
                . '     AND e.courseid IN ('
                . '         SELECT cn.instanceid '
                . '         FROM {role_assignments} ra, {context} cn '
                . '         WHERE ra.contextid = cn.id '
                . '         AND cn.contextlevel = :contextlevel '
                . '         AND ra.userid = ue.userid '
                . '         AND ra.roleid = :roleid '
                . '     )'
                . ' ) '
                . 'ORDER BY ue.timecreated DESC';
        $enrolments = $DB->get_records_sql($sql, array('userid' => $userid, 'courseid' => $courseid, 'contextlevel' => CONTEXT_COURSE, 'roleid' => $studentrole->id));
        if (empty($enrolments)) {
            return null;
        }
        return reset($enrolments);
    }

    /**
     * Get estimated workload for a course
     * 
     * @param int $courseid Id of course to be considered
     * @return int Estimated workload for this course
     */
    protected function get_course_workload(int $courseid) {

        $enrols = enrol_get_plugins(true);
        $enrolinstances = enrol_get_instances($courseid, true);
        $max = 0;
        foreach ($enrolinstances as $instance) {
            if (!isset($enrols[$instance->enrol])) {
                continue;
            }
            $classname = 'enrol_' . $instance->enrol . '_plugin';
            $methodname = 'get_course_workload';
            if (method_exists($classname, $methodname)) {
                $workload = $classname::{$methodname}($courseid);
                if ($workload > $max) {
                    $max = $workload;
                }
            }
        }
        return $max;
    }

    /**
     * Calculate course completion by an user
     * 
     * @param stdClass $user User to whom calculate completion
     * @param stdClass $course Course in which calculate completion
     * @return real Course completion percentage by user
     */
    function calc_course_completion($user, $course) {
        $modulescompleted = 0;
        $modinfo = get_fast_modinfo($course, $user->id);
        $monitorable = 0;
        foreach ($modinfo->cms as $cmid => $cm) {
            if ($this->is_cm_monitorable($cm)) {
                $completed = format_specialization::is_cm_completed($user->id, $cmid);
                $modulescompleted += $completed;
                ++$monitorable;
            }
        }
        if ($monitorable == 0) {
            return null;
        }
        $completionpercentage = round($modulescompleted / $monitorable, 2);
        return $completionpercentage;
    }

    /**
     * Check if a course module is monotorable
     * 
     * @param cm_info $cm Course module info
     */
    function is_cm_monitorable(cm_info $cm) {
        global $DB;
        // Filter by criteriatype: course_completion_criteria.moduleinstance only
        // means "cm.id" when the criteria is COMPLETION_CRITERIA_TYPE_ACTIVITY
        // (4). Without this filter, role/grade/date criteria can collide with
        // cm.ids and contaminate the result.
        require_once($GLOBALS['CFG']->dirroot . '/completion/criteria/completion_criteria.php');
        $sql = 'SELECT id '
                . 'FROM {course_completion_criteria} '
                . 'WHERE moduleinstance = :coursemoduleid '
                . 'AND criteriatype = :criteriatype';
        $record = $DB->get_record_sql($sql, array(
            'coursemoduleid' => $cm->id,
            'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
        ));
        return ($cm->uservisible && $record != null);
    }

    protected function page_title() {
        return get_string('topicoutline');
    }

    public function print_section_navbar($section, $cm) {
        
    }

}

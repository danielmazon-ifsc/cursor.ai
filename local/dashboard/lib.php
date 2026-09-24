<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Local dashboard helper functions.
 *
 * @package   local_dashboard
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

use local_coin\coin_stack;
use local_studypace\studypace;
use local_autobadge\autobadge;

/**
 * local_dashboard_print_banner().
 *
 * @param mixed $user
 */
function local_dashboard_print_banner($user) {
    global $PAGE, $CFG;

    $html = '';
    $html .= '<!-- Banner BEGIN -->';
    $html .= html_writer::start_div('mdk-box mdk-box--bg-black js-mdk-box mb-0', [
                'data-effects' => 'parallax-background blend-background',
                'style' => 'background-color: #7000C4 !important;',
    ]);
    $html .= html_writer::start_div('mdk-box__bg');
    $html .= html_writer::start_div('mdk-box__content animated-gradient', [
                'style' => 'background-size: 400% 400%; position: relative; overflow: hidden; color: #44007A !important;',
    ]);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('mdk-box__content justify-content-center pt-32pt');
    $html .= html_writer::start_div('hero container page__container text-center');
    $html .= html_writer::start_div('hero container page__container text-center');
    $html .= html_writer::start_div('page-section');
    $html .= html_writer::start_div('container page__container d-flex flex-column align-items-center justify-content-center text-center');
    $userpicture = new user_picture($user);
    $userpicture->size = 140;
    $userpictureurl = $userpicture->get_url($PAGE);
    $html .= html_writer::tag('img', '', [
                'src' => $userpictureurl,
                'width' => '140',
                'class' => 'mb-32pt rounded-circle',
                'alt' => 'avatar',
    ]);
    $html .= html_writer::start_div('mb-32pt');
    $html .= html_writer::start_tag('h1', [
                'class' => 'text-white mb-3',
    ]);
    $html .= s(fullname($user));
    $html .= html_writer::end_tag('h1');
    require_once($CFG->dirroot . '/local/profile/lib.php');
    $profile = local_profile_get_last_profile_update($user);
    if ($profile) {
        $html .= html_writer::start_tag('p', [
                    'class' => 'text-shadow text-white-50',
                    'style' => 'font-size: 1.2em; color: #FFD700 !important; font-weight: bold;',
        ]);
        $html .= html_writer::start_tag('strong');
        $html .= get_string('courseduration', 'local_dashboard') . ': ';
        $html .= html_writer::start_tag('b', [
                    'class' => 'text-white-100',
        ]);
        $planmonths = $profile->nummonths;
        $plans = local_profile_get_study_plans();
        if (array_key_exists($planmonths, $plans)) {
            $planname = $plans[$planmonths];
        } else {
            $planname = '';
        }
        $html .= get_string('months', 'local_dashboard', $planmonths);
        $html .= html_writer::end_tag('b');
        $html .= html_writer::end_tag('strong');
        $html .= html_writer::end_tag('p');
        if (!empty($planname)) {
            $html .= html_writer::start_div();
            $html .= html_writer::start_tag('span', [
                        'class' => 'chip chip-light bg-purple100 text-purple600 d-inline-flex align-items-center shine-effect',
                        'data-toggle' => 'tooltip',
                        'data-title' => get_string('coursedurationlong', 'local_dashboard', ['planname' => $planname, 'planmonths' => $planmonths]),
                        'data-placement' => 'bottom',
                        'style' => 'padding: 8px 32px; font-size: 14px; position: relative; overflow: hidden; text-transform: uppercase;',
            ]);
            $html .= html_writer::start_tag('i', [
                        'class' => 'icon fa fa-calendar',
            ]);
            $html .= html_writer::end_tag('i');
            $html .= get_string('planname', 'local_dashboard', $planname);
            $html .= html_writer::end_tag('span');
            $html .= html_writer::end_div();
        }
    }
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= '<!-- Banner END -->';
    return $html;
}

/**
 * local_dashboard_print_notifications().
 *
 * @param mixed $user
 */
function local_dashboard_print_notifications($user) {
    global $CFG;

    $html = '';
    $html .= '<!-- Notifications BEGIN -->';
    $html .= html_writer::start_div('page-separator');
    $html .= html_writer::start_div('page-separator__text');
    $html .= get_string('lastnotifications', 'local_dashboard');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('card', [
                'id' => 'notifications',
    ]);
    $html .= html_writer::start_div('list-group list-group-flush');
    require_once($CFG->dirroot . '/local/notifications/lib.php');
    [, $notifications] = local_notifications_get_most_recent_notifications($user->id, 4 /* $maxnotifications */);
    $systemcontext = context_system::instance();
    foreach ($notifications as $notification) {
        // iconurl is unfiltered user-supplied data in some senders; validate
        // it as a URL before echoing so we don't render javascript: schemes.
        $iconurl = isset($notification->iconurl) ? clean_param((string) $notification->iconurl, PARAM_URL) : '';
        $shortmessage = isset($notification->shortmessage)
            ? format_text((string) $notification->shortmessage, FORMAT_HTML, ['context' => $systemcontext, 'para' => false])
            : '';
        $longmessage = isset($notification->longmessage)
            ? format_text((string) $notification->longmessage, FORMAT_HTML, ['context' => $systemcontext, 'para' => false])
            : '';
        $html .= html_writer::start_div('list-group-item p-3 mt-8pt');
        $html .= html_writer::start_div('row align-items-start');
        $html .= html_writer::start_div('col-md-4 mb-8pt mb-md-0');
        $html .= html_writer::start_div('media align-items-center');
        $html .= html_writer::start_div('media-left mr-12pt');
        $html .= html_writer::start_tag('span', [
                    'class' => 'notification-icon flex-shrink-0',
        ]);
        if ($iconurl !== '') {
            $html .= html_writer::tag('img', '', [
                        'src' => $iconurl,
                        'alt' => '',
                        'class' => 'notification-icon__img rounded-circle',
            ]);
        }
        $html .= html_writer::end_tag('span');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('d-flex flex-column media-body media-middle');
        $html .= html_writer::start_tag('span', [
                    'class' => 'card-title',
        ]);
        $html .= $shortmessage;
        $html .= html_writer::end_tag('span');
        $html .= html_writer::start_tag('small', [
                    'class' => 'text-muted',
        ]);
        $html .= local_notifications_time_to_text($notification->time);
        $html .= html_writer::end_tag('small');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('col mb-8pt mb-md-0');
        $html .= html_writer::start_tag('p', [
                    'class' => 'mb-8pt',
        ]);
        $html .= html_writer::start_tag('span', [
                    'class' => 'text-body',
        ]);
        $html .= html_writer::start_tag('strong');
        $html .= $longmessage;
        $html .= html_writer::end_tag('strong');
        $html .= html_writer::end_tag('span');
        $html .= html_writer::end_tag('p');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
    }
    $html .= html_writer::start_div('card-footer p-8pt border-top-0 text-center');
    $html .= html_writer::start_tag('a', [
                'href' => $CFG->wwwroot . '/local/notifications/view.php',
                'class' => 'btn',
    ]);
    $html .= html_writer::start_tag('i', [
                'class' => 'icon fa fa-bell',
    ]);
    $html .= html_writer::end_tag('i');
    $html .= get_string('seeallnotifications', 'local_dashboard');
    $html .= html_writer::end_tag('a');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= '<!-- Notifications END -->';
    return $html;
}

/**
 * local_dashboard_get_current_level().
 *
 * @param mixed $userid
 * @param mixed $courseid
 */
function local_dashboard_get_current_level($userid, $courseid) {
    return studypace::get_current_level($userid, $courseid);
}

/**
 * local_dashboard_get_current_course().
 *
 * @param mixed $userid
 * @param ?array $formats
 */
function local_dashboard_get_current_course($userid, ?array $formats = null) {
    return studypace::get_current_course($userid, $formats);
}

/**
 * Locate the active Sala de Conferências / Conference Room course, if any.
 *
 * Mirrors the resolver in format_specialization/renderer.php so the dashboard
 * doesn't need to take a hard dependency on either course format plugin. The
 * conference room appears at the end of the learning-journey progression bar
 * as a non-linear, always-available node.
 *
 * @return stdClass|null Course record, or null when no visible conference
 *   room course is available.
 */
function local_dashboard_find_conference_room_course() {
    global $CFG;

    $filter = function ($courses) {
        foreach ($courses as $c) {
            if (!empty($c->visible) || is_siteadmin()) {
                return $c;
            }
        }
        return null;
    };

    $saladelib = $CFG->dirroot . '/course/format/saladeconferencias/lib.php';
    if (file_exists($saladelib)) {
        require_once($saladelib);
        if (class_exists('format_saladeconferencias')) {
            $found = $filter(format_saladeconferencias::get_courses_in_format());
            if ($found) {
                return $found;
            }
        }
    }
    $conflib = $CFG->dirroot . '/course/format/conferenceroom/lib.php';
    if (file_exists($conflib)) {
        require_once($conflib);
        if (class_exists('format_conferenceroom')) {
            $found = $filter(format_conferenceroom::get_courses_in_format());
            if ($found) {
                return $found;
            }
        }
    }
    return null;
}

// Minimum course-total grade (%) required to treat an Eixo as passed.
if (!defined('LOCAL_DASHBOARD_EIXO_MIN_GRADE')) {
    define('LOCAL_DASHBOARD_EIXO_MIN_GRADE', 60.0);
}

/**
 * Course-total grades for one user across several Eixos (one query).
 *
 * @param int $userid
 * @param int[] $courseids
 * @return array<int,float> courseid => finalgrade (missing rows = 0)
 */
function local_dashboard_get_user_eixo_grades(int $userid, array $courseids): array {
    global $DB;

    $courseids = array_values(array_filter(array_map('intval', $courseids)));
    $grades = array_fill_keys($courseids, 0.0);
    if ($userid <= 0 || empty($courseids)) {
        return $grades;
    }

    [$in, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
    $params['userid'] = $userid;
    $params['itemtype'] = 'course';
    $rows = $DB->get_records_sql(
        "SELECT gi.courseid, gg.finalgrade
           FROM {grade_items} gi
           JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
          WHERE gi.itemtype = :itemtype
            AND gi.courseid {$in}",
        $params
    );
    foreach ($rows as $row) {
        $cid = (int) $row->courseid;
        $grades[$cid] = ($row->finalgrade === null) ? 0.0 : (float) $row->finalgrade;
    }
    return $grades;
}

/**
 * Format an Eixo grade for the journey UI.
 *
 * Approval uses the raw grade with a strict threshold (>= 60.0): 59.99999 fails.
 * Display prefers 1 decimal, but adds precision when rounding would wrongly look
 * like the pass mark (e.g. 59.96 → "60,0").
 *
 * @param float $grade
 * @return string
 */
function local_dashboard_format_eixo_grade(float $grade): string {
    $decimals = 1;
    if ($grade < LOCAL_DASHBOARD_EIXO_MIN_GRADE && round($grade, 1) >= LOCAL_DASHBOARD_EIXO_MIN_GRADE) {
        $decimals = 2;
        if (round($grade, 2) >= LOCAL_DASHBOARD_EIXO_MIN_GRADE) {
            $decimals = 5;
        }
    }
    return format_float($grade, $decimals);
}

/**
 * Visual state for one Eixo progress circle.
 *
 * States:
 * - locked: previous Eixo not finished
 * - completed: finished activities, course total >= 60.0, and every graded
 *   section (disciplina) >= 60.0
 * - incomplete: finished activities but course total or a section is below 60.0
 * - available: unlocked and still in progress
 *
 * @param bool $isblocked
 * @param bool $activitiescompleted studypace course completion
 * @param float $grade Course total grade (raw; not rounded before compare)
 * @param bool $sectionsok Every graded section meets the minimum (default true)
 * @return string locked|completed|incomplete|available
 */
function local_dashboard_progress_circle_state(
    bool $isblocked,
    bool $activitiescompleted,
    float $grade,
    bool $sectionsok = true
): string {
    if ($isblocked) {
        return 'locked';
    }
    if ($activitiescompleted) {
        // Strict: only >= 60.0 passes. Do not round before comparing.
        $gradepass = ($grade >= LOCAL_DASHBOARD_EIXO_MIN_GRADE);
        return ($gradepass && $sectionsok) ? 'completed' : 'incomplete';
    }
    return 'available';
}

/**
 * local_dashboard_print_specializations().
 *
 * @param mixed $user
 */
function local_dashboard_print_specializations($user) {
    global $CFG;

    $html = '';
    require_once($CFG->dirroot . '/course/format/specialization/lib.php');
    $specializations = format_specialization::get_courses_in_format(true /* onlyvisible */);
    studypace::preload_user_course_completions((int) $user->id);

    $eixoids = array_map(function ($c) {
        return (int) $c->id;
    }, $specializations);
    $eixogrades = local_dashboard_get_user_eixo_grades((int) $user->id, $eixoids);
    $sectionpass = format_specialization::user_section_grades_pass_map(
        (int) $user->id,
        $eixoids,
        LOCAL_DASHBOARD_EIXO_MIN_GRADE
    );

    // Visual bar width: only Eixos passed with grade + section minima (Sala is separate).
    $numspecializationscompleted = 0;
    foreach ($specializations as $specialization) {
        $cid = (int) $specialization->id;
        if (
            studypace::is_course_completed($cid, $user->id)
                && ($eixogrades[$cid] ?? 0.0) >= LOCAL_DASHBOARD_EIXO_MIN_GRADE
                && array_key_exists($cid, $sectionpass)
                && $sectionpass[$cid] === true
        ) {
            $numspecializationscompleted++;
        }
    }
    $specializationscompletion = round((($numspecializationscompleted * 90) / max(count($specializations), 1)), 0) . '%';
    // Restrict the "current course" lookup to the linear track. The
    // saladeconferencias course is non-linear (always available) and must not
    // drive the right-side "current level" widget.
    $specializationinprogress = local_dashboard_get_current_course($user->id, ['specialization']);
    $currentlevel = $specializationinprogress
        ? local_dashboard_get_current_level($user->id, $specializationinprogress->id)
        : 0;
    $conferenceroom = local_dashboard_find_conference_room_course();

    // The progression-bar panel is now rendered unconditionally: users who
    // haven't enrolled in any Eixo yet still need to see the path ahead, with
    // every Eixo locked until they make progress and the Sala de Conferências
    // always available at the end.
    if (!empty($specializations) || $conferenceroom) {
        $html .= '<!-- Specializations BEGIN -->';
        $html .= html_writer::start_div('col-md-9  h-100 card-group-row__card p-relativen px-0 mb-2', [
                    'style' => 'background: linear-gradient(to right, #fff, #f8f9fa); border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05);',
                    'id' => 'specializations',
        ]);
        $html .= html_writer::start_div('card-body d-flex flex-column py-24pt px-32pt');
        $html .= html_writer::start_div('d-flex align-items-center pb-2');
        $html .= html_writer::start_div('avatar avatar-lg mr-3', [
                    'style' => 'width: 4.5rem; height: 4.5rem;',
        ]);
        $html .= html_writer::start_tag('span', [
                    'class' => 'avatar-title rounded-circle',
                    'style' => 'background-color: rgba(75, 39, 150, 0.1);',
        ]);
        $html .= html_writer::start_tag('i', [
                    'class' => 'icon fa fa-line-chart text-purple500',
                    'style' => 'font-size: 28px; margin-top: -10px;',
        ]);
        $html .= html_writer::end_tag('i');
        $html .= html_writer::end_tag('span');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('flex');
        $html .= html_writer::start_tag('h4', [
                    'class' => 'card-title mb-0',
        ]);
        $html .= get_string('yourlearningjourney', 'local_dashboard');
        $html .= html_writer::end_tag('h4');
        $html .= html_writer::start_tag('p', [
                    'class' => 'card-subtitle text-50',
        ]);
        $html .= get_string('trackyourprogress', 'local_dashboard');
        $html .= html_writer::end_tag('p');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('progression-bar progression-bar--active-accent', [
                    'style' => 'display: flex; flex-flow: column;',
        ]);
        $html .= html_writer::start_div('progress-bar-container');
        $html .= html_writer::start_div('progress-bar-line', [
                    'style' => 'background: rgba(75, 39, 150, 0.1); width: 90%; margin-left: 5%;',
        ]);
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('progress-bar-line progress-bar-line-active align-items-start', [
                    'style' => 'background: linear-gradient(45deg, #4b2796, #6937b5); margin-left: 5%; width: ' . $specializationscompletion . ';',
        ]);
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('progress-bar-circles');
        foreach ($specializations as $specialization) {
            $cid = (int) $specialization->id;
            $activitiescompleted = studypace::is_course_completed($cid, $user->id);
            $isblocked = !studypace::all_previous_courses_completed($specializations, $cid, $user->id);
            $grade = (float) ($eixogrades[$cid] ?? 0.0);
            $sectionsok = array_key_exists($cid, $sectionpass) && $sectionpass[$cid] === true;
            $state = local_dashboard_progress_circle_state($isblocked, $activitiescompleted, $grade, $sectionsok);
            $iscurrent = ($specializationinprogress && ($cid == $specializationinprogress->id));
            $speccontext = context_course::instance($cid);
            $specname = format_string($specialization->fullname, true, ['context' => $speccontext]);
            $specializationlink = new moodle_url('/course/view.php', [
                'id' => $cid,
            ]);
            $clickable = ($state !== 'locked');
            if ($clickable) {
                $html .= html_writer::start_tag('a', [
                            'href' => $specializationlink,
                            'class' => 'progress-circle-link',
                ]);
            } else {
                $html .= html_writer::start_tag('span', [
                            'class' => 'progress-circle-link',
                ]);
            }
            switch ($state) {
                case 'completed':
                    $tooltip = get_string('completedalllevels', 'local_dashboard', $specname);
                    $circleclass = 'progress-circle completed';
                    $circlestyle = 'background: linear-gradient(45deg, #4b2796, #6937b5); box-shadow: 0 3px 6px rgba(75, 39, 150, 0.2);';
                    $iconclass = 'icon fa fa-check';
                    $labelclass = 'progress-circle-label bg-purple100';
                    $labelstyle = '';
                    break;
                case 'incomplete':
                    $tooltip = get_string('belowmingrade', 'local_dashboard', $specname);
                    $circleclass = 'progress-circle incomplete';
                    $circlestyle = 'background-color: #e6a23c; box-shadow: 0 3px 6px rgba(230, 162, 60, 0.35);';
                    $iconclass = 'icon fa fa-exclamation-triangle';
                    $labelclass = 'progress-circle-label incomplete';
                    $labelstyle = '';
                    break;
                case 'available':
                    $tooltip = $iscurrent
                            ? get_string('currentspecialization', 'local_dashboard', [
                                'level' => $currentlevel,
                                'specializationname' => $specname,
                            ])
                            : get_string('available', 'local_dashboard', $specname);
                    $circleclass = 'progress-circle available';
                    $circlestyle = 'background-color: #77c13a;';
                    $iconclass = 'icon fa fa-play';
                    $labelclass = 'progress-circle-label available';
                    $labelstyle = 'background-color: rgba(40, 167, 69, 0.1);';
                    break;
                case 'locked':
                default:
                    $tooltip = get_string('locked', 'local_dashboard');
                    $circleclass = 'progress-circle locked';
                    $circlestyle = '';
                    $iconclass = 'icon fa fa-lock';
                    $labelclass = 'progress-circle-label locked';
                    $labelstyle = '';
                    break;
            }
            $html .= html_writer::start_div('progress-circle-container align-items-start', [
                        'data-toggle' => 'tooltip',
                        'data-title' => $tooltip,
                        'data-placement' => 'top',
                        'data-type' => 'button',
                        'data-original-title' => '',
                        'title' => '',
            ]);
            $html .= html_writer::start_div($circleclass, [
                        'style' => $circlestyle,
            ]);
            $html .= html_writer::start_tag('i', [
                        'class' => $iconclass,
            ]);
            $html .= html_writer::end_tag('i');
            $html .= html_writer::end_div();
            $html .= html_writer::start_div($labelclass, [
                        'style' => $labelstyle,
            ]);
            $html .= $specname;
            $html .= html_writer::end_div();
            // Grade under the Eixo pill only when unlocked (Sala omits this entirely).
            if ($state !== 'locked') {
                $html .= html_writer::start_div('progress-circle-grade');
                $html .= html_writer::tag('i', '', [
                    'class' => 'icon fa fa-graduation-cap',
                    'aria-hidden' => 'true',
                ]);
                $html .= html_writer::span(
                    get_string('eixograde', 'local_dashboard', local_dashboard_format_eixo_grade($grade)),
                    'progress-circle-grade-value'
                );
                $html .= html_writer::end_div();
            }
            $html .= html_writer::end_div();
            if ($clickable) {
                $html .= html_writer::end_tag('a');
            } else {
                $html .= html_writer::end_tag('span');
            }
        }
        // Non-linear node: Sala de Conferências. Always available regardless
        // of Eixo progression - students can drop in at any time. We mark it
        // visually with a TV icon (class videos) and a distinct
        // "always open" tooltip so it's obvious it isn't part of the sequential
        // gating.
        if ($conferenceroom) {
            $confcompleted = studypace::is_course_completed($conferenceroom->id, $user->id);
            $confcontext = context_course::instance($conferenceroom->id);
            $confname = format_string($conferenceroom->fullname, true, ['context' => $confcontext]);
            $conflink = new moodle_url('/course/view.php', ['id' => $conferenceroom->id]);
            $conftooltip = $confcompleted
                    ? get_string('confroomcompleted', 'local_dashboard', $confname)
                    : get_string('confroomalwaysavailable', 'local_dashboard', $confname);
            // Conference room is always reachable, so always wrap in <a>.
            $html .= html_writer::start_tag('a', [
                        'href' => $conflink,
                        'class' => 'progress-circle-link',
            ]);
            $html .= html_writer::start_div('progress-circle-container align-items-start', [
                        'data-toggle' => 'tooltip',
                        'data-title' => $conftooltip,
                        'data-placement' => 'top',
                        'data-type' => 'button',
                        'data-original-title' => '',
                        'title' => '',
            ]);
            $html .= html_writer::start_div('progress-circle' . ($confcompleted ? ' completed' : ''), [
                        'style' => $confcompleted
                                ? 'background: linear-gradient(45deg, #4b2796, #6937b5); box-shadow: 0 3px 6px rgba(75, 39, 150, 0.2);'
                                : 'background-color: #0d8aa7;',
            ]);
            $html .= html_writer::start_tag('i', [
                        'class' => 'icon fa ' . ($confcompleted ? 'fa-check' : 'fa-tv'),
            ]);
            $html .= html_writer::end_tag('i');
            $html .= html_writer::end_div();
            $html .= html_writer::start_div('progress-circle-label progress-circle-label--confroom'
                    . ($confcompleted ? ' bg-purple100' : ''), [
                        'style' => $confcompleted ? '' : 'background-color: rgba(13, 138, 167, 0.1);',
            ]);
            $html .= get_string('confroomlabel_line1', 'local_dashboard');
            $html .= html_writer::empty_tag('br');
            $html .= get_string('confroomlabel_line2', 'local_dashboard');
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::end_tag('a');
        }
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('d-flex align-items-center');
        if ($numspecializationscompleted > 0) {
            $html .= html_writer::start_div('badge badge-soft-success mr-2', [
                        'style' => 'background-color: rgba(75, 39, 150, 0.1);',
            ]);
            $html .= html_writer::start_tag('i', [
                        'class' => 'icon fa fa-check-circle text-purple500',
            ]);
            $html .= html_writer::end_tag('i');
            $html .= html_writer::start_tag('span', [
                        'class' => 'text-purple500',
            ]);
            $html .= get_string('specializationscompleted', 'local_dashboard', $numspecializationscompleted);
            $html .= html_writer::end_tag('span');
            $html .= html_writer::end_div();
        }
        if (
            $specializationinprogress
                && !studypace::is_course_completed($specializationinprogress->id, $user->id)
        ) {
            $html .= html_writer::start_div('badge badge-soft-purple', [
                        'style' => 'background-color: rgba(40, 167, 69, 0.1);',
            ]);
            $html .= html_writer::start_tag('i', [
                        'class' => 'icon fa fa-play-circle text-success',
            ]);
            $html .= html_writer::end_tag('i');
            $html .= html_writer::start_tag('span', [
                        'class' => 'text-success',
            ]);
            $progname = format_string(
                $specializationinprogress->fullname,
                true,
                ['context' => context_course::instance($specializationinprogress->id)]
            );
            $html .= get_string('specializationinprogress', 'local_dashboard', $progname);
            $html .= html_writer::end_tag('span');
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Specializations END -->';
    }

    // Right-side "current level" widget is only meaningful when the user is
    // mid-way through an Eixo. When they aren't, the left panel already
    // surfaces the full path - no need to render an empty card.
    if ($specializationinprogress) {
        $html .= '<!-- Current specializations BEGIN -->';
        $html .= html_writer::start_div('col-md-3', [
                    'style' => 'max-height: 251px',
        ]);
        $html .= html_writer::start_div('bg-white card h-100 card-group-row__card p-relative align-items-centercard h-100 card-group-row__card p-relative align-items-center', [
                    'style' => 'background: #ffffff00; border: none;',
        ]);
        $html .= html_writer::start_div('card-body d-flex flex-column align-items-center justify-content-center', [
                    'style' => 'border-radius: 10px;',
        ]);
        $html .= html_writer::start_div('d-flex align-items-center mb-1');
        $html .= html_writer::start_div('avatar avatar-md mr-2');
        $iconupurl = (new moodle_url('/local/dashboard/pix/icon-up.png'))->out(false);
        $html .= html_writer::tag('img', '', [
                    'src' => $iconupurl,
                    'alt' => 'trend',
                    'class' => 'avatar rounded-circle',
        ]);
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('flex');
        $html .= html_writer::start_tag('h1', [
                    'class' => 'h2 mb-0',
        ]);
        $html .= get_string('levelinprogress', 'local_dashboard', $currentlevel);
        $html .= html_writer::end_tag('h1');
        $html .= html_writer::start_tag('p', [
                    'class' => 'mb-0 text-muted',
        ]);
        $html .= html_writer::start_tag('strong', [
                    'class' => 'text-muted',
        ]);
        $progname2 = format_string(
            $specializationinprogress->fullname,
            true,
            ['context' => context_course::instance($specializationinprogress->id)]
        );
        $html .= get_string('ofspecialization', 'local_dashboard', $progname2);
        $html .= html_writer::end_tag('strong');
        $html .= html_writer::end_tag('p');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_tag('p', [
                    'class' => 'text-muted mt-3 mb-0 text-center',
        ]);
        $html .= html_writer::start_tag('strong');
        $html .= get_string('progressdescription', 'local_dashboard', [
            'name' => s($user->firstname),
            'level' => $currentlevel,
            'specialization' => $progname2,
        ]);
        $html .= html_writer::end_tag('strong');
        $html .= html_writer::end_tag('p');
        $newlevels = studypace::get_new_levels_in_a_month($user->id, time());
        if ($newlevels > 0) {
            $html .= html_writer::start_div('badge mt-3 align-self-center pl-2 pr-2', [
                        'style' => 'background-color: rgba(40, 167, 69, 0.1);',
            ]);
            $html .= html_writer::start_tag('i', [
                        'class' => 'icon fa fa-line-chart text-success',
            ]);
            $html .= html_writer::end_tag('i');
            $html .= html_writer::start_tag('span', [
                        'class' => 'text-success',
                        'style' => 'font-size: 11px;',
            ]);
            $html .= get_string('newlevels', 'local_dashboard', $newlevels);
            $html .= html_writer::end_tag('span');
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Current specializations END -->';
    }
    return $html;
}

/**
 * local_dashboard_print_performance().
 *
 * @param mixed $user
 */
function local_dashboard_print_performance($user) {
    $html = '';
    // Defensive cast + clamp: averagegrade can be null when the user has
    // no graded courses (PHP 8.1 turns null into a warning under arithmetic)
    // and can technically exceed 100 if a custom grade item is configured
    // with grademax above 100. The conic-gradient spec ignores >100% but
    // we still clean it up for the readable label.
    $averagegrade = (int) max(0, min(100, (float) format_specialization::get_average_grade($user->id)));
    $html .= '<!-- Performance BEGIN -->';
    $html .= html_writer::start_div('col-lg-6 col-sm-6 d-flex pl-0 mb-2', [
                'id' => 'performance',
    ]);
    $html .= html_writer::start_div('card card-group-row__card text-center o-hidden bg-white mb-0 w-100');
    $html .= html_writer::start_div('card-body d-flex flex-column justify-content-between h-100');
    $html .= html_writer::start_div('circular-progress-container');
    $html .= html_writer::start_div('circular-progress', [
                'style' => 'background: conic-gradient(#42B77A ' . $averagegrade . '%, #e0e0e0 0);',
                'role' => 'progressbar',
                'aria-valuenow' => $averagegrade,
                'aria-valuemin' => '0',
                'aria-valuemax' => '100',
    ]);
    $html .= html_writer::start_div('circular-progress-value');
    $html .= html_writer::start_tag('span', [
                'class' => 'h1 m-0 font-weight-normal',
    ]);
    $html .= $averagegrade;
    $html .= html_writer::end_tag('span');
    $html .= html_writer::tag('span', '%', [
                'class' => 'h4 m-0 font-weight-normal',
    ]);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div();
    $html .= html_writer::start_tag('span', [
                'class' => 'h4 m-0 font-weight-normal mb-1',
    ]);
    $html .= get_string('onaverage', 'local_dashboard');
    $html .= html_writer::end_tag('span');
    $html .= html_writer::start_tag('p', [
                'class' => 'lh-1 text-muted mb-0 pl-3 pr-3',
    ]);
    $html .= html_writer::start_tag('small');
    $html .= get_string('averagedesc', 'local_dashboard');
    $html .= html_writer::end_tag('small');
    $html .= html_writer::end_tag('p');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('d-flex justify-content-center align-items-center mt-3');
    if ($averagegrade > 80) {
        $html .= html_writer::start_tag('i', [
                    'class' => 'icon fa fa-line-chart text-success',
        ]);
        $html .= html_writer::end_tag('i');
        $html .= html_writer::start_tag('span', [
                    'class' => 'text-success',
        ]);
        $html .= get_string('doingwell', 'local_dashboard');
        $html .= html_writer::end_tag('span');
    }
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= '<!-- Performance END -->';
    return $html;
}

/**
 * Coin totals for the dashboard keys card.
 *
 * @param stdClass $user
 * @return array{total:int, month:int}
 */
function local_dashboard_get_coin_totals($user): array {
    static $cache = [];
    $uid = (int) $user->id;
    if (!isset($cache[$uid])) {
        $coinstack = new coin_stack($uid);
        $cache[$uid] = [
            'total' => (int) $coinstack->coins,
            'month' => (int) $coinstack->coins_in_a_month(time()),
        ];
    }
    return $cache[$uid];
}

/**
 * local_dashboard_get_num_keys().
 *
 * @param mixed $user
 */
function local_dashboard_get_num_keys($user) {
    return local_dashboard_get_coin_totals($user)['total'];
}

/**
 * local_dashboard_get_num_keys_in_month().
 *
 * @param mixed $user
 */
function local_dashboard_get_num_keys_in_month($user) {
    return local_dashboard_get_coin_totals($user)['month'];
}

/**
 * local_dashboard_print_keys().
 *
 * @param mixed $user
 */
function local_dashboard_print_keys($user) {
    $html = '';
    $html .= '<!-- Keys BEGIN -->';
    $html .= html_writer::start_div('flex-grow-1 mb-3', [
                'id' => 'keys',
    ]);
    $html .= html_writer::start_div('card card-group-row__card card-body h-100', [
                'style' => 'position: relative; overflow: hidden; z-index: 0; '
                    . 'background: linear-gradient(45deg, #4b2796, #6937b5); border: none; '
                    . 'box-shadow: 0 4px 15px rgba(75, 39, 150, 0.2);',
    ]);
    $html .= html_writer::start_div('d-flex align-items-center');
    $html .= html_writer::start_div('flex mr-3');
    $html .= html_writer::start_div('d-flex align-items-center mb-2');
    $html .= html_writer::start_div('avatar avatar-lg mr-3');
    $html .= html_writer::start_tag('span', [
                'class' => 'avatar-title rounded-circle border-2',
                'style' => 'border: 1px solid rgba(255,255,255,0.7) !important; background: transparent;',
    ]);
    $html .= html_writer::start_tag('i', [
                'class' => 'icon fa fa-key',
                'style' => 'font-weight: bold; font-size: 20px; margin-left: 5px;',
    ]);
    $html .= html_writer::end_tag('i');
    $html .= html_writer::end_tag('span');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('d-flex flex-column');
    $html .= html_writer::start_div('h1 mb-0 text-white', [
                'style' => 'font-size: 2.5rem;',
    ]);
    $html .= local_dashboard_get_num_keys($user);
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('d-flex align-items-center');
    $html .= html_writer::start_tag('p', [
                'class' => 'mb-0',
    ]);
    $html .= html_writer::start_tag('strong', [
                'class' => 'text-white',
    ]);
    $html .= get_string('keys', 'local_dashboard');
    $html .= html_writer::end_tag('strong');
    $html .= html_writer::end_tag('p');
    $newkeys = local_dashboard_get_num_keys_in_month($user);
    if ($newkeys > 0) {
        $html .= html_writer::start_div('badge badge-soft-success ml-2', [
                    'style' => 'background-color: rgba(255,255,255,0.1);',
        ]);
        $html .= html_writer::start_tag('i', [
                    'class' => 'icon fa fa-line-chart text-white',
        ]);
        $html .= html_writer::end_tag('i');
        $html .= html_writer::start_tag('span', [
                    'class' => 'text-white',
        ]);
        $html .= get_string('newthismonth', 'local_dashboard', $newkeys);
        $html .= html_writer::end_tag('span');
        $html .= html_writer::end_div();
    }
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $iconkeyurl = (new moodle_url('/local/dashboard/pix/icon-key.png'))->out(false);
    $html .= html_writer::start_div('card__overlay-background', [
                'style' => 'background-image: url(' . $iconkeyurl . '); opacity: 0.7; '
                    . 'position: absolute; top: 0; right: 0; bottom: 0; left: 0; '
                    . 'background-size: 100px; background-position: right bottom; background-repeat: no-repeat;',
    ]);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= '<!-- Keys END -->';
    return $html;
}

/**
 * Number of active students that make up the ranking denominator.
 *
 * Counted entirely in the database (COUNT(DISTINCT ...)) so we never pull the
 * full id list into PHP. Excludes deleted/suspended users and the guest
 * account, otherwise promoted-then-deleted students keep inflating "N
 * students". Cached site-wide (short TTL) because the value is identical for
 * every dashboard hit.
 *
 * @return int
 */
function local_dashboard_get_student_count() {
    global $DB, $CFG;

    $cache = \cache::make('local_dashboard', 'studentids');
    $cached = $cache->get('count');
    if ($cached !== false) {
        return (int) $cached;
    }

    $sql = 'SELECT COUNT(DISTINCT ra.userid)
              FROM {role_assignments} ra
              JOIN {role} r ON r.id = ra.roleid
              JOIN {user} u ON u.id = ra.userid
             WHERE r.shortname = :studentrole
               AND u.deleted = 0
               AND u.suspended = 0
               AND u.id <> :guestid';
    $count = (int) $DB->count_records_sql($sql, [
        'studentrole' => 'student',
        'guestid' => $CFG->siteguest ?? 1,
    ]);
    $cache->set('count', $count);
    return $count;
}

/**
 * Site-wide coin-total distribution for all active students, sorted DESCENDING.
 *
 * This is the scalability keystone of the ranking widget. The coin total of
 * every eligible student is aggregated ONCE per cache window and cached
 * site-wide (the value is identical for every viewer), so a burst of
 * simultaneous dashboard loads (e.g. a whole cohort logging in at once) reads
 * a cached array instead of each running its own full {coin_stack} aggregate.
 *
 * The population matches local_dashboard_get_student_count() exactly (role
 * 'student', not deleted/suspended, not guest), and a student with no coins is
 * included as 0 via the LEFT JOIN — so count($distribution) == student count.
 * The DISTINCT-students subquery joined to a PRE-AGGREGATED coin sum avoids the
 * row multiplication a direct join would cause. PostgreSQL/MySQL-safe.
 *
 * Memory: one int per student (~10k ints ≈ tens of KB), trivially cacheable.
 *
 * @return int[] Coin totals, highest first.
 */
function local_dashboard_get_coin_distribution() {
    global $DB, $CFG;

    $cache = \cache::make('local_dashboard', 'coinranking');
    $cached = $cache->get('distribution');
    if (is_array($cached)) {
        return $cached;
    }

    $sql = 'SELECT COALESCE(c.coins, 0) AS total
              FROM (
                    SELECT DISTINCT ra.userid
                      FROM {role_assignments} ra
                      JOIN {role} r ON r.id = ra.roleid
                      JOIN {user} u ON u.id = ra.userid
                     WHERE r.shortname = :studentrole
                       AND u.deleted = 0
                       AND u.suspended = 0
                       AND u.id <> :guestid
                   ) s
         LEFT JOIN (
                    SELECT userid, SUM(coins) AS coins
                      FROM {coin_stack}
                  GROUP BY userid
                   ) c ON c.userid = s.userid';
    $totals = $DB->get_fieldset_sql($sql, [
        'studentrole' => 'student',
        'guestid' => $CFG->siteguest ?? 1,
    ]);
    $totals = array_map('intval', $totals);
    rsort($totals, SORT_NUMERIC);
    $cache->set('distribution', $totals);
    return $totals;
}

/**
 * Compute a user's 1-based ranking position based on total coin balance.
 *
 * NOTE: the student-facing widget ranks by COINS ONLY (classic "1224" ranking:
 * students with the same coin total share a position). The consolidated
 * final-grade tie-break exists only in the admin report (see
 * local_dashboard_get_sorted_ranking()).
 *
 * Scalability: the heavy site-wide work is done once in
 * local_dashboard_get_coin_distribution() (cached); here we just binary-search
 * the cached, pre-sorted list — O(log n) — plus one cheap indexed lookup of the
 * user's own total. So a cohort logging in together costs a single aggregate
 * per cache window instead of thousands.
 *
 * @param int $userid The current user.
 * @return int 1-based ranking position (1 = top).
 */
function local_dashboard_get_ranking_position($userid) {
    global $DB;

    $userid = (int) $userid;
    if ($userid <= 0) {
        return 1;
    }

    // Current user's total balance (indexed lookup by userid — cheap).
    $mycoins = (int) $DB->get_field_sql(
        'SELECT COALESCE(SUM(coins), 0) FROM {coin_stack} WHERE userid = :userid',
        ['userid' => $userid]
    );

    $distribution = local_dashboard_get_coin_distribution();

    // Count students strictly ahead via binary search on the descending list:
    // find the first index whose total is <= mycoins; that index is exactly the
    // number of students with MORE coins than the viewer.
    $lo = 0;
    $hi = count($distribution);
    while ($lo < $hi) {
        $mid = ($lo + $hi) >> 1;
        if ($distribution[$mid] > $mycoins) {
            $lo = $mid + 1;
        } else {
            $hi = $mid;
        }
    }

    return $lo + 1;
}

/**
 * Shared SQL for the full ranking table (admin report + CSV export).
 *
 * One active student per row with their TOTAL coin balance, best-first. A
 * DISTINCT-students subquery feeds 1:1 joins to {user} and to a pre-aggregated
 * coin sum, so there is no row multiplication (a student has one role row per
 * course AND one coin row per course) and no outer GROUP BY is needed. Fully
 * parameterised and PostgreSQL/MySQL-safe (ORDER BY on the alias, no MySQL-only
 * functions).
 *
 * @return array [string $sql, array $params]
 */
function local_dashboard_ranking_sql() {
    global $CFG;

    // Lean column list (instead of u.*): just what fullname()/display need, so
    // loading the full student set for the admin table/CSV stays light on a
    // 10k+ student site. All name parts are included so any fullnamedisplay
    // configuration renders correctly.
    $sql = 'SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                   u.middlename, u.alternatename, u.username, u.email,
                   COALESCE(c.coins, 0) AS coins
              FROM (
                    SELECT DISTINCT ra.userid
                      FROM {role_assignments} ra
                      JOIN {role} r ON r.id = ra.roleid
                      JOIN {user} su ON su.id = ra.userid
                     WHERE r.shortname = :studentrole
                       AND su.deleted = 0
                       AND su.suspended = 0
                       AND su.id <> :guestid
                   ) s
              JOIN {user} u ON u.id = s.userid
         LEFT JOIN (
                    SELECT userid, SUM(coins) AS coins
                      FROM {coin_stack}
                  GROUP BY userid
                   ) c ON c.userid = u.id
          ORDER BY coins DESC, u.lastname ASC, u.firstname ASC';
    $params = [
        'studentrole' => 'student',
        'guestid' => $CFG->siteguest ?? 1,
    ];
    return [$sql, $params];
}

/**
 * One page of the ranking table.
 *
 * @param int $limitfrom
 * @param int $limitnum 0 = no limit (avoid on large sites — prefer paging).
 * @return array<int,\stdClass> User rows with a ->coins property, best-first.
 */
function local_dashboard_get_ranking_rows($limitfrom = 0, $limitnum = 0) {
    global $DB;
    [$sql, $params] = local_dashboard_ranking_sql();
    return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
}

/**
 * Visible Eixos (specialization-format courses) in progression order. These
 * define the per-Eixo grade columns of the admin ranking report.
 *
 * @return array<int,\stdClass> Keyed by course id.
 */
function local_dashboard_get_ranking_eixos() {
    global $CFG;
    require_once($CFG->dirroot . '/course/format/specialization/lib.php');
    return format_specialization::get_courses_in_format(true /* onlyvisible */);
}

/**
 * Consolidated per-Eixo grade matrix for the ranking report.
 *
 * Returns [userid][courseid] => grade for every student who has COMPLETED
 * that Eixo (local_studypace course completion). A student who has not
 * completed an Eixo is simply absent for that course, so callers treat a
 * missing entry as 0 — exactly the "nota 0 quando não concluiu" rule.
 *
 * The grade is the course-total final grade (grade_items.itemtype = 'course'),
 * the same value format_specialization::get_student_grade() and
 * studypace::get_course_grade_for_gamification() resolve to.
 *
 * Set-based: one query per Eixo (a handful of them), each pulling only the
 * completed students, so it stays cheap for both the paginated page view
 * (pass the page's user ids) and the full-site CSV export (pass null).
 *
 * @param array<int>|null $userids Restrict to these users; null = all students.
 * @param array<int,\stdClass>|null $eixos Pre-fetched Eixo list (optional).
 * @return array<int,array<int,float>>
 */
function local_dashboard_get_eixo_grade_matrix(array $userids = null, array $eixos = null) {
    global $DB;

    if ($eixos === null) {
        $eixos = local_dashboard_get_ranking_eixos();
    }
    $matrix = [];
    if (empty($eixos)) {
        return $matrix;
    }

    $userfilter = '';
    $userparams = [];
    if ($userids !== null) {
        if (empty($userids)) {
            return $matrix;
        }
        [$insql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'gu');
        $userfilter = " AND sp.userid {$insql}";
    }

    foreach ($eixos as $eixo) {
        $params = $userparams + ['courseid' => $eixo->id];
        // Join grade_items on sp.targetid (not a second :courseid placeholder):
        // Moodle DML allows each named parameter to appear only once in the SQL,
        // and sp.targetid already equals :courseid via the WHERE clause.
        $sql = "SELECT sp.userid, gg.finalgrade
                  FROM {local_studypace} sp
                  JOIN {grade_items} gi ON gi.courseid = sp.targetid AND gi.itemtype = 'course'
             LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = sp.userid
                 WHERE sp.target = 'course'
                   AND sp.targetid = :courseid
                   AND sp.actualcompletion IS NOT NULL
                   {$userfilter}";
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $row) {
            $uid = (int) $row->userid;
            if (!isset($matrix[$uid])) {
                $matrix[$uid] = [];
            }
            $matrix[$uid][(int) $eixo->id] = ($row->finalgrade === null)
                ? 0.0
                : round((float) $row->finalgrade, 1);
        }
        $rs->close();
    }
    return $matrix;
}

/**
 * The per-Eixo grade for a user (0 when the Eixo wasn't completed) plus the
 * summative final grade (sum across all Eixos).
 *
 * @param int $userid
 * @param array<int,\stdClass> $eixos Ordered Eixo list.
 * @param array<int,array<int,float>> $matrix Output of local_dashboard_get_eixo_grade_matrix().
 * @return array [array<int,float> $pereixo keyed by course id, float $final]
 */
function local_dashboard_user_eixo_grades($userid, array $eixos, array $matrix) {
    $pereixo = [];
    $final = 0.0;
    foreach ($eixos as $eixo) {
        $grade = $matrix[(int) $userid][(int) $eixo->id] ?? 0.0;
        $pereixo[(int) $eixo->id] = (float) $grade;
        $final += (float) $grade;
    }
    return [$pereixo, round($final, 1)];
}

/**
 * Comparator implementing the ranking order WITH the grade tie-break:
 *   1) more coins first;
 *   2) on equal coins, the higher consolidated final grade wins (this is the
 *      tie-break the admin asked for — it decides "who is best" inside a coin
 *      tie);
 *   3) only when coins AND final grade are identical do we fall back to a
 *      stable, deterministic order (surname, first name, id) for display.
 *
 * @param \stdClass $a Row with ->coins and ->finalgrade (and name fields).
 * @param \stdClass $b
 * @return int
 */
function local_dashboard_ranking_cmp($a, $b) {
    if ((int) $a->coins !== (int) $b->coins) {
        return (int) $b->coins <=> (int) $a->coins;
    }
    $ga = (float) $a->finalgrade;
    $gb = (float) $b->finalgrade;
    if ($ga != $gb) {
        return $gb <=> $ga;
    }
    $c = strcasecmp((string) ($a->lastname ?? ''), (string) ($b->lastname ?? ''));
    if ($c !== 0) {
        return $c;
    }
    $c = strcasecmp((string) ($a->firstname ?? ''), (string) ($b->firstname ?? ''));
    if ($c !== 0) {
        return $c;
    }
    return (int) $a->id <=> (int) $b->id;
}

/**
 * Full student ranking, fully ordered with the grade tie-break, for the admin
 * report and CSV export. Each returned row carries ->coins, ->finalgrade and
 * ->pereixo (course id => grade) so the caller can render without further
 * queries.
 *
 * Admin-only / on-demand, so it loads the whole student set and sorts in PHP:
 * the final grade is a PHP-side sum (not an SQL column), which a paginated SQL
 * ORDER BY can't express. With lean columns + the cached-free matrix this is
 * fine for 10k+ students under raised memory/time limits.
 *
 * @param array<int,\stdClass>|null $eixos
 * @param array<int,array<int,float>>|null $matrix
 * @return array<int,\stdClass> Ordered best-first (numeric keys).
 */
function local_dashboard_get_sorted_ranking(array $eixos = null, array $matrix = null) {
    global $DB;

    if ($eixos === null) {
        $eixos = local_dashboard_get_ranking_eixos();
    }
    if ($matrix === null) {
        $matrix = local_dashboard_get_eixo_grade_matrix(null, $eixos);
    }

    [$sql, $params] = local_dashboard_ranking_sql();
    $rows = $DB->get_records_sql($sql, $params);
    foreach ($rows as $row) {
        [$pereixo, $final] = local_dashboard_user_eixo_grades($row->id, $eixos, $matrix);
        $row->coins = (int) $row->coins;
        $row->pereixo = $pereixo;
        $row->finalgrade = $final;
    }
    $ordered = array_values($rows);
    usort($ordered, 'local_dashboard_ranking_cmp');
    return $ordered;
}

/**
 * Stream the entire ranking as a CSV download (admins only — the caller page
 * enforces the capability). Rows are emitted in the SAME order as the on-screen
 * table (coins desc, then consolidated final grade desc as the tie-break), so
 * the export's position numbering matches the report.
 *
 * Sends headers and writes to php://output; the caller must exit() afterwards.
 *
 * @return void
 */
function local_dashboard_send_ranking_csv() {
    $eixos = local_dashboard_get_ranking_eixos();
    $ordered = local_dashboard_get_sorted_ranking($eixos);

    $filename = 'ranking-' . userdate(time(), '%Y%m%d-%H%M') . '.csv';

    // Discard any buffered output so the CSV isn't corrupted by stray HTML.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM so Excel opens accented names correctly.
    fwrite($out, "\xEF\xBB\xBF");

    $header = [
        get_string('rankingcolposition', 'local_dashboard'),
        get_string('rankingcolname', 'local_dashboard'),
        get_string('rankingcolusername', 'local_dashboard'),
        get_string('rankingcolemail', 'local_dashboard'),
        get_string('rankingcolcoins', 'local_dashboard'),
    ];
    foreach ($eixos as $eixo) {
        $header[] = $eixo->shortname;
    }
    $header[] = get_string('rankingcolfinalgrade', 'local_dashboard');
    fputcsv($out, $header);

    $position = 0;
    foreach ($ordered as $row) {
        $position++;
        $line = [
            $position,
            fullname($row),
            $row->username,
            $row->email,
            (int) $row->coins,
        ];
        foreach ($eixos as $eixo) {
            // Dot decimal + unquoted so the CSV stays machine-readable.
            $line[] = number_format($row->pereixo[(int) $eixo->id], 1, '.', '');
        }
        $line[] = number_format($row->finalgrade, 1, '.', '');
        fputcsv($out, $line);
    }
    fclose($out);
}

/**
 * local_dashboard_print_ranking().
 *
 * @param mixed $user
 */
function local_dashboard_print_ranking($user) {
    $html = '';
    $html .= '<!-- Ranking BEGIN -->';
    $html .= html_writer::start_div('flex-grow-1');
    $html .= html_writer::start_div('card card-group-row__card h-100', [
                'style' => 'background: linear-gradient(45deg, #2b215a, #4b2796); border: none; box-shadow: 0 4px 15px rgba(75, 39, 150, 0.2);',
    ]);
    $html .= html_writer::start_div('card-body d-flex align-items-center justify-content-center h-100', [
                'style' => 'background-color: transparent;',
    ]);
    $html .= html_writer::start_div('d-flex align-items-center justify-content-center w-100');
    $html .= html_writer::start_div('avatar avatar-lg mr-3');
    $html .= html_writer::start_tag('span', [
                'class' => 'avatar-title rounded-circle border-2',
                'style' => 'border: 1px solid rgba(255,255,255,0.7) !important; background: transparent;',
    ]);
    $html .= html_writer::start_tag('i', [
                'class' => 'icon fa fa-trophy',
                'style' => 'font-weight: bold; font-size: 20px; margin-left: 5px;',
    ]);
    $html .= html_writer::end_tag('i');
    $html .= html_writer::end_tag('span');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('flex');
    $html .= html_writer::start_tag('h1', [
                'class' => 'h1 mb-0 text-white',
    ]);
    $numstudents = local_dashboard_get_student_count();
    $position = local_dashboard_get_ranking_position($user->id);
    $html .= $position . 'º';
    $html .= html_writer::end_tag('h1');
    $html .= html_writer::start_tag('p', [
                'class' => 'mb-0 text-white-50',
    ]);
    $html .= html_writer::start_tag('strong', [
                'class' => 'text-white',
    ]);
    $html .= get_string('place', 'local_dashboard');
    $html .= html_writer::end_tag('strong');
    $html .= get_string('amongstudents', 'local_dashboard', $numstudents);
    $html .= html_writer::end_tag('p');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('card-body d-flex flex-column align-items-center justify-content-center border-top', [
                'style' => 'border-color: rgba(255,255,255,0.1) !important; background-color: transparent;',
    ]);
    $html .= html_writer::start_div('d-flex align-items-center');
    $html .= html_writer::start_div('d-flex align-items-center');
    $html .= html_writer::start_tag('i', [
                'class' => 'icon fa fa-group text-white-50 mr-32pt',
    ]);
    $html .= html_writer::end_tag('i');
    $html .= html_writer::start_div('text-white-50');
    $html .= html_writer::start_div('text-white', [
                'style' => 'white-space: nowrap;',
    ]);
    $studentpercentil = $numstudents > 0 ? (int) round(($position * 100) / $numstudents, 0) : 0;
    $html .= 'Top ' . $studentpercentil . '%';
    $html .= html_writer::end_div();
    $html .= get_string('similarperformance', 'local_dashboard');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= '<!-- Ranking END -->';
    return $html;
}

/**
 * local_dashboard_print_progress().
 *
 * @param mixed $user
 */
function local_dashboard_print_progress($user) {
    global $CFG;

    $html = '';
    $html .= '<!-- Progress BEGIN -->';
    $html .= html_writer::start_div('card card-body', [
                'id' => 'progress',
    ]);
    $html .= html_writer::start_div('flex d-inline-flex align-items-end');
    $html .= html_writer::start_tag('h1', [
                'style' => 'letter-spacing: -0.08em; font-weight: bold;',
    ]);
    $currentprogress = studypace::get_current_progress($user->id);
    $expectedprogress = studypace::get_expected_progress($user->id);
    $html .= $currentprogress;
    $html .= html_writer::end_tag('h1');
    $html .= html_writer::tag('h2', '%', [
                'class' => 'ml-4pt',
    ]);
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('flex', [
                'style' => 'max-width: 100%',
    ]);
    $html .= html_writer::start_div('flex', [
                'style' => 'max-width: 100%',
    ]);
    $html .= html_writer::start_div('progress');
    $missingprogress = ($expectedprogress - $currentprogress);
    $onpace = ($missingprogress <= 0);
    $currentbarstyle = 'width: ' . $currentprogress . '%';
    if ($onpace) {
        // On track ("Mantenha o ritmo!"): green bar.
        $currentbarstyle .= '; background-color: #42B77A !important';
    }
    $html .= html_writer::start_div($onpace ? 'progress-bar' : 'progress-bar bg-accent', [
                'role' => 'progressbar',
                'style' => $currentbarstyle,
                'aria-valuenow' => '15',
                'aria-valuemin' => '0',
                'aria-valuemax' => '100',
                'data-toggle' => 'tooltip',
                'data-title' => $currentprogress . get_string('coursecompleted', 'local_dashboard'),
                'data-placement' => 'bottom',
                'data-original-title' => '',
                'title' => '',
    ]);
    $html .= html_writer::end_div();
    if ($missingprogress > 0) {
        $html .= html_writer::start_div('progress-bar bg-purple200', [
                    'role' => 'progressbar',
                    'style' => 'width: ' . $missingprogress . '%',
                    'aria-valuenow' => '30',
                    'aria-valuemin' => '0',
                    'aria-valuemax' => '100',
                    'data-toggle' => 'tooltip',
                    'data-title' => get_string('expectedprogress', 'local_dashboard', $expectedprogress),
                    'data-placement' => 'bottom',
                    'data-original-title' => '',
                    'title' => '',
        ]);
        $html .= html_writer::end_div();
    }
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= '<br>';
    $html .= html_writer::start_div('alert alert-soft-warning mb-0 p-8pt');
    $html .= html_writer::start_div('d-flex align-items-start');
    $html .= html_writer::start_div('mr-8pt');
    $html .= html_writer::start_tag('i', [
                'class' => 'icon fa fa-clock-o',
    ]);
    $html .= html_writer::end_tag('i');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('flex');
    require_once($CFG->dirroot . '/local/profile/lib.php');
    $profile = local_profile_get_last_profile_update($user);
    if ($profile) {
        $html .= html_writer::start_tag('small', [
                    'class' => 'text-100',
        ]);
        $plannedmonths = $profile->nummonths;
        if ($missingprogress > 0) {
            $html .= get_string('badpace', 'local_dashboard', [
                'currentprogress' => $currentprogress,
                'months' => $plannedmonths,
                'expectedprogress' => $expectedprogress,
            ]);
        } else {
            $html .= get_string('goodpace', 'local_dashboard', [
                'currentprogress' => $currentprogress,
                'months' => $plannedmonths,
                'expectedprogress' => $expectedprogress,
            ]);
        }
        $html .= html_writer::end_tag('small');
    }
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::div(
        get_string(
            'overallprogresshowto',
            'local_dashboard',
            format_float(LOCAL_DASHBOARD_EIXO_MIN_GRADE, 0)
        ),
        'overall-progress-howto small text-50 mt-8pt mb-0'
    );
    $html .= html_writer::end_div();
    $html .= '<!-- Progress END -->';
    return $html;
}

/**
 * local_dashboard_get_user_badges().
 *
 * @param mixed $userid
 */
function local_dashboard_get_user_badges($userid) {
    return autobadge::get_user_badges($userid);
}

/**
 * local_dashboard_print_badges().
 *
 * @param mixed $user
 */
function local_dashboard_print_badges($user) {
    $html = '';
    $html .= '<!-- Badges BEGIN -->';
    $html .= html_writer::start_div('card card-body pb-32pt', [
                'style' => 'background: linear-gradient(to right, #fff, #f8f9fa); border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05);',
                'id' => 'badges',
    ]);
    $html .= html_writer::start_div('d-flex align-items-center mb-4');
    $html .= html_writer::start_div('avatar avatar-sm mr-3');
    $html .= html_writer::start_tag('span', [
                'class' => 'avatar-title rounded-circle',
                'style' => 'background-color: rgba(75, 39, 150, 0.1);',
    ]);
    $html .= html_writer::start_tag('i', [
                'class' => 'icon fa fa-star ml-2 text-purple500',
    ]);
    $html .= html_writer::end_tag('i');
    $html .= html_writer::end_tag('span');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('flex');
    $html .= html_writer::start_tag('h4', [
                'class' => 'card-title mb-0',
                'style' => 'font-weight: 600;',
    ]);
    $html .= get_string('badgeboard', 'local_dashboard');
    $html .= html_writer::end_tag('h4');
    $html .= html_writer::start_tag('p', [
                'class' => 'card-subtitle text-50',
    ]);
    $html .= get_string('badgedesc', 'local_dashboard');
    $html .= html_writer::end_tag('p');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('card-body d-flex flex-column align-items-center p-0');
    $html .= '<!-- First row BEGIN -->';
    $html .= html_writer::start_div('d-flex justify-content-center mb-32pt');
    $userbadges = local_dashboard_get_user_badges($user->id);
    $systemcontext = context_system::instance();
    $renderbadge = function ($userbadge) use ($systemcontext) {
        $badgedesc = isset($userbadge->badgedesc)
            ? format_string($userbadge->badgedesc, true, ['context' => $systemcontext])
            : '';
        $imageurl = isset($userbadge->imageurl) ? clean_param((string) $userbadge->imageurl, PARAM_URL) : '';
        $out = html_writer::start_div('mx-3 medal-master', [
                    'data-toggle' => 'tooltip',
                    'data-placement' => 'top',
                    'title' => '',
                    'data-original-title' => $badgedesc,
                    'style' => 'font-size: 1.70833rem; width: 5.125rem; height: 5.125rem;',
        ]);
        $out .= html_writer::start_tag('span');
        if ($imageurl !== '') {
            $out .= html_writer::tag('img', '', [
                        'src' => $imageurl,
                        'alt' => 'badge',
                        'class' => 'avatar-img rounded-circle bg-light border-2 medal-glow',
            ]);
        }
        $out .= html_writer::start_div('medal-effect');
        $out .= html_writer::end_div();
        $out .= html_writer::end_tag('span');
        $out .= html_writer::end_div();
        return $out;
    };
    foreach ($userbadges as $userbadge) {
        if ($userbadge->seq < 3) {
            $html .= $renderbadge($userbadge);
        }
    }
    $html .= html_writer::end_div();
    $html .= '<!-- First row END -->';
    $html .= '<!-- Second row BEGIN -->';
    $html .= html_writer::start_div('d-flex justify-content-center');
    foreach ($userbadges as $userbadge) {
        if ($userbadge->seq >= 3) {
            $html .= $renderbadge($userbadge);
        }
    }
    $html .= html_writer::end_div();
    $html .= '<!-- Second row END -->';
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= '<!-- Badges END -->';
    return $html;
}

/**
 * local_dashboard_print_modal().
 *
 */
function local_dashboard_print_modal() {
    $html = '';
    $html .= '<!-- Modal BEGIN -->';
    $html .= html_writer::start_div('custom-modal', [
                'id' => 'customGamificationModal',
    ]);
    $html .= html_writer::start_div('custom-modal-dialog');
    $html .= html_writer::start_div('custom-modal-content');
    $html .= html_writer::start_div('custom-modal-header');
    $html .= html_writer::start_tag('h5', [
                'class' => 'custom-modal-title',
    ]);
    $html .= get_string('platformgamificationrules', 'local_dashboard');
    $html .= html_writer::end_tag('h5');
    $html .= html_writer::start_div('custom-modal-controls');
    $html .= html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'custom-minimize',
                'data-action' => 'gamification-toggle',
    ]);
    $html .= html_writer::start_tag('i', [
                'class' => 'icon fa fa-window-minimize',
    ]);
    $html .= html_writer::end_tag('i');
    $html .= html_writer::end_tag('button');
    $html .= html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'custom-close',
                'data-action' => 'gamification-close',
    ]);
    $html .= html_writer::tag('span', '×');
    $html .= html_writer::end_tag('button');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('custom-modal-body');
    $html .= html_writer::start_tag('p', [
                'class' => 'mb-4',
                'style' => 'color: #272c33;',
    ]);
    $html .= get_string('watchtolearn', 'local_dashboard');
    $html .= html_writer::end_tag('p');
    $html .= html_writer::start_div('embed-responsive embed-responsive-16by9');
    // Note: Viddia Set video frame below similar to in onboarding block.
    $html .= '<iframe width="560" height="315" '
        . 'src="https://www.youtube.com/embed/kAMTBMwdgYQ?si=3JDn1i9o8riFpjZT" '
        . 'title="YouTube video player" frameborder="0" '
        . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; '
        . 'picture-in-picture; web-share" '
        . 'referrerpolicy="strict-origin-when-cross-origin" allowfullscreen="">';
    $html .= '</iframe>';
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('minimized-modal', [
                'id' => 'minimizedModal',
    ]);
    $html .= html_writer::start_div('minimized-modal-content');
    $html .= html_writer::start_tag('i', [
                'class' => 'icon fa fa-gamepad',
    ]);
    $html .= html_writer::end_tag('i');
    $html .= html_writer::start_tag('span');
    $html .= get_string('gamificationrules', 'local_dashboard');
    $html .= html_writer::end_tag('span');
    $html .= html_writer::start_div('minimized-modal-controls');
    $html .= html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'custom-maximize',
                'data-action' => 'gamification-toggle',
    ]);
    $html .= html_writer::start_tag('i', [
                'class' => 'icon fa fa-window-maximize',
    ]);
    $html .= html_writer::end_tag('i');
    $html .= html_writer::end_tag('button');
    $html .= html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'custom-close-mini',
                'data-action' => 'gamification-close',
    ]);
    $html .= html_writer::tag('span', '×');
    $html .= html_writer::end_tag('button');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= '<!-- Modal END -->';
    return $html;
}

/**
 * local_dashboard_print_page().
 *
 * @param mixed $user
 */
function local_dashboard_print_page($user) {
    $html = '';
    $html .= '<!-- Page BEGIN -->';
    $html .= html_writer::start_div('pt-32pt');
    $html .= html_writer::start_div('narrow-page');
    $html .= html_writer::start_div('container page__container d-flex flex-column flex-md-row align-items-center text-center text-sm-left');
    $html .= html_writer::start_div('flex d-flex flex-column flex-sm-row align-items-center mb-24pt mb-md-0');
    $html .= html_writer::start_div('mb-24pt mb-sm-0 mr-sm-24pt');
    $html .= html_writer::start_tag('h2', [
                'class' => 'mb-0',
    ]);
    $html .= get_string('pannel', 'local_dashboard');
    $html .= html_writer::end_tag('h2');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('row', [
                'role' => 'tablist',
    ]);
    $html .= html_writer::start_div('col-auto d-flex');
    // Note: Viddia Enable access to gamification video
    if (1 == 0) {
        $html .= html_writer::start_tag('a', [
                    'href' => '#',
                    'class' => 'mr-2',
        ]);
        $html .= html_writer::start_tag('button', [
                    'type' => 'button',
                    'class' => 'btn btn-outline-primary btn-rounded',
                    'data-action' => 'gamification-open',
        ]);
        $html .= html_writer::start_tag('i', [
                    'class' => 'icon fa fa-gamepad',
        ]);
        $html .= html_writer::end_tag('i');
        $html .= get_string('gamificationrules', 'local_dashboard');
        $html .= html_writer::end_tag('button');
        $html .= html_writer::end_tag('a');
    }
    $html .= html_writer::start_tag('a', [
        'href' => (new moodle_url('/local/profile/edit.php'))->out(false),
    ]);
    $html .= html_writer::start_tag('button', [
                'class' => 'btn btn-outline-accent btn-rounded ml-16pt',
    ]);
    $html .= html_writer::start_tag('i', [
                'class' => 'icon fa fa-gear',
    ]);
    $html .= html_writer::end_tag('i');
    $html .= get_string('preferences', 'local_dashboard');
    $html .= html_writer::end_tag('button');
    $html .= html_writer::end_tag('a');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('container page__container page-section', [
                'style' => 'padding-bottom: 0;',
    ]);
    $html .= html_writer::start_div('page-separator');
    $html .= html_writer::start_div('page-separator__text');
    $html .= get_string('yourprogress', 'local_dashboard');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('row d-flex align-items-stretc');
    $html .= local_dashboard_print_specializations($user);
    $html .= '<!-- Page Content -->';
    $html .= html_writer::start_div('mb-112pt mt-32pt');
    $html .= html_writer::start_div('row flex d-flex');
    $html .= html_writer::start_div('col-lg-7');
    $html .= html_writer::start_div('page-separator');
    $html .= html_writer::start_div('page-separator__text');
    $html .= get_string('overview', 'local_dashboard');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('row mb-64pt');
    $html .= local_dashboard_print_performance($user);
    $html .= html_writer::start_div('col-lg-6 col-md-6 d-flex flex-column');
    $html .= local_dashboard_print_keys($user);
    $html .= local_dashboard_print_ranking($user);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= local_dashboard_print_notifications($user);
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('col-lg-5');
    $html .= html_writer::start_div('page-separator');
    $html .= html_writer::start_div('page-separator__text');
    $html .= get_string('youroverallprogress', 'local_dashboard');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= local_dashboard_print_progress($user);
    $html .= local_dashboard_print_badges($user);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= local_dashboard_print_modal();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= '<!-- Page END -->';
    return $html;
}

/**
 * local_dashboard_view().
 *
 * @param mixed $user
 */
function local_dashboard_view($user) {
    $html = '';
    $html .= local_dashboard_print_banner($user);
    $html .= local_dashboard_print_page($user);
    return $html;
}

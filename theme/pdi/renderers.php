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
 * Theme renderers for theme_pdi.
 *
 * @package   theme_pdi
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/renderer.php');
require_once($CFG->dirroot . '/theme/pdi/lib.php');

use theme_boost\output\core_renderer;
use local_studypace\studypace;

/**
 * Core renderer overrides for the PDI theme.
 *
 * @package   theme_pdi
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_pdi_core_renderer extends core_renderer {
    /** @var mixed $custompages */
    protected $custompages = [
        'page-site-index',
        'page-course-view-specialization',
        'page-local-profile-edit',
        'page-mod-scorm-player',
        'page-course-view-conferenceroom',
        'page-course-view-saladeconferencias',
        'page-course-view-videogallery',
        'page-mod-video-view',
        'page-mod-cquiz-view',
        'page-mod-cquiz-attempt',
        'page-mod-cquiz-review',
        'page-local-notifications-view',
        'page-local-dashboard-view',
    ];
    /** @var mixed $whiteheaderfooterpages */
    protected $whiteheaderfooterpages = [
        'page-mod-cquiz-view',
        'page-mod-cquiz-attempt',
        'page-mod-cquiz-review',
    ];

    /**
     * Pages that use the PDI top navbar (#default-navbar) and custom footer.
     *
     * @return bool
     */
    protected function uses_pdi_custom_chrome(): bool {

        if (in_array($this->page->bodyid, $this->custompages, true)) {
            return true;
        }

        return theme_pdi_is_avaliacao_reacao_page($this->page);
    }

    /**
     * get_course_enrol_url().
     *
     * @param mixed $courseid
     */
    private function get_course_enrol_url($courseid) {
        // Use Moodle's standard enrolment landing page (/enrol/index.php) which
        // discovers the enabled enrolment instances for the course at runtime
        // and renders the appropriate join form (self enrolment, payment, etc.).
        //
        // History: prior versions of this method read the URL from
        // {role_names}.name for the "student" role in the course context. That
        // was a hack — the field is meant for renaming the role per context
        // ("Aluno", "Discípulo"...), not for routing. It also broke when admins
        // forgot to set the override per Eixo (link silently fell back to the
        // site home with ?redirect=0). The native landing page is robust,
        // theme-aware, and respects all of Moodle's enrolment plugins.
        return (new \moodle_url('/enrol/index.php', ['id' => $courseid]))->out(false);
    }

    /**
     * all_previous_courses_completed().
     *
     * @param mixed $courses
     * @param mixed $courseid
     * @param mixed $user
     */
    public static function all_previous_courses_completed($courses, $courseid, $user) {
        return format_specialization::all_previous_courses_completed($courses, $courseid, $user);
    }

    /**
     * get_first_not_enroled_course_id().
     *
     * @param mixed $courses
     * @param mixed $user
     */
    public static function get_first_not_enroled_course_id($courses, $user) {
        return format_specialization::get_first_not_enroled_course_id($courses, $user);
    }

    /**
     * Home certificate area stage: new, inprogress or complete.
     *
     * Cheap checks run first so the front page stays light for ~18k learners
     * who have not finished the programme. Full completion + grade work only
     * runs when every Eixo already has a studypace completion marker.
     *
     * @param int $userid
     * @return string
     */
    private static function home_certificate_stage(int $userid): string {
        global $CFG;

        if ($userid <= 0) {
            return 'new';
        }

        require_once($CFG->dirroot . '/course/format/specialization/lib.php');
        $specializations = format_specialization::get_courses_in_format(true /* onlyvisible */);
        if (empty($specializations)) {
            return 'new';
        }

        if (!self::user_has_started_first_eixo($userid, $specializations)) {
            return 'new';
        }
        if (self::user_can_access_certificate($userid, $specializations)) {
            return 'complete';
        }
        return 'inprogress';
    }

    /**
     * Whether the learner has completed at least one activity in the first Eixo.
     *
     * @param int $userid
     * @param \stdClass[] $specializations Visible specialization courses, ordered.
     * @return bool
     */
    private static function user_has_started_first_eixo(int $userid, array $specializations): bool {
        global $DB;

        $first = reset($specializations);
        if (!$first || empty($first->id)) {
            return false;
        }

        $sql = "SELECT 1
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :courseid
                   AND cmc.userid = :userid
                   AND cm.deletioninprogress = 0
                   AND cmc.completionstate IN (:c1, :c2, :c3)";
        return $DB->record_exists_sql($sql, [
            'courseid' => (int) $first->id,
            'userid' => $userid,
            'c1' => COMPLETION_COMPLETE,
            'c2' => COMPLETION_COMPLETE_PASS,
            'c3' => COMPLETION_COMPLETE_FAIL,
        ]);
    }

    /**
     * Visible Sala de Conferências course, if any (legacy conferenceroom fallback).
     *
     * @return \stdClass|null
     */
    private static function find_conference_room_course(): ?\stdClass {
        global $CFG;

        $pick = static function (array $courses): ?\stdClass {
            foreach ($courses as $course) {
                if (!empty($course->visible)) {
                    return $course;
                }
            }
            return null;
        };

        $saladelib = $CFG->dirroot . '/course/format/saladeconferencias/lib.php';
        if (is_readable($saladelib)) {
            require_once($saladelib);
            if (class_exists('format_saladeconferencias')) {
                $found = $pick(format_saladeconferencias::get_courses_in_format());
                if ($found) {
                    return $found;
                }
            }
        }

        $legacylib = $CFG->dirroot . '/course/format/conferenceroom/lib.php';
        if (is_readable($legacylib)) {
            require_once($legacylib);
            if (class_exists('format_conferenceroom')) {
                return $pick(format_conferenceroom::get_courses_in_format());
            }
        }

        return null;
    }

    /**
     * Whether studypace has marked every given course complete (no live CM re-check).
     *
     * @param int $userid
     * @param int[] $courseids
     * @return bool
     */
    private static function user_has_all_eixo_completion_markers(int $userid, array $courseids): bool {
        global $DB;

        $courseids = array_values(array_filter(array_map('intval', $courseids)));
        if (empty($courseids)) {
            return false;
        }

        [$in, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $params['userid'] = $userid;
        $params['target'] = 'course';
        $count = $DB->count_records_select(
            'local_studypace',
            "userid = :userid AND target = :target AND actualcompletion IS NOT NULL AND targetid {$in}",
            $params
        );
        return (int) $count === count($courseids);
    }

    /**
     * Whether the user can access the certificate action on the home screen.
     *
     * Rules:
     * - Every visible Eixo must be completed with course total grade >= 60.
     * - Every graded section (disciplina with evaluative activity) in each
     *   Eixo must have at least one activity at/above 60%.
     * - Mean of visible Eixo grades must be >= 60.
     * - Visible Sala de Conferências (if any) must be completed with grade >= 60
     *   (all mandatory videos watched — same studypace marker as the dashboard).
     *
     * @param int $userid
     * @param \stdClass[]|null $specializations Optional preloaded visible Eixos.
     * @return bool
     */
    private static function user_can_access_certificate(int $userid, ?array $specializations = null): bool {
        global $CFG;

        if ($userid <= 0) {
            return false;
        }

        require_once($CFG->dirroot . '/course/format/specialization/lib.php');
        if ($specializations === null) {
            $specializations = format_specialization::get_courses_in_format(true /* onlyvisible */);
        }
        if (empty($specializations)) {
            return false;
        }

        $eixoids = [];
        foreach ($specializations as $specialization) {
            $eixoids[] = (int) $specialization->id;
        }
        $eixoids = array_values(array_filter($eixoids));
        if (empty($eixoids)) {
            return false;
        }

        $sala = self::find_conference_room_course();
        $salaid = ($sala && !empty($sala->id)) ? (int) $sala->id : 0;

        // Cheap gate: every required course (Eixos + Sala) must already have a
        // studypace completion marker before we touch grades / preload.
        $requiredids = $eixoids;
        if ($salaid > 0) {
            $requiredids[] = $salaid;
        }
        if (!self::user_has_all_eixo_completion_markers($userid, $requiredids)) {
            return false;
        }

        require_once($CFG->dirroot . '/local/studypace/classes/studypace.php');
        studypace::preload_user_course_completions($userid);

        foreach ($requiredids as $courseid) {
            if (!studypace::is_course_completed($courseid, $userid)) {
                return false;
            }
        }

        $grades = self::eixo_course_grades($userid, $requiredids);
        $sumeixogrades = 0.0;
        foreach ($eixoids as $courseid) {
            $grade = $grades[$courseid] ?? 0.0;
            if ($grade < 60.0) {
                return false;
            }
            $sumeixogrades += $grade;
        }

        if ($salaid > 0 && ($grades[$salaid] ?? 0.0) < 60.0) {
            return false;
        }

        // Same gate as dashboard journey circles: every graded section must
        // have at least one evaluative activity >= 60%. Fail closed.
        if (!method_exists('\\format_specialization', 'user_section_grades_pass_map')) {
            return false;
        }
        $sectionpass = format_specialization::user_section_grades_pass_map($userid, $eixoids, 60.0);
        foreach ($eixoids as $courseid) {
            // Strict bool check (avoid empty() quirks with unexpected types).
            if (!array_key_exists($courseid, $sectionpass) || $sectionpass[$courseid] !== true) {
                return false;
            }
        }

        return ($sumeixogrades / count($eixoids)) >= 60.0;
    }

    /**
     * Course-total grades for several Eixos in one query.
     *
     * @param int $userid
     * @param int[] $courseids
     * @return array<int,float>
     */
    private static function eixo_course_grades(int $userid, array $courseids): array {
        global $DB;

        $courseids = array_values(array_filter(array_map('intval', $courseids)));
        if (empty($courseids)) {
            return [];
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

        $grades = [];
        foreach ($rows as $row) {
            if ($row->finalgrade === null) {
                $grades[(int) $row->courseid] = 0.0;
                continue;
            }
            $grades[(int) $row->courseid] = (float) $row->finalgrade;
        }
        return $grades;
    }

    /**
     * Admin-configured certificate URL, or empty when missing/unsafe.
     *
     * @return string
     */
    private static function configured_certificate_url(): string {
        $raw = trim((string) get_config('theme_pdi', 'certificateurl'));
        if ($raw === '') {
            return '';
        }
        if (preg_match('#^(javascript|data|vbscript):#i', $raw)) {
            return '';
        }
        $parts = parse_url($raw);
        if ($parts === false) {
            return '';
        }
        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
        if ($scheme !== '' && !in_array($scheme, ['http', 'https'], true)) {
            return '';
        }
        if ($scheme === '' && (strpos($raw, '//') === 0 || $raw[0] !== '/')) {
            return '';
        }
        return $raw;
    }

    /**
     * Certificate control on the home screen (hidden / locked / unlocked).
     *
     * @param stdClass $user
     * @return string
     */
    public function home_certificate_button_html(\stdClass $user): string {
        // Guests still see the user strip on the home page; never leave that
        // cell empty — show the same door instructions as a brand-new learner.
        if (!isloggedin() || isguestuser()) {
            return get_string('clickinstructions', 'theme_pdi');
        }

        try {
            $stage = self::home_certificate_stage((int) $user->id);
        } catch (\Throwable $e) {
            debugging('theme_pdi home certificate stage failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return get_string('clickinstructions', 'theme_pdi');
        }
        if ($stage === 'new') {
            return get_string('clickinstructions', 'theme_pdi');
        }

        $buttontext = get_string('certificatebutton', 'theme_pdi');
        $classes = 'btn btn-outline-white btn-rounded home-certificate-button mt-3';
        $configuredurl = self::configured_certificate_url();
        $hasurl = $configuredurl !== '';

        if ($stage === 'complete') {
            $html = html_writer::div(
                get_string('certificatecongrats', 'theme_pdi'),
                'home-certificate-congrats-message mt-2'
            );
            if ($hasurl) {
                $html .= html_writer::link($configuredurl, $buttontext, [
                    'class' => $classes,
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer',
                ]);
            } else {
                $html .= html_writer::tag('button', $buttontext, [
                    'type' => 'button',
                    'class' => $classes . ' disabled',
                    'disabled' => 'disabled',
                    'aria-disabled' => 'true',
                ]);
                $html .= html_writer::div(
                    get_string('certificatenourlmessage', 'theme_pdi'),
                    'home-certificate-disabled-message mt-2'
                );
            }
            return $html;
        }

        $html = html_writer::div(
            get_string('certificatedisabledmessage', 'theme_pdi'),
            'home-certificate-disabled-message mt-2'
        );
        // Not eligible yet: keep the CTA active and send the learner to the
        // dashboard to review progress / missing requirements.
        $dashboardurl = new moodle_url('/local/dashboard/view.php');
        $html .= html_writer::link($dashboardurl, $buttontext, [
            'class' => $classes . ' home-certificate-button--pending',
        ]);
        return $html;
    }

    /**
     * specialization_menu_content().
     *
     * @param mixed $user
     */
    private function specialization_menu_content($user) {
        global $CFG;

        require_once($CFG->dirroot . '/course/format/specialization/lib.php');
        $specializations = format_specialization::get_courses_in_format(true /* onlyvisible */);
        studypace::preload_user_course_completions((int) $user->id);
        $html = '';
        foreach ($specializations as $specialization) {
            $specializationname = format_string(
                $specialization->fullname,
                true,
                ['context' => \context_course::instance($specialization->id)]
            );
            $isblocked = (!is_siteadmin() && (!self::all_previous_courses_completed($specializations, $specialization->id, $user) || !$specialization->visible));
            $toenrol = (!is_siteadmin() && self::get_first_not_enroled_course_id($specializations, $user) == $specialization->id) && $specialization->visible;
            $areaurl = $toenrol ? $this->get_course_enrol_url($specialization->id) : new moodle_url('/course/view.php', [
                'id' => $specialization->id,
            ]);
            $tagstr = ($isblocked ? 'span' : 'a');
            $tagattrs = ['class' => 'dropdown-item'];
            if (!$isblocked) {
                $tagattrs['href'] = $areaurl;
            }
            $html .= html_writer::start_tag($tagstr, $tagattrs);
            $html .= html_writer::tag('span', $specializationname, [
                        'class' => ('mr-16pt ' . ($isblocked ? 'text-20' : '')),
            ]);
            if ($isblocked) {
                $blockedtext = get_string('blocked', 'theme_pdi');
                $html .= html_writer::tag('span', $blockedtext, [
                            'class' => 'badge badge-notifications text-uppercase ml-auto',
                            'style' => 'background-color: #868e96; color: white;',
                ]);
            } else {
                $isnew = !studypace::is_course_completed($specialization->id, $user->id);
                if ($isnew) {
                    $newtext = get_string('new');
                    $html .= html_writer::tag('span', $newtext, [
                                'class' => 'badge badge-notifications text-uppercase ml-2',
                                'style' => 'background-color: #FF0036; color: white;',
                    ]);
                }
            }
            $html .= html_writer::end_tag($tagstr);
        }
        return $html;
    }

    /**
     * full_header().
     *
     */
    public function full_header() {
        global $CFG, $USER;

        if (!$this->uses_pdi_custom_chrome()) {
            return '';
        }
        $whiteheaderfooter = in_array($this->page->bodyid, $this->whiteheaderfooterpages);

        $html = '';
        $html .= html_writer::start_div('navbar navbar-expand pr-0 navbar-dark bg-transparent', [
                    'id' => 'default-navbar',
                    'data-primary' => '',
                    'style' => $whiteheaderfooter ? 'background-color: white !important;' : 'background-color: rgb(1,19,23) !important;',
        ]);

        $html .= '<!-- Header Brand BEGIN -->';
        $html .= html_writer::start_tag('a', [
                    'class' => 'navbar-brand mr-16pt',
                    'href' => $CFG->wwwroot . '/?redirect=0',
        ]);
        $html .= html_writer::start_tag('span', [
                    'class' => 'mr-0 mr-lg-8pt',
        ]);
        $html .= html_writer::start_tag('span', [
                    'class' => '',
        ]);
        $html .= html_writer::tag('img', '', [
                    'src' => $CFG->wwwroot . '/theme/pdi/pix/' . ($whiteheaderfooter ? 'logo_text_pdi_white.png' : 'logo_text_pdi.png'),
                    'alt' => 'logo',
                    'class' => 'img-fluid',
                    'style' => 'max-height: 48px;',
        ]);
        $html .= html_writer::end_tag('span');
        $html .= html_writer::end_tag('span');
        $html .= html_writer::end_tag('a');
        $html .= '<!-- Header Brand END -->';

        $html .= '<!-- Header Toggler BEGIN -->';
        $html .= html_writer::start_tag('ul', [
                    'class' => 'nav navbar-nav d-none d-lg-flex flex justify-content-start ml-32pt',
        ]);
        $dashboardtext = get_string('dashboard', 'theme_pdi');
        $dashboardlink = $CFG->wwwroot . '/local/dashboard/view.php';
        if (isloggedin()) {
            $html .= html_writer::start_tag('li', [
                        'class' => 'nav-item',
            ]);
            $starttext = get_string('start', 'theme_pdi');
            $startlink = $CFG->wwwroot . '/?redirect=0';
            $html .= html_writer::tag('a', $starttext, [
                        'href' => $startlink,
                        'class' => 'nav-link',
            ]);
            $html .= html_writer::end_tag('li');
            $html .= html_writer::start_tag('li', [
                        'class' => 'nav-item dropdown',
            ]);
            $specializationstext = get_string('specializations', 'format_specialization');
            $html .= html_writer::tag('a', $specializationstext, [
                        'href' => '#',
                        'class' => 'nav-link dropdown-toggle',
                        'data-toggle' => 'dropdown',
                        'data-caret' => 'false',
            ]);
            $html .= html_writer::start_div('dropdown-menu');
            $html .= $this->specialization_menu_content($USER);
            $html .= html_writer::end_div();
            $html .= html_writer::end_tag('li');
            $html .= html_writer::start_tag('li', [
                        'class' => 'nav-item',
            ]);
            $html .= html_writer::tag('a', $dashboardtext, [
                        'href' => $dashboardlink,
                        'class' => 'nav-link',
            ]);
            $html .= html_writer::end_tag('li');
        }
        $html .= html_writer::end_tag('ul');
        $html .= '<!-- Header Toggler END -->';

        $html .= '<!-- Header Menu BEGIN -->';
        $html .= html_writer::start_div('nav navbar-nav flex-nowrap d-flex mr-16pt', [
                    'style' => 'right: 0; position: absolute;',
        ]);
        if (isloggedin()) {
            require_once($CFG->dirroot . '/local/notifications/lib.php');
            [$numunread, $notifications] = local_notifications_get_most_recent_notifications($USER->id, 4 /* $maxnotifications */);
            $numnotifications = count($notifications);
            // The bell now renders unconditionally for logged-in users. Hiding
            // the entire dropdown when $numnotifications == 0 made the icon
            // vanish from the header on accounts whose notifications had been
            // cleared (Moodle's message cleanup task purges read notifications
            // after a few months) or that simply had nothing pending yet.
            $html .= '<!-- Notifications dropdown BEGIN -->';
            $notificationstext = get_string('notifications', 'theme_pdi');
            $html .= html_writer::start_div('nav-item ml-16pt dropdown dropdown-notifications dropdown-xs-down-full', [
                        'data-toggle' => 'tooltip',
                        'data-title' => $notificationstext,
                        'data-placement' => 'bottom',
                        'data-boundary' => 'window',
                        'data-original-title' => '',
                        'title' => '',
            ]);
            $html .= html_writer::start_tag('button', [
                        'class' => 'nav-link btn-flush dropdown-toggle',
                        'type' => 'button',
                        'data-toggle' => 'dropdown',
                        'data-caret' => 'false',
            ]);
            $html .= html_writer::start_tag('i', [
                        'class' => 'icon fa fa-bell-o',
                        'style' => 'font-size: 1.5rem;',
            ]);
            $html .= html_writer::end_tag('i');
            if ($numunread > 0) {
                $html .= html_writer::tag('span', $numunread, [
                            'class' => 'badge badge-notifications bg-purple500 text-white-100',
                            'style' => 'background-color: #7000C4 !important;',
                ]);
            }
            $html .= html_writer::end_tag('button');
            $html .= html_writer::start_div('dropdown-menu dropdown-menu-right rounded-lg', [
                        'style' => 'width: 30rem;',
            ]);
            $html .= html_writer::start_div('position-relative ps', [
                        'data-perfect-scrollbar' => '',
            ]);
            $html .= html_writer::start_div('dropdown-header pt-16pt');
            $html .= html_writer::start_tag('strong');
            $notificationscenter = get_string('notificationscenter', 'theme_pdi');
            $html .= html_writer::tag('h5', $notificationscenter);
            $html .= html_writer::end_tag('strong');
            $html .= html_writer::end_div();
            $html .= html_writer::start_div('list-group list-group-flush mb-0');
            $systemcontext = context_system::instance();
            if ($numnotifications === 0) {
                // Empty state - keeps the dropdown body well-formed and gives
                // users feedback that the bell is intentional even when no
                // notification is pending.
                $html .= html_writer::start_div('list-group-item text-center text-muted py-4');
                $html .= get_string('nonotifications', 'theme_pdi');
                $html .= html_writer::end_div();
            }
            foreach ($notifications as $notification) {
                $html .= html_writer::start_tag('span', [
                            'class' => 'notification list-group-item list-group-item-action card mb-1' . ($notification->unread ? ' unread' : ' read'),
                            'style' => 'border-radius: 15px;',
                ]);
                $html .= html_writer::start_tag('span', [
                            'class' => 'd-flex',
                            'style' => 'align-items: center;',
                ]);
                // Reject "javascript:" and other unsafe URLs in iconurl.
                $awardimagelink1 = isset($notification->iconurl)
                    ? clean_param((string) $notification->iconurl, PARAM_URL)
                    : '';
                $html .= html_writer::start_tag('span', [
                            'class' => 'notification-icon mr-24pt flex-shrink-0',
                ]);
                if ($awardimagelink1 !== '') {
                    $html .= html_writer::tag('img', '', [
                                'class' => 'notification-icon__img rounded-circle',
                                'src' => $awardimagelink1,
                                'alt' => '',
                    ]);
                }
                $html .= html_writer::end_tag('span');
                $html .= html_writer::start_tag('span', [
                            'class' => 'flex d-flex flex-column',
                ]);
                $shortmessage = isset($notification->shortmessage)
                    ? format_text((string) $notification->shortmessage, FORMAT_HTML, ['context' => $systemcontext, 'para' => false])
                    : '';
                $html .= html_writer::tag('strong', $shortmessage, [
                            'class' => 'text-black-100 card-title mb-1',
                ]);
                $longmessage = isset($notification->longmessage)
                    ? format_text((string) $notification->longmessage, FORMAT_HTML, ['context' => $systemcontext, 'para' => false])
                    : '';
                $html .= html_writer::tag('span', $longmessage, [
                            'class' => 'text-black-70 mb-1',
                ]);
                $timetxt = local_notifications_time_to_text($notification->time);
                $html .= html_writer::tag('small', $timetxt, [
                            'style' => 'color: black !important;',
                ]);
                $html .= html_writer::end_tag('span');
                $html .= html_writer::end_tag('span');
                $html .= html_writer::end_tag('span');
            }
                // Use moodle_url so the wwwroot prefix (e.g. /moodle-pdi) is
                // included; a bare /local/notifications/view.php href resolves
                // against the server root and 404s on subdirectory installs.
                $notificationscenterlink = (new moodle_url('/local/notifications/view.php'))->out(false);
                $html .= html_writer::start_tag('a', [
                            'href' => $notificationscenterlink,
                            'class' => 'list-group-item list-group-item-action',
                ]);
                $html .= html_writer::start_tag('span', [
                            'class' => 'd-flex',
                            'style' => 'flex-flow: row; justify-content: center;',
                ]);
                $html .= html_writer::start_tag('span', [
                            'class' => 'avatar avatar-xs mr-2',
                            'style' => 'width: 1.625rem; height: 1.625rem;',
                ]);
                $html .= html_writer::start_tag('span', [
                            'class' => 'avatar-title rounded-circle',
                            'style' => 'background-color: #7000C4 !important;',
                ]);
                $html .= html_writer::start_tag('i', [
                            'class' => 'icon fa fa-plus',
                            'style' => 'color: white !important; margin-left: 8px;',
                ]);
                $html .= html_writer::end_tag('i');
                $html .= html_writer::end_tag('span');
                $html .= html_writer::end_tag('span');
                $html .= html_writer::start_tag('span');
                $seeallnotificationstext = get_string('seeallnotifications', 'theme_pdi');
                $html .= html_writer::tag('strong', $seeallnotificationstext, [
                            'class' => 'text-black-100',
                ]);
                $html .= html_writer::end_tag('span');
                $html .= html_writer::end_tag('span');
                $html .= html_writer::end_tag('a');
                $html .= html_writer::end_div();
                $html .= html_writer::start_div('ps__rail-x', [
                            'style' => 'left: 0px; bottom: 0px;',
                ]);
                $html .= html_writer::div('', 'ps__thumb-x', [
                            'tabindex' => '0',
                            'style' => 'left: 0px; width: 0px;',
                ]);
                $html .= html_writer::end_div();
                $html .= html_writer::start_div('ps__rail-y', [
                            'style' => 'top: 0px; right: 0px;',
                ]);
                $html .= html_writer::div('', 'ps__thumb-y', [
                            'tabindex' => '0',
                            'style' => 'top: 0px; height: 0px;',
                ]);
                $html .= html_writer::end_div();
                $html .= html_writer::end_div();
                $html .= html_writer::end_div();
                $html .= html_writer::end_div();
                $html .= '<!-- Notifications dropdown END -->';
        }
        if (!isloggedin()) {
            $html .= '<!-- User menu BEGIN -->';
            $html .= html_writer::start_div('nav-item');
            $userlink = '/login/index.php';
            $html .= html_writer::start_tag('a', [
                        'href' => $userlink,
                        'class' => 'nav-link d-flex align-items-center',
            ]);
            $html .= html_writer::start_tag('span', [
                        'class' => 'avatar avatar-sm mr-8pt2',
            ]);
            $html .= html_writer::start_tag('span', [
                        'class' => 'avatar-title rounded-circle bg-primary',
            ]);
            $userpictureurl = $CFG->wwwroot . '/theme/pdi/pix/login.png';
            $html .= html_writer::tag('img', '', [
                        'alt' => 'student',
                        'class' => 'avatar rounded-circle bg-white',
                        'src' => $userpictureurl,
            ]);
            $html .= html_writer::end_tag('span');
            $html .= html_writer::end_tag('span');
            $html .= html_writer::end_tag('a');
            $html .= html_writer::end_div();
            $html .= '<!-- User menu END -->';
        } else {
            $html .= '<!-- User menu BEGIN -->';
            $html .= html_writer::start_div('nav-item dropdown');
            $html .= html_writer::start_tag('a', [
                        'href' => '#',
                        'class' => 'd-flex align-items-center',
                        'data-toggle' => 'dropdown',
                        'data-caret' => 'false',
            ]);
            $html .= html_writer::start_tag('span', [
                        'class' => 'avatar avatar-sm mr-8pt2',
            ]);
            $html .= html_writer::start_tag('span', [
                        'class' => 'avatar-title rounded-circle bg-primary',
            ]);
            if (!isloggedin() || isguestuser()) {
                $userpictureurl = $CFG->wwwroot . '/theme/pdi/pix/login.png';
            } else {
                $userpicture = new user_picture($USER);
                $userpicture->size = 140;
                $userpictureurl = $userpicture->get_url($this->page);
            }
            $html .= html_writer::tag('img', '', [
                        'alt' => 'student',
                        'class' => 'avatar rounded-circle bg-white',
                        'src' => $userpictureurl,
            ]);
            $html .= html_writer::end_tag('span');
            $html .= html_writer::end_tag('span');
            $html .= html_writer::end_tag('a');
            $html .= html_writer::start_div('dropdown-menu dropdown-menu-right');
            $html .= html_writer::start_div('dropdown-header');
            $greetingstext = get_string('hello', 'theme_pdi') . ', ' . s($USER->firstname);
            $html .= html_writer::tag('strong', $greetingstext);
            $html .= html_writer::end_div();
            $html .= html_writer::tag('a', $dashboardtext, [
                        'class' => 'dropdown-item',
                        'href' => $dashboardlink,
            ]);
            $myprofiletext = get_string('myprofile', 'theme_pdi');
            $returnurlstr = theme_pdi_prepare_url($this->page->url);
            $myprofilelink = new moodle_url('/local/profile/edit.php', [
                'returnto' => $returnurlstr,
            ]);
            $html .= html_writer::tag('a', $myprofiletext, [
                        'class' => 'dropdown-item',
                        'href' => $myprofilelink,
            ]);
            $exittext = get_string('exit', 'theme_pdi');
            $exitlink = $CFG->wwwroot . '/login/logout.php?sesskey=' . sesskey();
            $html .= html_writer::tag('a', $exittext, [
                        'class' => 'dropdown-item',
                        'href' => $exitlink,
            ]);
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= '<!-- User menu END -->';
        }
        $html .= html_writer::end_div();
        $html .= '<!-- Header Menu END -->';
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * footer_content().
     *
     */
    private function footer_content() {
        global $CFG;

        $whiteheaderfooter = in_array($this->page->bodyid, $this->whiteheaderfooterpages);

        $html = '';
        $html .= '<!-- Footer BEGIN -->';
        $html .= html_writer::start_div('border-top-0 mt-auto', [
                    'id' => 'footer-content',
                    'style' => 'color: rgb(133, 141, 148) !important;' . ($whiteheaderfooter ? ' background-color: white;' : ' background-color: rgb(1,19,23);'),
        ]);
        $html .= html_writer::start_div('container page__container d-flex flex-column narrow-page', [
                    'style' => 'padding-top: 1rem; padding-bottom: 2rem; padding-right: 1.5rem !important; padding-left: 1.5rem !important;',
        ]);
        $html .= html_writer::start_tag('p', [
                    'class' => 'brand mb-24pt',
                    'style' => 'font-size: 16px; font-weight: 700;',
        ]);
        $html .= html_writer::tag('img', '', [
                    'class' => 'brand-icon mr-3',
                    'src' => $CFG->wwwroot . '/theme/pdi/pix/logo_pdi.png',
                    'alt' => 'logo',
                    'style' => 'width: 48px; height: auto;',
        ]);
        global $SITE;
        $course = $SITE;
        $coursecontext = context_course::instance($course->id);
        $html .= format_string($course->fullname, true, ['context' => $coursecontext]);
        $html .= html_writer::end_tag('p');
        $html .= html_writer::start_tag('p', [
                    'class' => 'measure-lead-max small mr-8pt',
        ]);
        $html .= format_text($course->summary, isset($course->summaryformat) ? $course->summaryformat : FORMAT_HTML, ['context' => $coursecontext]);
        $html .= html_writer::end_tag('p');
        $html .= html_writer::start_tag('p', [
                    'class' => 'mb-8pt d-flex',
        ]);
        // TODO Viddia Set and enable link below
        if (1 == 0) {
            $termslink = '';
            $html .= html_writer::start_tag('a', [
                        'href' => $termslink,
                        'class' => 'text-underline mr-8pt small',
            ]);
            $html .= get_string('termsandconditions', 'theme_pdi');
            $html .= html_writer::end_tag('a');
        }
        // TODO Viddia Set and enable link below
        if (1 == 0) {
            $privacylink = '';
            $html .= html_writer::start_tag('a', [
                        'href' => $privacylink,
                        'class' => 'text-underline small ml-3',
            ]);
            $html .= get_string('privacypolicies', 'theme_pdi');
            $html .= html_writer::end_tag('a');
        }
        $html .= html_writer::end_tag('p');
        $html .= html_writer::start_tag('p', [
                    'class' => 'small mt-n1 mb-0',
        ]);
        $html .= get_string('allrightsreserved', 'theme_pdi', '2025');
        $html .= html_writer::end_tag('p');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Footer END -->';
        return $html;
    }

    /**
     * footer().
     *
     */
    public function footer() {

        if ($this->page->bodyid === 'page-site-index') {
            $this->page->requires->js('/theme/pdi/js/home.js');
        }
        // The "Avaliação de Reação" banner used to be injected on the
        // client by /theme/pdi/js/avaliacao.js. It is now rendered
        // server-side by format_specialization's renderer
        // (build_avaliacao_reacao_mural). Keeping it client-side meant a
        // flash where the card was missing on first paint and required
        // every page that loads this footer to ship the inert script,
        // even on pages without any Eixo windows.
        $html = '';
        if ($this->uses_pdi_custom_chrome()) {
            $html .= $this->footer_content();
        }
        $html .= parent::footer();

        return $html;
    }
}

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
/**
 * Course renderer overrides for the PDI theme.
 *
 * @package   theme_pdi
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_pdi_core_course_renderer extends core_course_renderer {
    /**
     * get_course_enrol_url().
     *
     * @param mixed $courseid
     */
    private function get_course_enrol_url($courseid) {
        // Mirror the core_renderer variant. See
        // theme_pdi_core_renderer::get_course_enrol_url for the rationale on
        // why we route to /enrol/index.php instead of reading the URL from
        // {role_names}.name.
        return (new \moodle_url('/enrol/index.php', ['id' => $courseid]))->out(false);
    }

    /**
     * frontpage().
     *
     */
    public function frontpage() {
        global $USER, $CFG;

        $html = '';
        $html .= '<!-- Home page BEGIN -->';
        $html .= html_writer::start_div('mdk-header-layout__content page-content');
        if (isloggedin()) {
            $html .= '<!-- User section BEGIN -->';
            $html .= html_writer::start_div('container narrow-page page-section mt-64pt home-user-section');
            $html .= html_writer::start_div('container page__container justify-content-center align-items-center');
            $html .= html_writer::start_div('d-flex flex-column flex-lg-row align-items-center home-user-row');
            $html .= html_writer::start_div(
                'd-flex flex-column flex-md-row align-items-center flex mb-lg-0 text-center text-md-left home-user-main'
            );
            $html .= html_writer::start_div('avatar-wrapper position-relative');
            $html .= html_writer::start_div('avatar-background');
            $html .= html_writer::tag('img', '', [
                        'src' => $CFG->wwwroot . '/theme/pdi/pix/elipse-gradient.png',
                        'alt' => 'elipse',
                        'class' => 'rotating-glow',
            ]);
            $html .= html_writer::end_div();
            $html .= '<!-- Avatar image -->';
            $html .= html_writer::start_div('avatar avatar-xxl mb-md-0 mr-md-32pt rounded-circle', [
                        'style' => 'transform: scale(1.0);',
            ]);
            $userpicture = new user_picture($USER);
            $userpicture->size = 140;
            $userpictureurl = $userpicture->get_url($this->page);
            $html .= html_writer::tag('img', '', [
                        'alt' => 'student',
                        'class' => 'avatar rounded-circle bg-white',
                        'style' => 'font-size: 2.66667rem; width: 8rem; height: 8rem;',
                        'src' => $userpictureurl,
            ]);
            $html .= html_writer::div('', 'overlay__content');
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::start_div('flex measure-lead-max');
            $html .= html_writer::start_div('flex d-flex flex-row align-items-center');
            $html .= html_writer::start_div();
            $html .= html_writer::start_tag('small', [
                        'class' => 'text-white-70 d-block mb-3',
            ]);
            $welcometext = get_string('welcome', 'theme_pdi');
            $html .= html_writer::tag('strong', $welcometext);
            $html .= html_writer::end_tag('small');
            $html .= html_writer::tag('h2', s(fullname($USER)), [
                        'class' => 'home-user-fullname measure-lead-max mb-0pt text-primary200',
            ]);
            $html .= html_writer::div('', 'border-bottom-0 border-secondary mb-24pt');
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= '<!-- Minidashboard BEGIN -->';
            require_once($CFG->dirroot . '/local/dashboard/lib.php');
            $html .= html_writer::start_div('home-minidashboard', [
                        'id' => 'minidashboard',
            ]);
            $keystext = get_string('keys', 'theme_pdi');
            $html .= html_writer::start_tag('span', [
                        'class' => 'chip chip-light d-inline-flex align-items-center',
                        'data-toggle' => 'tooltip',
                        'data-title' => $keystext,
                        'data-placement' => 'bottom',
            ]);
            $html .= html_writer::tag('img', '', [
                        'src' => $CFG->wwwroot . '/theme/pdi/pix/key.png',
                        'alt' => 'key icon',
                        'style' => 'width: 24px; height: 24px; margin-right: 8px; vertical-align: middle;',
            ]);
            $html .= local_dashboard_get_num_keys($USER);
            $html .= html_writer::end_tag('span');
            // Restrict the minidashboard "Em <curso> - nível N" chip to the
            // specialization (Eixos) track only. The saladeconferencias
            // course is tracked by studypace too, but the chip is meant to
            // surface the user's place in the structured learning path and
            // showing the conference room there is misleading.
            $course = local_dashboard_get_current_course($USER->id, ['specialization']);
            if ($course) {
                $level = local_dashboard_get_current_level($USER->id, $course->id);
                $coursefullname = format_string(
                    $course->fullname,
                    true,
                    ['context' => context_course::instance($course->id)]
                );
                $coursetext = get_string('incourse', 'theme_pdi', $coursefullname);
                $html .= html_writer::tag('span', $coursetext, [
                            'class' => 'chip chip-light d-inline-flex align-items-center',
                            'style' => 'height: 34px;',
                ]);
                $html .= html_writer::start_tag('span', [
                            'class' => 'chip chip-light d-inline-flex align-items-center',
                            'data-toggle' => 'tooltip',
                            'data-title' => get_string('currentlevel', 'theme_pdi'),
                            'data-placement' => 'bottom',
                            'style' => 'height: 34px;',
                ]);
                $html .= html_writer::start_tag('i', [
                            'class' => 'icon fa fa-bar-chart',
                ]);
                $html .= html_writer::end_tag('i');
                $html .= get_string('levelnum', 'theme_pdi', $level);
                $html .= html_writer::end_tag('span');
            }
            $html .= html_writer::start_tag('span', [
                        'class' => 'align-items-center',
            ]);
            $html .= html_writer::start_tag('a', [
                        'href' => $CFG->wwwroot . '/local/dashboard/view.php',
                        'class' => 'text-white-70 ml-4 mb-sm-0',
            ]);
            $html .= html_writer::start_tag('strong');
            $html .= get_string('seemore', 'theme_pdi');
            $html .= html_writer::end_tag('strong');
            $html .= html_writer::tag('img', '', [
                        'src' => $CFG->wwwroot . '/theme/pdi/pix/arrow-right.png',
                        'style' => 'height: 15px; margin-left: 10px;',
            ]);
            $html .= html_writer::end_tag('a');
            $html .= html_writer::end_tag('span');
            $html .= html_writer::end_div();
            $html .= '<!-- Minidashboard END -->';
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::start_div('home-user-certificate');
            $html .= $this->home_certificate_button_html($USER);
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= '<!-- User section END -->';
        }
        $html .= html_writer::start_div('page-section flex d-flex align-items-center justify-content-center pb-112pt', [
                    'style' => 'padding-top: 2rem !important; padding-bottom: 0rem !important;',
        ]);
        $html .= '<!-- Áreas invisíveis interativas BEGIN -->';
        require_once($CFG->dirroot . '/course/format/specialization/lib.php');
        $specializations = format_specialization::get_courses_in_format(false /* onlyvisible */);
        $html .= html_writer::start_div('containerhome');
        foreach ($specializations as $specialization) {
            $isblocked = (!is_siteadmin() && (!theme_pdi_core_renderer::all_previous_courses_completed($specializations, $specialization->id, $USER) || !$specialization->visible));
            $toenrol = (!is_siteadmin() && theme_pdi_core_renderer::get_first_not_enroled_course_id($specializations, $USER) == $specialization->id) && $specialization->visible;
            $areaclass = ($toenrol ? ' toenrol-area' : ($isblocked ? ' locked-area' : ''));
            $areaurl = $toenrol ? $this->get_course_enrol_url($specialization->id) : new moodle_url('/course/view.php', [
                'id' => $specialization->id,
            ]);
            $speccontext = context_course::instance($specialization->id);
            $tooltip = format_string($specialization->fullname, true, ['context' => $speccontext]);
            if (!$specialization->visible) {
                // strftime is deprecated in PHP 8.1 and removed in 9.0; use
                // userdate() with a localised format placeholder instead.
                $tooltip .= ' -  ' . get_string('coursestart', 'theme_pdi')
                    . ' ' . userdate($specialization->startdate, get_string('strftimedateshort', 'langconfig'));
            }
            $safeshort = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $specialization->shortname);
            $html .= html_writer::div('', 'interactive-area active' . $areaclass, [
                        'id' => $safeshort,
                        'data-tooltip' => $tooltip,
                        'data-url' => is_string($areaurl) ? $areaurl : $areaurl->out(false),
            ]);
        }
        $saladeconferenciastext = get_string('conferenceroom', 'theme_pdi');
        require_once($CFG->dirroot . '/course/format/saladeconferencias/lib.php');
        $saladeconferenciascourses = format_saladeconferencias::get_courses_in_format();
        $saladeconferenciascourse = null;
        if (count($saladeconferenciascourses) > 0) {
            $saladeconferenciascourse = reset($saladeconferenciascourses);
        }
        if (!$saladeconferenciascourse && file_exists($CFG->dirroot . '/course/format/conferenceroom/lib.php')) {
            require_once($CFG->dirroot . '/course/format/conferenceroom/lib.php');
            $legacyrooms = format_conferenceroom::get_courses_in_format();
            if (count($legacyrooms) > 0) {
                $saladeconferenciascourse = reset($legacyrooms);
            }
        }
        if ($saladeconferenciascourse && (is_siteadmin() || $saladeconferenciascourse->visible)) {
            $saladeconferenciasurl = new moodle_url('/course/view.php', [
                'id' => $saladeconferenciascourse->id,
            ]);
            $html .= html_writer::div('', 'interactive-area', [
                        'id' => 'saladeconferencias',
                        'data-tooltip' => $saladeconferenciastext,
                        'data-url' => $saladeconferenciasurl->out(false),
            ]);
        } else {
            $html .= html_writer::div('', 'interactive-area locked-area', [
                        'id' => 'saladeconferencias',
                        'data-tooltip' => get_string('checkvideogallery', 'theme_pdi'),
                        'data-url' => '#',
            ]);
        }
        $cafeconsabertext = get_string('videogallery', 'theme_pdi');
        require_once($CFG->dirroot . '/course/format/videogallery/lib.php');
        $videogalleries = format_videogallery::get_courses_in_format();
        if (count($videogalleries) > 0) {
            $videogallery = reset($videogalleries);
            $cafeconsaberurl = new moodle_url('/course/view.php', [
                'id' => $videogallery->id,
            ]);
            $html .= html_writer::div('', 'interactive-area', [
                        'id' => 'cafeconsaber',
                        'data-tooltip' => $cafeconsabertext,
                        'data-url' => $cafeconsaberurl->out(false),
            ]);
        } else {
            $html .= html_writer::div('', 'interactive-area locked-area', [
                        'id' => 'cafeconsaber',
                        'data-tooltip' => $cafeconsabertext,
                        'data-url' => '#',
            ]);
        }
        $html .= html_writer::end_div();
        $html .= '<!-- Áreas invisíveis interativas END -->';
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('narrow-page page-section flex d-flex border-bottom-0 justify-content-center align-items-center pb-0');
        $html .= html_writer::start_div('container page__container flex d-flex  justify-content-center align-items-center', [
                    'style' => 'height: 160px;',
        ]);
        $html .= '';
        $html .= html_writer::start_div('row');
        $html .= html_writer::start_div('col-lg-9 flex d-flex flex-lg-column', [
                    'style' => 'padding: 0;',
        ]);
        $html .= html_writer::start_div('row');
        $html .= html_writer::start_div('col-md-9 mb-24pt mb-lg-0', [
                    'style' => 'padding: .5rem;',
        ]);
        $html .= html_writer::start_tag('p', [
                    'class' => 'text-white-70 mb-0',
        ]);
        $html .= html_writer::start_tag('strong');
        $html .= html_writer::end_tag('strong');
        $html .= html_writer::end_tag('p');
        $abouttext = get_string('about', 'theme_pdi');
        $html .= html_writer::tag('h3', $abouttext, [
                    'class' => 'text-white',
        ]);
        $html .= html_writer::start_tag('p', [
                    'class' => 'text-white-50',
        ]);
        $html .= html_writer::start_tag('span', [
                    'class' => 'text-white-100',
        ]);
        $html .= get_string('abouttxt', 'theme_pdi');
        $html .= html_writer::end_tag('span');
        $html .= html_writer::end_tag('p');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('col-lg-3 text-lg-right d-flex flex-lg-column mb-24pt mb-lg-0 border-lg-0 pb-16pt pb-lg-0');
        if (isloggedin()) {
            $html .= '<!-- Last access BEGIN -->';
            $html .= html_writer::start_div('mt-12pt');
            $html .= html_writer::start_tag('p', [
                        'class' => 'text-white-70 mb-8pt',
            ]);
            $lastaccesstext = get_string('lastaccess', 'theme_pdi');
            $html .= html_writer::tag('strong', $lastaccesstext);
            $html .= html_writer::end_tag('p');
            $html .= html_writer::start_tag('p', [
                        'class' => 'text-white-50',
            ]);
            // userdate() replaces strftime() (deprecated in PHP 8.1, removed
            // in PHP 9.0). The lang string 'dateformat' still uses %-tokens
            // because userdate() understands the same placeholders.
            $datestr = str_replace("De", "de", ucwords(userdate($USER->lastaccess, get_string('dateformat', 'theme_pdi'))));
            $timestr = userdate($USER->lastaccess, '%H:%M');
            $html .= get_string('datetime', 'theme_pdi', [
                'date' => $datestr,
                'time' => $timestr,
            ]);
            $html .= html_writer::end_tag('p');
            $html .= html_writer::end_div();
            $html .= '<!-- Last access END -->';
        }
        $html .= html_writer::start_div();
        $contactlink = 'https://formulario-cpnu.enap.gov.br/pdi';
        $html .= html_writer::start_tag('a', [
                    'class' => 'btn btn-outline-white btn-rounded',
                    'href' => $contactlink,
                    'id' => 'contact',
                    'style' => 'font-size: 8px; color: rgb(133, 141, 148) !important;',
        ]);
        $html .= get_string('contact', 'theme_pdi');
        $html .= html_writer::start_tag('i', [
                    'class' => 'icon fa fa-envelope fa-fw navicon ml-2 mr-0',
                    'style' => 'font-size: 12px;',
        ]);
        $html .= html_writer::end_tag('i');
        $html .= html_writer::end_tag('a');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Home page END -->';
        return $html;
    }

    /**
     * course_section_cm().
     *
     * @param mixed $course
     * @param mixed $completioninfo
     * @param cm_info $mod
     * @param mixed $sectionreturn
     * @param mixed $displayoptions
     */
    public function course_section_cm($course, &$completioninfo, cm_info $mod, $sectionreturn, $displayoptions = []) {
        global $USER, $CFG;

        if (!$mod->uservisible && empty($mod->availableinfo)) {
            return '';
        }
        $html = '';
        if ($mod->modname == 'url') {
            $html .= $this->simple_course_section_cm($course, $completioninfo, $mod, $sectionreturn, $displayoptions);
        } else {
            $html .= '<!-- Start course module content -->';
            $html .= html_writer::start_div('course-module-box col-12 col-sm-6 col-md-4 col-xl-3' . ($this->page->user_is_editing() ? ' editing' : ''), [
                        'id' => 'cm-' . $mod->id,
            ]);
            if ($this->page->user_is_editing()) {
                $html .= course_get_cm_move($mod, $sectionreturn);
            }
            $modicons = '';
            if ($this->page->user_is_editing()) {
                $editactions = course_get_cm_edit_actions($mod, $mod->indent, $sectionreturn);
                $modicons .= ' ' . $this->course_section_cm_edit_actions($editactions, $mod, $displayoptions);
                $modicons .= $mod->afterediticons;
            }
            if (!empty($modicons)) {
                $html .= html_writer::span($modicons, 'actions');
            }
            $modulename = $mod->modname;
            $thumbnailurl = $this->get_thumbnail_url($mod);
            require_once($CFG->dirroot . '/mod/video/lib.php');
            [$coursemoduletitle, $videoduration] = video_extract_duration_str_from_cm_title($mod);
            if (!$mod->available) {
                $messageicon = 'lock';
                $statusmessage = get_string('blocked', 'theme_pdi');
                $statusicon = 'lock';
                $statusicontext = get_string('blocked', 'theme_pdi');
                $statusiconcolor = 'rgba(39, 44, 51, 0.5)';
                $cmoperation = '';
            } else {
                if (theme_pdi_is_cm_completed($USER->id, $mod->id)) {
                    if ($modulename == 'video') {
                        $messageicon = 'fa-play-circle-o';
                        $statusmessage = get_string('watchagain', 'theme_pdi');
                    } else {
                        $messageicon = 'try';
                        $statusmessage = get_string('tryagain', 'theme_pdi');
                    }
                    $statusicon = 'check';
                    $cmoperation = '';
                    $statusicontext = get_string('completed', 'theme_pdi');
                    $statusiconcolor = 'rgb(119, 193, 58)';
                } else {
                    if ($modulename == 'video') {
                        $statusicon = 'fa-play-circle-o';
                        $messageicon = 'fa-play-circle-o';
                        $statusmessage = get_string('watch', 'theme_pdi');
                    } else {
                        $statusicon = 'lock_open';
                        $messageicon = 'try';
                        $statusmessage = get_string('enter', 'theme_pdi');
                    }
                    $cmoperation = '';
                    $statusicontext = get_string('unblocked', 'theme_pdi');
                    $statusiconcolor = 'rgba(39, 44, 51, 0.5)';
                }
            }
            // Teachers get a checklist icon. Do NOT call
            // is_cm_completed_by_all_students() here: the bulk-toggle markup
            // that consumed $cmoperation is commented out below, and with
            // ~18k enrolments that check was O(activities × students) DB
            // work on every specialization course page for teachers.
            if (self::is_user_a_course_teacher($USER->id, $mod->course)) {
                $statusicon = 'checklist';
                $statusiconcolor = 'unset';
            }
            $html .= html_writer::start_div('card card-sm card--elevated p-relative o-hidden overlay overlay--primary-dodger-blue js-overlay mdk-reveal js-mdk-reveal', [
                        'data-partial-height' => '44',
                        'data-toggle' => 'popover',
                        'data-trigger' => 'hover',
            ]);
            if ($mod->available) {
                $html .= html_writer::start_tag('a', [
                    'href' => $mod->url,
                    'class' => 'js-image',
                    'data-position' => '',
                ]);
            } else {
                $html .= html_writer::start_div('js-image', [
                    'data-position' => '',
                ]);
            }
            $html .= html_writer::tag('img', '', [
                        'src' => $thumbnailurl,
                        'height' => '178px',
                        'alt' => 'course',
            ]);
            $html .= html_writer::start_tag('span', [
                        'class' => 'overlay__content align-items-start justify-content-start',
            ]);
            $html .= html_writer::start_tag('span', [
                        'class' => 'overlay__action card-body d-flex align-items-center',
            ]);
            $html .= html_writer::start_tag('i', [
                        'class' => 'icon fa ' . $messageicon,
            ]);
            $html .= html_writer::end_tag('i');
            $html .= html_writer::tag('span', $statusmessage, [
                        'class' => 'card-title text-white',
            ]);
            $html .= html_writer::end_tag('span');
            $html .= html_writer::end_tag('span');
            if ($mod->available) {
                $html .= html_writer::end_tag('a');
            } else {
                $html .= html_writer::end_div();
            }
            $html .= html_writer::start_div('mdk-reveal__content');
            $html .= html_writer::start_div('card-body');
            $html .= html_writer::start_div('d-flex');
            $html .= html_writer::start_div('flex');
            $html .= html_writer::tag('a', format_string($coursemoduletitle, true, ['context' => $mod->context]), [
                        'class' => 'card-title',
                        'href' => $mod->url,
            ]);
            $html .= html_writer::end_div();
            // Display course module icon
            // $html .= html_writer::div($statusicon, 'ml-4pt material-icons card-course__icon-favorite ' . $iconclass, array(
            // 'data-toggle' => 'tooltip',
            // 'data-title' => $statusicontext,
            // 'data-placement' => 'top',
            // 'data-boundary' => 'window',
            // 'data-original-title' => '',
            // 'title' => '',
            // 'style' => 'color: ' . $statusiconcolor . ';',
            // 'cmid' => $mod->id,
            // 'cmoperation' => $cmoperation
            // ));

            $html .= html_writer::start_tag('i', [
                        'class' => 'icon fa ' . $statusicon,
            ]);
            $html .= html_writer::end_tag('i');

            $html .= html_writer::end_div();
            if ($modulename == 'video') {
                $html .= html_writer::start_div('d-flex');
                $html .= html_writer::tag('small', $videoduration, [
                            'class' => 'text-50',
                ]);
                $html .= html_writer::end_div();
            }
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= '<!-- End course module content -->';
        }
        return $html;
    }

    /**
     * Whether every enrolled student has completed this activity.
     *
     * Unused by the live card UI (bulk toggle is commented out). Kept for
     * possible admin tooling — callers must not invoke this on a full
     * course page with large enrolments (N student queries per CM).
     *
     * @param cm_info $mod
     * @return bool
     */
    public static function is_cm_completed_by_all_students(cm_info $mod): bool {
        global $DB;

        $context = \context_module::instance($mod->id);
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        if (!$studentroleid) {
            return false;
        }
        $students = get_role_users($studentroleid, $context, false, 'u.id', 'u.id ASC');
        if (empty($students)) {
            return false;
        }
        foreach ($students as $student) {
            if (!theme_pdi_is_cm_completed($student->id, $mod->id)) {
                return false;
            }
        }
        return true;
    }

    /**
     * get_module_name().
     *
     * @param mixed $cmid
     */
    public static function get_module_name($cmid) {
        global $DB;

        $cm = $DB->get_record('course_modules', ['id' => $cmid]);
        if (!$cm) {
            return null;
        }
        $module = $DB->get_record('modules', ['id' => $cm->module]);
        if (!$module) {
            return null;
        }
        return $module->name;
    }

    /**
     * Returns the first HTML tag of the given type found in a HTML fragment.
     *
     * @param string|null $html HTML content
     * @param string $tagname Tag name (e.g. img)
     * @return DOMElement|null
     */
    public static function retrieve_tag_from_html($html, $tagname) {
        if (empty($html) || empty($tagname)) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $tags = $dom->getElementsByTagName($tagname);
        if ($tags->length === 0) {
            return null;
        }

        return $tags->item(0);
    }

    /**
     * get_thumbnail_url().
     *
     * @param cm_info $mod
     */
    private function get_thumbnail_url(cm_info $mod) {
        global $CFG, $DB;

        $modulename = $this->get_module_name($mod->id);
        $thumbnailurl = '';
        if ($modulename == 'video') {
            $thumbnailurl = video_get_thumbnail_url($mod->instance, $mod->id);
        }
        if ($modulename == 'page' || $modulename == 'resource') {
            $page = $DB->get_record($modulename, ['id' => $mod->instance]);
            if ($page) {
                $thumbnailhtml = self::retrieve_tag_from_html($page->intro, 'img');
                if ($thumbnailhtml) {
                    $imagesource = $thumbnailhtml->getAttribute('src');
                    if ($imagesource) {
                        $cmcontext = context_module::instance($mod->id);
                        $thumbnailurl = file_rewrite_pluginfile_urls($imagesource, 'pluginfile.php', $cmcontext->id, 'mod_' . $modulename, 'intro', null);
                    }
                }
            }
        }
        if (!$thumbnailurl) {
            if ($modulename && file_exists($CFG->dirroot . "/mod/{$modulename}/pix/thumbnail.jpg")) {
                $thumbnailurl = $CFG->wwwroot . "/mod/{$modulename}/pix/thumbnail.jpg";
            } else if (file_exists($CFG->dirroot . '/theme/pdi/pix/defaultthumbnail.jpg')) {
                $thumbnailurl = $CFG->wwwroot . '/theme/pdi/pix/defaultthumbnail.jpg';
            } else {
                // pix/defaultthumbnail.jpg isn't actually shipped, so fall
                // back to a generic Moodle pixmap instead of returning a 404
                // image URL.
                $thumbnailurl = $this->output->image_url('icon', 'moodle')->out(false);
            }
        }
        return $thumbnailurl;
    }

    /**
     * is_user_a_course_teacher().
     *
     * @param mixed $userid
     * @param mixed $courseid
     */
    private static function is_user_a_course_teacher($userid, $courseid) {
        global $DB;

        static $cache = [];
        $cachekey = ((int) $userid) . ':' . ((int) $courseid);
        if (array_key_exists($cachekey, $cache)) {
            return $cache[$cachekey];
        }

        $coursecontext = context_course::instance($courseid);
        $sql = 'SELECT * '
                . 'FROM {role_assignments} ra, {role} r '
                . 'WHERE ra.roleid = r.id '
                . 'AND ra.contextid = :coursecontextid '
                . "AND r.shortname IN ('teacher', 'editingteacher') "
                . 'AND ra.userid = :userid';
        $teacherinfo = $DB->get_records_sql($sql, [
            'coursecontextid' => $coursecontext->id,
            'userid' => $userid,
        ]);
        $cache[$cachekey] = count($teacherinfo) > 0;
        return $cache[$cachekey];
    }

    /**
     * course_section_cm_list().
     *
     * @param mixed $course
     * @param mixed $section
     * @param mixed $sectionreturn
     * @param mixed $displayoptions
     */
    public function course_section_cm_list($course, $section, $sectionreturn = null, $displayoptions = []) {
        global $CFG, $USER;

        // Build the completion info up-front so the loop has a real object to
        // pass down. The old code dereferenced $completioninfo when it was
        // never declared in this scope.
        require_once($CFG->libdir . '/completionlib.php');
        $completioninfo = new completion_info($course);

        $modinfo = get_fast_modinfo($course);
        if (is_object($section)) {
            $section = $modinfo->get_section_info($section->section);
        } else {
            $section = $modinfo->get_section_info($section);
        }
        $ismoving = $this->page->user_is_editing() && ismoving($course->id);
        if ($ismoving) {
            $movingpix = new pix_icon('movehere', get_string('movehere'), 'moodle', ['class' => 'movetarget']);
            $strmovefull = strip_tags(get_string("movefull", "", "'$USER->activitycopyname'"));
        }
        $moduleshtml = [];
        if (!empty($modinfo->sections[$section->section])) {
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];
                if ($ismoving && $mod->id == $USER->activitycopy) {
                    continue;
                }
                if ($modulehtml = $this->course_section_cm_list_item($course, $completioninfo, $mod, $sectionreturn, $displayoptions)) {
                    $moduleshtml[$modnumber] = $modulehtml;
                }
            }
        }
        $listhtml = '';
        if (!empty($moduleshtml) || $ismoving) {
            $count = 0;
            $carousel = !array_key_exists('carousel', $displayoptions) || $displayoptions['carousel'];
            $slidesatsametime = ($this->page->user_is_editing() ? 1 : 3);
            foreach ($moduleshtml as $modnumber => $modulehtml) {
                if (($count % $slidesatsametime) == 0) {
                    $listhtml .= html_writer::start_div($carousel ? ('carousel-item' . ($count == 0 ? ' active' : '')) : '');
                }
                if ($ismoving) {
                    $movingurl = new moodle_url('/course/mod.php', ['moveto' => $modnumber, 'sesskey' => sesskey()]);
                    $listhtml .= html_writer::tag('li', html_writer::link($movingurl, $this->output->render($movingpix), ['title' => $strmovefull]), ['class' => 'movehere']);
                }
                $listhtml .= $modulehtml;
                if (($count % $slidesatsametime) == ($slidesatsametime - 1)) {
                    $listhtml .= html_writer::end_div();
                }
                ++$count;
            }
            if ((count($moduleshtml) % $slidesatsametime) != 0) {
                $listhtml .= html_writer::end_div();
            }
            if ($ismoving) {
                $movingurl = new moodle_url('/course/mod.php', ['movetosection' => $section->id, 'sesskey' => sesskey()]);
                $listhtml .= html_writer::tag('li', html_writer::link($movingurl, $this->output->render($movingpix), ['title' => $strmovefull]), ['class' => 'movehere']);
            }
        }
        return $listhtml;
    }

    /**
     * course_section_cm_list_item().
     *
     * @param mixed $course
     * @param mixed $completioninfo
     * @param cm_info $mod
     * @param mixed $sectionreturn
     * @param mixed $displayoptions
     */
    public function course_section_cm_list_item($course, &$completioninfo, cm_info $mod, $sectionreturn, $displayoptions = []) {
        return $this->course_section_cm($course, $completioninfo, $mod, $sectionreturn, $displayoptions);
    }

    /**
     * simple_course_section_cm().
     *
     * @param mixed $course
     * @param mixed $completioninfo
     * @param cm_info $mod
     * @param mixed $sectionreturn
     * @param mixed $displayoptions
     */
    private function simple_course_section_cm($course, &$completioninfo, cm_info $mod, $sectionreturn, $displayoptions = []) {
        $html = '';
        $html .= html_writer::start_div('', [
                    'id' => 'cm-' . $mod->id,
                    'style' => 'float: left;',
        ]);
        $modicons = '';
        if ($this->page->user_is_editing()) {
            $editactions = course_get_cm_edit_actions($mod, $mod->indent, $sectionreturn);
            $modicons .= ' ' . $this->course_section_cm_edit_actions($editactions, $mod, $displayoptions);
            $modicons .= $mod->afterediticons;
        }
        if (!empty($modicons)) {
            $html .= html_writer::span($modicons, 'actions');
        }
        $html .= html_writer::start_tag('a', [
                    'href' => $mod->url,
                    'class' => 'btn btn-md btn-outline-white btn-rounded mr-16pt',
                    'target' => '_blank',
        ]);
        $html .= html_writer::start_tag('i', [
                    'class' => 'icon fa fa-book',
        ]);
        $html .= html_writer::end_tag('i');
        $html .= $mod->get_formatted_name();
        $html .= html_writer::end_tag('a');
        $html .= html_writer::end_div();
        return $html;
    }
}

<?php

/**
 * Renderer for outputting the conferenceroom course format.
 *
 * @package    format_conferenceroom
 * @copyright  2025 Viddia (http://viddia.com.br)
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/renderer.php');

use core_courseformat\output\section_renderer;

/**
 * Basic renderer for conferenceroom format.
 *
 * @package    format_conferenceroom
 * @copyright  2025 Viddia (http://viddia.com.br)
 */
class format_conferenceroom_renderer extends section_renderer {

    /**
     * Constructor method, calls the parent constructor
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);

        // Since format_conferenceroom_renderer::section_edit_controls() only displays the 'Set current section' control when editing mode is on
        // we need to be sure that the link 'Turn editing mode on' is available for a user who does not have any other managing capability.
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    private function print_course_banner($formattedcourse) {
        global $PAGE, $USER, $OUTPUT;

        $html = '';
        $html .= '<!-- Conference rooom banner BEGIN -->';
        $html .= html_writer::start_div('page-section border-bottom-0 bg-primary800', array(
                    'id' => 'conferenceroom-banner',
                    'style' => 'height: 320px; background-color: rgb(5, 43, 45);'
        ));
        $html .= html_writer::start_div('narrow-page container page__container h-100 justify-content-center align-items-center');
        $html .= html_writer::start_div('d-flex flex-column flex-lg-row align-items-center h-100');
        $html .= html_writer::start_div('d-flex flex-column flex-md-row align-items-center  flex mb-16pt mb-lg-0 text-center text-md-left');
        $html .= html_writer::start_div('avatar avatar-xxl mr-5');
        $html .= html_writer::tag('img', '', array(
                    'src' => $OUTPUT->image_url('icon', 'format_conferenceroom')->out(false),
                    'class' => 'avatar avatar-xxl rounded',
                    'alt' => 'conferenceroom'
        ));
        $html .= html_writer::div('', 'overlay__content');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('flex', array(
                    'style' => 'max-width: 40rem;'
        ));
        $html .= html_writer::start_div('flex d-flex flex-row align-items-center');
        $html .= html_writer::tag('h1', $formattedcourse->fullname, array(
                    'style' => 'max-width: 616px; color: #F5FEFF !important; line-height: 1.0;'
        ));
        $uncompletevideos = $this->get_uncomplete_videos($formattedcourse);
        $numnonwatchedvideos = count($uncompletevideos);
        if ($numnonwatchedvideos > 0) {
            $html .= html_writer::start_div('ml-4');
            $html .= html_writer::start_tag('span', array(
                        'class' => 'badge-pill p-1 bg-purple100 pl-2 pr-2'
            ));
            $unwatchedvideostxt = get_string('unwatchedvideos', 'format_conferenceroom', $numnonwatchedvideos);
            $html .= html_writer::tag('b', $unwatchedvideostxt, array(
                        'style' => 'color: rgb(29, 0, 57);'
            ));
            $html .= html_writer::end_tag('span');
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div();
        $html .= html_writer::tag('small', $formattedcourse->summary, array(
                    'class' => 'w-25 text-white-70'
        ));
        $html .= '';
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('d-flex flex-column flex-sm-row align-items-center justify-content-start');
        $html .= html_writer::start_tag('a', array(
                    'href' => 'javascript:void(0);',
                    'onclick' => 'window.history.back();',
                    'class' => 'btn btn-outline-white btn-rounded mb-16pt mb-sm-0 mr-sm-16pt'
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-chevron-left fa-fw navicon mr-1'
        ));
        $html .= html_writer::end_tag('i');
        $html .= get_string('back');
        $html .= html_writer::end_tag('a');
        $coursecontext = context_course::instance($formattedcourse->id);
        if (has_capability('moodle/course:update', $coursecontext, $USER)) {
            $isediting = $PAGE->user_is_editing();
            $edittxt = ($isediting ? get_string('turneditingoff') : get_string('turneditingon'));
            $editurl = new moodle_url('/course/view.php', array(
                'id' => $formattedcourse->id,
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
        $html .= '<!-- Conference rooom banner END -->';
        return $html;
    }

    private function get_course_videos($formattedcourse, $availability = format_conferenceroom::BOTH) {
        global $DB;

        $videomodule = $DB->get_record('modules', array('name' => 'video'));
        $videomoduleid = $videomodule->id;
        $videos = array();
        $modinfo = get_fast_modinfo($formattedcourse);
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section > 0) {
                if (array_key_exists($section->section, $modinfo->sections)) {
                    foreach ($modinfo->sections[$section->section] as $modnumber) {
                        $mod = $modinfo->cms[$modnumber];
                        if ($mod->module == $videomoduleid &&
                                (($availability == format_conferenceroom::BOTH) ||
                                ($availability == format_conferenceroom::UNAVAILABLE && !$mod->available) ||
                                ($availability == format_conferenceroom::AVAILABLE && $mod->available))) {
                            $video = $DB->get_record('video', array('id' => $mod->instance));
                            $videos[$mod->id] = $video;
                        }
                    }
                }
            }
        }
        return $videos;
    }

    private function is_cm_completed($userid, $cmid) {
        global $DB;
        $record = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cmid, 'userid' => $userid));
        if ($record) {
            return ($record->completionstate == COMPLETION_COMPLETE || $record->completionstate == COMPLETION_COMPLETE_FAIL || $record->completionstate == COMPLETION_COMPLETE_PASS);
        } else {
            return false;
        }
    }

    private function get_first_available_video($formattedcourse) {
        global $DB;

        $availablevideos = $this->get_course_videos($formattedcourse, format_conferenceroom::AVAILABLE);
        foreach ($availablevideos as $cmid => $video) {
            $cm = $DB->get_record('course_modules', array('id' => $cmid));
            return array($cm, $video);
        }
        return array(null, null);
    }

    private function get_uncomplete_videos($formattedcourse) {
        global $USER;

        $coursevideos = $this->get_course_videos($formattedcourse, format_conferenceroom::AVAILABLE);
        $uncompletevideos = array();
        foreach ($coursevideos as $cmid => $video) {
            if (!$this->is_cm_completed($USER->id, $cmid)) {
                $uncompletevideos[] = $video;
            }
        }
        return $uncompletevideos;
    }

    private function get_cm_available_date($video) {
        $cm = get_coursemodule_from_instance('video', $video->id, $video->course, false, MUST_EXIST);
        $availability = json_decode($cm->availability);
        foreach ($availability->c as $availabilityitem) {
            if ($availabilityitem->type == 'date' && $availabilityitem->d == '>=') {
                $datestr = date('d/m/Y', $availabilityitem->t);
                return $datestr;
            }
        }
        return '';
    }

    private function print_course_header($formattedcourse) {
        global $CFG;

        $html = '';
        list($currentvideocm, $currentvideo) = $this->get_first_available_video($formattedcourse);
        $html .= '<!-- Conference room header BEGIN -->';
        $html .= html_writer::start_div('py-64pt mt-32pt', array(
                    'id' => 'conferenceroom-header',
                    'style' => 'background-color: white; margin: 0 !important; padding-top: 6rem !important; padding-bottom: 6rem !important;'
        ));
        $html .= html_writer::start_div('narrow-page container page__container');
        $html .= html_writer::start_div('row');
        if ($currentvideo != null) {
            $html .= '<!-- Current video column BEGIN -->';
            $html .= html_writer::start_div('col-md-8', array(
                        'id' => 'currentvideo',
                        'cmid' => $currentvideocm->id,
                        'courseid' => $currentvideocm->course
            ));
            $html .= html_writer::start_div('card h-100');
            $html .= html_writer::start_div('card-body p-0');
            $html .= html_writer::start_div();
            require_once($CFG->dirroot . '/mod/video/lib.php');
            $html .= video_print_player($currentvideocm);
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::start_div('card-body border-top-0');
            $currentvideotitle = $currentvideo->name;
            $html .= html_writer::tag('h1', $currentvideotitle, array(
                        'class' => 'h2 mb-3',
                        'style' => 'color: rgb(14, 66, 66); line-height: 1;'
            ));
            $currentvideosummary = strip_tags($currentvideo->intro);
            $html .= html_writer::tag('p', $currentvideosummary, array(
                        'class' => 'text-70',
                        'style' => 'color: rgba(39, 44, 51, 0.7);'
            ));
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= '<!-- Current video column END -->';
        }
        $html .= '<!-- Next videos column BEGIN -->';
        $html .= html_writer::start_div('col-md-4', array(
                    'style' => 'height: fit-content; color: rgb(48, 56, 64);'
        ));
        $html .= html_writer::start_div('card h-100');
        $html .= html_writer::start_div('card-header', array(
                    'style' => 'background-color: rgb(55, 182, 181);'
        ));
        $html .= html_writer::start_div('d-flex align-items-center');
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-play-circle-o',
                    'style' => 'color: white; font-size: 18px;'
        ));
        $html .= html_writer::end_tag('i');
        $nextvideostxt = get_string('nextvideos', 'format_conferenceroom');
        $html .= html_writer::tag('h4', $nextvideostxt, array(
                    'class' => 'card-header-title text-white mb-0'
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('card-body p-2');
        $MAXNEXT = 4;
        $nextvideos = $this->get_course_videos($formattedcourse, format_conferenceroom::UNAVAILABLE);
        $i = 0;
        foreach ($nextvideos as $nextvideo) {
            if ($i >= $MAXNEXT) {
                break;
            }
            if ($i > 0) {
                $html .= html_writer::tag('hr', '', array(
                            'class' => 'my-2'
                ));
            }
            $html .= '<!-- Next video BEGIN -->';
            $html .= html_writer::start_div('related-video-item disabled-video');
            $html .= html_writer::start_div('d-flex position-relative');
            $html .= html_writer::start_div('flex-shrink-0 position-relative video-thumbnail-container', array(
                        'style' => 'width: 140px;'
            ));
            $videothumbnail = video_get_thumbnail_url($nextvideo->id);
            $html .= html_writer::tag('img ', '', array(
                        'src' => $videothumbnail,
                        'alt' => 'video thumbnail',
                        'class' => 'img-fluid grayscale rounded'
            ));
            $html .= html_writer::start_div('video-overlay rounded');
            $html .= html_writer::start_tag('i', array(
                        'class' => 'icon fa fa-lock',
                        'style' => 'color: white; font-size: 28px;'
            ));
            $html .= html_writer::end_tag('i');
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::start_div('flex-grow-1 pl-3 py-2');
            list($videotitle, ) = video_extract_duration_str_from_title($nextvideo);
            $html .= html_writer::tag('h6', $videotitle, array(
                        'class' => 'm-0'
            ));
            $availablein = $this->get_cm_available_date($nextvideo);
            if (!empty($availablein)) {
                $html .= html_writer::start_div('d-flex align-items-center mt-1', array(
                            'style' => 'color: rgb(55, 182, 181);'
                ));
                $html .= html_writer::start_tag('i', array(
                            'class' => 'icon fa fa-clock-o'
                ));
                $html .= html_writer::end_tag('i');
                $availableintxt = get_string('availablein', 'format_conferenceroom', $availablein);
                $html .= html_writer::tag('small', $availableintxt, array(
                            'class' => 'text-primary700',
                            'style' => 'font-size: 10px;'
                ));
                $html .= html_writer::end_div();
            }
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= '<!-- Next video END -->';
            ++$i;
        }

        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Next videos column END -->';

        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Conference room header END -->';
        return $html;
    }

    private function print_course_content($formattedcourse) {
        global $PAGE;

        $html = '';
        $html .= '<!-- Conference room content BEGIN -->';
        $html .= html_writer::start_div('container-xxl page-section pl-64pt pr-64pt mt-32pt bg-primary100 border-top-1', array(
                    'id' => 'conferenceroom-content',
                    'style' => 'background-color: rgb(245, 254, 255); margin-top: 0 !important; padding-top: 2rem !important; padding-bottom: 3rem;'
        ));
        $html .= $this->print_course_sections($formattedcourse);
        // Print section add control
        $context = context_course::instance($formattedcourse->id);
        if ($PAGE->user_is_editing() and has_capability('moodle/course:update', $context)) {
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
        }
        $html .= '<!-- Conference room content END -->';
        return $html;
    }

    /**
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
        global $CFG, $PAGE;

        $html = '';
        $html .= '<!-- Multi section page BEGIN -->';
        $formattedcourse = course_get_format($course)->get_course();
        $html .= $this->print_course_banner($formattedcourse);
        $html .= $this->print_course_header($formattedcourse);
        $html .= $this->print_course_content($formattedcourse);
        $html .= '<!-- Multi section page END -->';
        $sesskey = sesskey();
        $html .= html_writer::tag('input', '', array(
                    'type' => 'hidden',
                    'id' => 'sesskey',
                    'name' => 'sesskey',
                    'value' => $sesskey
        ));
        echo $html;
        return;
    }

    protected function print_course_sections($formattedcourse) {
        $html = '';
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
        return $html;
    }

    protected function print_section($formattedcourse, section_info $section) {
        global $onlynames, $PAGE;

        $html = '';
        if ($section->uservisible) {
            $html .= '<!-- Section content BEGIN -->';
            $html .= html_writer::start_div('section', array(
                        'id' => 'section-' . $section->section,
            ));
            $html .= html_writer::start_div('container-xl border-left-0 page-section pl-32pt pb-8pt pt-0 mt-0  pt-32pt');
            if ($PAGE->user_is_editing()) {
                $html .= html_writer::start_div('', array(
                            'style' => 'display: block ruby;'
                ));
                $rightcontent = $this->section_right_content($section, $formattedcourse, true);
                $html .= html_writer::tag('div', $rightcontent, array('class' => 'side'));
                $html .= html_writer::end_div();
            }

            $html .= html_writer::start_div('d-flex align-items-center page-num-container');
            $html .= html_writer::start_div('page-num', array(
                        'style' => 'background-color: rgb(205, 254, 253);'
            ));
            $html .= html_writer::start_tag('i', array(
                        'class' => 'icon fa fa-play',
                        'style' => 'color: #303840; font-size: 18px; margin-left: 13px;'
            ));
            $html .= html_writer::end_tag('i');
            $html .= html_writer::end_div();
            $sectionname = $section->name;
            $html .= html_writer::tag('h4', $sectionname, array(
                        'style' => 'color: rgb(48, 56, 64); margin-bottom: 0;'
            ));
            $html .= html_writer::end_div();
            $sectionsummary = strip_tags($section->summary);
            $html .= html_writer::tag('p', $sectionsummary, array(
                        'class' => 'text-70 mb-lg-32pt',
                        'style' => 'color: rgba(39, 44, 51, .7) !important; margin-left: 0.2rem;'
            ));

            $html .= $this->section_header($section, $formattedcourse, false, 0);
            $html .= $this->course_section_cm_list($formattedcourse, $section, null, array('onlynames' => $onlynames));
            $html .= $this->section_footer();
            $html .= html_writer::start_div('', array(
                        'style' => 'display: block ruby;'
            ));
            $html .= $this->courserenderer->course_section_add_cm_control($formattedcourse, $section->section, 0);
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= '<!-- Section content END -->';
        }
        return $html;
    }

    public function course_section_cm_list($course, $section, $sectionreturn = null, $displayoptions = []) {
        global $USER, $PAGE;

        debugging('course_section_cm_list is deprecated. Use core_courseformat\\output\\local\\content\\section\\cmlist ' .
                'classes instead.', DEBUG_DEVELOPER);

        $output = '';

        $format = course_get_format($course);
        $modinfo = $format->get_modinfo();

        if (is_object($section)) {
            $section = $modinfo->get_section_info($section->section);
        } else {
            $section = $modinfo->get_section_info($section);
        }
        $completioninfo = new completion_info($course);

        // check if we are currently in the process of moving a module with JavaScript disabled
        $ismoving = $format->show_editor() && ismoving($course->id);

        if ($ismoving) {
            $strmovefull = strip_tags(get_string("movefull", "", "'$USER->activitycopyname'"));
        }

        // Get the list of modules visible to user (excluding the module being moved if there is one)
        $moduleshtml = [];
        if (!empty($modinfo->sections[$section->section])) {
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];
                if (!$mod->available && !$PAGE->user_is_editing() && !is_siteadmin()) {
                    continue;
                }
                if ($ismoving and $mod->id == $USER->activitycopy) {
                    // do not display moving mod
                    continue;
                }
                if ($modulehtml = $this->courserenderer->course_section_cm_list_item($course, $completioninfo, $mod, $sectionreturn, $displayoptions)) {
                    $moduleshtml[$modnumber] = $modulehtml;
                }
            }
        }

        $sectionoutput = '';
        if (!empty($moduleshtml) || $ismoving) {
            foreach ($moduleshtml as $modnumber => $modulehtml) {
                if ($ismoving) {
                    $movingurl = new moodle_url('/course/mod.php', array('moveto' => $modnumber, 'sesskey' => sesskey()));
                    $sectionoutput .= html_writer::tag('li', html_writer::link($movingurl, '', array('title' => $strmovefull, 'class' => 'movehere')), array('class' => 'movehere'));
                }

                $sectionoutput .= $modulehtml;
            }

            if ($ismoving) {
                $movingurl = new moodle_url('/course/mod.php', array('movetosection' => $section->id, 'sesskey' => sesskey()));
                $sectionoutput .= html_writer::tag('li', html_writer::link($movingurl, '', array('title' => $strmovefull, 'class' => 'movehere')), array('class' => 'movehere'));
            }
        }

        // Always output the section module list.
        $output .= html_writer::tag('ul', $sectionoutput, array('class' => 'section img-text'));

        return $output;
    }

    /**
     * Generate the display of the header part of a section before
     * course modules are included
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a single-section page
     * @param int $sectionreturn The section to return to after an action
     * @return string HTML to output.
     */
    protected function section_header($section, $course, $onsectionpage, $sectionreturn = null) {
        $html = '';
        $html .= '<!-- Carousel content BEGIN -->';
        $html .= html_writer::start_div('position-relative carousel-card');
        $html .= html_writer::start_div('carousel slide', array(
                    'id' => 'carousel-videos',
                    'data-ride' => 'carousel',
                    'data-interval' => 'false'
        ));
        $html .= html_writer::start_tag('a', array(
                    'class' => 'carousel-control-prev',
                    'href' => '#carousel-videos',
                    'role' => 'button',
                    'data-slide' => 'prev'
        ));
        $html .= html_writer::tag('span', '', array(
                    'class' => 'carousel-control-prev-icon',
                    'aria-hidden' => 'true'
        ));
        $html .= html_writer::tag('span', 'Previous', array(
                    'class' => 'sr-only'
        ));
        $html .= html_writer::end_tag('a');
        $html .= html_writer::start_tag('a', array(
                    'class' => 'carousel-control-next',
                    'href' => '#carousel-videos',
                    'role' => 'button',
                    'data-slide' => 'next'
        ));
        $html .= html_writer::tag('span', '', array(
                    'class' => 'carousel-control-next-icon',
                    'aria-hidden' => 'true'
        ));
        $html .= html_writer::tag('span', 'Next', array(
                    'class' => 'sr-only'
        ));
        $html .= html_writer::end_tag('a');
        $html .= html_writer::start_div('carousel-inner', array(
                    'style' => 'width: 95%;'
        ));
        return $html;
    }

    /**
     * Generate the display of the footer part of a section
     *
     * @return string HTML to output.
     */
    protected function section_footer() {
        $html = '';
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Carousel content END -->';
        return $html;
    }

    /**
     * Output the html for a single section page .
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     * @param int $displaysection The section number in the course which is being displayed
     */
    public function print_single_section_page($course, $sections, $mods, $modnames, $modnamesused, $displaysection) {
        global $PAGE;

        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        // Can we view the section in question?
        if (!($sectioninfo = $modinfo->get_section_info($displaysection))) {
            // This section doesn't exist
            print_error('unknowncoursesection', 'error', null, $course->fullname);
            return;
        }

        if (!$sectioninfo->uservisible) {
            if (!$course->hiddensections) {
                echo $this->start_section_list();
                echo $this->section_hidden($displaysection, $course->id);
                echo $this->end_section_list();
            }
            // Can't view this section.
            return;
        }

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, $displaysection);
        $thissection = $modinfo->get_section_info(0);
        if ($thissection->summary or ! empty($modinfo->sections[0]) or $PAGE->user_is_editing()) {
            echo $this->start_section_list();
            echo $this->section_header($thissection, $course, true, $displaysection);
            echo $this->courserenderer->course_section_cm_list($course, $thissection, $displaysection);
            echo $this->section_footer();
            echo $this->courserenderer->course_section_add_cm_control($course, 0, $displaysection);
            echo $this->end_section_list();
        }

        // Start single-section div
        echo html_writer::start_tag('div', array('class' => 'single-section'));

        // The requested section page.
        $thissection = $modinfo->get_section_info($displaysection);

        // Title with section navigation links.
        $sectionnavlinks = $this->get_nav_links($course, $modinfo->get_section_info_all(), $displaysection);
        $sectiontitle = '';
        $sectiontitle .= html_writer::start_tag('div', array('class' => 'section-navigation navigationtitle'));
        $sectiontitle .= html_writer::tag('span', $sectionnavlinks['previous'], array('class' => 'mdl-left'));
        $sectiontitle .= html_writer::tag('span', $sectionnavlinks['next'], array('class' => 'mdl-right'));
        // Title attributes
        $classes = 'sectionname';
        if (!$thissection->visible) {
            $classes .= ' dimmed_text';
        }
        $sectionname = html_writer::tag('span', $this->section_title_without_link($thissection, $course));
        $sectiontitle .= $this->output->heading($sectionname, 3, $classes);

        $sectiontitle .= html_writer::end_tag('div');
        echo $sectiontitle;

        // Now the list of sections..
        echo $this->start_section_list();

        echo $this->section_header($thissection, $course, true, $displaysection);
        // Show completion help icon.
        $completioninfo = new completion_info($course);

        echo $this->courserenderer->course_section_cm_list($course, $thissection, $displaysection);
        echo $this->courserenderer->course_section_add_cm_control($course, $displaysection, $displaysection);
        echo $this->section_footer();
        echo $this->end_section_list();

        // Display section bottom navigation.
        $sectionbottomnav = '';
        $sectionbottomnav .= html_writer::start_tag('div', array('class' => 'section-navigation mdl-bottom'));
        $sectionbottomnav .= html_writer::tag('span', $sectionnavlinks['previous'], array('class' => 'mdl-left'));
        $sectionbottomnav .= html_writer::tag('span', $sectionnavlinks['next'], array('class' => 'mdl-right'));
        $sectionbottomnav .= html_writer::tag('div', $this->section_nav_selection($course, $sections, $displaysection), array('class' => 'mdl-align'));
        $sectionbottomnav .= html_writer::end_tag('div');
        echo $sectionbottomnav;

        // Close single-section div.
        echo html_writer::end_tag('div');
    }

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('div', array('class' => 'panel-group sections', 'id' => 'sections'));
    }

    /**
     * Generating a course summary block
     * 
     * @param object $course Course which summary will be printed
     * @return string HTML representation of a course summary block
     */
    protected function course_summary(coursecat_helper $chelper, core_course_list_element $course): string {
        $html = '';
        $html .= html_writer::start_tag('div', array('class' => 'coursesummary'));
        $html .= html_writer::start_tag('div', array('class' => 'coursesummarylabel'));
        $html .= get_string('coursesummary') . ":" . "&nbsp";
        $html .= html_writer::end_tag('div'); // coursesummarylabel
        $html .= html_writer::start_tag('div', array('class' => 'coursesummarytext'));
        $html .= $course->summary;
        $html .= html_writer::end_tag('div'); // coursesummarytext
        $html .= html_writer::end_tag('div'); // coursesummary
        return $html;
    }

    /**
     * Generate the closing container html for a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('div');
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title() {
        return get_string('topicoutline');
    }

    /**
     * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title to be displayed on the section page, without a link
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }

    /**
     * Generate the edit control items of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of edit control items
     */
    protected function section_edit_control_items($course, $section, $onsectionpage = false) {
        global $PAGE;

        if (!$PAGE->user_is_editing()) {
            return array();
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $isstealth = $section->section > $course->numsections;
        $controls = array();
        if (!$isstealth && $section->section && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $markedthistopic = get_string('markedthistopic');
                $highlightoff = get_string('highlightoff');
                $controls['highlight'] = array('url' => $url, "icon" => 'i/marked',
                    'name' => $highlightoff,
                    'pixattr' => array('class' => '', 'alt' => $markedthistopic),
                    'attr' => array('class' => 'editing_highlight', 'title' => $markedthistopic));
            } else {
                $url->param('marker', $section->section);
                $markthistopic = get_string('markthistopic');
                $highlight = get_string('highlight');
                $controls['highlight'] = array('url' => $url, "icon" => 'i/marker',
                    'name' => $highlight,
                    'pixattr' => array('class' => '', 'alt' => $markthistopic),
                    'attr' => array('class' => 'editing_highlight', 'title' => $markthistopic));
            }
        }

        $parentcontrols = parent::section_edit_control_items($course, $section, $onsectionpage);

        // If the edit key exists, we are going to insert our controls after it.
        if (array_key_exists("edit", $parentcontrols)) {
            $merged = array();
            // We can't use splice because we are using associative arrays.
            // Step through the array and merge the arrays.
            foreach ($parentcontrols as $key => $action) {
                $merged[$key] = $action;
                if ($key == "edit") {
                    // If we have come to the edit key, merge these controls here.
                    $merged = array_merge($merged, $controls);
                }
            }

            return $merged;
        } else {
            return array_merge($controls, $parentcontrols);
        }
    }

    protected function group_evals_by_stars($evals) {
        $groups = array();
        $groups[5] = 0;
        $groups[4] = 0;
        $groups[3] = 0;
        $groups[2] = 0;
        $groups[1] = 0;
        foreach ($evals as $eval) {
            ++$groups[$eval->evaluation];
        }
        return $groups;
    }

    /**
     * Retrives the next course module after the current section
     * 
     * @param course_modinfo $cminfo Course module info
     * @param section_info $section Section info
     * @return cm_info or null
     */
    private function get_next_cm(course_modinfo $cminfo, section_info $section) {
        global $PAGE;

        $nextsectionnum = $section->section + 1;
        if (isset($cminfo->sections[$nextsectionnum])) {
            $nextsection = $cminfo->sections[$nextsectionnum];
            foreach ($nextsection as $modnumber) {
                $mod = $cminfo->cms[$modnumber];
                if (($mod->available && local_studypace\studypace::is_mandatory($mod)) || $PAGE->user_allowed_editing()) {
                    return $mod;
                }
            }
        }
        return null;
    }

    public function print_section_navbar($section, $cm) {
        global $COURSE, $PAGE;

        $modinfo = get_fast_modinfo($COURSE);
        if (is_object($section)) {
            $section = $modinfo->get_section_info($section->section);
        } else {
            $section = $modinfo->get_section_info($section);
        }
        $html = '';
        $html .= html_writer::start_div('section-navbar');
        $html .= html_writer::tag('h4', $section->section . '. ' .
                        get_string('module', 'mod_video') . ': ' . $section->name, array(
                    'class' => 'text-white-50 mb-1',
        ));
        $html .= html_writer::start_tag('nav', array(
                    'class' => 'course-section-nav',
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
            $nextcm = $this->get_next_cm($modinfo, $section);
            if ($nextcm) {
                $html .= html_writer::start_tag('a', array(
                            'href' => $nextcm->url,
                            'class' => 'iconplaceholder',
                ));
                $html .= html_writer::tag('i', 'double_arrow', array(
                            'class' => 'material-icons icon-24pt mr-2 mb-2 next_section text-white-50',
                            'data-toggle' => 'tooltip',
                            'data-placement' => 'top',
                            'title' => get_string('nextsection', 'format_conferenceroom')));
                $html .= html_writer::end_tag('a');
            }
            $html .= html_writer::end_tag('nav');
            return $html;
        }
    }

}

<?php

/**
 * Renderer for outputting the videogallery course format.
 *
 * @package    format_videogallery
 * @copyright  2024 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/renderer.php');

use core_courseformat\output\section_renderer;

/**
 * Basic renderer for videogallery format.
 *
 * @package    format_videogallery
 * @copyright  2024 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class format_videogallery_renderer extends section_renderer {

    /**
     * Constructor method, calls the parent constructor
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);

        // Since format_videogallery_renderer::section_edit_controls() only displays the 'Set current section' control when editing mode is on
        // we need to be sure that the link 'Turn editing mode on' is available for a user who does not have any other managing capability.
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    private function get_course_videos($formattedcourse) {
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
                        if ($mod->module == $videomoduleid) {
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

    private function get_uncomplete_videos($formattedcourse) {
        global $USER;

        $coursevideos = $this->get_course_videos($formattedcourse);
        $uncompletevideos = array();
        foreach ($coursevideos as $cmid => $video) {
            if (!$this->is_cm_completed($USER->id, $cmid)) {
                $uncompletevideos[] = $video;
            }
        }
        return $uncompletevideos;
    }

    private function print_page_header($formattedcourse) {
        global $PAGE, $USER, $OUTPUT;

        echo '<!-- Page header BEGIN -->';
        echo html_writer::start_div('page-section border-bottom-1', array(
            'id' => 'videogalleryheader',
            'style' => 'min-height: 400px; background-color: #44007A !important; padding-bottom: 0; border: none !important; display: flex; flex-flow: row; align-items: center;'
        ));
        echo html_writer::start_div('narrow-page container page__container h-100 justify-content-center align-items-center');
        echo html_writer::start_div('d-flex flex-column flex-lg-row align-items-center h-100');
        echo html_writer::start_div('d-flex flex-column flex-md-row align-items-center  flex mb-16pt mb-lg-0 text-center text-md-left');
        echo html_writer::start_div('avatar avatar-xxl mb-16pt mb-md-0 mr-md-32pt');
        echo html_writer::tag('img', '', array(
            'src' => $OUTPUT->image_url('logo', 'format_videogallery')->out(false),
            'class' => 'avatar avatar-xxl rounded',
            'alt' => 'logo'
        ));
        echo html_writer::div('', 'overlay__content');
        echo html_writer::end_div();
        echo html_writer::start_div('flex measure-lead-max');
        echo html_writer::start_div('flex d-flex flex-row align-items-center', array(
            'style' => 'margin-bottom: 1rem;'
        ));
        $coursetitle = $formattedcourse->fullname;
        echo html_writer::tag('h1', $coursetitle, array(
            'class' => 'measure-lead-max',
            'style' => 'color: rgb(245, 254, 255); '
        ));
        $uncompletevideos = $this->get_uncomplete_videos($formattedcourse);
        $newvideos = count($uncompletevideos);
        if ($newvideos > 0) {
            echo html_writer::start_div('ml-4');
            echo html_writer::start_tag('span', array(
                'class' => 'badge-pill pl-16pt pr-16pt p-1 border-0 bg-purple100 text-purple700'
            ));
            $newvideostxt = get_string('unwatchedvideos', 'format_videogallery', $newvideos);
            echo html_writer::tag('b', $newvideostxt, array(
                'style' => 'color: rgb(29, 0, 57);'
            ));
            echo html_writer::end_tag('span');
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
        $coursesummary = strip_tags($formattedcourse->summary);
        echo html_writer::tag('small', $coursesummary, array(
            'class' => 'w-25 text-white-70'
        ));
        echo '<!-- Section 0 BEGIN -->';
        echo html_writer::start_div('d-flex mt-16pt flex-column');
        $modinfo = get_fast_modinfo($formattedcourse);
        foreach ($modinfo->get_section_info_all() as $sectionnum => $section) {
            if ($sectionnum == 0) {
                $this->print_header_section($formattedcourse, $section);
            }
        }
        echo '<!-- Section 0 END -->';
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::start_div('d-flex flex-column flex-sm-row align-items-center justify-content-start');
        echo html_writer::start_tag('a', array(
            'href' => 'javascript:void(0);',
            'onclick' => 'window.history.back();',
            'class' => 'btn btn-outline-white btn-rounded mb-16pt mb-sm-0 mr-sm-16pt'
        ));
        echo html_writer::start_tag('i', array(
            'class' => 'icon fa fa-chevron-left'
        ));
        echo html_writer::end_tag('i');
        echo get_string('back');
        echo html_writer::end_tag('a');
        $coursecontext = context_course::instance($formattedcourse->id);
        if (has_capability('moodle/course:update', $coursecontext, $USER)) {
            $isediting = $PAGE->user_is_editing();
            $edittxt = ($isediting ? get_string('turneditingoff') : get_string('turneditingon'));
            $editurl = new moodle_url('/course/view.php', array(
                'id' => $formattedcourse->id,
                'edit' => ($isediting ? 'off' : 'on'),
                'sesskey' => sesskey()
            ));
            echo html_writer::tag('a', $edittxt, array(
                'href' => $editurl,
                'class' => 'btn btn-clear btn-rounded ml-2',
                'style' => 'text-wrap: nowrap;',
                'title' => $edittxt
            ));
        }
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo '<!-- Page header END -->';
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
        global $PAGE;

        echo '<!-- Multisection page BEGIN -->';
        $formattedcourse = course_get_format($course)->get_course();
        $this->print_page_header($formattedcourse);
        // Print course sections
        $this->print_course_sections($formattedcourse);
        // Print section add control
        $context = context_course::instance($course->id);
        if ($PAGE->user_is_editing() and has_capability('moodle/course:update', $context)) {
            echo html_writer::start_tag('div', array(
                'id' => 'changenumsections',
                'class' => 'mdl-right',
                'style' => 'background-color: white;'
            ));
            // Increase number of sections.
            $straddsection = get_string('increasesections', 'moodle');
            $url = new moodle_url('/course/changenumsections.php', array('courseid' => $course->id,
                'increase' => true,
                'sesskey' => sesskey()));
            $icon = $this->output->pix_icon('t/switch_plus', $straddsection);
            echo html_writer::link($url, $icon . get_accesshide($straddsection), array('class' => 'increase-sections'));
            if ($course->numsections > 0) {
                // Reduce number of sections sections.
                $strremovesection = get_string('reducesections', 'moodle');
                $url = new moodle_url('/course/changenumsections.php', array('courseid' => $course->id,
                    'increase' => false,
                    'sesskey' => sesskey()));
                $icon = $this->output->pix_icon('t/switch_minus', $strremovesection);
                echo html_writer::link($url, $icon . get_accesshide($strremovesection), array('class' => 'reduce-sections'));
            }
            echo html_writer::end_tag('div');
        }
        echo '<!-- Multisection page END -->';
    }

    protected function print_course_sections($formattedcourse) {
        echo "<!-- Course sections BEGIN -->";
        echo html_writer::start_div('coursecontent container-xxl', array(
            'id' => 'videogallerycoursesections',
            'style' => 'background-color: rgb(255, 255, 255); padding-top: 32px; padding-bottom: 4rem;'
        ));
        $modinfo = get_fast_modinfo($formattedcourse);
        foreach ($modinfo->get_section_info_all() as $sectionnum => $section) {
            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display.
            $showsection = ($sectionnum != 0) && ($section->uservisible ||
                    ($section->visible && !$section->available &&
                    !empty($section->availableinfo)));
            if ($showsection) {
                echo '<!-- Printing section ' . $sectionnum . ' BEGIN -->';
                $this->print_section($formattedcourse, $section);
                echo '<!-- Printing section  ' . $sectionnum . ' END -->';
            }
        }
        echo html_writer::end_div();
        echo "<!-- Course sections END -->";
    }

    protected function print_header_section($formattedcourse, section_info $section) {
        global $onlynames;

        if ($section->uservisible) {
            echo $this->section_header($section, $formattedcourse, false, 0);
            echo $this->courserenderer->course_section_cm_list($formattedcourse, $section, null, array('carousel' => false));
            echo $this->courserenderer->course_section_add_cm_control($formattedcourse, $section->section, 0);
            echo $this->section_footer();
        }
    }

    protected function print_section($formattedcourse, section_info $section) {
        global $onlynames;

        if ($section->uservisible) {
            echo '<!-- Section BEGIN -->';
            echo $this->section_header($section, $formattedcourse, false, 0);
            echo '<!-- Section carousel BEGIN -->';
            echo html_writer::start_div('position-relative carousel-card');
            echo html_writer::start_div('carousel slide', array(
                'id' => 'carousel-section' . $section->section
            ));
            echo html_writer::start_tag('a', array(
                'class' => 'carousel-control-prev',
                'href' => '#carousel-section' . $section->section,
                'role' => 'button',
                'data-slide' => 'prev'
            ));
            echo html_writer::tag('span', '', array(
                'class' => 'carousel-control-prev-icon',
                'aria-hidden' => 'true'
            ));
            echo html_writer::tag('span', 'Previous', array(
                'class' => 'sr-only'
            ));
            echo html_writer::end_tag('a');
            echo html_writer::start_tag('a', array(
                'class' => 'carousel-control-next',
                'href' => '#carousel-section' . $section->section,
                'role' => 'button',
                'data-slide' => 'next'
            ));
            echo html_writer::tag('span', '', array(
                'class' => 'carousel-control-next-icon',
                'aria-hidden' => 'true'
            ));
            echo html_writer::tag('span', 'Next', array(
                'class' => 'sr-only'
            ));
            echo html_writer::end_tag('a');
            echo '<!-- Carousel content BEGIN -->';
            echo html_writer::start_div('carousel-inner');
            echo $this->courserenderer->course_section_cm_list($formattedcourse, $section, null, array('onlynames' => $onlynames));
            echo html_writer::end_div();
            echo '<!-- Carousel content END -->';
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo '<!-- Section carousel END -->';
            echo '<!-- Section controls BEGIN -->';
            echo $this->courserenderer->course_section_add_cm_control($formattedcourse, $section->section, 0);
            echo '<!-- Section controls END -->';
            echo $this->section_footer();
            echo '<!-- Section END -->';
        }
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
        global $PAGE;

        if ($PAGE->user_is_editing()) {
            $rightcontent = $this->section_right_content($section, $course, $onsectionpage);
            echo html_writer::tag('div', $rightcontent, array('class' => 'right side'));
        }
        $sessioname = empty($section->name) ? get_string('section', 'format_videogallery') . ' ' . $section->section : $section->name;
        echo '<!-- Start section content -->';
        echo html_writer::start_div('section page-section pl-64pt pr-64pt', array(
            'id' => 'section-' . $section->section
        ));
        if ($section->section != 0) {
            echo '<!-- Section info BEGIN -->';
            echo html_writer::start_div('d-flex align-items-center page-num-container pl-48pt');
            echo html_writer::start_div('page-num bg-purple100');
            echo html_writer::start_tag('i', array(
                'class' => 'icon fa fa-play',
                'style' => 'color: #303840; font-size: 18px; margin-left: 13px;'
            ));
            echo html_writer::end_tag('i');
            echo html_writer::end_div();
            echo html_writer::tag('h4', $sessioname, array(
                'style' => 'color: #303840;'
            ));
            echo html_writer::end_div();
            echo html_writer::tag('p', strip_tags($section->summary), array(
                'class' => 'text-70 mb-lg-32pt',
                'style' => 'color: rgba(39, 44, 51, 0.7);'
            ));
            echo '<!-- Section info END -->';
        }
    }

    /**
     * Generate the display of the footer part of a section
     *
     * @return string HTML to output.
     */
    protected function section_footer() {
        echo html_writer::end_div();
        echo '<!-- End section content -->';
        return;
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
            echo $this->courserenderer->course_section_add_cm_control($course, 0, $displaysection);
            echo $this->section_footer();
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
        global $COURSE;

        $modinfo = get_fast_modinfo($COURSE);
        if (is_object($section)) {
            $section = $modinfo->get_section_info($section->section);
        } else {
            $section = $modinfo->get_section_info($section);
        }
        $html = '';
        $html .= html_writer::start_div('section-navbar', array('style' => 'padding-bottom: 0;'));
        $html .= html_writer::start_tag('nav', array(
                    'class' => 'course-section-nav',
        ));
        if (!empty($modinfo->sections[$section->section])) {
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];
                if ($mod->visible) {
                    $completioninfo = new completion_info($mod->get_course());
                    $completion = $completioninfo->is_enabled($mod);
                    if ($completion == COMPLETION_TRACKING_NONE) {
                        $iconhtml = html_writer::empty_tag('img', array(
                                    'class' => 'cm-icon',
                                    'src' => $mod->get_icon_url()
                        ));
                        $circleclass = 'available';
                    } else {
                        $completiondata = $completioninfo->get_data($mod, true);
                        switch ($completiondata->completionstate) {
                            case COMPLETION_COMPLETE:
                            case COMPLETION_COMPLETE_PASS:
                                $circleclass = 'completed';
                                $iconhtml = html_writer::tag('span', 'check_circle', array(
                                            'class' => 'material-icons',
                                            'style' => 'color: rgb(245, 247, 250) !important;'
                                ));
                                break;
                            default:
                                $circleclass = 'available';
                                $iconhtml = html_writer::empty_tag('img', array(
                                            'class' => 'cm-icon',
                                            'style' => 'color: rgba(39, 44, 51, 0.7) !important;',
                                            'src' => $mod->get_icon_url()
                                ));
                        }
                    }
                    if ($mod->uservisible) {
                        $tag = 'a';
                        $opacity = 1;
                    } else {
                        $tag = 'span';
                        $opacity = .4;
                    }
                    $tag = $mod->uservisible ? 'a' : 'span';
                    $html .= html_writer::start_tag($tag, array(
                                'data-toggle' => 'tooltip',
                                'data-placement' => 'bottom',
                                'data-title' => $mod->name,
                                'href' => $mod->url,
                                'class' => 'iconplaceholder' . ' ' . $circleclass . ($cm->id == $mod->id ? ' current' : ''),
                                'data-original-title' => '',
                                'title' => $mod->name,
                                'style' => 'opacity: ' . $opacity . ';'
                    ));
                    $html .= $iconhtml;
                    $html .= html_writer::end_tag($tag);
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
                            'title' => get_string('nextsection', 'format_videogallery')));
                $html .= html_writer::end_tag('a');
            }
            $html .= html_writer::end_tag('nav');
            $html .= html_writer::end_div();
            return $html;
        }
    }

}

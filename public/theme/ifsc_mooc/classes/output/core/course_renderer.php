<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Course renderer for IFSC MOOC.
 *
 * @package   theme_ifsc_mooc
 * @copyright 2026 Instituto Federal de Santa Catarina
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_ifsc_mooc\output\core;

defined('MOODLE_INTERNAL') || die();

use core_course_category;
use coursecat_helper;
use html_writer;
use moodle_url;
use theme_ifsc_mooc\output\course_card;

/**
 * Renders course lists as homepage-style cards.
 *
 * @package   theme_ifsc_mooc
 */
class course_renderer extends \core_course_renderer {

    /**
     * Render the course category page as a card catalogue.
     *
     * @param core_course_category|int|\stdClass $category
     * @return string
     */
    public function course_category($category) {
        $usertop = core_course_category::user_top();
        if (empty($category)) {
            $coursecat = $usertop;
        } else if (is_object($category) && $category instanceof core_course_category) {
            $coursecat = $category;
        } else {
            $coursecat = core_course_category::get(is_object($category) ? $category->id : $category);
        }

        $actionbar = new \core_course\output\category_action_bar($this->page, $coursecat);
        $output = $this->render_from_template('core_course/category_actionbar', $actionbar->export_for_template($this));

        if (core_course_category::is_simple_site()) {
            $this->page->set_title(get_string('fulllistofcourses'));
        } else if (!$coursecat->id || !$coursecat->is_uservisible()) {
            $this->page->set_title(get_string('categories'));
        } else {
            $this->page->set_title(get_string('fulllistofcourses'));
        }

        $chelper = new coursecat_helper();
        if ($description = $chelper->get_category_formatted_description($coursecat)) {
            $output .= $this->box($description, ['class' => 'generalbox info']);
        }

        $output .= $this->render_from_template('theme_ifsc_mooc/course_catalogue', $this->export_catalogue($coursecat));
        return $output;
    }

    /**
     * Render a list of courses as cards.
     *
     * @param coursecat_helper $chelper
     * @param array $courses
     * @param int|null $totalcount
     * @return string
     */
    protected function coursecat_courses(coursecat_helper $chelper, $courses, $totalcount = null) {
        if ($totalcount === null) {
            $totalcount = count($courses);
        }
        if (!$totalcount && empty($courses)) {
            return '';
        }

        $cards = [];
        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            $cards[] = course_card::export($course, $this->output);
        }

        if (empty($cards)) {
            return '';
        }

        $attributes = $chelper->get_and_erase_attributes('courses');
        $classes = trim(($attributes['class'] ?? '') . ' ifsc-catalogue');
        $attributes['class'] = $classes;

        $html = html_writer::start_tag('div', $attributes);
        $html .= html_writer::start_div('row ifsc-course-grid');
        foreach ($cards as $card) {
            $html .= $this->render_from_template('theme_ifsc_mooc/course_card', $card);
        }
        $html .= html_writer::end_div();
        $html .= html_writer::end_tag('div');
        return $html;
    }

    /**
     * Build catalogue template data.
     *
     * @param core_course_category $coursecat
     * @return array
     */
    protected function export_catalogue(core_course_category $coursecat): array {
        $currentid = (int) $coursecat->id;
        $root = core_course_category::get(0);
        $categories = [[
            'name' => get_string('allcourses', 'theme_ifsc_mooc'),
            'url' => (new moodle_url('/course/index.php'))->out(false),
            'active' => empty($currentid),
        ]];

        foreach ($root->get_children() as $child) {
            if (!$child->is_uservisible()) {
                continue;
            }
            $categories[] = [
                'name' => $child->get_formatted_name(),
                'url' => (new moodle_url('/course/index.php', ['categoryid' => $child->id]))->out(false),
                'active' => $currentid === (int) $child->id,
            ];
        }

        $source = $currentid ? $coursecat : $root;
        $listed = $source->get_courses(['recursive' => true]);
        $courses = [];
        foreach ($listed as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            $courses[] = course_card::export($course, $this->output);
        }

        return [
            'hascategories' => count($categories) > 1,
            'categories' => $categories,
            'hascourses' => !empty($courses),
            'courses' => $courses,
        ];
    }
}

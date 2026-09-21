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
 * Course card data helper.
 *
 * @package   theme_ifsc_mooc
 * @copyright 2026 Instituto Federal de Santa Catarina
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_ifsc_mooc\output;

defined('MOODLE_INTERNAL') || die();

use core_course\external\course_summary_exporter;
use core_course_category;
use moodle_url;
use renderer_base;

/**
 * Builds the data used by the homepage and catalogue course cards.
 *
 * @package   theme_ifsc_mooc
 */
class course_card {

    /**
     * Export a Moodle course as a landing-style card.
     *
     * @param \core_course_list_element|\stdClass $course
     * @param renderer_base $output
     * @return array
     */
    public static function export($course, renderer_base $output): array {
        if ($course instanceof \stdClass) {
            $course = new \core_course_list_element($course);
        }

        $image = course_summary_exporter::get_course_image($course);
        if (empty($image)) {
            $image = $output->get_generated_image_for_id($course->id);
        }

        $categoryname = '';
        try {
            $category = core_course_category::get($course->category, IGNORE_MISSING);
            if ($category) {
                $categoryname = $category->get_formatted_name();
            }
        } catch (\Exception $e) {
            $categoryname = '';
        }

        $customfields = [];
        if ($course->has_custom_fields()) {
            foreach ($course->get_custom_fields() as $data) {
                $value = $data->export_value();
                if ($value === null || $value === '') {
                    continue;
                }
                $customfields[] = [
                    'name' => $data->get_field()->get_formatted_name(),
                    'value' => $value,
                    'shortname' => $data->get_field()->get('shortname'),
                    'type' => $data->get_field()->get('type'),
                ];
            }
        }

        return [
            'title' => $course->get_formatted_fullname(),
            'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'image' => $image,
            'category' => $categoryname,
            'price' => get_string('freecourse', 'theme_ifsc_mooc'),
            'hascustomfields' => !empty($customfields),
            'customfields' => $customfields,
            'id' => $course->id,
        ];
    }
}

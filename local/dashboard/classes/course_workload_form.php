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
 * Admin form to set workload hours per tracked course.
 *
 * @package   local_dashboard
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dashboard;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Workload hours form.
 *
 * @package   local_dashboard
 */
class course_workload_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $courses = $this->_customdata['courses'];
        $workloads = $this->_customdata['workloads'];

        $mform->addElement('static', 'intro', '', get_string('workloadintro', 'local_dashboard'));

        $this->add_course_section(
            $mform,
            'workloadeixosheader',
            get_string('workloadeixos', 'local_dashboard'),
            $courses['specialization'] ?? [],
            $workloads
        );

        $this->add_course_section(
            $mform,
            'workloadsalaheader',
            get_string('workloadsala', 'local_dashboard'),
            $courses['saladeconferencias'] ?? [],
            $workloads
        );

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Add one format group of course hour fields.
     *
     * @param \MoodleQuickForm $mform
     * @param string $headername
     * @param string $headertitle
     * @param \stdClass[] $courses
     * @param array<int,float> $workloads
     */
    private function add_course_section(
        $mform,
        string $headername,
        string $headertitle,
        array $courses,
        array $workloads
    ): void {
        $mform->addElement('header', $headername, $headertitle);

        if (empty($courses)) {
            $mform->addElement(
                'static',
                $headername . '_empty',
                '',
                get_string('workloadnocourses', 'local_dashboard')
            );
            return;
        }

        foreach ($courses as $course) {
            $field = 'workload_' . $course->id;
            $label = format_string($course->fullname);
            if (empty($course->visible)) {
                $label .= ' (' . get_string('hidden') . ')';
            }
            $mform->addElement('text', $field, $label, ['size' => 8]);
            $mform->setType($field, PARAM_FLOAT);
            if (isset($workloads[$course->id]) && $workloads[$course->id] > 0) {
                $mform->setDefault($field, $workloads[$course->id]);
            }
            $mform->addHelpButton($field, 'workloadhours', 'local_dashboard');
        }
    }

    /**
     * Submitted hours, including zeros so cleared fields are persisted as deletions.
     *
     * @return array<int,float> courseid => hours
     */
    public function get_submitted_workloads(): array {
        $data = $this->get_data();
        if (!$data) {
            return [];
        }

        $workloads = [];
        foreach ((array) $data as $key => $value) {
            if (strpos($key, 'workload_') !== 0) {
                continue;
            }
            $courseid = (int) substr($key, strlen('workload_'));
            if ($courseid <= 0) {
                continue;
            }
            if ($value === '' || $value === null) {
                $workloads[$courseid] = 0.0;
                continue;
            }
            $workloads[$courseid] = is_numeric($value) ? (float) $value : 0.0;
        }
        return $workloads;
    }

    /**
     * Validate submitted hours.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        foreach ($data as $key => $value) {
            if (strpos($key, 'workload_') !== 0 || $value === '' || $value === null) {
                continue;
            }
            if (!is_numeric($value) || (float) $value < 0) {
                $errors[$key] = get_string('workloadinvalid', 'local_dashboard');
            }
        }

        return $errors;
    }
}

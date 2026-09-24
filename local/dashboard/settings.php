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
 * Admin menu entry for local_dashboard.
 *
 * Adds a top-level admin category with the student coin ranking report and
 * the course workload hours used to weight dashboard progress. The pages
 * themselves are gated by 'moodle/site:config' (site administrators only).
 *
 * @package   local_dashboard
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('root', new admin_category('dashboard', get_string('admincategory', 'local_dashboard')));

    $ADMIN->add('dashboard', new admin_externalpage(
        'local_dashboard_ranking',
        get_string('rankingpageheading', 'local_dashboard'),
        new moodle_url('/local/dashboard/ranking.php'),
        'moodle/site:config'
    ));

    $ADMIN->add('dashboard', new admin_externalpage(
        'local_dashboard_workload',
        get_string('workloadpageheading', 'local_dashboard'),
        new moodle_url('/local/dashboard/workload.php'),
        'moodle/site:config'
    ));
}

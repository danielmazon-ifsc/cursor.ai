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
 * Admin settings for local_studypace.
 *
 * Adds a top-level admin category (same pattern as local_coin) so admins can
 * find the recalculate tool without browsing Plugins → Local plugins.
 *
 * @package   local_studypace
 * @copyright 2025 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('root', new admin_category('studypace', get_string('admincategory', 'local_studypace')));

    $ADMIN->add('studypace', new admin_externalpage(
        'local_studypace_recalculate',
        get_string('recalculatepageheading', 'local_studypace'),
        new moodle_url('/local/studypace/recalculate.php'),
        'moodle/site:config'
    ));

    $ADMIN->add('studypace', new admin_externalpage(
        'local_studypace_gamification_gaps',
        get_string('gapspageheading', 'local_studypace'),
        new moodle_url('/local/studypace/gamification_gaps.php'),
        'moodle/site:config'
    ));

    $ADMIN->add('studypace', new admin_externalpage(
        'local_studypace_repair_coins',
        get_string('repairrewardspageheading', 'local_studypace'),
        new moodle_url('/local/studypace/repair_coins.php'),
        'moodle/site:config'
    ));

    $ADMIN->add('studypace', new admin_externalpage(
        'local_studypace_sala_reset',
        get_string('salaresetpageheading', 'local_studypace'),
        new moodle_url('/local/studypace/sala_reset.php'),
        'moodle/site:config'
    ));

    $ADMIN->add('studypace', new admin_externalpage(
        'local_studypace_coin_upgrade_checks',
        get_string('upgradecheckspageheading', 'local_coin'),
        new moodle_url('/local/coin/upgrade_checks.php'),
        'moodle/site:config'
    ));
}

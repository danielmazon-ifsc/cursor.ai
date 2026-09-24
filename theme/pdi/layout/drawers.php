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
 * PDI override of boost's drawers layout.
 *
 * The only PDI-specific change is forcing the right drawer to be visible for users
 * who can edit blocks on the saladeconferencias course format. That format hides
 * the left drawer and starts with no blocks, so without this override the admin
 * has no toggler to open the right drawer / add blocks until they enable editing.
 *
 * @package   theme_pdi
 * @copyright 2025 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

$addblockbutton = $OUTPUT->addblockbutton();

user_preference_allow_ajax_update('drawer-open-index', PARAM_BOOL);
user_preference_allow_ajax_update('drawer-open-block', PARAM_BOOL);

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

if (defined('BEHAT_SITE_RUNNING')) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers'];
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

// PDI: on the saladeconferencias format, expose the right drawer to anyone who can
// manage blocks (typically admin/teacher) so they don't need to enable editing first
// to find the "Add a block" action.
$isconfigurableformat = (isset($COURSE->format) && $COURSE->format === 'saladeconferencias');
$canmanageblocks = false;
if ($isconfigurableformat) {
    try {
        $coursecontext = context_course::instance($COURSE->id);
        $canmanageblocks = $PAGE->user_can_edit_blocks()
            || has_capability('moodle/site:manageblocks', $coursecontext)
            || has_capability('moodle/block:edit', $coursecontext)
            || has_capability('moodle/course:update', $coursecontext);
    } catch (\Throwable $e) {
        $canmanageblocks = $PAGE->user_can_edit_blocks();
    }
}

// Make sure side-pre is a known region on this page so $OUTPUT->blocks('side-pre')
// works even when initialise_theme_and_output() was invoked under a layout that
// declared no regions. add_region() is idempotent — safe to call always.
if ($isconfigurableformat) {
    try {
        $PAGE->blocks->add_region('side-pre', false);
    } catch (\Throwable $e) {
        // The manager may complain after blocks have already been rendered; ignore.
    }
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));

// Synthesize an "Add a block" entry point for users who can manage blocks. The
// inner gate ($canmanageblocks) guarantees the synthetic button never reaches
// students or any role without the manageblocks / block:edit / course:update
// capabilities, so this branch only exposes the toggler to admins/teachers.
if ($isconfigurableformat && !$hasblocks && $canmanageblocks) {
    try {
        $params = ['bui_addblock' => '', 'sesskey' => sesskey(), 'edit' => 'on'];
        $url = new moodle_url($PAGE->url, $params);
        $addblockbutton = $OUTPUT->render_from_template('core/add_block_button', [
            'link' => $url->out(false),
            'escapedlink' => '?' . $url->get_query_string(false),
            'pageType' => $PAGE->pagetype,
            'pageLayout' => $PAGE->pagelayout,
            'subPage' => $PAGE->subpage,
        ]);
    } catch (\Throwable $e) {
        $addblockbutton = '';
    }
    $hasblocks = !empty($addblockbutton);
}


if (!$hasblocks) {
    $blockdraweropen = false;
} else if ($isconfigurableformat && $canmanageblocks && !$blockdraweropen) {
    // Open the block drawer by default so Administração is discoverable on Sala pages.
    $blockdraweropen = true;
}
$courseindex = core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton,
];

echo $OUTPUT->render_from_template('theme_boost/drawers', $templatecontext);

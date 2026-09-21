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
 * Theme functions for IFSC MOOC.
 *
 * @package   theme_ifsc_mooc
 * @copyright 2026 Instituto Federal de Santa Catarina
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_ifsc_mooc_get_main_scss_content($theme) {
    global $CFG;

    $scss = file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    $post = $CFG->dirroot . '/theme/ifsc_mooc/scss/post.scss';
    if (is_readable($post)) {
        $scss .= "\n" . file_get_contents($post);
    }

    return $scss;
}

/**
 * Inject additional SCSS from theme settings.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_ifsc_mooc_get_extra_scss($theme) {
    $content = '';
    $imageurl = $theme->setting_file_url('backgroundimage', 'backgroundimage');
    if (!empty($imageurl)) {
        $content .= '@media (min-width: 768px) {';
        $content .= 'body { background-image: url(\'' . $imageurl . '\'); background-size: cover; }';
        $content .= '}';
    }
    $loginbackgroundimageurl = $theme->setting_file_url('loginbackgroundimage', 'loginbackgroundimage');
    if (!empty($loginbackgroundimageurl)) {
        $content .= 'body.pagelayout-login #page { ';
        $content .= 'background-image: url(\'' . $loginbackgroundimageurl . '\'); background-size: cover;';
        $content .= ' }';
    }
    return !empty($theme->settings->scss) ? $theme->settings->scss . "\n" . $content : $content;
}

/**
 * Get SCSS to prepend (brand variables).
 *
 * Official IF colours (Manual da Marca, 2015): green #2f9e41, red #cd191e.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_ifsc_mooc_get_pre_scss($theme) {
    $scss = file_get_contents(__DIR__ . '/scss/pre.scss');

    $brand = !empty($theme->settings->brandcolor) ? $theme->settings->brandcolor : '#2f9e41';
    $scss .= '$primary: ' . $brand . ";\n";

    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }

    return $scss;
}

/**
 * Serves any files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_ifsc_mooc_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    $allowed = ['logo', 'backgroundimage', 'loginbackgroundimage', 'heroimage',
        'aboutimage1', 'aboutimage2', 'aboutimage3'];
    if ($context->contextlevel == CONTEXT_SYSTEM && in_array($filearea, $allowed, true)) {
        $theme = theme_config::load('ifsc_mooc');
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }
    send_file_not_found();
}

/**
 * Get the current user preferences that are available.
 *
 * @return array[]
 */
function theme_ifsc_mooc_user_preferences(): array {
    return theme_boost_user_preferences();
}

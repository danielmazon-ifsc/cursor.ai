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
 * Core renderer overrides for IFSC MOOC.
 *
 * @package   theme_ifsc_mooc
 * @copyright 2026 Instituto Federal de Santa Catarina
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_ifsc_mooc\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderers for the IFSC MOOC theme.
 *
 * @package   theme_ifsc_mooc
 * @copyright 2026 Instituto Federal de Santa Catarina
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_boost\output\core_renderer {

    /**
     * Horizontal IFSC logo used in the navbar.
     *
     * @return string
     */
    public function get_ifsc_logo_url(): string {
        return $this->image_url('logo_horizontal', 'theme')->out();
    }

    /**
     * Always show a navbar logo, falling back to the IFSC wordmark.
     *
     * @return bool
     */
    public function should_display_navbar_logo() {
        return true;
    }

    /**
     * Compact logo URL with theme fallback.
     *
     * @param int|null $maxwidth
     * @param int $maxheight
     * @return \moodle_url|false
     */
    public function get_compact_logo_url($maxwidth = 300, $maxheight = 300) {
        $url = parent::get_compact_logo_url($maxwidth, $maxheight);
        if (!empty($url)) {
            return $url;
        }
        return $this->image_url('logo_horizontal', 'theme');
    }

    /**
     * Whether guests (or visitors) should see the prominent login button.
     *
     * @return bool
     */
    public function show_ifsc_login_button(): bool {
        return !isloggedin() || isguestuser();
    }

    /**
     * Login URL for the navbar CTA.
     *
     * @return string
     */
    public function get_ifsc_login_url(): string {
        return get_login_url();
    }

    /**
     * Add Open Sans (official IFSC typeface) and the theme favicon.
     *
     * @return string
     */
    public function standard_head_html() {
        $output = parent::standard_head_html();
        $output .= '<link rel="preconnect" href="https://fonts.googleapis.com">';
        $output .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        $output .= '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap">';
        return $output;
    }

    /**
     * Use the IF mark as the site favicon.
     *
     * @return string
     */
    public function favicon() {
        return $this->image_url('favicon', 'theme')->out();
    }
}

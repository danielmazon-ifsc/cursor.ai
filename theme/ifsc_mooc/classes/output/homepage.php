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
 * Front page content exporter.
 *
 * @package   theme_ifsc_mooc
 * @copyright 2026 Instituto Federal de Santa Catarina
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_ifsc_mooc\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the mustache context for the IFSC MOOC landing page.
 *
 * @package   theme_ifsc_mooc
 * @copyright 2026 Instituto Federal de Santa Catarina
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class homepage {

    /**
     * Export landing-page data for templates.
     *
     * @param \renderer_base $output
     * @return array
     */
    public static function export(\renderer_base $output): array {
        $config = get_config('theme_ifsc_mooc');
        $theme = \theme_config::load('ifsc_mooc');

        $highlight = self::setting($config, 'herotitlehighlight', 'herotitlehighlight_default');
        $title = self::setting($config, 'herotitle', 'herotitle_default');
        $titlehtml = str_replace(
            '[[highlight]]',
            '<span class="ifsc-highlight">' . s($highlight) . '</span>',
            s($title)
        );

        $heroimage = $theme->setting_file_url('heroimage', 'heroimage');
        if (empty($heroimage)) {
            $heroimage = $output->image_url('homepage/campus-canoinhas', 'theme')->out();
        }

        $courses = self::export_courses($output);
        $statcourses = 0;
        foreach ($courses as $card) {
            if (!empty($card['id'])) {
                $statcourses++;
            }
        }

        return [
            'herotitlehtml' => $titlehtml,
            'herotext' => self::setting($config, 'herotext', 'herotext_default'),
            'heroimage' => $heroimage,
            'searchurl' => (new \moodle_url('/course/search.php'))->out(false),
            'searchcourses' => get_string('searchcourses', 'theme_ifsc_mooc'),
            'searchplaceholder' => get_string('searchplaceholder', 'theme_ifsc_mooc'),
            'sesskey' => sesskey(),
            'statcourses' => (string) $statcourses,
            'aboutkicker' => get_string('aboutkicker', 'theme_ifsc_mooc'),
            'abouttitle' => self::setting($config, 'abouttitle', 'abouttitle_default'),
            'aboutachieve' => get_string('aboutachieve', 'theme_ifsc_mooc'),
            'abouttext' => self::setting($config, 'abouttext', 'abouttext_default'),
            'ourmission' => get_string('ourmission', 'theme_ifsc_mooc'),
            'missiontext' => self::setting($config, 'missiontext', 'missiontext_default'),
            'ourvision' => get_string('ourvision', 'theme_ifsc_mooc'),
            'visiontext' => self::setting($config, 'visiontext', 'visiontext_default'),
            'readmore' => get_string('readmore', 'theme_ifsc_mooc'),
            'readmoreurl' => 'https://www.ifsc.edu.br/missao-visao-e-valores',
            'aboutimage1' => $output->image_url('homepage/campus-design', 'theme')->out(),
            'aboutimage2' => $output->image_url('homepage/campus-costura', 'theme')->out(),
            'aboutimage3' => $output->image_url('homepage/campus-continente', 'theme')->out(),
            'choosecourses' => get_string('choosecourses', 'theme_ifsc_mooc'),
            'courseshighlight' => get_string('courseshighlight', 'theme_ifsc_mooc'),
            'viewallcourses' => get_string('viewallcourses', 'theme_ifsc_mooc'),
            'allcoursesurl' => (new \moodle_url('/course/index.php'))->out(false),
            'hascourses' => $statcourses > 0,
            'courses' => $courses,
            'nocoursesyet' => get_string('nocoursesyet', 'theme_ifsc_mooc'),
            'subscribenewsletter' => get_string('subscribenewsletter', 'theme_ifsc_mooc'),
            'subscribenow' => get_string('subscribenow', 'theme_ifsc_mooc'),
            'emailplaceholder' => get_string('emailplaceholder', 'theme_ifsc_mooc'),
            'newsletterurl' => !empty($config->newsletterurl) ? $config->newsletterurl : 'https://www.ifsc.edu.br/',
            'logourl' => $output->image_url('logo_horizontal', 'theme')->out(),
            'logoverticalurl' => $output->image_url('logo_vertical', 'theme')->out(),
            'footerabout' => get_string('footerabout', 'theme_ifsc_mooc'),
            'footerlinks' => get_string('footerlinks', 'theme_ifsc_mooc'),
            'footerlegal' => get_string('footerlegal', 'theme_ifsc_mooc'),
            'home' => get_string('home', 'theme_ifsc_mooc'),
            'homeurl' => (new \moodle_url('/'))->out(false),
            'aboutifsc' => get_string('aboutifsc', 'theme_ifsc_mooc'),
            'aboutifscurl' => 'https://www.ifsc.edu.br/',
            'contact' => get_string('contact', 'theme_ifsc_mooc'),
            'contacturl' => 'https://www.ifsc.edu.br/fale-conosco',
            'accessibility' => get_string('accessibility', 'theme_ifsc_mooc'),
            'accessibilityurl' => 'https://www.ifsc.edu.br/acesso-a-informacao',
            'transparency' => get_string('transparency', 'theme_ifsc_mooc'),
            'transparencyurl' => 'https://www.ifsc.edu.br/transparencia-e-prestacao-de-contas',
            'ifscportal' => get_string('ifscportal', 'theme_ifsc_mooc'),
            'copyright' => get_string('copyright', 'theme_ifsc_mooc'),
            'backtotop' => get_string('backtotop', 'theme_ifsc_mooc'),
            'opencourses' => get_string('opencourses', 'theme_ifsc_mooc'),
            'certified' => get_string('certified', 'theme_ifsc_mooc'),
            'alwaysfree' => get_string('alwaysfree', 'theme_ifsc_mooc'),
            'herostats_courses' => get_string('herostats_courses', 'theme_ifsc_mooc'),
            'herostats_certified' => get_string('herostats_certified', 'theme_ifsc_mooc'),
            'year' => userdate(time(), '%Y'),
            'wwwroot' => $GLOBALS['CFG']->wwwroot,
        ];
    }

    /**
     * Read a theme setting or fall back to the language default.
     *
     * @param mixed $config
     * @param string $name
     * @param string $defaultkey
     * @return string
     */
    private static function setting($config, string $name, string $defaultkey): string {
        if (!empty($config->{$name})) {
            return $config->{$name};
        }
        return get_string($defaultkey, 'theme_ifsc_mooc');
    }

    /**
     * Visible site courses as landing-page cards (max 6).
     *
     * @param \renderer_base $output
     * @return array
     */
    private static function export_courses(\renderer_base $output): array {
        $courses = [];

        try {
            $coursecat = \core_course_category::get(0);
            $listed = $coursecat->get_courses([
                'recursive' => true,
                'limit' => 6,
            ]);
            foreach ($listed as $course) {
                if ($course->id == SITEID) {
                    continue;
                }
                $courses[] = course_card::export($course, $output);
                if (count($courses) >= 6) {
                    break;
                }
            }
        } catch (\Exception $e) {
            $courses = [];
        }

        if (empty($courses)) {
            return self::get_featured_placeholders($output);
        }

        return $courses;
    }

    /**
     * Featured placeholders when the catalogue is still empty.
     *
     * @param \renderer_base $output
     * @return array
     */
    private static function get_featured_placeholders(\renderer_base $output): array {
        $catalog = (new \moodle_url('/course/index.php'))->out(false);
        $items = [
            ['key' => 'featureead', 'cat' => 'featureeadcat', 'img' => 'homepage/campus-design'],
            ['key' => 'featurequalificacao', 'cat' => 'featurequalificacaocat', 'img' => 'homepage/campus-costura'],
            ['key' => 'featuretecnologia', 'cat' => 'featuretecnologiacat', 'img' => 'homepage/campus-saojose'],
            ['key' => 'featuregestao', 'cat' => 'featuregestaocat', 'img' => 'homepage/campus-reitoria'],
            ['key' => 'featureinclusao', 'cat' => 'featureinclusaocat', 'img' => 'homepage/campus-continente'],
            ['key' => 'featureformacao', 'cat' => 'featureformacaocat', 'img' => 'homepage/campus-canoinhas'],
        ];

        $cards = [];
        foreach ($items as $item) {
            $cards[] = [
                'title' => get_string($item['key'], 'theme_ifsc_mooc'),
                'url' => $catalog,
                'image' => $output->image_url($item['img'], 'theme')->out(false),
                'category' => get_string($item['cat'], 'theme_ifsc_mooc'),
                'price' => get_string('freecourse', 'theme_ifsc_mooc'),
                'hascustomfields' => false,
                'customfields' => [],
            ];
        }

        return $cards;
    }
}

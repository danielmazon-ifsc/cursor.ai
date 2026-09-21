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
 * Theme settings for IFSC MOOC.
 *
 * @package   theme_ifsc_mooc
 * @copyright 2026 Instituto Federal de Santa Catarina
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs('themesettingifsc_mooc', get_string('configtitle', 'theme_ifsc_mooc'));

    $page = new admin_settingpage('theme_ifsc_mooc_general', get_string('generalsettings', 'theme_ifsc_mooc'));

    $name = 'theme_ifsc_mooc/brandcolor';
    $title = get_string('brandcolor', 'theme_ifsc_mooc');
    $description = get_string('brandcolor_desc', 'theme_ifsc_mooc');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, '#2f9e41');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_ifsc_mooc/backgroundimage';
    $title = get_string('backgroundimage', 'theme_boost');
    $description = get_string('backgroundimage_desc', 'theme_boost');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'backgroundimage');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_ifsc_mooc/loginbackgroundimage';
    $title = get_string('loginbackgroundimage', 'theme_boost');
    $description = get_string('loginbackgroundimage_desc', 'theme_boost');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'loginbackgroundimage');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);

    $page = new admin_settingpage('theme_ifsc_mooc_frontpage', get_string('frontpagesettings', 'theme_ifsc_mooc'));

    $page->add(new admin_setting_heading('theme_ifsc_mooc_heroheading',
        get_string('herosettings', 'theme_ifsc_mooc'), ''));

    $setting = new admin_setting_configtext('theme_ifsc_mooc/herotitle',
        get_string('herotitle', 'theme_ifsc_mooc'),
        get_string('herotitle_desc', 'theme_ifsc_mooc'),
        get_string('herotitle_default', 'theme_ifsc_mooc'), PARAM_TEXT);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $setting = new admin_setting_configtext('theme_ifsc_mooc/herotitlehighlight',
        get_string('herotitlehighlight', 'theme_ifsc_mooc'),
        get_string('herotitlehighlight_desc', 'theme_ifsc_mooc'),
        get_string('herotitlehighlight_default', 'theme_ifsc_mooc'), PARAM_TEXT);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $setting = new admin_setting_configtextarea('theme_ifsc_mooc/herotext',
        get_string('herotext', 'theme_ifsc_mooc'),
        get_string('herotext_desc', 'theme_ifsc_mooc'),
        get_string('herotext_default', 'theme_ifsc_mooc'), PARAM_TEXT);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $setting = new admin_setting_configstoredfile('theme_ifsc_mooc/heroimage',
        get_string('heroimage', 'theme_ifsc_mooc'),
        get_string('heroimage_desc', 'theme_ifsc_mooc'), 'heroimage');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $page->add(new admin_setting_heading('theme_ifsc_mooc_aboutheading',
        get_string('aboutsettings', 'theme_ifsc_mooc'), ''));

    $setting = new admin_setting_configtext('theme_ifsc_mooc/abouttitle',
        get_string('abouttitle', 'theme_ifsc_mooc'),
        '', get_string('abouttitle_default', 'theme_ifsc_mooc'), PARAM_TEXT);
    $page->add($setting);

    $setting = new admin_setting_configtextarea('theme_ifsc_mooc/abouttext',
        get_string('abouttext', 'theme_ifsc_mooc'),
        '', get_string('abouttext_default', 'theme_ifsc_mooc'), PARAM_TEXT);
    $page->add($setting);

    $setting = new admin_setting_configtextarea('theme_ifsc_mooc/missiontext',
        get_string('missiontext', 'theme_ifsc_mooc'),
        '', get_string('missiontext_default', 'theme_ifsc_mooc'), PARAM_TEXT);
    $page->add($setting);

    $setting = new admin_setting_configtextarea('theme_ifsc_mooc/visiontext',
        get_string('visiontext', 'theme_ifsc_mooc'),
        '', get_string('visiontext_default', 'theme_ifsc_mooc'), PARAM_TEXT);
    $page->add($setting);

    $setting = new admin_setting_configtext('theme_ifsc_mooc/newsletterurl',
        get_string('newsletterurl', 'theme_ifsc_mooc'),
        get_string('newsletterurl_desc', 'theme_ifsc_mooc'),
        'https://www.ifsc.edu.br/', PARAM_URL);
    $page->add($setting);

    $settings->add($page);

    $page = new admin_settingpage('theme_ifsc_mooc_advanced', get_string('advancedsettings', 'theme_boost'));

    $setting = new admin_setting_scsscode('theme_ifsc_mooc/scsspre',
        get_string('rawscsspre', 'theme_boost'), get_string('rawscsspre_desc', 'theme_boost'), '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $setting = new admin_setting_scsscode('theme_ifsc_mooc/scss',
        get_string('rawscss', 'theme_boost'), get_string('rawscss_desc', 'theme_boost'), '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
}

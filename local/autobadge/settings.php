<?php

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_autobadge', get_string('pluginname', 'local_autobadge'));
    $ADMIN->add('localplugins', $settings);
    $settings->add(new admin_setting_configtextarea('local_autobadge/awardsubject', get_string('awardsubject', 'local_autobadge'), get_string('awardsubject_desc', 'local_autobadge'), get_string('awardsubject_default', 'local_autobadge')));
    $settings->add(new admin_setting_configtextarea('local_autobadge/awardmessage', get_string('awardmessage', 'local_autobadge'), get_string('awardmessage_desc', 'local_autobadge'), get_string('awardmessage_default', 'local_autobadge')));
}
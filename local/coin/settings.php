<?php

/**
 * This plugin manage coins in Moodle platform
 * 
 * @package    local
 * @subpackage coin
 * @copyright  2016 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die;

require_once('settingslib.php');

if ($hassiteconfig) {


    $ADMIN->add('root', new admin_category('coin', get_string('pluginname', 'local_coin')));
    $settings = new admin_settingpage('local_coin', get_string('coinconfig', 'local_coin'));
    $ADMIN->add('coin', $settings);

    $settings->add(new admin_setting_configtext('local_coin/coindenomination', get_string('coindenomination', 'local_coin'), get_string('coindenomination_desc', 'local_coin'), get_string('coindenomination_default', 'local_coin')));
    $settings->add(new admin_setting_configtext('local_coin/coindenomination_plural', get_string('coindenomination_plural', 'local_coin'), get_string('coindenomination_plural_desc', 'local_coin'), get_string('coindenomination_plural_default', 'local_coin')));
    $settings->add(new admin_setting_configjsonarray('local_coin/triggersjson', get_string('coinsawarding', 'local_coin'), get_string('coinsawarding_desc', 'local_coin'), get_string('coinsawarding_default', 'local_coin')));
    $settings->add(new admin_setting_configjsonarray('local_coin/cancellersjson', get_string('coinscancelling', 'local_coin'), get_string('coinscancelling_desc', 'local_coin'), get_string('coinscancelling_default', 'local_coin')));
    $settings->add(new admin_setting_configduration('local_coin/expirationperiod', get_string('expirationperiod', 'local_coin'), get_string('expirationperiod_desc', 'local_coin'), 26 * 7 * 24 * 60 * 60));
    $settings->add(new admin_setting_configtextarea('local_coin/awardsubject', get_string('awardsubject', 'local_coin'), get_string('awardsubject_desc', 'local_coin'), get_string('awardsubject_default', 'local_coin')));
    $settings->add(new admin_setting_configtextarea('local_coin/awardmessage', get_string('awardmessage', 'local_coin'), get_string('awardmessage_desc', 'local_coin'), get_string('awardmessage_default', 'local_coin')));
    $settings->add(new admin_setting_configtextarea('local_coin/cancelsubject', get_string('cancelsubject', 'local_coin'), get_string('cancelsubject_desc', 'local_coin'), get_string('cancelsubject_default', 'local_coin')));
    $settings->add(new admin_setting_configtextarea('local_coin/cancelmessage', get_string('cancelmessage', 'local_coin'), get_string('cancelmessage_desc', 'local_coin'), get_string('cancelmessage_default', 'local_coin')));
    $settings->add(new admin_setting_configtextarea(
        'local_coin/cancelmessage_expired',
        get_string('cancelmessage_expired_setting', 'local_coin'),
        get_string('cancelmessage_expired_setting_desc', 'local_coin'),
        get_string('cancelmessage_expired', 'local_coin')
    ));

    // Add link to coins ledger
    $coinsledger = new admin_externalpage('coinsledger', get_string('coinsledger', 'local_coin'), '/local/coin/report/ledger/index.php');
    $ADMIN->add('coin', $coinsledger);

    $ADMIN->add('coin', new admin_externalpage(
        'local_coin_upgrade_checks',
        get_string('upgradecheckspageheading', 'local_coin'),
        new moodle_url('/local/coin/upgrade_checks.php'),
        'moodle/site:config'
    ));
}
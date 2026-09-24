<?php

/**
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot . '/mod/socialforum/lib.php');

    $settings->add(new admin_setting_configselect('socialforum_displaymode', get_string('displaymode', 'mod_socialforum'), get_string('configdisplaymode', 'mod_socialforum'), SOCIALFORUM_MODE_NESTED, socialforum_get_layout_modes()));

    $settings->add(new admin_setting_configcheckbox('socialforum_replytouser', get_string('replytouser', 'mod_socialforum'), get_string('configreplytouser', 'mod_socialforum'), 0));

    // Less non-HTML characters than this is short
    $settings->add(new admin_setting_configtext('socialforum_shortpost', get_string('shortpost', 'mod_socialforum'), get_string('configshortpost', 'mod_socialforum'), 300, PARAM_INT));

    // More non-HTML characters than this is long
    $settings->add(new admin_setting_configtext('socialforum_longpost', get_string('longpost', 'mod_socialforum'), get_string('configlongpost', 'mod_socialforum'), 600, PARAM_INT));

    // Number of discussions on a page
    $settings->add(new admin_setting_configtext('socialforum_manydiscussions', get_string('manydiscussions', 'mod_socialforum'), get_string('configmanydiscussions', 'mod_socialforum'), 100, PARAM_INT));

    if (isset($CFG->maxbytes)) {
        $maxbytes = 0;
        if (isset($CFG->socialforum_maxbytes)) {
            $maxbytes = $CFG->socialforum_maxbytes;
        }
        $settings->add(new admin_setting_configselect('socialforum_maxbytes', get_string('maxattachmentsize', 'mod_socialforum'), get_string('configmaxbytes', 'mod_socialforum'), 512000, get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes)));
    }

    // Default number of attachments allowed per post in all forums
    $settings->add(new admin_setting_configtext('socialforum_maxattachments', get_string('maxattachments', 'mod_socialforum'), get_string('configmaxattachments', 'mod_socialforum'), 9, PARAM_INT));

    // Default Read Tracking setting.
    $options = array();
    $options[SOCIALFORUM_TRACKING_OPTIONAL] = get_string('trackingoptional', 'mod_socialforum');
    $options[SOCIALFORUM_TRACKING_OFF] = get_string('trackingoff', 'mod_socialforum');
    $options[SOCIALFORUM_TRACKING_FORCED] = get_string('trackingon', 'mod_socialforum');
    $settings->add(new admin_setting_configselect('socialforum_trackingtype', get_string('trackingtype', 'mod_socialforum'), get_string('configtrackingtype', 'mod_socialforum'), SOCIALFORUM_TRACKING_OPTIONAL, $options));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('socialforum_trackreadposts', get_string('tracksocialforum', 'mod_socialforum'), get_string('configtrackreadposts', 'mod_socialforum'), 1));

    // Default whether user needs to mark a post as read.
    $settings->add(new admin_setting_configcheckbox('socialforum_allowforcedreadtracking', get_string('forcedreadtracking', 'mod_socialforum'), get_string('forcedreadtracking_desc', 'mod_socialforum'), 1));

    // Default number of days that a post is considered old
    $settings->add(new admin_setting_configtext('socialforum_oldpostdays', get_string('oldpostdays', 'mod_socialforum'), get_string('configoldpostdays', 'mod_socialforum'), 14, PARAM_INT));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('socialforum_usermarksread', get_string('usermarksread', 'mod_socialforum'), get_string('configusermarksread', 'mod_socialforum'), 0));

    $options = array();
    for ($i = 0; $i < 24; $i++) {
        $options[$i] = sprintf("%02d", $i);
    }
    // Default time (hour) to execute 'clean_read_records' cron
    $settings->add(new admin_setting_configselect('socialforum_cleanreadtime', get_string('cleanreadtime', 'mod_socialforum'), get_string('configcleanreadtime', 'mod_socialforum'), 2, $options));

    // Default time (hour) to send digest email
    $settings->add(new admin_setting_configselect('digestmailtime', get_string('digestmailtime', 'mod_socialforum'), get_string('configdigestmailtime', 'mod_socialforum'), 17, $options));

    if (empty($CFG->enablerssfeeds)) {
        $options = array(0 => get_string('rssglobaldisabled', 'admin'));
        $str = get_string('configenablerssfeeds', 'mod_socialforum') . '<br />' . get_string('configenablerssfeedsdisabled2', 'admin');
    } else {
        $options = array(0 => get_string('no'), 1 => get_string('yes'));
        $str = get_string('configenablerssfeeds', 'mod_socialforum');
    }
    $settings->add(new admin_setting_configselect('socialforum_enablerssfeeds', get_string('enablerssfeeds', 'admin'), $str, 0, $options));

    if (!empty($CFG->enablerssfeeds)) {
        $options = array(
            0 => get_string('none'),
            1 => get_string('discussions', 'mod_socialforum'),
            2 => get_string('posts', 'mod_socialforum')
        );
        $settings->add(new admin_setting_configselect('socialforum_rsstype', get_string('rsstypedefault', 'mod_socialforum'), get_string('configrsstypedefault', 'mod_socialforum'), 0, $options));

        $options = array(
            0 => '0',
            1 => '1',
            2 => '2',
            3 => '3',
            4 => '4',
            5 => '5',
            10 => '10',
            15 => '15',
            20 => '20',
            25 => '25',
            30 => '30',
            40 => '40',
            50 => '50'
        );
        $settings->add(new admin_setting_configselect('socialforum_rssarticles', get_string('rssarticles', 'mod_socialforum'), get_string('configrssarticlesdefault', 'mod_socialforum'), 0, $options));
    }

    $settings->add(new admin_setting_configcheckbox('socialforum_enabletimedposts', get_string('timedposts', 'mod_socialforum'), get_string('configenabletimedposts', 'mod_socialforum'), 0));
}


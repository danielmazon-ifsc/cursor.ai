<?php

/**
 * Video module admin settings and defaults
 *
 * @package   mod_video
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once("$CFG->libdir/resourcelib.php");

    $displayoptions = resourcelib_get_displayoptions(array(RESOURCELIB_DISPLAY_OPEN, RESOURCELIB_DISPLAY_POPUP));
    $defaultdisplayoptions = array(RESOURCELIB_DISPLAY_OPEN);

    //--- general settings -----------------------------------------------------------------------------------
    $settings->add(new admin_setting_configmultiselect('video/displayoptions', get_string('displayoptions', 'video'), get_string('configdisplayoptions', 'video'), $defaultdisplayoptions, $displayoptions));

    //--- modedit defaults -----------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('videomodeditdefaults', get_string('modeditdefaults', 'admin'), get_string('condifmodeditdefaults', 'admin')));

    $settings->add(new admin_setting_configcheckbox('video/printheading', get_string('printheading', 'video'), get_string('printheadingexplain', 'video'), 1));
    $settings->add(new admin_setting_configcheckbox('video/printintro', get_string('printintro', 'video'), get_string('printintroexplain', 'video'), 0));
    $settings->add(new admin_setting_configcheckbox('video/completeimmediately', get_string('completeimmediately', 'video'), get_string('completeimmediately_desc', 'video'), 0));
    $settings->add(new admin_setting_configtext('video/secondstocomplete', get_string('secondstocomplete', 'video'), get_string('secondstocomplete_desc', 'video'), 0, PARAM_INT, 2));
    $settings->add(new admin_setting_configselect('video/display', get_string('displayselect', 'video'), get_string('displayselectexplain', 'video'), RESOURCELIB_DISPLAY_OPEN, $displayoptions));
    $settings->add(new admin_setting_configtext('video/popupwidth', get_string('popupwidth', 'video'), get_string('popupwidthexplain', 'video'), 620, PARAM_INT, 7));
    $settings->add(new admin_setting_configtext('video/popupheight', get_string('popupheight', 'video'), get_string('popupheightexplain', 'video'), 450, PARAM_INT, 7));
    $settings->add(new admin_setting_configtextarea('video/vimeoformat', get_string('vimeoformat', 'video'), get_string('vimeoformatexplain', 'video'), get_string('vimeoformatdefault', 'video'), PARAM_RAW, '50', '10'));
    $settings->add(new admin_setting_configtextarea('video/youtubeformat', get_string('youtubeformat', 'video'), get_string('youtubeformatexplain', 'video'), get_string('youtubeformatdefault', 'video'), PARAM_RAW, '50', '10'));
    $settings->add(new admin_setting_configtextarea('video/embededformat', get_string('embededformat', 'video'), get_string('embededformatexplain', 'video'), get_string('embededformatdefault', 'video'), PARAM_RAW, '50', '10'));

    // Vimeo account settings
    $settings->add(new admin_setting_heading('vimeoaccountinfo', get_string('vimeoaccountinfo', 'video'), get_string('vimeoaccountinfodesc', 'video')));
    $settings->add(new admin_setting_configcheckbox('video/uploadtovimeo', get_string('uploadtovimeo', 'video'), get_string('uploadtovimeodesc', 'video'), 0));
    $settings->add(new admin_setting_configtext('video/vimeouserid', get_string('vimeouserid', 'video'), get_string('vimeouseriddesc', 'video'), get_string('vimeouseriddefault', 'video'), PARAM_TEXT, 50));
    $settings->add(new admin_setting_configtext('video/vimeoclientid', get_string('vimeoclientid', 'video'), get_string('vimeoclientiddesc', 'video'), get_string('vimeoclientiddefault', 'video'), PARAM_TEXT, 50));
    $settings->add(new admin_setting_configtextarea('video/vimeoclientsecret', get_string('vimeoclientsecret', 'video'), get_string('vimeoclientsecretdesc', 'video'), get_string('vimeoclientsecretdefault', 'video'), PARAM_TEXT, '80', '2'));
    $settings->add(new admin_setting_configtext('video/vimeoclienttoken', get_string('vimeoclienttoken', 'video'), get_string('vimeoclienttokendesc', 'video'), get_string('vimeoclienttokendefault', 'video'), PARAM_TEXT, 50));
}

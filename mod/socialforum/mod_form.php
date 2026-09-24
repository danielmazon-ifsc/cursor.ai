<?php

/**
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot . '/course/moodleform_mod.php');

class mod_socialforum_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $COURSE, $DB;

        $mform = & $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('socialforumname', 'mod_socialforum'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('socialforumintro', 'mod_socialforum'));

        $socialforumtypes = socialforum_get_socialforum_types();
        core_collator::asort($socialforumtypes, core_collator::SORT_STRING);
        $mform->addElement('select', 'type', get_string('socialforumtype', 'mod_socialforum'), $socialforumtypes);
        $mform->addHelpButton('type', 'socialforumtype', 'socialforum');
        $mform->setDefault('type', 'general');

        // Attachments and word count.
        $mform->addElement('header', 'attachmentswordcounthdr', get_string('attachmentswordcount', 'mod_socialforum'));

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes, 0, $CFG->socialforum_maxbytes);
        $choices[1] = get_string('uploadnotallowed');
        $mform->addElement('select', 'maxbytes', get_string('maxattachmentsize', 'mod_socialforum'), $choices);
        $mform->addHelpButton('maxbytes', 'maxattachmentsize', 'socialforum');
        $mform->setDefault('maxbytes', $CFG->socialforum_maxbytes);

        $choices = array(
            0 => 0,
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            9 => 9,
            10 => 10,
            20 => 20,
            50 => 50,
            100 => 100
        );
        $mform->addElement('select', 'maxattachments', get_string('maxattachments', 'mod_socialforum'), $choices);
        $mform->addHelpButton('maxattachments', 'maxattachments', 'socialforum');
        $mform->setDefault('maxattachments', $CFG->socialforum_maxattachments);

        $mform->addElement('selectyesno', 'displaywordcount', get_string('displaywordcount', 'mod_socialforum'));
        $mform->addHelpButton('displaywordcount', 'displaywordcount', 'socialforum');
        $mform->setDefault('displaywordcount', 0);

        // Subscription and tracking.
        $mform->addElement('header', 'subscriptionandtrackinghdr', get_string('subscriptionandtracking', 'mod_socialforum'));

        $options = array();
        $options[SOCIALFORUM_CHOOSESUBSCRIBE] = get_string('subscriptionoptional', 'mod_socialforum');
        $options[SOCIALFORUM_FORCESUBSCRIBE] = get_string('subscriptionforced', 'mod_socialforum');
        $options[SOCIALFORUM_INITIALSUBSCRIBE] = get_string('subscriptionauto', 'mod_socialforum');
        $options[SOCIALFORUM_DISALLOWSUBSCRIBE] = get_string('subscriptiondisabled', 'mod_socialforum');
        $mform->addElement('select', 'forcesubscribe', get_string('subscriptionmode', 'mod_socialforum'), $options);
        $mform->addHelpButton('forcesubscribe', 'subscriptionmode', 'socialforum');

        $options = array();
        $options[SOCIALFORUM_TRACKING_OPTIONAL] = get_string('trackingoptional', 'mod_socialforum');
        $options[SOCIALFORUM_TRACKING_OFF] = get_string('trackingoff', 'mod_socialforum');
        if ($CFG->socialforum_allowforcedreadtracking) {
            $options[SOCIALFORUM_TRACKING_FORCED] = get_string('trackingon', 'mod_socialforum');
        }
        $mform->addElement('select', 'trackingtype', get_string('trackingtype', 'mod_socialforum'), $options);
        $mform->addHelpButton('trackingtype', 'trackingtype', 'socialforum');
        $default = $CFG->socialforum_trackingtype;
        if ((!$CFG->socialforum_allowforcedreadtracking) && ($default == SOCIALFORUM_TRACKING_FORCED)) {
            $default = SOCIALFORUM_TRACKING_OPTIONAL;
        }
        $mform->setDefault('trackingtype', $default);

        if ($CFG->enablerssfeeds && isset($CFG->socialforum_enablerssfeeds) && $CFG->socialforum_enablerssfeeds) {
//-------------------------------------------------------------------------------
            $mform->addElement('header', 'rssheader', get_string('rss'));
            $choices = array();
            $choices[0] = get_string('none');
            $choices[1] = get_string('discussions', 'mod_socialforum');
            $choices[2] = get_string('posts', 'mod_socialforum');
            $mform->addElement('select', 'rsstype', get_string('rsstype'), $choices);
            $mform->addHelpButton('rsstype', 'rsstype', 'socialforum');
            if (isset($CFG->socialforum_rsstype)) {
                $mform->setDefault('rsstype', $CFG->socialforum_rsstype);
            }

            $choices = array();
            $choices[0] = '0';
            $choices[1] = '1';
            $choices[2] = '2';
            $choices[3] = '3';
            $choices[4] = '4';
            $choices[5] = '5';
            $choices[10] = '10';
            $choices[15] = '15';
            $choices[20] = '20';
            $choices[25] = '25';
            $choices[30] = '30';
            $choices[40] = '40';
            $choices[50] = '50';
            $mform->addElement('select', 'rssarticles', get_string('rssarticles'), $choices);
            $mform->addHelpButton('rssarticles', 'rssarticles', 'socialforum');
            $mform->disabledIf('rssarticles', 'rsstype', 'eq', '0');
            if (isset($CFG->socialforum_rssarticles)) {
                $mform->setDefault('rssarticles', $CFG->socialforum_rssarticles);
            }
        }

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'blockafterheader', get_string('blockafter', 'mod_socialforum'));
        $options = array();
        $options[0] = get_string('blockperioddisabled', 'mod_socialforum');
        $options[60 * 60 * 24] = '1 ' . get_string('day');
        $options[60 * 60 * 24 * 2] = '2 ' . get_string('days');
        $options[60 * 60 * 24 * 3] = '3 ' . get_string('days');
        $options[60 * 60 * 24 * 4] = '4 ' . get_string('days');
        $options[60 * 60 * 24 * 5] = '5 ' . get_string('days');
        $options[60 * 60 * 24 * 6] = '6 ' . get_string('days');
        $options[60 * 60 * 24 * 7] = '1 ' . get_string('week');
        $mform->addElement('select', 'blockperiod', get_string('blockperiod', 'mod_socialforum'), $options);
        $mform->addHelpButton('blockperiod', 'blockperiod', 'socialforum');

        $mform->addElement('text', 'blockafter', get_string('blockafter', 'mod_socialforum'));
        $mform->setType('blockafter', PARAM_INT);
        $mform->setDefault('blockafter', '0');
        $mform->addRule('blockafter', null, 'numeric', null, 'client');
        $mform->addHelpButton('blockafter', 'blockafter', 'socialforum');
        $mform->disabledIf('blockafter', 'blockperiod', 'eq', 0);

        $mform->addElement('text', 'warnafter', get_string('warnafter', 'mod_socialforum'));
        $mform->setType('warnafter', PARAM_INT);
        $mform->setDefault('warnafter', '0');
        $mform->addRule('warnafter', null, 'numeric', null, 'client');
        $mform->addHelpButton('warnafter', 'warnafter', 'socialforum');
        $mform->disabledIf('warnafter', 'blockperiod', 'eq', 0);

        $coursecontext = context_course::instance($COURSE->id);
        plagiarism_get_form_elements_module($mform, $coursecontext, 'mod_socialforum');

//-------------------------------------------------------------------------------

        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
// buttons
        $this->add_action_buttons();
    }

    function definition_after_data() {
        parent::definition_after_data();
        $mform = & $this->_form;
        $type = & $mform->getElement('type');
        $typevalue = $mform->getElementValue('type');

        //we don't want to have these appear as possible selections in the form but
        //we want the form to display them if they are set.
        if ($typevalue[0] == 'news') {
            $type->addOption(get_string('namenews', 'mod_socialforum'), 'news');
            $mform->addHelpButton('type', 'namenews', 'mod_socialforum');
            $type->freeze();
            $type->setPersistantFreeze(true);
        }
        if ($typevalue[0] == 'social') {
            $type->addOption(get_string('namesocial', 'mod_socialforum'), 'social');
            $type->freeze();
            $type->setPersistantFreeze(true);
        }
    }

    function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);

        // Set up the completion checkboxes which aren't part of standard data.
        // We also make the default value (if you turn on the checkbox) for those
        // numbers to be 1, this will not apply unless checkbox is ticked.
        $default_values['completiondiscussionsenabled'] = !empty($default_values['completiondiscussions']) ? 1 : 0;
        if (empty($default_values['completiondiscussions'])) {
            $default_values['completiondiscussions'] = 1;
        }
        $default_values['completionrepliesenabled'] = !empty($default_values['completionreplies']) ? 1 : 0;
        if (empty($default_values['completionreplies'])) {
            $default_values['completionreplies'] = 1;
        }
        $default_values['completionpostsenabled'] = !empty($default_values['completionposts']) ? 1 : 0;
        if (empty($default_values['completionposts'])) {
            $default_values['completionposts'] = 1;
        }
    }

    function add_completion_rules() {
        $mform = & $this->_form;

        $group = array();
        $group[] = & $mform->createElement('checkbox', 'completionpostsenabled', '', get_string('completionposts', 'mod_socialforum'));
        $group[] = & $mform->createElement('text', 'completionposts', '', array('size' => 3));
        $mform->setType('completionposts', PARAM_INT);
        $mform->addGroup($group, 'completionpostsgroup', get_string('completionpostsgroup', 'mod_socialforum'), array(' '), false);
        $mform->disabledIf('completionposts', 'completionpostsenabled', 'notchecked');

        $group = array();
        $group[] = & $mform->createElement('checkbox', 'completiondiscussionsenabled', '', get_string('completiondiscussions', 'mod_socialforum'));
        $group[] = & $mform->createElement('text', 'completiondiscussions', '', array('size' => 3));
        $mform->setType('completiondiscussions', PARAM_INT);
        $mform->addGroup($group, 'completiondiscussionsgroup', get_string('completiondiscussionsgroup', 'mod_socialforum'), array(' '), false);
        $mform->disabledIf('completiondiscussions', 'completiondiscussionsenabled', 'notchecked');

        $group = array();
        $group[] = & $mform->createElement('checkbox', 'completionrepliesenabled', '', get_string('completionreplies', 'mod_socialforum'));
        $group[] = & $mform->createElement('text', 'completionreplies', '', array('size' => 3));
        $mform->setType('completionreplies', PARAM_INT);
        $mform->addGroup($group, 'completionrepliesgroup', get_string('completionrepliesgroup', 'mod_socialforum'), array(' '), false);
        $mform->disabledIf('completionreplies', 'completionrepliesenabled', 'notchecked');

        return array('completiondiscussionsgroup', 'completionrepliesgroup', 'completionpostsgroup');
    }

    function completion_rule_enabled($data) {
        return (!empty($data['completiondiscussionsenabled']) && $data['completiondiscussions'] != 0) ||
                (!empty($data['completionrepliesenabled']) && $data['completionreplies'] != 0) ||
                (!empty($data['completionpostsenabled']) && $data['completionposts'] != 0);
    }

    function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return false;
        }
        // Turn off completion settings if the checkboxes aren't ticked
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completiondiscussionsenabled) || !$autocompletion) {
                $data->completiondiscussions = 0;
            }
            if (empty($data->completionrepliesenabled) || !$autocompletion) {
                $data->completionreplies = 0;
            }
            if (empty($data->completionpostsenabled) || !$autocompletion) {
                $data->completionposts = 0;
            }
        }
        return $data;
    }

}

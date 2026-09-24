<?php

/**
 * Video configuration form
 *
 * @package   mod_video
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/video/locallib.php');
require_once($CFG->libdir . '/filelib.php');

class mod_video_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $DB, $COURSE;

        $mform = $this->_form;

        $config = get_config('video');

        //-------------------------------------------------------

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), array('size' => '48'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        // Video content 
        $mform->addElement('header', 'contentsection', get_string('contentheader', 'video'));
        $videotype_options = array();
        $videotype_options[FORMAT_STORED] = get_string('stored', 'video');
        $videotype_options[FORMAT_VIMEO] = get_string('vimeo', 'video');
        $videotype_options[FORMAT_YOUTUBE] = get_string('youtube', 'video');
        $videotype_options[FORMAT_EMBEDED] = get_string('embeded', 'video');
        $mform->addElement('select', 'contentformat', get_string('type', 'video'), $videotype_options);
        $mform->addRule('contentformat', null, 'required', null, 'client');
        $stored_filemanager_options = array();
        $stored_filemanager_options['accepted_types'] = '*';
        $stored_filemanager_options['maxbytes'] = 104857600; // 100 MB
        $stored_filemanager_options['maxfiles'] = 1;
        $stored_filemanager_options['mainfile'] = true;
        $mform->addElement('filemanager', 'storedvideo', get_string('storedvideo', 'video'), null, $stored_filemanager_options);
        $mform->addElement('text', 'vimeoid', get_string('vimeoid', 'video'), array('size' => '20'));
        $mform->setType('vimeoid', PARAM_NUMBER);
        $mform->addElement('text', 'youtubeid', get_string('youtubeid', 'video'), array('size' => '20'));
        $mform->setType('youtubeid', PARAM_ALPHANUMEXT);
        $mform->addElement('text', 'embededurl', get_string('embededurl', 'video'), array('size' => '60'));
        $mform->setType('embededurl', PARAM_URL);
        $cardthumbnailoptions = array(
            'accepted_types' => array('image'),
            'maxbytes' => 0,
            'maxfiles' => 1,
        );
        $mform->addElement('filemanager', 'cardthumbnail', get_string('cardthumbnail', 'video'), null, $cardthumbnailoptions);
        $mform->addHelpButton('cardthumbnail', 'cardthumbnail', 'video');
        $mform->hideIf('cardthumbnail', 'contentformat', 'neq', FORMAT_EMBEDED);

        $captionoptions = [
            'accepted_types' => ['.vtt'],
            'maxbytes' => 0,
            'maxfiles' => 1,
        ];
        $mform->addElement('filemanager', 'caption', get_string('caption', 'video'), null, $captionoptions);
        $mform->addHelpButton('caption', 'caption', 'video');

        // Download content 
        $mform->addElement('header', 'downloadsection', get_string('downloadheader', 'video'));
        $filemanager_options = array();
        $filemanager_options['accepted_types'] = '*';
        $filemanager_options['maxbytes'] = 0;
        $filemanager_options['maxfiles'] = -1;
        $filemanager_options['mainfile'] = true;
        $mform->addElement('filemanager', 'files', get_string('download', 'video'), null, $filemanager_options);


        $mform->addElement('header', 'commentssection', get_string('commentsheader', 'video'));
        $forums = array('' => null);
        $modinfo = get_fast_modinfo($COURSE->id);
        foreach ($modinfo->cms as $cm) {
            if ($cm->visible && strpos($cm->modname, 'socialforum') !== false) {
                $forums[$cm->instance] = $cm->name;
            }
        }
        $mform->addElement('select', 'socialforumid', get_string('commentsforum', 'video'), $forums);
        $mform->addHelpButton('socialforumid', 'commentsforum', 'video');

        //-------------------------------------------------------

        $mform->addElement('header', 'optionssection', get_string('appearance'));

        $mform->addElement('advcheckbox', 'printheading', get_string('printheading', 'page'));
        $mform->setDefault('printheading', $config->printheading);
        $mform->addElement('advcheckbox', 'printintro', get_string('printintro', 'page'));
        $mform->setDefault('printintro', $config->printintro);

        if ($this->current->instance) {
            $options = resourcelib_get_displayoptions(explode(',', $config->displayoptions), $this->current->display);
        } else {
            $options = resourcelib_get_displayoptions(explode(',', $config->displayoptions));
        }

        if (count($options) == 1) {
            $mform->addElement('hidden', 'display');
            $mform->setType('display', PARAM_INT);
            reset($options);
            $mform->setDefault('display', key($options));
        } else {
            $mform->addElement('select', 'display', get_string('displayselect', 'video'), $options);
            $mform->setDefault('display', $config->display);
            $mform->addHelpButton('display', 'displayselect', 'video');
        }

        if (array_key_exists(RESOURCELIB_DISPLAY_POPUP, $options)) {
            $mform->addElement('text', 'popupwidth', get_string('popupwidth', 'video'), array('size' => 3));
            if (count($options) > 1) {
                $mform->disabledIf('popupwidth', 'display', 'noteq', RESOURCELIB_DISPLAY_POPUP);
            }
            $mform->setType('popupwidth', PARAM_INT);
            $mform->setDefault('popupwidth', $config->popupwidth);
            $mform->setAdvanced('popupwidth', true);

            $mform->addElement('text', 'popupheight', get_string('popupheight', 'video'), array('size' => 3));
            if (count($options) > 1) {
                $mform->disabledIf('popupheight', 'display', 'noteq', RESOURCELIB_DISPLAY_POPUP);
            }
            $mform->setType('popupheight', PARAM_INT);
            $mform->setDefault('popupheight', $config->popupheight);
            $mform->setAdvanced('popupheight', true);
        }

        if (array_key_exists(RESOURCELIB_DISPLAY_AUTO, $options) or
                array_key_exists(RESOURCELIB_DISPLAY_EMBED, $options) or
                array_key_exists(RESOURCELIB_DISPLAY_FRAME, $options)) {
            $mform->addElement('checkbox', 'printintro', get_string('printintro', 'video'));
            $mform->disabledIf('printintro', 'display', 'eq', RESOURCELIB_DISPLAY_POPUP);
            $mform->disabledIf('printintro', 'display', 'eq', RESOURCELIB_DISPLAY_DOWNLOAD);
            $mform->disabledIf('printintro', 'display', 'eq', RESOURCELIB_DISPLAY_OPEN);
            $mform->disabledIf('printintro', 'display', 'eq', RESOURCELIB_DISPLAY_NEW);
            $mform->setDefault('printintro', $config->printintro);
        }

        //-------------------------------------------------------
        $this->standard_coursemodule_elements();

        //-------------------------------------------------------
        $this->add_action_buttons();

        //-------------------------------------------------------
        $mform->addElement('hidden', 'revision');
        $mform->setType('revision', PARAM_INT);
        $mform->setDefault('revision', 1);
    }

    private function get_course_socialforum_id() {
        global $COURSE, $DB;

        if (!$COURSE) {
            return 0;
        }
        $socialforums = $DB->get_records('socialforum', array('course' => $COURSE->id));
        if (!$socialforums) {
            return 0;
        }
        $socialforum = reset($socialforums);
        return $socialforum->id;
    }

    function data_preprocessing(&$default_values) {

        // Defaults for new activities only (do not override manual completion on edit).
        if (empty($this->current->instance)) {
            $default_values['completion'] = COMPLETION_TRACKING_AUTOMATIC;
            $default_values['completionview'] = COMPLETION_VIEW_REQUIRED;
        }

        // Set default social form
        $default_values['socialforumid'] = $this->get_course_socialforum_id();

        if ($this->current->instance) {
            // Video format and file
            switch ($default_values['contentformat']) {
                case FORMAT_VIMEO:
                    $default_values['vimeoid'] = $default_values['content'];
                    break;
                case FORMAT_YOUTUBE:
                    $default_values['youtubeid'] = $default_values['content'];
                    break;
                case FORMAT_EMBEDED:
                    $default_values['embededurl'] = $default_values['content'];
                    break;
                case FORMAT_STORED:
                default:
                    $default_values['storedvideo'] = $default_values['content'];
                    break;
            }
            $draftitemid = file_get_submitted_draft_itemid('storedvideo');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_video', 'storedvideo', 0);
            $default_values['storedvideo'] = $draftitemid;

            // Files to be downloaded
            $draftitemid = file_get_submitted_draft_itemid('files');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_video', 'download', 0);
            $default_values['files'] = $draftitemid;
        }

        $draftitemid = file_get_submitted_draft_itemid('cardthumbnail');
        file_prepare_draft_area($draftitemid, $this->context->id, 'mod_video', 'cardthumbnail', 0);
        $default_values['cardthumbnail'] = $draftitemid;

        $draftitemid = file_get_submitted_draft_itemid('caption');
        file_prepare_draft_area($draftitemid, $this->context->id, 'mod_video', 'caption', 0);
        $default_values['caption'] = $draftitemid;

        if (!empty($default_values['displayoptions'])) {
            $displayoptions = unserialize($default_values['displayoptions']);
            if (isset($displayoptions['printintro'])) {
                $default_values['printintro'] = $displayoptions['printintro'];
            }
            if (isset($displayoptions['printheading'])) {
                $default_values['printheading'] = $displayoptions['printheading'];
            }
            if (!empty($displayoptions['popupwidth'])) {
                $default_values['popupwidth'] = $displayoptions['popupwidth'];
            }
            if (!empty($displayoptions['popupheight'])) {
                $default_values['popupheight'] = $displayoptions['popupheight'];
            }
        }
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate video fields
        switch ($data["contentformat"]) {
            case FORMAT_VIMEO:
                if (!$data["vimeoid"]) {
                    $errors['vimeoid'] = get_string('fieldcannotbeempty', 'video', get_string("vimeoid", "video"));
                }
                break;
            case FORMAT_YOUTUBE:
                if (!$data["youtubeid"]) {
                    $errors['youtubeid'] = get_string('fieldcannotbeempty', 'video', get_string("youtubeid", "video"));
                }
                break;
            case FORMAT_EMBEDED:
                if (!$data["embededurl"]) {
                    $errors['embededurl'] = get_string('fieldcannotbeempty', 'video', get_string("embededurl", "video"));
                }
                if (!$this->cardthumbnail_has_files($data)) {
                    $errors['cardthumbnail'] = get_string('fieldcannotbeempty', 'video', get_string('cardthumbnail', 'video'));
                }
                break;
            case FORMAT_STORED:
            default:
                $draftItemId = file_get_submitted_draft_itemid('storedvideo');
                $videofiles = file_get_drafarea_files($draftItemId);
                if (count($videofiles->list) == 0) {
                    $errors['storedvideo'] = get_string('fieldcannotbeempty', 'video', get_string("storedvideo", "video"));
                }
                break;
        }

        return $errors;
    }

    /**
     * Whether the card thumbnail field has an uploaded image (draft or stored).
     *
     * @param array $data Form data
     * @return bool
     */
    protected function cardthumbnail_has_files(array $data): bool {
        global $USER;

        if (!empty($data['cardthumbnail'])) {
            $usercontext = context_user::instance($USER->id);
            $fs = get_file_storage();
            foreach ($fs->get_area_files($usercontext->id, 'user', 'draft', $data['cardthumbnail'], 'itemid', false) as $file) {
                if (!$file->is_directory()) {
                    return true;
                }
            }
        }

        if ($this->current->instance) {
            $fs = get_file_storage();
            foreach ($fs->get_area_files($this->context->id, 'mod_video', 'cardthumbnail', 0, 'itemid', false) as $file) {
                if (!$file->is_directory()) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function init_features() {
        global $PAGE;
        parent::init_features();
        $PAGE->requires->js('/mod/video/formcontrol.js');
    }

}

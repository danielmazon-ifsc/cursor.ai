<?php

/**
 * Lists renamed classes so that the autoloader can make the old names still work.
 *
 * @package   mod_cquiz
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

// Array 'old_class_name' => 'new\class_name'.
$renamedclasses = array(
    // Changed in Moodle 2.8.
    'cquiz_question_bank_view' => 'mod_cquiz\question\bank\custom_view',
    'question_bank_add_to_cquiz_action_column' => 'mod_cquiz\question\bank\add_action_column',
    'question_bank_question_name_text_column' => 'mod_cquiz\question\bank\question_name_text_column',
);

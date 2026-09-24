<?php

/**
 * @package    moodlecore
 * @subpackage backup-moodle2
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Provides the information to backup cmultichoice questions
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class backup_qtype_cmultichoice_plugin extends backup_qtype_plugin {

    /**
     * Returns the qtype information to attach to question element
     */
    protected function define_question_plugin_structure() {

        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element(null, '../../qtype', 'cmultichoice');

        // Create one standard named plugin element (the visible container).
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect the visible container ASAP.
        $plugin->add_child($pluginwrapper);

        // This qtype uses standard question_answers, add them here
        // to the tree before any other information that will use them.
        $this->add_question_question_answers($pluginwrapper);

        // Now create the qtype own structures.
        $cmultichoice = new backup_nested_element('cmultichoice', array('id'), array(
            'layout', 'single', 'shuffleanswers',
            'correctfeedback', 'correctfeedbackformat',
            'partiallycorrectfeedback', 'partiallycorrectfeedbackformat',
            'incorrectfeedback', 'incorrectfeedbackformat', 'answernumbering', 'shownumcorrect'));
        $competency = new backup_nested_element('competency', array('id'), array('competencyid'));

        // Now the own qtype tree.
        $pluginwrapper->add_child($cmultichoice);
        $pluginwrapper->add_child($competency);

        // Set source to populate the data.
        $cmultichoice->set_source_table('qtype_cmultichoice_options', array('questionid' => backup::VAR_PARENTID));
        $competency->set_source_table('qtype_cmultichoice_compts', array('questionid' => backup::VAR_PARENTID));

        // Don't need to annotate ids nor files.

        return $plugin;
    }

}

<?php

/**
 * A column type for the add this question to the cquiz action.
 *
 * @package   mod_cquiz
 * @category  question
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */

namespace mod_cquiz\question\bank;

defined('MOODLE_INTERNAL') || die();

/**
 * A column type for the add this question to the cquiz action.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class add_action_column extends \core_question\bank\action_column_base {

    /** @var string caches a lang string used repeatedly. */
    protected $stradd;

    public function init(): void {
        parent::init();
        $this->stradd = get_string('addtocquiz', 'cquiz');
    }

    public function get_name() {
        return 'addtocquizaction';
    }

    protected function display_content($question, $rowclasses) {
        if (!question_has_capability_on($question, 'use')) {
            return;
        }
        $this->print_icon('t/add', $this->stradd, $this->qbank->add_to_cquiz_url($question->id));
    }

    public function get_required_fields(): array {
        return array('q.id');
    }

}

<?php

/**
 * A column type for the name followed by the start of the question text.
 *
 * @package   mod_cquiz
 * @category  question
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */

namespace mod_cquiz\question\bank;

defined('MOODLE_INTERNAL') || die();

/**
 * A column type for the name followed by the start of the question text.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class question_name_text_column extends \core_question\bank\question_name_column {

    public function get_name(): string {
        return 'questionnametext';
    }

    protected function display_content($question, $rowclasses): void {
        echo '<div>';
        $labelfor = $this->label_for($question);
        if ($labelfor) {
            echo '<label for="' . $labelfor . '">';
        }
        echo cquiz_question_tostring($question);
        if ($labelfor) {
            echo '</label>';
        }
        echo '</div>';
    }

    public function get_required_fields(): array {
        $fields = parent::get_required_fields();
        $fields[] = 'q.questiontext';
        $fields[] = 'q.questiontextformat';
        return $fields;
    }

}

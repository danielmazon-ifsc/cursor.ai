<?php

/**
 * A report to display user's coin ledger
 *
 * @package   local_coin
 * @copyright 2021 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class coinreportledger_form extends moodleform {

    /**
     * @return void
     */
    public function definition() {
        global $USER;

        $mform = $this->_form;

        $mform->addElement('date_selector', 'startdate', get_string('startdate', 'local_coin'), ['optional' => true]);
        $mform->addElement('date_selector', 'finishdate', get_string('finishdate', 'local_coin'), ['optional' => true]);

        $systemcontext = context_system::instance();
        $canviewothers = has_capability('local/coin:viewledger', $systemcontext)
            || has_capability('moodle/user:viewdetails', $systemcontext);

        if ($canviewothers) {
            $mform->addElement('autocomplete', 'userid', get_string('user'), [], [
                'ajax' => 'core_user/form_autocomplete_user',
                'multiple' => false,
                'valuehtmlcallback' => [static::class, 'user_value_html'],
            ]);
            $mform->setType('userid', PARAM_INT);
        } else {
            $mform->addElement('hidden', 'userid', $USER->id);
            $mform->setType('userid', PARAM_INT);
        }

        $mform->addElement('submit', 'getledger', get_string('getledger', 'local_coin'));
    }

    /**
     * @param int $value User id
     * @return string|false
     */
    public static function user_value_html($value) {
        global $DB;

        $user = $DB->get_record('user', ['id' => (int) $value], 'id, firstname, lastname, deleted');
        if (!$user || $user->deleted) {
            return false;
        }
        return fullname($user);
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        return parent::validation($data, $files);
    }
}

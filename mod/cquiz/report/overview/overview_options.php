<?php

/**
 * Class to store the options for a {@link cquiz_overview_report}.
 *
 * @package   cquiz_overview
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/cquiz/report/attemptsreport_options.php');

/**
 * Class to store the options for a {@link cquiz_overview_report}.
 *
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquiz_overview_options extends mod_cquiz_attempts_report_options {

    /** @var bool whether to show only attempt that need regrading. */
    public $onlyregraded = false;

    /** @var bool whether to show marks for each question (slot). */
    public $slotmarks = true;

    protected function get_url_params() {
        $params = parent::get_url_params();
        $params['onlyregraded'] = $this->onlyregraded;
        $params['slotmarks'] = $this->slotmarks;
        return $params;
    }

    public function get_initial_form_data() {
        $toform = parent::get_initial_form_data();
        $toform->onlyregraded = $this->onlyregraded;
        $toform->slotmarks = $this->slotmarks;

        return $toform;
    }

    public function setup_from_form_data($fromform) {
        parent::setup_from_form_data($fromform);

        $this->onlyregraded = !empty($fromform->onlyregraded);
        $this->slotmarks = $fromform->slotmarks;
    }

    public function setup_from_params() {
        parent::setup_from_params();

        $this->onlyregraded = optional_param('onlyregraded', $this->onlyregraded, PARAM_BOOL);
        $this->slotmarks = optional_param('slotmarks', $this->slotmarks, PARAM_BOOL);
    }

    public function setup_from_user_preferences() {
        parent::setup_from_user_preferences();

        $this->slotmarks = get_user_preferences('cquiz_overview_slotmarks', $this->slotmarks);
    }

    public function update_user_preferences() {
        parent::update_user_preferences();

        if (cquiz_has_grades($this->cquiz)) {
            set_user_preference('cquiz_overview_slotmarks', $this->slotmarks);
        }
    }

    public function resolve_dependencies() {
        parent::resolve_dependencies();

        if ($this->attempts == cquiz_attempts_report::ENROLLED_WITHOUT) {
            $this->onlyregraded = false;
        }

        if (!$this->usercanseegrades) {
            $this->slotmarks = false;
        }

        // We only want to show the checkbox to delete attempts
        // if the user has permissions and if the report mode is showing attempts.
        $this->checkboxcolumn = has_any_capability(
                        array('mod/cquiz:regrade', 'mod/cquiz:deleteattempts'), context_module::instance($this->cm->id)) && ($this->attempts != cquiz_attempts_report::ENROLLED_WITHOUT);
    }

}

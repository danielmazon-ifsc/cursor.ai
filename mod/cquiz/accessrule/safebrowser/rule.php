<?php

/**
 * Implementaton of the cquizaccess_safebrowser plugin.
 *
 * @package    cquizaccess
 * @subpackage safebrowser
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/cquiz/accessrule/accessrulebase.php');

/**
 * A rule representing the safe browser check.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquizaccess_safebrowser extends cquiz_access_rule_base {

    public static function make(cquiz $cquizobj, $timenow, $canignoretimelimits) {

        if ($cquizobj->get_cquiz()->browsersecurity !== 'safebrowser') {
            return null;
        }

        return new self($cquizobj, $timenow);
    }

    public function prevent_access() {
        if (!$this->check_safe_browser()) {
            return get_string('safebrowsererror', 'cquizaccess_safebrowser');
        } else {
            return false;
        }
    }

    public function description() {
        return get_string('safebrowsernotice', 'cquizaccess_safebrowser');
    }

    public function setup_attempt_page($page) {
        $page->set_title($this->cquizobj->get_course()->shortname . ': ' . $page->title);
        $page->set_cacheable(false);
        $page->set_popup_notification_allowed(false); // Prevent message notifications.
        $page->set_heading($page->title);
        $page->set_pagelayout('secure');
    }

    /**
     * Checks if browser is safe browser
     *
     * @return true, if browser is safe browser else false
     */
    public function check_safe_browser() {
        return strpos($_SERVER['HTTP_USER_AGENT'], 'SEB') !== false;
    }

    /**
     * @return array key => lang string any choices to add to the cquiz Browser
     *      security settings menu.
     */
    public static function get_browser_security_choices() {
        global $CFG;

        if (empty($CFG->enablesafebrowserintegration)) {
            return array();
        }

        return array('safebrowser' =>
            get_string('requiresafeexambrowser', 'cquizaccess_safebrowser'));
    }

}

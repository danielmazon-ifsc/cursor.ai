<?php

/**
 * Implementaton of the cquizaccess_ipaddress plugin.
 *
 * @package    cquizaccess
 * @subpackage ipaddress
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/cquiz/accessrule/accessrulebase.php');

/**
 * A rule implementing the ipaddress check against the ->subnet setting.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class cquizaccess_ipaddress extends cquiz_access_rule_base {

    public static function make(cquiz $cquizobj, $timenow, $canignoretimelimits) {
        if (empty($cquizobj->get_cquiz()->subnet)) {
            return null;
        }

        return new self($cquizobj, $timenow);
    }

    public function prevent_access() {
        if (address_in_subnet(getremoteaddr(), $this->cquiz->subnet)) {
            return false;
        } else {
            return get_string('subnetwrong', 'cquizaccess_ipaddress');
        }
    }

}

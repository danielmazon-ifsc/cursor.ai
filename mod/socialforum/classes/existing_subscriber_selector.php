<?php

/**
 * A type of socialforum.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/selector/lib.php');

/**
 * User selector control for removing subscribed users
 * 
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class mod_socialforum_existing_subscriber_selector extends mod_socialforum_subscriber_selector_base {

    /**
     * Finds all subscribed users
     *
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;
        list($wherecondition, $params) = $this->search_sql($search, 'u');
        $params['socialforumid'] = $this->socialforumid;

        // only active enrolled or everybody on the frontpage
        list($esql, $eparams) = get_enrolled_sql($this->context, '', $this->currentgroup, true);
        $fields = $this->required_fields_sql('u');
        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $params = array_merge($params, $eparams, $sortparams);

        $subscribers = $DB->get_records_sql("SELECT $fields
                                               FROM {user} u
                                               JOIN ($esql) je ON je.id = u.id
                                               JOIN {socialforum_subscriptions} s ON s.userid = u.id
                                              WHERE $wherecondition AND s.socialforum = :socialforumid
                                           ORDER BY $sort", $params);

        $cm = get_coursemodule_from_instance('socialforum', $this->socialforumid);
        $modinfo = get_fast_modinfo($cm->course);
        $info = new \core_availability\info_module($modinfo->get_cm($cm->id));
        $subscribers = $info->filter_user_list($subscribers);

        return array(get_string("existingsubscribers", 'mod_socialforum') => $subscribers);
    }

}

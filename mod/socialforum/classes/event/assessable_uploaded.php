<?php

/**
 * The mod_socialforum assessable uploaded event.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace mod_socialforum\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_socialforum assessable uploaded event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int discussionid: id of discussion.
 *      - string triggeredfrom: name of the function from where event was triggered.
 * }
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class assessable_uploaded extends \core\event\assessable_uploaded {

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has posted content in the socialforum post with id '$this->objectid' " .
                "in the discussion '{$this->other['discussionid']}' located in the socialforum with course module id " .
                "'$this->contextinstanceid'.";
    }

    /**
     * Legacy event data if get_legacy_eventname() is not empty.
     *
     * @return \stdClass
     */
    protected function get_legacy_eventdata() {
        $eventdata = new \stdClass();
        $eventdata->modulename = 'socialforum';
        $eventdata->name = $this->other['triggeredfrom'];
        $eventdata->cmid = $this->contextinstanceid;
        $eventdata->itemid = $this->objectid;
        $eventdata->courseid = $this->courseid;
        $eventdata->userid = $this->userid;
        $eventdata->content = $this->other['content'];
        if ($this->other['pathnamehashes']) {
            $eventdata->pathnamehashes = $this->other['pathnamehashes'];
        }
        return $eventdata;
    }

    /**
     * Return the legacy event name.
     *
     * @return string
     */
    public static function get_legacy_eventname() {
        return 'assessable_content_uploaded';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventassessableuploaded', 'mod_socialforum');
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/socialforum/discuss.php', array('d' => $this->other['discussionid'], 'parent' => $this->objectid));
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        parent::init();
        $this->data['objecttable'] = 'socialforum_posts';
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['discussionid'])) {
            throw new \coding_exception('The \'discussionid\' value must be set in other.');
        } else if (!isset($this->other['triggeredfrom'])) {
            throw new \coding_exception('The \'triggeredfrom\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'socialforum_posts', 'restore' => 'socialforum_post');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['discussionid'] = array('db' => 'socialforum_discussions', 'restore' => 'socialforum_discussion');

        return $othermapped;
    }

}

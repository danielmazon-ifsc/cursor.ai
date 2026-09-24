<?php

/**
 * The mod_socialforum post voted as no longer the most relevant event.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace mod_socialforum\event;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/socialforum/lib.php');

/**
 * The mod_socialforum post voted as no longer the most relevant event class.
 *
 * @package    mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class post_nolongermostrelevant extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'socialforum_posts';
        $this->data['contextlevel'] = CONTEXT_USER;
        $this->data['courseid'] = null;
        $this->data['anonymous'] = 0;
        $this->data['other'] = null;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return 'The user with id ' . $this->userid . ' voted post id '
                . $this->objectid . ' and it is no longer the most relevant';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventpostnolongermostrelevant', 'mod_socialforum');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        $post = socialforum_get_post_full($this->objectid);
        if ($this->other['socialforumtype'] == 'single') {
            // Single discussion socialforums are an exception. We show
            // the socialforum itself since it only has one discussion
            // thread.
            $url = new \moodle_url('/mod/socialforum/view.php', array('f' => $this->other['socialforumid']));
        } else {
            $url = new \moodle_url('/mod/socialforum/discuss.php', array('d' => $post->discussion));
        }
        $url->set_anchor('p' . $this->objectid);
        return $url;
    }

}

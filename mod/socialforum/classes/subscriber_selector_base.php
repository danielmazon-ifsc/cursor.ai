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
 * Abstract class used by socialforum subscriber selection controls
 * 
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
abstract class mod_socialforum_subscriber_selector_base extends user_selector_base {

    /**
     * The id of the socialforum this selector is being used for
     * @var int
     */
    protected $socialforumid = null;

    /**
     * The context of the socialforum this selector is being used for
     * @var object
     */
    protected $context = null;

    /**
     * The id of the current group
     * @var int
     */
    protected $currentgroup = null;

    /**
     * Constructor method
     * @param string $name
     * @param array $options
     */
    public function __construct($name, $options) {
        $options['accesscontext'] = $options['context'];
        parent::__construct($name, $options);
        if (isset($options['context'])) {
            $this->context = $options['context'];
        }
        if (isset($options['currentgroup'])) {
            $this->currentgroup = $options['currentgroup'];
        }
        if (isset($options['socialforumid'])) {
            $this->socialforumid = $options['socialforumid'];
        }
    }

    /**
     * Returns an array of options to seralise and store for searches
     *
     * @return array
     */
    protected function get_options() {
        global $CFG;
        $options = parent::get_options();
        $options['file'] = substr(__FILE__, strlen($CFG->dirroot . '/'));
        $options['context'] = $this->context;
        $options['currentgroup'] = $this->currentgroup;
        $options['socialforumid'] = $this->socialforumid;
        return $options;
    }

}

<?php

/**
 * Social Forum post renderable.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace mod_socialforum\output\emaildigestbasic;

defined('MOODLE_INTERNAL') || die();

/**
 * Social Forum post renderable.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class renderer extends \mod_socialforum\output\email\renderer {

    /**
     * The template name for this renderer.
     *
     * @return string
     */
    public function socialforum_post_template() {
        return 'socialforum_post_emaildigestbasic_htmlemail';
    }

}

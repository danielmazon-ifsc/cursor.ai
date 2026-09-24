<?php

/**
 * Social Forum post renderable.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace mod_socialforum\output\email;

defined('MOODLE_INTERNAL') || die();

/**
 * Social Forum post renderable.
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
class renderer_textemail extends renderer {

    /**
     * The template name for this renderer.
     *
     * @return string
     */
    public function socialforum_post_template() {
        return 'socialforum_post_email_textemail';
    }

    /**
     * The plaintext version of the e-mail message.
     *
     * @param \stdClass $cm
     * @param \stdClass $post
     * @return string
     */
    public function format_message_text($cm, $post) {
        $message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', \context_module::instance($cm->id)->id, 'mod_socialforum', 'post', $post->id);
        return format_text_email($message, $post->messageformat);
    }

    /**
     * The plaintext version of the attachments list.
     *
     * @param \stdClass $cm
     * @param \stdClass $post
     * @return string
     */
    public function format_message_attachments($cm, $post) {
        return socialforum_print_attachments($post, $cm, "text");
    }

}

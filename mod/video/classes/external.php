<?php

/**
 * Video external API
 *
 * @package   mod_video
 * @category  external
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

/**
 * Video external functions
 *
 * @package    mod_video
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_video_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function view_video_parameters() {
        return new external_function_parameters(
                array(
            'videoid' => new external_value(PARAM_INT, 'video instance id')
                )
        );
    }

    /**
     * Simulate the video/view.php web interface video: trigger events, completion, etc...
     *
     * @param int $videoid the video instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function view_video($videoid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/video/lib.php");

        $params = self::validate_parameters(self::view_video_parameters(), array(
                    'videoid' => $videoid
        ));
        $warnings = array();

        // Request and permission validation.
        $video = $DB->get_record('video', array('id' => $params['videoid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($video, 'video');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/video:view', $context);

        // Call the video/lib API.
        video_view($video, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function view_video_returns() {
        return new external_single_structure(
                array(
            'status' => new external_value(PARAM_BOOL, 'status: true if success'),
            'warnings' => new external_warnings()
                )
        );
    }

}

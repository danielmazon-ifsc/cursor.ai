<?php

/**
 * Search area for mod_scorm activities.
 *
 * @package    mod_scorm
 */

namespace mod_scorm\search;

defined('MOODLE_INTERNAL') || die();

/**
 * Search area for mod_scorm activities.
 *
 * @package    mod_scorm
 */
class activity extends \core_search\base_activity {

    /**
     * Returns true if this area uses file indexing.
     *
     * @return bool
     */
    public function uses_file_indexing() {
        return true;
    }

}

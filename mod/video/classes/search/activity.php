<?php

/**
 * Search area for mod_video activities.
 *
 * @package   mod_video
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace mod_video\search;

defined('MOODLE_INTERNAL') || die();

/**
 * Search area for mod_video activities.
 *
 * @package    mod_video
 * @copyright  2015 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

    /**
     * Add all the folder files to the index.
     *
     * @param document $document The current document
     * @return null
     */
    public function attach_files($document) {
        $fs = get_file_storage();

        $cm = $this->get_cm($this->get_module_name(), $document->get('itemid'), $document->get('courseid'));
        $context = \context_module::instance($cm->id);

        $videofiles = $fs->get_area_files($context->id, 'mod_video', 'storedvideo', 0, 'sortorder DESC, id ASC', false);
        foreach ($videofiles as $file) {
            $document->add_stored_file($file);
        }
        $downloadfiles = $fs->get_area_files($context->id, 'mod_video', 'download', 0, 'sortorder DESC, id ASC', false);
        foreach ($downloadfiles as $file) {
            $document->add_stored_file($file);
        }
    }

    /**
     * Returns the document associated with this activity.
     *
     * Overwriting base_activity method as video contents field is required,
     * description field is not.
     *
     * @param stdClass $record
     * @param array    $options
     * @return \core_search\document
     */
    public function get_document($record, $options = array()) {

        try {
            $cm = $this->get_cm($this->get_module_name(), $record->id, $record->course);
            $context = \context_module::instance($cm->id);
        } catch (\dml_missing_record_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document, not all required data is available: ' .
                    $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        } catch (\dml_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        // Prepare associative array with data from DB.
        $doc = \core_search\document_factory::instance($record->id, $this->componentname, $this->areaname);
        $doc->set('title', content_to_text($record->name, false));
        $doc->set('content', content_to_text($record->content, $record->contentformat));
        $doc->set('contextid', $context->id);
        $doc->set('courseid', $record->course);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $doc->set('modified', $record->timemodified);
        $doc->set('description1', content_to_text($record->intro, $record->introformat));

        return $doc;
    }

}

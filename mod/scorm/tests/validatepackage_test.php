<?php

/**
 * Unit tests for the mod_quiz_display_options class.
 *
 * @package    mod_scorm
 * @category   phpunit
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/scorm/locallib.php');

/**
 * Unit tests for {@link mod_scorm}.
 *
 */
class mod_scorm_validatepackage_testcase extends advanced_testcase {

    /**
     * Convenience to take a fixture test file and create a stored_file.
     *
     * @param string $filepath
     * @return stored_file
     */
    protected function create_stored_file_from_path($filepath) {
        $syscontext = context_system::instance();
        $filerecord = array(
            'contextid' => $syscontext->id,
            'component' => 'mod_scorm',
            'filearea' => 'unittest',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => basename($filepath)
        );

        $fs = get_file_storage();
        return $fs->create_file_from_pathname($filerecord, $filepath);
    }

    public function test_validate_package() {
        global $CFG;

        $this->resetAfterTest(true);

        $filename = "validscorm.zip";
        $file = $this->create_stored_file_from_path($CFG->dirroot . '/mod/scorm/tests/packages/' . $filename, file_archive::OPEN);
        $errors = scorm_validate_package($file);
        $this->assertEmpty($errors);

        $filename = "validaicc.zip";
        $file = $this->create_stored_file_from_path($CFG->dirroot . '/mod/scorm/tests/packages/' . $filename, file_archive::OPEN);
        $errors = scorm_validate_package($file);
        $this->assertEmpty($errors);

        $filename = "invalid.zip";
        $file = $this->create_stored_file_from_path($CFG->dirroot . '/mod/scorm/tests/packages/' . $filename, file_archive::OPEN);
        $errors = scorm_validate_package($file);
        $this->assertArrayHasKey('packagefile', $errors);
        if (isset($errors['packagefile'])) {
            $this->assertEquals(get_string('nomanifest', 'scorm'), $errors['packagefile']);
        }

        $filename = "badscorm.zip";
        $file = $this->create_stored_file_from_path($CFG->dirroot . '/mod/scorm/tests/packages/' . $filename, file_archive::OPEN);
        $errors = scorm_validate_package($file);
        $this->assertArrayHasKey('packagefile', $errors);
        if (isset($errors['packagefile'])) {
            $this->assertEquals(get_string('badimsmanifestlocation', 'scorm'), $errors['packagefile']);
        }
    }

}

<?php

/**
 * This file contains mappings for classes that have been renamed so that they meet the requirements of the autoloader.
 *
 * Renaming isn't always the recommended approach, but can provide benefit in situations where we've already got a
 * close structure, OR where lots of classes get included and not necessarily used, or checked for often.
 *
 * When renaming a class delete the original class and add an entry to the db/renamedclasses.php directory for that
 * component.
 * This way we don't need to keep around old classes, instead creating aliases only when required.
 * One big advantage to this method is that we provide consistent debugging for renamed classes when they are used.
 *
 * @package    scormreport_interactions
 */
defined('MOODLE_INTERNAL') || die();

// Like other files in the db directory this file uses an array.
// The old class name is the key, the new class name is the value.
// The array must be called $renamedclasses.
$renamedclasses = array(
    'scorm_interactions_report' => 'scormreport_objectives\report'
);

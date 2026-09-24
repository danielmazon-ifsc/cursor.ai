<?php

/**
 * Library of functions used by the cquiz module.
 *
 * This contains functions that are called from within the cquiz module only
 * Functions that are also called by core Moodle are in {@link lib.php}
 * This script also loads the code in {@link questionlib.php} which holds
 * the module-indpendent code for handling questions and which in turn
 * initialises all the questiontype classes.
 *
 * @package    mod_cquiz
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/cquiz/lib.php');
require_once($CFG->dirroot . '/mod/cquiz/accessmanager.php');
require_once($CFG->dirroot . '/mod/cquiz/accessmanager_form.php');
require_once($CFG->dirroot . '/mod/cquiz/renderer.php');
require_once($CFG->dirroot . '/mod/cquiz/attemptlib.php');
require_once($CFG->libdir . '/completionlib.php');
//require_once($CFG->libdir . '/eventslib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/questionlib.php');

use core\message\message;

/**
 * @var int We show the countdown timer if there is less than this amount of time left before the
 * the cquiz close date. (1 hour)
 */
define('CQUIZ_SHOW_TIME_BEFORE_DEADLINE', '3600');

/**
 * @var int If there are fewer than this many seconds left when the student submits
 * a page of the cquiz, then do not take them to the next page of the cquiz. Instead
 * close the cquiz immediately.
 */
define('CQUIZ_MIN_TIME_TO_CONTINUE', '2');

/**
 * @var int We show no image when user selects No image from dropdown menu in cquiz settings.
 */
define('CQUIZ_SHOWIMAGE_NONE', 0);

/**
 * @var int We show small image when user selects small image from dropdown menu in cquiz settings.
 */
define('CQUIZ_SHOWIMAGE_SMALL', 1);

/**
 * @var int We show Large image when user selects Large image from dropdown menu in cquiz settings.
 */
define('CQUIZ_SHOWIMAGE_LARGE', 2);


// Functions related to attempts ///////////////////////////////////////////////

/**
 * Creates an object to represent a new attempt at a cquiz
 *
 * Creates an attempt object to represent an attempt at the cquiz by the current
 * user starting at the current time. The ->id field is not set. The object is
 * NOT written to the database.
 *
 * @param object $cquizobj the cquiz object to create an attempt for.
 * @param int $attemptnumber the sequence number for the attempt.
 * @param object $lastattempt the previous attempt by this user, if any. Only needed
 *         if $attemptnumber > 1 and $cquiz->attemptonlast is true.
 * @param int $timenow the time the attempt was started at.
 * @param bool $ispreview whether this new attempt is a preview.
 * @param int $userid  the id of the user attempting this cquiz.
 *
 * @return object the newly created attempt object.
 */
function cquiz_create_attempt(cquiz $cquizobj, $attemptnumber, $lastattempt, $timenow, $ispreview = false, $userid = null) {
    global $USER;

    if ($userid === null) {
        $userid = $USER->id;
    }

    $cquiz = $cquizobj->get_cquiz();
    if ($cquiz->sumgrades < 0.000005 && $cquiz->grade > 0.000005) {
        throw new moodle_exception('cannotstartgradesmismatch', 'cquiz', new moodle_url('/mod/cquiz/view.php', array('q' => $cquiz->id)), array('grade' => cquiz_format_grade($cquiz, $cquiz->grade)));
    }

    if ($attemptnumber == 1 || !$cquiz->attemptonlast) {
        // We are not building on last attempt so create a new attempt.
        $attempt = new stdClass();
        $attempt->cquiz = $cquiz->id;
        $attempt->userid = $userid;
        $attempt->preview = 0;
        $attempt->layout = '';
    } else {
        // Build on last attempt.
        if (empty($lastattempt)) {
            print_error('cannotfindprevattempt', 'cquiz');
        }
        $attempt = $lastattempt;
    }

    $attempt->attempt = $attemptnumber;
    $attempt->timestart = $timenow;
    $attempt->timefinish = 0;
    $attempt->timemodified = $timenow;
    $attempt->state = cquiz_attempt::IN_PROGRESS;
    $attempt->currentpage = 0;
    $attempt->sumgrades = null;

    // If this is a preview, mark it as such.
    if ($ispreview) {
        $attempt->preview = 1;
    }

    $timeclose = $cquizobj->get_access_manager($timenow)->get_end_time($attempt);
    if ($timeclose === false || $ispreview) {
        $attempt->timecheckstate = null;
    } else {
        $attempt->timecheckstate = $timeclose;
    }

    return $attempt;
}

/**
 * Start a normal, new, cquiz attempt.
 *
 * @param cquiz      $cquizobj            the cquiz object to start an attempt for.
 * @param question_usage_by_activity $quba
 * @param object    $attempt
 * @param integer   $attemptnumber      starting from 1
 * @param integer   $timenow            the attempt start time
 * @param array     $questionids        slot number => question id. Used for random questions, to force the choice
 *                                        of a particular actual question. Intended for testing purposes only.
 * @param array     $forcedvariantsbyslot slot number => variant. Used for questions with variants,
 *                                          to force the choice of a particular variant. Intended for testing
 *                                          purposes only.
 * @throws moodle_exception
 * @return object   modified attempt object
 */
function cquiz_start_new_attempt($cquizobj, $quba, $attempt, $attemptnumber, $timenow, $questionids = array(), $forcedvariantsbyslot = array()) {

    // Usages for this user's previous cquiz attempts.
    $qubaids = new \mod_cquiz\question\qubaids_for_users_attempts(
            $cquizobj->get_cquizid(), $attempt->userid);

    // Fully load all the questions in this cquiz.
    $cquizobj->preload_questions();
    $cquizobj->load_questions();

    // First load all the non-random questions.
    $randomfound = false;
    $slot = 0;
    $questions = array();
    $maxmark = array();
    $page = array();
    foreach ($cquizobj->get_questions() as $questiondata) {
        $slot += 1;
        $maxmark[$slot] = $questiondata->maxmark;
        $page[$slot] = $questiondata->page;
        if ($questiondata->qtype == 'random') {
            $randomfound = true;
            continue;
        }
        if (!$cquizobj->get_cquiz()->shuffleanswers) {
            $questiondata->options->shuffleanswers = false;
        }
        $questions[$slot] = question_bank::make_question($questiondata);
    }

    // Then find a question to go in place of each random question.
    if ($randomfound) {
        $slot = 0;
        $usedquestionids = array();
        foreach ($questions as $question) {
            if (isset($usedquestions[$question->id])) {
                $usedquestionids[$question->id] += 1;
            } else {
                $usedquestionids[$question->id] = 1;
            }
        }
        $randomloader = new \core_question\bank\random_question_loader($qubaids, $usedquestionids);

        foreach ($cquizobj->get_questions() as $questiondata) {
            $slot += 1;
            if ($questiondata->qtype != 'random') {
                continue;
            }

            // Deal with fixed random choices for testing.
            if (isset($questionids[$quba->next_slot_number()])) {
                if ($randomloader->is_question_available($questiondata->category, (bool) $questiondata->questiontext, $questionids[$quba->next_slot_number()])) {
                    $questions[$slot] = question_bank::load_question(
                                    $questionids[$quba->next_slot_number()], $cquizobj->get_cquiz()->shuffleanswers);
                    continue;
                } else {
                    throw new coding_exception('Forced question id not available.');
                }
            }

            // Normal case, pick one at random.
            $questionid = $randomloader->get_next_question_id($questiondata->category, (bool) $questiondata->questiontext);
            if ($questionid === null) {
                throw new moodle_exception('notenoughrandomquestions', 'cquiz', $cquizobj->view_url(), $questiondata);
            }

            $questions[$slot] = question_bank::load_question($questionid, $cquizobj->get_cquiz()->shuffleanswers);
        }
    }

    // Finally add them all to the usage.
    ksort($questions);
    foreach ($questions as $slot => $question) {
        $newslot = $quba->add_question($question, $maxmark[$slot]);
        if ($newslot != $slot) {
            throw new coding_exception('Slot numbers have got confused.');
        }
    }

    // Start all the questions.
    $variantstrategy = new core_question\engine\variants\least_used_strategy($quba, $qubaids);

    if (!empty($forcedvariantsbyslot)) {
        $forcedvariantsbyseed = question_variant_forced_choices_selection_strategy::prepare_forced_choices_array(
                        $forcedvariantsbyslot, $quba);
        $variantstrategy = new question_variant_forced_choices_selection_strategy(
                $forcedvariantsbyseed, $variantstrategy);
    }

    $quba->start_all_questions($variantstrategy, $timenow);

    // Work out the attempt layout.
    $sections = $cquizobj->get_sections();
    foreach ($sections as $i => $section) {
        if (isset($sections[$i + 1])) {
            $sections[$i]->lastslot = $sections[$i + 1]->firstslot - 1;
        } else {
            $sections[$i]->lastslot = count($questions);
        }
    }

    $layout = array();
    foreach ($sections as $section) {
        if ($section->shufflequestions) {
            $questionsinthissection = array();
            for ($slot = $section->firstslot; $slot <= $section->lastslot; $slot += 1) {
                $questionsinthissection[] = $slot;
            }
            shuffle($questionsinthissection);
            $questionsonthispage = 0;
            foreach ($questionsinthissection as $slot) {
                if ($questionsonthispage && $questionsonthispage == $cquizobj->get_cquiz()->questionsperpage) {
                    $layout[] = 0;
                    $questionsonthispage = 0;
                }
                $layout[] = $slot;
                $questionsonthispage += 1;
            }
        } else {
            $currentpage = $page[$section->firstslot];
            for ($slot = $section->firstslot; $slot <= $section->lastslot; $slot += 1) {
                if ($currentpage !== null && $page[$slot] != $currentpage) {
                    $layout[] = 0;
                }
                $layout[] = $slot;
                $currentpage = $page[$slot];
            }
        }

        // Each section ends with a page break.
        $layout[] = 0;
    }
    $attempt->layout = implode(',', $layout);

    return $attempt;
}

/**
 * Start a subsequent new attempt, in each attempt builds on last mode.
 *
 * @param question_usage_by_activity    $quba         this question usage
 * @param object                        $attempt      this attempt
 * @param object                        $lastattempt  last attempt
 * @return object                       modified attempt object
 *
 */
function cquiz_start_attempt_built_on_last($quba, $attempt, $lastattempt) {
    $oldquba = question_engine::load_questions_usage_by_activity($lastattempt->uniqueid);

    $oldnumberstonew = array();
    foreach ($oldquba->get_attempt_iterator() as $oldslot => $oldqa) {
        $newslot = $quba->add_question($oldqa->get_question(), $oldqa->get_max_mark());

        $quba->start_question_based_on($newslot, $oldqa);

        $oldnumberstonew[$oldslot] = $newslot;
    }

    // Update attempt layout.
    $newlayout = array();
    foreach (explode(',', $lastattempt->layout) as $oldslot) {
        if ($oldslot != 0) {
            $newlayout[] = $oldnumberstonew[$oldslot];
        } else {
            $newlayout[] = 0;
        }
    }
    $attempt->layout = implode(',', $newlayout);
    return $attempt;
}

/**
 * The save started question usage and cquiz attempt in db and log the started attempt.
 *
 * @param cquiz                       $cquizobj
 * @param question_usage_by_activity $quba
 * @param object                     $attempt
 * @return object                    attempt object with uniqueid and id set.
 */
function cquiz_attempt_save_started($cquizobj, $quba, $attempt) {
    global $DB;
    // Save the attempt in the database.
    question_engine::save_questions_usage_by_activity($quba);
    $attempt->uniqueid = $quba->get_id();
    $attempt->id = $DB->insert_record('cquiz_attempts', $attempt);

    // Params used by the events below.
    $params = array(
        'objectid' => $attempt->id,
        'relateduserid' => $attempt->userid,
        'courseid' => $cquizobj->get_courseid(),
        'context' => $cquizobj->get_context()
    );
    // Decide which event we are using.
    if ($attempt->preview) {
        $params['other'] = array(
            'cquizid' => $cquizobj->get_cquizid()
        );
        $event = \mod_cquiz\event\attempt_preview_started::create($params);
    } else {
        $event = \mod_cquiz\event\attempt_started::create($params);
    }

    // Trigger the event.
    $event->add_record_snapshot('cquiz', $cquizobj->get_cquiz());
    $event->add_record_snapshot('cquiz_attempts', $attempt);
    $event->trigger();

    return $attempt;
}

/**
 * Returns an unfinished attempt (if there is one) for the given
 * user on the given cquiz. This function does not return preview attempts.
 *
 * @param int $cquizid the id of the cquiz.
 * @param int $userid the id of the user.
 *
 * @return mixed the unfinished attempt if there is one, false if not.
 */
function cquiz_get_user_attempt_unfinished($cquizid, $userid) {
    $attempts = cquiz_get_user_attempts($cquizid, $userid, 'unfinished', true);
    if ($attempts) {
        return array_shift($attempts);
    } else {
        return false;
    }
}

/**
 * Delete a cquiz attempt.
 * @param mixed $attempt an integer attempt id or an attempt object
 *      (row of the cquiz_attempts table).
 * @param object $cquiz the cquiz object.
 */
function cquiz_delete_attempt($attempt, $cquiz) {
    global $DB;
    if (is_numeric($attempt)) {
        if (!$attempt = $DB->get_record('cquiz_attempts', array('id' => $attempt))) {
            return;
        }
    }

    if ($attempt->cquiz != $cquiz->id) {
        debugging("Trying to delete attempt $attempt->id which belongs to cquiz $attempt->cquiz " .
                "but was passed cquiz $cquiz->id.");
        return;
    }

    if (!isset($cquiz->cmid)) {
        $cm = get_coursemodule_from_instance('cquiz', $cquiz->id, $cquiz->course);
        $cquiz->cmid = $cm->id;
    }

    question_engine::delete_questions_usage_by_activity($attempt->uniqueid);
    $DB->delete_records('cquiz_attempts', array('id' => $attempt->id));

    // Log the deletion of the attempt if not a preview.
    if (!$attempt->preview) {
        $params = array(
            'objectid' => $attempt->id,
            'relateduserid' => $attempt->userid,
            'context' => context_module::instance($cquiz->cmid),
            'other' => array(
                'cquizid' => $cquiz->id
            )
        );
        $event = \mod_cquiz\event\attempt_deleted::create($params);
        $event->add_record_snapshot('cquiz_attempts', $attempt);
        $event->trigger();
    }

    // Search cquiz_attempts for other instances by this user.
    // If none, then delete record for this cquiz, this user from cquiz_grades
    // else recalculate best grade.
    $userid = $attempt->userid;
    if (!$DB->record_exists('cquiz_attempts', array('userid' => $userid, 'cquiz' => $cquiz->id))) {
        $DB->delete_records('cquiz_grades', array('userid' => $userid, 'cquiz' => $cquiz->id));
    } else {
        cquiz_save_best_grade($cquiz, $userid);
    }

    cquiz_update_grades($cquiz, $userid);
}

/**
 * Delete all the preview attempts at a cquiz, or possibly all the attempts belonging
 * to one user.
 * @param object $cquiz the cquiz object.
 * @param int $userid (optional) if given, only delete the previews belonging to this user.
 */
function cquiz_delete_previews($cquiz, $userid = null) {
    global $DB;
    $conditions = array('cquiz' => $cquiz->id, 'preview' => 1);
    if (!empty($userid)) {
        $conditions['userid'] = $userid;
    }
    $previewattempts = $DB->get_records('cquiz_attempts', $conditions);
    foreach ($previewattempts as $attempt) {
        cquiz_delete_attempt($attempt, $cquiz);
    }
}

/**
 * @param int $cquizid The cquiz id.
 * @return bool whether this cquiz has any (non-preview) attempts.
 */
function cquiz_has_attempts($cquizid) {
    global $DB;
    return $DB->record_exists('cquiz_attempts', array('cquiz' => $cquizid, 'preview' => 0));
}

// Functions to do with cquiz layout and pages //////////////////////////////////

/**
 * Repaginate the questions in a cquiz
 * @param int $cquizid the id of the cquiz to repaginate.
 * @param int $slotsperpage number of items to put on each page. 0 means unlimited.
 */
function cquiz_repaginate_questions($cquizid, $slotsperpage) {
    global $DB;
    $trans = $DB->start_delegated_transaction();

    $sections = $DB->get_records('cquiz_sections', array('cquizid' => $cquizid), 'firstslot ASC');
    $firstslots = array();
    foreach ($sections as $section) {
        if ((int) $section->firstslot === 1) {
            continue;
        }
        $firstslots[] = $section->firstslot;
    }

    $slots = $DB->get_records('cquiz_slots', array('cquizid' => $cquizid), 'slot');
    $currentpage = 1;
    $slotsonthispage = 0;
    foreach ($slots as $slot) {
        if (($firstslots && in_array($slot->slot, $firstslots)) ||
                ($slotsonthispage && $slotsonthispage == $slotsperpage)) {
            $currentpage += 1;
            $slotsonthispage = 0;
        }
        if ($slot->page != $currentpage) {
            $DB->set_field('cquiz_slots', 'page', $currentpage, array('id' => $slot->id));
        }
        $slotsonthispage += 1;
    }

    $trans->allow_commit();
}

// Functions to do with cquiz grades ////////////////////////////////////////////

/**
 * Convert the raw grade stored in $attempt into a grade out of the maximum
 * grade for this cquiz.
 *
 * @param float $rawgrade the unadjusted grade, fof example $attempt->sumgrades
 * @param object $cquiz the cquiz object. Only the fields grade, sumgrades and decimalpoints are used.
 * @param bool|string $format whether to format the results for display
 *      or 'question' to format a question grade (different number of decimal places.
 * @return float|string the rescaled grade, or null/the lang string 'notyetgraded'
 *      if the $grade is null.
 */
function cquiz_rescale_grade($rawgrade, $cquiz, $format = true) {
    if (is_null($rawgrade)) {
        $grade = null;
    } else if ($cquiz->sumgrades >= 0.000005) {
        $grade = $rawgrade * $cquiz->grade / $cquiz->sumgrades;
    } else {
        $grade = 0;
    }
    if ($format === 'question') {
        $grade = cquiz_format_question_grade($cquiz, $grade);
    } else if ($format) {
        $grade = cquiz_format_grade($cquiz, $grade);
    }
    return $grade;
}

/**
 * Get the feedback object for this grade on this cquiz.
 *
 * @param float $grade a grade on this cquiz.
 * @param object $cquiz the cquiz settings.
 * @return false|stdClass the record object or false if there is not feedback for the given grade
 * @since  Moodle 3.1
 */
function cquiz_feedback_record_for_grade($grade, $cquiz) {
    global $DB;

    // With CBM etc, it is possible to get -ve grades, which would then not match
    // any feedback. Therefore, we replace -ve grades with 0.
    $grade = max($grade, 0);

    $feedback = $DB->get_record_select('cquiz_feedback', 'cquizid = ? AND mingrade <= ? AND ? < maxgrade', array($cquiz->id, $grade, $grade));

    return $feedback;
}

/**
 * Get the feedback text that should be show to a student who
 * got this grade on this cquiz. The feedback is processed ready for diplay.
 *
 * @param float $grade a grade on this cquiz.
 * @param object $cquiz the cquiz settings.
 * @param object $context the cquiz context.
 * @return string the comment that corresponds to this grade (empty string if there is not one.
 */
function cquiz_feedback_for_grade($grade, $cquiz, $context) {

    if (is_null($grade)) {
        return '';
    }

    $feedback = cquiz_feedback_record_for_grade($grade, $cquiz);

    if (empty($feedback->feedbacktext)) {
        return '';
    }

    // Clean the text, ready for display.
    $formatoptions = new stdClass();
    $formatoptions->noclean = true;
    $feedbacktext = file_rewrite_pluginfile_urls($feedback->feedbacktext, 'pluginfile.php', $context->id, 'mod_cquiz', 'feedback', $feedback->id);
    $feedbacktext = format_text($feedbacktext, $feedback->feedbacktextformat, $formatoptions);

    return $feedbacktext;
}

/**
 * @param object $cquiz the cquiz database row.
 * @return bool Whether this cquiz has any non-blank feedback text.
 */
function cquiz_has_feedback($cquiz) {
    global $DB;
    static $cache = array();
    if (!array_key_exists($cquiz->id, $cache)) {
        $cache[$cquiz->id] = cquiz_has_grades($cquiz) &&
                $DB->record_exists_select('cquiz_feedback', "cquizid = ? AND " .
                        $DB->sql_isnotempty('cquiz_feedback', 'feedbacktext', false, true), array($cquiz->id));
    }
    return $cache[$cquiz->id];
}

/**
 * Update the sumgrades field of the cquiz. This needs to be called whenever
 * the grading structure of the cquiz is changed. For example if a question is
 * added or removed, or a question weight is changed.
 *
 * You should call {@link cquiz_delete_previews()} before you call this function.
 *
 * @param object $cquiz a cquiz.
 */
function cquiz_update_sumgrades($cquiz) {
    global $DB;

    $sql = 'UPDATE {cquiz}
            SET sumgrades = COALESCE((
                SELECT SUM(maxmark)
                FROM {cquiz_slots}
                WHERE cquizid = {cquiz}.id
            ), 0)
            WHERE id = ?';
    $DB->execute($sql, array($cquiz->id));
    $cquiz->sumgrades = $DB->get_field('cquiz', 'sumgrades', array('id' => $cquiz->id));

    if ($cquiz->sumgrades < 0.000005 && cquiz_has_attempts($cquiz->id)) {
        // If the cquiz has been attempted, and the sumgrades has been
        // set to 0, then we must also set the maximum possible grade to 0, or
        // we will get a divide by zero error.
        cquiz_set_grade(0, $cquiz);
    }
}

/**
 * Update the sumgrades field of the attempts at a cquiz.
 *
 * @param object $cquiz a cquiz.
 */
function cquiz_update_all_attempt_sumgrades($cquiz) {
    global $DB;
    $dm = new question_engine_data_mapper();
    $timenow = time();

    $sql = "UPDATE {cquiz_attempts}
            SET
                timemodified = :timenow,
                sumgrades = (
                    {$dm->sum_usage_marks_subquery('uniqueid')}
                )
            WHERE cquiz = :cquizid AND state = :finishedstate";
    $DB->execute($sql, array('timenow' => $timenow, 'cquizid' => $cquiz->id,
        'finishedstate' => cquiz_attempt::FINISHED));
}

/**
 * The cquiz grade is the maximum that student's results are marked out of. When it
 * changes, the corresponding data in cquiz_grades and cquiz_feedback needs to be
 * rescaled. After calling this function, you probably need to call
 * cquiz_update_all_attempt_sumgrades, cquiz_update_all_final_grades and
 * cquiz_update_grades.
 *
 * @param float $newgrade the new maximum grade for the cquiz.
 * @param object $cquiz the cquiz we are updating. Passed by reference so its
 *      grade field can be updated too.
 * @return bool indicating success or failure.
 */
function cquiz_set_grade($newgrade, $cquiz) {
    global $DB;
    // This is potentially expensive, so only do it if necessary.
    if (abs($cquiz->grade - $newgrade) < 1e-7) {
        // Nothing to do.
        return true;
    }

    $oldgrade = $cquiz->grade;
    $cquiz->grade = $newgrade;

    // Use a transaction, so that on those databases that support it, this is safer.
    $transaction = $DB->start_delegated_transaction();

    // Update the cquiz table.
    $DB->set_field('cquiz', 'grade', $newgrade, array('id' => $cquiz->instance));

    if ($oldgrade < 1) {
        // If the old grade was zero, we cannot rescale, we have to recompute.
        // We also recompute if the old grade was too small to avoid underflow problems.
        cquiz_update_all_final_grades($cquiz);
    } else {
        // We can rescale the grades efficiently.
        $timemodified = time();
        $DB->execute("
                UPDATE {cquiz_grades}
                SET grade = ? * grade, timemodified = ?
                WHERE cquiz = ?
        ", array($newgrade / $oldgrade, $timemodified, $cquiz->id));
    }

    if ($oldgrade > 1e-7) {
        // Update the cquiz_feedback table.
        $factor = $newgrade / $oldgrade;
        $DB->execute("
                UPDATE {cquiz_feedback}
                SET mingrade = ? * mingrade, maxgrade = ? * maxgrade
                WHERE cquizid = ?
        ", array($factor, $factor, $cquiz->id));
    }

    // Update grade item and send all grades to gradebook.
    cquiz_grade_item_update($cquiz);
    cquiz_update_grades($cquiz);

    $transaction->allow_commit();
    return true;
}

/**
 * Save the overall grade for a user at a cquiz in the cquiz_grades table
 *
 * @param object $cquiz The cquiz for which the best grade is to be calculated and then saved.
 * @param int $userid The userid to calculate the grade for. Defaults to the current user.
 * @param array $attempts The attempts of this user. Useful if you are
 * looping through many users. Attempts can be fetched in one master query to
 * avoid repeated querying.
 * @return bool Indicates success or failure.
 */
function cquiz_save_best_grade($cquiz, $userid = null, $attempts = array()) {
    global $DB, $OUTPUT, $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    if (!$attempts) {
        // Get all the attempts made by the user.
        $attempts = cquiz_get_user_attempts($cquiz->id, $userid);
    }

    // Calculate the best grade.
    $bestgrade = cquiz_calculate_best_grade($cquiz, $attempts);
    $bestgrade = cquiz_rescale_grade($bestgrade, $cquiz, false);

    // Save the best grade in the database.
    if (is_null($bestgrade)) {
        $DB->delete_records('cquiz_grades', array('cquiz' => $cquiz->id, 'userid' => $userid));
    } else if ($grade = $DB->get_record('cquiz_grades', array('cquiz' => $cquiz->id, 'userid' => $userid))) {
        $grade->grade = $bestgrade;
        $grade->timemodified = time();
        $DB->update_record('cquiz_grades', $grade);
    } else {
        $grade = new stdClass();
        $grade->cquiz = $cquiz->id;
        $grade->userid = $userid;
        $grade->grade = $bestgrade;
        $grade->timemodified = time();
        $DB->insert_record('cquiz_grades', $grade);
    }

    cquiz_update_grades($cquiz, $userid);
}

/**
 * Calculate the overall grade for a cquiz given a number of attempts by a particular user.
 *
 * @param object $cquiz    the cquiz settings object.
 * @param array $attempts an array of all the user's attempts at this cquiz in order.
 * @return float          the overall grade
 */
function cquiz_calculate_best_grade($cquiz, $attempts) {

    switch ($cquiz->grademethod) {

        case CQUIZ_ATTEMPTFIRST:
            $firstattempt = reset($attempts);
            return $firstattempt->sumgrades;

        case CQUIZ_ATTEMPTLAST:
            $lastattempt = end($attempts);
            return $lastattempt->sumgrades;

        case CQUIZ_GRADEAVERAGE:
            $sum = 0;
            $count = 0;
            foreach ($attempts as $attempt) {
                if (!is_null($attempt->sumgrades)) {
                    $sum += $attempt->sumgrades;
                    $count++;
                }
            }
            if ($count == 0) {
                return null;
            }
            return $sum / $count;

        case CQUIZ_GRADEHIGHEST:
        default:
            $max = null;
            foreach ($attempts as $attempt) {
                if ($attempt->sumgrades > $max) {
                    $max = $attempt->sumgrades;
                }
            }
            return $max;
    }
}

/**
 * Update the final grade at this cquiz for all students.
 *
 * This function is equivalent to calling cquiz_save_best_grade for all
 * users, but much more efficient.
 *
 * @param object $cquiz the cquiz settings.
 */
function cquiz_update_all_final_grades($cquiz) {
    global $DB;

    if (!$cquiz->sumgrades) {
        return;
    }

    $param = array('icquizid' => $cquiz->id, 'istatefinished' => cquiz_attempt::FINISHED);
    $firstlastattemptjoin = "JOIN (
            SELECT
                icquiza.userid,
                MIN(attempt) AS firstattempt,
                MAX(attempt) AS lastattempt

            FROM {cquiz_attempts} icquiza

            WHERE
                icquiza.state = :istatefinished AND
                icquiza.preview = 0 AND
                icquiza.cquiz = :icquizid

            GROUP BY icquiza.userid
        ) first_last_attempts ON first_last_attempts.userid = cquiza.userid";

    switch ($cquiz->grademethod) {
        case CQUIZ_ATTEMPTFIRST:
            // Because of the where clause, there will only be one row, but we
            // must still use an aggregate function.
            $select = 'MAX(cquiza.sumgrades)';
            $join = $firstlastattemptjoin;
            $where = 'cquiza.attempt = first_last_attempts.firstattempt AND';
            break;

        case CQUIZ_ATTEMPTLAST:
            // Because of the where clause, there will only be one row, but we
            // must still use an aggregate function.
            $select = 'MAX(cquiza.sumgrades)';
            $join = $firstlastattemptjoin;
            $where = 'cquiza.attempt = first_last_attempts.lastattempt AND';
            break;

        case CQUIZ_GRADEAVERAGE:
            $select = 'AVG(cquiza.sumgrades)';
            $join = '';
            $where = '';
            break;

        default:
        case CQUIZ_GRADEHIGHEST:
            $select = 'MAX(cquiza.sumgrades)';
            $join = '';
            $where = '';
            break;
    }

    if ($cquiz->sumgrades >= 0.000005) {
        $finalgrade = $select . ' * ' . ($cquiz->grade / $cquiz->sumgrades);
    } else {
        $finalgrade = '0';
    }
    $param['cquizid'] = $cquiz->id;
    $param['cquizid2'] = $cquiz->id;
    $param['cquizid3'] = $cquiz->id;
    $param['cquizid4'] = $cquiz->id;
    $param['statefinished'] = cquiz_attempt::FINISHED;
    $param['statefinished2'] = cquiz_attempt::FINISHED;
    $finalgradesubquery = "
            SELECT cquiza.userid, $finalgrade AS newgrade
            FROM {cquiz_attempts} cquiza
            $join
            WHERE
                $where
                cquiza.state = :statefinished AND
                cquiza.preview = 0 AND
                cquiza.cquiz = :cquizid3
            GROUP BY cquiza.userid";

    $changedgrades = $DB->get_records_sql("
            SELECT users.userid, qg.id, qg.grade, newgrades.newgrade

            FROM (
                SELECT userid
                FROM {cquiz_grades} qg
                WHERE cquiz = :cquizid
            UNION
                SELECT DISTINCT userid
                FROM {cquiz_attempts} cquiza2
                WHERE
                    cquiza2.state = :statefinished2 AND
                    cquiza2.preview = 0 AND
                    cquiza2.cquiz = :cquizid2
            ) users

            LEFT JOIN {cquiz_grades} qg ON qg.userid = users.userid AND qg.cquiz = :cquizid4

            LEFT JOIN (
                $finalgradesubquery
            ) newgrades ON newgrades.userid = users.userid

            WHERE
                ABS(newgrades.newgrade - qg.grade) > 0.000005 OR
                ((newgrades.newgrade IS NULL OR qg.grade IS NULL) AND NOT
                          (newgrades.newgrade IS NULL AND qg.grade IS NULL))",
            // The mess on the previous line is detecting where the value is
            // NULL in one column, and NOT NULL in the other, but SQL does
            // not have an XOR operator, and MS SQL server can't cope with
            // (newgrades.newgrade IS NULL) <> (qg.grade IS NULL).
            $param);

    $timenow = time();
    $todelete = array();
    foreach ($changedgrades as $changedgrade) {

        if (is_null($changedgrade->newgrade)) {
            $todelete[] = $changedgrade->userid;
        } else if (is_null($changedgrade->grade)) {
            $toinsert = new stdClass();
            $toinsert->cquiz = $cquiz->id;
            $toinsert->userid = $changedgrade->userid;
            $toinsert->timemodified = $timenow;
            $toinsert->grade = $changedgrade->newgrade;
            $DB->insert_record('cquiz_grades', $toinsert);
        } else {
            $toupdate = new stdClass();
            $toupdate->id = $changedgrade->id;
            $toupdate->grade = $changedgrade->newgrade;
            $toupdate->timemodified = $timenow;
            $DB->update_record('cquiz_grades', $toupdate);
        }
    }

    if (!empty($todelete)) {
        list($test, $params) = $DB->get_in_or_equal($todelete);
        $DB->delete_records_select('cquiz_grades', 'cquiz = ? AND userid ' . $test, array_merge(array($cquiz->id), $params));
    }
}

/**
 * Efficiently update check state time on all open attempts
 *
 * @param array $conditions optional restrictions on which attempts to update
 *                    Allowed conditions:
 *                      courseid => (array|int) attempts in given course(s)
 *                      userid   => (array|int) attempts for given user(s)
 *                      cquizid   => (array|int) attempts in given cquiz(s)
 *                      groupid  => (array|int) cquizzes with some override for given group(s)
 *
 */
function cquiz_update_open_attempts(array $conditions) {
    global $DB;

    foreach ($conditions as &$value) {
        if (!is_array($value)) {
            $value = array($value);
        }
    }

    $params = array();
    $wheres = array("cquiza.state IN ('inprogress', 'overdue')");
    $iwheres = array("icquiza.state IN ('inprogress', 'overdue')");

    if (isset($conditions['courseid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['courseid'], SQL_PARAMS_NAMED, 'cid');
        $params = array_merge($params, $inparams);
        $wheres[] = "cquiza.cquiz IN (SELECT q.id FROM {cquiz} q WHERE q.course $incond)";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['courseid'], SQL_PARAMS_NAMED, 'icid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "icquiza.cquiz IN (SELECT q.id FROM {cquiz} q WHERE q.course $incond)";
    }

    if (isset($conditions['userid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['userid'], SQL_PARAMS_NAMED, 'uid');
        $params = array_merge($params, $inparams);
        $wheres[] = "cquiza.userid $incond";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['userid'], SQL_PARAMS_NAMED, 'iuid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "icquiza.userid $incond";
    }

    if (isset($conditions['cquizid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['cquizid'], SQL_PARAMS_NAMED, 'qid');
        $params = array_merge($params, $inparams);
        $wheres[] = "cquiza.cquiz $incond";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['cquizid'], SQL_PARAMS_NAMED, 'iqid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "icquiza.cquiz $incond";
    }

    if (isset($conditions['groupid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['groupid'], SQL_PARAMS_NAMED, 'gid');
        $params = array_merge($params, $inparams);
        $wheres[] = "cquiza.cquiz IN (SELECT qo.cquiz FROM {cquiz_overrides} qo WHERE qo.groupid $incond)";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['groupid'], SQL_PARAMS_NAMED, 'igid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "icquiza.cquiz IN (SELECT qo.cquiz FROM {cquiz_overrides} qo WHERE qo.groupid $incond)";
    }

    // SQL to compute timeclose and timelimit for each attempt:
    $cquizausersql = cquiz_get_attempt_usertime_sql(
            implode("\n                AND ", $iwheres));

    // SQL to compute the new timecheckstate
    $timecheckstatesql = "
          CASE WHEN cquizauser.usertimelimit = 0 AND cquizauser.usertimeclose = 0 THEN NULL
               WHEN cquizauser.usertimelimit = 0 THEN cquizauser.usertimeclose
               WHEN cquizauser.usertimeclose = 0 THEN cquiza.timestart + cquizauser.usertimelimit
               WHEN cquiza.timestart + cquizauser.usertimelimit < cquizauser.usertimeclose THEN cquiza.timestart + cquizauser.usertimelimit
               ELSE cquizauser.usertimeclose END +
          CASE WHEN cquiza.state = 'overdue' THEN cquiz.graceperiod ELSE 0 END";

    // SQL to select which attempts to process
    $attemptselect = implode("\n                         AND ", $wheres);

    /*
     * Each database handles updates with inner joins differently:
     *  - mysql does not allow a FROM clause
     *  - postgres and mssql allow FROM but handle table aliases differently
     *  - oracle requires a subquery
     *
     * Different code for each database.
     */

    $dbfamily = $DB->get_dbfamily();
    if ($dbfamily == 'mysql') {
        $updatesql = "UPDATE {cquiz_attempts} cquiza
                        JOIN {cquiz} cquiz ON cquiz.id = cquiza.cquiz
                        JOIN ( $cquizausersql ) cquizauser ON cquizauser.id = cquiza.id
                         SET cquiza.timecheckstate = $timecheckstatesql
                       WHERE $attemptselect";
    } else if ($dbfamily == 'postgres') {
        $updatesql = "UPDATE {cquiz_attempts} cquiza
                         SET timecheckstate = $timecheckstatesql
                        FROM {cquiz} cquiz, ( $cquizausersql ) cquizauser
                       WHERE cquiz.id = cquiza.cquiz
                         AND cquizauser.id = cquiza.id
                         AND $attemptselect";
    } else if ($dbfamily == 'mssql') {
        $updatesql = "UPDATE cquiza
                         SET timecheckstate = $timecheckstatesql
                        FROM {cquiz_attempts} cquiza
                        JOIN {cquiz} cquiz ON cquiz.id = cquiza.cquiz
                        JOIN ( $cquizausersql ) cquizauser ON cquizauser.id = cquiza.id
                       WHERE $attemptselect";
    } else {
        // oracle, sqlite and others
        $updatesql = "UPDATE {cquiz_attempts} cquiza
                         SET timecheckstate = (
                           SELECT $timecheckstatesql
                             FROM {cquiz} cquiz, ( $cquizausersql ) cquizauser
                            WHERE cquiz.id = cquiza.cquiz
                              AND cquizauser.id = cquiza.id
                         )
                         WHERE $attemptselect";
    }

    $DB->execute($updatesql, $params);
}

/**
 * Returns SQL to compute timeclose and timelimit for every attempt, taking into account user and group overrides.
 *
 * @param string $redundantwhereclauses extra where clauses to add to the subquery
 *      for performance. These can use the table alias icquiza for the cquiz attempts table.
 * @return string SQL select with columns attempt.id, usertimeclose, usertimelimit.
 */
function cquiz_get_attempt_usertime_sql($redundantwhereclauses = '') {
    if ($redundantwhereclauses) {
        $redundantwhereclauses = 'WHERE ' . $redundantwhereclauses;
    }
    // The multiple qgo JOINS are necessary because we want timeclose/timelimit = 0 (unlimited) to supercede
    // any other group override
    $cquizausersql = "
          SELECT icquiza.id,
           COALESCE(MAX(quo.timeclose), MAX(qgo1.timeclose), MAX(qgo2.timeclose), icquiz.timeclose) AS usertimeclose,
           COALESCE(MAX(quo.timelimit), MAX(qgo3.timelimit), MAX(qgo4.timelimit), icquiz.timelimit) AS usertimelimit

           FROM {cquiz_attempts} icquiza
           JOIN {cquiz} icquiz ON icquiz.id = icquiza.cquiz
      LEFT JOIN {cquiz_overrides} quo ON quo.cquiz = icquiza.cquiz AND quo.userid = icquiza.userid
      LEFT JOIN {groups_members} gm ON gm.userid = icquiza.userid
      LEFT JOIN {cquiz_overrides} qgo1 ON qgo1.cquiz = icquiza.cquiz AND qgo1.groupid = gm.groupid AND qgo1.timeclose = 0
      LEFT JOIN {cquiz_overrides} qgo2 ON qgo2.cquiz = icquiza.cquiz AND qgo2.groupid = gm.groupid AND qgo2.timeclose > 0
      LEFT JOIN {cquiz_overrides} qgo3 ON qgo3.cquiz = icquiza.cquiz AND qgo3.groupid = gm.groupid AND qgo3.timelimit = 0
      LEFT JOIN {cquiz_overrides} qgo4 ON qgo4.cquiz = icquiza.cquiz AND qgo4.groupid = gm.groupid AND qgo4.timelimit > 0
          $redundantwhereclauses
       GROUP BY icquiza.id, icquiz.id, icquiz.timeclose, icquiz.timelimit";
    return $cquizausersql;
}

/**
 * Return the attempt with the best grade for a cquiz
 *
 * Which attempt is the best depends on $cquiz->grademethod. If the grade
 * method is GRADEAVERAGE then this function simply returns the last attempt.
 * @return object         The attempt with the best grade
 * @param object $cquiz    The cquiz for which the best grade is to be calculated
 * @param array $attempts An array of all the attempts of the user at the cquiz
 */
function cquiz_calculate_best_attempt($cquiz, $attempts) {

    switch ($cquiz->grademethod) {

        case CQUIZ_ATTEMPTFIRST:
            foreach ($attempts as $attempt) {
                return $attempt;
            }
            break;

        case CQUIZ_GRADEAVERAGE: // We need to do something with it.
        case CQUIZ_ATTEMPTLAST:
            foreach ($attempts as $attempt) {
                $final = $attempt;
            }
            return $final;

        default:
        case CQUIZ_GRADEHIGHEST:
            $max = -1;
            foreach ($attempts as $attempt) {
                if ($attempt->sumgrades > $max) {
                    $max = $attempt->sumgrades;
                    $maxattempt = $attempt;
                }
            }
            return $maxattempt;
    }
}

/**
 * @return array int => lang string the options for calculating the cquiz grade
 *      from the individual attempt grades.
 */
function cquiz_get_grading_options() {
    return array(
        CQUIZ_GRADEHIGHEST => get_string('gradehighest', 'cquiz'),
        CQUIZ_GRADEAVERAGE => get_string('gradeaverage', 'cquiz'),
        CQUIZ_ATTEMPTFIRST => get_string('attemptfirst', 'cquiz'),
        CQUIZ_ATTEMPTLAST => get_string('attemptlast', 'cquiz')
    );
}

/**
 * @param int $option one of the values CQUIZ_GRADEHIGHEST, CQUIZ_GRADEAVERAGE,
 *      CQUIZ_ATTEMPTFIRST or CQUIZ_ATTEMPTLAST.
 * @return the lang string for that option.
 */
function cquiz_get_grading_option_name($option) {
    $strings = cquiz_get_grading_options();
    return $strings[$option];
}

/**
 * @return array string => lang string the options for handling overdue cquiz
 *      attempts.
 */
function cquiz_get_overdue_handling_options() {
    return array(
        'autosubmit' => get_string('overduehandlingautosubmit', 'cquiz'),
        'graceperiod' => get_string('overduehandlinggraceperiod', 'cquiz'),
        'autoabandon' => get_string('overduehandlingautoabandon', 'cquiz'),
    );
}

/**
 * Get the choices for what size user picture to show.
 * @return array string => lang string the options for whether to display the user's picture.
 */
function cquiz_get_user_image_options() {
    return array(
        CQUIZ_SHOWIMAGE_NONE => get_string('shownoimage', 'cquiz'),
        CQUIZ_SHOWIMAGE_SMALL => get_string('showsmallimage', 'cquiz'),
        CQUIZ_SHOWIMAGE_LARGE => get_string('showlargeimage', 'cquiz'),
    );
}

/**
 * Get the choices to offer for the 'Questions per page' option.
 * @return array int => string.
 */
function cquiz_questions_per_page_options() {
    $pageoptions = array();
    $pageoptions[0] = get_string('neverallononepage', 'cquiz');
    $pageoptions[1] = get_string('everyquestion', 'cquiz');
    for ($i = 2; $i <= CQUIZ_MAX_QPP_OPTION; ++$i) {
        $pageoptions[$i] = get_string('everynquestions', 'cquiz', $i);
    }
    return $pageoptions;
}

/**
 * Get the human-readable name for a cquiz attempt state.
 * @param string $state one of the state constants like {@link cquiz_attempt::IN_PROGRESS}.
 * @return string The lang string to describe that state.
 */
function cquiz_attempt_state_name($state) {
    switch ($state) {
        case cquiz_attempt::IN_PROGRESS:
            return get_string('stateinprogress', 'cquiz');
        case cquiz_attempt::OVERDUE:
            return get_string('stateoverdue', 'cquiz');
        case cquiz_attempt::FINISHED:
            return get_string('statefinished', 'cquiz');
        case cquiz_attempt::ABANDONED:
            return get_string('stateabandoned', 'cquiz');
        default:
            throw new coding_exception('Unknown cquiz attempt state.');
    }
}

// Other cquiz functions ////////////////////////////////////////////////////////

/**
 * @param object $cquiz the cquiz.
 * @param int $cmid the course_module object for this cquiz.
 * @param object $question the question.
 * @param string $returnurl url to return to after action is done.
 * @param int $variant which question variant to preview (optional).
 * @return string html for a number of icons linked to action pages for a
 * question - preview and edit / view icons depending on user capabilities.
 */
function cquiz_question_action_icons($cquiz, $cmid, $question, $returnurl, $variant = null) {
    $html = cquiz_question_preview_button($cquiz, $question, false, $variant) . ' ' .
            cquiz_question_edit_button($cmid, $question, $returnurl);
    return $html;
}

/**
 * @param int $cmid the course_module.id for this cquiz.
 * @param object $question the question.
 * @param string $returnurl url to return to after action is done.
 * @param string $contentbeforeicon some HTML content to be added inside the link, before the icon.
 * @return the HTML for an edit icon, view icon, or nothing for a question
 *      (depending on permissions).
 */
function cquiz_question_edit_button($cmid, $question, $returnurl, $contentaftericon = '') {
    global $CFG, $OUTPUT;

    // Minor efficiency saving. Only get strings once, even if there are a lot of icons on one page.
    static $stredit = null;
    static $strview = null;
    if ($stredit === null) {
        $stredit = get_string('edit');
        $strview = get_string('view');
    }

    // What sort of icon should we show?
    $action = '';
    if (!empty($question->id) &&
            (question_has_capability_on($question, 'edit', $question->category) ||
            question_has_capability_on($question, 'move', $question->category))) {
        $action = $stredit;
        $icon = '/t/edit';
    } else if (!empty($question->id) &&
            question_has_capability_on($question, 'view', $question->category)) {
        $action = $strview;
        $icon = '/i/info';
    }

    // Build the icon.
    if ($action) {
        if ($returnurl instanceof moodle_url) {
            $returnurl = $returnurl->out_as_local_url(false);
        }
        $questionparams = array('returnurl' => $returnurl, 'cmid' => $cmid, 'id' => $question->id);
        $questionurl = new moodle_url("$CFG->wwwroot/question/edit.php", $questionparams);
        return '<a title="' . $action . '" href="' . $questionurl->out() . '" class="questioneditbutton">' .
                $OUTPUT->pix_icon($icon, $action) . $contentaftericon .
                '</a>';
    } else if ($contentaftericon) {
        return '<span class="questioneditbutton">' . $contentaftericon . '</span>';
    } else {
        return '';
    }
}

/**
 * @param object $cquiz the cquiz settings
 * @param object $question the question
 * @param int $variant which question variant to preview (optional).
 * @return moodle_url to preview this question with the options from this cquiz.
 */
function cquiz_question_preview_url($cquiz, $question, $variant = null) {
    // Get the appropriate display options.
    $displayoptions = mod_cquiz_display_options::make_from_cquiz($cquiz, mod_cquiz_display_options::DURING);

    $maxmark = null;
    if (isset($question->maxmark)) {
        $maxmark = $question->maxmark;
    }

    // Work out the correcte preview URL.
    return question_preview_url($question->id, $cquiz->preferredbehaviour, $maxmark, $displayoptions, $variant);
}

/**
 * @param object $cquiz the cquiz settings
 * @param object $question the question
 * @param bool $label if true, show the preview question label after the icon
 * @param int $variant which question variant to preview (optional).
 * @return the HTML for a preview question icon.
 */
function cquiz_question_preview_button($cquiz, $question, $label = false, $variant = null) {
    global $PAGE;
    if (!question_has_capability_on($question, 'use', $question->category)) {
        return '';
    }

    return $PAGE->get_renderer('mod_cquiz', 'edit')->question_preview_icon($cquiz, $question, $label, $variant);
}

/**
 * @param object $attempt the attempt.
 * @param object $context the cquiz context.
 * @return int whether flags should be shown/editable to the current user for this attempt.
 */
function cquiz_get_flag_option($attempt, $context) {
    global $USER;
    if (!has_capability('moodle/question:flag', $context)) {
        return question_display_options::HIDDEN;
    } else if ($attempt->userid == $USER->id) {
        return question_display_options::EDITABLE;
    } else {
        return question_display_options::VISIBLE;
    }
}

/**
 * Work out what state this cquiz attempt is in - in the sense used by
 * cquiz_get_review_options, not in the sense of $attempt->state.
 * @param object $cquiz the cquiz settings
 * @param object $attempt the cquiz_attempt database row.
 * @return int one of the mod_cquiz_display_options::DURING,
 *      IMMEDIATELY_AFTER, LATER_WHILE_OPEN or AFTER_CLOSE constants.
 */
function cquiz_attempt_state($cquiz, $attempt) {
    if ($attempt->state == cquiz_attempt::IN_PROGRESS) {
        return mod_cquiz_display_options::DURING;
    } else if (time() < $attempt->timefinish + 120) {
        return mod_cquiz_display_options::IMMEDIATELY_AFTER;
    } else if (!$cquiz->timeclose || time() < $cquiz->timeclose) {
        return mod_cquiz_display_options::LATER_WHILE_OPEN;
    } else {
        return mod_cquiz_display_options::AFTER_CLOSE;
    }
}

/**
 * The the appropraite mod_cquiz_display_options object for this attempt at this
 * cquiz right now.
 *
 * @param object $cquiz the cquiz instance.
 * @param object $attempt the attempt in question.
 * @param $context the cquiz context.
 *
 * @return mod_cquiz_display_options
 */
function cquiz_get_review_options($cquiz, $attempt, $context) {
    $options = mod_cquiz_display_options::make_from_cquiz($cquiz, cquiz_attempt_state($cquiz, $attempt));

    $options->readonly = true;
    $options->flags = cquiz_get_flag_option($attempt, $context);
    if (!empty($attempt->id)) {
        $options->questionreviewlink = new moodle_url('/mod/cquiz/reviewquestion.php', array('attempt' => $attempt->id));
    }

    // Show a link to the comment box only for closed attempts.
    if (!empty($attempt->id) && $attempt->state == cquiz_attempt::FINISHED && !$attempt->preview &&
            !is_null($context) && has_capability('mod/cquiz:grade', $context)) {
        $options->manualcomment = question_display_options::VISIBLE;
        $options->manualcommentlink = new moodle_url('/mod/cquiz/comment.php', array('attempt' => $attempt->id));
    }

    if (!is_null($context) && !$attempt->preview &&
            has_capability('mod/cquiz:viewreports', $context) &&
            has_capability('moodle/grade:viewhidden', $context)) {
        // People who can see reports and hidden grades should be shown everything,
        // except during preview when teachers want to see what students see.
        $options->attempt = question_display_options::VISIBLE;
        $options->correctness = question_display_options::VISIBLE;
        $options->marks = question_display_options::MARK_AND_MAX;
        $options->feedback = question_display_options::VISIBLE;
        $options->numpartscorrect = question_display_options::VISIBLE;
        $options->manualcomment = question_display_options::VISIBLE;
        $options->generalfeedback = question_display_options::VISIBLE;
        $options->rightanswer = question_display_options::VISIBLE;
        $options->overallfeedback = question_display_options::VISIBLE;
        $options->history = question_display_options::VISIBLE;
    }

    return $options;
}

/**
 * Combines the review options from a number of different cquiz attempts.
 * Returns an array of two ojects, so the suggested way of calling this
 * funciton is:
 * list($someoptions, $alloptions) = cquiz_get_combined_reviewoptions(...)
 *
 * @param object $cquiz the cquiz instance.
 * @param array $attempts an array of attempt objects.
 *
 * @return array of two options objects, one showing which options are true for
 *          at least one of the attempts, the other showing which options are true
 *          for all attempts.
 */
function cquiz_get_combined_reviewoptions($cquiz, $attempts) {
    $fields = array('feedback', 'generalfeedback', 'rightanswer', 'overallfeedback');
    $someoptions = new stdClass();
    $alloptions = new stdClass();
    foreach ($fields as $field) {
        $someoptions->$field = false;
        $alloptions->$field = true;
    }
    $someoptions->marks = question_display_options::HIDDEN;
    $alloptions->marks = question_display_options::MARK_AND_MAX;

    // This shouldn't happen, but we need to prevent reveal information.
    if (empty($attempts)) {
        return array($someoptions, $someoptions);
    }

    foreach ($attempts as $attempt) {
        $attemptoptions = mod_cquiz_display_options::make_from_cquiz($cquiz, cquiz_attempt_state($cquiz, $attempt));
        foreach ($fields as $field) {
            $someoptions->$field = $someoptions->$field || $attemptoptions->$field;
            $alloptions->$field = $alloptions->$field && $attemptoptions->$field;
        }
        $someoptions->marks = max($someoptions->marks, $attemptoptions->marks);
        $alloptions->marks = min($alloptions->marks, $attemptoptions->marks);
    }
    return array($someoptions, $alloptions);
}

// Functions for sending notification messages /////////////////////////////////

/**
 * Sends a confirmation message to the student confirming that the attempt was processed.
 *
 * @param object $a lots of useful information that can be used in the message
 *      subject and body.
 *
 * @return int|false as for {@link message_send()}.
 */
function cquiz_send_confirmation($recipient, $a) {

    // Add information about the recipient to $a.
    // Don't do idnumber. we want idnumber to be the submitter's idnumber.
    $a->username = fullname($recipient);
    $a->userusername = $recipient->username;

    // Prepare the message.
    $message = new message();
    $message->component = 'mod_cquiz';
    $message->name = 'confirmation';
    $message->notification = 1;

    $message->userfrom = core_user::get_noreply_user();
    $message->userto = $recipient;
    $message->subject = get_string('emailconfirmsubject', 'cquiz', $a);
    $message->fullmessage = get_string('emailconfirmbody', 'cquiz', $a);
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml = '';

    $message->smallmessage = get_string('emailconfirmsmall', 'cquiz', $a);
    $message->contexturl = $a->cquizurl;
    $message->contexturlname = $a->cquizname;

    // ... and send it.
    return message_send($message);
}

/**
 * Sends a message to the student informing that a grade has been earned.
 *
 * @param object $a Lots of useful information that can be used in the message
 *      subject and body.
 *
 * @return int|false as for {@link message_send()}.
 */
function cquiz_send_grade_message($recipient, $a) {

    // Add information about the recipient to $cquiz.
    $a->username = fullname($recipient);
    $a->userusername = $recipient->username;

    // Prepare the message.
    $message = new message();
    $message->component = 'mod_cquiz';
    $message->name = 'grade_earned';
    $message->notification = 1;

    $message->userfrom = core_user::get_noreply_user();
    $message->userto = $recipient;
    if (isset($a->previousgrade)) {
        $message->subject = get_string('emailgradeimprovedsubject', 'cquiz', $a);
        $message->fullmessage = get_string('emailgradeimprovedbody', 'cquiz', $a);
        $message->smallmessage = get_string('emailgradeimprovedsmall', 'cquiz', $a);
    } else {
        $message->subject = get_string('emailgradeearnedsubject', 'cquiz', $a);
        $message->fullmessage = get_string('emailgradeearnedbody', 'cquiz', $a);
        $message->smallmessage = get_string('emailgradeearnedsmall', 'cquiz', $a);
    }
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml = '';

    $message->contexturl = isset($a->cquizurl) ? $a->cquizurl : null;
    $message->contexturlname = $a->cquizname;

    // ... and send it.
    return message_send($message);
}

/**
 * Sends notification messages to the interested parties that assign the role capability
 *
 * @param object $recipient user object of the intended recipient
 * @param object $a associative array of replaceable fields for the templates
 *
 * @return int|false as for {@link message_send()}.
 */
function cquiz_send_notification($recipient, $submitter, $a) {

    // Recipient info for template.
    $a->useridnumber = $recipient->idnumber;
    $a->username = fullname($recipient);
    $a->userusername = $recipient->username;

    // Prepare the message.
    $message = new message();
    $message->component = 'mod_cquiz';
    $message->name = 'submission';
    $message->notification = 1;

    $message->userfrom = $submitter;
    $message->userto = $recipient;
    $message->subject = get_string('emailnotifysubject', 'cquiz', $a);
    $message->fullmessage = get_string('emailnotifybody', 'cquiz', $a);
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml = '';

    $message->smallmessage = get_string('emailnotifysmall', 'cquiz', $a);
    $message->contexturl = $a->cquizreviewurl;
    $message->contexturlname = $a->cquizname;

    // ... and send it.
    return message_send($message);
}

/**
 * Send all the requried messages when a cquiz attempt is submitted.
 *
 * @param object $course the course
 * @param object $cquiz the cquiz
 * @param object $attempt this attempt just finished
 * @param object $context the cquiz context
 * @param object $cm the coursemodule for this cquiz
 *
 * @return bool true if all necessary messages were sent successfully, else false.
 */
function cquiz_send_notification_messages($course, $cquiz, $attempt, $context, $cm) {
    global $CFG, $DB;

    // Do nothing if required objects not present.
    if (empty($course) or empty($cquiz) or empty($attempt) or empty($context)) {
        throw new coding_exception('$course, $cquiz, $attempt, $context and $cm must all be set.');
    }

    $submitter = $DB->get_record('user', array('id' => $attempt->userid), '*', MUST_EXIST);

    // Check for confirmation required.
    $sendconfirm = false;
    $notifyexcludeusers = '';
    if (has_capability('mod/cquiz:emailconfirmsubmission', $context, $submitter, false)) {
        $notifyexcludeusers = $submitter->id;
        $sendconfirm = true;
    }

    // Check for notifications required.
    $notifyfields = 'u.id, u.username, u.idnumber, u.email, u.emailstop, u.lang,
            u.timezone, u.mailformat, u.maildisplay, u.auth, u.suspended, u.deleted, ';
    $notifyfields .= get_all_user_name_fields(true, 'u');
    $groups = groups_get_all_groups($course->id, $submitter->id, $cm->groupingid);
    if (is_array($groups) && count($groups) > 0) {
        $groups = array_keys($groups);
    } else if (groups_get_activity_groupmode($cm, $course) != NOGROUPS) {
        // If the user is not in a group, and the cquiz is set to group mode,
        // then set $groups to a non-existant id so that only users with
        // 'moodle/site:accessallgroups' get notified.
        $groups = -1;
    } else {
        $groups = '';
    }
    $userstonotify = get_users_by_capability($context, 'mod/cquiz:emailnotifysubmission', $notifyfields, '', '', '', $groups, $notifyexcludeusers, false, false, true);

    if (empty($userstonotify) && !$sendconfirm) {
        return true; // Nothing to do.
    }

    $a = new stdClass();
    // Course info.
    $a->coursename = $course->fullname;
    $a->courseshortname = $course->shortname;
    // Cquiz info.
    $a->cquizname = $cquiz->name;
    $a->cquizreporturl = $CFG->wwwroot . '/mod/cquiz/report.php?id=' . $cm->id;
    $a->cquizreportlink = '<a href="' . $a->cquizreporturl . '">' .
            format_string($cquiz->name) . ' report</a>';
    $a->cquizurl = $CFG->wwwroot . '/mod/cquiz/view.php?id=' . $cm->id;
    $a->cquizlink = '<a href="' . $a->cquizurl . '">' . format_string($cquiz->name) . '</a>';
    // Attempt info.
    $a->submissiontime = userdate($attempt->timefinish);
    $a->timetaken = format_time($attempt->timefinish - $attempt->timestart);
    $a->cquizreviewurl = $CFG->wwwroot . '/mod/cquiz/review.php?attempt=' . $attempt->id;
    $a->cquizreviewlink = '<a href="' . $a->cquizreviewurl . '">' .
            format_string($cquiz->name) . ' review</a>';
    // Student who sat the cquiz info.
    $a->studentidnumber = $submitter->idnumber;
    $a->studentname = fullname($submitter);
    $a->studentusername = $submitter->username;

    $allok = true;

    // Send notifications if required.
    if (!empty($userstonotify)) {
        foreach ($userstonotify as $recipient) {
            $allok = $allok && cquiz_send_notification($recipient, $submitter, $a);
        }
    }

    // Send confirmation if required. We send the student confirmation last, so
    // that if message sending is being intermittently buggy, which means we send
    // some but not all messages, and then try again later, then teachers may get
    // duplicate messages, but the student will always get exactly one.
    if ($sendconfirm) {
        $allok = $allok && cquiz_send_confirmation($submitter, $a);
    }

    return $allok;
}

/**
 * Send the notification message when a cquiz attempt becomes overdue.
 *
 * @param cquiz_attempt $attemptobj all the data about the cquiz attempt.
 */
function cquiz_send_overdue_message($attemptobj) {
    global $CFG, $DB;

    $submitter = $DB->get_record('user', array('id' => $attemptobj->get_userid()), '*', MUST_EXIST);

    if (!$attemptobj->has_capability('mod/cquiz:emailwarnoverdue', $submitter->id, false)) {
        return; // Message not required.
    }

    if (!$attemptobj->has_response_to_at_least_one_graded_question()) {
        return; // Message not required.
    }

    // Prepare lots of useful information that admins might want to include in
    // the email message.
    $cquizname = format_string($attemptobj->get_cquiz_name());

    $deadlines = array();
    if ($attemptobj->get_cquiz()->timelimit) {
        $deadlines[] = $attemptobj->get_attempt()->timestart + $attemptobj->get_cquiz()->timelimit;
    }
    if ($attemptobj->get_cquiz()->timeclose) {
        $deadlines[] = $attemptobj->get_cquiz()->timeclose;
    }
    $duedate = min($deadlines);
    $graceend = $duedate + $attemptobj->get_cquiz()->graceperiod;

    $a = new stdClass();
    // Course info.
    $a->coursename = format_string($attemptobj->get_course()->fullname);
    $a->courseshortname = format_string($attemptobj->get_course()->shortname);
    // Cquiz info.
    $a->cquizname = $cquizname;
    $a->cquizurl = $attemptobj->view_url();
    $a->cquizlink = '<a href="' . $a->cquizurl . '">' . $cquizname . '</a>';
    // Attempt info.
    $a->attemptduedate = userdate($duedate);
    $a->attemptgraceend = userdate($graceend);
    $a->attemptsummaryurl = $attemptobj->summary_url()->out(false);
    $a->attemptsummarylink = '<a href="' . $a->attemptsummaryurl . '">' . $cquizname . ' review</a>';
    // Student's info.
    $a->studentidnumber = $submitter->idnumber;
    $a->studentname = fullname($submitter);
    $a->studentusername = $submitter->username;

    // Prepare the message.
    $message = new message();
    $message->component = 'mod_cquiz';
    $message->name = 'attempt_overdue';
    $message->notification = 1;

    $message->userfrom = core_user::get_noreply_user();
    $message->userto = $submitter;
    $message->subject = get_string('emailoverduesubject', 'cquiz', $a);
    $message->fullmessage = get_string('emailoverduebody', 'cquiz', $a);
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml = '';

    $message->smallmessage = get_string('emailoverduesmall', 'cquiz', $a);
    $message->contexturl = $a->cquizurl;
    $message->contexturlname = $a->cquizname;

    // Send the message.
    return message_send($message);
}

/**
 * Handle the cquiz_attempt_submitted event.
 *
 * This sends the confirmation and notification messages, if required.
 *
 * @param object $event the event object.
 */
function cquiz_attempt_submitted_handler($event) {
    global $DB;

    $course = $DB->get_record('course', array('id' => $event->courseid));
    $attempt = $event->get_record_snapshot('cquiz_attempts', $event->objectid);
    $cquiz = $event->get_record_snapshot('cquiz', $attempt->cquiz);
    $cm = get_coursemodule_from_id('cquiz', $event->get_context()->instanceid, $event->courseid);

    if (!($course && $cquiz && $cm && $attempt)) {
        // Something has been deleted since the event was raised. Therefore, the
        // event is no longer relevant.
        return true;
    }

    // Update completion state.
    $completion = new completion_info($course);
    if ($completion->is_enabled($cm) && ($cquiz->completionattemptsexhausted || $cquiz->completionpass)) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $event->userid);
    }
    return cquiz_send_notification_messages($course, $cquiz, $attempt, context_module::instance($cm->id), $cm);
}

/**
 * Handle groups_member_added event
 *
 * @param object $event the event object.
 * @deprecated since 2.6, see {@link \mod_cquiz\group_observers::group_member_added()}.
 */
function cquiz_groups_member_added_handler($event) {
    debugging('cquiz_groups_member_added_handler() is deprecated, please use ' .
            '\mod_cquiz\group_observers::group_member_added() instead.', DEBUG_DEVELOPER);
    cquiz_update_open_attempts(array('userid' => $event->userid, 'groupid' => $event->groupid));
}

/**
 * Handle groups_member_removed event
 *
 * @param object $event the event object.
 * @deprecated since 2.6, see {@link \mod_cquiz\group_observers::group_member_removed()}.
 */
function cquiz_groups_member_removed_handler($event) {
    debugging('cquiz_groups_member_removed_handler() is deprecated, please use ' .
            '\mod_cquiz\group_observers::group_member_removed() instead.', DEBUG_DEVELOPER);
    cquiz_update_open_attempts(array('userid' => $event->userid, 'groupid' => $event->groupid));
}

/**
 * Handle groups_group_deleted event
 *
 * @param object $event the event object.
 * @deprecated since 2.6, see {@link \mod_cquiz\group_observers::group_deleted()}.
 */
function cquiz_groups_group_deleted_handler($event) {
    global $DB;
    debugging('cquiz_groups_group_deleted_handler() is deprecated, please use ' .
            '\mod_cquiz\group_observers::group_deleted() instead.', DEBUG_DEVELOPER);
    cquiz_process_group_deleted_in_course($event->courseid);
}

/**
 * Logic to happen when a/some group(s) has/have been deleted in a course.
 *
 * @param int $courseid The course ID.
 * @return void
 */
function cquiz_process_group_deleted_in_course($courseid) {
    global $DB;

    // It would be nice if we got the groupid that was deleted.
    // Instead, we just update all cquizzes with orphaned group overrides.
    $sql = "SELECT o.id, o.cquiz
              FROM {cquiz_overrides} o
              JOIN {cquiz} cquiz ON cquiz.id = o.cquiz
         LEFT JOIN {groups} grp ON grp.id = o.groupid
             WHERE cquiz.course = :courseid
               AND o.groupid IS NOT NULL
               AND grp.id IS NULL";
    $params = array('courseid' => $courseid);
    $records = $DB->get_records_sql_menu($sql, $params);
    if (!$records) {
        return; // Nothing to do.
    }
    $DB->delete_records_list('cquiz_overrides', 'id', array_keys($records));
    cquiz_update_open_attempts(array('cquizid' => array_unique(array_values($records))));
}

/**
 * Handle groups_members_removed event
 *
 * @param object $event the event object.
 * @deprecated since 2.6, see {@link \mod_cquiz\group_observers::group_member_removed()}.
 */
function cquiz_groups_members_removed_handler($event) {
    debugging('cquiz_groups_members_removed_handler() is deprecated, please use ' .
            '\mod_cquiz\group_observers::group_member_removed() instead.', DEBUG_DEVELOPER);
    if ($event->userid == 0) {
        cquiz_update_open_attempts(array('courseid' => $event->courseid));
    } else {
        cquiz_update_open_attempts(array('courseid' => $event->courseid, 'userid' => $event->userid));
    }
}

/**
 * Get the information about the standard cquiz JavaScript module.
 * @return array a standard jsmodule structure.
 */
function cquiz_get_js_module() {
    global $PAGE;

    return array(
        'name' => 'mod_cquiz',
        'fullpath' => '/mod/cquiz/module.js',
        'requires' => array('base', 'dom', 'event-delegate', 'event-key',
            'core_question_engine', 'moodle-core-formchangechecker'),
        'strings' => array(
            array('cancel', 'moodle'),
            array('flagged', 'question'),
            array('functiondisabledbysecuremode', 'cquiz'),
            array('startattempt', 'cquiz'),
            array('timesup', 'cquiz'),
            array('changesmadereallygoaway', 'moodle'),
        ),
    );
}

/**
 * An extension of question_display_options that includes the extra options used
 * by the cquiz.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class mod_cquiz_display_options extends question_display_options {
    /*     * #@+
     * @var integer bits used to indicate various times in relation to a
     * cquiz attempt.
     */

    const DURING = 0x10000;
    const IMMEDIATELY_AFTER = 0x01000;
    const LATER_WHILE_OPEN = 0x00100;
    const AFTER_CLOSE = 0x00010;

    /*     * #@- */

    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $attempt = true;

    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $overallfeedback = self::VISIBLE;

    /**
     * Set up the various options from the cquiz settings, and a time constant.
     * @param object $cquiz the cquiz settings.
     * @param int $one of the {@link DURING}, {@link IMMEDIATELY_AFTER},
     * {@link LATER_WHILE_OPEN} or {@link AFTER_CLOSE} constants.
     * @return mod_cquiz_display_options set up appropriately.
     */
    public static function make_from_cquiz($cquiz, $when) {
        $options = new self();

        $options->attempt = self::extract($cquiz->reviewattempt, $when, true, false);
        $options->correctness = self::extract($cquiz->reviewcorrectness, $when);
        $options->marks = self::extract($cquiz->reviewmarks, $when, self::MARK_AND_MAX, self::MAX_ONLY);
        $options->feedback = self::extract($cquiz->reviewspecificfeedback, $when);
        $options->generalfeedback = self::extract($cquiz->reviewgeneralfeedback, $when);
        $options->rightanswer = self::extract($cquiz->reviewrightanswer, $when);
        $options->overallfeedback = self::extract($cquiz->reviewoverallfeedback, $when);

        $options->numpartscorrect = $options->feedback;
        $options->manualcomment = $options->feedback;

        if ($cquiz->questiondecimalpoints != -1) {
            $options->markdp = $cquiz->questiondecimalpoints;
        } else {
            $options->markdp = $cquiz->decimalpoints;
        }

        return $options;
    }

    protected static function extract($bitmask, $bit, $whenset = self::VISIBLE, $whennotset = self::HIDDEN) {
        if ($bitmask & $bit) {
            return $whenset;
        } else {
            return $whennotset;
        }
    }

}

/**
 * A {@link qubaid_condition} for finding all the question usages belonging to
 * a particular cquiz.
 *
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class qubaids_for_cquiz extends qubaid_join {

    public function __construct($cquizid, $includepreviews = true, $onlyfinished = false) {
        $where = 'cquiza.cquiz = :cquizacquiz';
        $params = array('cquizacquiz' => $cquizid);

        if (!$includepreviews) {
            $where .= ' AND preview = 0';
        }

        if ($onlyfinished) {
            $where .= ' AND state == :statefinished';
            $params['statefinished'] = cquiz_attempt::FINISHED;
        }

        parent::__construct('{cquiz_attempts} cquiza', 'cquiza.uniqueid', $where, $params);
    }

}

/**
 * Creates a textual representation of a question for display.
 *
 * @param object $question A question object from the database questions table
 * @param bool $showicon If true, show the question's icon with the question. False by default.
 * @param bool $showquestiontext If true (default), show question text after question name.
 *       If false, show only question name.
 * @return string
 */
function cquiz_question_tostring($question, $showicon = false, $showquestiontext = true) {
    $result = '';

    $name = shorten_text(format_string($question->name), 200);
    if ($showicon) {
        $name .= print_question_icon($question) . ' ' . $name;
    }
    $result .= html_writer::span($name, 'questionname');

    if ($showquestiontext) {
        $questiontext = question_utils::to_plain_text($question->questiontext, $question->questiontextformat, array('noclean' => true, 'para' => false));
        $questiontext = shorten_text($questiontext, 200);
        if ($questiontext) {
            $result .= ' ' . html_writer::span(s($questiontext), 'questiontext');
        }
    }

    return $result;
}

/**
 * Verify that the question exists, and the user has permission to use it.
 * Does not return. Throws an exception if the question cannot be used.
 * @param int $questionid The id of the question.
 */
function cquiz_require_question_use($questionid) {
    global $DB;
    $question = $DB->get_record('question', array('id' => $questionid), '*', MUST_EXIST);
    question_require_capability_on($question, 'use');
}

/**
 * Verify that the question exists, and the user has permission to use it.
 * @param object $cquiz the cquiz settings.
 * @param int $slot which question in the cquiz to test.
 * @return bool whether the user can use this question.
 */
function cquiz_has_question_use($cquiz, $slot) {
    global $DB;
    $question = $DB->get_record_sql("
            SELECT q.*
              FROM {cquiz_slots} slot
              JOIN {question} q ON q.id = slot.questionid
             WHERE slot.cquizid = ? AND slot.slot = ?", array($cquiz->id, $slot));
    if (!$question) {
        return false;
    }
    return question_has_capability_on($question, 'use');
}

/**
 * Add a question to a cquiz
 *
 * Adds a question to a cquiz by updating $cquiz as well as the
 * cquiz and cquiz_slots tables. It also adds a page break if required.
 * @param int $questionid The id of the question to be added
 * @param object $cquiz The extended cquiz object as used by edit.php
 *      This is updated by this function
 * @param int $page Which page in cquiz to add the question on. If 0 (default),
 *      add at the end
 * @param float $maxmark The maximum mark to set for this question. (Optional,
 *      defaults to question.defaultmark.
 * @return bool false if the question was already in the cquiz
 */
function cquiz_add_cquiz_question($questionid, $cquiz, $page = 0, $maxmark = null) {
    global $DB;
    $slots = $DB->get_records('cquiz_slots', array('cquizid' => $cquiz->id), 'slot', 'questionid, slot, page, id');
    if (array_key_exists($questionid, $slots)) {
        return false;
    }

    $trans = $DB->start_delegated_transaction();

    $maxpage = 1;
    $numonlastpage = 0;
    foreach ($slots as $slot) {
        if ($slot->page > $maxpage) {
            $maxpage = $slot->page;
            $numonlastpage = 1;
        } else {
            $numonlastpage += 1;
        }
    }

    // Add the new question instance.
    $slot = new stdClass();
    $slot->cquizid = $cquiz->id;
    $slot->questionid = $questionid;

    if ($maxmark !== null) {
        $slot->maxmark = $maxmark;
    } else {
        $slot->maxmark = $DB->get_field('question', 'defaultmark', array('id' => $questionid));
    }

    if (is_int($page) && $page >= 1) {
        // Adding on a given page.
        $lastslotbefore = 0;
        foreach (array_reverse($slots) as $otherslot) {
            if ($otherslot->page > $page) {
                $DB->set_field('cquiz_slots', 'slot', $otherslot->slot + 1, array('id' => $otherslot->id));
            } else {
                $lastslotbefore = $otherslot->slot;
                break;
            }
        }
        $slot->slot = $lastslotbefore + 1;
        $slot->page = min($page, $maxpage + 1);

        $DB->execute("
                UPDATE {cquiz_sections}
                   SET firstslot = firstslot + 1
                 WHERE cquizid = ?
                   AND firstslot > ?
                ", array($cquiz->id, max($lastslotbefore, 1)));
    } else {
        $lastslot = end($slots);
        if ($lastslot) {
            $slot->slot = $lastslot->slot + 1;
        } else {
            $slot->slot = 1;
        }
        if ($cquiz->questionsperpage && $numonlastpage >= $cquiz->questionsperpage) {
            $slot->page = $maxpage + 1;
        } else {
            $slot->page = $maxpage;
        }
    }

    $DB->insert_record('cquiz_slots', $slot);
    $trans->allow_commit();
}

/**
 * Add a random question to the cquiz at a given point.
 * @param object $cquiz the cquiz settings.
 * @param int $addonpage the page on which to add the question.
 * @param int $categoryid the question category to add the question from.
 * @param int $number the number of random questions to add.
 * @param bool $includesubcategories whether to include questoins from subcategories.
 */
function cquiz_add_random_questions($cquiz, $addonpage, $categoryid, $number, $includesubcategories) {
    global $DB;

    $category = $DB->get_record('question_categories', array('id' => $categoryid));
    if (!$category) {
        print_error('invalidcategoryid', 'error');
    }

    $catcontext = context::instance_by_id($category->contextid);
    require_capability('moodle/question:useall', $catcontext);

    // Find existing random questions in this category that are
    // not used by any cquiz.
    if ($existingquestions = $DB->get_records_sql(
            "SELECT q.id, q.qtype FROM {question} q
            WHERE qtype = 'random'
                AND category = ?
                AND " . $DB->sql_compare_text('questiontext') . " = ?
                AND NOT EXISTS (
                        SELECT *
                          FROM {cquiz_slots}
                         WHERE questionid = q.id)
            ORDER BY id", array($category->id, ($includesubcategories ? '1' : '0')))) {
        // Take as many of these as needed.
        while (($existingquestion = array_shift($existingquestions)) && $number > 0) {
            cquiz_add_cquiz_question($existingquestion->id, $cquiz, $addonpage);
            $number -= 1;
        }
    }

    if ($number <= 0) {
        return;
    }

    // More random questions are needed, create them.
    for ($i = 0; $i < $number; $i += 1) {
        $form = new stdClass();
        $form->questiontext = array('text' => ($includesubcategories ? '1' : '0'), 'format' => 0);
        $form->category = $category->id . ',' . $category->contextid;
        $form->defaultmark = 1;
        $form->hidden = 1;
        $form->stamp = make_unique_id_code(); // Set the unique code (not to be changed).
        $question = new stdClass();
        $question->qtype = 'random';
        $question = question_bank::get_qtype('random')->save_question($question, $form);
        if (!isset($question->id)) {
            print_error('cannotinsertrandomquestion', 'cquiz');
        }
        cquiz_add_cquiz_question($question->id, $cquiz, $addonpage);
    }
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $cquiz       cquiz object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.1
 */
function cquiz_view($cquiz, $course, $cm, $context) {

    $params = array(
        'objectid' => $cquiz->id,
        'context' => $context
    );

    $event = \mod_cquiz\event\course_module_viewed::create($params);
    $event->add_record_snapshot('cquiz', $cquiz);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Validate permissions for creating a new attempt and start a new preview attempt if required.
 *
 * @param  cquiz $cquizobj cquiz object
 * @param  cquiz_access_manager $accessmanager cquiz access manager
 * @param  bool $forcenew whether was required to start a new preview attempt
 * @param  int $page page to jump to in the attempt
 * @param  bool $redirect whether to redirect or throw exceptions (for web or ws usage)
 * @return array an array containing the attempt information, access error messages and the page to jump to in the attempt
 * @throws moodle_cquiz_exception
 * @since Moodle 3.1
 */
function cquiz_validate_new_attempt(cquiz $cquizobj, cquiz_access_manager $accessmanager, $forcenew, $page, $redirect) {
    global $DB, $USER;
    $timenow = time();

    if ($cquizobj->is_preview_user() && $forcenew) {
        $accessmanager->current_attempt_finished();
    }

    // Check capabilities.
    if (!$cquizobj->is_preview_user()) {
        $cquizobj->require_capability('mod/cquiz:attempt');
    }

    // Check to see if a new preview was requested.
    if ($cquizobj->is_preview_user() && $forcenew) {
        // To force the creation of a new preview, we mark the current attempt (if any)
        // as finished. It will then automatically be deleted below.
        $DB->set_field('cquiz_attempts', 'state', cquiz_attempt::FINISHED, array('cquiz' => $cquizobj->get_cquizid(), 'userid' => $USER->id));
    }

    // Look for an existing attempt.
    $attempts = cquiz_get_user_attempts($cquizobj->get_cquizid(), $USER->id, 'all', true);
    $lastattempt = end($attempts);

    $attemptnumber = null;
    // If an in-progress attempt exists, check password then redirect to it.
    if ($lastattempt && ($lastattempt->state == cquiz_attempt::IN_PROGRESS ||
            $lastattempt->state == cquiz_attempt::OVERDUE)) {
        $currentattemptid = $lastattempt->id;
        $messages = $accessmanager->prevent_access();

        // If the attempt is now overdue, deal with that.
        $cquizobj->create_attempt_object($lastattempt)->handle_if_time_expired($timenow, true);

        // And, if the attempt is now no longer in progress, redirect to the appropriate place.
        if ($lastattempt->state == cquiz_attempt::ABANDONED || $lastattempt->state == cquiz_attempt::FINISHED) {
            if ($redirect) {
                redirect($cquizobj->review_url($lastattempt->id));
            } else {
                throw new moodle_cquiz_exception($cquizobj, 'attemptalreadyclosed');
            }
        }

        // If the page number was not explicitly in the URL, go to the current page.
        if ($page == -1) {
            $page = $lastattempt->currentpage;
        }
    } else {
        while ($lastattempt && $lastattempt->preview) {
            $lastattempt = array_pop($attempts);
        }

        // Get number for the next or unfinished attempt.
        if ($lastattempt) {
            $attemptnumber = $lastattempt->attempt + 1;
        } else {
            $lastattempt = false;
            $attemptnumber = 1;
        }
        $currentattemptid = null;

        $messages = $accessmanager->prevent_access() +
                $accessmanager->prevent_new_attempt(count($attempts), $lastattempt);

        if ($page == -1) {
            $page = 0;
        }
    }
    return array($currentattemptid, $attemptnumber, $lastattempt, $messages, $page);
}

/**
 * Prepare and start a new attempt deleting the previous preview attempts.
 *
 * @param  cquiz $cquizobj cquiz object
 * @param  int $attemptnumber the attempt number
 * @param  object $lastattempt last attempt object
 * @param  bool $preview the attempt is a preview
 * @return object the new attempt
 * @since  Moodle 3.1
 */
function cquiz_prepare_and_start_new_attempt(cquiz $cquizobj, $attemptnumber, $lastattempt, $preview) {
    global $DB, $USER;

    // Delete any previous preview attempts belonging to this user.
    cquiz_delete_previews($cquizobj->get_cquiz(), $USER->id);

    $quba = question_engine::make_questions_usage_by_activity('mod_cquiz', $cquizobj->get_context());
    $quba->set_preferred_behaviour($cquizobj->get_cquiz()->preferredbehaviour);

    // Create the new attempt and initialize the question sessions
    $timenow = time(); // Update time now, in case the server is running really slowly.
    $attempt = cquiz_create_attempt($cquizobj, $attemptnumber, $lastattempt, $timenow, $preview);

    if (!($cquizobj->get_cquiz()->attemptonlast && $lastattempt)) {
        $attempt = cquiz_start_new_attempt($cquizobj, $quba, $attempt, $attemptnumber, $timenow);
    } else {
        $attempt = cquiz_start_attempt_built_on_last($quba, $attempt, $lastattempt);
    }

    $transaction = $DB->start_delegated_transaction();

    $attempt = cquiz_attempt_save_started($cquizobj, $quba, $attempt);

    $transaction->allow_commit();

    return $attempt;
}

/**
 * Reset attempt start time to current time
 * 
 * @param int $attemptid Id of attempt to have its timer reset
 */
function cquiz_reset_attempt_timer($attemptid) {
    global $DB;

    $attempt = $DB->get_record('cquiz_attempts', array('id' => $attemptid));
    if (!$attempt) {
        debugging('Attempt id ' . $attemptid . ' not found');
        return;
    }
    $attempt->timestart = time();
    $DB->update_record('cquiz_attempts', $attempt);
}

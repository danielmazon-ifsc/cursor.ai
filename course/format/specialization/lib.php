<?php

/**
 * Library for specialization format
 *
 * @package   format_specialization
 * @copyright 2018 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/lib.php');

use local_studypace\studypace;

/**
 * Main class for the Specialization course format.
 *
 * @package    format_specialization
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class format_specialization extends core_courseformat\base {

    /**
     * Creates a new instance of the class.
     *
     * Previously the constructor inserted a row into {format_specialization}
     * on every instantiation, but course_get_format() is called from many
     * core paths (REST, web services, navigation, blocks). That caused two
     * problems: (1) implicit DB writes during seemingly read-only requests,
     * and (2) "specialization exists" became true any time someone asked the
     * format class. Creation now happens lazily on first real access through
     * ensure_specialization() and explicitly through the course_created /
     * course_updated observers.
     *
     * Please use {@link course_get_format($courseorid)} to get an instance.
     *
     * @param string $format
     * @param int $courseid
     */
    protected function __construct($format, $courseid) {
        parent::__construct($format, $courseid);
    }

    /**
     * Make sure the format_specialization row exists for this course. Safe to
     * call repeatedly — it short-circuits when the row is already there.
     */
    public function ensure_specialization() {
        if ($this->courseid) {
            self::create_specialization((int) $this->courseid);
        }
    }

    /**
     * Returns true if this course format uses sections
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    /**
     * Returns the display name of the given section that the course prefers.
     *
     * The legacy implementation returned the literal string 'Null', which
     * broke breadcrumbs, accessibility tools, navigation and admin pages.
     * Delegate to the parent (which uses the section's own name or builds
     * one from the section number) so the format integrates cleanly with
     * core Moodle.
     *
     * @param int|stdClass $section Section object from DB or section number.
     * @return string Display name that the course format prefers.
     */
    public function get_section_name($section) {
        return parent::get_section_name($section);
    }

    /**
     * Register format assets and work around settings-block tree issues on course view.
     *
     * @param moodle_page $page
     */
    public function page_set_course(moodle_page $page): void {
        parent::page_set_course($page);

        if (!$page->has_set_url() ||
                !$page->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
            return;
        }

        $pluginmanager = core_plugin_manager::instance();
        $plugininfo = $pluginmanager->get_plugin_info('format_specialization');
        $pluginrev = $plugininfo ? (int) $plugininfo->versiondb : 0;

        // Avoid core settingsblock init() fatal when "Administração do curso" has no <a>.
        $page->requires->js_amd_inline(
            'require.config({map:{"*":{"block_settings/settingsblock":"format_specialization/settingsblock"}}});'
        );

        $page->requires->css(new moodle_url(
            '/course/format/specialization/settingsblock-tree.css',
            ['v' => $pluginrev]
        ));
        $page->requires->js(new moodle_url(
            '/course/format/specialization/settingsblock-tree.js',
            ['v' => $pluginrev]
        ));
        $page->requires->js(new moodle_url(
            '/course/format/specialization/format-specialization.js',
            ['v' => $pluginrev]
        ));
    }

    /**
     * Create a new specialization
     * 
     * @param int $specializationid Specialization course id
     * @return bool True if specialization exists now
     */
    static function create_specialization(int $specializationid) {
        global $DB;
        $specrec = $DB->get_record('format_specialization', array('courseid' => $specializationid));
        if ($specrec) {
            return true;
        }
        $record = new stdClass();
        $record->courseid = $specializationid;
        try {
            return $DB->insert_record('format_specialization', $record);
        } catch (\dml_write_exception $e) {
            // TOCTOU: a concurrent observer (course_created + course_updated
            // both firing on a single bulk import) raced us and inserted
            // first. The UNIQUE index on courseid (added in upgrade
            // 2026052502) makes the second insert raise. Treat as success
            // since the row exists now.
            $specrec = $DB->get_record('format_specialization', array('courseid' => $specializationid));
            if ($specrec) {
                return true;
            }
            throw $e;
        }
    }

    /**
     * Delete an existing specialization
     * 
     * @param int $specializationid Specialization course id
     */
    static function delete_specialization(int $specializationid) {
        global $DB;
        $DB->delete_records('format_specialization_course', array('specializationid' => $specializationid));
        $DB->delete_records('format_specialization', array('courseid' => $specializationid));
    }

    /**
     * Add a course to a specialization in a definied position
     * 
     * @param int $specializationid Id of specialization to be changed
     * @param int $courseid Id of course to be added
     * @param int $position Position in which course should be added
     * @retun bool True if course was added to specialization
     */
    static function add_course(int $specializationid, int $courseid, int $position) {
        global $DB;
        // Check conditions
        $specrec = $DB->get_record('format_specialization', array('courseid' => $specializationid));
        if (!$specrec) {
            debugging("Specialization not found: $specializationid");
            return false;
        }
        $speccourserec = $DB->get_record('format_specialization_course', array('specializationid' => $specializationid, 'courseid' => $courseid));
        if ($speccourserec) {
            debugging("Course $courseid already in specialization $specializationid");
            return false;
        }
        // Renumber-then-insert wrapped in a transaction. Symmetric with
        // remove_course: without this, a failure between the UPDATE and the
        // INSERT leaves a gap in sequence numbers, which breaks ordering.
        $transaction = $DB->start_delegated_transaction();
        try {
            $sql = 'UPDATE {format_specialization_course} '
                    . 'SET sequence = sequence + 1 '
                    . 'WHERE specializationid = :specializationid '
                    . 'AND sequence >= :position';
            $DB->execute($sql, array('specializationid' => $specializationid, 'position' => $position));
            $record = new stdClass();
            $record->specializationid = $specializationid;
            $record->courseid = $courseid;
            $record->sequence = $position;
            $newid = $DB->insert_record('format_specialization_course', $record);
            $transaction->allow_commit();
            return $newid;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            return false;
        }
    }

    /**
     * Remove a course from a specialization
     * 
     * @param int $specializationid Id of specialization to be changed
     * @param int $courseid Id of course to be removed
     * @retun bool True if course was removed from specialization
     */
    static function remove_course(int $specializationid, int $courseid) {
        global $DB;
        $specrec = $DB->get_record('format_specialization', array('courseid' => $specializationid));
        if (!$specrec) {
            debugging("Specialization not found: $specializationid");
            return false;
        }
        $speccourserec = $DB->get_record('format_specialization_course', array('specializationid' => $specializationid, 'courseid' => $courseid));
        if (!$speccourserec) {
            debugging("Course $courseid not found in specialization $specializationid");
            return false;
        }
        // Delete first, then compact the rows that came AFTER the removed one
        // (strictly greater, so the removed slot itself doesn't get touched).
        // Wrapped in a transaction so we never observe duplicate sequence
        // values, even if the second statement fails.
        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->delete_records('format_specialization_course', array('id' => $speccourserec->id));
            $sql = 'UPDATE {format_specialization_course} '
                    . 'SET sequence = sequence - 1 '
                    . 'WHERE specializationid = :specializationid '
                    . 'AND sequence > :position';
            $DB->execute($sql, array(
                'specializationid' => $specializationid,
                'position' => $speccourserec->sequence,
            ));
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            return false;
        }
        return true;
    }

    /**
     * Retrieve specialization components
     * 
     * @return array Ordered array of course ids in specialization
     */
    static function get_courses(stdClass $course) {
        global $DB;
        if (!$DB->get_record('format_specialization', array('courseid' => $course->id))) {
            debugging('Not a specialization course: ' . $course->id);
        }
        $sql = 'SELECT c.* '
                . 'FROM {course} c, {format_specialization_course} fsc '
                . 'WHERE c.id = fsc.courseid '
                . 'AND specializationid = :specializationid '
                . 'ORDER BY sequence';
        return $DB->get_records_sql($sql, array('specializationid' => $course->id));
    }

    /**
     * Retrieve non specialization courses
     * 
     * @param stdClass $specialization Current specialization course
     * @return array of stdClass Non specialization courses
     */
    static function get_non_specialization_courses(stdClass $specialization) {
        global $DB;
        $sql = 'SELECT * '
                . 'FROM {course} '
                . 'WHERE category <> 0 '
                . 'AND id NOT IN (SELECT courseid FROM {format_specialization}) '
                . 'AND id NOT IN (SELECT courseid FROM {format_specialization_course} WHERE specializationid = :id)';
        return $DB->get_records_sql($sql, array('id' => $specialization->id));
    }

    /**
     * Retrieve specialization courses
     * 
     * @return array of stdClass Specialization courses
     */
    /**
     * Return every course currently using the specialization (Eixo) format.
     *
     * Ordering: primary by Moodle's native `sortorder` (the field admins
     * reorder via "Manage courses" drag/drop), with `shortname` as a stable
     * tie-breaker. The previous implementation used `ORDER BY shortname ASC`
     * alone, which broke as soon as admins named Eixos "Eixo I, II, III..."
     * (lexicographic order is wrong) or mixed conventions. `sortorder` is
     * the canonical Moodle "this comes before that" signal — it's what
     * /course/management.php saves when categories are reorganised and what
     * /course/edit.php uses for the up/down arrows.
     *
     * @param bool $onlyvisible If true, restrict to visible courses only.
     * @return stdClass[] Course records, ordered for the progression UI.
     */
    static function get_courses_in_format($onlyvisible = false) {
        global $DB;
        $sql = 'SELECT * '
                . 'FROM {course} '
                . "WHERE format = 'specialization' "
                . ($onlyvisible ? 'AND visible = 1 ' : '')
                . 'ORDER BY sortorder ASC, shortname ASC';
        return $DB->get_records_sql($sql, array());
    }

    /**
     * Check if course has specialization format
     * 
     * @param int $courseid Id of course to be checked
     * @return True if course is a specialization
     */
    static function is_specialization(int $courseid) {
        global $DB;
        return $DB->get_record('format_specialization', array('courseid' => $courseid));
    }

    /**
     * Retrieve specializations in which a course is part
     * 
     * @param int $courseid Id of course to be checked
     * @return array of stdClass Specialization list
     */
    static function part_of(int $courseid) {
        global $DB;
        $sql = 'SELECT c.* '
                . 'FROM {course} c, {format_specialization_course} fsc '
                . 'WHERE c.id = fsc.specializationid '
                . 'AND fsc.courseid = :courseid';
        return $DB->get_records_sql($sql, array('courseid' => $courseid));
    }

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * Topics format uses the following options:
     * - coursedisplay
     * - numsections
     * - hiddensections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = array(
                'numsections' => array(
                    'default' => $courseconfig->numsections,
                    'type' => PARAM_INT,
                ),
            );
        }
        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $sectionmenu = array();
            for ($i = 0; $i <= 5; $i++) {
                $sectionmenu[$i] = "$i";
            }
            $courseformatoptionsedit = array(
                'numsections' => array(
                    'label' => new lang_string('numberweeks'),
                    'element_type' => 'select',
                    'element_attributes' => array($sectionmenu),
                ),
            );
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    /**
     * Retrieve user completion state in a course module
     * 
     * @param int $userid Student to consider
     * @param int $cmid Course module to consider
     * @result bool completed or not
     */
    static function is_cm_completed($userid, $cmid) {
        global $DB;

        $cm = $DB->get_record('course_modules', array(
            'id' => $cmid
        ));
        if (!$cm) {
            // Stale sequence reference: do not treat as completed.
            return false;
        }
        if (!$cm->visible) {
            // Hidden activities are NOT completed - they're just hidden.
            // Returning true here would silently let users past the section
            // locking gate, even though they could not see the activity.
            return false;
        }
        $completion = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cmid, 'userid' => $userid));
        if ($completion) {
            return ($completion->completionstate == COMPLETION_COMPLETE || $completion->completionstate == COMPLETION_COMPLETE_FAIL || $completion->completionstate == COMPLETION_COMPLETE_PASS);
        } else {
            return false;
        }
    }

    static function is_cm_failed($userid, $cmid) {
        global $DB;

        $cm = $DB->get_record('course_modules', array(
            'id' => $cmid
        ));
        if (!$cm) {
            return false;
        }
        $completion = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cmid, 'userid' => $userid));
        if ($completion) {
            return $completion->completionstate == COMPLETION_COMPLETE_FAIL;
        } else {
            return false;
        }
    }

    static function all_previous_sections_completed($courseid, $sectionnum) {
        global $DB, $USER;

        $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
        $modinfo = get_fast_modinfo($course, $USER->id);

        $sql = 'SELECT * '
                . 'FROM {course_sections} '
                . 'WHERE course = :courseid '
                . 'AND section > 0 '
                . 'AND section < :sectionnum '
                . 'ORDER BY section;';
        $previoussections = $DB->get_records_sql($sql, array(
            'courseid' => $courseid,
            'sectionnum' => $sectionnum
        ));
        foreach ($previoussections as $previoussection) {
            $cmids = array_filter(array_map('intval', explode(',', $previoussection->sequence)));
            foreach ($cmids as $cmid) {
                if (empty($modinfo->cms[$cmid]) || !$modinfo->cms[$cmid]->uservisible) {
                    continue;
                }
                if (format_specialization::is_cm_monitorable($cmid) && !format_specialization::is_cm_completed($USER->id, $cmid)) {
                    return false;
                }
            }
        }
        return true;
    }

    static function get_average_grade($userid) {
        global $DB;

        $sql = 'SELECT avg(gg.finalgrade) as avg '
                . 'FROM {grade_items} gi, {grade_grades} gg, {modules} m, {course_modules} cm '
                . 'WHERE gg.itemid = gi.id '
                . 'AND m.name = gi.itemmodule '
                . 'AND cm.module = m.id '
                . 'AND cm.instance = gi.iteminstance '
                . 'AND gg.userid = :userid '
                . "AND gi.itemmodule = 'cquiz' "
                . 'AND cm.completion = 2;';
        $record = $DB->get_record_sql($sql, array(
            'userid' => $userid
        ));
        if (!$record || $record->avg === null) {
            return 0;
        }
        return round((float) $record->avg, 0);
    }

    /**
     * Calculate student's grade percentage in course so far
     * 
     * @param int $userid
     * @param int $courseid
     * @return float Student's grade percentage (0-100), zero also whether user
     *                  is not enrolled or doesn't exist
     */
    static function get_student_grade($userid, $courseid) {
        global $DB;

        $sql = 'SELECT gg.finalgrade '
                . 'FROM {grade_grades} gg, {grade_items} gi '
                . 'WHERE gg.itemid = gi.id '
                . 'AND gi.courseid = :courseid '
                . 'AND gi.categoryid is null '
                . 'AND gg.userid = :userid;';
        $gradegrades = $DB->get_records_sql($sql, array(
            'courseid' => $courseid,
            'userid' => $userid
        ));
        if (empty($gradegrades)) {
            return 0;
        }
        $gradegrade = reset($gradegrades);
        // finalgrade is NULL until the course actually grades the student;
        // round(null) is a PHP 8.1+ deprecation warning that turned into an
        // error in PHP 9. Coerce explicitly.
        if ($gradegrade->finalgrade === null) {
            return 0;
        }
        return round((float) $gradegrade->finalgrade, 0);
    }

    /**
     * Check if a course module is monitorable
     * 
     * @param cm_info $cm Course module info
     */
    static function is_cm_monitorable($cmid) {
        global $CFG, $DB;
        // Restrict to activity completion criteria. Without this filter
        // role/grade/date criteria can collide with cm.ids via moduleinstance.
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');
        $sql = 'SELECT id '
                . 'FROM {course_completion_criteria} '
                . 'WHERE moduleinstance = :coursemoduleid '
                . 'AND criteriatype = :criteriatype';
        $record = $DB->get_record_sql($sql, array(
            'coursemoduleid' => $cmid,
            'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
        ));
        return ($record != null);
    }

    /**
     * Calculate course module completions by an user in a course
     * 
     * @param type $user
     * @param type $course
     * @return array of two integers: modules completed, total modules
     */
    static function get_course_module_completions($user, $course) {
        $modulescompleted = 0;
        $modinfo = get_fast_modinfo($course, $user->id);
        $monitorable = 0;
        foreach ($modinfo->cms as $cmid => $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            if (format_specialization::is_cm_monitorable($cm->id)) {
                $completed = format_specialization::is_cm_completed($user->id, $cmid);
                $modulescompleted += $completed;
                ++$monitorable;
            }
        }
        if ($monitorable == 0) {
            return array(0, 0);
        }
        return array($modulescompleted, $monitorable);
    }

    static function get_course_completion($user, $course) {
        list($modulescompleted, $totalmodules) = format_specialization::get_course_module_completions($user, $course);
        return ($totalmodules > 0 ? $modulescompleted / $totalmodules : 0);
    }

    /**
     * Returns true when the user has completed the course.
     *
     * Source-of-truth order:
     *  1) {local_studypace} actualcompletion timestamp (set by
     *     studypace::mark_course_completed when every monitored CM in the
     *     course is done). This is what local_dashboard already uses, so
     *     delegating here keeps the home page, mini-dashboard and progress
     *     bar mutually consistent.
     *  2) Fallback to the CM-counting heuristic. Only kicks in if
     *     local_studypace isn't loaded yet (very early upgrades, CLI
     *     scripts, or unit tests that bypass the plugin), or if there's
     *     simply no row recorded for the user in that course.
     *
     * Important: when the course has *no monitorable course modules* the
     * heuristic used to return true (0 >= 0). That made empty/misconfigured
     * Eixos silently appear "completed", causing the home page to skip past
     * them in get_first_not_enroled_course_id(). Return false instead — an
     * Eixo without completion criteria cannot be considered done.
     *
     * @param stdClass $user
     * @param int|stdClass $course Course record or id.
     * @return bool
     */
    static function is_course_completed($user, $course) {
        $courseid = is_object($course) ? (int) $course->id : (int) $course;
        $userid = is_object($user) ? (int) $user->id : (int) $user;

        if (class_exists('\\local_studypace\\studypace')) {
            // Single canonical answer. If studypace has a row with
            // actualcompletion set, the course is completed.
            if (\local_studypace\studypace::is_course_completed($courseid, $userid)) {
                return true;
            }
            // No studypace row yet → fall through to the CM heuristic so we
            // don't lock the user into "not completed" on first load before
            // the enrolment row triggers a pace creation.
        }

        list($modulescompleted, $totalmodules) = format_specialization::get_course_module_completions($user, $course);
        if ($totalmodules <= 0) {
            // An Eixo with no monitorable CMs is unconfigured — it cannot be
            // completed by the student. Returning false here keeps the home
            // page from skipping past it during progression.
            return false;
        }
        return ($modulescompleted >= $totalmodules);
    }

    static function all_previous_courses_completed($courses, $courseid, $user) {
        foreach ($courses as $course) {
            if ($course->id == $courseid) {
                return true;
            }
            if (!format_specialization::is_course_completed($user, $course->id)) {
                return false;
            }
        }
        // Reaching this point means we ran through every course in the
        // specialization without ever finding $courseid. The caller is asking
        // "did the user finish everything before $courseid?"; if $courseid is
        // not part of the specialization at all, treat it as if it had no
        // predecessors (true) instead of the previous accidental false.
        return true;
    }

    /**
     * Find the next Eixo the user can enrol into.
     *
     * Skips:
     *  - Eixos already completed (advance past them).
     *  - Hidden Eixos — pointing the home-page "toenrol-area" card at a
     *    hidden course would land the student on a 403 / "course not
     *    available" page after they click "Enrol me". The previous version
     *    didn't filter visibility because the home page passes
     *    onlyvisible=false (so admins can still see hidden cards in edit
     *    mode); we filter here to keep the progression target safe.
     *
     * Stops returning if the user is already enrolled in the next pending
     * Eixo (returns 0 — UI marks no card as "toenrol").
     *
     * @param stdClass[] $courses Eixos in progression order.
     * @param stdClass $user
     * @return int Course id of the next enrolable Eixo, or 0.
     */
    static function get_first_not_enroled_course_id($courses, $user) {
        // local_studypace is a soft dependency at the file-level (we have a
        // 'use' import). In a hypothetical install with only the format
        // installed (no studypace), the symbol resolution would fatal here.
        // The class_exists() guard lets the home page degrade gracefully:
        // if studypace is missing we fall back to is_enrolled() from core.
        $hasstudypace = class_exists('\\local_studypace\\studypace');
        foreach ($courses as $course) {
            if (empty($course->visible)) {
                continue;
            }
            if (format_specialization::is_course_completed($user, $course->id)) {
                continue;
            }
            if ($hasstudypace) {
                $enrolled = studypace::is_enroled($course->id, $user->id);
            } else {
                $enrolled = is_enrolled(context_course::instance($course->id), $user);
            }
            if ($enrolled) {
                return 0;
            }
            return $course->id;
        }
        return 0;
    }

    /**
     * Convert a raw finalgrade + grademax into a 0–100 percentage.
     *
     * @param float|null $finalgrade
     * @param float $grademax
     * @return float|null null when there is no grade yet
     */
    public static function grade_to_percent($finalgrade, $grademax) {
        if ($finalgrade === null) {
            return null;
        }
        $grademax = (float) $grademax;
        if ($grademax > 0) {
            return ((float) $finalgrade / $grademax) * 100.0;
        }
        return (float) $finalgrade;
    }

    /**
     * Whether every graded section (disciplina) in an Eixo is at/above $mingrade.
     *
     * A section counts when section > 0 and it has at least one graded cquiz.
     * The section grade is the best percentage among those cquizzes (main +
     * recovery). Sections without graded cquizzes are ignored. Returns true
     * when the course has no graded sections.
     *
     * @param int $userid
     * @param int $courseid
     * @param float $mingrade
     * @return bool
     */
    public static function user_passes_all_section_grades($userid, $courseid, $mingrade = 60.0) {
        $map = self::user_section_grades_pass_map($userid, [(int) $courseid], $mingrade);
        return array_key_exists((int) $courseid, $map) && $map[(int) $courseid] === true;
    }

    /**
     * Per-course map: every graded section meets $mingrade.
     *
     * A section counts when section > 0 and it contains at least one graded
     * cquiz. The section passes when the best cquiz percentage is >= $mingrade
     * (main + recovery). Sections without graded cquizzes are ignored.
     * Courses with no graded sections remain true.
     *
     * @param int $userid
     * @param int[] $courseids
     * @param float $mingrade
     * @return array<int,bool> courseid => true when all graded sections pass
     */
    public static function user_section_grades_pass_map($userid, array $courseids, $mingrade = 60.0) {
        global $DB, $CFG;

        require_once($CFG->libdir . '/grade/constants.php');

        $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids))));
        $result = array_fill_keys($courseids, true);
        if ((int) $userid <= 0 || empty($courseids)) {
            return $result;
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $params['userid'] = (int) $userid;
        $params['gradetypevalue'] = GRADE_TYPE_VALUE;
        $params['gradetypescale'] = GRADE_TYPE_SCALE;

        // Only mod_cquiz grade items in sections > 0 (main + recovery).
        $sql = "SELECT cs.course AS courseid, cs.id AS sectionid,
                       gi.grademax, gg.finalgrade
                  FROM {course_sections} cs
                  JOIN {course_modules} cm
                    ON cm.section = cs.id
                   AND cm.deletioninprogress = 0
                   AND cm.visible = 1
                  JOIN {modules} m
                    ON m.id = cm.module AND m.name = 'cquiz'
                  JOIN {grade_items} gi
                    ON gi.itemtype = 'mod'
                   AND gi.itemmodule = 'cquiz'
                   AND gi.iteminstance = cm.instance
                   AND gi.courseid = cs.course
                   AND gi.gradetype IN (:gradetypevalue, :gradetypescale)
             LEFT JOIN {grade_grades} gg
                    ON gg.itemid = gi.id AND gg.userid = :userid
                 WHERE cs.course {$insql}
                   AND cs.section > 0";

        $rows = $DB->get_recordset_sql($sql, $params);
        $bestbysection = [];
        foreach ($rows as $row) {
            $cid = (int) $row->courseid;
            $sid = (int) $row->sectionid;
            $pct = self::grade_to_percent(
                $row->finalgrade === null ? null : (float) $row->finalgrade,
                (float) $row->grademax
            );
            if (!isset($bestbysection[$cid][$sid])) {
                $bestbysection[$cid][$sid] = $pct;
                continue;
            }
            // Keep the best attempt across main + recovery cquizzes.
            $current = $bestbysection[$cid][$sid];
            if ($pct === null) {
                continue;
            }
            if ($current === null || $pct > $current) {
                $bestbysection[$cid][$sid] = $pct;
            }
        }
        $rows->close();

        foreach ($bestbysection as $cid => $sections) {
            foreach ($sections as $pct) {
                if ($pct === null || (float) $pct < (float) $mingrade) {
                    $result[(int) $cid] = false;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Whether an Eixo meets approval for dashboard/certificate (course + sections).
     *
     * @param int $userid
     * @param int $courseid
     * @param float $coursetotal Raw course-total finalgrade (same as gradebook course item)
     * @param float $mingrade
     * @return bool
     */
    public static function user_passes_eixo_approval($userid, $courseid, $coursetotal, $mingrade = 60.0) {
        if ((float) $coursetotal < (float) $mingrade) {
            return false;
        }
        return self::user_passes_all_section_grades($userid, $courseid, $mingrade);
    }

}

<?php

/**
 * Common functions for the cquiz statistics report.
 *
 * @package    cquiz_statistics
 * @copyright  2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */

/**
 * SQL to fetch relevant 'cquiz_attempts' records.
 *
 * @param int    $cquizid        cquiz id to get attempts for
 * @param array  $groupstudents empty array if not using groups or array of students in current group.
 * @param string $whichattempts which attempts to use, represented internally as one of the constants as used in
 *                                   $cquiz->grademethod ie.
 *                                   CQUIZ_GRADEAVERAGE, CQUIZ_GRADEHIGHEST, CQUIZ_ATTEMPTLAST or CQUIZ_ATTEMPTFIRST
 *                                   we calculate stats based on which attempts would affect the grade for each student.
 * @param bool   $includeungraded whether to fetch ungraded attempts too
 * @return array FROM and WHERE sql fragments and sql params
 */
function cquiz_statistics_attempts_sql($cquizid, $groupstudents, $whichattempts = CQUIZ_GRADEAVERAGE, $includeungraded = false) {
    global $DB;

    $fromqa = '{cquiz_attempts} cquiza ';

    $whereqa = 'cquiza.cquiz = :cquizid AND cquiza.preview = 0 AND cquiza.state = :cquizstatefinished';
    $qaparams = array('cquizid' => (int) $cquizid, 'cquizstatefinished' => cquiz_attempt::FINISHED);

    if ($groupstudents) {
        ksort($groupstudents);
        list($grpsql, $grpparams) = $DB->get_in_or_equal(array_keys($groupstudents), SQL_PARAMS_NAMED, 'statsuser');
        list($grpsql, $grpparams) = cquiz_statistics_renumber_placeholders(
                $grpsql, $grpparams, 'statsuser');
        $whereqa .= " AND cquiza.userid $grpsql";
        $qaparams += $grpparams;
    }

    $whichattemptsql = cquiz_report_grade_method_sql($whichattempts);
    if ($whichattemptsql) {
        $whereqa .= ' AND ' . $whichattemptsql;
    }

    if (!$includeungraded) {
        $whereqa .= ' AND cquiza.sumgrades IS NOT NULL';
    }

    return array($fromqa, $whereqa, $qaparams);
}

/**
 * Re-number all the params beginning with $paramprefix in a fragment of SQL.
 *
 * @param string $sql the SQL.
 * @param array $params the params.
 * @param string $paramprefix the parameter prefix.
 * @return array with two elements, the modified SQL, and the modified params.
 */
function cquiz_statistics_renumber_placeholders($sql, $params, $paramprefix) {
    $basenumber = null;
    $newparams = array();
    $newsql = preg_replace_callback('~:' . preg_quote($paramprefix, '~') . '(\d+)\b~', function($match) use ($paramprefix, $params, &$newparams, &$basenumber) {
        if ($basenumber === null) {
            $basenumber = $match[1] - 1;
        }
        $oldname = $paramprefix . $match[1];
        $newname = $paramprefix . ($match[1] - $basenumber);
        $newparams[$newname] = $params[$oldname];
        return ':' . $newname;
    }, $sql);

    return array($newsql, $newparams);
}

/**
 * Return a {@link qubaid_condition} from the values returned by {@link cquiz_statistics_attempts_sql}.
 *
 * @param int     $cquizid
 * @param array   $groupstudents
 * @param string $whichattempts which attempts to use, represented internally as one of the constants as used in
 *                                   $cquiz->grademethod ie.
 *                                   CQUIZ_GRADEAVERAGE, CQUIZ_GRADEHIGHEST, CQUIZ_ATTEMPTLAST or CQUIZ_ATTEMPTFIRST
 *                                   we calculate stats based on which attempts would affect the grade for each student.
 * @param bool    $includeungraded
 * @return        \qubaid_join
 */
function cquiz_statistics_qubaids_condition($cquizid, $groupstudents, $whichattempts = CQUIZ_GRADEAVERAGE, $includeungraded = false) {
    list($fromqa, $whereqa, $qaparams) = cquiz_statistics_attempts_sql($cquizid, $groupstudents, $whichattempts, $includeungraded);
    return new qubaid_join($fromqa, 'cquiza.uniqueid', $whereqa, $qaparams);
}

/**
 * This helper function returns a sequence of colours each time it is called.
 * Used for choosing colours for graph data series.
 * @return string colour name.
 */
function cquiz_statistics_graph_get_new_colour() {
    static $colourindex = -1;
    $colours = array('red', 'green', 'yellow', 'orange', 'purple', 'black',
        'maroon', 'blue', 'ltgreen', 'navy', 'ltred', 'ltltgreen', 'ltltorange',
        'olive', 'gray', 'ltltred', 'ltorange', 'lime', 'ltblue', 'ltltblue');

    $colourindex = ($colourindex + 1) % count($colours);

    return $colours[$colourindex];
}

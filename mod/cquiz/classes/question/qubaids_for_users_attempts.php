<?php

/**
 * A {@link qubaid_condition} representing all the attempts by one user at a given cquiz.
 *
 * @package   mod_cquiz
 * @category  question
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */

namespace mod_cquiz\question;

defined('MOODLE_INTERNAL') || die();

/**
 * A {@link qubaid_condition} representing all the attempts by one user at a given cquiz.
 *
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class qubaids_for_users_attempts extends \qubaid_join {

    /**
     * Constructor.
     *
     * This takes the same arguments as {@link cquiz_get_user_attempts()}.
     *
     * @param int $cquizid the cquiz id.
     * @param int $userid the userid.
     * @param string $status 'all', 'finished' or 'unfinished' to control
     * @param bool $includepreviews defaults to false.
     */
    public function __construct($cquizid, $userid, $status = 'finished', $includepreviews = false) {
        $where = 'cquiza.cquiz = :cquizacquiz AND cquiza.userid = :userid';
        $params = array('cquizacquiz' => $cquizid, 'userid' => $userid);

        if (!$includepreviews) {
            $where .= ' AND preview = 0';
        }

        switch ($status) {
            case 'all':
                break;

            case 'finished':
                $where .= ' AND state IN (:state1, :state2)';
                $params['state1'] = \cquiz_attempt::FINISHED;
                $params['state2'] = \cquiz_attempt::ABANDONED;
                break;

            case 'unfinished':
                $where .= ' AND state IN (:state1, :state2)';
                $params['state1'] = \cquiz_attempt::IN_PROGRESS;
                $params['state2'] = \cquiz_attempt::OVERDUE;
                break;
        }

        parent::__construct('{cquiz_attempts} cquiza', 'cquiza.uniqueid', $where, $params);
    }

}

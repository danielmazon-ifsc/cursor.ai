<?php

/**
 * Definition of the coin_ledger_report class
 *
 * @package   local_coin
 * @copyright 2021 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace local_coin;

use local_coin\coin_stack;
use stdClass;
use html_writer;
use html_table;
use html_table_row;
use html_table_cell;

class coin_report_ledger {

    private $user;

    public function __construct($user) {
        $this->user = $user;
    }

    public function print_report($start = 0, $finish = 0, $pagenum = 0) {
        global $PAGE;

        $html = '';
        $pagesize = 100;

        $html .= $this->render_ledger_balance($this->user);

        $numentries = $this->get_num_ledger_entries($this->user->id);
        $numpages = ceil($numentries / $pagesize);
        $entries = $this->get_ledger_entries($this->user->id, $start, $finish, $pagenum * $pagesize, $pagesize);

        // Add ledger table
        $html .= $this->render_ledger_table($entries);

        // Display pagination
        if ($numentries > $pagesize) {
            $url = new moodle_url($PAGE->url, array('userid' => $this->user->id));
            $html .= $this->render_ledger_pages($pagenum + 1, $numpages, $url);
        }

        return $html;
    }

    private function get_num_ledger_entries($userid) {
        global $DB;
        $sql = 'SELECT count(*) AS total '
                . 'FROM {coin_ledger} '
                . 'WHERE userid = :userid';
        $result = $DB->get_record_sql($sql, array('userid' => $userid));
        return $result->total;
    }

    private function get_ledger_entries($userid, $start, $finish, $from = 0, $numrows = 0) {
        global $DB;

        $params = ['userid' => $userid];
        $sql = 'SELECT * '
                . 'FROM {coin_ledger} cl '
                . 'WHERE cl.userid = :userid ';
        if ($start) {
            $sql .= 'AND cl.transactiontime >= :start ';
            $params['start'] = $start;
        }
        if ($finish) {
            $sql .= 'AND cl.transactiontime <= :finish ';
            $params['finish'] = $finish + 86400;
        }
        $sql .= 'ORDER BY cl.transactiontime';
        $ledgerentries = $DB->get_records_sql($sql, $params, $from, $numrows);

        return $ledgerentries;
    }

    private function render_ledger_table($entries) {

        $html = '';
        $table = new html_table();

        // Add table header
        $table->head = array(get_string('coins', 'local_coin'), get_string('action', 'local_coin'), get_string('date'));

        // Add table rows
        foreach ($entries as $entry) {
            $row = new html_table_row();
            $amount = new html_table_cell($entry->amount);
            $row->cells[] = $amount;
            $eventname = $entry->action;
            if (strpos($eventname, 'planned_completion_high_grade') !== false) {
                $eventname = 'planned_completion_high_grade';
            } else if (strpos($eventname, 'planned_completion') !== false) {
                $eventname = 'planned_completion';
            }
            $action = new html_table_cell(s($eventname));
            $row->cells[] = $action;
            $entrydate = new html_table_cell(userdate($entry->transactiontime));
            $row->cells[] = $entrydate;
            $table->data[] = $row;
        }

        $html .= html_writer::table($table);

        return $html;
    }

    private function render_ledger_pages($pagenumber, $numpages, $url) {
        $html = '';
        $html .= html_writer::start_tag('ul', array('class' => 'pagination'));
        for ($i = 1; $i <= $numpages; ++$i) {
            $extraclass = $i == $pagenumber ? ' active' : '';
            $url = new moodle_url($url, array('pagenum' => $i - 1));
            $html .= html_writer::tag('li', html_writer::tag('a', $i, array('href' => $url, 'class' => 'page-link')), array('class' => 'page-item' . $extraclass));
        }
        $html .= html_writer::end_tag('ul');
        return $html;
    }

    /**
     * Generate HTML for coins balance
     * 
     * @param stdClass $user User whose balance should be printed
     */
    private function render_ledger_balance(stdClass $user) {
        $html = '';
        $coinsstack = new coin_stack($user->id);
        $coinsbalance = $coinsstack->coins;
        $html .= html_writer::start_div('coinsbalance');
        $html .= get_string('coinsbalance', 'local_coin') . ": ";
        $html .= $coinsbalance;
        $html .= coin_stack::get_coin_icon_html();
        $html .= html_writer::end_div(); // coinsbalance
        return $html;
    }

}

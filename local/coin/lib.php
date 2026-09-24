<?php

/**
 * Coin helper functions.
 *
 * @package   local_coin
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace local_coin;

/**
 * Function to be run periodically according to the scheduled task.
 * 
 * @param int $lastrun Last time cron run
 * @param int $nextrun Next time cron is supposed to run
 */
function local_coin_cron($lastrun, $nextrun) {

    $expirationperiod = (int) get_config('local_coin', 'expirationperiod');
    if ($expirationperiod < 1) {
        return;
    }
    $expirationlimit = time() - $expirationperiod;
    coin_stack::remove_expired_coins($expirationlimit);
}

<?php

/**
 * Events tests.
 *
 * @package    local_coin
 * @copyright  2016 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Event tests class.
 *
 * @package    local_coin
 * @copyright  2016 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class events_testcase extends advanced_testcase {

    public function test_user_coinsawarded() {
        global $DB;

        $this->resetAfterTest(true);
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');

        $user = $this->getDataGenerator()->create_user();

        // Trigger event
        $events = $DB->get_records('logstore_standard_log', array('eventname' => '\local_coin\event\user_coinsawarded'));
        $this->assertCount(0, $events);
        $params = array(
            'objectid' => 1,
            'userid' => $user->id,
            'context' => \context_user::instance($user->id),
        );
        $event = \local_coin\event\user_coinsawarded::create($params);
        $event->trigger();
        $events = $DB->get_records('logstore_standard_log', array('eventname' => '\local_coin\event\user_coinsawarded'));
        $this->assertCount(1, $events);
    }

    public function test_user_coinscancelled() {
        global $DB;

        $this->resetAfterTest(true);
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');

        $user = $this->getDataGenerator()->create_user();

        // Trigger event
        $events = $DB->get_records('logstore_standard_log', array('eventname' => '\local_coin\event\user_coinscancelled'));
        $this->assertCount(0, $events);
        $params = array(
            'objectid' => 1,
            'userid' => $user->id,
            'context' => \context_user::instance($user->id),
        );
        $event = \local_coin\event\user_coinscancelled::create($params);
        $event->trigger();
        $events = $DB->get_records('logstore_standard_log', array('eventname' => '\local_coin\event\user_coinscancelled'));
        $this->assertCount(1, $events);
    }

}

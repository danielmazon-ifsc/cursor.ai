<?php

/**
 * coin_stack tests.
 *
 * @package    local_coin
 * @copyright  2016 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/coin/classes/coin_stack.php');
require_once($CFG->dirroot . '/local/coin/tests/HTML5Validate.php');

use local_coin\coin_stack;

/**
 * coin_stack tests class.
 *
 * @package    local_coin
 * @copyright  2016 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class coin_stack_testcase extends advanced_testcase {

    public function test_constructor() {

        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();

        try {
            $stack = new coin_stack();
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
        try {
            $stack = new coin_stack(null);
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
        try {
            $stack = new coin_stack('null');
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
        try {
            $stack = new coin_stack(-1);
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
        try {
            $stack = new coin_stack(1.0);
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        $stack = new coin_stack($user->id);
        $this->assertNotNull($stack);
        $this->assertEquals($stack->userid, $user->id);
        $this->assertEquals($stack->coins, 0);
    }

    public function test_get_set_isset() {

        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $stack = new coin_stack($user->id);

        // Enable legacy logging plugin.
        set_config('enabled_stores', 'logstore_legacy', 'tool_log');
        set_config('loglegacy', 1, 'logstore_legacy');

        // Check get
        ($stack->nonexistent == 1) ? 1 : 0;
        $this->assertDebuggingCalled();
        $this->assertTrue(isset($stack->userid));
        $this->assertEquals($stack->userid, $user->id);
        $this->assertTrue(isset($stack->coins));
        $this->assertEquals($stack->coins, 0);

        // Check set
        try {
            $stack->nonexistent = 1;
            $this->fail();
        } catch (Exception $ex) {
            $this->assertTrue(true);
        }
        try {
            $stack->userid = 1;
            $this->fail();
        } catch (Exception $ex) {
            $this->assertTrue(true);
        }
        try {
            $stack->coins = 1;
            $this->fail();
        } catch (Exception $ex) {
            $this->assertTrue(true);
        }
    }

    public function test_stack_coins() {
        global $DB;

        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $stack = new coin_stack($user->id);

        try {
            $stack->stack_coins();
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
        try {
            $stack->stack_coins('');
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
        try {
            $stack->stack_coins('', '');
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
        try {
            $stack->stack_coins(1, 1);
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
        try {
            $stack->stack_coins();
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        $this->assertEquals($stack->coins, 0);
        $events = $DB->get_records('logstore_standard_log', array('eventname' => '\local_coin\event\user_coinsawarded'));
        $this->assertCount(0, $events);

        $coins = $stack->stack_coins(10, 'action');
        $this->assertEquals($stack->coins, 10);
        $this->assertEquals($coins, 10);
        $events = $DB->get_records('logstore_standard_log', array('eventname' => '\local_coin\event\user_coinsawarded'));
        $this->assertCount(1, $events);

        $coins = $stack->stack_coins(-5, 'action');
        $this->assertEquals($stack->coins, 5);
        $this->assertEquals($coins, 5);
        $events = $DB->get_records('logstore_standard_log', array('eventname' => '\local_coin\event\user_coinsawarded'));
        $this->assertCount(2, $events);

        $coins = $stack->stack_coins(-10, 'action');
        $this->assertEquals($stack->coins, -5);
        $this->assertEquals($coins, -5);
        $events = $DB->get_records('logstore_standard_log', array('eventname' => '\local_coin\event\user_coinsawarded'));
        $this->assertCount(3, $events);
    }

    public function test_unstack_coins() {
        global $DB;

        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $stack = new coin_stack($user->id);

        try {
            $stack->unstack_coins();
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
        try {
            $stack->unstack_coins(null);
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
        try {
            $stack->unstack_coins(1);
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        $action = 'action';
        $stack->stack_coins(10, $action);
        $events = $DB->get_records('logstore_standard_log', array('eventname' => '\local_coin\event\user_coinscancelled'));
        $this->assertCount(0, $events);

        $coins = $stack->unstack_coins('wrongaction');
        $this->assertEquals($stack->coins, 10);
        $this->assertEquals($coins, null);
        $events = $DB->get_records('logstore_standard_log', array('eventname' => '\local_coin\event\user_coinscancelled'));
        $this->assertCount(0, $events);

        $coins = $stack->unstack_coins($action);
        $this->assertEquals($stack->coins, 0);
        $this->assertEquals($coins, 0);
        $events = $DB->get_records('logstore_standard_log', array('eventname' => '\local_coin\event\user_coinscancelled'));
        $this->assertCount(1, $events);

        $coins = $stack->unstack_coins($action);
        $this->assertEquals($stack->coins, 0);
        $this->assertEquals($coins, null);
        $events = $DB->get_records('logstore_standard_log', array('eventname' => '\local_coin\event\user_coinscancelled'));
        $this->assertCount(1, $events);

        $coins = $stack->unstack_coins('wrongaction');
        $this->assertEquals($stack->coins, 0);
        $this->assertEquals($coins, null);
        $events = $DB->get_records('logstore_standard_log', array('eventname' => '\local_coin\event\user_coinscancelled'));
        $this->assertCount(1, $events);
    }

    public function test_remove_expired_coins() {
        global $DB;

        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $stack = new coin_stack($user->id);

        // Award coins with some delay between them
        $stack->stack_coins(1, 'action1');
        sleep(5);
        $stack->stack_coins(1, 'action1');

        // Retrieve newest timestamp
        $records = $DB->get_records('coin_ledger');
        $newest = 0;
        foreach ($records as $record) {
            if ($record->transactiontime > $newest) {
                $newest = $record->transactiontime;
            }
        }

        // No events previoulsy
        $events = $DB->get_records('logstore_standard_log', array('eventname' => '\local_coin\event\user_coinscancelled'));
        $this->assertCount(0, $events);

        // Expire only the first set
        $expirationlimit = time() - 3;
        coin_stack::remove_expired_coins($expirationlimit);

        // Check results
        $stack = new coin_stack($user->id);
        $this->assertEquals($stack->coins, 1);
        $records = $DB->get_records('coin_ledger');
        $record = reset($records);
        $this->assertEquals($record->transactiontime, $newest);
        $events = $DB->get_records('logstore_standard_log', array('eventname' => '\local_coin\event\user_coinscancelled'));
        $this->assertCount(1, $events);
    }

    public function test_get_coin_icon_html() {

        $icon = coin_stack::get_coin_icon_html();
        $validator = new HTML5Validate();
        $this->assertTrue($validator->Assert($icon));
    }

}

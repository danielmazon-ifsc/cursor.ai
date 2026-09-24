<?php

/**
 * observer tests.
 *
 * @package    local_coin
 * @copyright  2016 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

use local_coin\observer;
use local_coin\coin_stack;

/**
 * coin_stack tests class.
 *
 * @package    local_coin
 * @copyright  2016 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class observer_testcase extends advanced_testcase {

    public function test_process_event() {

        $this->resetAfterTest(true);

        // Setup testing envoronment
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $observer = new observer();

        // Setup config to award 1 coin whenever an user views a course
        set_config('triggersjson', '{"\\\\core\\\\event\\\\course_viewed":{"amount":1,"userid":"userid"}}', 'local_coin');
        set_config('cancellersjson', '{}', 'local_coin');

        // Generate events and check results
        $stack = new coin_stack($user->id);
        $this->assertEquals($stack->coins, 0);
        $eventdata = array('context' => \context_course::instance($course->id),
            'userid' => $user->id);
        $courseevent = \core\event\course_viewed::create($eventdata);
        $eventdata = array(
            'userid' => $user->id,
            'objectid' => $user->id,
            'other' => array('username' => $user->username),
        );
        $userevent = \core\event\user_loggedin::create($eventdata);

        // Generate events and check results
        $observer->process_event($courseevent);
        $stack = new coin_stack($user->id);
        $this->assertEquals($stack->coins, 1);

        $observer->process_event($courseevent);
        $stack = new coin_stack($user->id);
        $this->assertEquals(1, $stack->coins);

        $observer->process_event($userevent);
        $stack = new coin_stack($user->id);
        $this->assertEquals($stack->coins, 2);

        // Setup config to withdraw 1 coin whenever an user views a course
        set_config('triggersjson', '{}', 'local_coin');
        set_config('cancellersjson', '{"\\\\core\\\\event\\\\course_viewed":{"amount":1,"userid":"userid"}}', 'local_coin');

        // Generate events and check results — unstack removes the single prior award.
        $observer->process_event($courseevent);
        $stack = new coin_stack($user->id);
        $this->assertEquals(0, $stack->coins);

        $observer->process_event($courseevent);
        $stack = new coin_stack($user->id);
        $this->assertEquals(0, $stack->coins);
    }

}

<?php

/**
 * Unit tests for the {@link \mod_cquiz\repaginate} class.
 * @package   mod_cquiz
 * @category  test
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/cquiz/locallib.php');
require_once($CFG->dirroot . '/mod/cquiz/classes/repaginate.php');

/**
 * Testable subclass, giving access to the protected methods of {@link \mod_cquiz\repaginate}
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class mod_cquiz_repaginate_testable extends \mod_cquiz\repaginate {

    public function __construct($cquizid = 0, $slots = null) {
        return parent::__construct($cquizid, $slots);
    }

    public function get_this_slot($slots, $slotnumber) {
        return parent::get_this_slot($slots, $slotnumber);
    }

    public function get_slots_by_slotid($slots = null) {
        return parent::get_slots_by_slotid($slots);
    }

    public function get_slots_by_slot_number($slots = null) {
        return parent::get_slots_by_slot_number($slots);
    }

    public function repaginate_this_slot($slot, $newpagenumber) {
        return parent::repaginate_this_slot($slot, $newpagenumber);
    }

    public function repaginate_next_slot($nextslotnumber, $type) {
        return parent::repaginate_next_slot($nextslotnumber, $type);
    }

}

/**
 * Test for some parts of the repaginate class.
 * @copyright 2017 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
class mod_cquiz_repaginate_test extends advanced_testcase {

    /** @var array stores the slots. */
    private $cquizslots;

    /** @var mod_cquiz_repaginate_testable the object being tested. */
    private $repaginate = null;

    public function setUp() {
        $this->set_cquiz_slots($this->get_cquiz_object()->get_slots());
        $this->repaginate = new mod_cquiz_repaginate_testable(0, $this->cquizslots);
    }

    public function tearDown() {
        $this->repaginate = null;
    }

    /**
     * Create a cquiz, add five questions to the cquiz
     * which are all on one page and return the cquiz object.
     */
    private function get_cquiz_object() {
        global $SITE;
        $this->resetAfterTest(true);

        // Make a cquiz.
        $cquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_cquiz');

        $cquiz = $cquizgenerator->create_instance(array(
            'course' => $SITE->id, 'questionsperpage' => 0, 'grade' => 100.0, 'sumgrades' => 2));
        $cm = get_coursemodule_from_instance('cquiz', $cquiz->id, $SITE->id);

        // Create five questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();

        $shortanswer = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        $numerical = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        $essay = $questiongenerator->create_question('essay', null, array('category' => $cat->id));
        $truefalse = $questiongenerator->create_question('truefalse', null, array('category' => $cat->id));
        $match = $questiongenerator->create_question('match', null, array('category' => $cat->id));

        // Add them to the cquiz.
        cquiz_add_cquiz_question($shortanswer->id, $cquiz);
        cquiz_add_cquiz_question($numerical->id, $cquiz);
        cquiz_add_cquiz_question($essay->id, $cquiz);
        cquiz_add_cquiz_question($truefalse->id, $cquiz);
        cquiz_add_cquiz_question($match->id, $cquiz);

        // Return the cquiz object.
        $cquizobj = new cquiz($cquiz, $cm, $SITE);
        return \mod_cquiz\structure::create_for_cquiz($cquizobj);
    }

    /**
     * Set the cquiz slots
     * @param string $slots
     */
    private function set_cquiz_slots($slots = null) {
        if (!$slots) {
            $this->cquizslots = $this->get_cquiz_object()->get_slots();
        } else {
            $this->cquizslots = $slots;
        }
    }

    /**
     * Test the get_this_slot() method
     */
    public function test_get_this_slot() {
        $this->set_cquiz_slots();
        $actual = array();
        $expected = $this->repaginate->get_slots_by_slot_number();
        $this->assertEquals($expected, $actual);

        $slotsbyno = $this->repaginate->get_slots_by_slot_number($this->cquizslots);
        $slotnumber = 5;
        $thisslot = $this->repaginate->get_this_slot($this->cquizslots, $slotnumber);
        $this->assertEquals($slotsbyno[$slotnumber], $thisslot);
    }

    public function test_get_slots_by_slotnumber() {
        $this->set_cquiz_slots();
        $expected = array();
        $actual = $this->repaginate->get_slots_by_slot_number();
        $this->assertEquals($expected, $actual);

        foreach ($this->cquizslots as $slot) {
            $expected[$slot->slot] = $slot;
        }
        $actual = $this->repaginate->get_slots_by_slot_number($this->cquizslots);
        $this->assertEquals($expected, $actual);
    }

    public function test_get_slots_by_slotid() {
        $this->set_cquiz_slots();
        $actual = $this->repaginate->get_slots_by_slotid();
        $this->assertEquals(array(), $actual);

        $slotsbyno = $this->repaginate->get_slots_by_slot_number($this->cquizslots);
        $actual = $this->repaginate->get_slots_by_slotid($slotsbyno);
        $this->assertEquals($this->cquizslots, $actual);
    }

    public function test_repaginate_n_questions_per_page() {
        $this->set_cquiz_slots();

        // Expect 2 questions per page.
        $expected = array();
        foreach ($this->cquizslots as $slot) {
            // Page 1 contains Slots 1 and 2.
            if ($slot->slot >= 1 && $slot->slot <= 2) {
                $slot->page = 1;
            }
            // Page 2 contains slots 3 and 4.
            if ($slot->slot >= 3 && $slot->slot <= 4) {
                $slot->page = 2;
            }
            // Page 3 contains slots 5.
            if ($slot->slot >= 5 && $slot->slot <= 6) {
                $slot->page = 3;
            }
            $expected[$slot->id] = $slot;
        }
        $actual = $this->repaginate->repaginate_n_question_per_page($this->cquizslots, 2);
        $this->assertEquals($expected, $actual);

        // Expect 3 questions per page.
        $expected = array();
        foreach ($this->cquizslots as $slot) {
            // Page 1 contains Slots 1, 2 and 3.
            if ($slot->slot >= 1 && $slot->slot <= 3) {
                $slot->page = 1;
            }
            // Page 2 contains slots 4 and 5.
            if ($slot->slot >= 4 && $slot->slot <= 6) {
                $slot->page = 2;
            }
            $expected[$slot->id] = $slot;
        }
        $actual = $this->repaginate->repaginate_n_question_per_page($this->cquizslots, 3);
        $this->assertEquals($expected, $actual);

        // Expect 5 questions per page.
        $expected = array();
        foreach ($this->cquizslots as $slot) {
            // Page 1 contains Slots 1, 2, 3, 4 and 5.
            if ($slot->slot > 0 && $slot->slot < 6) {
                $slot->page = 1;
            }
            // Page 2 contains slots 6, 7, 8, 9 and 10.
            if ($slot->slot > 5 && $slot->slot < 11) {
                $slot->page = 2;
            }
            $expected[$slot->id] = $slot;
        }
        $actual = $this->repaginate->repaginate_n_question_per_page($this->cquizslots, 5);
        $this->assertEquals($expected, $actual);

        // Expect 10 questions per page.
        $expected = array();
        foreach ($this->cquizslots as $slot) {
            // Page 1 contains Slots 1 to 10.
            if ($slot->slot >= 1 && $slot->slot <= 10) {
                $slot->page = 1;
            }
            // Page 2 contains slots 11 to 20.
            if ($slot->slot >= 11 && $slot->slot <= 20) {
                $slot->page = 2;
            }
            $expected[$slot->id] = $slot;
        }
        $actual = $this->repaginate->repaginate_n_question_per_page($this->cquizslots, 10);
        $this->assertEquals($expected, $actual);

        // Expect 1 questions per page.
        $expected = array();
        $page = 1;
        foreach ($this->cquizslots as $slot) {
            $slot->page = $page++;
            $expected[$slot->id] = $slot;
        }
        $actual = $this->repaginate->repaginate_n_question_per_page($this->cquizslots, 1);
        $this->assertEquals($expected, $actual);
    }

    public function test_repaginate_this_slot() {
        $this->set_cquiz_slots();
        $slotsbyslotno = $this->repaginate->get_slots_by_slot_number($this->cquizslots);
        $slotnumber = 3;
        $newpagenumber = 2;
        $thisslot = $slotsbyslotno[3];
        $thisslot->page = $newpagenumber;
        $expected = $thisslot;
        $actual = $this->repaginate->repaginate_this_slot($slotsbyslotno[3], $newpagenumber);
        $this->assertEquals($expected, $actual);
    }

    public function test_repaginate_the_rest() {
        $this->set_cquiz_slots();
        $slotfrom = 1;
        $type = \mod_cquiz\repaginate::LINK;
        $expected = array();
        foreach ($this->cquizslots as $slot) {
            if ($slot->slot > $slotfrom) {
                $slot->page = $slot->page - 1;
                $expected[$slot->id] = $slot;
            }
        }
        $actual = $this->repaginate->repaginate_the_rest($this->cquizslots, $slotfrom, $type, false);
        $this->assertEquals($expected, $actual);

        $slotfrom = 2;
        $newslots = array();
        foreach ($this->cquizslots as $s) {
            if ($s->slot === $slotfrom) {
                $s->page = $s->page - 1;
            }
            $newslots[$s->id] = $s;
        }

        $type = \mod_cquiz\repaginate::UNLINK;
        $expected = array();
        foreach ($this->cquizslots as $slot) {
            if ($slot->slot > ($slotfrom - 1)) {
                $slot->page = $slot->page - 1;
                $expected[$slot->id] = $slot;
            }
        }
        $actual = $this->repaginate->repaginate_the_rest($newslots, $slotfrom, $type, false);
        $this->assertEquals($expected, $actual);
    }

}

<?php

namespace local_studypace\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Fired by studypace::mark_course_completed() the FIRST time a tracked course
 * (Eixo or Sala de Conferências) is recorded as completed by a user. Unlike
 * planned_completion / planned_completion_high_grade, which only fire when
 * the user finished on time AND carry grade-band semantics for badges, this
 * event fires for every successful completion regardless of timing.
 *
 * Use it for "advance the user along the Eixo progression" / "enrol in the
 * next course" workflows where the only thing that matters is "the user
 * finished this course at least once".
 *
 * The event is dispatched ONCE per (user, course): the underlying
 * mark_course_completed() short-circuits when actualcompletion is already
 * set, so observers don't need their own idempotency guard against repeat
 * fires for the same completion.
 */
class course_completed extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_studypace';
        $this->data['contextlevel'] = CONTEXT_COURSE;
        $this->data['anonymous'] = 0;
    }

    public function get_description() {
        return "The user with id '$this->userid' completed course '$this->courseid' (tracked by local_studypace).";
    }

    public static function get_name() {
        return get_string('eventcoursecompleted', 'local_studypace');
    }
}

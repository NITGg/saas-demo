<?php
namespace local_nit_lessons;

use local_nit_lessons\local\hub;
use local_nit_lessons\local\lesson_view;

/**
 * The student hub (local/nit_lessons/student.php): its tabs, groups and summary
 * bar, and the lesson recordings shown on the lesson cards.
 *
 * @package    local_nit_lessons
 * @covers     \local_nit_lessons\local\hub
 * @covers     \local_nit_lessons\local\lesson_view::recordings
 */
final class hub_tabs_test extends \advanced_testcase {

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/nit_flex/lib.php');
    }

    /** A teacher (an editing-teacher role in a course). */
    private function teacher(): \stdClass {
        $gen = $this->getDataGenerator();
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $gen->create_course()->id, 'editingteacher');
        return $teacher;
    }

    public function test_a_student_gets_the_groups_in_order(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->assertSame(['book', 'lessons', 'flexavailable', 'packages', 'subavailable', 'mysubs', 'wallet'],
            hub::tabs((int) $student->id));
        $this->setUser($student);
        $ctx = hub::context((int) $student->id, 'lessons');
        $this->assertSame([['book', 'lessons'], ['flexavailable', 'packages'], ['subavailable', 'mysubs'], ['wallet']],
            array_map(fn($g) => array_column($g['tabs'], 'key'), $ctx['tabgroups']));
        $this->assertTrue($ctx['tabgroups'][0]['active']);
        // The summary bar: Flex and the wallet — no earnings for a student.
        $this->assertSame(['flex', 'wallet'], array_column($ctx['stats'], 'key'));
    }

    public function test_a_teacher_also_gets_the_requests_and_the_earnings(): void {
        $teacher = $this->teacher();
        $tabs = hub::tabs((int) $teacher->id);
        $this->assertContains('earnings', $tabs);
        $this->setUser($teacher);
        $ctx = hub::context((int) $teacher->id, 'earnings');
        $this->assertSame(['flex', 'wallet', 'earnings'], array_column($ctx['stats'], 'key'));
        $this->assertSame('wallet', $ctx['tabgroups'][3]['tabs'][0]['key']);
        $this->assertSame('earnings', $ctx['tabgroups'][3]['tabs'][1]['key']);
    }

    public function test_the_former_pages_are_drawn_as_tabs(): void {
        $teacher = $this->teacher();
        $this->setUser($teacher);
        foreach (['flexavailable', 'wallet', 'earnings'] as $tab) {
            $ctx = hub::context((int) $teacher->id, $tab);
            $this->assertNotEmpty($ctx['embedhtml'], $tab);
            $this->assertTrue($ctx['is' . $tab]);
        }
    }

    public function test_a_student_sees_a_recording_once_the_lesson_is_over(): void {
        global $DB;
        $now = time();
        $sessionid = $DB->insert_record('academy_live_sessions', (object) [
            'courseid' => SITEID, 'teacherid' => 3, 'title' => 'Lesson', 'start_time' => $now, 'duration' => 60, 'status' => 'live',
            'timecreated' => $now, 'timemodified' => $now]);
        $lessonid = $DB->insert_record('nit_lesson', (object) [
            'studentid' => 2, 'teacherid' => 3, 'subject' => 'Physics', 'status' => 'in_progress',
            'requested_time' => $now, 'duration' => 60, 'cmid' => 77, 'sessionid' => $sessionid,
            'flex_state' => 'none', 'timecreated' => $now, 'timemodified' => $now]);
        $DB->insert_record('academy_session_recordings', (object) [
            'sessionid' => $sessionid, 'cmid' => 77, 'vimeo_videoid' => '123', 'status' => 'ready',
            'timecreated' => $now, 'timemodified' => $now]);
        $lesson = ['id' => $lessonid, 'cmid' => 77];

        $this->assertCount(1, lesson_view::recordings($lesson, 'teacher'));
        $this->assertCount(0, lesson_view::recordings($lesson, 'student'));
        $DB->set_field('academy_live_sessions', 'status', 'ended', ['id' => $sessionid]);
        $this->assertCount(1, lesson_view::recordings($lesson, 'student'));
        // A lesson without a room has nothing.
        $this->assertSame([], lesson_view::recordings(['id' => $lessonid + 1, 'cmid' => 0], 'teacher'));
    }
}

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_academysessions;

/**
 * The live monitoring wall: who may see which lessons, each lesson's state and
 * alerts, the "in now" count, filters and the one-lesson view.
 *
 * @package    local_academysessions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_academysessions\monitor
 */
final class monitor_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;
    /** @var \stdClass */
    private $teacher;
    /** @var \stdClass */
    private $student;
    /** @var \stdClass */
    private $student2;
    /** @var int a fixed clock */
    private $now;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('monitor_grace', 5, 'local_academysessions');
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course(['fullname' => 'Physics']);
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->student = $gen->create_and_enrol($this->course, 'student');
        $this->student2 = $gen->create_and_enrol($this->course, 'student');
        $this->now = time();
    }

    /**
     * A session starting $offset seconds from now (negative = already started).
     *
     * @param int $offset
     * @param array $fields extra academy_live_sessions fields
     * @return int session id
     */
    private function session(int $offset, array $fields = []): int {
        global $DB;
        $id = (int) session_manager::create_session($this->course->id, $this->teacher->id, $fields['title'] ?? 'Live',
            $this->now + $offset, [$this->student->id, $this->student2->id], '', 50, null, 1);
        unset($fields['title']);
        if ($fields) {
            $DB->update_record('academy_live_sessions', (object) (['id' => $id] + $fields));
        }
        return $id;
    }

    /**
     * The wall's cards keyed by session id.
     *
     * @param array $filters overrides
     * @param int[]|null $scope
     * @return array
     */
    private function cards(array $filters = [], ?array $scope = null): array {
        $f = (object) array_merge(['view' => 'now', 'date' => userdate($this->now, '%Y-%m-%d'), 'courseid' => 0,
            'teacherid' => 0, 'provider' => '', 'status' => '', 'q' => ''], $filters);
        $out = [];
        foreach (monitor::wall($f, $scope, $this->now)['cards'] as $c) {
            $out[$c['key']] = $c;
        }
        return $out;
    }

    public function test_scope_admin_everything_course_manager_their_course_teacher_nothing(): void {
        $gen = $this->getDataGenerator();
        $this->assertNull(monitor::course_scope((int) get_admin()->id));

        $manager = $gen->create_user();
        $gen->role_assign('manager', $manager->id, \context_course::instance($this->course->id)->id);
        $this->assertSame([(int) $this->course->id], monitor::course_scope((int) $manager->id));

        $this->assertSame([], monitor::course_scope((int) $this->teacher->id));
        $this->assertSame([], monitor::course_scope((int) $this->student->id));
    }

    public function test_states_from_upcoming_to_ended(): void {
        global $DB;
        $upcoming = $this->session(10 * MINSECS);
        $waiting = $this->session(-2 * MINSECS);
        $late = $this->session(-12 * MINSECS);
        $running = $this->session(-20 * MINSECS, ['teacher_joined_at' => $this->now - 15 * MINSECS,
            'teacher_first_join' => $this->now - 15 * MINSECS]);
        $left = $this->session(-20 * MINSECS, ['teacher_first_join' => $this->now - 18 * MINSECS]);
        $ended = $this->session(-55 * MINSECS, ['status' => 'ended']);
        $cancelled = $this->session(-5 * MINSECS, ['status' => 'cancelled']);

        $cards = $this->cards();
        $this->assertSame(monitor::UPCOMING, $cards['s' . $upcoming]['status']);
        $this->assertSame(monitor::WAITING, $cards['s' . $waiting]['status']);
        $this->assertFalse($cards['s' . $waiting]['hasalert']);
        $this->assertSame(monitor::LATE, $cards['s' . $late]['status']);
        $this->assertTrue($cards['s' . $late]['hasalert']);
        $this->assertSame(monitor::RUNNING, $cards['s' . $running]['status']);
        $this->assertSame(monitor::TEACHERLEFT, $cards['s' . $left]['status']);
        $this->assertTrue($cards['s' . $left]['hasalert']);
        $this->assertSame(monitor::ENDED, $cards['s' . $ended]['status']);
        // "Now" leaves cancelled lessons out; the day view shows them.
        $this->assertArrayNotHasKey('s' . $cancelled, $cards);
        $this->assertSame(monitor::CANCELLED, $this->cards(['view' => 'day'])['s' . $cancelled]['status']);

        // Alerts come first.
        $this->assertTrue(reset($cards)['hasalert']);
        // The teacher came in 5 minutes after the start (grace 5): not flagged; 6 is.
        $this->assertSame(0, $cards['s' . $running]['latemin']);
        $DB->set_field('academy_live_sessions', 'teacher_first_join', $this->now - 14 * MINSECS, ['id' => $running]);
        $this->assertSame(6, $this->cards()['s' . $running]['latemin']);
    }

    public function test_in_now_counts_invited_students_with_an_open_row_only(): void {
        $id = $this->session(-10 * MINSECS, ['teacher_joined_at' => $this->now - 9 * MINSECS,
            'teacher_first_join' => $this->now - 9 * MINSECS]);
        session_manager::record_attendance($id, (int) $this->teacher->id);         // Teacher: not a student.
        session_manager::record_attendance($id, (int) get_admin()->id);           // Admin dropping in.
        session_manager::record_attendance($id, (int) $this->student->id);        // In.
        session_manager::record_attendance($id, (int) $this->student2->id);
        session_manager::record_leave($id, (int) $this->student2->id);            // Came and left.

        $card = $this->cards()['s' . $id];
        $this->assertSame(2, $card['invited']);
        $this->assertSame(1, $card['present']);
        $this->assertSame(2, $card['joined']);
        $this->assertFalse($card['hasalert']);
    }

    public function test_running_with_no_student_in_is_flagged_after_the_grace_period(): void {
        $id = $this->session(-10 * MINSECS, ['teacher_joined_at' => $this->now - 9 * MINSECS,
            'teacher_first_join' => $this->now - 9 * MINSECS]);
        $card = $this->cards()['s' . $id];
        $this->assertSame(monitor::RUNNING, $card['status']);
        $this->assertTrue($card['hasalert']);
        $this->assertSame(get_string('monitor_alert_nostudents', 'local_academysessions'), $card['alerts'][0]['text']);
    }

    public function test_filters_and_scope(): void {
        $gen = $this->getDataGenerator();
        $mine = $this->session(-2 * MINSECS, ['title' => 'Algebra']);
        $other = $gen->create_course();
        $otherteacher = $gen->create_and_enrol($other, 'editingteacher');
        $theirs = (int) session_manager::create_session($other->id, $otherteacher->id, 'Biology', $this->now - 60, [], '', 50);

        $this->assertArrayHasKey('s' . $theirs, $this->cards());
        $this->assertSame(['s' . $mine], array_keys($this->cards([], [(int) $this->course->id])));
        $this->assertSame(['s' . $mine], array_keys($this->cards(['teacherid' => (int) $this->teacher->id])));
        $this->assertSame(['s' . $mine], array_keys($this->cards(['q' => 'alge'])));
        // Provider: the course session has a Jitsi room, the other one only a link.
        $this->assertSame(['s' . $theirs], array_keys($this->cards(['provider' => 'link'])));
        $this->assertSame(['s' . $mine], array_keys($this->cards(['status' => monitor::WAITING, 'courseid' => (int) $this->course->id])));
        // Nobody's courses: nothing.
        $this->assertSame([], $this->cards([], []));
    }

    public function test_confirmed_flex_lesson_the_teacher_did_not_start(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('nit_lesson')) {
            $this->markTestSkipped('local_nit_lessons is not installed');
        }
        set_config('lessons_courseid', $this->course->id, 'local_nit_lessons');
        $base = ['studentid' => $this->student->id, 'teacherid' => $this->teacher->id, 'subject' => 'Chemistry',
            'status' => 'confirmed', 'requested_time' => $this->now, 'duration' => 60, 'purchaseid' => 0,
            'flex_state' => 'reserved', 'actual_start' => 0, 'actual_end' => 0, 'sessionid' => 0, 'cmid' => 0,
            'usermodified' => 0, 'timecreated' => $this->now, 'timemodified' => $this->now];
        $late = $DB->insert_record('nit_lesson', (object) ($base + ['confirmed_time' => $this->now - 10 * MINSECS]));
        $soon = $DB->insert_record('nit_lesson', (object) ($base + ['confirmed_time' => $this->now + 10 * MINSECS]));

        $cards = $this->cards();
        $this->assertSame(monitor::NOTSTARTED, $cards['l' . $late]['status']);
        $this->assertTrue($cards['l' . $late]['hasalert']);
        $this->assertFalse($cards['l' . $late]['hasdetail']);
        $this->assertSame(monitor::UPCOMING, $cards['l' . $soon]['status']);
        // A manager of another course does not see them.
        $this->assertArrayNotHasKey('l' . $late, $this->cards([], [(int) $this->getDataGenerator()->create_course()->id]));
    }

    public function test_detail_lists_everyone_and_respects_scope(): void {
        $id = $this->session(-10 * MINSECS, ['teacher_joined_at' => $this->now - 9 * MINSECS,
            'teacher_first_join' => $this->now - 9 * MINSECS]);
        session_manager::record_attendance($id, (int) $this->teacher->id);
        session_manager::record_attendance($id, (int) $this->student->id);
        session_manager::record_attendance($id, (int) get_admin()->id);

        $detail = monitor::detail($id, null, $this->now);
        $roles = array_map(fn($p) => [$p['role'], $p['present']], $detail['people']);
        $this->assertSame([
            [get_string('monitor_role_teacher', 'local_academysessions'), true],
            [get_string('monitor_role_student', 'local_academysessions'), true],
            [get_string('monitor_role_student', 'local_academysessions'), false],
            [get_string('monitor_role_other', 'local_academysessions'), true],
        ], $roles);
        $this->assertSame(get_string('monitor_person_never', 'local_academysessions'), $detail['people'][2]['state']);

        $this->assertNull(monitor::detail($id, [(int) $this->getDataGenerator()->create_course()->id], $this->now));
        $this->assertNull(monitor::detail(999999, null, $this->now));
    }
}

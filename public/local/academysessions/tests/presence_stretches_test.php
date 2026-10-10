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
 * Leaving and coming back: every stretch in the call is kept, the time in counts only
 * the stretches, and how a session ended (when, why, by whom) is recorded.
 *
 * @package    local_academysessions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_academysessions\session_manager
 */
final class presence_stretches_test extends \advanced_testcase {

    /**
     * A session with one student.
     *
     * @return array [sessionid, teacher, student]
     */
    private function session(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $id = (int) session_manager::create_session($course->id, $teacher->id, 'Live', time() - HOURSECS / 2,
            [$student->id], '', 50);
        return [$id, $teacher, $student];
    }

    /**
     * The stretches of a user, oldest first.
     *
     * @param int $sessionid
     * @param int $userid
     * @return \stdClass[]
     */
    private function stretches(int $sessionid, int $userid): array {
        global $DB;
        return array_values($DB->get_records('academy_session_presence', ['sessionid' => $sessionid, 'userid' => $userid],
            'joined_at, id'));
    }

    public function test_each_leave_and_rejoin_is_a_stretch_and_time_out_does_not_count(): void {
        global $DB;
        $this->resetAfterTest();
        [$id, , $student] = $this->session();
        $t = time() - 1000;

        session_manager::record_attendance($id, (int) $student->id, true);
        $DB->set_field('academy_session_presence', 'joined_at', $t, ['sessionid' => $id]);
        session_manager::record_leave($id, (int) $student->id, $t + 300);         // 5 min in.
        session_manager::record_attendance($id, (int) $student->id, true);         // Back...
        $second = $this->stretches($id, (int) $student->id)[1];
        $DB->set_field('academy_session_presence', 'joined_at', $t + 600, ['id' => $second->id]); // ...5 min later.
        $att = session_manager::record_leave($id, (int) $student->id, $t + 720);  // 2 min in.

        $this->assertCount(2, $this->stretches($id, (int) $student->id));
        // 7 minutes in the call, not the 12 between the first join and the last leave.
        $this->assertSame(420, (int) $att->duration_seconds);
        $this->assertSame(420, session_manager::presence_seconds($id, (int) $student->id, $t + 2000));
    }

    public function test_a_second_join_while_in_does_not_open_another_stretch(): void {
        $this->resetAfterTest();
        [$id, , $student] = $this->session();
        session_manager::record_attendance($id, (int) $student->id, true);
        session_manager::record_attendance($id, (int) $student->id, true);  // Second tab / repeated ping.
        session_manager::presence_open($id, (int) $student->id);
        $this->assertCount(1, $this->stretches($id, (int) $student->id));
    }

    public function test_end_records_how_it_ended_and_closes_everyone(): void {
        global $DB;
        $this->resetAfterTest();
        [$id, $teacher, $student] = $this->session();
        $DB->set_field('academy_live_sessions', 'teacher_joined_at', time(), ['id' => $id]);
        session_manager::record_attendance($id, (int) $student->id, true);

        session_manager::end_session($id, 'teacher', (int) $teacher->id);
        $s = $DB->get_record('academy_live_sessions', ['id' => $id]);
        $this->assertSame('ended', $s->status);
        $this->assertSame('teacher', $s->end_reason);
        $this->assertSame((int) $teacher->id, (int) $s->ended_by);
        $this->assertGreaterThan(0, (int) $s->ended_at);
        $this->assertNull($s->teacher_joined_at);
        $this->assertGreaterThan(0, (int) $s->teacher_last_leave);
        $this->assertNotNull($this->stretches($id, (int) $student->id)[0]->left_at);

        // Ending again (the scheduled task after the teacher) keeps the first answer.
        session_manager::end_session($id, 'schedule');
        $this->assertSame('teacher', $DB->get_field('academy_live_sessions', 'end_reason', ['id' => $id]));
    }

    public function test_default_end_is_the_schedule(): void {
        global $DB;
        $this->resetAfterTest();
        [$id] = $this->session();
        session_manager::end_session($id);
        $s = $DB->get_record('academy_live_sessions', ['id' => $id]);
        $this->assertSame('schedule', $s->end_reason);
        $this->assertNull($s->ended_by);
    }

    public function test_delete_removes_the_stretches(): void {
        global $DB;
        $this->resetAfterTest();
        [$id, , $student] = $this->session();
        session_manager::record_attendance($id, (int) $student->id, true);
        session_manager::delete_session($id);
        $this->assertSame(0, $DB->count_records('academy_session_presence', ['sessionid' => $id]));
    }
}

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

namespace mod_jitsi;

use local_academysessions\session_manager;
use mod_jitsi\local\presence;

/**
 * Tests for the teacher presence / end room logic shared by web + mobile.
 *
 * @package    mod_jitsi
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_jitsi\local\presence
 */
final class presence_test extends \advanced_testcase {

    /**
     * A course with a teacher, a student and a Jitsi room linked to a session.
     *
     * @return array [course, cm, teacher, student, sessionid]
     */
    private function linked_room(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $jitsi = $gen->create_module('jitsi', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('jitsi', $jitsi->cmid, 0, false, MUST_EXIST);
        $sessionid = session_manager::create_session($course->id, $teacher->id, 'Live', time() + 600,
            [$student->id], '', 50, null, $jitsi->id);
        return [$course, $cm, $teacher, $student, $sessionid];
    }

    public function test_set_present_stamps_gate_attendance_once_and_clears(): void {
        global $DB;
        $this->resetAfterTest();
        [, $cm, $teacher, , $sessionid] = $this->linked_room();

        $session = presence::set($cm, (int) $teacher->id, true);
        $this->assertSame((int) $sessionid, (int) $session->id);
        $this->assertNotEmpty($DB->get_field('academy_live_sessions', 'teacher_joined_at', ['id' => $sessionid]));
        presence::set($cm, (int) $teacher->id, true);
        $this->assertSame(1, $DB->count_records('academy_session_attendance',
            ['sessionid' => $sessionid, 'userid' => $teacher->id]));

        presence::set($cm, (int) $teacher->id, false);
        $this->assertNull($DB->get_field('academy_live_sessions', 'teacher_joined_at', ['id' => $sessionid]));
    }

    public function test_first_join_is_kept_when_the_teacher_leaves_and_rejoins(): void {
        global $DB;
        $this->resetAfterTest();
        [, $cm, $teacher, , $sessionid] = $this->linked_room();

        presence::set($cm, (int) $teacher->id, true);
        $first = (int) $DB->get_field('academy_live_sessions', 'teacher_first_join', ['id' => $sessionid]);
        $this->assertGreaterThan(0, $first);

        // Leaving clears the gate but not the first join; a later rejoin does not move it.
        presence::set($cm, (int) $teacher->id, false);
        $this->waitForSecond();
        presence::set($cm, (int) $teacher->id, true);
        $row = $DB->get_record('academy_live_sessions', ['id' => $sessionid]);
        $this->assertSame($first, (int) $row->teacher_first_join);
        $this->assertGreaterThan($first, (int) $row->teacher_joined_at);
    }

    public function test_site_admin_joining_is_not_the_teacher_arriving(): void {
        global $DB;
        $this->resetAfterTest();
        [, $cm, , , $sessionid] = $this->linked_room();

        presence::set($cm, (int) get_admin()->id, true);
        $session = $DB->get_record('academy_live_sessions', ['id' => $sessionid]);
        $this->assertNull($session->teacher_first_join);
        // Nor does it let the students in: the gate stays closed.
        $this->assertNull($session->teacher_joined_at);
        // The admin is recorded as being in the call (a stretch), then out.
        $this->assertSame(1, $DB->count_records_select('academy_session_presence',
            'sessionid = ? AND userid = ? AND left_at IS NULL', [$sessionid, get_admin()->id]));
        presence::set($cm, (int) get_admin()->id, false);
        $this->assertSame(0, $DB->count_records_select('academy_session_presence',
            'sessionid = ? AND userid = ? AND left_at IS NULL', [$sessionid, get_admin()->id]));
    }

    public function test_teacher_leaving_and_coming_back_is_kept(): void {
        global $DB;
        $this->resetAfterTest();
        [, $cm, $teacher, , $sessionid] = $this->linked_room();

        presence::set($cm, (int) $teacher->id, true);
        presence::set($cm, (int) $teacher->id, false);
        $this->assertGreaterThan(0, (int) $DB->get_field('academy_live_sessions', 'teacher_last_leave', ['id' => $sessionid]));
        presence::set($cm, (int) $teacher->id, true);
        $this->assertSame(2, $DB->count_records('academy_session_presence',
            ['sessionid' => $sessionid, 'userid' => $teacher->id]));
    }

    public function test_set_present_on_standalone_room_stores_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $jitsi = $gen->create_module('jitsi', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('jitsi', $jitsi->cmid, 0, false, MUST_EXIST);

        $this->assertNull(presence::set($cm, (int) $teacher->id, true));
        $this->assertSame(0, $DB->count_records('academy_session_attendance'));
    }

    public function test_is_moderator_pins_linked_room_to_session_teacher(): void {
        $this->resetAfterTest();
        [$course, $cm, $teacher, $student] = $this->linked_room();
        $otherteacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->assertTrue(presence::is_moderator($cm, (int) $teacher->id));
        $this->assertFalse(presence::is_moderator($cm, (int) $student->id));
        $this->assertFalse(presence::is_moderator($cm, (int) $otherteacher->id));
    }

    public function test_end_room_sets_the_ended_flag(): void {
        $this->resetAfterTest();
        $this->assertSame(0, presence::room_ended_at(123456));
        $at = presence::end_room(123456);
        $this->assertSame($at, presence::room_ended_at(123456));
        $this->assertEquals($at, get_config('mod_jitsi', 'ended_123456'));
    }
}

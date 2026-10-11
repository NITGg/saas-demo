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

use mod_jitsi\external\get_session_info;
use mod_jitsi\local\access;
use mod_jitsi\local\presence;
use mod_jitsi\local\roomlog;

/**
 * A course room's waiting room (the Jitsi lobby): a student only gets a key while a host
 * is in the call (so someone can admit them from inside the meeting); teachers and admins
 * never wait; the app is told to use the lobby and who the hosts are. And the room's
 * attendance log keeps every stretch in the call.
 *
 * @package    mod_jitsi
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_jitsi\local\access
 * @covers     \mod_jitsi\local\roomlog
 * @covers     \mod_jitsi\external\get_session_info
 */
final class waiting_room_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;
    /** @var \stdClass */
    private $teacher;
    /** @var \stdClass */
    private $student;
    /** @var \stdClass the jitsi row */
    private $jitsi;
    /** @var int */
    private $cmid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('jitsi_jwt_app_secret', 'test-secret', 'local_academysessions');
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->student = $gen->create_and_enrol($this->course, 'student');
        $this->jitsi = $gen->create_module('jitsi', ['course' => $this->course->id, 'lobby_enabled' => 1]);
        $this->cmid = (int) $this->jitsi->cmid;
    }

    /**
     * The access decision for a user.
     *
     * @param \stdClass $user
     * @return \stdClass
     */
    private function check(\stdClass $user): \stdClass {
        $this->setUser($user);
        return access::check(get_fast_modinfo($this->course->id, $user->id)->get_cm($this->cmid), (int) $user->id);
    }

    public function test_student_waits_until_a_host_is_in_the_call(): void {
        $d = $this->check($this->student);
        $this->assertFalse($d->allowed);
        $this->assertSame(access::WAITING, $d->code);
        $r = get_session_info::execute($this->cmid);
        $this->assertSame('', $r['jwt']);

        // The teacher joins: the student gets a key (the lobby then holds them until admitted).
        roomlog::joined((int) $this->jitsi->id, (int) $this->teacher->id, true);
        $this->assertTrue($this->check($this->student)->allowed);

        // The teacher leaves: the student waits again.
        roomlog::left((int) $this->jitsi->id, (int) $this->teacher->id);
        $this->assertSame(access::WAITING, $this->check($this->student)->code);
    }

    public function test_a_host_stretch_left_open_for_hours_does_not_count(): void {
        roomlog::joined((int) $this->jitsi->id, (int) $this->teacher->id, true, time() - roomlog::HOST_STALE - 60);
        $this->assertFalse(roomlog::host_in((int) $this->jitsi->id));
        $this->assertSame(access::WAITING, $this->check($this->student)->code);
    }

    public function test_a_student_in_the_call_does_not_open_the_room(): void {
        roomlog::joined((int) $this->jitsi->id, (int) $this->student->id, false);
        $this->assertSame(access::WAITING, $this->check($this->student)->code);
    }

    public function test_teacher_and_admin_never_wait(): void {
        $this->assertTrue($this->check($this->teacher)->allowed);
        $this->assertTrue($this->check(get_admin())->allowed);
    }

    public function test_room_without_a_waiting_room_lets_students_straight_in(): void {
        global $DB;
        $DB->set_field('jitsi', 'lobby_enabled', 0, ['id' => $this->jitsi->id]);
        $this->assertTrue($this->check($this->student)->allowed);
        $this->setUser($this->teacher);
        $this->assertFalse(get_session_info::execute($this->cmid)['lobby']);
    }

    public function test_the_app_is_told_to_use_the_lobby_and_who_the_hosts_are(): void {
        $this->setUser($this->teacher);
        $r = get_session_info::execute($this->cmid);
        $this->assertTrue($r['lobby']);
        $this->assertTrue($r['auto_admit']);
        $this->assertContains(fullname($this->teacher), $r['host_names']);
        $this->assertContains(fullname(get_admin()), $r['host_names']);
        $this->assertNotContains(fullname($this->student), $r['host_names']);
    }

    public function test_attendance_log_keeps_every_stretch(): void {
        $id = (int) $this->jitsi->id;
        $t = time() - 3600;
        roomlog::joined($id, (int) $this->teacher->id, true, $t);
        roomlog::joined($id, (int) $this->student->id, false, $t + 60);
        roomlog::joined($id, (int) $this->student->id, false, $t + 90);   // Second tab: same stretch.
        roomlog::left($id, (int) $this->student->id, $t + 600);
        roomlog::joined($id, (int) $this->student->id, false, $t + 900);
        roomlog::left($id, (int) $this->student->id, $t + 1200);
        presence::end_room($this->cmid);                                   // Closes the teacher's stretch.

        $day = roomlog::day($id, userdate($t, '%Y-%m-%d'));
        $people = array_column($day['people'], null, 'userid');
        $this->assertTrue($people[(int) $this->teacher->id]['host']);
        $this->assertFalse($people[(int) $this->teacher->id]['open']);
        $student = $people[(int) $this->student->id];
        $this->assertSame(2, $student['visits']);
        $this->assertSame(540 + 300, $student['seconds']);
        // Hosts first.
        $this->assertSame((int) $this->teacher->id, $day['people'][0]['userid']);
    }
}

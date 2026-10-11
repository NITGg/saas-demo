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
use mod_jitsi\local\admission;
use mod_jitsi\local\presence;
use mod_jitsi\local\roomlog;

/**
 * A course room's waiting room (kept by Moodle): a student waits until a teacher lets
 * them in, gets no key before that, and teachers / admins never wait. And the room's
 * attendance log keeps every stretch in the call.
 *
 * @package    mod_jitsi
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_jitsi\local\admission
 * @covers     \mod_jitsi\local\access
 * @covers     \mod_jitsi\local\roomlog
 */
final class admission_test extends \advanced_testcase {

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

    public function test_student_waits_and_gets_no_key_until_let_in(): void {
        $d = $this->check($this->student);
        $this->assertFalse($d->allowed);
        $this->assertSame(access::WAITING_APPROVAL, $d->code);

        // Opening the room (mobile WS) puts the request in, with no key.
        $r = get_session_info::execute($this->cmid);
        $this->assertFalse($r['available']);
        $this->assertSame('', $r['jwt']);
        $this->assertSame('waitingforapproval', $r['access_code']);
        $this->assertSame([(int) $this->student->id], array_column(admission::waiting((int) $this->jitsi->id), 'userid'));

        $this->assertTrue(admission::decide((int) $this->jitsi->id, (int) $this->student->id, true, (int) $this->teacher->id));
        $this->assertTrue($this->check($this->student)->allowed);
        $this->assertSame([], admission::waiting((int) $this->jitsi->id));
    }

    public function test_teacher_and_admin_never_wait(): void {
        $this->assertTrue($this->check($this->teacher)->allowed);
        $this->assertTrue($this->check(get_admin())->allowed);
        $this->assertSame([], admission::waiting((int) $this->jitsi->id));
    }

    public function test_turned_away_then_asking_again(): void {
        admission::request((int) $this->jitsi->id, (int) $this->student->id, $this->cmid);
        admission::decide((int) $this->jitsi->id, (int) $this->student->id, false, (int) $this->teacher->id);
        $this->assertSame(access::DENIED, $this->check($this->student)->code);

        $this->assertSame(admission::WAITING, admission::request((int) $this->jitsi->id, (int) $this->student->id, $this->cmid));
        $this->assertSame(access::WAITING_APPROVAL, $this->check($this->student)->code);
    }

    public function test_a_let_in_ends_with_the_room_or_after_some_hours(): void {
        global $DB;
        admission::request((int) $this->jitsi->id, (int) $this->student->id, $this->cmid);
        admission::decide((int) $this->jitsi->id, (int) $this->student->id, true, (int) $this->teacher->id);
        $this->assertTrue($this->check($this->student)->allowed);

        // Hours later the student asks again.
        $DB->set_field('jitsi_admission', 'timedecided', time() - admission::ADMIT_FOR - 1, ['jitsiid' => $this->jitsi->id]);
        $this->assertSame(access::WAITING_APPROVAL, $this->check($this->student)->code);
    }

    public function test_room_without_a_waiting_room_lets_students_straight_in(): void {
        global $DB;
        $DB->set_field('jitsi', 'lobby_enabled', 0, ['id' => $this->jitsi->id]);
        $this->assertTrue($this->check($this->student)->allowed);
    }

    public function test_only_a_host_answers(): void {
        $this->assertTrue(presence::is_moderator(get_coursemodule_from_id('jitsi', $this->cmid), (int) $this->teacher->id));
        $this->assertFalse(presence::is_moderator(get_coursemodule_from_id('jitsi', $this->cmid), (int) $this->student->id));
        // Nothing to answer when nobody asked.
        $this->assertFalse(admission::decide((int) $this->jitsi->id, (int) $this->student->id, true, (int) $this->teacher->id));
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

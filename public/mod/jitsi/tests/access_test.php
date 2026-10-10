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
use mod_jitsi\external\get_session_info;
use mod_jitsi\local\access;
use mod_jitsi\local\presence;

/**
 * Who may enter a Jitsi room and as what: one rule for the web page and the mobile
 * APIs. Regression tests for the mobile holes found in task 19: another teacher of
 * the course got a moderator JWT for a session that was not theirs, and a student
 * got the room JWT while still waiting for the teacher.
 *
 * @package    mod_jitsi
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_jitsi\local\access
 * @covers     \mod_jitsi\external\get_session_info
 */
final class access_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;
    /** @var \stdClass the session's teacher */
    private $teacher;
    /** @var \stdClass another editing teacher of the same course */
    private $otherteacher;
    /** @var \stdClass invited to the session */
    private $student;
    /** @var \stdClass enrolled in the course, not invited */
    private $stranger;
    /** @var int */
    private $cmid;
    /** @var int */
    private $sessionid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('jitsi_jwt_app_secret', 'test-secret', 'local_academysessions');
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->otherteacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->student = $gen->create_and_enrol($this->course, 'student');
        $this->stranger = $gen->create_and_enrol($this->course, 'student');
        $jitsi = $gen->create_module('jitsi', ['course' => $this->course->id]);
        $this->cmid = (int) $jitsi->cmid;
        // Started a minute ago, 50 minutes long; the teacher is not in the call yet.
        $this->sessionid = (int) session_manager::create_session($this->course->id, $this->teacher->id, 'Live',
            time() - 60, [$this->student->id], '', 50, null, $jitsi->id);
        session_manager::start_session($this->sessionid);
    }

    /**
     * The access decision for a user, with the room loaded for them.
     *
     * @param \stdClass $user
     * @param int|null $now
     * @return \stdClass
     */
    private function check(\stdClass $user, ?int $now = null): \stdClass {
        // jitsi_cm_info_dynamic() works on $USER, as on a real request.
        $this->setUser($user);
        $cm = get_fast_modinfo($this->course->id, $user->id)->get_cm($this->cmid);
        return access::check($cm, (int) $user->id, $now);
    }

    /**
     * Mark the session's teacher as in the call.
     */
    private function teacher_joins(): void {
        presence::set(get_coursemodule_from_id('jitsi', $this->cmid), (int) $this->teacher->id, true);
    }

    /**
     * The JWT payload.
     *
     * @param string $jwt
     * @return array
     */
    private function payload(string $jwt): array {
        return json_decode(base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')), true);
    }

    public function test_session_teacher_is_the_moderator_and_never_waits(): void {
        $d = $this->check($this->teacher);
        $this->assertTrue($d->allowed);
        $this->assertTrue($d->moderator);
        // Even long before the start the teacher may open the room.
        $d = $this->check($this->teacher, time() - 5 * HOURSECS);
        $this->assertTrue($d->allowed);
    }

    public function test_another_teacher_of_the_course_is_refused(): void {
        $d = $this->check($this->otherteacher);
        $this->assertFalse($d->allowed);
        $this->assertFalse($d->moderator);
        $this->assertSame(access::NOT_ALLOWED, $d->code);
        $this->assertFalse(presence::is_moderator(get_coursemodule_from_id('jitsi', $this->cmid),
            (int) $this->otherteacher->id));
    }

    public function test_invited_student_waits_for_the_teacher_then_enters_as_member(): void {
        $d = $this->check($this->student);
        $this->assertFalse($d->allowed);
        $this->assertSame(access::WAITING, $d->code);

        $this->teacher_joins();
        $d = $this->check($this->student);
        $this->assertTrue($d->allowed);
        $this->assertFalse($d->moderator);

        // The teacher leaves: the student is held out again.
        presence::set(get_coursemodule_from_id('jitsi', $this->cmid), (int) $this->teacher->id, false);
        $this->assertSame(access::WAITING, $this->check($this->student)->code);
    }

    public function test_student_window_opens_half_an_hour_before_and_closes_at_the_end(): void {
        global $DB;
        $this->teacher_joins();
        // Starts in 10 minutes (so the room is open right now, also for Moodle's own
        // visibility); the checks below move the clock with $now.
        $start = time() + 10 * MINSECS;
        $DB->set_field('academy_live_sessions', 'start_time', $start, ['id' => $this->sessionid]);

        $d = $this->check($this->student, $start - access::OPEN_BEFORE - 120);
        $this->assertSame(access::NOT_OPEN, $d->code);
        $this->assertSame(2, $d->opensin);
        $this->assertTrue($this->check($this->student, $start - access::OPEN_BEFORE + 1)->allowed);
        $this->assertSame(access::ENDED, $this->check($this->student, $start + 50 * MINSECS + 1)->code);
    }

    public function test_ended_and_cancelled_sessions_are_closed_for_everyone(): void {
        global $DB;
        foreach (['ended', 'cancelled'] as $status) {
            $DB->set_field('academy_live_sessions', 'status', $status, ['id' => $this->sessionid]);
            $this->assertSame(access::ENDED, $this->check($this->teacher)->code, $status);
            $this->assertSame(access::ENDED, $this->check($this->student)->code, $status);
        }
    }

    public function test_site_admin_moderates_unless_invited_as_student(): void {
        $this->setAdminUser();
        $admin = get_admin();
        $d = $this->check($admin);
        $this->assertTrue($d->allowed);
        $this->assertTrue($d->moderator);
    }

    public function test_standalone_room_follows_the_moderate_capability(): void {
        $gen = $this->getDataGenerator();
        $jitsi = $gen->create_module('jitsi', ['course' => $this->course->id]);
        $this->setUser($this->otherteacher);
        $cm = get_fast_modinfo($this->course->id, $this->otherteacher->id)->get_cm($jitsi->cmid);
        $d = access::check($cm, (int) $this->otherteacher->id);
        $this->assertTrue($d->allowed);
        $this->assertTrue($d->moderator);
        $this->setUser($this->stranger);
        $cm = get_fast_modinfo($this->course->id, $this->stranger->id)->get_cm($jitsi->cmid);
        $d = access::check($cm, (int) $this->stranger->id);
        $this->assertTrue($d->allowed);
        $this->assertFalse($d->moderator);
    }

    public function test_ws_gives_another_teacher_no_jwt(): void {
        $this->setUser($this->otherteacher);
        $r = get_session_info::execute($this->cmid);
        $this->assertFalse($r['available']);
        $this->assertFalse($r['is_teacher']);
        $this->assertSame('', $r['jwt']);
    }

    public function test_ws_gives_a_waiting_student_no_jwt_and_records_no_attendance(): void {
        global $DB;
        $this->setUser($this->student);
        $r = get_session_info::execute($this->cmid);
        $this->assertFalse($r['available']);
        $this->assertSame('', $r['jwt']);
        $this->assertSame(get_string('waitingforteacher', 'jitsi'), $r['available_info']);
        $this->assertFalse($DB->record_exists('academy_session_attendance',
            ['sessionid' => $this->sessionid, 'userid' => $this->student->id]));
    }

    public function test_ws_lets_the_student_in_once_the_teacher_is_there(): void {
        global $DB;
        $this->teacher_joins();
        $this->setUser($this->student);
        $r = get_session_info::execute($this->cmid);
        $this->assertTrue($r['available']);
        $this->assertFalse($this->payload($r['jwt'])['context']['user']['moderator']);
        $this->assertTrue($DB->record_exists('academy_session_attendance',
            ['sessionid' => $this->sessionid, 'userid' => $this->student->id]));
    }

    public function test_ws_gives_the_session_teacher_a_moderator_jwt(): void {
        $this->setUser($this->teacher);
        $r = get_session_info::execute($this->cmid);
        $this->assertTrue($r['available']);
        $this->assertTrue($r['is_teacher']);
        $this->assertTrue($this->payload($r['jwt'])['context']['user']['moderator']);
    }
}

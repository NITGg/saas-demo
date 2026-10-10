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
 * Tests for the live-session mobile service, attendance leave/rejoin and recordings.
 *
 * @package    local_academysessions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_academysessions\mobile_service
 * @covers     \local_academysessions\session_manager
 * @covers     \local_academysessions\recordings
 */
final class mobile_service_test extends \advanced_testcase {

    /**
     * Course + teacher + student + a session starting in 10 minutes.
     *
     * @param bool $withjitsi link a Jitsi room
     * @return array [course, teacher, student, sessionid, cmid|null]
     */
    private function fixture(bool $withjitsi = true): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $jitsiid = 0;
        $cmid = null;
        if ($withjitsi && \core_component::get_component_directory('mod_jitsi')) {
            $jitsi = $gen->create_module('jitsi', ['course' => $course->id]);
            $jitsiid = (int) $jitsi->id;
            $cmid = (int) $jitsi->cmid;
        }
        $this->setUser($teacher);
        $res = mobile_service::create((int) $teacher->id, (int) $course->id, 'Live', time() + 600,
            (string) $student->id, '', 50, 0, $jitsiid);
        return [$course, $teacher, $student, $res['sessionid'], $cmid];
    }

    public function test_create_returns_cmid_and_export_adds_fields(): void {
        $this->resetAfterTest();
        [, , , $sessionid, $cmid] = $this->fixture();
        $this->assertGreaterThan(0, $sessionid);
        $session = mobile_service::require_session($sessionid);
        $payload = mobile_service::export_session($session,
            mobile_service::jitsi_cmids([(int) $session->jitsiid]));
        $this->assertSame($cmid, $payload->cmid);
        $this->assertFalse($payload->teacher_present);
        $this->assertSame($session->title, $payload->title);
    }

    public function test_create_rejects_jitsi_of_another_course(): void {
        $this->resetAfterTest();
        [$course, $teacher] = $this->fixture(false);
        try {
            mobile_service::create((int) $teacher->id, (int) $course->id, 'X', time(), '', '', 50, 0, 999999);
            $this->fail('foreign jitsi accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_invalidvalue', $e->errorcode);
        }
    }

    public function test_create_rejects_students_not_enrolled(): void {
        $this->resetAfterTest();
        [$course, $teacher] = $this->fixture(false);
        $outsider = $this->getDataGenerator()->create_user();
        try {
            mobile_service::create((int) $teacher->id, (int) $course->id, 'X', time() + 600,
                (string) $outsider->id);
            $this->fail('student outside the course accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_studentnotenrolled', $e->errorcode);
        }
    }

    public function test_only_the_session_teacher_may_change_it(): void {
        $this->resetAfterTest();
        [$course, , , $sessionid] = $this->fixture(false);
        $session = mobile_service::require_session($sessionid);
        mobile_service::require_session_owner($session); // Its own teacher (current user) passes.

        $other = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($other);
        try {
            mobile_service::require_session_owner($session);
            $this->fail('another teacher may change the session');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_notsessionteacher', $e->errorcode);
        }

        $this->setAdminUser();
        mobile_service::require_session_owner($session); // Site admin passes.
    }

    public function test_teacher_sessions_course_needs_capability(): void {
        $this->resetAfterTest();
        [$course, $teacher, $student, $sessionid] = $this->fixture();
        $this->assertCount(1, mobile_service::teacher_sessions((int) $teacher->id, (int) $course->id));
        $this->assertSame((string) $sessionid,
            (string) mobile_service::teacher_sessions((int) $teacher->id)[0]->id);

        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        mobile_service::teacher_sessions((int) $student->id, (int) $course->id);
    }

    public function test_join_before_the_teacher_records_no_attendance(): void {
        global $DB;
        $this->resetAfterTest();
        [, , $student, $sessionid, $cmid] = $this->fixture();
        if (!$cmid) {
            $this->markTestSkipped('mod_jitsi is not installed');
        }

        $data = mobile_service::join($sessionid, (int) $student->id);
        $this->assertFalse($data['teacher_present']);
        $this->assertFalse($DB->record_exists('academy_session_attendance',
            ['sessionid' => $sessionid, 'userid' => $student->id]));
    }

    public function test_join_without_a_jitsi_room_records_attendance_at_once(): void {
        global $DB;
        $this->resetAfterTest();
        [, , $student, $sessionid] = $this->fixture(false);

        mobile_service::join($sessionid, (int) $student->id);
        $this->assertTrue($DB->record_exists('academy_session_attendance',
            ['sessionid' => $sessionid, 'userid' => $student->id]));
    }

    public function test_join_leave_rejoin(): void {
        global $DB;
        $this->resetAfterTest();
        [, $teacher, $student, $sessionid, $cmid] = $this->fixture();
        if ($cmid) {
            // The teacher is in the call, so the student's join counts.
            \mod_jitsi\local\presence::set(get_coursemodule_from_id('jitsi', $cmid), (int) $teacher->id, true);
        }

        $data = mobile_service::join($sessionid, (int) $student->id);
        $this->assertSame($sessionid, $data['sessionid']);

        $left = mobile_service::leave($sessionid, (int) $student->id);
        $this->assertGreaterThanOrEqual(0, $left['duration_seconds']);
        $this->assertNotEmpty($DB->get_field('academy_session_attendance', 'left_at',
            ['sessionid' => $sessionid, 'userid' => $student->id]));

        mobile_service::join($sessionid, (int) $student->id);
        $this->assertNull($DB->get_field('academy_session_attendance', 'left_at',
            ['sessionid' => $sessionid, 'userid' => $student->id]));

        // The teacher is not on the student list.
        try {
            mobile_service::join($sessionid, (int) $teacher->id);
            $this->fail('teacher joined as student');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_notallowed', $e->errorcode);
        }
    }

    public function test_leave_without_join_fails(): void {
        $this->resetAfterTest();
        [, $teacher, , $sessionid] = $this->fixture();
        try {
            mobile_service::leave($sessionid, (int) $teacher->id);
            $this->fail('leave without join accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_notjoined', $e->errorcode);
        }
    }

    public function test_record_attendance_default_keeps_left_at(): void {
        global $DB;
        $this->resetAfterTest();
        [, , $student, $sessionid] = $this->fixture(false);
        session_manager::record_attendance($sessionid, $student->id);
        session_manager::record_leave($sessionid, $student->id);
        session_manager::record_attendance($sessionid, $student->id);
        $this->assertNotEmpty($DB->get_field('academy_session_attendance', 'left_at',
            ['sessionid' => $sessionid, 'userid' => $student->id]));
    }

    public function test_join_ended_session_fails(): void {
        $this->resetAfterTest();
        [, , $student, $sessionid] = $this->fixture(false);
        session_manager::end_session($sessionid);
        try {
            mobile_service::join($sessionid, (int) $student->id);
            $this->fail('joined an ended session');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_sessionended', $e->errorcode);
        }
    }

    public function test_recordings_hidden_from_students_until_session_over(): void {
        global $DB;
        $this->resetAfterTest();
        [, $teacher, $student, $sessionid, $cmid] = $this->fixture();
        $DB->insert_record('academy_session_recordings', (object) [
            'sessionid' => $sessionid, 'cmid' => $cmid, 'vimeo_videoid' => '123', 'title' => 'Rec',
            'status' => 'ready', 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $staff = mobile_service::recordings((int) $teacher->id, $sessionid);
        $this->assertTrue($staff['available']);
        $this->assertSame('https://player.vimeo.com/video/123', $staff['recordings'][0]['playback_url']);

        $this->setUser($student);
        $before = mobile_service::recordings((int) $student->id, $sessionid);
        $this->assertFalse($before['available']);
        $this->assertSame([], $before['recordings']);

        session_manager::end_session($sessionid);
        $this->assertCount(1, mobile_service::recordings((int) $student->id, $sessionid)['recordings']);
    }

    public function test_visible_to_students_rules(): void {
        $now = 1000000;
        $this->assertTrue(recordings::visible_to_students(null, $now));
        $live = (object) ['status' => 'live', 'start_time' => $now - 60, 'duration' => 50];
        $this->assertFalse(recordings::visible_to_students($live, $now));
        $past = (object) ['status' => 'live', 'start_time' => $now - 3600, 'duration' => 50];
        $this->assertTrue(recordings::visible_to_students($past, $now));
        $ended = (object) ['status' => 'ended', 'start_time' => $now, 'duration' => 50];
        $this->assertTrue(recordings::visible_to_students($ended, $now));
    }

    public function test_rows_skip_recordings_without_video(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->insert_record('academy_session_recordings', (object) [
            'cmid' => 77, 'vimeo_videoid' => null, 'title' => 'Syncing', 'status' => 'syncing',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('academy_session_recordings', (object) [
            'cmid' => 77, 'vimeo_videoid' => '555', 'title' => 'Ready', 'status' => 'ready',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $list = recordings::list_for(77);
        $this->assertCount(1, $list);
        $this->assertSame('555', $list[0]['vimeo_videoid']);
        $this->assertSame([], recordings::list_for(0, 0));
    }
}

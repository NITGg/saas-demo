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
use mod_jitsi\local\access;
use mod_jitsi\local\presence;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/jitsi/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

/**
 * A Jitsi activity scheduled from its settings ("Session time"): a course session for
 * every student of the course, open from half an hour before the start, hosted by any
 * teacher of the course; rescheduling, taking the time off, the form's checks, who may
 * join (not a guest), and the recordings after the call.
 *
 * @package    mod_jitsi
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::jitsi_save_schedule
 * @covers     ::jitsi_release_session
 * @covers     ::jitsi_schedule_errors
 * @covers     ::jitsi_recordings_for_user
 * @covers     \mod_jitsi\local\access
 * @covers     \local_academysessions\session_manager
 */
final class schedule_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;
    /** @var \stdClass the teacher who schedules */
    private $teacher;
    /** @var \stdClass another editing teacher of the course */
    private $otherteacher;
    /** @var \stdClass */
    private $student;
    /** @var \stdClass */
    private $student2;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('jitsi_jwt_app_secret', 'test-secret', 'local_academysessions');
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->otherteacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->student = $gen->create_and_enrol($this->course, 'student');
        $this->student2 = $gen->create_and_enrol($this->course, 'student');
    }

    /**
     * A Jitsi activity created by the teacher from the settings form.
     *
     * @param int $start 0 = no session time
     * @param int $duration minutes
     * @return \stdClass the instance (with ->cmid)
     */
    private function room(int $start, int $duration = 45): \stdClass {
        $this->setUser($this->teacher);
        return $this->getDataGenerator()->create_module('jitsi', ['course' => $this->course->id, 'name' => 'Algebra live',
            'sessionstart' => $start, 'sessionduration' => $duration]);
    }

    /**
     * Save the room's settings again, as the form does.
     *
     * @param \stdClass $jitsi
     * @param int $start
     * @param int $duration
     */
    private function save(\stdClass $jitsi, int $start, int $duration = 45): void {
        global $DB;
        $this->setUser($this->teacher);
        $data = $DB->get_record('jitsi', ['id' => $jitsi->id]);
        $data->instance = $jitsi->id;
        $data->coursemodule = $jitsi->cmid;
        $data->sessionstart = $start;
        $data->sessionduration = $duration;
        jitsi_update_instance($data);
    }

    /**
     * The room's session.
     *
     * @param \stdClass $jitsi
     * @return \stdClass|false
     */
    private function session(\stdClass $jitsi) {
        global $DB;
        return $DB->get_record('academy_live_sessions', ['jitsiid' => $jitsi->id]);
    }

    /**
     * The students on a session's list.
     *
     * @param int $sessionid
     * @return int[]
     */
    private function listed(int $sessionid): array {
        global $DB;
        $ids = array_map('intval', $DB->get_fieldset_select('academy_session_students', 'userid', 'sessionid = ?',
            [$sessionid]));
        sort($ids);
        return $ids;
    }

    /**
     * The access decision for a user, with the room loaded for them.
     *
     * @param \stdClass $jitsi
     * @param \stdClass $user
     * @param int|null $now
     * @return \stdClass
     */
    private function check(\stdClass $jitsi, \stdClass $user, ?int $now = null): \stdClass {
        $this->setUser($user);
        $cm = get_fast_modinfo($this->course->id, $user->id)->get_cm($jitsi->cmid);
        return access::check($cm, (int) $user->id, $now);
    }

    public function test_scheduling_makes_a_course_session_for_every_student(): void {
        $start = time() + 2 * HOURSECS;
        $jitsi = $this->room($start);

        $session = $this->session($jitsi);
        $this->assertNotFalse($session);
        $this->assertSame(session_manager::AUDIENCE_COURSE, $session->audience);
        $this->assertSame($start, (int) $session->start_time);
        $this->assertSame(45, (int) $session->duration);
        $this->assertSame('scheduled', $session->status);
        $this->assertSame((int) $this->teacher->id, (int) $session->teacherid);
        $expected = [(int) $this->student->id, (int) $this->student2->id];
        sort($expected);
        $this->assertSame($expected, $this->listed((int) $session->id), 'the students, not the teachers');
    }

    public function test_a_room_without_a_time_has_no_session(): void {
        $this->assertFalse($this->session($this->room(0)));
    }

    public function test_the_room_stays_on_the_course_page_before_its_time_and_says_when(): void {
        $start = time() + 2 * HOURSECS;
        $jitsi = $this->room($start);
        $this->setUser($this->student);
        $cm = get_fast_modinfo($this->course->id, $this->student->id)->get_cm($jitsi->cmid);
        $this->assertTrue($cm->uservisible);
        $this->assertTrue($cm->available);
        $this->assertStringContainsString(s(userdate($start, get_string('strftimedaydatetime', 'langconfig'))),
            $cm->afterlink);
        // The call itself is not open yet.
        $this->assertSame(access::NOT_OPEN, $this->check($jitsi, $this->student)->code);
    }

    public function test_a_student_enrolled_later_gets_in_and_on_the_list(): void {
        $jitsi = $this->room(time() - MINSECS);
        $sessionid = (int) $this->session($jitsi)->id;
        $late = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->assertNotContains((int) $late->id, $this->listed($sessionid));

        $this->assertSame(access::WAITING, $this->check($jitsi, $late)->code, 'in, waiting for the teacher');
        $this->assertContains((int) $late->id, $this->listed($sessionid));

        presence::set(get_coursemodule_from_id('jitsi', $jitsi->cmid), (int) $this->teacher->id, true);
        $this->assertTrue($this->check($jitsi, $late)->allowed);
    }

    public function test_any_teacher_of_the_course_hosts_and_lets_the_students_in(): void {
        $jitsi = $this->room(time() - MINSECS);
        $d = $this->check($jitsi, $this->otherteacher);
        $this->assertTrue($d->allowed);
        $this->assertTrue($d->moderator);

        $cm = get_coursemodule_from_id('jitsi', $jitsi->cmid);
        presence::set($cm, (int) $this->otherteacher->id, true);
        $this->assertTrue($this->check($jitsi, $this->student)->allowed);
    }

    public function test_the_gate_stays_open_while_another_teacher_is_still_in(): void {
        $jitsi = $this->room(time() - MINSECS);
        $cm = get_coursemodule_from_id('jitsi', $jitsi->cmid);
        foreach ([$this->teacher, $this->otherteacher] as $t) {
            presence::set($cm, (int) $t->id, true);
            local\roomlog::joined((int) $jitsi->id, (int) $t->id, true);
        }
        presence::set($cm, (int) $this->teacher->id, false);
        local\roomlog::left((int) $jitsi->id, (int) $this->teacher->id);
        $this->assertTrue($this->check($jitsi, $this->student)->allowed);

        presence::set($cm, (int) $this->otherteacher->id, false);
        local\roomlog::left((int) $jitsi->id, (int) $this->otherteacher->id);
        $this->assertSame(access::WAITING, $this->check($jitsi, $this->student)->code);
    }

    public function test_someone_not_enrolled_is_refused(): void {
        $outsider = $this->getDataGenerator()->create_user();
        $scheduled = $this->room(time() - MINSECS);
        $this->assertSame(access::NOT_ALLOWED, $this->check($scheduled, $outsider)->code);

        // A room without a time too: a guest of the course may not join its calls.
        $open = $this->room(0);
        $this->assertSame(access::NOT_ALLOWED, $this->check($open, $outsider)->code);
        $this->assertTrue($this->check($open, $this->student)->allowed);
        $this->assertSame(access::NOT_ALLOWED, $this->check($open, guest_user())->code);
    }

    public function test_sync_drops_a_student_who_left_the_course_unless_they_came(): void {
        global $DB;
        $jitsi = $this->room(time() + HOURSECS);
        $session = $this->session($jitsi);
        $DB->insert_record('academy_session_attendance', (object) ['sessionid' => $session->id,
            'userid' => $this->student2->id, 'joined_at' => time(), 'duration_seconds' => 0]);
        $plugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual']);
        $plugin->unenrol_user($instance, $this->student->id);
        $plugin->unenrol_user($instance, $this->student2->id);

        session_manager::sync_course_students($session);
        $this->assertSame([(int) $this->student2->id], $this->listed((int) $session->id));
    }

    public function test_moving_the_time_of_a_session_not_held_keeps_it(): void {
        $jitsi = $this->room(time() + HOURSECS);
        $before = $this->session($jitsi);
        $later = time() + 3 * HOURSECS;
        $this->save($jitsi, $later, 30);
        $after = $this->session($jitsi);
        $this->assertSame((int) $before->id, (int) $after->id);
        $this->assertSame($later, (int) $after->start_time);
        $this->assertSame(30, (int) $after->duration);
    }

    public function test_a_new_time_after_the_session_was_held_is_a_new_session(): void {
        global $DB;
        $jitsi = $this->room(time() - MINSECS);
        $old = $this->session($jitsi);
        session_manager::record_attendance((int) $old->id, (int) $this->student->id);
        session_manager::end_session((int) $old->id, 'schedule');

        $this->save($jitsi, time() + DAYSECS);
        $new = $this->session($jitsi);
        $this->assertNotEquals((int) $old->id, (int) $new->id);
        $this->assertSame('scheduled', $new->status);
        // The one held stays, for the reports, no longer the room's.
        $kept = $DB->get_record('academy_live_sessions', ['id' => $old->id]);
        $this->assertNull($kept->jitsiid);
        $this->assertSame('ended', $kept->status);
    }

    public function test_taking_the_time_off_deletes_a_session_never_held(): void {
        global $DB;
        $jitsi = $this->room(time() + HOURSECS);
        $sessionid = (int) $this->session($jitsi)->id;
        $this->save($jitsi, 0);
        $this->assertFalse($DB->record_exists('academy_live_sessions', ['id' => $sessionid]));
        $this->assertFalse($DB->record_exists('academy_session_students', ['sessionid' => $sessionid]));
        $this->assertTrue($this->check($jitsi, $this->student)->allowed, 'an open room again');
    }

    public function test_deleting_the_room_keeps_a_held_session_for_the_reports(): void {
        global $DB;
        $jitsi = $this->room(time() - MINSECS);
        $sessionid = (int) $this->session($jitsi)->id;
        session_manager::record_attendance($sessionid, (int) $this->student->id);
        course_delete_module($jitsi->cmid);
        $this->assertTrue($DB->record_exists('academy_live_sessions', ['id' => $sessionid, 'jitsiid' => null]));
    }

    public function test_the_form_refuses_a_time_already_over_and_a_length_of_zero(): void {
        $now = time();
        $this->assertSame([], jitsi_schedule_errors(['sessionstart' => 0], $now), 'no time: nothing to check');
        $this->assertArrayHasKey('sessionduration',
            jitsi_schedule_errors(['sessionstart' => $now + HOURSECS, 'sessionduration' => 0], $now));
        // Two days ago (the wrong day picked): refused.
        $this->assertArrayHasKey('sessionstart',
            jitsi_schedule_errors(['sessionstart' => $now - 2 * DAYSECS, 'sessionduration' => 60], $now));
        // Started ten minutes ago, still on: fine.
        $this->assertSame([], jitsi_schedule_errors(['sessionstart' => $now - 10 * MINSECS, 'sessionduration' => 60], $now));
        // A past session saved again with its own time (another setting changed): fine.
        $jitsi = $this->room($now - 2 * HOURSECS, 60);
        $this->assertSame([], jitsi_schedule_errors(['instance' => $jitsi->id, 'sessionstart' => $now - 2 * HOURSECS,
            'sessionduration' => 60], $now));
    }

    public function test_recordings_after_the_call_follow_the_tab_rules(): void {
        global $DB;
        $jitsi = $this->room(time() - MINSECS);
        $session = $this->session($jitsi);
        $DB->insert_record('academy_session_recordings', (object) ['cmid' => $jitsi->cmid, 'sessionid' => $session->id,
            'vimeo_videoid' => '123', 'title' => 'Lesson 1', 'status' => 'ready', 'timecreated' => time(),
            'timemodified' => time()]);
        $cm = fn(\stdClass $u) => get_fast_modinfo($this->course->id, $u->id)->get_cm($jitsi->cmid);

        $this->setUser($this->teacher);
        $this->assertCount(1, jitsi_recordings_for_user($cm($this->teacher), (int) $this->teacher->id), 'the host: always');
        $this->setUser($this->student);
        $this->assertSame([], jitsi_recordings_for_user($cm($this->student), (int) $this->student->id), 'not during it');

        session_manager::end_session((int) $session->id, 'schedule');
        $recs = jitsi_recordings_for_user($cm($this->student), (int) $this->student->id);
        $this->assertCount(1, $recs);
        $this->assertSame('https://player.vimeo.com/video/123', $recs[0]['embed_url']);

        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);
        $this->assertSame([], jitsi_recordings_for_user($cm($outsider), (int) $outsider->id), 'not enrolled: nothing');
    }
}

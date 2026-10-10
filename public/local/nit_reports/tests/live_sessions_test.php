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

namespace local_nit_reports;

use local_academysessions\session_manager;
use mod_jitsi\local\presence;

/**
 * Live sessions in the lessons report: the teacher's lateness comes from their first
 * join (it used to read teacher_joined_at, which is cleared when the teacher leaves),
 * and the teacher is not counted as an attending student.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_reports\report\lessons
 * @covers     \local_nit_reports\report\teachers
 */
final class live_sessions_test extends \advanced_testcase {

    /** @var \stdClass */
    private $teacher;
    /** @var int */
    private $sessionid;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        if (!\core_component::get_component_directory('mod_jitsi')) {
            $this->markTestSkipped('mod_jitsi is not installed');
        }
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->teacher = $gen->create_and_enrol($course, 'editingteacher', ['firstname' => 'Late', 'lastname' => 'Teacher']);
        $student = $gen->create_and_enrol($course, 'student');
        $jitsi = $gen->create_module('jitsi', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('jitsi', $jitsi->cmid, 0, false, MUST_EXIST);
        // Started 6 minutes ago.
        $this->sessionid = (int) session_manager::create_session($course->id, $this->teacher->id, 'ZZ late session',
            time() - 6 * MINSECS, [$student->id], '', 50, null, $jitsi->id);

        // The teacher arrives now (6 minutes late), the student comes in, the teacher
        // drops out and the session ends.
        presence::set($cm, (int) $this->teacher->id, true);
        session_manager::record_attendance($this->sessionid, (int) $student->id, true);
        presence::set($cm, (int) $this->teacher->id, false);
        // A site admin drops in for support: not a student either.
        presence::set($cm, (int) get_admin()->id, true);
        presence::set($cm, (int) get_admin()->id, false);
        session_manager::end_session($this->sessionid);
        $this->assertNull($DB->get_field('academy_live_sessions', 'teacher_joined_at', ['id' => $this->sessionid]));
        $this->setAdminUser();
    }

    protected function tearDown(): void {
        $_GET = [];
        parent::tearDown();
    }

    /**
     * A report with request parameters.
     *
     * @param string $key
     * @param array $get
     * @return report\base
     */
    private function report(string $key, array $get): report\base {
        $_GET = $get;
        $class = '\\local_nit_reports\\report\\' . $key;
        return new $class(filters::from_request(), scope::for_user());
    }

    public function test_lateness_survives_the_teacher_leaving(): void {
        $rows = $this->report('lessons', ['view' => 'live', 'q' => 'ZZ late'])->rows(0, 0)['rows'];
        $this->assertCount(1, $rows);
        $this->assertSame(get_string('minutes', 'local_nit_reports', 6), $rows[0]['late']);
        $this->assertNotSame(get_string('no'), $rows[0]['teacherjoined']);
    }

    public function test_only_invited_students_count_as_attending(): void {
        $rows = $this->report('lessons', ['view' => 'live', 'q' => 'ZZ late'])->rows(0, 0)['rows'];
        // One invited student, who attended: "1 (100%)", not "3 (300%)" with the
        // teacher and the admin.
        $this->assertStringStartsWith('1 ', $rows[0]['attended']);
        $this->assertStringContainsString('100', $rows[0]['attended']);
    }
}

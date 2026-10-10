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

/**
 * Tests for the teacher reports mobile API.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_reports\mobile_api
 */
final class mobile_api_test extends \advanced_testcase {

    /** The five teaching reports. */
    const TEACHING = ['students', 'courses', 'student_results', 'videos', 'teacher_dues'];

    public function test_teacher_gets_only_the_teaching_reports(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $keys = array_column(mobile_api::list((int) $teacher->id)['reports'], 'key');
        foreach ($keys as $key) {
            $this->assertContains($key, self::TEACHING);
        }
        $this->assertContains('students', $keys);
        $this->assertNotContains('sales', $keys);
    }

    public function test_student_gets_no_reports(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->assertSame([], mobile_api::list((int) $student->id)['reports']);
        $this->expectException(\moodle_exception::class);
        mobile_api::get((int) $student->id, 'students', new filters(), 0, 20);
    }

    public function test_site_report_is_refused_to_a_teacher(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        try {
            mobile_api::get((int) $teacher->id, 'sales', new filters(), 0, 20);
            $this->fail('a site report was returned to a teacher');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_reportnotfound', $e->errorcode);
        }
    }

    public function test_rows_follow_the_columns_and_the_teacher_scope(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $mine = $gen->create_course();
        $other = $gen->create_course();
        $teacher = $gen->create_and_enrol($mine, 'editingteacher');
        $gen->create_and_enrol($mine, 'student', ['email' => 'mine@example.com']);
        $gen->create_and_enrol($other, 'student', ['email' => 'other@example.com']);
        $this->setUser($teacher);

        $data = mobile_api::get((int) $teacher->id, 'students', new filters(), 0, 20);
        $this->assertSame('students', $data['report']['key']);
        $keys = array_column($data['columns'], 'key');
        $this->assertNotContains('paid', $keys); // Money columns are site-wide only.
        $emails = array_column($data['rows'], 'email');
        $this->assertSame(['mine@example.com'], $emails);
        $this->assertSame($keys, array_keys($data['rows'][0]));
        $this->assertSame([(int) $mine->id], array_column($data['filters']['options']->courses, 'id'));
        $this->assertSame(1, $data['total']);
    }

    public function test_perpage_is_bounded(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $this->assertSame(mobile_api::MAXPERPAGE,
            mobile_api::get((int) $teacher->id, 'courses', new filters(), 0, 5000)['perpage']);
    }
}

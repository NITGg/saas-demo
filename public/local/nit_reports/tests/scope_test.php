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
 * Who sees which reports, and which courses' data.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_reports\scope
 * @covers     \local_nit_reports\registry
 * @covers     \local_nit_reports\report\students
 */
final class scope_test extends \advanced_testcase {

    /** @var \stdClass */
    private $cat;
    /** @var \stdClass course in $cat */
    private $c1;
    /** @var \stdClass course in another category */
    private $c2;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->cat = $gen->create_category();
        $this->c1 = $gen->create_course(['category' => $this->cat->id, 'fullname' => 'Course one']);
        $this->c2 = $gen->create_course(['fullname' => 'Course two']);
    }

    public function test_admin_sees_every_report_and_course(): void {
        $s = scope::for_user((int) get_admin()->id);
        $this->assertTrue($s->sitewide);
        $this->assertNull($s->course_ids(scope::MANAGE));
        $this->assertSame(['1 = 1', []], $s->course_sql('c.id', scope::TEACHING));
        $reports = registry::for_scope($s);
        $this->assertArrayHasKey('students', $reports);
        $this->assertArrayHasKey('teacher_dues', $reports);
    }

    public function test_teacher_sees_only_teaching_reports_of_own_courses(): void {
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->c1->id, 'editingteacher');

        $s = scope::for_user((int) $teacher->id);
        $this->assertFalse($s->sitewide);
        $this->assertTrue($s->teacher_only());
        $this->assertSame([(int) $this->c1->id], $s->course_ids(scope::TEACHING));
        $this->assertFalse($s->can(scope::SITE));
        $this->assertFalse($s->can(scope::MANAGE));
        $this->assertTrue($s->can(scope::TEACHING));

        $reports = registry::for_scope($s);
        $this->assertArrayHasKey('students', $reports);
        // Whole-site money reports stay closed to teachers.
        $this->assertArrayNotHasKey('sales', $reports);
        $this->assertArrayNotHasKey('subscriptions', $reports);
        $this->assertArrayNotHasKey('codes', $reports);
    }

    public function test_student_sees_nothing(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->c1->id, 'student');

        $s = scope::for_user((int) $student->id);
        $this->assertSame([], registry::for_scope($s));
        // An empty scope must match no rows, never every row.
        $this->assertSame(['1 = 0', []], $s->course_sql('c.id', scope::TEACHING));
    }

    public function test_category_manager_is_limited_to_their_category(): void {
        global $DB;
        $manager = $this->getDataGenerator()->create_user();
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($roleid, $manager->id, \context_coursecat::instance($this->cat->id));

        $s = scope::for_user((int) $manager->id);
        $this->assertFalse($s->sitewide);
        $this->assertFalse($s->teacher_only());
        $this->assertSame([(int) $this->c1->id], $s->course_ids(scope::MANAGE));
        $this->assertFalse($s->can(scope::SITE));
        $this->assertTrue($s->can(scope::MANAGE));
    }

    public function test_students_report_of_a_teacher_lists_only_their_course_students(): void {
        $gen = $this->getDataGenerator();
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $this->c1->id, 'editingteacher');
        $mine = $gen->create_user(['firstname' => 'Mine', 'lastname' => 'Student']);
        $other = $gen->create_user(['firstname' => 'Other', 'lastname' => 'Student']);
        $gen->enrol_user($mine->id, $this->c1->id, 'student');
        $gen->enrol_user($other->id, $this->c2->id, 'student');

        $this->setUser($teacher);
        $report = new report\students(new filters(), scope::for_user((int) $teacher->id));
        $res = $report->rows(0, 0);

        $names = array_column($res['rows'], 'name');
        $this->assertContains(fullname($mine), $names);
        $this->assertNotContains(fullname($other), $names);
        $this->assertSame(1, $res['total']);
    }
}

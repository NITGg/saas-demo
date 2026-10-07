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
 * Students who left a course: kept apart from the current students, and the
 * table's sorting and paging.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_reports\data
 * @covers     \local_nit_reports\report\base
 * @covers     \local_nit_reports\report\courses
 * @covers     \local_nit_reports\report\students
 */
final class left_students_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;
    /** @var \stdClass still in the course */
    private $stay;
    /** @var \stdClass removed from the course */
    private $gone;
    /** @var \stdClass enrolment suspended */
    private $paused;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course(['fullname' => 'Left test']);
        $this->stay = $gen->create_user(['firstname' => 'Amal', 'lastname' => 'Stay']);
        $this->gone = $gen->create_user(['firstname' => 'Basma', 'lastname' => 'Gone']);
        $this->paused = $gen->create_user(['firstname' => 'Camilia', 'lastname' => 'Paused']);
        foreach ([$this->stay, $this->gone, $this->paused] as $u) {
            $gen->enrol_user($u->id, $this->course->id, 'student');
        }
        $plugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $plugin->unenrol_user($instance, $this->gone->id);
        $plugin->update_user_enrol($instance, $this->paused->id, ENROL_USER_SUSPENDED);
        $this->setAdminUser();
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

    protected function tearDown(): void {
        $_GET = [];
        parent::tearDown();
    }

    public function test_course_counts_current_students_and_the_ones_who_left_apart(): void {
        $rows = $this->report('courses', ['q' => 'Left test'])->rows(0, 0)['rows'];

        $this->assertCount(1, $rows);
        // Before: removed and suspended students still counted while they held the role.
        $this->assertSame('1', $rows[0]['students']);
        $this->assertSame('2', $rows[0]['left']);
    }

    public function test_students_status_filter_lists_current_or_left(): void {
        $current = $this->report('students', ['courseid' => $this->course->id])->rows(0, 0)['rows'];
        $this->assertSame([fullname($this->stay)], array_column($current, 'name'));

        $left = $this->report('students', ['courseid' => $this->course->id, 'status' => 'left', 'sort' => 'name'])
            ->rows(0, 0)['rows'];
        $this->assertSame([fullname($this->gone), fullname($this->paused)], array_column($left, 'name'));
    }

    public function test_sort_direction_and_paging(): void {
        $get = ['courseid' => $this->course->id, 'status' => 'left', 'sort' => 'name', 'dir' => 'desc'];
        $desc = array_column($this->report('students', $get)->rows(0, 0)['rows'], 'name');
        $this->assertSame([fullname($this->paused), fullname($this->gone)], $desc);

        $second = $this->report('students', $get)->rows(1, 1);
        $this->assertSame(2, $second['total']);
        $this->assertSame([fullname($this->gone)], array_column($second['rows'], 'name'));
    }

    public function test_unknown_sort_column_keeps_the_default_order(): void {
        $report = $this->report('students', ['courseid' => $this->course->id, 'sort' => 'nosuchcolumn']);
        $this->assertNull($report->sort());
        $this->assertCount(1, $report->rows(0, 0)['rows']);
    }

    public function test_every_column_has_a_meaning(): void {
        foreach (['students', 'courses', 'student_results'] as $key) {
            $report = $this->report($key, []);
            $this->assertSame([], array_diff_key($report->columns(), $report->help()), "$key columns without help");
        }
    }
}

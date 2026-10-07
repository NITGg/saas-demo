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
 * Teacher earnings & payouts: lessons the teacher is not paid for (student absent,
 * late cancel) are counted apart and kept out of the teacher's percent.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_reports\report\teacher_dues
 */
final class teacher_dues_test extends \advanced_testcase {

    /**
     * An earning row as local_nit_finance writes it.
     *
     * @param int $teacherid
     * @param int $percent teacher percent (0 = not paid)
     * @param int $value flex value, minor units
     */
    private function earning(int $teacherid, int $percent, int $value): void {
        global $DB;
        $teacher = (int) round($value * $percent / 100);
        $DB->insert_record('nit_earning', (object) ['lessonid' => random_int(1, 99999), 'source' => 'lesson',
            'teacherid' => $teacherid, 'studentid' => 0, 'purchaseid' => 0, 'flex_value_minor' => $value,
            'teacher_amount_minor' => $teacher, 'platform_amount_minor' => $value - $teacher,
            'teacher_percent' => $percent, 'platform_percent' => 100 - $percent, 'status' => 'active',
            'timecreated' => time(), 'timemodified' => time()]);
    }

    /**
     * The report row of a teacher.
     *
     * @param \stdClass $teacher
     * @return array
     */
    private function row(\stdClass $teacher): array {
        $_GET = ['q' => $teacher->lastname];
        $rows = (new report\teacher_dues(filters::from_request(), scope::for_user()))->rows(0, 0)['rows'];
        $_GET = [];
        $this->assertCount(1, $rows);
        return $rows[0];
    }

    public function test_unpaid_lessons_are_apart_from_the_percent(): void {
        global $DB;
        $this->resetAfterTest();
        if (!$DB->get_manager()->table_exists('nit_earning')) {
            $this->markTestSkipped('local_nit_finance is not installed.');
        }
        $this->setAdminUser();
        $teacher = $this->getDataGenerator()->create_user(['lastname' => 'Duesteacher']);
        $this->earning((int) $teacher->id, 40, 2000);
        $this->earning((int) $teacher->id, 0, 5);

        $row = $this->row($teacher);
        $this->assertSame('2', $row['operations']);
        $this->assertSame('1', $row['unpaid']);
        // Before: the 0% of the absent lesson showed as the range "0.0% – 40.0%".
        $this->assertSame(format_float(40, 1) . '%', $row['percent']);
    }

    public function test_only_unpaid_lessons_show_no_percent(): void {
        global $DB;
        $this->resetAfterTest();
        if (!$DB->get_manager()->table_exists('nit_earning')) {
            $this->markTestSkipped('local_nit_finance is not installed.');
        }
        $this->setAdminUser();
        $teacher = $this->getDataGenerator()->create_user(['lastname' => 'Absentonly']);
        $this->earning((int) $teacher->id, 0, 5);

        $row = $this->row($teacher);
        $this->assertSame('1', $row['unpaid']);
        $this->assertSame('—', $row['percent']);
    }
}

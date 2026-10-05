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

namespace local_nit_videoprogress;

/**
 * The helpers behind the mobile API (local/nit_videoprogress/api.php): access
 * checks, slice parsing and the per-lesson export.
 *
 * @package    local_nit_videoprogress
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_videoprogress\progress
 */
final class progress_api_test extends \advanced_testcase {

    /**
     * A course with one Vimeo lesson and one page, plus an enrolled student.
     *
     * @return array [course, videocm, pagecm, student]
     */
    private function make_course(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $video = $gen->create_module('vimeo', ['course' => $course->id, 'videoid' => '76979871']);
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $modinfo = get_fast_modinfo($course);
        return [$course, $modinfo->get_cm($video->cmid), $modinfo->get_cm($page->cmid), $student];
    }

    public function test_parse_slices_accepts_csv_0_to_99_and_dedupes(): void {
        $this->assertSame([], progress::parse_slices(''));
        $this->assertSame([0, 5, 99], progress::parse_slices('0,5,5,99'));
        foreach (['1,,2', '1,a', '-1', '100', '1 2', '[1,2]'] as $bad) {
            try {
                progress::parse_slices($bad);
                $this->fail("accepted '$bad'");
            } catch (\moodle_exception $e) {
                $this->assertSame('err_invalidslices', $e->errorcode, $bad);
            }
        }
    }

    public function test_require_lesson_checks_type_and_enrolment(): void {
        $this->resetAfterTest();
        [, $cm, $page, $student] = $this->make_course();

        $this->setUser($student);
        [, $got] = progress::require_lesson($cm->id);
        $this->assertSame((int) $cm->id, (int) $got->id);

        try {
            progress::require_lesson($page->id);
            $this->fail('a page passed as a video');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_notvideo', $e->errorcode);
        }

        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\require_login_exception::class);
        progress::require_lesson($cm->id);
    }

    public function test_export_lists_slices_and_resume_point(): void {
        $this->resetAfterTest();
        [, $cm, , $student] = $this->make_course();

        $empty = progress::export($cm, null);
        $this->assertFalse($empty['started']);
        $this->assertSame(0, $empty['percent']);
        $this->assertSame(str_repeat('0', 100), $empty['watched']);
        $this->assertSame([], $empty['slices']);

        $row = progress::record($student->id, $cm, 60, 200, [3, 4, 50]);
        $out = progress::export($cm, $row);
        $this->assertTrue($out['started']);
        $this->assertSame(3, $out['percent']);
        $this->assertSame([3, 4, 50], $out['slices']);
        $this->assertSame(60, $out['resume_position']);
        $this->assertSame('vimeo', $out['provider']);

        $row = progress::record($student->id, $cm, 195, 200, []);
        $this->assertSame(0, progress::export($cm, $row)['resume_position'], 'within 10 s of the end');
    }
}

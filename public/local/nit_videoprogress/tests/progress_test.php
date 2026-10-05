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

use local_nit_videoprogress\external\save;

/**
 * Video progress: percent counts only played slices, resume rules, and the
 * save endpoint's access checks.
 *
 * @package    local_nit_videoprogress
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_videoprogress\progress
 * @covers     \local_nit_videoprogress\external\save
 */
final class progress_test extends \advanced_testcase {

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

    public function test_percent_counts_distinct_played_slices_only(): void {
        $this->resetAfterTest();
        [, $cm, , $student] = $this->make_course();

        progress::record($student->id, $cm, 60, 200, range(0, 29));
        // Overlap (10, 11) is not counted twice; out-of-range slices are ignored.
        $row = progress::record($student->id, $cm, 120, 200, [10, 11, 50, 100, -1]);

        $this->assertSame(31, (int) $row->percent);
        $this->assertSame(120, (int) $row->position);
    }

    public function test_position_is_clamped_and_duration_never_shrinks(): void {
        $this->resetAfterTest();
        [, $cm, , $student] = $this->make_course();

        progress::record($student->id, $cm, 30, 0, []);
        progress::record($student->id, $cm, 40, 200, []);
        $row = progress::record($student->id, $cm, 900, 180, []);

        $this->assertSame(200, (int) $row->duration);
        $this->assertSame(200, (int) $row->position);
    }

    public function test_resume_position_skips_start_and_end(): void {
        $this->assertSame(0, progress::resume_position(null));
        $this->assertSame(0, progress::resume_position((object) ['position' => 3, 'duration' => 600]));
        $this->assertSame(0, progress::resume_position((object) ['position' => 595, 'duration' => 600]));
        $this->assertSame(262, progress::resume_position((object) ['position' => 262, 'duration' => 600]));
    }

    public function test_save_endpoint_records_for_current_user(): void {
        $this->resetAfterTest();
        [$course, $cm, , $student] = $this->make_course();
        $this->setUser($student);

        $result = save::execute($cm->id, 15, 100, '0,1,2,3');
        $result = \core_external\external_api::clean_returnvalue(save::execute_returns(), $result);

        $this->assertSame(4, $result['percent']);
        $this->assertEquals([$cm->id => 4], progress::course_percents($student->id, $course->id));
    }

    public function test_save_endpoint_refuses_non_video_activity(): void {
        $this->resetAfterTest();
        [, , $page, $student] = $this->make_course();
        $this->setUser($student);

        $this->expectException(\invalid_parameter_exception::class);
        save::execute($page->id, 15, 100, '1');
    }

    public function test_save_endpoint_refuses_user_not_enrolled(): void {
        $this->resetAfterTest();
        [, $cm] = $this->make_course();
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);

        $this->expectException(\require_login_exception::class);
        save::execute($cm->id, 15, 100, '1');
    }

    public function test_overview_lists_unstarted_lessons_at_zero(): void {
        $this->resetAfterTest();
        [$course, $cm, , $student] = $this->make_course();
        $gen = $this->getDataGenerator();
        $second = $gen->create_module('vimeo', ['course' => $course->id, 'videoid' => '1']);
        progress::record($student->id, $cm, 10, 100, range(0, 49));

        $overview = progress::overview($student->id, [$course->id]);

        $this->assertCount(1, $overview);
        $percents = array_column($overview[0]['lessons'], 'percent', 'cmid');
        $this->assertSame(50, $percents[$cm->id]);
        $this->assertSame(0, $percents[$second->cmid]);
        $this->assertSame(25, $overview[0]['percent']);
    }
}

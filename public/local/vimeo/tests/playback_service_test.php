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

namespace local_vimeo;

/**
 * Tests for Vimeo playback resolution + the lesson lock check.
 *
 * @package    local_vimeo
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_vimeo\playback_service
 */
final class playback_service_test extends \advanced_testcase {

    public function test_video_row_falls_back_to_mod_vimeo_instance(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $vimeo = $this->getDataGenerator()->create_module('vimeo', ['course' => $course->id, 'videoid' => '42']);
        $row = playback_service::video_row((int) $vimeo->cmid);
        $this->assertSame('42', $row->videoid);
        $this->assertNull(playback_service::video_row(99999999));
    }

    public function test_enrolled_student_gets_embed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $vimeo = $this->getDataGenerator()->create_module('vimeo', ['course' => $course->id, 'videoid' => '42']);
        $this->setUser($student);
        $data = playback_service::get_playback((int) $vimeo->cmid, $student);
        $this->assertSame('https://player.vimeo.com/video/42', $data['embedurl']);
    }

    public function test_locked_lesson_is_refused_but_teacher_passes(): void {
        $this->resetAfterTest();
        if (!class_exists('\local_academy\api\courses')) {
            $this->markTestSkipped('local_academy not installed');
        }
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $student = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        // An earlier tracked lesson the student has not completed locks the next one.
        $gen->create_module('vimeo', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $second = $gen->create_module('vimeo', ['course' => $course->id, 'videoid' => '43']);
        set_config('player_lockorder', 1, 'local_academy');

        $this->setUser($teacher);
        $this->assertSame('43', playback_service::get_playback((int) $second->cmid, $teacher)['videoid']);

        $this->setUser($student);
        try {
            playback_service::get_playback((int) $second->cmid, $student);
            $this->fail('locked lesson played');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_lessonlocked', $e->errorcode);
        }
    }
}

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

namespace local_academy;

use local_academy\api\pages;

/**
 * Tests for the Bassthalk pages as data (home sections, teacher page, course page).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_academy\api\pages
 */
final class pages_test extends \advanced_testcase {

    public function test_course_page_action_follows_the_viewer(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_user();
        $outsider = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');

        // Visitor: log in first, nothing opens.
        $this->setGuestUser();
        $visitor = pages::course((int) $course->id, 0);
        $this->assertSame('login', $visitor['action']);
        $this->assertFalse($visitor['sections'][0]['items'][0]['canopen']);

        // Enrolled student: open, with a resume lesson.
        $this->setUser($student);
        $page = pages::course((int) $course->id, (int) $student->id);
        $this->assertSame('open', $page['action']);
        $this->assertTrue($page['price']['enrolled']);
        $this->assertGreaterThan(0, $page['resume_cmid']);
        $this->assertTrue($page['sections'][0]['items'][0]['canopen']);
        $this->assertSame(1, $page['counts']['lessons']);

        // Signed-in, not enrolled, free course: enrol for free.
        $this->setUser($outsider);
        $this->assertSame('enrol_free', pages::course((int) $course->id, (int) $outsider->id)['action']);
    }

    public function test_hidden_or_missing_course_is_not_found(): void {
        $this->resetAfterTest();
        $hidden = $this->getDataGenerator()->create_course(['visible' => 0]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        foreach ([(int) $hidden->id, SITEID, 999999] as $courseid) {
            try {
                pages::course($courseid, (int) $user->id);
                $this->fail('course ' . $courseid . ' should not be found');
            } catch (\moodle_exception $e) {
                $this->assertSame('err_coursenotfound', $e->errorcode);
            }
        }
    }

    public function test_price_state_of_a_free_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $state = pages::price_state((int) $course->id, 0);
        $this->assertTrue($state['free']);
        $this->assertFalse($state['haspricing']);
        $this->assertSame(0, $state['price_minor']);
        $this->assertFalse($state['hasoffer']);
        $this->assertSame(0, $state['discountpercent']);
    }

    public function test_unknown_teacher_is_not_found(): void {
        $this->resetAfterTest();
        $notateacher = $this->getDataGenerator()->create_user();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('err_teachernotfound', 'local_academy'));
        pages::teacher((int) $notateacher->id, 0);
    }

    public function test_ml_picks_the_current_language(): void {
        $this->resetAfterTest();
        $context = \context_system::instance();
        force_current_language('en');
        $this->assertSame('Literary', pages::ml('{mlang ar}ادبى{mlang}{mlang en}Literary{mlang}', $context));
        $this->assertSame('', pages::ml('', $context));
        $this->assertSame('plain', pages::ml('plain', $context));
    }
}

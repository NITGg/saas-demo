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

namespace local_nit_reviews;

/**
 * The review-list, validated save and delete methods behind the mobile API
 * (local/nit_reviews/api.php).
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_reviews\api
 */
final class api_test extends \advanced_testcase {

    /**
     * Expect a moodle_exception with the given errorcode.
     *
     * @param string $code
     * @param callable $fn
     */
    private function assert_error(string $code, callable $fn): void {
        try {
            $fn();
            $this->fail("expected $code");
        } catch (\moodle_exception $e) {
            $this->assertSame($code, $e->errorcode);
        }
    }

    public function test_get_reviews_pages_newest_first_with_author(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $a = $gen->create_and_enrol($course, 'student', ['firstname' => 'Ali', 'lastname' => 'One']);
        $b = $gen->create_and_enrol($course, 'student', ['firstname' => 'Badr', 'lastname' => 'Two']);
        $this->assertTrue(api::save($course->id, 4, 'Good', $a->id));
        $this->assertTrue(api::save($course->id, 2, '', $b->id));
        // Make A's review the newest.
        $DB->set_field('local_nit_reviews', 'timemodified', time() + 60, ['userid' => $a->id]);

        $page0 = api::get_reviews($course->id, 0, 1);
        $this->assertSame(2, $page0['total']);
        $this->assertCount(1, $page0['reviews']);
        $this->assertSame((int) $a->id, $page0['reviews'][0]['userid']);
        $this->assertSame('Ali One', $page0['reviews'][0]['fullname']);
        $this->assertSame(4, $page0['reviews'][0]['rating']);
        $this->assertNotEmpty($page0['reviews'][0]['pictureurl']);

        $page1 = api::get_reviews($course->id, 1, 1);
        $this->assertSame((int) $b->id, $page1['reviews'][0]['userid']);

        $this->assertSame([1 => 0, 2 => 1, 3 => 0, 4 => 1, 5 => 0], api::get_distribution($course->id));
    }

    public function test_get_reviews_skips_deleted_users(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_and_enrol($course, 'student');
        api::save($course->id, 5, 'x', $user->id);
        delete_user($user);
        $this->assertSame(0, api::get_reviews($course->id)['total']);
    }

    public function test_save_validated_rejects_bad_input_and_outsiders(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $outsider = $gen->create_user();

        $saved = api::save_validated($course->id, 5, '  Great  ', $student->id);
        $this->assertSame(5, (int) $saved->rating);
        $this->assertSame('Great', $saved->review);

        $this->assert_error('err_invalidrating', fn() => api::save_validated($course->id, 6, '', $student->id));
        $this->assert_error('err_invalidrating', fn() => api::save_validated($course->id, 0, '', $student->id));
        $this->assert_error('err_cannotrate', fn() => api::save_validated($course->id, 3, '', $outsider->id));
        $this->assert_error('err_reviewtoolong', fn() => api::save_validated($course->id, 3,
            str_repeat('x', api::MAX_REVIEW_LENGTH + 1), $student->id));
        $this->assert_error('err_coursenotfound', fn() => api::save_validated(999999, 3, '', $student->id));
        // The failed calls did not touch the saved review.
        $this->assertSame(5, (int) api::get_user_review($course->id, $student->id)->rating);
    }

    public function test_delete_user_review_removes_only_own(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $a = $gen->create_and_enrol($course, 'student');
        $b = $gen->create_and_enrol($course, 'student');
        api::save($course->id, 4, '', $a->id);
        api::save($course->id, 2, '', $b->id);

        $this->assertTrue(api::delete_user_review($course->id, $a->id));
        $this->assertFalse(api::delete_user_review($course->id, $a->id), 'nothing left to delete');
        $this->assertNotNull(api::get_user_review($course->id, $b->id));
        $this->assertSame(1, api::get_aggregate($course->id)->count);
    }

    public function test_delete_review_needs_manage_capability(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        api::save($course->id, 1, 'spam', $student->id);
        $review = api::get_user_review($course->id, $student->id);

        $this->setUser($student);
        try {
            api::delete_review($review->id);
            $this->fail('student deleted a review');
        } catch (\required_capability_exception $e) {
            $this->assertNotNull(api::get_user_review($course->id, $student->id));
        }

        $this->setUser($teacher);
        $deleted = api::delete_review($review->id);
        $this->assertSame((int) $review->id, (int) $deleted->id);
        $this->assertNull(api::get_user_review($course->id, $student->id));

        $this->assert_error('err_reviewnotfound', fn() => api::delete_review($review->id));
    }
}

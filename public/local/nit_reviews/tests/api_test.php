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
        // A's comment is public once approved; make it the newest.
        $DB->set_field('local_nit_reviews', 'status', api::STATUS_APPROVED, ['userid' => $a->id]);
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
        api::save($course->id, 5, '', $user->id);
        $this->assertSame(1, api::get_reviews($course->id)['total']);
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

    /**
     * A manager assigned on the course's category.
     *
     * @param \stdClass $course
     * @return \stdClass the user
     */
    private function category_manager(\stdClass $course): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        role_assign($DB->get_field('role', 'id', ['shortname' => 'manager']), $user->id,
            \context_coursecat::instance($course->category)->id);
        return $user;
    }

    public function test_delete_review_needs_moderate_capability(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $manager = $this->category_manager($course);
        api::save($course->id, 1, 'spam', $student->id);
        $review = api::get_user_review($course->id, $student->id);

        // Neither the author nor the course's teacher may delete it.
        foreach ([$student, $teacher] as $user) {
            $this->setUser($user);
            try {
                api::delete_review($review->id);
                $this->fail('deleted without local/nit_reviews:moderate');
            } catch (\required_capability_exception $e) {
                $this->assertNotNull(api::get_user_review($course->id, $student->id));
            }
        }

        $this->setUser($manager);
        $deleted = api::delete_review($review->id);
        $this->assertSame((int) $review->id, (int) $deleted->id);
        $this->assertNull(api::get_user_review($course->id, $student->id));

        $this->assert_error('err_reviewnotfound', fn() => api::delete_review($review->id));
    }

    public function test_comment_waits_for_approval_stars_alone_do_not(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $a = $gen->create_and_enrol($course, 'student');
        $b = $gen->create_and_enrol($course, 'student');

        $this->assertSame(api::STATUS_APPROVED, (int) api::save_validated($course->id, 4, '  ', $a->id)->status);
        $this->assertSame(api::STATUS_PENDING, (int) api::save_validated($course->id, 2, 'Too fast', $b->id)->status);

        // Only the approved review is listed and averaged.
        $this->assertSame(1, api::get_aggregate($course->id)->count);
        $this->assertSame(4.0, api::get_aggregate($course->id)->avg);
        $this->assertSame(1, api::get_reviews($course->id)['total']);
        $this->assertSame([1 => 0, 2 => 0, 3 => 0, 4 => 1, 5 => 0], api::get_distribution($course->id));
    }

    public function test_teacher_review_rules(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $other = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $otherteacher = $gen->create_and_enrol($other, 'editingteacher');
        $outsider = $gen->create_user();

        $this->assertTrue(api::can_rate_teacher($course->id, $teacher->id, $student->id));
        $this->assertFalse(api::can_rate_teacher($course->id, $teacher->id, $teacher->id), 'nobody rates themself');
        $this->assertFalse(api::can_rate_teacher($course->id, $otherteacher->id, $student->id), 'not this course\'s teacher');
        $this->assertFalse(api::can_rate_teacher($course->id, $teacher->id, $outsider->id), 'not enrolled');
        $this->assertFalse(api::can_rate($course->id, $teacher->id), 'teachers do not rate their course');
        $this->assertSame([(int) $course->id], api::rateable_courses_for_teacher($teacher->id, $student->id));
        $this->assert_error('err_cannotrateteacher',
            fn() => api::save_validated($course->id, 5, '', $outsider->id, $teacher->id));

        // One review per learner per teacher per course, apart from the course review.
        api::save_validated($course->id, 5, '', $student->id, $teacher->id);
        api::save_validated($course->id, 3, '', $student->id, $teacher->id);
        api::save_validated($course->id, 4, '', $student->id);
        $this->assertSame(3, (int) api::get_user_review($course->id, $student->id, $teacher->id)->rating);
        $this->assertSame(3.0, api::get_teacher_aggregate($teacher->id)->avg);
        $this->assertSame(4.0, api::get_aggregate($course->id)->avg, 'teacher reviews stay out of the course average');
    }

    public function test_category_manager_moderates_only_their_courses(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $mine = $gen->create_course(['category' => $gen->create_category()->id]);
        $theirs = $gen->create_course(['category' => $gen->create_category()->id]);
        $s1 = $gen->create_and_enrol($mine, 'student');
        $s2 = $gen->create_and_enrol($theirs, 'student');
        $manager = $this->category_manager($mine);
        $r1 = api::save_validated($mine->id, 5, 'Great', $s1->id);
        $r2 = api::save_validated($theirs->id, 1, 'Bad', $s2->id);

        $this->assertSame([(int) $mine->id], api::moderated_course_ids($manager->id));
        $this->assertSame(1, api::pending_count($manager->id));
        $this->assertNull(api::moderated_course_ids(get_admin()->id), 'a site admin moderates everything');

        $this->setUser($manager);
        $this->assertSame(api::STATUS_APPROVED, (int) api::approve($r1->id)->status);
        $this->expectException(\required_capability_exception::class);
        api::approve($r2->id);
    }

    public function test_reject_hides_and_editing_sends_back_to_pending(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $manager = $this->category_manager($course);
        $review = api::save_validated($course->id, 5, 'Nice', $student->id);

        $this->setUser($manager);
        api::approve($review->id);
        $this->assertSame(1, api::get_aggregate($course->id)->count);

        // Editing an approved comment hides it until it is approved again.
        $edited = api::save_validated($course->id, 2, 'Changed my mind', $student->id);
        $this->assertSame(api::STATUS_PENDING, (int) $edited->status);
        $this->assertSame(0, api::get_aggregate($course->id)->count);

        $rejected = api::reject($edited->id, 'Rude');
        $this->assertSame(api::STATUS_REJECTED, (int) $rejected->status);
        $this->assertSame('Rude', $rejected->rejectreason);
        $this->assertSame((int) $manager->id, (int) $rejected->reviewedby);
        $this->assertSame(0, api::get_reviews($course->id)['total']);
        $this->assert_error('err_reasontoolong', fn() => api::reject($edited->id, str_repeat('x', api::MAX_REASON_LENGTH + 1)));
    }
}

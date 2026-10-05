<?php
namespace local_nit_lessons;

use local_nit_lessons\exception\lesson_exception;
use local_nit_lessons\local\profile_section;
use local_nit_lessons\service\teacher_service;

/**
 * Teacher subjects come from a dropdown (the admin's list), on the profile page.
 *
 * @package    local_nit_lessons
 * @covers     \local_nit_lessons\service\teacher_service::subject_options
 * @covers     \local_nit_lessons\service\teacher_service::save
 * @covers     \local_nit_lessons\local\profile_section
 */
final class teacher_subjects_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /** A teacher: an editing-teacher role in a course named $coursename. */
    private function teacher(string $coursename = 'Algebra 1'): int {
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $gen->create_course(['fullname' => $coursename])->id, 'editingteacher');
        return (int) $user->id;
    }

    public function test_options_are_the_admin_list_else_the_default_list(): void {
        $tid = $this->teacher();
        $this->assertCount(16, teacher_service::subject_options($tid)); // Not configured: the default list.
        set_config('subjects', "Physics\nChemistry\n\nPhysics", 'local_nit_lessons');
        $this->assertSame(['Physics', 'Chemistry'], teacher_service::subject_options($tid));
    }

    public function test_an_empty_list_offers_the_teachers_courses(): void {
        set_config('subjects', '', 'local_nit_lessons');
        $this->assertSame(['Algebra 1'], teacher_service::subject_options($this->teacher()));
    }

    public function test_save_refuses_a_subject_not_in_the_list(): void {
        set_config('subjects', "Physics\nChemistry", 'local_nit_lessons');
        $tid = $this->teacher();
        (new teacher_service())->save($tid, true, '', ['Chemistry'], []);
        $this->assertSame(['Chemistry'], (new teacher_service())->profile($tid)['subjects']);

        try {
            (new teacher_service())->save($tid, true, '', ['Astrology'], []);
            $this->fail('a subject outside the list was saved');
        } catch (lesson_exception $e) {
            $this->assertSame('err_badsubject', $e->errorcode);
        }
        $this->assertSame(['Chemistry'], (new teacher_service())->profile($tid)['subjects']);
    }

    public function test_a_subject_removed_from_the_list_can_still_be_kept(): void {
        set_config('subjects', "Physics\nChemistry", 'local_nit_lessons');
        $tid = $this->teacher();
        (new teacher_service())->save($tid, true, '', ['Physics'], []);
        set_config('subjects', 'Chemistry', 'local_nit_lessons');
        (new teacher_service())->save($tid, true, 'Updated', ['Physics', 'Chemistry'], []);
        $this->assertSame(['Physics', 'Chemistry'], (new teacher_service())->profile($tid)['subjects']);
    }

    public function test_profile_card_only_for_teachers_with_flex_on(): void {
        $student = (int) $this->getDataGenerator()->create_user()->id;
        $this->assertFalse(profile_section::applies($student));
        $this->assertSame('', profile_section::render($student));

        set_config('subjects', "Physics\nChemistry", 'local_nit_lessons');
        $tid = $this->teacher();
        $this->setUser($tid);
        $html = profile_section::render($tid);
        $this->assertStringContainsString('name="nitlx_subjects[]"', $html);
        $this->assertStringContainsString('<option value="Chemistry"', $html);
    }
}

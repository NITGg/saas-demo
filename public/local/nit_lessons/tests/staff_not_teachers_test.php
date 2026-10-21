<?php
namespace local_nit_lessons;

use local_nit_lessons\service\teacher_service;

/**
 * Admins and managers are never offered to students as teachers ("Book a lesson" list,
 * instant lessons, booking), even when they hold a teacher role and a teacher profile.
 *
 * @package    local_nit_lessons
 * @covers     \local_nit_lessons\service\teacher_service::is_staff
 * @covers     \local_nit_lessons\service\teacher_service::browse
 * @covers     \local_nit_lessons\service\teacher_service::bookable
 */
final class staff_not_teachers_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('subjects', "Physics\nChemistry", 'local_nit_lessons');
    }

    /** A bookable teacher: an editing-teacher role plus an available profile teaching Physics. */
    private function teacher(): int {
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $gen->create_course()->id, 'editingteacher');
        (new teacher_service())->save((int) $user->id, true, '', ['Physics'], []);
        return (int) $user->id;
    }

    public function test_site_admin_with_a_teacher_profile_is_not_listed_or_bookable(): void {
        global $CFG;
        $teacher = $this->teacher();
        $admin = $this->teacher();
        set_config('siteadmins', $CFG->siteadmins . ',' . $admin);

        $service = new teacher_service();
        $this->assertSame([$teacher], array_column($service->browse(), 'id'));
        $this->assertSame([$teacher], array_column($service->browse('Physics'), 'id'));
        $this->assertTrue($service->bookable($teacher));
        $this->assertFalse($service->bookable($admin));
    }

    public function test_manager_with_a_teacher_profile_is_not_listed_or_bookable(): void {
        $teacher = $this->teacher();
        $manager = $this->teacher();
        $managerrole = (int) array_key_first(get_archetype_roles('manager'));
        role_assign($managerrole, $manager, \context_system::instance()->id);

        $service = new teacher_service();
        $this->assertSame([$teacher], array_column($service->browse(), 'id'));
        $this->assertFalse($service->bookable($manager));
    }

    public function test_is_staff(): void {
        $student = (int) $this->getDataGenerator()->create_user()->id;
        $this->assertFalse(teacher_service::is_staff($student));
        $this->assertFalse(teacher_service::is_staff($this->teacher()));
        $this->assertTrue(teacher_service::is_staff((int) get_admin()->id));
    }
}

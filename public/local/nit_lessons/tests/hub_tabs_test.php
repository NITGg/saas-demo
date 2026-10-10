<?php
namespace local_nit_lessons;

use local_nit_lessons\local\hub;

/**
 * The tabs of the student hub (local/nit_lessons/student.php).
 *
 * @package    local_nit_lessons
 * @covers     \local_nit_lessons\local\hub
 */
final class hub_tabs_test extends \advanced_testcase {

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/nit_flex/lib.php');
    }

    public function test_a_student_gets_the_pairs_and_the_wallet_in_order(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->assertSame(['book', 'lessons', 'flexavailable', 'packages', 'subavailable', 'mysubs', 'wallet'],
            hub::tabs((int) $student->id));
    }

    public function test_earnings_is_a_teacher_tab(): void {
        $gen = $this->getDataGenerator();
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $gen->create_course()->id, 'editingteacher');
        $this->assertSame('earnings', array_slice(hub::tabs((int) $teacher->id), -1)[0]);
    }

    public function test_the_former_pages_are_drawn_as_tabs(): void {
        $gen = $this->getDataGenerator();
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $gen->create_course()->id, 'editingteacher');
        $this->setUser($teacher);
        foreach (['flexavailable', 'wallet', 'earnings'] as $tab) {
            $ctx = hub::context((int) $teacher->id, $tab);
            $this->assertNotEmpty($ctx['embedhtml'], $tab);
            $this->assertTrue($ctx['is' . $tab]);
        }
        // The flex bar's button opens the packages tab.
        $this->assertStringContainsString('tab=flexavailable', hub::context((int) $teacher->id, 'book')['packagesurl']);
        // A divider before each pair after the first.
        $groups = array_column(array_filter(hub::context((int) $teacher->id, 'book')['tabs'],
            fn($t) => $t['groupstart']), 'key');
        $this->assertSame(['flexavailable', 'subavailable', 'wallet'], array_values($groups));
    }
}

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

namespace local_nit_lessons;

use local_nit_finance\api\wallet;
use local_nit_flex\api\packages;
use local_nit_flex\api\purchase;
use local_nit_lessons\api\lessons;
use local_nit_lessons\room\room_factory;
use local_nit_lessons\room\stub_room;
use local_nit_lessons\service\teacher_service;
use local_nit_lessons\task\auto_complete_lessons;

/**
 * Started lessons the teacher never closed complete themselves after auto_complete_minutes.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_lessons\service\lesson_service::auto_complete_due
 * @covers     \local_nit_lessons\api\lessons::auto_complete_due
 * @covers     \local_nit_lessons\task\auto_complete_lessons
 */
final class auto_complete_test extends \advanced_testcase {
    /** @var \stdClass */
    private $student;

    /** @var \stdClass */
    private $teacher;

    /**
     * A student with a 10-Flex package (100 EGP per Flex), a bookable Physics teacher on 40%,
     * time gates that pass instantly and a 60-minute auto-complete grace.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->student = $gen->create_user();
        $this->teacher = $gen->create_user();
        $course = $gen->create_course();
        $gen->enrol_user($this->teacher->id, $course->id, 'editingteacher');
        set_config('subjects', 'Physics', 'local_nit_lessons');
        (new teacher_service())->save((int) $this->teacher->id, true, '', ['Physics'], []);
        foreach (['min_booking_minutes' => 0, 'start_allowed_minutes' => 100000,
                'complete_allowed_minutes' => 0, 'auto_complete_minutes' => 60] as $k => $v) {
            set_config($k, $v, 'local_nit_lessons');
        }
        set_config('teacher_percent', 40, 'local_nit_finance');
        room_factory::set_override(new stub_room());
        $packageid = packages::create((object) [
            'name' => 'Flex10', 'flex_count' => 10, 'price_minor' => 100000, 'expiration_days' => 0,
        ]);
        purchase::fulfil((int) $this->student->id, $packageid);
    }

    protected function tearDown(): void {
        room_factory::set_override(null);
        parent::tearDown();
    }

    /**
     * A confirmed lesson $days from now at noon in the teacher's timezone.
     *
     * @param int $days
     * @return array
     */
    private function confirmed(int $days = 1): array {
        $when = (new \DateTimeImmutable('today', teacher_service::timezone((int) $this->teacher->id)))
            ->modify("+{$days} day")->setTime(12, 0)->getTimestamp();
        $lesson = lessons::request((int) $this->student->id, (int) $this->teacher->id, 'Physics', $when, 'note');
        return lessons::teacher_respond((int) $this->teacher->id, $lesson['id'], 'accept');
    }

    /**
     * A 60-minute lesson the teacher started $minutesago minutes ago.
     *
     * @param int $minutesago
     * @param int $days which day to book, so several lessons do not clash
     * @return int lesson id
     */
    private function started(int $minutesago, int $days = 1): int {
        global $DB;
        $lesson = $this->confirmed($days);
        lessons::start((int) $this->teacher->id, $lesson['id']);
        $DB->set_field('nit_lesson', 'actual_start', time() - $minutesago * MINSECS, ['id' => $lesson['id']]);
        return (int) $lesson['id'];
    }

    /**
     * Past its end plus the grace, a started lesson completes: Flex used, teacher paid 40%, and the
     * end time is the planned end, not the moment the task ran.
     *
     * @return void
     */
    public function test_due_started_lesson_completes_and_pays(): void {
        global $DB;
        $id = $this->started(150); // Ended 90 minutes ago; grace is 60.
        $start = (int) $DB->get_field('nit_lesson', 'actual_start', ['id' => $id]);

        $this->assertSame(1, lessons::auto_complete_due(time()));

        $row = $DB->get_record('nit_lesson', ['id' => $id]);
        $this->assertSame('completed', $row->status);
        $this->assertSame('consumed', $row->flex_state);
        $this->assertSame($start + HOURSECS, (int) $row->actual_end, 'actual_end should be the planned end');
        $this->assertSame(9, purchase::active((int) $this->student->id)['remaining_flex']);
        $this->assertSame(4000, wallet::teacher((int) $this->teacher->id)['available_balance_minor']);
    }

    /**
     * Inside the grace, the lesson is left for the teacher to close.
     *
     * @return void
     */
    public function test_lesson_inside_grace_is_left_alone(): void {
        global $DB;
        $id = $this->started(90); // Ended 30 minutes ago; grace is 60.
        $this->assertSame(0, lessons::auto_complete_due(time()));
        $this->assertSame('in_progress', $DB->get_field('nit_lesson', 'status', ['id' => $id]));
        $this->assertSame(0, wallet::teacher((int) $this->teacher->id)['available_balance_minor']);
    }

    /**
     * A confirmed lesson that never started is not completed, however old: nobody knows if it
     * happened, so it stays for an absence report or the admin.
     *
     * @return void
     */
    public function test_never_started_lesson_is_not_completed(): void {
        global $DB;
        $lesson = $this->confirmed();
        $DB->set_field('nit_lesson', 'confirmed_time', time() - 10 * DAYSECS, ['id' => $lesson['id']]);
        $this->assertSame(0, lessons::auto_complete_due(time()));
        $this->assertSame('confirmed', $DB->get_field('nit_lesson', 'status', ['id' => $lesson['id']]));
        $this->assertSame(1, purchase::active((int) $this->student->id)['reserved_flex']);
    }

    /**
     * Setting the grace to 0 turns auto-complete off.
     *
     * @return void
     */
    public function test_zero_turns_it_off(): void {
        global $DB;
        set_config('auto_complete_minutes', 0, 'local_nit_lessons');
        $id = $this->started(10 * 24 * 60);
        $this->assertSame(0, lessons::auto_complete_due(time()));
        $this->assertSame('in_progress', $DB->get_field('nit_lesson', 'status', ['id' => $id]));
    }

    /**
     * The scheduled task completes every due lesson and tells each student.
     *
     * @return void
     */
    public function test_task_completes_all_due_and_notifies_student(): void {
        global $DB;
        $first = $this->started(300, 1);
        $second = $this->started(300, 2);
        $sink = $this->redirectMessages();

        ob_start();
        (new auto_complete_lessons())->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('2 lesson(s) auto-completed', $output);
        foreach ([$first, $second] as $id) {
            $this->assertSame('completed', $DB->get_field('nit_lesson', 'status', ['id' => $id]));
        }
        $tostudent = array_filter($sink->get_messages(),
            fn($m) => (int) $m->useridto === (int) $this->student->id);
        $this->assertCount(2, $tostudent, 'the student should hear about each completed lesson');
    }

    /**
     * The task is registered so cron runs it.
     *
     * @return void
     */
    public function test_task_is_registered(): void {
        $this->assertInstanceOf(auto_complete_lessons::class,
            \core\task\manager::get_scheduled_task(auto_complete_lessons::class));
    }
}

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
        // local_nit_tutors (when installed) books only approved tutors; these tests are about lessons.
        set_config('requireapproval', 0, 'local_nit_tutors');
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
     * @param bool $joined whether the student opened the room
     * @return int lesson id
     */
    private function started(int $minutesago, int $days = 1, bool $joined = true): int {
        global $DB;
        $lesson = $this->confirmed($days);
        lessons::start((int) $this->teacher->id, $lesson['id']);
        $DB->set_field('nit_lesson', 'actual_start', time() - $minutesago * MINSECS, ['id' => $lesson['id']]);
        if ($joined) {
            $DB->set_field('nit_lesson', 'student_joined', time() - $minutesago * MINSECS, ['id' => $lesson['id']]);
        }
        return (int) $lesson['id'];
    }

    /**
     * The default waits 72 hours, like Preply, so the student has time to report a problem.
     *
     * @return void
     */
    public function test_default_grace_is_72_hours(): void {
        unset_config('auto_complete_minutes', 'local_nit_lessons');
        $this->assertSame(4320, (new \local_nit_lessons\service\settings_service())->get('auto_complete_minutes'));
    }

    /**
     * A started lesson the student never joined is a no-show case for support: it is never
     * auto-completed, so the teacher is not paid for it automatically.
     *
     * @return void
     */
    public function test_lesson_student_never_joined_is_left_for_support(): void {
        global $DB;
        $id = $this->started(10 * 24 * 60, 1, false);
        $this->assertSame(0, lessons::auto_complete_due(time()));
        $this->assertSame('in_progress', $DB->get_field('nit_lesson', 'status', ['id' => $id]));
        $this->assertSame(0, wallet::teacher((int) $this->teacher->id)['available_balance_minor']);
        $this->assertSame(1, purchase::active((int) $this->student->id)['reserved_flex']);
    }

    /**
     * Opening the room as the student records the first join; the teacher opening it does not,
     * and a second student visit keeps the first time.
     *
     * @return void
     */
    public function test_student_opening_the_room_records_the_join(): void {
        global $DB;
        room_factory::set_override(new class extends stub_room {
            public function session_payload(\local_nit_lessons\entity\lesson $lesson, int $viewerid): ?array {
                return ['join_url' => 'https://room.test/lesson'];
            }
        });
        $lesson = $this->confirmed();
        lessons::start((int) $this->teacher->id, $lesson['id']);
        $service = new \local_nit_lessons\service\lesson_service();

        $service->open_room((int) $this->teacher->id, $lesson['id']);
        $this->assertSame(0, (int) $DB->get_field('nit_lesson', 'student_joined', ['id' => $lesson['id']]),
            'the teacher opening the room is not the student joining');

        $service->open_room((int) $this->student->id, $lesson['id']);
        $first = (int) $DB->get_field('nit_lesson', 'student_joined', ['id' => $lesson['id']]);
        $this->assertGreaterThan(0, $first);

        $DB->set_field('nit_lesson', 'student_joined', $first - 600, ['id' => $lesson['id']]);
        $service->open_room((int) $this->student->id, $lesson['id']);
        $this->assertSame($first - 600, (int) $DB->get_field('nit_lesson', 'student_joined', ['id' => $lesson['id']]),
            'a later visit keeps the first join time');
    }

    /**
     * The upgrade moves the earlier 24-hour default to 72 hours and keeps a value an admin chose.
     *
     * @return void
     */
    public function test_upgrade_moves_24h_default_to_72h(): void {
        global $CFG;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');

        set_config('version', 2026101800, 'local_nit_lessons');
        set_config('auto_complete_minutes', 1440, 'local_nit_lessons');
        xmldb_local_nit_lessons_upgrade(2026101800);
        $this->assertSame('4320', get_config('local_nit_lessons', 'auto_complete_minutes'));

        set_config('version', 2026101800, 'local_nit_lessons');
        set_config('auto_complete_minutes', 600, 'local_nit_lessons');
        xmldb_local_nit_lessons_upgrade(2026101800);
        $this->assertSame('600', get_config('local_nit_lessons', 'auto_complete_minutes'));
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

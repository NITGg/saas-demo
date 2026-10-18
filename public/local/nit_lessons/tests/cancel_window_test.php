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

use local_nit_flex\api\packages;
use local_nit_flex\api\purchase;
use local_nit_lessons\api\lessons;
use local_nit_lessons\exception\lesson_exception;
use local_nit_lessons\room\room_factory;
use local_nit_lessons\room\stub_room;
use local_nit_lessons\service\settings_service;
use local_nit_lessons\service\teacher_service;

/**
 * Free cancel / reschedule window: 12 hours by default (academy spec 2.2).
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_lessons\service\settings_service
 * @covers     \local_nit_lessons\service\lesson_service::cancel_as_student
 * @covers     \local_nit_lessons\service\lesson_service::request_time_update
 */
final class cancel_window_test extends \advanced_testcase {
    /** @var \stdClass */
    private $student;

    /** @var \stdClass */
    private $teacher;

    /**
     * A student with 10 Flex and a bookable Physics teacher; the cancel and reschedule windows
     * are left unset so the defaults apply.
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
        set_config('min_booking_minutes', 0, 'local_nit_lessons');
        unset_config('cancel_deadline_minutes', 'local_nit_lessons');
        unset_config('update_deadline_minutes', 'local_nit_lessons');
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
     * A confirmed lesson, then moved so it starts $hours from now.
     *
     * @param float $hours
     * @param int $days booking day, so several lessons do not clash
     * @return int lesson id
     */
    private function lesson_in(float $hours, int $days = 1): int {
        global $DB;
        $when = (new \DateTimeImmutable('today', teacher_service::timezone((int) $this->teacher->id)))
            ->modify("+{$days} day")->setTime(12, 0)->getTimestamp();
        $lesson = lessons::request((int) $this->student->id, (int) $this->teacher->id, 'Physics', $when, 'note');
        $lesson = lessons::teacher_respond((int) $this->teacher->id, $lesson['id'], 'accept');
        $DB->set_field('nit_lesson', 'confirmed_time', time() + (int) ($hours * HOURSECS), ['id' => $lesson['id']]);
        return (int) $lesson['id'];
    }

    /**
     * Both windows default to 720 minutes.
     *
     * @return void
     */
    public function test_defaults_are_twelve_hours(): void {
        $settings = new settings_service();
        $this->assertSame(720, $settings->get('cancel_deadline_minutes'));
        $this->assertSame(720, $settings->get('update_deadline_minutes'));
    }

    /**
     * Cancelling 13 hours ahead returns the Flex; 11 hours ahead uses it.
     *
     * @return void
     */
    public function test_cancel_inside_twelve_hours_uses_the_flex(): void {
        $early = lessons::cancel_as_student((int) $this->student->id, $this->lesson_in(13, 1));
        $this->assertSame('returned', $early['flex_state'], '13h ahead is a free cancel');

        $late = lessons::cancel_as_student((int) $this->student->id, $this->lesson_in(11, 2));
        $this->assertSame('consumed', $late['flex_state'], '11h ahead is a late cancel');
        $this->assertSame(9, purchase::active((int) $this->student->id)['remaining_flex']);
    }

    /**
     * A new time can be asked for 13 hours ahead, not 11.
     *
     * @return void
     */
    public function test_reschedule_closes_twelve_hours_before(): void {
        $sid = (int) $this->student->id;
        $newtime = (new \DateTimeImmutable('today', teacher_service::timezone((int) $this->teacher->id)))
            ->modify('+5 day')->setTime(12, 0)->getTimestamp();

        lessons::request_time_update($sid, $this->lesson_in(13, 1), $newtime);

        try {
            lessons::request_time_update($sid, $this->lesson_in(11, 2), $newtime + HOURSECS);
            $this->fail('err_updatedeadline expected 11h before the lesson');
        } catch (lesson_exception $e) {
            $this->assertSame('err_updatedeadline', $e->errorcode);
        }
    }

    /**
     * The upgrade moves the old 120-minute default to 720 but keeps a value an admin chose.
     *
     * @return void
     */
    public function test_upgrade_moves_old_default_only(): void {
        global $CFG;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');

        set_config('version', 2026101100, 'local_nit_lessons');
        set_config('cancel_deadline_minutes', 120, 'local_nit_lessons');
        set_config('update_deadline_minutes', 300, 'local_nit_lessons');

        xmldb_local_nit_lessons_upgrade(2026101100);

        $this->assertSame('720', get_config('local_nit_lessons', 'cancel_deadline_minutes'));
        $this->assertSame('300', get_config('local_nit_lessons', 'update_deadline_minutes'));
    }
}

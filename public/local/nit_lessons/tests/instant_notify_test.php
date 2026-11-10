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
use local_nit_lessons\local\push_defaults;
use local_nit_lessons\room\room_factory;
use local_nit_lessons\room\stub_room;
use local_nit_lessons\service\instant_service;
use local_nit_lessons\service\teacher_service;

/**
 * The teacher really gets the "a student wants a lesson now" notification: the message goes
 * through Moodle's real outputs (no message sink) and lands in the bell, after the push upgrade.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_lessons\service\instant_service
 */
final class instant_notify_test extends \advanced_testcase {
    public function test_online_teacher_gets_the_request_notification(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback(); // Messages are sent after the transaction.
        $gen = $this->getDataGenerator();
        $student = $gen->create_user();
        $teacher = $gen->create_user();
        $course = $gen->create_course();
        set_config('subjects', 'Physics', 'local_nit_lessons');
        set_config('requireapproval', 0, 'local_nit_tutors');
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        (new teacher_service())->save((int) $teacher->id, true, '', ['Physics'], []);
        room_factory::set_override(new stub_room());
        purchase::fulfil((int) $student->id, packages::create((object) [
            'name' => 'Flex10', 'flex_count' => 10, 'price_minor' => 100000, 'expiration_days' => 0,
        ]));
        push_defaults::enable(); // As on the server after the upgrade.

        $service = new instant_service();
        $service->set_online((int) $teacher->id, true);
        $service->request((int) $student->id, 'Physics', 'question 4');
        room_factory::set_override(null);

        $this->assertDebuggingNotCalled();
        $this->assertTrue($DB->record_exists('notifications', ['useridto' => $teacher->id,
            'component' => 'local_nit_lessons', 'eventtype' => 'instantoffer']), 'The teacher has the request in the bell.');
    }
}

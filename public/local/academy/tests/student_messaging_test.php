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

use core_external\external_api;
use local_academy\local\student_messaging;

/**
 * "Prevent messaging between students", through the same external-function
 * entry point the message drawer (AJAX) uses.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_academy\local\student_messaging
 */
final class student_messaging_test extends \advanced_testcase {

    /** @var \stdClass */
    private $s1;
    /** @var \stdClass */
    private $s2;
    /** @var \stdClass */
    private $teacher;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback(); // Messages are sent through the message API.
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->s1 = $gen->create_and_enrol($course, 'student');
        $this->s2 = $gen->create_and_enrol($course, 'student');
        $this->teacher = $gen->create_and_enrol($course, 'editingteacher');
        student_messaging::reset_cache();
    }

    /** Turn the setting on/off. */
    private function rule(bool $on): void {
        set_config(student_messaging::CONFIG, $on ? 1 : 0, 'local_academy');
    }

    /** Call an external function as $user, the way the drawer does. */
    private function call(\stdClass $user, string $function, array $args): array {
        $this->setUser($user);
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function($function, $args, true);
    }

    /** Send one instant message; returns the per-message result. */
    private function send(\stdClass $from, \stdClass $to): array {
        $response = $this->call($from, 'core_message_send_instant_messages',
            ['messages' => [['touserid' => $to->id, 'text' => 'hi', 'textformat' => FORMAT_PLAIN]]]);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        return $response['data'][0];
    }

    public function test_off_students_can_message_each_other(): void {
        $this->rule(false);
        $this->assertGreaterThan(0, $this->send($this->s1, $this->s2)['msgid']);
    }

    public function test_on_student_to_student_is_refused(): void {
        $this->rule(true);
        $result = $this->send($this->s1, $this->s2);
        $this->assertSame(-1, $result['msgid']);
        $this->assertSame(get_string('studentmessaging_blocked', 'local_academy'), $result['errormessage']);
    }

    public function test_on_student_and_teacher_can_message_each_other(): void {
        $this->rule(true);
        $this->assertGreaterThan(0, $this->send($this->s1, $this->teacher)['msgid']);
        $this->assertGreaterThan(0, $this->send($this->teacher, $this->s1)['msgid']);
    }

    public function test_on_mixed_batch_keeps_order_and_sends_the_allowed_one(): void {
        $this->rule(true);
        $response = $this->call($this->s1, 'core_message_send_instant_messages', ['messages' => [
            ['touserid' => $this->s2->id, 'text' => 'a', 'textformat' => FORMAT_PLAIN, 'clientmsgid' => 'one'],
            ['touserid' => $this->teacher->id, 'text' => 'b', 'textformat' => FORMAT_PLAIN, 'clientmsgid' => 'two'],
        ]]);
        $this->assertFalse($response['error']);
        [$first, $second] = $response['data'];
        $this->assertSame('one', $first['clientmsgid']);
        $this->assertSame(-1, $first['msgid']);
        $this->assertSame('two', $second['clientmsgid']);
        $this->assertGreaterThan(0, $second['msgid']);
    }

    public function test_on_existing_conversation_between_students_is_refused(): void {
        // A conversation made before the setting was turned on.
        $this->rule(false);
        $conversationid = $this->send($this->s1, $this->s2)['conversationid'];
        $this->rule(true);
        $response = $this->call($this->s2, 'core_message_send_messages_to_conversation',
            ['conversationid' => $conversationid, 'messages' => [['text' => 'reply', 'textformat' => FORMAT_PLAIN]]]);
        $this->assertTrue($response['error']);
        $this->assertSame('studentmessaging_blocked', $response['exception']->errorcode);
    }

    public function test_on_contact_request_between_students_is_refused(): void {
        $this->rule(true);
        $response = $this->call($this->s1, 'core_message_create_contact_request',
            ['userid' => $this->s1->id, 'requesteduserid' => $this->s2->id]);
        $this->assertTrue($response['error']);
        $this->assertSame('studentmessaging_blocked', $response['exception']->errorcode);

        $response = $this->call($this->s1, 'core_message_create_contact_request',
            ['userid' => $this->s1->id, 'requesteduserid' => $this->teacher->id]);
        $this->assertFalse($response['error']);
    }

    public function test_on_member_info_closes_the_message_box_only_for_students(): void {
        $this->rule(true);
        $response = $this->call($this->s1, 'core_message_get_member_info', [
            'referenceuserid' => $this->s1->id,
            'userids' => [$this->s2->id, $this->teacher->id],
            'includecontactrequests' => false,
            'includeprivacyinfo' => true,
        ]);
        $this->assertFalse($response['error']);
        $canmessage = array_column($response['data'], 'canmessage', 'id');
        $this->assertFalse($canmessage[$this->s2->id]);
        $this->assertTrue($canmessage[$this->teacher->id]);
    }
}

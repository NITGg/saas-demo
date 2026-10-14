<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_nit_assistant;

use local_nit_assistant\chat\access;
use local_nit_assistant\runtime\state;
use local_nit_assistant\tools\context;
use local_nit_assistant\tools\registry;

/**
 * The student tools: each returns the signed-in student's own data and never
 * another student's, whatever the model asks; the mode is decided by the server.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\student\my_subscription::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\student\my_flex::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\student\my_lessons::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\student\my_live_sessions::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\student\my_courses::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\student\my_wallet::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\student\my_payments::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\student\lesson_video_help::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\student\open_ticket::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\chat\access::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\inbox\manager::class)]
final class student_tools_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $amira;

    /** @var \stdClass */
    private \stdClass $badr;

    /** @var \stdClass */
    private \stdClass $teacher;

    /** @var registry */
    private registry $tools;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->amira = $gen->create_user(['firstname' => 'Amira']);
        $this->badr = $gen->create_user(['firstname' => 'Badr']);
        $this->teacher = $gen->create_user(['firstname' => 'Mona', 'lastname' => 'Ali']);
        $this->tools = new registry();
    }

    /**
     * Run a tool for a user as the model would, in that user's own conversation.
     *
     * @param string $name
     * @param \stdClass $user
     * @param array $input
     * @param context|null $ctx
     * @return array
     */
    private function call(string $name, \stdClass $user, array $input = [], ?context &$ctx = null): array {
        global $DB;
        $this->setUser($user);
        $convid = $DB->insert_record('local_nit_asst_conv', (object) ['userid' => $user->id, 'status' => state::OPEN,
            'mode' => 'student', 'lang' => 'en', 'timecreated' => time()]);
        $ctx = new context((int) $convid, 'turn-1', 'student', 'web', 'en', (int) $user->id, time());
        return $this->tools->execute($ctx, $name, $input, 'toolu_' . random_string(8));
    }

    public function test_the_mode_is_decided_by_the_server(): void {
        $this->assertSame('sales', access::mode(0));
        $this->assertSame('sales', access::mode((int) $this->amira->id), 'a new account is still a prospect');
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->amira->id, $course->id, 'student');
        $this->assertSame('student', access::mode((int) $this->amira->id));

        $plan = \local_nit_subscriptions\subscription_manager::create_subscription(['name' => 'Gold', 'price' => 500,
            'duration_days' => 30, 'active' => 1], 2);
        \local_nit_subscriptions\subscription_purchase_manager::create_wallet_purchase((int) $this->badr->id, $plan, 500, 'r-b');
        $this->assertSame('student', access::mode((int) $this->badr->id), 'a subscriber is a student');
    }

    public function test_student_tools_are_not_offered_to_visitors(): void {
        $names = array_column($this->tools->definitions('sales'), 'name');
        foreach (['my_subscription', 'my_flex', 'my_lessons', 'my_wallet', 'my_payments', 'open_ticket'] as $tool) {
            $this->assertNotContains($tool, $names);
        }
        $ctx = new context(1, 't', 'sales', 'web', 'en', (int) $this->amira->id, time());
        $this->assertSame('not_authorized', $this->tools->execute($ctx, 'my_wallet', [], 'x')['code']);
    }

    public function test_each_student_sees_only_their_own_subscription(): void {
        $sm = '\local_nit_subscriptions\subscription_manager';
        $gold = $sm::create_subscription(['name' => 'Gold', 'price' => 500, 'duration_days' => 30, 'active' => 1], 2);
        $silver = $sm::create_subscription(['name' => 'Silver', 'price' => 200, 'duration_days' => 10, 'active' => 1], 2);
        \local_nit_subscriptions\subscription_purchase_manager::create_wallet_purchase((int) $this->amira->id, $gold, 500, 'r-a');
        \local_nit_subscriptions\subscription_purchase_manager::create_wallet_purchase((int) $this->badr->id, $silver, 200, 'r-b');

        $r = $this->call('my_subscription', $this->amira);
        $this->assertSame('Gold', $r['data']['active']['plan']);
        $this->assertStringNotContainsString('Silver', json_encode($r));
        $this->assertSame('Silver', $this->call('my_subscription', $this->badr)['data']['active']['plan']);
    }

    public function test_flex_and_wallet_are_the_students_own(): void {
        $pkg = \local_nit_flex\api\packages::create((object) ['name' => 'Ten lessons', 'flex_count' => 10,
            'price_minor' => 100000, 'expiration_days' => 60, 'status' => 'active']);
        \local_nit_flex\api\purchase::assign(2, (int) $this->amira->id, $pkg);
        \local_nit_finance\local\wallets::move('student', (int) $this->amira->id, 25000, 'topup', ['note' => 'Fawry']);
        \local_nit_finance\local\wallets::move('student', (int) $this->badr->id, 99900, 'topup');

        $flex = $this->call('my_flex', $this->amira)['data'];
        $this->assertSame(10, $flex['available_credits']);
        $this->assertSame('Ten lessons', $flex['package']['name']);
        $this->assertSame(0, $this->call('my_flex', $this->badr)['data']['available_credits']);

        $wallet = $this->call('my_wallet', $this->amira)['data'];
        $this->assertSame(250.0, $wallet['balance']);
        $this->assertSame(999.0, $this->call('my_wallet', $this->badr)['data']['balance']);
    }

    public function test_lessons_show_the_join_link_only_while_the_room_is_open(): void {
        global $DB;
        $now = time();
        $lesson = static fn(int $studentid, string $status, int $when): int => $DB->insert_record('nit_lesson', (object) [
            'studentid' => $studentid, 'teacherid' => 0, 'subject' => 'Maths', 'status' => $status,
            'requested_time' => $when, 'duration' => 60, 'cmid' => 0, 'flex_state' => 'none',
            'timecreated' => $now, 'timemodified' => $now]);
        $open = $DB->insert_record('nit_lesson', (object) ['studentid' => $this->amira->id, 'teacherid' => $this->teacher->id,
            'subject' => 'Maths', 'status' => 'in_progress', 'requested_time' => $now, 'confirmed_time' => $now,
            'duration' => 60, 'cmid' => 77, 'flex_state' => 'reserved', 'timecreated' => $now, 'timemodified' => $now]);
        $lesson((int) $this->amira->id, 'pending', $now + DAYSECS);
        $lesson((int) $this->badr->id, 'confirmed', $now + 2 * DAYSECS);

        $r = $this->call('my_lessons', $this->amira, [], $ctx)['data'];
        $this->assertCount(1, $r['upcoming']);
        $this->assertCount(1, $r['waiting']);
        $this->assertStringContainsString('/local/nit_lessons/join.php?id=' . $open, $r['upcoming'][0]['join_url']);
        $this->assertSame('in_progress', $r['upcoming'][0]['status']);
        $this->assertNotEmpty($r['rules']['free_cancellation']);
        $this->assertContains($r['upcoming'][0]['join_url'], array_column($ctx->actions, 'url'));
        $this->assertArrayNotHasKey('join_url', $r['waiting'][0]);

        $b = $this->call('my_lessons', $this->badr)['data'];
        $this->assertCount(1, $b['upcoming']);
        $this->assertArrayNotHasKey('join_url', $b['upcoming'][0], 'confirmed but not started: no link yet');
        $this->assertSame([], $b['waiting']);
    }

    public function test_payments_are_the_students_own_with_a_retry_link_for_failed_ones(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Physics']);
        $tx = static fn(int $userid, string $status) => $DB->insert_record('local_payments_transactions', (object) [
            'userid' => $userid, 'courseid' => $course->id, 'order_id' => uniqid('o'), 'provider_id' => 1,
            'idempotency_key' => uniqid('k'), 'amount' => 250, 'currency' => 'EGP',
            'status' => $status, 'metadata' => json_encode(['item_type' => 'course']), 'timecreated' => time(),
            'timemodified' => time()]);
        $tx((int) $this->amira->id, 'failed');
        $tx((int) $this->badr->id, 'completed');

        $r = $this->call('my_payments', $this->amira)['data']['payments'];
        $this->assertCount(1, $r);
        $this->assertSame('failed', $r[0]['status']);
        $this->assertSame('Physics', $r[0]['course']);
        $this->assertStringContainsString('/local/payments/buy.php?courseid=' . $course->id, $r[0]['try_again_url']);
        $b = $this->call('my_payments', $this->badr)['data']['payments'];
        $this->assertSame('completed', $b[0]['status']);
        $this->assertArrayNotHasKey('try_again_url', $b[0]);
    }

    public function test_courses_with_progress_are_the_students_own(): void {
        $gen = $this->getDataGenerator();
        $mine = $gen->create_course(['fullname' => 'Algebra', 'enablecompletion' => 1]);
        $theirs = $gen->create_course(['fullname' => 'Chemistry']);
        $gen->enrol_user($this->amira->id, $mine->id, 'student');
        $gen->enrol_user($this->badr->id, $theirs->id, 'student');
        $r = $this->call('my_courses', $this->amira)['data'];
        $this->assertSame(['Algebra'], array_column($r['courses'], 'name'));
        $this->assertSame(['Chemistry'], array_column($this->call('my_courses', $this->badr)['data']['courses'], 'name'));
    }

    public function test_video_help_points_to_the_students_own_lessons_with_an_assistant(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $mine = $gen->create_course(['fullname' => 'Algebra']);
        $other = $gen->create_course(['fullname' => 'Algebra advanced']);
        $gen->enrol_user($this->amira->id, $mine->id, 'student');
        $page = $gen->create_module('page', ['course' => $mine->id, 'name' => 'Quadratic equations']);
        $hidden = $gen->create_module('page', ['course' => $other->id, 'name' => 'Quadratic tricks']);
        foreach ([$page, $hidden] as $cm) {
            $DB->insert_record('local_nit_ai_transcripts', (object) ['cmid' => $cm->cmid, 'courseid' => $cm->course,
                'component' => 'mod_page', 'enabled' => 1, 'approved' => 1, 'timecreated' => time(), 'timemodified' => time()]);
        }
        $r = $this->call('lesson_video_help', $this->amira, ['query' => 'quadratic'])['data']['lessons'];
        $this->assertSame(['Quadratic equations'], array_column($r, 'lesson'));
    }

    public function test_a_ticket_is_masked_capped_and_alerts_the_team(): void {
        global $DB;
        $sink = $this->redirectMessages();
        $input = ['category' => 'billing', 'subject' => 'Charged twice for Gold',
            'description' => 'I paid twice on 12 Oct with card 4111 1111 1111 1111, please refund one.'];
        $r = $this->call('open_ticket', $this->amira, $input, $ctx);
        $this->assertTrue($r['ok']);
        foreach ($ctx->afterturn as $task) {
            $task();
        }
        $ticket = $DB->get_record('local_nit_asst_ticket', ['id' => $r['data']['ticket_number']]);
        $this->assertSame((int) $this->amira->id, (int) $ticket->userid);
        $this->assertStringNotContainsString('4111 1111 1111 1111', $ticket->description);
        $this->assertGreaterThan(0, $sink->count());

        for ($i = 1; $i < tools\student\open_ticket::PER_DAY; $i++) {
            $this->assertTrue($this->call('open_ticket', $this->amira, $input)['ok']);
        }
        $this->assertSame('rate_limited', $this->call('open_ticket', $this->amira, $input)['code']);
        $this->assertTrue($this->call('open_ticket', $this->badr, $input)['ok'], 'the cap is per student');

        [$rows, $total] = inbox\manager::tickets('', 0, 10);
        $this->assertSame(4, $total);
        inbox\manager::update_ticket((int) $ticket->id, 'resolved', 'Refunded', 2);
        $this->assertSame('resolved', $DB->get_field('local_nit_asst_ticket', 'status', ['id' => $ticket->id]));
        $sink->close();
    }
}

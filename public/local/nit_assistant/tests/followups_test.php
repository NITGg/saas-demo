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

use local_nit_assistant\followup\finder;
use local_nit_assistant\followup\manager;

/**
 * Follow-ups: who gets one (and who must not), drafts in each user's language,
 * nothing sent without staff approval, one per subject ever, a cooldown per person.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\followup\finder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\followup\manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\task\followups::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\inbox\alerts::class)]
final class followups_test extends \advanced_testcase {
    /** @var int */
    private int $now;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->now = time();
    }

    /**
     * A payment row.
     *
     * @param int $userid
     * @param string $status
     * @param int $ago seconds
     * @param string $type
     * @param int $itemid
     * @return int
     */
    private function payment(int $userid, string $status, int $ago, string $type, int $itemid): int {
        global $DB;
        return $DB->insert_record('local_payments_transactions', (object) [
            'userid' => $userid, 'courseid' => $type === 'course' ? $itemid : 0, 'provider_id' => 1,
            'order_id' => uniqid('o'), 'idempotency_key' => uniqid('k'), 'amount' => 250, 'currency' => 'EGP',
            'status' => $status, 'metadata' => json_encode(['item_type' => $type, 'item_id' => $itemid]),
            'timecreated' => $this->now - $ago, 'timemodified' => $this->now - $ago,
        ]);
    }

    public function test_an_unfinished_payment_is_followed_up_until_they_buy(): void {
        $gen = $this->getDataGenerator();
        $u = $gen->create_user();
        $course = $gen->create_course(['fullname' => 'Physics']);
        $this->payment((int) $u->id, 'failed', 2 * DAYSECS, 'course', (int) $course->id);
        $this->payment((int) $u->id, 'failed', HOURSECS, 'course', $gen->create_course()->id); // Too fresh.

        $c = finder::checkout_unpaid($this->now);
        $this->assertCount(1, $c);
        $this->assertSame('Physics', $c[0]['item']);
        $this->assertStringContainsString('/local/payments/buy.php?courseid=' . $course->id, $c[0]['url']);

        $this->payment((int) $u->id, 'completed', DAYSECS, 'course', (int) $course->id);
        $this->assertSame([], finder::checkout_unpaid($this->now), 'they paid afterwards');
    }

    public function test_an_ended_plan_is_followed_up_only_without_a_current_one(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $a = $gen->create_user();
        $b = $gen->create_user();
        $plan = \local_nit_subscriptions\subscription_manager::create_subscription(['name' => 'Gold', 'price' => 500,
            'duration_days' => 30, 'active' => 1], 2);
        foreach ([$a, $b] as $u) {
            $p = \local_nit_subscriptions\subscription_purchase_manager::create_wallet_purchase(
                (int) $u->id,
                $plan,
                500,
                'r' . $u->id
            );
            $DB->set_field('nit_sub_purchase', 'expires_at', $this->now - 5 * DAYSECS, ['id' => $p->id]);
        }
        // B renewed.
        \local_nit_subscriptions\subscription_purchase_manager::create_wallet_purchase((int) $b->id, $plan, 500, 'renew');

        $c = finder::winback($this->now);
        $this->assertSame([(int) $a->id], array_column($c, 'userid'));
        $this->assertSame('Gold', $c[0]['item']);
    }

    public function test_a_buying_link_from_the_chat_is_followed_up_if_unused(): void {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Maths']);
        $DB->insert_record('local_nit_asst_audit', (object) ['convid' => 7, 'turnid' => 't', 'userid' => $u->id,
            'mode' => 'sales', 'tool' => 'start_checkout', 'status' => 'ok',
            'args' => json_encode(['item_type' => 'course', 'item_id' => (int) $course->id]),
            'timecreated' => $this->now - 3 * DAYSECS]);
        $c = finder::chat_interest($this->now);
        $this->assertSame(7, $c[0]['convid']);
        $this->assertSame('Maths', $c[0]['item']);

        $this->getDataGenerator()->enrol_user($u->id, $course->id, 'student');
        $this->assertSame([], finder::chat_interest($this->now), 'they enrolled');
    }

    public function test_unused_credits_are_followed_up_unless_a_lesson_was_asked_for(): void {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $pkg = \local_nit_flex\api\packages::create((object) ['name' => 'Ten', 'flex_count' => 10, 'price_minor' => 1000,
            'expiration_days' => 0, 'status' => 'active']);
        \local_nit_flex\api\purchase::assign(2, (int) $u->id, $pkg);
        $DB->set_field('nit_package_purchase', 'timeactivated', $this->now - 20 * DAYSECS, ['userid' => $u->id]);
        $c = finder::flex_idle($this->now);
        $this->assertSame('10', $c[0]['item']);

        $DB->insert_record('nit_lesson', (object) ['studentid' => $u->id, 'teacherid' => 0, 'subject' => 'Maths',
            'status' => 'pending', 'requested_time' => $this->now + DAYSECS, 'duration' => 60, 'flex_state' => 'none',
            'timecreated' => $this->now - DAYSECS, 'timemodified' => $this->now]);
        $this->assertSame([], finder::flex_idle($this->now));
    }

    public function test_drafts_are_in_the_users_language_once_per_subject_and_never_sent_by_themselves(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $ar = $gen->create_user(['firstname' => 'سارة']);
        $en = $gen->create_user(['firstname' => 'Omar', 'lang' => 'en']);
        $off = $gen->create_user(['suspended' => 1]);
        // The test site has no Arabic pack, so the generator stores "en"; academies have Arabic installed.
        $DB->set_field('user', 'lang', 'ar', ['id' => $ar->id]);
        $cand = static fn($u, string $ref) => ['userid' => (int) $u->id, 'kind' => 'winback', 'refkey' => $ref,
            'item' => '{mlang en}Gold{mlang}{mlang ar}ذهبي{mlang}', 'url' => 'https://x.test/renew', 'convid' => null];
        $sink = $this->redirectMessages();

        $made = manager::create_drafts($this->now, [$cand($ar, 'w:1'), $cand($en, 'w:2'), $cand($off, 'w:3'), $cand($en, 'w:4')]);
        $this->assertSame(2, $made, 'suspended users are skipped; one draft per person at a time');
        $this->assertSame(0, $sink->count(), 'nothing is sent without approval');
        $arabic = $DB->get_record('local_nit_asst_followup', ['refkey' => 'w:1']);
        $this->assertStringContainsString('ذهبي', $arabic->title);
        $this->assertStringContainsString('سارة', $arabic->body);
        $this->assertStringContainsString('Gold', $DB->get_field('local_nit_asst_followup', 'title', ['refkey' => 'w:2']));
        $this->assertSame(0, manager::create_drafts($this->now, [$cand($ar, 'w:1')]), 'the same subject is never drafted twice');
        $sink->close();
    }

    public function test_staff_approve_send_once_and_the_cooldown_holds(): void {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $cand = static fn(string $ref) => ['userid' => (int) $u->id, 'kind' => 'checkout_unpaid', 'refkey' => $ref,
            'item' => 'Physics', 'url' => 'https://x.test/pay', 'convid' => null];
        manager::create_drafts($this->now, [$cand('c:1')]);
        $id = (int) $DB->get_field('local_nit_asst_followup', 'id', ['refkey' => 'c:1']);
        $sink = $this->redirectMessages();

        $this->assertTrue(manager::approve($id, 2, 'Your payment', 'Edited text'));
        $this->assertSame(1, $sink->count());
        $row = $DB->get_record('local_nit_asst_followup', ['id' => $id]);
        $this->assertSame('sent', $row->status);
        $this->assertSame('Edited text', $row->body);
        $this->assertFalse(manager::approve($id, 2), 'sent only once');
        $this->assertSame(1, $sink->count());

        $this->assertSame(0, manager::create_drafts($this->now, [$cand('c:2')]), 'cooldown after a sent follow-up');
        $later = $this->now + (manager::cooldown_days() + 1) * DAYSECS;
        $this->assertSame(1, manager::create_drafts($later, [$cand('c:3')]));
        $draft = (int) $DB->get_field('local_nit_asst_followup', 'id', ['refkey' => 'c:3']);
        $this->assertTrue(manager::dismiss($draft, 2));
        $this->assertSame('dismissed', $DB->get_field('local_nit_asst_followup', 'status', ['id' => $draft]));
        $this->assertFalse(manager::approve($draft, 2), 'a dismissed draft cannot be sent');
        $sink->close();
    }

    public function test_the_daily_task_drafts_only_when_switched_on_and_tells_staff(): void {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->payment((int) $u->id, 'expired', 2 * DAYSECS, 'course', (int) $course->id);
        $DB->insert_record('local_nit_asst_lead', (object) ['convid' => 1, 'name' => 'Hany', 'phone' => '+201000',
            'status' => 'new', 'consenttime' => 1, 'timecreated' => $this->now - 3 * DAYSECS, 'timemodified' => 1]);
        $sink = $this->redirectMessages();
        $task = new task\followups();

        ob_start();
        $task->execute();
        ob_end_clean();
        $this->assertSame(0, $DB->count_records('local_nit_asst_followup'), 'off by default');
        $this->assertSame(1, $sink->count(), 'only the leads reminder');

        set_config('followups', 1, 'local_nit_assistant');
        ob_start();
        $task->execute();
        ob_end_clean();
        $this->assertSame(1, $DB->count_records('local_nit_asst_followup', ['status' => 'draft']));
        $this->assertSame(3, $sink->count(), 'drafts waiting + leads reminder');
        $sink->close();
    }
}

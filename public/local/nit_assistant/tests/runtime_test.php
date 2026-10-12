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

use local_nit_assistant\llm\profiles;
use local_nit_assistant\llm\usage;
use local_nit_assistant\runtime\budget;
use local_nit_assistant\runtime\rate_limit;
use local_nit_assistant\runtime\state;

/**
 * Budget, prices, rate limits and the conversation state machine.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\runtime\budget::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\runtime\rate_limit::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\runtime\state::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\llm\profiles::class)]
final class runtime_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_prices_follow_the_model_the_api_returns_including_dated_ids(): void {
        $million = new usage(1000000, 0, 0, 0);
        $this->assertEqualsWithDelta(2.0, profiles::cost('claude-sonnet-5-5', $million), 1e-9);
        $this->assertEqualsWithDelta(0.10, profiles::cost('claude-haiku-5-5-20261001', $million), 1e-9);
        $this->assertSame('claude-haiku-5-5', profiles::price_model('claude-haiku-5-5-20261001'));
        // Unknown models err on the expensive side.
        $this->assertNull(profiles::price_model('claude-new-9'));
        $this->assertEqualsWithDelta(4.0, @profiles::cost('claude-new-9', $million), 1e-9);
        $this->assertDebuggingCalled();
        // Cache reads are cheap, writes cost more than plain input.
        $this->assertEqualsWithDelta(0.2 + 2.5, profiles::cost('claude-sonnet-5-5', new usage(0, 0, 1000000, 1000000)), 1e-9);
    }

    public function test_budget_reserves_only_within_the_daily_limit_and_settles_to_the_real_cost(): void {
        global $DB;
        $now = make_timestamp(2026, 10, 12, 10);
        $this->assertNull(budget::reserve(0.1, 0, $now), 'a budget of 0 keeps the assistant off');

        $a = budget::reserve(0.6, 1.0, $now);
        $this->assertNotNull($a);
        $this->assertNull(budget::reserve(0.6, 1.0, $now), 'the second reservation would exceed the budget');

        $meta = ['kind' => 'chat', 'model' => 'claude-sonnet-5-5', 'convid' => 7, 'userid' => 3];
        budget::settle($a, 0.05, new usage(100, 10), $meta);
        $row = $DB->get_record('local_nit_asst_usage', ['day' => budget::day($now)]);
        $this->assertEqualsWithDelta(0.05, (float) $row->spent, 1e-9);
        $this->assertEqualsWithDelta(0.05, (float) $row->committed, 1e-9);
        $this->assertEqualsWithDelta(0.0, (float) $row->reserved, 1e-9);
        $this->assertNotNull(budget::reserve(0.6, 1.0, $now), 'after settling, there is room again');
        $this->assertTrue(budget::left(1.0, $now));

        $event = $DB->get_record('local_nit_asst_event', ['convid' => 7]);
        $this->assertSame('chat', $event->kind);
        $this->assertEquals(3, $event->userid);
        $this->assertEquals(100, $event->inputtokens);

        // A failed call (no usage) releases the reservation and logs nothing.
        $b = budget::reserve(0.1, 10.0, $now);
        budget::settle($b, 0.0);
        $this->assertSame(1, $DB->count_records('local_nit_asst_event'));
    }

    public function test_budget_alert_is_claimed_once_per_day(): void {
        $now = make_timestamp(2026, 10, 12, 10);
        $this->assertTrue(budget::claim_alert($now));
        $this->assertFalse(budget::claim_alert($now));
        $this->assertTrue(budget::claim_alert($now + 2 * DAYSECS));
    }

    public function test_rate_limit_counts_every_hit_in_the_window_then_resets(): void {
        $now = 1_000_000_000;
        for ($i = 1; $i <= 3; $i++) {
            $this->assertTrue(rate_limit::hit('ip:x', 3, 600, $now)['allowed']);
        }
        $fourth = rate_limit::hit('ip:x', 3, 600, $now);
        $this->assertFalse($fourth['allowed']);
        $this->assertSame(4, $fourth['count']);
        $this->assertTrue(rate_limit::hit('ip:y', 3, 600, $now)['allowed'], 'keys are independent');
        $this->assertTrue(rate_limit::hit('ip:x', 3, 600, $now + 600)['allowed'], 'next window starts fresh');
        $this->assertGreaterThan(0, rate_limit::cleanup($now + 1200));
    }

    public function test_take_over_has_a_single_winner_and_bumps_the_state_version(): void {
        global $DB;
        $id = $DB->insert_record('local_nit_asst_conv', (object) ['status' => state::OPEN, 'timecreated' => time()]);
        $this->assertTrue(state::take_over($id, 11));
        $this->assertFalse(state::take_over($id, 22), 'already taken');
        $conv = $DB->get_record('local_nit_asst_conv', ['id' => $id]);
        $this->assertSame(state::HUMAN, $conv->status);
        $this->assertEquals(11, $conv->assignedto);
        $this->assertEquals(1, $conv->stateversion);
        $this->assertFalse(state::transition($id, [state::OPEN], state::WAITING), 'only from the listed states');
        $this->assertTrue(state::transition($id, [state::HUMAN], state::OPEN, ['assignedto' => null]));
        $this->assertEquals(2, $DB->get_field('local_nit_asst_conv', 'stateversion', ['id' => $id]));
    }

    public function test_one_turn_at_a_time_per_conversation_and_a_crashed_turn_frees_itself(): void {
        global $DB;
        $a = $DB->insert_record('local_nit_asst_conv', (object) ['status' => state::OPEN, 'timecreated' => time()]);
        $b = $DB->insert_record('local_nit_asst_conv', (object) ['status' => state::OPEN, 'timecreated' => time()]);
        $now = 1_000_000_000;
        $this->assertTrue(state::begin_turn($a, 60, $now));
        $this->assertFalse(state::begin_turn($a, 60, $now + 1), 'busy while a turn runs');
        $this->assertTrue(state::begin_turn($b, 60, $now), 'other conversations are independent');
        state::end_turn($a);
        $this->assertTrue(state::begin_turn($a, 60, $now + 2));
        $this->assertTrue(state::begin_turn($b, 60, $now + 61), 'the claim expires if a turn never ended');
        $this->assertFalse(state::begin_turn(999999, 60, $now), 'unknown conversation');
    }
}

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

use local_nit_assistant\runtime\state;
use local_nit_assistant\tools\context;
use local_nit_assistant\tools\registry;

/**
 * The sales tools against the academy's real plugins: visitors only see what
 * the public site shows, prices and offers come from the checkout's own
 * calculation, and private data (teacher phones, coupon codes) never leaks.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\sales\list_subscriptions::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\sales\list_packages::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\sales\search_courses::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\sales\get_course::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\sales\list_offers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\sales\preview_price::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\sales\find_teachers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\sales\start_checkout::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\sales\capture_lead::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\sales\helper::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\common\search_help::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\common\handoff_to_human::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\knowledge\help_index::class)]
final class sales_tools_test extends \advanced_testcase {
    /** @var int */
    private int $convid;

    /** @var registry */
    private registry $tools;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        set_config('forcelogin', 0); // As every academy sets it (local_academy site_defaults): the catalogue is public.
        $this->convid = $DB->insert_record(
            'local_nit_asst_conv',
            (object) ['status' => state::OPEN, 'mode' => 'sales', 'lang' => 'en', 'timecreated' => time()]
        );
        $this->tools = new registry();
    }

    /**
     * Run a tool as the model would.
     *
     * @param string $name
     * @param array $input
     * @param int $userid 0 = visitor
     * @param context|null $ctx
     * @return array
     */
    private function call(string $name, array $input = [], int $userid = 0, ?context &$ctx = null): array {
        $ctx = new context($this->convid, 'turn-1', 'sales', 'web', 'en', $userid, time());
        return $this->tools->execute($ctx, $name, $input, 'toolu_' . random_string(8));
    }

    /**
     * Give a course an active default price.
     *
     * @param int $courseid
     * @param float $price
     * @return void
     */
    private function price(int $courseid, float $price): void {
        global $DB;
        $DB->insert_record('local_payments_course_prices', (object) [
            'courseid' => $courseid, 'country' => '*', 'currency' => 'EGP', 'price' => $price,
            'is_default' => 1, 'is_active' => 1, 'priority' => 0, 'created_by' => 2,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    public function test_every_sales_tool_is_offered_to_visitors_and_listed_once(): void {
        $names = array_column($this->tools->definitions('sales'), 'name');
        $this->assertSame(array_unique($names), $names);
        foreach (
            ['list_subscriptions', 'list_packages', 'search_courses', 'get_course', 'list_offers', 'preview_price',
                'find_teachers', 'search_help', 'start_checkout', 'capture_lead', 'handoff_to_human'] as $tool
        ) {
            $this->assertContains($tool, $names);
        }
        $this->assertNotContains('capture_lead', array_column($this->tools->definitions('student'), 'name'));
    }

    public function test_only_active_plans_are_listed_with_their_offer_price(): void {
        $sm = '\local_nit_subscriptions\subscription_manager';
        $active = $sm::create_subscription(['name' => '{mlang en}Gold{mlang}{mlang ar}ذهبي{mlang}', 'price' => 500,
            'duration_days' => 30, 'active' => 1], 2);
        $sm::create_subscription(['name' => 'Old plan', 'price' => 100, 'duration_days' => 30, 'active' => 0], 2);
        \local_nit_commerce\offer_manager::create_offer(['name' => 'Ramadan', 'discount_type' => 'percent',
            'discount_value' => 10, 'active' => 1, 'items' => [['item_type' => 'subscription', 'item_id' => $active]]], 2);

        $r = $this->call('list_subscriptions');
        $this->assertTrue($r['ok']);
        $this->assertCount(1, $r['data']['plans']);
        $plan = $r['data']['plans'][0];
        $this->assertSame('Gold', $plan['name']);
        $this->assertSame(500.0, $plan['price']);
        $this->assertSame('EGP', $plan['currency']);
        $this->assertSame(450.0, $plan['offer']['final_price']);
    }

    public function test_packages_carry_the_checkout_quote(): void {
        \local_nit_flex\api\packages::create((object) ['name' => 'Ten lessons', 'flex_count' => 10, 'price_minor' => 100000,
            'expiration_days' => 60, 'status' => 'active']);
        $r = $this->call('list_packages');
        $this->assertTrue($r['ok']);
        $p = $r['data']['packages'][0];
        $this->assertSame(10, $p['lesson_credits']);
        $this->assertSame(1000.0, $p['price']);
        $this->assertSame(100.0, $p['price_per_credit']);
        $this->assertSame(60, $p['valid_days']);
        $this->assertArrayNotHasKey('offer_price', $p);
    }

    public function test_visitors_find_visible_courses_only_with_their_price(): void {
        $gen = $this->getDataGenerator();
        $paid = $gen->create_course(['fullname' => 'Physics for grade 3']);
        $gen->create_course(['fullname' => 'Physics secret draft', 'visible' => 0]);
        $this->price((int) $paid->id, 250);

        $r = $this->call('search_courses', ['query' => 'physics']);
        $this->assertTrue($r['ok']);
        $this->assertSame(1, $r['data']['total']);
        $c = $r['data']['courses'][0];
        $this->assertSame('Physics for grade 3', $c['name']);
        $this->assertFalse($c['free']);
        $this->assertSame(250.0, $c['price']);
        $this->assertArrayNotHasKey('enrolled', $c, 'visitors have no enrolment state');

        // A visible course inside a hidden category is not public either.
        $hiddencat = $gen->create_category(['visible' => 0]);
        $gen->create_course(['fullname' => 'Physics in a hidden category', 'category' => $hiddencat->id]);
        $this->assertSame(1, $this->call('search_courses', ['query' => 'physics'])['data']['total']);

        // A whole question that is not in any title still finds the course by its strongest word.
        $r = $this->call('search_courses', ['query' => 'any physics']);
        $this->assertSame(1, $r['data']['total']);
    }

    public function test_a_hidden_course_has_no_details_or_checkout_for_visitors(): void {
        $hidden = $this->getDataGenerator()->create_course(['visible' => 0]);
        $this->assertSame('not_found', $this->call('get_course', ['courseid' => (int) $hidden->id])['code']);
        $this->assertSame('not_found', $this->call('start_checkout', ['item_type' => 'course',
            'item_id' => (int) $hidden->id])['code']);
    }

    public function test_checkout_links_to_the_buy_page_and_tells_visitors_to_sign_in(): void {
        $paid = $this->getDataGenerator()->create_course();
        $this->price((int) $paid->id, 99);
        $r = $this->call('start_checkout', ['item_type' => 'course', 'item_id' => (int) $paid->id], 0, $ctx);
        $this->assertStringContainsString('/local/payments/buy.php?courseid=' . $paid->id, $r['data']['url']);
        $this->assertFalse($r['data']['signed_in']);
        $this->assertSame($r['data']['url'], $ctx->actions[0]['url']);

        $free = $this->getDataGenerator()->create_course();
        $r = $this->call('start_checkout', ['item_type' => 'course', 'item_id' => (int) $free->id]);
        $this->assertStringContainsString('/local/academy/course.php?id=' . $free->id, $r['data']['url']);
    }

    public function test_offers_never_reveal_coupon_codes(): void {
        \local_nit_commerce\offer_manager::create_offer(['name' => 'Summer', 'discount_type' => 'percent',
            'discount_value' => 20, 'active' => 1, 'items' => [['item_type' => 'course', 'item_id' => 0]]], 2);
        \local_nit_commerce\coupon_manager::create_coupon(['code' => 'SECRET50', 'discount_type' => 'percent',
            'discount_value' => 50, 'active' => 1, 'items' => [['item_type' => 'course', 'item_id' => 0]]], 2);
        $r = $this->call('list_offers');
        $this->assertSame('Summer', $r['data']['offers'][0]['name']);
        $this->assertSame(20.0, $r['data']['offers'][0]['percent_off']);
        $this->assertStringNotContainsString('SECRET50', json_encode($r));
    }

    public function test_a_typed_coupon_is_checked_and_a_bad_one_keeps_the_normal_price(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->price((int) $course->id, 200);
        \local_nit_commerce\coupon_manager::create_coupon(['code' => 'HALF', 'discount_type' => 'percent',
            'discount_value' => 50, 'active' => 1, 'items' => [['item_type' => 'course', 'item_id' => 0]]], 2);

        $r = $this->call('preview_price', ['item_type' => 'course', 'item_id' => (int) $course->id, 'coupon' => 'HALF']);
        $this->assertTrue($r['data']['coupon_valid']);
        $this->assertSame(100.0, $r['data']['final_price']);

        $r = $this->call('preview_price', ['item_type' => 'course', 'item_id' => (int) $course->id, 'coupon' => 'NOPE']);
        $this->assertFalse($r['data']['coupon_valid']);
        $this->assertSame(200.0, $r['data']['final_price']);
        $this->assertNotEmpty($r['data']['coupon_problem']);
    }

    public function test_coupon_guessing_is_capped_per_conversation(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->price((int) $course->id, 200);
        for ($i = 0; $i < tools\sales\preview_price::COUPON_CHECKS; $i++) {
            $r = $this->call('preview_price', ['item_type' => 'course', 'item_id' => (int) $course->id, 'coupon' => "G{$i}"]);
            $this->assertTrue($r['ok']);
        }
        $r = $this->call('preview_price', ['item_type' => 'course', 'item_id' => (int) $course->id, 'coupon' => 'G9']);
        $this->assertSame('rate_limited', $r['code']);
        // Without a coupon the price still answers.
        $this->assertTrue($this->call('preview_price', ['item_type' => 'course', 'item_id' => (int) $course->id])['ok']);
    }

    public function test_teachers_are_found_by_subject_without_their_phone(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $t = $gen->create_user(['firstname' => 'Mona', 'lastname' => 'Ali', 'phone1' => '01000000001']);
        $gen->enrol_user($t->id, $gen->create_course()->id, 'editingteacher');
        $DB->insert_record('nit_teacher_profile', (object) ['userid' => $t->id, 'available' => 1, 'headline' => 'Maths teacher',
            'lesson_flex' => 2, 'timemodified' => time()]);
        $DB->insert_record('nit_teacher_subject', (object) ['teacherid' => $t->id, 'subject' => 'Mathematics']);

        $r = $this->call('find_teachers', ['subject' => 'math']);
        $this->assertSame(1, $r['data']['total']);
        $this->assertSame('Mona Ali', $r['data']['teachers'][0]['name']);
        $this->assertSame(2, $r['data']['teachers'][0]['credits_per_lesson']);
        $this->assertStringNotContainsString('01000000001', json_encode($r));
        $this->assertSame(0, $this->call('find_teachers', ['subject' => 'chemistry'])['data']['total']);
    }

    public function test_help_search_finds_the_academy_notes_across_arabic_spellings(): void {
        set_config('notes', "Refunds: within 14 days of payment.\n\nالدفع بفودافون كاش أو بالبطاقة.\n\n"
            . "يمكن إسترداد المبلغ خلال أسبوعين.", 'local_nit_assistant');
        // The site's own pages (privacy, terms) may rank too; the model sees every result returned.
        $r = $this->call('search_help', ['query' => 'refund policy']);
        $this->assertStringContainsString('14 days', implode(' ', array_column($r['data']['results'], 'text')));
        $r = $this->call('search_help', ['query' => 'refunds']);
        $this->assertStringContainsString('14 days', $r['data']['results'][0]['text']);
        $r = $this->call('search_help', ['query' => 'طرق الدفع']);
        $this->assertStringContainsString('فودافون', $r['data']['results'][0]['text']);
        // Arabic spelling: "الاسترداد" finds "إسترداد" (article dropped, hamza unified).
        $r = $this->call('search_help', ['query' => 'الاسترداد']);
        $this->assertStringContainsString('أسبوعين', $r['data']['results'][0]['text']);
        $this->assertSame([], $this->call('search_help', ['query' => 'zzzz'])['data']['results']);
    }

    public function test_a_lead_needs_consent_and_valid_contact_and_is_saved_once_per_chat(): void {
        global $DB;
        $sink = $this->redirectMessages();
        $base = ['name' => 'Omar', 'phone' => '+20 100 123 4567', 'interest' => 'Grade 3 maths'];
        $this->assertSame('validation_failed', $this->call('capture_lead', $base + ['consent' => false])['code']);
        $badphone = ['phone' => '12'] + $base + ['consent' => true];
        $this->assertSame('validation_failed', $this->call('capture_lead', $badphone)['code']);
        $this->assertSame(0, $DB->count_records('local_nit_asst_lead'));

        $r = $this->call('capture_lead', $base + ['consent' => true], 0, $ctx);
        $this->assertTrue($r['ok']);
        foreach ($ctx->afterturn as $task) {
            $task();
        }
        $lead = $DB->get_record('local_nit_asst_lead', ['convid' => $this->convid]);
        $this->assertSame('+201001234567', $lead->phone);
        $this->assertSame('new', $lead->status);
        $this->assertGreaterThan(0, (int) $lead->consenttime);

        $this->call('capture_lead', ['email' => 'Omar@Example.com', 'name' => 'Omar', 'interest' => 'Plans', 'consent' => true]);
        $this->assertSame(1, $DB->count_records('local_nit_asst_lead'));
        $this->assertSame('omar@example.com', $DB->get_field('local_nit_asst_lead', 'email', ['convid' => $this->convid]));
        $sink->close();
    }

    public function test_handoff_moves_the_chat_to_the_team_once_and_alerts_staff(): void {
        global $DB;
        $sink = $this->redirectMessages();
        set_config('workinghours', 'Sun–Thu 10–18', 'local_nit_assistant');
        $r = $this->call('handoff_to_human', ['note' => 'Wants a refund for March'], 0, $ctx);
        $this->assertTrue($r['ok']);
        $this->assertSame(state::WAITING, $DB->get_field('local_nit_asst_conv', 'status', ['id' => $this->convid]));
        $this->assertStringContainsString('Sun–Thu 10–18', $ctx->handoff['message']);
        foreach ($ctx->afterturn as $task) {
            $task();
        }
        $this->assertGreaterThan(0, $sink->count(), 'the site admin is alerted when nobody has the inbox capability');

        $again = $this->call('handoff_to_human', ['note' => 'again please']);
        $this->assertFalse($again['ok']);
        // The tool itself refuses too, not only the registry's check.
        $ctx = new context($this->convid, 'turn-2', 'sales', 'web', 'en', 0, time());
        $direct = (new tools\common\handoff_to_human())->run($ctx, ['note' => 'once more']);
        $this->assertSame('conflict', $direct['code']);
        $this->assertNull($ctx->handoff);
        $sink->close();
    }
}

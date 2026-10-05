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

namespace local_nit_finance;

use local_nit_finance\exception\finance_exception;
use local_nit_finance\local\access;
use local_nit_finance\local\catalog;
use local_nit_finance\local\codes;
use local_nit_finance\local\purchases;
use local_nit_finance\local\teacher_share;
use local_nit_finance\local\wallets;

/**
 * Wallets, the per-teacher earning split, buying one lesson (wallet or code)
 * and the lesson lock.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_finance\local\purchases
 * @covers     \local_nit_finance\local\codes
 * @covers     \local_nit_finance\local\wallets
 * @covers     \local_nit_finance\local\teacher_share
 * @covers     \local_nit_finance\local\access
 */
final class lesson_purchase_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;
    /** @var \stdClass */
    private $teacher;
    /** @var \stdClass the lesson sold on its own (50 EGP) */
    private $sold;
    /** @var \stdClass a lesson without a price */
    private $free;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('teacher_percent', 40, 'local_nit_finance');
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->sold = $gen->create_module('page', ['course' => $this->course->id]);
        $this->free = $gen->create_module('page', ['course' => $this->course->id]);
        catalog::set_price((int) $this->sold->cmid, (int) $this->course->id, 5000);
    }

    /**
     * Give the teacher their own percent on the profile.
     *
     * @param string $value
     */
    private function set_teacher_percent(string $value): void {
        global $DB;
        teacher_share::ensure_profile_field();
        $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => teacher_share::FIELD]);
        $DB->insert_record('user_info_data', (object) ['userid' => $this->teacher->id, 'fieldid' => $fieldid,
            'data' => $value, 'dataformat' => 0]);
    }

    public function test_split_uses_default_then_teacher_profile_then_falls_back(): void {
        $this->assertSame(40, teacher_share::percent_for((int) $this->teacher->id));
        $this->assertSame([40, 61], teacher_share::split(101, 40));

        $this->set_teacher_percent('70');
        $this->assertSame(70, teacher_share::percent_for((int) $this->teacher->id));

        global $DB;
        $DB->set_field('user_info_data', 'data', '150', ['userid' => $this->teacher->id]);
        $this->assertSame(40, teacher_share::percent_for((int) $this->teacher->id), 'out of range falls back');
        $this->assertSame(0, teacher_share::percent_for(0), 'no teacher: all to the platform');
    }

    public function test_wallet_purchase_splits_and_credits_all_three_wallets(): void {
        global $DB;
        $student = $this->getDataGenerator()->create_user();
        wallets::move(wallets::STUDENT, (int) $student->id, 8000, wallets::KIND_TOPUP);

        $purchase = purchases::buy_with_wallet((int) $student->id, (int) $this->sold->cmid);

        $this->assertSame(3000, wallets::balance(wallets::STUDENT, (int) $student->id));
        $this->assertSame(2000, wallets::balance(wallets::TEACHER, (int) $this->teacher->id));
        $this->assertSame(3000, wallets::balance(wallets::PLATFORM));
        $this->assertSame(1, (int) $purchase->enrolled);
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $student->id, '', true));
        $this->assertTrue($DB->record_exists('nit_earning',
            ['purchaseid' => $purchase->id, 'teacherid' => $this->teacher->id, 'teacher_amount_minor' => 2000]));
    }

    public function test_per_teacher_percent_applies_to_sales(): void {
        $this->set_teacher_percent('70');
        $student = $this->getDataGenerator()->create_user();
        wallets::move(wallets::STUDENT, (int) $student->id, 5000, wallets::KIND_TOPUP);

        $purchase = purchases::buy_with_wallet((int) $student->id, (int) $this->sold->cmid);

        $this->assertSame(3500, (int) $purchase->teacher_amount_minor);
        $this->assertSame(1500, (int) $purchase->platform_amount_minor);
    }

    public function test_wallet_purchase_without_enough_balance_changes_nothing(): void {
        global $DB;
        $student = $this->getDataGenerator()->create_user();
        wallets::move(wallets::STUDENT, (int) $student->id, 4999, wallets::KIND_TOPUP);

        try {
            purchases::buy_with_wallet((int) $student->id, (int) $this->sold->cmid);
            $this->fail('Bought with too little credit');
        } catch (finance_exception $e) {
            $this->assertSame('err_insufficientwallet', $e->errorcode);
        }
        $this->assertSame(4999, wallets::balance(wallets::STUDENT, (int) $student->id));
        $this->assertSame(0, wallets::balance(wallets::TEACHER, (int) $this->teacher->id));
        $this->assertFalse($DB->record_exists('nit_purchase', ['userid' => $student->id]));
        $this->assertFalse(is_enrolled(\context_course::instance($this->course->id), $student->id));
    }

    public function test_cannot_buy_twice_or_buy_an_unpriced_lesson(): void {
        $student = $this->getDataGenerator()->create_user();
        wallets::move(wallets::STUDENT, (int) $student->id, 20000, wallets::KIND_TOPUP);
        purchases::buy_with_wallet((int) $student->id, (int) $this->sold->cmid);

        try {
            purchases::buy_with_wallet((int) $student->id, (int) $this->sold->cmid);
            $this->fail('Bought twice');
        } catch (finance_exception $e) {
            $this->assertSame('err_alreadyowned', $e->errorcode);
        }
        $this->expectExceptionMessage(get_string('err_notforsale', 'local_nit_finance'));
        purchases::buy_with_wallet((int) $student->id, (int) $this->free->cmid);
    }

    public function test_wallet_code_tops_up_once(): void {
        $admin = get_admin();
        $student = $this->getDataGenerator()->create_user();
        $code = codes::generate(catalog::WALLET, 0, 1, 10000, 0, '', (int) $admin->id)[0];

        // Typed in lower case with spaces.
        codes::redeem((int) $student->id, strtolower(str_replace('-', ' ', $code->code)));
        $this->assertSame(10000, wallets::balance(wallets::STUDENT, (int) $student->id));
        $this->assertSame(0, wallets::balance(wallets::PLATFORM), 'top-up is not a sale');

        $other = $this->getDataGenerator()->create_user();
        $this->expectExceptionMessage(get_string('err_codeused', 'local_nit_finance'));
        codes::redeem((int) $other->id, $code->code);
    }

    public function test_lesson_code_unlocks_and_splits_the_offline_amount(): void {
        $admin = get_admin();
        $student = $this->getDataGenerator()->create_user();
        $code = codes::generate(catalog::CM, (int) $this->sold->cmid, 1, 4000, 0, '', (int) $admin->id)[0];

        $result = codes::redeem((int) $student->id, $code->code);

        $this->assertSame(1600, (int) $result['purchase']->teacher_amount_minor);
        $this->assertSame(1600, wallets::balance(wallets::TEACHER, (int) $this->teacher->id));
        $this->assertSame(2400, wallets::balance(wallets::PLATFORM));
        $this->assertSame(0, wallets::balance(wallets::STUDENT, (int) $student->id));
        $this->assertTrue(access::can_open((int) $student->id, (object) ['id' => $this->sold->cmid, 'course' => $this->course->id]));
    }

    public function test_bad_codes_are_refused_and_owned_items_keep_the_code(): void {
        global $DB;
        $admin = get_admin();
        $student = $this->getDataGenerator()->create_user();
        $expired = codes::generate(catalog::WALLET, 0, 1, 100, time() - 1, '', (int) $admin->id)[0];
        foreach (['', 'nonsense', 'AAAA-BBBB-CCCC', $expired->code] as $input) {
            try {
                codes::redeem((int) $student->id, $input);
                $this->fail("Code '$input' was accepted");
            } catch (finance_exception $e) {
                $this->assertContains($e->errorcode, ['err_codeinvalid', 'err_codeexpired']);
            }
        }

        $first = codes::generate(catalog::CM, (int) $this->sold->cmid, 2, 5000, 0, '', (int) $admin->id);
        codes::redeem((int) $student->id, $first[0]->code);
        try {
            codes::redeem((int) $student->id, $first[1]->code);
            $this->fail('Second code for an owned lesson was used');
        } catch (finance_exception $e) {
            $this->assertSame('err_alreadyowned', $e->errorcode);
        }
        $this->assertSame(codes::STATUS_ACTIVE, $DB->get_field('nit_access_code', 'status', ['id' => $first[1]->id]));
    }

    public function test_course_code_opens_every_lesson(): void {
        $admin = get_admin();
        $student = $this->getDataGenerator()->create_user();
        $code = codes::generate(catalog::COURSE, (int) $this->course->id, 1, 30000, 0, '', (int) $admin->id)[0];

        codes::redeem((int) $student->id, $code->code);

        $state = access::course_state((int) $student->id, (int) $this->course->id);
        $this->assertTrue(access::cm_open($state, (int) $this->sold->cmid));
        $this->assertTrue(access::cm_open($state, (int) $this->free->cmid));
    }

    public function test_access_rules(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $sold = (int) $this->sold->cmid;
        $free = (int) $this->free->cmid;

        // Enrolled for free in a course that is not sold: free lessons open, sold ones do not.
        $enrolled = $gen->create_and_enrol($this->course, 'student');
        $state = access::course_state((int) $enrolled->id, (int) $this->course->id);
        $this->assertFalse(access::cm_open($state, $sold));
        $this->assertTrue(access::cm_open($state, $free));

        // Staff open everything.
        $state = access::course_state((int) $this->teacher->id, (int) $this->course->id);
        $this->assertTrue(access::cm_open($state, $sold));

        // The course itself is sold online: a student who only bought one lesson
        // does not get the unpriced lessons for free.
        $DB->insert_record('local_payments_course_prices', (object) ['courseid' => $this->course->id, 'country' => 'EG',
            'currency' => 'EGP', 'price' => 300, 'is_active' => 1, 'created_by' => get_admin()->id,
            'timecreated' => time(), 'timemodified' => time()]);
        $buyer = $gen->create_user();
        wallets::move(wallets::STUDENT, (int) $buyer->id, 5000, wallets::KIND_TOPUP);
        purchases::buy_with_wallet((int) $buyer->id, $sold);
        $state = access::course_state((int) $buyer->id, (int) $this->course->id);
        $this->assertTrue(access::cm_open($state, $sold));
        $this->assertFalse(access::cm_open($state, $free));
    }

    public function test_require_login_blocks_unbought_lesson_for_web_services(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);
        [$course, $cm] = get_course_and_cm_from_cmid((int) $this->free->cmid);
        require_login($course, false, $cm, false, true); // Free lesson: no exception.

        [$course, $cm] = get_course_and_cm_from_cmid((int) $this->sold->cmid);
        $this->expectExceptionMessage(get_string('err_lessonlocked', 'local_nit_finance'));
        require_login($course, false, $cm, false, true);
    }

    public function test_online_topup_is_credited_once_per_payment(): void {
        $student = $this->getDataGenerator()->create_user();

        // The gateway webhook and the student's redirect both report payment 77.
        $this->assertTrue(wallets::topup_from_payment((int) $student->id, 12000, 77, 'PAY-1'));
        $this->assertFalse(wallets::topup_from_payment((int) $student->id, 12000, 77, 'PAY-1'));
        $this->assertTrue(wallets::topup_from_payment((int) $student->id, 5000, 78, 'PAY-2'));

        $this->assertSame(17000, wallets::balance(wallets::STUDENT, (int) $student->id));
        $this->assertSame(0, wallets::balance(wallets::PLATFORM), 'a top-up is not a sale');
    }

    public function test_paid_withdrawal_leaves_the_teacher_wallet(): void {
        $student = $this->getDataGenerator()->create_user();
        wallets::move(wallets::STUDENT, (int) $student->id, 5000, wallets::KIND_TOPUP);
        purchases::buy_with_wallet((int) $student->id, (int) $this->sold->cmid);

        $wd = \local_nit_finance\api\wallet::request_withdrawal((int) $this->teacher->id, 1500, 'bank', 'IBAN');
        $admin = (int) get_admin()->id;
        \local_nit_finance\api\wallet::process_withdrawal($admin, (int) $wd['id'], 'approve');
        $this->assertSame(2000, wallets::balance(wallets::TEACHER, (int) $this->teacher->id), 'approval moves no money');
        \local_nit_finance\api\wallet::process_withdrawal($admin, (int) $wd['id'], 'pay', ['reference' => 'T-1']);

        $this->assertSame(500, wallets::balance(wallets::TEACHER, (int) $this->teacher->id));
    }
}

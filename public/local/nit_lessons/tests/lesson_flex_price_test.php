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

use local_nit_finance\local\wallets;
use local_nit_flex\api\packages;
use local_nit_flex\api\purchase;
use local_nit_lessons\api\lessons;
use local_nit_lessons\exception\lesson_exception;
use local_nit_lessons\local\mobile_api;
use local_nit_lessons\local\profile_section;
use local_nit_lessons\room\room_factory;
use local_nit_lessons\room\stub_room;
use local_nit_lessons\service\teacher_service;

/**
 * The teacher's Flex price per lesson: set on the profile, fixed on the lesson when it is
 * requested, and used for every Flex movement and the earning.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_lessons\service\teacher_service
 * @covers     \local_nit_lessons\service\lesson_service
 * @covers     \local_nit_lessons\local\profile_section
 * @covers     \local_nit_lessons\local\mobile_api
 */
final class lesson_flex_price_test extends \advanced_testcase {
    /** @var int */
    private $sid;

    /** @var int */
    private $tid;

    /**
     * A student with a 10-Flex package (100 EGP per Flex) and a Physics teacher who asks 3 Flex a lesson.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_debugging(DEBUG_NORMAL); // TMPVERIFY
        $gen = $this->getDataGenerator();
        $this->sid = (int) $gen->create_user()->id;
        $this->tid = (int) $gen->create_user()->id;
        $gen->enrol_user($this->tid, $gen->create_course()->id, 'editingteacher');
        set_config('subjects', 'Physics', 'local_nit_lessons');
        (new teacher_service())->save($this->tid, true, '', ['Physics'], [], 3);
        foreach (['min_booking_minutes' => 0, 'start_allowed_minutes' => 100000,
                'complete_allowed_minutes' => 0, 'cancel_deadline_minutes' => 60,
                'absence_report_minutes' => 0] as $k => $v) {
            set_config($k, $v, 'local_nit_lessons');
        }
        set_config('teacher_percent', 40, 'local_nit_finance');
        room_factory::set_override(new stub_room());
        purchase::fulfil($this->sid, packages::create((object) [
            'name' => 'Flex10', 'flex_count' => 10, 'price_minor' => 100000, 'expiration_days' => 0,
        ]));
    }

    protected function tearDown(): void {
        room_factory::set_override(null);
        parent::tearDown();
    }

    /**
     * Noon in the teacher's timezone, $days from today.
     *
     * @param int $days
     * @return int
     */
    private function noon(int $days = 1): int {
        return (new \DateTimeImmutable('today', teacher_service::timezone($this->tid)))
            ->modify("+{$days} day")->setTime(12, 0)->getTimestamp();
    }

    /**
     * Request and confirm a lesson.
     *
     * @param int $days
     * @return array
     */
    private function confirmed(int $days = 1): array {
        $lesson = lessons::request($this->sid, $this->tid, 'Physics', $this->noon($days), 'note');
        return lessons::teacher_respond($this->tid, $lesson['id'], 'accept');
    }

    /**
     * The price is saved, kept when not sent, must be 1 or more, and has no maximum.
     *
     * @return void
     */
    public function test_teacher_sets_price(): void {
        $service = new teacher_service();
        $this->assertSame(3, $service->profile($this->tid)['lesson_flex']);
        $this->assertSame(3, $service->lesson_flex($this->tid));

        // Not sent (null): the saved price stays.
        $service->save($this->tid, true, 'Updated', ['Physics'], []);
        $this->assertSame(3, $service->lesson_flex($this->tid));

        foreach ([0, -1] as $bad) {
            try {
                $service->save($this->tid, true, '', ['Physics'], [], $bad);
                $this->fail("price $bad should be refused");
            } catch (lesson_exception $e) {
                $this->assertSame('err_badlessonflex', $e->errorcode);
            }
        }
        // No maximum: a big price is kept as it is (beyond the old 4-digit column, too).
        $service->save($this->tid, true, '', ['Physics'], [], 50000);
        $this->assertSame(50000, $service->lesson_flex($this->tid));

        // A teacher who never saved a profile costs 1 Flex.
        $this->assertSame(1, $service->lesson_flex((int) $this->getDataGenerator()->create_user()->id));
    }

    /**
     * Completing a 3-Flex lesson reserves then uses 3 Flex and pays 40% of 3 Flex to the teacher.
     *
     * @return void
     */
    public function test_complete_uses_the_lesson_price(): void {
        $lesson = $this->confirmed();
        $this->assertSame(3, $lesson['flex_count']);
        $active = purchase::active($this->sid);
        $this->assertSame(7, $active['remaining_flex']);
        $this->assertSame(3, $active['reserved_flex']);

        lessons::start($this->tid, $lesson['id']);
        lessons::complete($this->tid, $lesson['id']);
        $active = purchase::active($this->sid);
        $this->assertSame(7, $active['remaining_flex']);
        $this->assertSame(0, $active['reserved_flex']);
        $this->assertSame(3, $active['consumed_flex']);
        $this->assertSame(12000, wallets::balance(wallets::TEACHER, $this->tid)); // 40% of 3 × 10000.
        $this->assertSame(18000, wallets::balance(wallets::PLATFORM));
    }

    /**
     * A price change after the request does not change the lesson's price.
     *
     * @return void
     */
    public function test_lesson_keeps_the_price_it_was_requested_at(): void {
        $lesson = lessons::request($this->sid, $this->tid, 'Physics', $this->noon(), 'note');
        (new teacher_service())->save($this->tid, true, '', ['Physics'], [], 5);
        $lesson = lessons::teacher_respond($this->tid, $lesson['id'], 'accept');
        $this->assertSame(3, $lesson['flex_count']);
        $this->assertSame(7, purchase::active($this->sid)['remaining_flex']);

        // The next request takes the new price.
        $next = lessons::request($this->sid, $this->tid, 'Physics', $this->noon(2), 'note');
        $this->assertSame(5, $next['flex_count']);
    }

    /**
     * Returns give back the whole price: teacher cancel, early student cancel, admin reversal.
     *
     * @return void
     */
    public function test_returns_give_back_the_whole_price(): void {
        $lesson = $this->confirmed(1);
        lessons::cancel_as_teacher($this->tid, $lesson['id'], 'busy');
        $this->assertSame(10, purchase::active($this->sid)['remaining_flex']);

        $lesson = $this->confirmed(2);
        $lesson = lessons::cancel_as_student($this->sid, $lesson['id']);
        $this->assertSame('returned', $lesson['flex_state']);
        $this->assertSame(10, purchase::active($this->sid)['remaining_flex']);

        $lesson = $this->confirmed(3);
        lessons::start($this->tid, $lesson['id']);
        lessons::complete($this->tid, $lesson['id']);
        lessons::reverse_flex($lesson['id'], 2, 'refund');
        $active = purchase::active($this->sid);
        $this->assertSame(10, $active['remaining_flex']);
        $this->assertSame(0, $active['consumed_flex']);
        $this->assertSame(0, wallets::balance(wallets::TEACHER, $this->tid));
    }

    /**
     * A late cancel uses the whole price and the platform keeps all of it.
     *
     * @return void
     */
    public function test_late_cancel_uses_the_whole_price(): void {
        set_config('cancel_deadline_minutes', 100000, 'local_nit_lessons');
        $lesson = $this->confirmed();
        lessons::cancel_as_student($this->sid, $lesson['id']);
        $this->assertSame(7, purchase::active($this->sid)['remaining_flex']);
        $this->assertSame(30000, wallets::balance(wallets::PLATFORM));
    }

    /**
     * A student with fewer Flex than the price cannot request; with none at all, the old error.
     *
     * @return void
     */
    public function test_request_needs_the_whole_price(): void {
        $student = function(int $flex): int {
            $id = (int) $this->getDataGenerator()->create_user()->id;
            purchase::fulfil($id, packages::create((object) [
                'name' => 'Flex' . $flex, 'flex_count' => $flex, 'price_minor' => $flex * 10000, 'expiration_days' => 0,
            ]));
            return $id;
        };
        // Exactly the price (3): allowed.
        lessons::request($student(3), $this->tid, 'Physics', $this->noon(1), 'note');
        try {
            lessons::request($student(2), $this->tid, 'Physics', $this->noon(2), 'note');
            $this->fail('notenoughflex expected');
        } catch (lesson_exception $e) {
            $this->assertSame('err_notenoughflex', $e->errorcode);
            $this->assertStringContainsString('3', $e->getMessage());
        }
        $none = (int) $this->getDataGenerator()->create_user()->id;
        try {
            lessons::request($none, $this->tid, 'Physics', $this->noon(2), 'note');
            $this->fail('noflex expected');
        } catch (lesson_exception $e) {
            $this->assertSame('err_noflex', $e->errorcode);
        }
    }

    /**
     * The profile form (/local/academy/profile.php) saves the price; a bad value is refused.
     *
     * @return void
     */
    public function test_profile_form_saves_the_price(): void {
        $post = ['nitlx_profile' => 1, 'nitlx_available' => 1, 'nitlx_headline' => 'H',
            'nitlx_subjects' => ['Physics'], 'nitlx_lessonflex' => '4'];
        $_POST = $post;
        $this->assertNull(profile_section::save($this->tid));
        $this->assertSame(4, (new teacher_service())->lesson_flex($this->tid));

        foreach (['abc', '0', '-2', '1.5'] as $bad) {
            $_POST = ['nitlx_lessonflex' => $bad] + $post;
            $this->assertSame(get_string('err_badlessonflex', 'local_nit_lessons'), profile_section::save($this->tid), $bad);
        }
        $this->assertSame(4, (new teacher_service())->lesson_flex($this->tid));

        // No maximum.
        $_POST = ['nitlx_lessonflex' => '99'] + $post;
        $this->assertNull(profile_section::save($this->tid));
        $this->assertSame(99, (new teacher_service())->lesson_flex($this->tid));

        $this->setUser($this->tid);
        $html = profile_section::render($this->tid);
        $this->assertMatchesRegularExpression('/name="nitlx_lessonflex"\s+value="99" min="1" step="1"/', $html);
        $this->assertStringNotContainsString('max=', $html);
        $_POST = [];
    }

    /**
     * The mobile API shows the price and lets the teacher change it (left out = kept).
     *
     * @return void
     */
    public function test_mobile_api_price(): void {
        $teachers = mobile_api::get_teachers($this->sid, '')['teachers'];
        $this->assertSame(3, $teachers[0]['lesson_flex']);

        $profile = mobile_api::update_teacher_profile($this->tid, true, '', ['Physics'], [], 2);
        $this->assertSame(2, $profile['lesson_flex']);
        $this->assertArrayNotHasKey('max_lesson_flex', $profile);
        $profile = mobile_api::update_teacher_profile($this->tid, true, '', ['Physics'], []);
        $this->assertSame(2, $profile['lesson_flex']);

        $lesson = mobile_api::request_lesson($this->sid, $this->tid, 'Physics', $this->noon(), 'note');
        $this->assertSame(2, $lesson['flex_count']);
    }
}

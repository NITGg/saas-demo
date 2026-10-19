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
use local_nit_lessons\api\instant;
use local_nit_lessons\exception\lesson_exception;
use local_nit_lessons\room\room_factory;
use local_nit_lessons\room\stub_room;
use local_nit_lessons\service\instant_service;
use local_nit_lessons\service\teacher_service;
use local_nit_lessons\task\expire_instant_requests;

/**
 * Instant lessons ("Get a tutor now"): presence, prompting, first-accept-wins, decline / cancel /
 * expiry, and the started lesson.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_lessons\service\instant_service
 * @covers     \local_nit_lessons\service\lesson_service::start_instant
 * @covers     \local_nit_lessons\api\instant
 * @covers     \local_nit_lessons\task\expire_instant_requests
 */
final class instant_test extends \advanced_testcase {
    /** @var \stdClass */
    private $student;

    /** @var \stdClass */
    private $t1;

    /** @var \stdClass */
    private $t2;

    /** @var instant_service */
    private $service;

    /**
     * A student with 10 Flex and two bookable Physics teachers (1 Flex per lesson).
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->student = $gen->create_user();
        $this->t1 = $gen->create_user();
        $this->t2 = $gen->create_user();
        $course = $gen->create_course();
        set_config('subjects', "Physics\nChemistry", 'local_nit_lessons');
        set_config('requireapproval', 0, 'local_nit_tutors');
        foreach ([$this->t1, $this->t2] as $t) {
            $gen->enrol_user($t->id, $course->id, 'editingteacher');
            (new teacher_service())->save((int) $t->id, true, '', ['Physics'], []);
        }
        set_config('start_allowed_minutes', 100000, 'local_nit_lessons');
        room_factory::set_override(new stub_room());
        $packageid = packages::create((object) [
            'name' => 'Flex10', 'flex_count' => 10, 'price_minor' => 100000, 'expiration_days' => 0,
        ]);
        purchase::fulfil((int) $this->student->id, $packageid);
        $this->service = new instant_service();
    }

    protected function tearDown(): void {
        room_factory::set_override(null);
        parent::tearDown();
    }

    /**
     * Expect a lesson_exception with this code.
     *
     * @param string $code
     * @param callable $fn
     * @return void
     */
    private function assert_fails(string $code, callable $fn): void {
        try {
            $fn();
            $this->fail("$code expected");
        } catch (lesson_exception $e) {
            $this->assertSame($code, $e->errorcode);
        }
    }

    /**
     * Only online, fresh, free teachers of the subject count.
     *
     * @return void
     */
    public function test_available_teachers(): void {
        global $DB;
        $sid = (int) $this->student->id;
        $this->assertSame([], $this->service->available_teachers('Physics', $sid), 'nobody online yet');

        $this->service->set_online((int) $this->t1->id, true);
        $this->assertSame([(int) $this->t1->id], $this->service->available_teachers('Physics', $sid));
        $this->assertSame([], $this->service->available_teachers('Chemistry', $sid), 'other subject');

        $DB->set_field('nit_teacher_presence', 'lastseen', time() - 120, ['teacherid' => $this->t1->id]);
        $this->assertSame([], $this->service->available_teachers('Physics', $sid), 'stale heartbeat');

        $this->service->heartbeat((int) $this->t1->id);
        $this->assertCount(1, $this->service->available_teachers('Physics', $sid), 'heartbeat brings them back');

        $this->service->set_online((int) $this->t1->id, false);
        $this->assertSame([], $this->service->available_teachers('Physics', $sid), 'switched off');
    }

    /**
     * A teacher students cannot book cannot go online.
     *
     * @return void
     */
    public function test_unbookable_teacher_cannot_go_online(): void {
        (new teacher_service())->save((int) $this->t1->id, false, '', ['Physics'], []);
        $this->assert_fails('err_instantnotbookable', fn() => $this->service->set_online((int) $this->t1->id, true));
    }

    /**
     * A request prompts every free online teacher (in the page and as a notification).
     *
     * @return void
     */
    public function test_request_prompts_online_teachers(): void {
        $this->service->set_online((int) $this->t1->id, true);
        $this->service->set_online((int) $this->t2->id, true);
        $sink = $this->redirectMessages();

        $r = $this->service->request((int) $this->student->id, 'Physics', 'question 4');

        $this->assertSame('searching', $r['status']);
        $this->assertSame(2, $r['teachers']);
        $offers = $this->service->heartbeat((int) $this->t1->id)['offers'];
        $this->assertCount(1, $offers);
        $this->assertSame('question 4', $offers[0]['note']);
        $this->assertSame(1, $offers[0]['flex']);
        $this->assertCount(2, $sink->get_messages(), 'each teacher gets a push');
    }

    /**
     * Requests are refused without tutors, without Flex, or when one is already open.
     *
     * @return void
     */
    public function test_request_rules(): void {
        $sid = (int) $this->student->id;
        $this->assert_fails('err_noinstanttutor', fn() => $this->service->request($sid, 'Physics'));
        $this->service->set_online((int) $this->t1->id, true);
        $this->service->request($sid, 'Physics');
        $this->assert_fails('err_instantopen', fn() => $this->service->request($sid, 'Physics'));

        $poor = $this->getDataGenerator()->create_user();
        $this->assert_fails('err_noflex', fn() => $this->service->request((int) $poor->id, 'Physics'));

        set_config('instant_enabled', 0, 'local_nit_lessons');
        $this->assert_fails('err_instantoff', fn() => $this->service->request((int) $poor->id, 'Physics'));
    }

    /**
     * First accept wins: an instant lesson starts with Flex reserved; the second teacher is told it
     * was taken and their prompt disappears.
     *
     * @return void
     */
    public function test_first_accept_wins(): void {
        global $DB;
        $this->service->set_online((int) $this->t1->id, true);
        $this->service->set_online((int) $this->t2->id, true);
        $r = $this->service->request((int) $this->student->id, 'Physics', 'help');

        $result = instant::act((int) $this->t2->id, 'accept', ['requestid' => $r['id']]);

        $lesson = $DB->get_record('nit_lesson', ['id' => $result['lesson']['id']]);
        $this->assertSame('instant', $lesson->type);
        $this->assertSame('in_progress', $lesson->status);
        $this->assertSame((int) $this->t2->id, (int) $lesson->teacherid);
        $this->assertSame(30, (int) $lesson->duration);
        $this->assertSame(1, purchase::active((int) $this->student->id)['reserved_flex']);
        $this->assertStringContainsString('join.php?id=' . $lesson->id, $result['joinurl']);

        $status = $this->service->status((int) $this->student->id, $r['id']);
        $this->assertSame('matched', $status['status']);
        $this->assertSame((int) $lesson->id, $status['lessonid']);

        $this->assert_fails('err_instanttaken', fn() => $this->service->accept((int) $this->t1->id, $r['id']));
        $this->assertSame('taken', $DB->get_field('nit_instant_offer', 'status',
            ['requestid' => $r['id'], 'teacherid' => $this->t1->id]));
        $this->assertSame([], $this->service->heartbeat((int) $this->t1->id)['offers']);
        $this->assertSame(1, $DB->count_records('nit_lesson'), 'one request, one lesson');
        $this->assertTrue($this->service->presence((int) $this->t2->id)['busy'], 'the teacher is busy now');
    }

    /**
     * A teacher who is not prompted for the request cannot accept it.
     *
     * @return void
     */
    public function test_only_prompted_teachers_can_accept(): void {
        $this->service->set_online((int) $this->t1->id, true);
        $r = $this->service->request((int) $this->student->id, 'Physics');
        $this->assert_fails('err_forbidden', fn() => $this->service->accept((int) $this->t2->id, $r['id']));
    }

    /**
     * Declined by everyone or cancelled: the request ends and nothing is charged.
     *
     * @return void
     */
    public function test_decline_and_cancel_charge_nothing(): void {
        $sid = (int) $this->student->id;
        $this->service->set_online((int) $this->t1->id, true);
        $r = $this->service->request($sid, 'Physics');
        $this->service->decline((int) $this->t1->id, $r['id']);
        $this->assertSame('expired', $this->service->status($sid, $r['id'])['status'], 'nobody left to ask');

        $r2 = $this->service->request($sid, 'Physics');
        $this->assertSame('cancelled', $this->service->cancel($sid, $r2['id'])['status']);
        $this->assert_fails('err_instanttaken', fn() => $this->service->accept((int) $this->t1->id, $r2['id']));
        $this->assertSame(0, purchase::active($sid)['reserved_flex']);
        $this->assertSame(10, purchase::active($sid)['remaining_flex']);
    }

    /**
     * Requests end when their time is up (also from cron); prompts become missed.
     *
     * @return void
     */
    public function test_expiry(): void {
        global $DB;
        $this->service->set_online((int) $this->t1->id, true);
        $r = $this->service->request((int) $this->student->id, 'Physics');
        $DB->set_field('nit_instant_request', 'expiresat', time() - 1, ['id' => $r['id']]);

        ob_start();
        (new expire_instant_requests())->execute();
        $this->assertStringContainsString('1 instant request(s) expired', ob_get_clean());

        $this->assertSame('expired', $DB->get_field('nit_instant_request', 'status', ['id' => $r['id']]));
        $this->assertSame('missed', $DB->get_field('nit_instant_offer', 'status', ['requestid' => $r['id']]));
        $this->assert_fails('err_instanttaken', fn() => $this->service->accept((int) $this->t1->id, $r['id']));
    }

    /**
     * A student only sees their own request.
     *
     * @return void
     */
    public function test_status_is_private(): void {
        $this->service->set_online((int) $this->t1->id, true);
        $r = $this->service->request((int) $this->student->id, 'Physics');
        $other = $this->getDataGenerator()->create_user();
        $this->assert_fails('err_forbidden', fn() => $this->service->status((int) $other->id, $r['id']));
        $this->assert_fails('err_forbidden', fn() => $this->service->cancel((int) $other->id, $r['id']));
    }

    /**
     * The student's picker lists subjects with someone online.
     *
     * @return void
     */
    public function test_online_subjects(): void {
        $this->service->set_online((int) $this->t1->id, true);
        $subjects = $this->service->online_subjects((int) $this->student->id);
        $this->assertSame([['subject' => 'Physics', 'label' => 'Physics', 'teachers' => 1]], $subjects);
    }

    /**
     * The expiry task is registered for cron.
     *
     * @return void
     */
    public function test_task_registered(): void {
        $this->assertInstanceOf(expire_instant_requests::class,
            \core\task\manager::get_scheduled_task(expire_instant_requests::class));
    }
}

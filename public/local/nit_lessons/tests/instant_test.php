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
     * Teachers are asked in waves, best first (the student's previous tutor leads); when a wave rings
     * out or declines, the next teacher is asked.
     *
     * @return void
     */
    public function test_waves_best_first(): void {
        global $DB;
        set_config('instant_wave_size', 1, 'local_nit_lessons');
        $sid = (int) $this->student->id;
        $DB->insert_record('nit_lesson', (object) ['studentid' => $sid, 'teacherid' => $this->t2->id,
            'subject' => 'Physics', 'status' => 'completed', 'duration' => 60, 'timecreated' => time() - DAYSECS]);
        $this->service->set_online((int) $this->t1->id, true);
        $this->service->set_online((int) $this->t2->id, true);

        $r = $this->service->request($sid, 'Physics');
        $this->assertSame(1, $r['teachers'], 'one teacher per wave');
        $this->assertCount(1, $this->service->heartbeat((int) $this->t2->id)['offers'], 'previous tutor first');
        $this->assertSame([], $this->service->heartbeat((int) $this->t1->id)['offers']);

        // The first wave rings out: the next teacher is asked.
        $DB->set_field('nit_instant_offer', 'timecreated', time() - 31, ['requestid' => $r['id']]);
        $this->service->expire_due(time());
        $this->assertSame('missed', $DB->get_field('nit_instant_offer', 'status',
            ['requestid' => $r['id'], 'teacherid' => $this->t2->id]));
        $this->assertCount(1, $this->service->heartbeat((int) $this->t1->id)['offers']);
        $this->assertSame(2, (int) $DB->get_field('nit_instant_request', 'wave', ['id' => $r['id']]));

        // The last teacher declines: nobody is left, the request ends.
        $this->service->decline((int) $this->t1->id, $r['id']);
        $this->assertSame('expired', $this->service->status($sid, $r['id'])['status']);
    }

    /**
     * A teacher who accepts but never opens the room: lesson cancelled, Flex back, request reopened
     * once for the other teachers (the one who was "taken" is asked again), student told.
     *
     * @return void
     */
    public function test_teacher_noshow_reopens_once(): void {
        global $DB;
        $sid = (int) $this->student->id;
        $this->service->set_online((int) $this->t1->id, true);
        $this->service->set_online((int) $this->t2->id, true);
        $r = $this->service->request($sid, 'Physics');
        $lesson = $this->service->accept((int) $this->t1->id, $r['id']);
        $this->assertSame(1, purchase::active($sid)['reserved_flex']);

        $DB->set_field('nit_lesson', 'actual_start', time() - 4 * MINSECS, ['id' => $lesson['id']]);
        $sink = $this->redirectMessages();
        $this->assertSame(1, $this->service->noshow_due(time()));

        $this->assertSame('cancelled_teacher', $DB->get_field('nit_lesson', 'status', ['id' => $lesson['id']]));
        $this->assertSame(0, purchase::active($sid)['reserved_flex'], 'the Flex is back');
        $this->assertSame(10, purchase::active($sid)['remaining_flex']);
        $req = $DB->get_record('nit_instant_request', ['id' => $r['id']]);
        $this->assertSame('searching', $req->status);
        $this->assertSame(1, (int) $req->redispatched);
        $this->assertSame('noshow', $DB->get_field('nit_instant_offer', 'status',
            ['requestid' => $r['id'], 'teacherid' => $this->t1->id]));
        $this->assertCount(1, $this->service->heartbeat((int) $this->t2->id)['offers'], 'the other teacher is asked');
        $this->assertSame([], $this->service->heartbeat((int) $this->t1->id)['offers'], 'not the no-show teacher');
        $tostudent = array_filter($sink->get_messages(), fn($m) => (int) $m->useridto === $sid);
        $this->assertCount(1, $tostudent, 'the student is told');

        // A second no-show is not reopened again.
        $lesson2 = $this->service->accept((int) $this->t2->id, $r['id']);
        $DB->set_field('nit_lesson', 'actual_start', time() - 4 * MINSECS, ['id' => $lesson2['id']]);
        $this->service->noshow_due(time());
        $this->assertSame('matched', $DB->get_field('nit_instant_request', 'status', ['id' => $r['id']]),
            'not searching again');
        $this->assertSame(0, purchase::active($sid)['reserved_flex']);
    }

    /**
     * A teacher who opened the room is never treated as a no-show.
     *
     * @return void
     */
    public function test_teacher_who_joined_is_not_a_noshow(): void {
        global $DB;
        $this->service->set_online((int) $this->t1->id, true);
        $r = $this->service->request((int) $this->student->id, 'Physics');
        $lesson = $this->service->accept((int) $this->t1->id, $r['id']);
        (new \local_nit_lessons\service\lesson_service())->open_room((int) $this->t1->id, $lesson['id']);
        $this->assertGreaterThan(0, (int) $DB->get_field('nit_lesson', 'teacher_joined', ['id' => $lesson['id']]));

        $DB->set_field('nit_lesson', 'actual_start', time() - HOURSECS, ['id' => $lesson['id']]);
        $this->assertSame(0, $this->service->noshow_due(time()));
        $this->assertSame('in_progress', $DB->get_field('nit_lesson', 'status', ['id' => $lesson['id']]));
    }

    /**
     * The admin live view lists online teachers, waiting requests and the day's figures.
     *
     * @return void
     */
    public function test_live_view(): void {
        $this->service->set_online((int) $this->t1->id, true);
        $this->service->set_online((int) $this->t2->id, true);
        $r = $this->service->request((int) $this->student->id, 'Physics');
        $view = $this->service->live_view();
        $this->assertCount(2, $view['teachers']);
        $this->assertCount(1, $view['requests']);
        $this->assertSame(2, $view['requests'][0]['ringing']);

        $this->service->accept((int) $this->t1->id, $r['id']);
        $view = $this->service->live_view();
        $this->assertSame([], $view['requests']);
        $this->assertSame(1, $view['stats']['requests']);
        $this->assertSame(100, $view['stats']['matchrate']);
        $this->assertSame(1, $view['subjects'][0]['matched']);
    }

    /**
     * A 1x1 PNG in a temp file, shaped like an upload.
     *
     * @return array
     */
    private function png_upload(): array {
        $path = make_request_directory() . '/p.png';
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        return ['name' => 'p.png', 'tmp_name' => $path, 'size' => filesize($path)];
    }

    /**
     * The instant price adds instant_extra_flex to the teacher's price, shown in the prompt and charged.
     *
     * @return void
     */
    public function test_extra_flex_price(): void {
        set_config('instant_extra_flex', 2, 'local_nit_lessons');
        $sid = (int) $this->student->id;
        $this->service->set_online((int) $this->t1->id, true);
        $r = $this->service->request($sid, 'Physics');
        $this->assertSame(3, $this->service->heartbeat((int) $this->t1->id)['offers'][0]['flex']);
        $lesson = $this->service->accept((int) $this->t1->id, $r['id']);
        $this->assertSame(3, purchase::active($sid)['reserved_flex']);
        global $DB;
        $this->assertSame(3, (int) $DB->get_field('nit_lesson', 'flex_count', ['id' => $lesson['id']]));
    }

    /**
     * A photo of the problem is stored with the request, shown to the prompted teacher and the
     * student only; anything that is not an image is refused.
     *
     * @return void
     */
    public function test_photo(): void {
        $sid = (int) $this->student->id;
        $this->service->set_online((int) $this->t1->id, true);
        $r = $this->service->request($sid, 'Physics', '', null, $this->png_upload());

        $offer = $this->service->heartbeat((int) $this->t1->id)['offers'][0];
        $this->assertStringContainsString('/local_nit_lessons/instantphoto/' . $r['id'] . '/problem.png', $offer['photourl']);
        $this->assertTrue(instant_service::can_see_photo($sid, $r['id']));
        $this->assertTrue(instant_service::can_see_photo((int) $this->t1->id, $r['id']));
        $this->assertFalse(instant_service::can_see_photo((int) $this->t2->id, $r['id']), 'not prompted');

        $text = make_request_directory() . '/x.png';
        file_put_contents($text, '<?php echo 1;');
        $this->assert_fails('err_instantphoto', fn() => instant_service::check_photo(['tmp_name' => $text, 'size' => 13]));

        // Old photos are deleted by the hourly cleanup.
        global $DB;
        $DB->set_field('nit_instant_request', 'timecreated', time() - 31 * DAYSECS, ['id' => $r['id']]);
        $this->service->cleanup(time());
        $this->assertSame('', $this->service->photo_url($r['id']));
    }

    /**
     * "Notify me": when a tutor of the subject goes online, the student is told once.
     *
     * @return void
     */
    public function test_notify_me_when_online(): void {
        global $DB;
        $sid = (int) $this->student->id;
        $this->service->watch($sid, 'Physics');
        $this->service->watch($sid, 'Physics');
        $this->assertSame(1, $DB->count_records('nit_instant_watch'), 'once per subject');
        $this->assert_fails('err_subjectrequired', fn() => $this->service->watch($sid, 'Astrology'));

        $sink = $this->redirectMessages();
        $this->service->set_online((int) $this->t1->id, true);
        $tostudent = array_filter($sink->get_messages(), fn($m) => (int) $m->useridto === $sid);
        $this->assertCount(1, $tostudent);
        $this->assertSame(0, $DB->count_records('nit_instant_watch'), 'forgotten after the alert');

        $sink->clear();
        $this->service->set_online((int) $this->t2->id, true);
        $this->assertCount(0, $sink->get_messages(), 'only once');

        $this->service->watch($sid, 'Chemistry');
        $DB->set_field('nit_instant_watch', 'timecreated', time() - 2 * DAYSECS, []);
        $this->service->cleanup(time());
        $this->assertSame(0, $DB->count_records('nit_instant_watch'), 'a day later it is forgotten');
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

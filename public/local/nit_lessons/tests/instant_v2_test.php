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
use local_nit_lessons\exception\lesson_exception;
use local_nit_lessons\room\room_factory;
use local_nit_lessons\room\stub_room;
use local_nit_lessons\service\instant_service;
use local_nit_lessons\service\instant_tutors;
use local_nit_lessons\service\teacher_service;

/**
 * Instant lessons v2: tutor list (year, price, rating, online), ask one tutor, max price, the
 * student confirms the tutor who accepted, favourites, on call, usually online, after-call card,
 * and the Complete window of instant lessons.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_lessons\service\instant_service
 * @covers     \local_nit_lessons\service\instant_tutors
 * @covers     \local_nit_lessons\service\lesson_service
 */
final class instant_v2_test extends \advanced_testcase {
    /** @var \stdClass */
    private $student;
    /** @var \stdClass teaches a course in year A, 3 Flex */
    private $t1;
    /** @var \stdClass no courses (all years), 1 Flex */
    private $t2;
    /** @var \stdClass */
    private $yeara;
    /** @var \stdClass */
    private $yearb;
    /** @var instant_service */
    private $service;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->student = $gen->create_user();
        $this->t1 = $gen->create_user(['firstname' => 'Alpha']);
        $this->t2 = $gen->create_user(['firstname' => 'Beta']);
        $this->yeara = $gen->create_category(['name' => 'Year A']);
        $this->yearb = $gen->create_category(['name' => 'Year B']);
        $course = $gen->create_course(['category' => $this->yeara->id]);
        $gen->enrol_user($this->t1->id, $course->id, 'editingteacher');
        // t2 teaches no course: a system-level teach capability makes them a tutor for every year.
        $roleid = $gen->create_role(['shortname' => 'tutor']);
        assign_capability('local/nit_lessons:teach', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $this->t2->id, \context_system::instance()->id);
        set_config('subjects', 'Physics', 'local_nit_lessons');
        set_config('requireapproval', 0, 'local_nit_tutors');
        set_config('start_allowed_minutes', 100000, 'local_nit_lessons');
        (new teacher_service())->save((int) $this->t1->id, true, '', ['Physics'], [], 3);
        (new teacher_service())->save((int) $this->t2->id, true, '', ['Physics'], [], 1);
        room_factory::set_override(new stub_room());
        purchase::fulfil((int) $this->student->id, packages::create((object) [
            'name' => 'Flex10', 'flex_count' => 10, 'price_minor' => 100000, 'expiration_days' => 0,
        ]));
        $this->service = new instant_service();
    }

    protected function tearDown(): void {
        room_factory::set_override(null);
        parent::tearDown();
    }

    private function fails(string $code, callable $fn): void {
        try {
            $fn();
            $this->fail("$code expected");
        } catch (lesson_exception $e) {
            $this->assertSame($code, $e->errorcode);
        }
    }

    /**
     * Online first; max price filters; a year shows only tutors teaching it (no courses = every year).
     *
     * @return void
     */
    public function test_tutor_list(): void {
        $sid = (int) $this->student->id;
        $this->service->set_online((int) $this->t1->id, true);
        $list = (new instant_tutors())->list($sid, 'Physics');
        $this->assertSame([(int) $this->t1->id, (int) $this->t2->id], array_column($list, 'id'), 'online first');
        $this->assertTrue($list[0]['online']);
        $this->assertSame(3, $list[0]['flex']);
        $this->assertFalse($list[1]['online']);
        $this->assertStringContainsString('teacher.php?id=' . $this->t1->id, $list[0]['profileurl']);

        $this->assertSame([(int) $this->t2->id], array_column((new instant_tutors())->list($sid, 'Physics', '', 'normal', 2),
            'id'), 'max price 2 hides the 3-Flex tutor');

        $years = array_column(\local_academy\local\academic_structure::years(), 'key', 'id');
        $this->assertSame([(int) $this->t1->id, (int) $this->t2->id],
            array_column((new instant_tutors())->list($sid, 'Physics', $years[$this->yeara->id]), 'id'));
        $this->assertSame([(int) $this->t2->id],
            array_column((new instant_tutors())->list($sid, 'Physics', $years[$this->yearb->id]), 'id'),
            'year B: only the tutor without courses');
    }

    /**
     * "Ask this tutor": only they ring, once; a decline ends it without asking anyone else.
     *
     * @return void
     */
    public function test_ask_one_tutor(): void {
        $this->service->set_online((int) $this->t1->id, true);
        $this->service->set_online((int) $this->t2->id, true);
        $r = $this->service->request((int) $this->student->id, 'Physics', '', null, null, 'normal',
            ['teacherid' => (int) $this->t1->id]);
        $this->assertTrue($r['targeted']);
        $this->assertSame(1, $r['teachers']);
        $this->assertSame([], $this->service->heartbeat((int) $this->t2->id)['offers'], 'nobody else');
        $this->service->decline((int) $this->t1->id, $r['id']);
        $this->assertSame('expired', $this->service->status((int) $this->student->id, $r['id'])['status']);

        $this->service->set_online((int) $this->t1->id, false);
        $this->fails('err_tutornotavailable', fn() => $this->service->request((int) $this->student->id, 'Physics', '', null,
            null, 'normal', ['teacherid' => (int) $this->t1->id]));
    }

    /**
     * Max price: a broadcast only asks tutors at or under it.
     *
     * @return void
     */
    public function test_max_price_limits_broadcast(): void {
        $this->service->set_online((int) $this->t1->id, true);
        $this->service->set_online((int) $this->t2->id, true);
        $r = $this->service->request((int) $this->student->id, 'Physics', '', null, null, 'normal', ['maxflex' => 2]);
        $this->assertSame(1, $r['teachers']);
        $this->assertSame([], $this->service->heartbeat((int) $this->t1->id)['offers']);
        $this->assertCount(1, $this->service->heartbeat((int) $this->t2->id)['offers']);
    }

    /**
     * Confirmation: the tutor accepts and waits; the student sees the card and starts the lesson;
     * the tutor's heartbeat then sends them to the room.
     *
     * @return void
     */
    public function test_student_confirms(): void {
        global $DB;
        $sid = (int) $this->student->id;
        $this->service->set_online((int) $this->t2->id, true);
        $r = $this->service->request($sid, 'Physics', '', null, null, 'normal', ['autostart' => false]);
        $wait = $this->service->accept((int) $this->t2->id, $r['id']);
        $this->assertTrue($wait['waiting']);
        $this->assertSame(0, purchase::active($sid)['reserved_flex'], 'nothing charged before the student confirms');

        $st = $this->service->status($sid, $r['id']);
        $this->assertSame('accepted', $st['status']);
        $this->assertSame((int) $this->t2->id, $st['teacher']['id']);
        $this->assertSame(1, $st['teacher']['flex']);
        $this->assertCount(1, $this->service->heartbeat((int) $this->t2->id)['waiting']);

        $lesson = $this->service->confirm($sid, $r['id']);
        $this->assertSame('in_progress', $DB->get_field('nit_lesson', 'status', ['id' => $lesson['id']]));
        $this->assertSame(1, purchase::active($sid)['reserved_flex']);
        $started = $this->service->heartbeat((int) $this->t2->id)['started'];
        $this->assertSame($lesson['id'], $started[0]['lessonid'], 'the tutor is sent to the room');
    }

    /**
     * The student declines (or lets the confirmation run out): the tutor is released without counting
     * against them, and the broadcast asks the next tutor.
     *
     * @return void
     */
    public function test_reject_and_timeout_release_the_tutor(): void {
        global $DB;
        set_config('instant_wave_size', 1, 'local_nit_lessons');
        $sid = (int) $this->student->id;
        $this->service->set_online((int) $this->t1->id, true);
        $this->service->set_online((int) $this->t2->id, true);
        $r = $this->service->request($sid, 'Physics', '', null, null, 'normal', ['autostart' => false]);
        $first = (int) $this->service->status($sid, $r['id'])['teachers'] ? $this->asked($r['id']) : 0;
        $this->service->accept($first, $r['id']);
        $st = $this->service->reject($sid, $r['id']);
        $this->assertSame('searching', $st['status']);
        $this->assertSame('released', $DB->get_field('nit_instant_offer', 'status', ['requestid' => $r['id'],
            'teacherid' => $first]));
        $this->assertSame(0, $this->service->reliability($first)['prompts'], 'a release is not held against the tutor');

        $second = $this->asked($r['id']);
        $this->assertNotSame($first, $second, 'the next tutor is asked');
        $this->service->accept($second, $r['id']);
        $DB->set_field('nit_instant_request', 'confirmdeadline', time() - 1, ['id' => $r['id']]);
        $this->service->expire_due(time());
        $this->assertSame('released', $DB->get_field('nit_instant_offer', 'status', ['requestid' => $r['id'],
            'teacherid' => $second]), 'the student did not confirm in time');
    }

    /**
     * The tutor currently ringing for a request.
     *
     * @param int $requestid
     * @return int
     */
    private function asked(int $requestid): int {
        global $DB;
        return (int) $DB->get_field('nit_instant_offer', 'teacherid', ['requestid' => $requestid, 'status' => 'ringing']);
    }

    /**
     * Favourites: a star, and a notice (once a day) when that tutor goes online.
     *
     * @return void
     */
    public function test_favourites(): void {
        $sid = (int) $this->student->id;
        $this->assertTrue($this->service->toggle_favourite($sid, (int) $this->t1->id)['favourite']);
        $sink = $this->redirectMessages();
        $this->service->set_online((int) $this->t1->id, true);
        $this->service->set_online((int) $this->t1->id, false);
        $this->service->set_online((int) $this->t1->id, true);
        $this->assertCount(1, array_filter($sink->get_messages(), fn($m) => (int) $m->useridto === $sid), 'once a day');
        $this->assertFalse($this->service->toggle_favourite($sid, (int) $this->t1->id)['favourite']);
        $this->fails('err_forbidden', fn() => $this->service->toggle_favourite($sid, $sid));
    }

    /**
     * On call: an offline on-call tutor gets a push for a broadcast; two no-shows suspend it.
     *
     * @return void
     */
    public function test_on_call(): void {
        global $DB;
        $this->service->set_oncall((int) $this->t1->id, true);
        $this->service->set_online((int) $this->t2->id, true);
        $sink = $this->redirectMessages();
        $r = $this->service->request((int) $this->student->id, 'Physics');
        $this->assertSame('oncall', $DB->get_field('nit_instant_offer', 'status', ['requestid' => $r['id'],
            'teacherid' => $this->t1->id]));
        $this->assertCount(1, array_filter($sink->get_messages(), fn($m) => (int) $m->useridto === (int) $this->t1->id));

        foreach ([1, 2] as $i) {
            $DB->insert_record('nit_instant_offer', (object) ['requestid' => 500 + $i, 'teacherid' => $this->t1->id,
                'status' => 'noshow', 'timecreated' => time()]);
        }
        $method = new \ReflectionMethod($this->service, 'penalise_noshows');
        $method->invoke($this->service, (int) $this->t1->id, time());
        $presence = $this->service->presence((int) $this->t1->id);
        $this->assertFalse($presence['oncall']);
        $this->assertGreaterThan(time(), $presence['oncallblockeduntil']);
        $this->fails('err_oncallblocked', fn() => $this->service->set_oncall((int) $this->t1->id, true));
    }

    /**
     * Usually online: an hour seen in 3 of the last 4 weeks counts.
     *
     * @return void
     */
    public function test_usually_online(): void {
        global $DB;
        $now = time();
        $base = $now - ($now % WEEKSECS) - WEEKSECS; // A fixed slot each week.
        foreach ([0, 1, 2] as $w) {
            $start = $base - $w * WEEKSECS;
            $DB->insert_record('nit_teacher_presence_log', (object) ['teacherid' => $this->t1->id,
                'timestart' => $start, 'timeend' => $start + 2 * HOURSECS - 1]);
        }
        $ranges = instant_tutors::usually_online((int) $this->t1->id, $now);
        $this->assertCount(1, $ranges);
        $this->assertSame(2, $ranges[0]['to'] - $ranges[0]['from'], 'two hours');
        $this->assertNotSame('', instant_tutors::usually_online_text((int) $this->t1->id));
        $this->assertSame([], instant_tutors::usually_online((int) $this->t2->id, $now), 'no history');
    }

    /**
     * After an instant lesson: rate / book again / favourite card; the lesson completes after its own
     * length (not the 45-minute rule of booked lessons).
     *
     * @return void
     */
    public function test_after_call_and_complete_window(): void {
        global $DB;
        set_config('complete_allowed_minutes', 45, 'local_nit_lessons');
        $this->service->set_online((int) $this->t2->id, true);
        $r = $this->service->request((int) $this->student->id, 'Physics', '', null, null, 'quick');
        $lesson = $this->service->accept((int) $this->t2->id, $r['id']);
        $recent = instant_tutors::recent((int) $this->student->id);
        $this->assertSame((int) $this->t2->id, $recent['teacherid']);
        $this->assertStringContainsString('teacher=' . $this->t2->id, $recent['bookagainurl']);

        $DB->set_field('nit_lesson', 'actual_start', time() - 16 * MINSECS, ['id' => $lesson['id']]);
        \local_nit_lessons\api\lessons::complete((int) $this->t2->id, $lesson['id']);
        $this->assertSame('completed', $DB->get_field('nit_lesson', 'status', ['id' => $lesson['id']]),
            'a 15-min quick question completes after 15 min');
    }
}

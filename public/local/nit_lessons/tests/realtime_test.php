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
use local_nit_lessons\local\realtime;
use local_nit_lessons\room\room_factory;
use local_nit_lessons\room\stub_room;
use local_nit_lessons\service\instant_service;
use local_nit_lessons\service\teacher_service;

/**
 * Realtime signals for instant lessons (notify-ws relay): who is told what, and only after the
 * change is committed.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_lessons\local\realtime
 * @covers     \local_nit_lessons\service\instant_service
 */
final class realtime_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        realtime::$sink = [];
    }

    protected function tearDown(): void {
        realtime::$sink = null;
        room_factory::set_override(null);
        parent::tearDown();
    }

    /**
     * The signals sent so far, as "userid:what".
     *
     * @return string[]
     */
    private function sent(): array {
        $out = array_map(fn($s) => $s[0] . ':' . $s[1]['what'], realtime::$sink);
        realtime::$sink = [];
        sort($out);
        return $out;
    }

    /**
     * Off until the relay is configured; the token is the relay's HMAC format.
     *
     * @return void
     */
    public function test_client_config_and_token(): void {
        global $CFG;
        $this->assertNull(realtime::client_config(5));
        $CFG->academy_notify_publicurl = 'https://academy.test/';
        $CFG->academy_notify_secret = 's3cret';
        $CFG->academy_notify_path = '/notify-ws/socket.io';
        $config = realtime::client_config(5);
        $this->assertSame('https://academy.test', $config['url']);
        $this->assertSame('/notify-ws/socket.io', $config['path']);

        [$uid, $exp, $sig] = explode('.', realtime::mint_token(5, 1000));
        $this->assertSame('5', $uid);
        $this->assertSame((string) (1000 + 6 * HOURSECS), $exp);
        $this->assertSame(hash_hmac('sha256', "5.$exp", 's3cret'), $sig);

        $CFG->academy_notify_enabled = false;
        $this->assertNull(realtime::client_config(5), 'switched off again');
    }

    /**
     * Request → the asked teachers get "offer", the student "status"; accept → the other teacher's
     * prompt is "closed"; a failed accept sends nothing.
     *
     * @return void
     */
    public function test_signals_follow_the_request(): void {
        $gen = $this->getDataGenerator();
        $student = $gen->create_user();
        $t1 = $gen->create_user();
        $t2 = $gen->create_user();
        $course = $gen->create_course();
        set_config('subjects', 'Physics', 'local_nit_lessons');
        set_config('requireapproval', 0, 'local_nit_tutors');
        set_config('start_allowed_minutes', 100000, 'local_nit_lessons');
        foreach ([$t1, $t2] as $t) {
            $gen->enrol_user($t->id, $course->id, 'editingteacher');
            (new teacher_service())->save((int) $t->id, true, '', ['Physics'], []);
        }
        room_factory::set_override(new stub_room());
        purchase::fulfil((int) $student->id, packages::create((object) [
            'name' => 'Flex10', 'flex_count' => 10, 'price_minor' => 100000, 'expiration_days' => 0,
        ]));
        $service = new instant_service();
        $service->set_online((int) $t1->id, true);
        $service->set_online((int) $t2->id, true);
        $this->sent();

        $r = $service->request((int) $student->id, 'Physics');
        $expected = ["{$student->id}:status", "{$t1->id}:offer", "{$t2->id}:offer"];
        sort($expected);
        $this->assertSame($expected, $this->sent());

        $service->accept((int) $t2->id, $r['id']);
        $this->assertSame(["{$student->id}:status", "{$t1->id}:closed"], $this->sent());

        try {
            $service->accept((int) $t1->id, $r['id']);
            $this->fail('taken expected');
        } catch (\local_nit_lessons\exception\lesson_exception $e) {
            $this->assertSame('err_instanttaken', $e->errorcode);
        }
        $this->assertSame([], $this->sent(), 'nothing for a failed accept');
    }
}

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
 * The teacher tools: each teacher sees only their own lessons, classes, earnings
 * and profile; payout accounts are masked; teachers get the teacher mode.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\teacher\my_teaching::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\teacher\my_classes::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\teacher\my_earnings::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\teacher\my_availability::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\chat\access::class)]
final class teacher_tools_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $mona;

    /** @var \stdClass */
    private \stdClass $karim;

    /** @var \stdClass */
    private \stdClass $student;

    /** @var registry */
    private registry $tools;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->mona = $gen->create_user(['firstname' => 'Mona', 'lastname' => 'Ali']);
        $this->karim = $gen->create_user(['firstname' => 'Karim', 'lastname' => 'Saad']);
        $this->student = $gen->create_user(['firstname' => 'Sara', 'lastname' => 'Adel', 'phone1' => '01099990000']);
        $gen->enrol_user($this->mona->id, $course->id, 'editingteacher');
        $gen->enrol_user($this->karim->id, $course->id, 'teacher');
        $gen->enrol_user($this->student->id, $course->id, 'student');
        $this->tools = new registry();
    }

    /**
     * Run a tool for a teacher as the model would.
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
            'mode' => 'teacher', 'lang' => 'en', 'timecreated' => time()]);
        $ctx = new context((int) $convid, 'turn-1', 'teacher', 'web', 'en', (int) $user->id, time());
        return $this->tools->execute($ctx, $name, $input, 'toolu_' . random_string(8));
    }

    public function test_teachers_get_the_teacher_mode_and_only_teacher_tools(): void {
        $this->assertSame('teacher', access::mode((int) $this->mona->id));
        $this->assertSame('teacher', access::mode((int) $this->karim->id));
        $this->assertSame('student', access::mode((int) $this->student->id));
        $names = array_column($this->tools->definitions('teacher'), 'name');
        foreach (
            ['my_teaching', 'my_classes', 'my_earnings', 'my_availability', 'search_help', 'open_ticket',
                'handoff_to_human'] as $tool
        ) {
            $this->assertContains($tool, $names);
        }
        foreach (['my_wallet', 'capture_lead', 'my_payments'] as $tool) {
            $this->assertNotContains($tool, $names);
        }
        $this->assertNotContains('my_earnings', array_column($this->tools->definitions('student'), 'name'));
    }

    public function test_each_teacher_sees_only_their_own_lessons_and_requests(): void {
        global $DB;
        $now = time();
        $lesson = function (\stdClass $teacher, string $status, int $when, string $note = '') use ($DB, $now): int {
            return $DB->insert_record('nit_lesson', (object) ['studentid' => $this->student->id, 'teacherid' => $teacher->id,
                'subject' => 'Maths', 'status' => $status, 'requested_time' => $when, 'duration' => 60, 'cmid' => 0,
                'note' => $note, 'flex_state' => 'none', 'timecreated' => $now, 'timemodified' => $now]);
        };
        $lesson($this->mona, 'pending', $now + 2 * DAYSECS, 'Please help with fractions');
        $lesson($this->mona, 'confirmed', $now + 5 * DAYSECS);
        $lesson($this->karim, 'pending', $now + 3 * DAYSECS, 'Karim only');

        $r = $this->call('my_teaching', $this->mona, [], $ctx)['data'];
        $this->assertCount(1, $r['requests']);
        $this->assertSame('Sara Adel', $r['requests'][0]['student']);
        $this->assertSame('Please help with fractions', $r['requests'][0]['student_note']);
        $this->assertCount(1, $r['upcoming']);
        $this->assertNotEmpty($r['rules']['start_room']);
        $this->assertStringNotContainsString('Karim only', json_encode($r));
        $this->assertStringNotContainsString('01099990000', json_encode($r), 'no student contact details');
        $this->assertCount(1, $this->call('my_teaching', $this->karim)['data']['requests']);
    }

    public function test_earnings_are_the_teachers_own_and_payout_accounts_are_masked(): void {
        global $DB;
        $now = time();
        $DB->insert_record('nit_earning', (object) ['lessonid' => 1, 'source' => 'lesson', 'teacherid' => $this->mona->id,
            'studentid' => $this->student->id, 'purchaseid' => 0, 'flex_value_minor' => 20000, 'teacher_amount_minor' => 14000,
            'platform_amount_minor' => 6000, 'teacher_percent' => 70, 'platform_percent' => 30, 'status' => 'active',
            'reversedby' => 0, 'timereversed' => 0, 'usermodified' => 2, 'timecreated' => $now, 'timemodified' => $now]);
        $DB->insert_record('nit_earning', (object) ['lessonid' => 2, 'source' => 'lesson', 'teacherid' => $this->karim->id,
            'studentid' => $this->student->id, 'purchaseid' => 0, 'flex_value_minor' => 99900, 'teacher_amount_minor' => 77700,
            'platform_amount_minor' => 22200, 'teacher_percent' => 70, 'platform_percent' => 30, 'status' => 'active',
            'reversedby' => 0, 'timereversed' => 0, 'usermodified' => 2, 'timecreated' => $now, 'timemodified' => $now]);
        $DB->insert_record('nit_withdrawal', (object) ['teacherid' => $this->mona->id, 'amount_minor' => 5000,
            'method' => 'bank', 'account' => 'EG380019000500000000263180002', 'status' => 'rejected',
            'reason' => 'Wrong IBAN', 'processedby' => 2, 'timeprocessed' => $now, 'usermodified' => 2,
            'timecreated' => $now, 'timemodified' => $now]);

        $r = $this->call('my_earnings', $this->mona)['data'];
        $this->assertSame(140.0, $r['total_earned']);
        $this->assertCount(1, $r['recent_earnings']);
        $this->assertSame(140.0, $r['recent_earnings'][0]['amount']);
        $this->assertSame('Wrong IBAN', $r['withdrawals'][0]['reason']);
        $this->assertSame('…0002', $r['withdrawals'][0]['account']);
        $this->assertStringNotContainsString('EG380019000500000000263180002', json_encode($r));
        $this->assertStringNotContainsString('777', json_encode($r), 'not another teacher\'s earnings');
        $this->assertSame(777.0, $this->call('my_earnings', $this->karim)['data']['total_earned']);
    }

    public function test_availability_explains_why_students_cannot_book(): void {
        global $DB;
        $DB->insert_record('nit_teacher_profile', (object) ['userid' => $this->mona->id, 'available' => 1,
            'headline' => 'Maths teacher', 'lesson_flex' => 2, 'timemodified' => time()]);
        $DB->insert_record('nit_teacher_subject', (object) ['teacherid' => $this->mona->id, 'subject' => 'Mathematics']);
        $DB->insert_record('nit_teacher_hour', (object) ['teacherid' => $this->mona->id, 'dayofweek' => 0,
            'starttime' => '10:00', 'endtime' => '14:00']);
        $r = $this->call('my_availability', $this->mona, [], $ctx)['data'];
        $this->assertTrue($r['bookable']);
        $this->assertSame(['Mathematics'], $r['subjects']);
        $this->assertSame('Sunday', $r['weekly_hours'][0]['day']);
        $this->assertSame(2, $r['credits_per_lesson']);
        $this->assertStringContainsString('/local/academy/profile.php', $ctx->actions[0]['url']);

        $k = $this->call('my_availability', $this->karim)['data'];
        $this->assertFalse($k['bookable']);
        $this->assertContains('no weekly hours set', $k['why_not_bookable']);
        $this->assertSame([], $k['subjects'], 'not Mona\'s profile');
    }

    public function test_group_classes_are_the_teachers_upcoming_ones(): void {
        global $DB;
        $now = time();
        $class = static fn(\stdClass $t, string $title, int $start, string $status) => $DB->insert_record(
            'academy_live_sessions',
            (object) ['courseid' => SITEID, 'teacherid' => $t->id, 'title' => $title, 'start_time' => $start,
                'duration' => 60, 'status' => $status, 'timecreated' => $now, 'timemodified' => $now]
        );
        $class($this->mona, 'Revision', $now + DAYSECS, 'scheduled');
        $class($this->mona, 'Old class', $now - 3 * DAYSECS, 'ended');
        $class($this->karim, 'Karim class', $now + DAYSECS, 'scheduled');
        $r = $this->call('my_classes', $this->mona)['data']['classes'];
        $this->assertSame(['Revision'], array_column($r, 'title'));
        $this->assertFalse($r[0]['room_open'], 'opens 30 minutes before');
    }
}

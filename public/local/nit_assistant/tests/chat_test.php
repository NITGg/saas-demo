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

use local_nit_assistant\admin\setting_secret;
use local_nit_assistant\chat\access;
use local_nit_assistant\chat\conversation;
use local_nit_assistant\chat\service;
use local_nit_assistant\inbox\manager;
use local_nit_assistant\llm\client;
use local_nit_assistant\local\launcher;
use local_nit_assistant\runtime\state;
use local_nit_assistant\tests\fake_client;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixtures/fake_client.php');

/**
 * A message's way through the chat: whose conversation it is, the checks before
 * the assistant answers, the team taking over, and where the chat button shows.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\chat\conversation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\chat\service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\chat\access::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\inbox\manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\inbox\alerts::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\local\launcher::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\external\send_message::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\external\get_messages::class)]
final class chat_test extends \advanced_testcase {
    /** A visitor's browser key. */
    private const KEY = 'a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6';

    /** @var fake_client */
    private fake_client $llm;

    /** @var array[] Events emitted. */
    private array $events = [];

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->nit_crypto_key = 'base64:' . base64_encode(random_bytes(32));
        set_config('enabled', 1, 'local_nit_assistant');
        set_config('dailybudget', '5', 'local_nit_assistant');
        (new setting_secret('local_nit_assistant/apikey', 'k', '', ''))->write_setting('sk-ant-test');
        $this->llm = new fake_client();
        \core\di::set(client::class, $this->llm);
    }

    /**
     * Collects events.
     *
     * @return \Closure
     */
    private function emit(): \Closure {
        return function (string $event, array $data): void {
            $this->events[] = [$event, $data];
        };
    }

    /**
     * Names of the events emitted.
     *
     * @return string[]
     */
    private function names(): array {
        return array_column($this->events, 0);
    }

    /**
     * A visitor's conversation.
     *
     * @return \stdClass
     */
    private function visitor_conv(): \stdClass {
        return conversation::find_or_create(0, self::KEY, ['lang' => 'en', 'ip' => '10.0.0.1', 'page' => '/']);
    }

    public function test_a_visitor_keeps_their_chat_by_browser_key_and_it_follows_them_when_they_sign_in(): void {
        $conv = $this->visitor_conv();
        $this->assertSame((int) $conv->id, (int) $this->visitor_conv()->id);
        $this->assertNotSame(self::KEY, $conv->sessionkey, 'only the hash is stored');
        $this->assertNull(conversation::find(0, 'b' . substr(self::KEY, 1)), 'another browser sees nothing');

        $user = $this->getDataGenerator()->create_user();
        $mine = conversation::find_or_create((int) $user->id, self::KEY, ['lang' => 'en']);
        $this->assertSame((int) $conv->id, (int) $mine->id);
        // Signed out again on the same browser: the signed-in chat is not shown.
        $this->assertNull(conversation::find(0, self::KEY));
        $this->assertNotSame((int) $conv->id, (int) $this->visitor_conv()->id);
    }

    public function test_a_signed_in_users_chat_is_never_found_by_a_browser_key(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $conv = $this->visitor_conv();
        conversation::find_or_create((int) $user->id, self::KEY, ['lang' => 'en']);
        $this->assertNull($DB->get_field('local_nit_asst_conv', 'sessionkey', ['id' => $conv->id]), 'the key is dropped');

        // Even a row that still has a key (older data) is not a visitor's once it belongs to a user.
        $DB->insert_record('local_nit_asst_conv', (object) ['userid' => $user->id, 'sessionkey' => conversation::hash(self::KEY),
            'status' => state::OPEN, 'lang' => 'en', 'lastmessageat' => time(), 'timecreated' => time()]);
        $this->assertNull(conversation::find(0, self::KEY));
    }

    public function test_a_closed_or_long_idle_chat_starts_over(): void {
        $conv = $this->visitor_conv();
        $this->assertTrue(conversation::close(0, self::KEY));
        $this->assertNotSame((int) $conv->id, (int) $this->visitor_conv()->id);

        $idle = $this->visitor_conv();
        $later = time() + conversation::IDLE + 60;
        $this->assertNull(conversation::find(0, self::KEY, 'web', $later));
    }

    public function test_the_reply_follows_the_language_of_each_message_not_the_site(): void {
        global $DB;
        $conv = conversation::find_or_create(0, self::KEY, ['lang' => 'ar']); // The site is in Arabic.
        $this->llm->script = [fake_client::text('Hello!'), fake_client::text('أهلًا!'), fake_client::text('OK')];

        (new service())->send($conv, 'what are the courses?', $this->emit());
        $this->assertSame('en', $DB->get_field('local_nit_asst_conv', 'lang', ['id' => $conv->id]));
        $this->assertStringContainsString('latest message: English', $this->llm->requests[0]['volatile']);

        (new service())->send($conv, 'ايه الكورسات المتاحة؟', $this->emit());
        $this->assertStringContainsString('latest message: Arabic', $this->llm->requests[1]['volatile']);

        // Digits or an emoji alone keep the language so far.
        (new service())->send($conv, '👍 2', $this->emit());
        $this->assertSame('ar', $DB->get_field('local_nit_asst_conv', 'lang', ['id' => $conv->id]));
        $this->assertSame('en', conversation::detect_lang('see https://مثال.com and tell me', 'ar'));
    }

    public function test_the_assistant_answers_and_the_reply_is_stored(): void {
        global $DB;
        $this->llm->script = [fake_client::text('Welcome! We have maths courses.')];
        $conv = $this->visitor_conv();
        $outcome = (new service())->send($conv, '  Hello  ', $this->emit());
        $this->assertSame('replied', $outcome);
        $this->assertSame(['stored', 'delta', 'done'], $this->names());
        $msgs = conversation::messages((int) $conv->id);
        $this->assertSame(['visitor', 'assistant'], array_column($msgs, 'role'));
        $this->assertSame('Hello', $msgs[0]['text']);
        $this->assertSame(
            0,
            (int) $DB->get_field('local_nit_asst_conv', 'turnlockuntil', ['id' => $conv->id]),
            'the turn claim is released'
        );
    }

    public function test_bad_messages_and_double_sends_are_refused_before_the_model(): void {
        $conv = $this->visitor_conv();
        $this->assertSame('invalid', (new service())->send($conv, '   ', $this->emit()));
        $this->assertSame('invalid', (new service())->send($conv, str_repeat('x', service::MAXLEN + 1), $this->emit()));

        state::begin_turn((int) $conv->id, 60);
        $this->assertSame('busy', (new service())->send($conv, 'hello?', $this->emit()));
        $this->assertSame([], conversation::messages((int) $conv->id), 'nothing stored while another reply runs');
        $this->assertSame([], $this->llm->requests);
    }

    public function test_when_the_assistant_is_off_nothing_is_stored(): void {
        set_config('dailybudget', '0', 'local_nit_assistant');
        $conv = $this->visitor_conv();
        $this->assertSame('unavailable', (new service())->send($conv, 'hi', $this->emit()));
        $this->assertSame([], conversation::messages((int) $conv->id));
    }

    public function test_a_long_conversation_must_start_over(): void {
        global $DB;
        $conv = $this->visitor_conv();
        $DB->set_field('local_nit_asst_conv', 'messagecount', config::LIMITS['maxconvmessages'], ['id' => $conv->id]);
        $this->assertSame('full', (new service())->send($conv, 'hi', $this->emit()));
    }

    public function test_while_the_team_has_the_chat_messages_go_to_them_not_the_model(): void {
        $sink = $this->redirectMessages();
        global $DB;
        $staff = $this->getDataGenerator()->create_user();
        $manager = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        $this->getDataGenerator()->role_assign($manager, $staff->id, \context_system::instance()->id);
        $conv = $this->visitor_conv();
        $this->assertTrue(manager::take_over((int) $conv->id, (int) $staff->id));

        $this->assertSame('queued', (new service())->send($conv, 'are you there?', $this->emit()));
        $this->assertSame([], $this->llm->requests);
        $this->assertContains('queued', $this->names());
        $this->assertSame(1, $sink->count(), 'the owner is alerted');
        // A second message right after does not alert again.
        (new service())->send($conv, 'hello??', $this->emit());
        $this->assertSame(1, $sink->count());

        manager::reply((int) $conv->id, (int) $staff->id, 'Yes, Mona here.');
        $roles = array_column(conversation::messages((int) $conv->id), 'role');
        $this->assertSame(['visitor', 'visitor', 'staff'], $roles);
        $sink->close();
    }

    public function test_replying_takes_the_chat_over_and_another_staff_member_cannot_reply(): void {
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $conv = $this->visitor_conv();
        manager::reply((int) $conv->id, (int) $a->id, 'Hi, I can help.');
        $this->assertSame(state::HUMAN, conversation::find(0, self::KEY)->status);
        try {
            manager::reply((int) $conv->id, (int) $b->id, 'Me too');
            $this->fail('second staff member replied');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_notowner', $e->errorcode);
        }
        $this->assertTrue(manager::hand_back((int) $conv->id));
        $this->assertSame(state::OPEN, conversation::find(0, self::KEY)->status);
    }

    public function test_abuse_limit_per_sender(): void {
        set_config('hourlylimit', 3, 'local_nit_assistant');
        $now = time();
        foreach ([true, true, true, false] as $expected) {
            $this->assertSame($expected, service::allow('ip:abc', $now));
        }
        $this->assertTrue(service::allow('ip:other', $now));

        // Per day: four times the hourly limit, even when spread over the hours.
        set_config('hourlylimit', 2, 'local_nit_assistant');
        $day = intdiv(time(), DAYSECS) * DAYSECS + 60;
        for ($h = 0; $h < 4; $h++) {
            $this->assertTrue(service::allow('ip:day', $day + $h * HOURSECS));
            $this->assertTrue(service::allow('ip:day', $day + $h * HOURSECS));
        }
        $this->assertFalse(service::allow('ip:day', $day + 5 * HOURSECS), 'the ninth message of the day');
    }

    public function test_who_may_chat(): void {
        $this->assertSame(0, access::userid(), 'visitors by default');
        set_config('visitors', 0, 'local_nit_assistant');
        $this->assertNull(access::userid());

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertSame((int) $user->id, access::userid());

        set_config('enabled', 0, 'local_nit_assistant');
        $this->assertNull(access::userid());
    }

    public function test_the_chat_button_shows_only_where_it_should(): void {
        global $SESSION;
        $this->assertTrue(launcher::should_show('standard'));
        $this->assertFalse(launcher::should_show('embedded'));
        $this->assertFalse(launcher::should_show('admin'));

        $SESSION->theme_nit_appembed = 1;
        $this->assertFalse(launcher::should_show('standard'), 'never inside the app WebView');
        unset($SESSION->theme_nit_appembed);

        set_config('dailybudget', '0', 'local_nit_assistant');
        $this->assertFalse(launcher::should_show('standard'));

        $data = launcher::data();
        $this->assertStringContainsString('/local/nit_assistant/chat.php', $data['chat']);
        $this->assertNotSame('', $data['greeting']);
        $this->assertArrayHasKey('chat_open', $data['strings']);
    }

    public function test_admins_can_test_the_ai_connection_and_see_the_last_error(): void {
        $this->llm->script = [fake_client::text('OK')];
        $r = llm\check::run();
        $this->assertTrue($r['ok']);
        $this->assertSame('OK', $r['reply']);

        $this->llm->script = [new \moodle_exception(
            'error_model',
            'local_nit_assistant',
            '',
            null,
            'Anthropic\Core\Exceptions\AuthenticationException: invalid x-api-key'
        )];
        $r = llm\check::run();
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('invalid x-api-key', $r['error'], 'the real reason, not the generic message');
        $this->assertStringContainsString('invalid x-api-key', config::last_error()['message']);
    }

    public function test_the_app_gets_the_whole_reply_and_the_history(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->llm->script = [fake_client::text('Hi from the assistant')];
        $out = external\send_message::execute('Hello');
        $out = \core_external\external_api::clean_returnvalue(external\send_message::execute_returns(), $out);
        $this->assertSame('replied', $out['outcome']);
        $this->assertSame('Hi from the assistant', $out['reply']);
        $this->assertSame('open', $out['status']);

        $hist = external\get_messages::execute(0);
        $hist = \core_external\external_api::clean_returnvalue(external\get_messages::execute_returns(), $hist);
        $this->assertSame(['visitor', 'assistant'], array_column($hist['messages'], 'role'));
        $this->assertTrue($hist['available']);
        $this->assertNotSame('', $hist['greeting']);
    }
}

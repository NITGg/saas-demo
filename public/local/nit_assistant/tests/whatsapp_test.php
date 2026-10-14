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

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use local_nit_assistant\admin\setting_secret;
use local_nit_assistant\inbox\manager;
use local_nit_assistant\llm\client;
use local_nit_assistant\runtime\state;
use local_nit_assistant\tests\fake_client;
use local_nit_assistant\whatsapp\cloud;
use local_nit_assistant\whatsapp\identity;
use local_nit_assistant\whatsapp\inbound;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixtures/fake_client.php');

/**
 * The WhatsApp channel: Meta's webhook checks, the same assistant on WhatsApp,
 * a person's chats left to the person, account help only after an email code,
 * and staff replies sent on WhatsApp inside the 24-hour window. Meta's API is
 * mocked; nothing goes over the network.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\whatsapp\cloud::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\whatsapp\inbound::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\whatsapp\identity::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\tools\common\link_whatsapp_account::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nit_assistant\inbox\manager::class)]
final class whatsapp_test extends \advanced_testcase {
    /** The user's number. */
    private const PHONE = '+201001234567';

    /** @var fake_client */
    private fake_client $llm;

    /** @var array Requests sent to Meta. */
    private array $sent = [];

    /** @var MockHandler */
    private MockHandler $meta;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->nit_crypto_key = 'base64:' . base64_encode(random_bytes(32));
        set_config('enabled', 1, 'local_nit_assistant');
        set_config('dailybudget', '5', 'local_nit_assistant');
        set_config('wa_ai', 1, 'local_nit_assistant');
        set_config('wa_phonenumberid', '1234567890', 'local_nit_assistant');
        (new setting_secret('local_nit_assistant/apikey', 'k', '', ''))->write_setting('sk-ant-test');
        (new setting_secret('local_nit_assistant/wa_token', 'k', '', ''))->write_setting('EAAG-test');
        $this->llm = new fake_client();
        \core\di::set(client::class, $this->llm);

        // Meta answers every call with a message id unless a test queues something else.
        $this->meta = new MockHandler();
        $stack = HandlerStack::create($this->meta);
        $stack->push(Middleware::history($this->sent));
        \core\di::set(cloud::class, new cloud(cloud::config(), new \GuzzleHttp\Client(['handler' => $stack])));
    }

    /**
     * Queue Meta's answers.
     *
     * @param int $n how many "ok" answers
     * @return void
     */
    private function meta_ok(int $n): void {
        for ($i = 0; $i < $n; $i++) {
            $this->meta->append(new Response(200, [], json_encode(['messages' => [['id' => 'wamid.out' . $i]]])));
        }
    }

    /**
     * Texts sent to the user (mark-read calls left out).
     *
     * @return string[]
     */
    private function texts(): array {
        $out = [];
        foreach ($this->sent as $t) {
            $body = json_decode((string) $t['request']->getBody(), true) ?: [];
            if (($body['type'] ?? '') === 'text') {
                $out[] = $body['text']['body'];
            }
        }
        return $out;
    }

    /**
     * An incoming message as cloud::parse_webhook() gives it.
     *
     * @param string|null $text
     * @param string $type
     * @return array
     */
    private function msg(?string $text, string $type = 'text'): array {
        return ['id' => 'wamid.' . random_string(10), 'from' => self::PHONE, 'name' => 'Hany', 'type' => $type,
            'text' => $text, 'time' => time(), 'phonenumberid' => '1234567890'];
    }

    /**
     * The number's conversation.
     *
     * @return \stdClass
     */
    private function conv(): \stdClass {
        global $DB;
        return $DB->get_record('local_nit_asst_conv', ['channel' => 'whatsapp', 'phone' => self::PHONE], '*', MUST_EXIST);
    }

    public function test_only_calls_signed_with_the_app_secret_are_accepted(): void {
        $raw = '{"object":"whatsapp_business_account"}';
        $good = 'sha256=' . hash_hmac('sha256', $raw, 'app-secret');
        $this->assertTrue(cloud::verify_signature($raw, $good, 'app-secret'));
        $this->assertFalse(cloud::verify_signature($raw . ' ', $good, 'app-secret'), 'body changed');
        $this->assertFalse(cloud::verify_signature($raw, $good, 'other-secret'));
        $this->assertFalse(cloud::verify_signature($raw, null, 'app-secret'));
        $this->assertFalse(cloud::verify_signature($raw, $good, ''), 'no secret set: refuse everything');
    }

    public function test_webhook_payloads_are_read_and_long_replies_split(): void {
        $payload = ['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => '1234567890'],
            'contacts' => [['wa_id' => '201001234567', 'profile' => ['name' => 'Hany']]],
            'messages' => [
                ['id' => 'w1', 'from' => '201001234567', 'timestamp' => '1700000000', 'type' => 'text', 'text' => ['body' => 'hi']],
                ['id' => 'w2', 'from' => '201001234567', 'type' => 'image'],
                ['id' => 'w3', 'from' => '201001234567', 'type' => 'interactive',
                    'interactive' => ['button_reply' => ['title' => 'Yes']]],
            ],
            'statuses' => [['id' => 'x', 'status' => 'delivered']],
        ]]]]]];
        $m = cloud::parse_webhook($payload);
        $this->assertSame(['hi', null, 'Yes'], array_column($m, 'text'));
        $this->assertSame(self::PHONE, $m[0]['from']);
        $this->assertSame('Hany', $m[0]['name']);
        $this->assertSame([], cloud::parse_webhook(['object' => 'page']));

        $long = str_repeat("Paragraph text here.\n\n", 400);
        $parts = cloud::split_text($long);
        $this->assertGreaterThan(1, count($parts));
        foreach ($parts as $p) {
            $this->assertLessThanOrEqual(cloud::MAX_TEXT, \core_text::strlen($p));
        }
    }

    public function test_the_assistant_answers_on_whatsapp_and_each_message_is_handled_once(): void {
        $this->llm->script = [fake_client::text('Welcome! We have maths courses.')];
        $this->meta_ok(2); // Mark read + the reply.
        $m = $this->msg('مرحبا، عندكم رياضيات؟');
        $this->assertTrue(inbound::claim($m['id']));
        $this->assertFalse(inbound::claim($m['id']), 'Meta retried: ignored');

        $outcome = (new inbound())->process($m);
        $this->assertSame('AI replied (sales)', $outcome);
        $this->assertSame(['Welcome! We have maths courses.'], $this->texts());
        $conv = $this->conv();
        $this->assertSame('ar', $conv->lang);
        $this->assertSame(0, (int) $conv->userid, 'a number alone identifies nobody');
        // WhatsApp gets the WhatsApp tools and the plain-text instruction.
        $this->assertStringContainsString('WhatsApp', $this->llm->requests[0]['volatile']);
        $this->assertContains('link_whatsapp_account', array_column($this->llm->requests[0]['tools'], 'name'));
    }

    public function test_while_a_person_has_the_chat_the_ai_stays_quiet(): void {
        global $DB;
        $this->meta_ok(2); // Mark read + the text-only notice.
        $sink = $this->redirectMessages();
        $staff = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign(
            $DB->get_field('role', 'id', ['shortname' => 'manager']),
            $staff->id,
            \context_system::instance()->id
        );
        (new inbound())->process($this->msg(null, 'image'));        // Creates the conversation (text-only notice).
        $this->meta_ok(2);
        manager::take_over((int) $this->conv()->id, (int) $staff->id);
        $this->sent = [];
        $outcome = (new inbound())->process($this->msg('are you there?'));
        $this->assertStringContainsString('stored for the team', $outcome);
        $this->assertSame([], $this->llm->requests);
        $this->assertSame([], $this->texts());
        $sink->close();
    }

    public function test_with_the_ai_off_messages_go_to_the_team(): void {
        set_config('wa_ai', 0, 'local_nit_assistant');
        $this->meta_ok(1);
        $sink = $this->redirectMessages();
        (new inbound())->process($this->msg('hello'));
        $this->assertSame(state::WAITING, $this->conv()->status);
        $this->assertSame([], $this->texts(), 'no auto-reply');
        $this->assertGreaterThan(0, $sink->count(), 'staff alerted');
        $sink->close();
    }

    public function test_media_gets_a_text_only_notice(): void {
        $this->meta_ok(2);
        (new inbound())->process($this->msg(null, 'audio'));
        $this->assertCount(1, $this->texts());
        $this->assertSame([], $this->llm->requests);
    }

    public function test_a_number_is_linked_only_with_the_code_emailed_to_the_account(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['email' => 'sara@example.com']);
        $this->getDataGenerator()->enrol_user($user->id, $this->getDataGenerator()->create_course()->id, 'student');
        $emails = $this->redirectEmails();

        // The model calls link_whatsapp_account with the email the user gave.
        $this->llm->script = [fake_client::tools([['link_whatsapp_account', ['email' => 'Sara@Example.com']]]),
            fake_client::text('I sent a code to your email — type it here.')];
        $this->meta_ok(2);
        (new inbound())->process($this->msg('I want to check my subscription, my email is Sara@Example.com'));
        $this->assertSame(1, $emails->count());
        $email = $emails->get_messages()[0];
        $this->assertSame('sara@example.com', $email->to);
        $this->assertSame(1, preg_match('/\b(\d{6})\b/', $email->subject, $mm));
        $code = $mm[1];

        // A wrong code, then the right one.
        $this->meta_ok(4);
        $this->assertSame('verification code → wrong_code', (new inbound())->process($this->msg('000000')));
        $this->assertSame('verification code → verified', (new inbound())->process($this->msg(implode(' ', str_split($code, 3)))));
        $conv = $this->conv();
        $this->assertSame((int) $user->id, (int) $conv->userid);
        $this->assertSame('student', $conv->mode);
        $this->assertSame((int) $user->id, identity::active(self::PHONE));
        $this->assertFalse(
            $DB->record_exists_select('local_nit_asst_msg', $DB->sql_like('content', ':c'), ['c' => "%{$code}%"]),
            'the code is never stored'
        );
        $this->assertFalse($DB->record_exists_select('local_nit_asst_audit', $DB->sql_like('args', ':c'), ['c' => "%{$code}%"]));
        $emails->close();
    }

    public function test_an_email_without_an_account_gets_the_same_answer_and_no_email(): void {
        $emails = $this->redirectEmails();
        $this->llm->script = [fake_client::tools([['link_whatsapp_account', ['email' => 'nobody@example.com']]]),
            fake_client::text('If an account has this email, a code was sent.')];
        $this->meta_ok(2);
        (new inbound())->process($this->msg('link nobody@example.com'));
        $this->assertSame(0, $emails->count());
        $this->assertSame('ok', $GLOBALS['DB']->get_field('local_nit_asst_audit', 'status', ['tool' => 'link_whatsapp_account']));
        // Any code is wrong.
        $this->meta_ok(2);
        $this->assertSame('verification code → wrong_code', (new inbound())->process($this->msg('123456')));
        $this->assertNull(identity::active(self::PHONE));
        $emails->close();
    }

    public function test_the_link_tool_exists_only_on_whatsapp(): void {
        $reg = new tools\registry();
        $this->assertContains('link_whatsapp_account', array_column($reg->definitions('sales', 'whatsapp'), 'name'));
        $this->assertNotContains('link_whatsapp_account', array_column($reg->definitions('sales', 'web'), 'name'));
        $ctx = new tools\context(1, 't', 'sales', 'web', 'en', 0, time());
        $this->assertSame('not_authorized', $reg->execute($ctx, 'link_whatsapp_account', ['email' => 'a@b.cd'], 'x')['code']);
    }

    public function test_staff_replies_go_out_on_whatsapp_only_inside_the_24_hour_window(): void {
        global $DB;
        $staff = $this->getDataGenerator()->create_user();
        set_config('wa_ai', 0, 'local_nit_assistant');
        $this->meta_ok(1);
        $sink = $this->redirectMessages();
        (new inbound())->process($this->msg('I need a person'));
        $convid = (int) $this->conv()->id;

        $this->meta_ok(1);
        manager::reply($convid, (int) $staff->id, 'Hi, Mona from the team here.');
        $this->assertSame(['Hi, Mona from the team here.'], $this->texts());

        // A day later the window is closed.
        $DB->execute("UPDATE {local_nit_asst_msg} SET timecreated = timecreated - ? WHERE role = 'visitor'", [DAYSECS + 60]);
        try {
            manager::reply($convid, (int) $staff->id, 'Are you still there?');
            $this->fail('replied outside the window');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_wawindow', $e->errorcode);
        }

        // Meta refuses → the message is kept and marked failed.
        $DB->execute("UPDATE {local_nit_asst_msg} SET timecreated = ? WHERE role = 'visitor'", [time()]);
        $this->meta->append(new Response(400, [], json_encode(['error' => ['message' => 'Re-engagement required']])));
        try {
            manager::reply($convid, (int) $staff->id, 'Third try');
            $this->fail('no error shown');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_wasend', $e->errorcode);
        }
        $this->assertSame('failed', $DB->get_field_select(
            'local_nit_asst_msg',
            'status',
            $DB->sql_compare_text('content') . ' = ?',
            ['Third try']
        ));
        $sink->close();
    }
}

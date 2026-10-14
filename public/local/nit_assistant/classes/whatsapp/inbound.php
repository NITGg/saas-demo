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

namespace local_nit_assistant\whatsapp;

use local_nit_assistant\chat\access;
use local_nit_assistant\chat\conversation;
use local_nit_assistant\chat\service;
use local_nit_assistant\config;
use local_nit_assistant\inbox\alerts;
use local_nit_assistant\runtime\state;
use local_nit_assistant\runtime\turn;

/**
 * One incoming WhatsApp message → the same assistant as the website: one open
 * conversation per number, the same limits, account help only after the number
 * is linked by an email code, a person's conversations left to the person.
 * Replies always fall inside Meta's 24-hour window because the user just wrote.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class inbound {
    /** Longest message read (characters). */
    public const MAXLEN = 2000;

    /** How long to wait for a reply that is still being written (seconds). */
    public const LOCK_WAIT = 45;

    /** @var \Closure function(): turn */
    private \Closure $turnfactory;

    /**
     * Constructor.
     *
     * @param \Closure|null $turnfactory builds the turn (tests inject one)
     */
    public function __construct(?\Closure $turnfactory = null) {
        $this->turnfactory = $turnfactory ?? static fn(): turn => turn::create();
    }

    /**
     * Is the WhatsApp message id new? Meta retries webhooks; each message is handled once.
     *
     * @param string $messageid
     * @return bool
     */
    public static function claim(string $messageid): bool {
        global $DB;
        try {
            $DB->insert_record('local_nit_asst_idem', (object) ['idemkey' => substr('wa:in:' . $messageid, 0, 191),
                'tool' => 'whatsapp_inbound', 'result' => '{}', 'timecreated' => time()]);
            return true;
        } catch (\dml_write_exception $e) {
            return false;
        }
    }

    /**
     * Should the AI answer on WhatsApp? Off: messages go to the AI Inbox for the team.
     *
     * @return bool
     */
    public static function ai_on(): bool {
        return (bool) get_config('local_nit_assistant', 'wa_ai');
    }

    /**
     * Handle one message (from cloud::parse_webhook()).
     *
     * @param array $m
     * @param int|null $now
     * @return string what happened (for the log)
     */
    public function process(array $m, ?int $now = null): string {
        $now ??= time();
        $cloud = cloud::instance();
        if (!$cloud) {
            return 'not configured';
        }
        if (!service::allow('wa' . conversation::hash($m['from']), $now)) {
            return 'blocked (message limit)';
        }
        $cloud->mark_read($m['id']);
        $text = \core_text::substr(trim((string) ($m['text'] ?? '')), 0, self::MAXLEN);
        $userid = identity::active($m['from'], $now);
        $mode = $userid ? access::mode($userid) : 'sales';
        $conv = self::conversation($m['from'], (int) $userid, $mode, $text, $now);
        $conv = conversation::follow_lang($conv, $text); // Reply in the language they just wrote in.
        $lang = $conv->lang;
        $withperson = in_array($conv->status, [state::WAITING, state::HUMAN], true);

        // Pictures, voice notes, stickers…: text only for now.
        if ($m['text'] === null || $text === '') {
            conversation::add_message($conv, 'visitor', '[' . $m['type'] . ']', [], $now);
            if ($withperson) {
                alerts::visitor_message($conv);
                return 'media stored for the team';
            }
            if (self::ai_on()) {
                $this->say($cloud, $conv, self::str('wa_textonly', $lang));
            }
            return 'media → text-only notice';
        }

        // A typed verification code is checked here: it never reaches the model or the history.
        $pending = identity::pending($conv, $now);
        $code = $pending ? identity::extract_code($text) : null;
        if ($pending && $code) {
            conversation::add_message($conv, 'visitor', '[verification code]', [], $now);
            $outcome = identity::confirm($conv, $pending, $code, $now);
            $this->say($cloud, $conv, self::str(['verified' => 'wa_verified', 'wrong_code' => 'wa_wrongcode',
                'expired' => 'wa_codeexpired'][$outcome], $lang));
            return "verification code → {$outcome}";
        }

        if ($withperson) {
            conversation::add_message($conv, 'visitor', $text, [], $now);
            alerts::visitor_message($conv);
            return "stored for the team ({$conv->status})";
        }
        if (!self::ai_on()) {
            conversation::add_message($conv, 'visitor', $text, [], $now);
            $this->hand_off($cloud, $conv, $lang, $text, false);
            return 'AI off on WhatsApp → AI Inbox';
        }
        if ((int) $conv->messagecount >= config::LIMITS['maxconvmessages'] || !config::usable()) {
            conversation::add_message($conv, 'visitor', $text, [], $now);
            $this->hand_off($cloud, $conv, $lang, $text, true);
            return 'limit or assistant unavailable → handed to the team';
        }

        // One reply at a time per number; WhatsApp can't be told "busy", so wait for the running one.
        $deadline = time() + self::LOCK_WAIT;
        while (!state::begin_turn((int) $conv->id, (int) config::LIMITS['turntimeout'] + 30, time())) {
            if (time() > $deadline) {
                conversation::add_message($conv, 'visitor', $text, [], $now);
                return 'stored without a reply (another reply was still running)';
            }
            sleep(1);
        }
        try {
            conversation::add_message($conv, 'visitor', $text, [], $now);
            $reply = '';
            $links = [];
            $handoff = '';
            $messageid = null;
            $outcome = ($this->turnfactory)()->run((int) $conv->id, static function (
                string $event,
                array $data
            ) use (
                &$reply,
                &$links,
                &$handoff,
                &$messageid
            ): void {
                match ($event) {
                    'delta' => $reply .= $data['text'],
                    'action' => $links[] = $data['label'] . ': ' . $data['url'],
                    'handoff' => $handoff = (string) ($data['message'] ?? ''),
                    'done' => $messageid = $data['messageid'] ?? null,
                    default => null,
                };
            }, null, $now);
        } finally {
            state::end_turn((int) $conv->id);
        }
        if ($outcome === 'error') {
            $this->hand_off($cloud, $conv, $lang, $text, true);
            return 'AI model failed → handed to the team';
        }
        if (!$messageid) {
            return 'reply discarded (a person took over)';
        }
        $body = implode("\n\n", array_filter([trim($reply) !== '' ? trim($reply) : $handoff, ...array_unique($links)]));
        return $this->deliver($cloud, $conv, (int) $messageid, $body) ? "AI replied ({$conv->mode})" : 'AI reply NOT delivered';
    }

    /**
     * The number's open conversation (kept in step with the linked account), or a new one.
     *
     * @param string $phone
     * @param int $userid 0 when the number is not linked
     * @param string $mode
     * @param string $text
     * @param int $now
     * @return \stdClass
     */
    private static function conversation(string $phone, int $userid, string $mode, string $text, int $now): \stdClass {
        global $DB;
        $rows = $DB->get_records_select(
            'local_nit_asst_conv',
            "channel = 'whatsapp' AND phone = ? AND status <> ?",
            [$phone, state::CLOSED],
            'id DESC',
            '*',
            0,
            1
        );
        $conv = $rows ? reset($rows) : null;
        if ($conv && ($conv->status !== state::OPEN || $now - (int) $conv->lastmessageat <= conversation::IDLE)) {
            if ((int) $conv->userid !== $userid || $conv->mode !== $mode) {
                $DB->update_record('local_nit_asst_conv', (object) ['id' => $conv->id, 'userid' => $userid, 'mode' => $mode]);
                $conv->userid = $userid;
                $conv->mode = $mode;
            }
            return $conv;
        }
        $id = $DB->insert_record('local_nit_asst_conv', (object) [
            'userid' => $userid, 'channel' => 'whatsapp', 'phone' => $phone, 'mode' => $mode, 'status' => state::OPEN,
            'lang' => preg_match('/\p{Arabic}/u', $text) || $text === '' ? 'ar' : 'en', 'sourcepage' => 'whatsapp',
            'iphash' => conversation::hash($phone), 'lastmessageat' => $now, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        return $DB->get_record('local_nit_asst_conv', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Give the conversation to the team (and tell the user when $say).
     *
     * @param cloud $cloud
     * @param \stdClass $conv
     * @param string $lang
     * @param string $text the user's message (a note for the team)
     * @param bool $say
     * @return void
     */
    private function hand_off(cloud $cloud, \stdClass $conv, string $lang, string $text, bool $say): void {
        if (state::transition((int) $conv->id, [state::OPEN], state::WAITING, ['summary' => \core_text::substr($text, 0, 500)])) {
            alerts::handoff((int) $conv->id, \core_text::substr($text, 0, 500));
        }
        if ($say) {
            $this->say($cloud, $conv, self::str('msg_handoff', $lang));
        }
    }

    /**
     * Store and send a fixed reply.
     *
     * @param cloud $cloud
     * @param \stdClass $conv
     * @param string $text
     * @return void
     */
    private function say(cloud $cloud, \stdClass $conv, string $text): void {
        $id = conversation::add_message($conv, 'assistant', $text);
        $this->deliver($cloud, $conv, $id, $text);
    }

    /**
     * Send a stored reply; a failed send marks the message failed (shown in the inbox).
     *
     * @param cloud $cloud
     * @param \stdClass $conv
     * @param int $messageid
     * @param string $text
     * @return bool
     */
    private function deliver(cloud $cloud, \stdClass $conv, int $messageid, string $text): bool {
        global $DB;
        if (trim($text) === '') {
            return true;
        }
        try {
            $cloud->send_text($conv->phone, $text);
            return true;
        } catch (\Throwable $e) {
            debugging('local_nit_assistant: WhatsApp send failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $DB->set_field('local_nit_asst_msg', 'status', 'failed', ['id' => $messageid]);
            return false;
        }
    }

    /**
     * A plugin string in a language.
     *
     * @param string $id
     * @param string $lang
     * @return string
     */
    private static function str(string $id, string $lang): string {
        return get_string_manager()->get_string($id, 'local_nit_assistant', null, $lang);
    }
}

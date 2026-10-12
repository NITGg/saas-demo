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

namespace local_nit_assistant\chat;

use local_nit_assistant\config;
use local_nit_assistant\inbox\alerts;
use local_nit_assistant\runtime\rate_limit;
use local_nit_assistant\runtime\state;
use local_nit_assistant\runtime\turn;

/**
 * One message from the user, whatever the channel (web stream, app, later
 * WhatsApp): checks, stores it, and either lets the assistant answer or — when
 * a person owns the conversation — passes it to the team.
 *
 * Events passed to $emit: stored {id} · then the turn's events (delta, action,
 * handoff, done, error) · or queued {status} + done when the team has it ·
 * or error {code} alone when the message was refused.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class service {
    /** Longest message accepted (characters). */
    public const MAXLEN = 2000;

    /** @var \Closure function(): turn */
    private \Closure $turnfactory;

    /**
     * Constructor.
     *
     * @param \Closure|null $turnfactory builds the turn (tests inject one); default turn::create()
     */
    public function __construct(?\Closure $turnfactory = null) {
        $this->turnfactory = $turnfactory ?? static fn(): turn => turn::create();
    }

    /**
     * Abuse limits per sender (an IP hash for visitors, the user id otherwise).
     *
     * @param string $sender
     * @param int|null $now
     * @return bool false once the sender has sent too many messages this hour or day
     */
    public static function allow(string $sender, ?int $now = null): bool {
        $hourly = rate_limit::hit("msg:h:{$sender}", config::hourly_limit(), HOURSECS, $now);
        $daily = rate_limit::hit("msg:d:{$sender}", config::hourly_limit() * 4, DAYSECS, $now);
        return $hourly['allowed'] && $daily['allowed'];
    }

    /**
     * Handle one message.
     *
     * @param \stdClass $conv from conversation::find_or_create()
     * @param string $text
     * @param callable $emit function(string $event, array $data): void
     * @param string|null $page the page the user is on
     * @param int|null $now
     * @return string replied | handoff | discarded | queued | invalid | full | busy | unavailable | error
     */
    public function send(\stdClass $conv, string $text, callable $emit, ?string $page = null, ?int $now = null): string {
        global $DB;
        $now ??= time();
        $text = trim(clean_param($text, PARAM_TEXT));
        if ($text === '' || \core_text::strlen($text) > self::MAXLEN) {
            $emit('error', ['code' => 'invalid']);
            return 'invalid';
        }
        $conv = $DB->get_record('local_nit_asst_conv', ['id' => $conv->id], '*', MUST_EXIST);
        if ((int) $conv->messagecount >= config::LIMITS['maxconvmessages']) {
            $emit('error', ['code' => 'full']);
            return 'full';
        }

        if ($conv->status !== state::OPEN) {
            // A person has (or is about to take) the conversation: no AI reply.
            $id = conversation::add_message($conv, 'visitor', $text, [], $now);
            $emit('stored', ['id' => $id]);
            $emit('queued', ['status' => $conv->status]);
            $emit('done', ['messageid' => null]);
            alerts::visitor_message($conv);
            return 'queued';
        }

        if (!config::usable()) {
            $emit('error', ['code' => 'unavailable']);
            return 'unavailable';
        }
        $seconds = (int) config::LIMITS['turntimeout'] + 30;
        if (!state::begin_turn((int) $conv->id, $seconds, $now)) {
            $emit('error', ['code' => 'busy']);
            return 'busy';
        }
        try {
            $id = conversation::add_message($conv, 'visitor', $text, [], $now);
            $emit('stored', ['id' => $id]);
            return ($this->turnfactory)()->run((int) $conv->id, $emit, $page, $now);
        } finally {
            state::end_turn((int) $conv->id);
        }
    }
}

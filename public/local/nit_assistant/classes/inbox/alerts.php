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

namespace local_nit_assistant\inbox;

use local_nit_assistant\runtime\rate_limit;
use local_nit_assistant\runtime\state;

/**
 * Alerts to the academy's own staff (users with local/nit_assistant:viewinbox;
 * the site admins when nobody has it): bell, app push and email, through
 * local_nit_notifications when installed, else Moodle messaging.
 * Every alert is best-effort — a failed alert never fails the chat.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class alerts {
    /** Message provider (db/messages.php). */
    public const PROVIDER = 'staffalert';

    /** At most one "new message" alert per conversation in this many seconds. */
    public const MESSAGE_THROTTLE = 600;

    /**
     * A conversation was handed to the team.
     *
     * @param int $convid
     * @param string $reason the assistant's note for the team
     * @return void
     */
    public static function handoff(int $convid, string $reason): void {
        self::send('handoff', $convid, self::recipients(), $reason);
    }

    /**
     * A lead was captured.
     *
     * @param int $convid
     * @param \stdClass $lead
     * @return void
     */
    public static function lead(int $convid, \stdClass $lead): void {
        $detail = trim($lead->name . ' · ' . ($lead->phone ?: $lead->email) . "\n" . $lead->interest);
        self::send('lead', $convid, self::recipients(), $detail);
    }

    /**
     * The user wrote while the team has the conversation (throttled per conversation).
     *
     * @param \stdClass $conv
     * @return void
     */
    public static function visitor_message(\stdClass $conv): void {
        if (!rate_limit::hit("alert:msg:{$conv->id}", 1, self::MESSAGE_THROTTLE)['allowed']) {
            return;
        }
        $to = $conv->status === state::HUMAN && $conv->assignedto ? [(int) $conv->assignedto] : self::recipients();
        self::send('message', (int) $conv->id, $to, '');
    }

    /**
     * Who gets alerts.
     *
     * @return int[]
     */
    public static function recipients(): array {
        $users = get_users_by_capability(\context_system::instance(), 'local/nit_assistant:viewinbox', 'u.id');
        $ids = array_map('intval', array_keys($users));
        return $ids ?: array_map('intval', array_keys(get_admins()));
    }

    /**
     * Send one alert.
     *
     * @param string $kind handoff | lead | message
     * @param int $convid
     * @param int[] $userids
     * @param string $detail
     * @return void
     */
    private static function send(string $kind, int $convid, array $userids, string $detail): void {
        if (!$userids) {
            return;
        }
        $url = (new \moodle_url('/local/nit_assistant/view.php', ['id' => $convid]))->out(false);
        $text = static function (string $lang) use ($kind, $convid, $detail): array {
            $sm = get_string_manager();
            $title = $sm->get_string("alert_{$kind}", 'local_nit_assistant', $convid, $lang);
            $body = $sm->get_string("alert_{$kind}_body", 'local_nit_assistant', $convid, $lang)
                . ($detail !== '' ? "\n\n" . \core_text::substr($detail, 0, 500) : '');
            return [$title, $body];
        };
        try {
            if (class_exists('\local_nit_notifications\sender')) {
                [$ten, $ben] = $text('en');
                [$tar, $bar] = $text('ar');
                $ml = static fn(string $en, string $ar): string => "{mlang en}{$en}{mlang}{mlang ar}{$ar}{mlang}";
                \local_nit_notifications\sender::send_to_users(
                    'nit_assistant_' . $kind,
                    'general',
                    $userids,
                    $ml($ten, $tar),
                    $ml($ben, $bar),
                    0,
                    $url,
                    'local_nit_assistant/' . self::PROVIDER
                );
                return;
            }
            foreach ($userids as $uid) {
                $user = \core_user::get_user($uid);
                if (!$user || $user->deleted || $user->suspended) {
                    continue;
                }
                [$title, $body] = $text($user->lang ?: 'en');
                $msg = new \core\message\message();
                $msg->component = 'local_nit_assistant';
                $msg->name = self::PROVIDER;
                $msg->userfrom = \core_user::get_noreply_user();
                $msg->userto = $user;
                $msg->subject = $title;
                $msg->fullmessage = $body . "\n\n" . $url;
                $msg->fullmessageformat = FORMAT_PLAIN;
                $msg->fullmessagehtml = '';
                $msg->smallmessage = $title;
                $msg->notification = 1;
                $msg->contexturl = $url;
                $msg->contexturlname = get_string('inbox', 'local_nit_assistant');
                message_send($msg);
            }
        } catch (\Throwable $e) {
            debugging('local_nit_assistant: staff alert failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

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

/**
 * The mobile app's chat, on the academy's usual app API pattern
 * (`/local/nit_assistant/api.php?function=…&token=…`, the local_academy
 * envelope). Signed-in users send their wstoken; visitors send the shared
 * pre-login token plus a device key, exactly like the website's browser key.
 * Same assistant, limits and conversations as the website, on channel "app".
 * docs/apis/ai-assistant-mobile-api.md describes it for the app team.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class app_api {
    /** The app's channel. */
    public const CHANNEL = 'app';

    /**
     * Answer the request (does not return).
     *
     * @return void
     */
    public static function handle(): void {
        $api = '\local_academy\api\endpoint';
        if (!class_exists($api)) {
            http_response_code(404);
            exit;
        }
        $api::boot();
        $api::authenticate();
        $api::public_viewer(); // The shared pre-login token makes the caller a visitor (guest).
        if (($lang = optional_param('lang', '', PARAM_LANG)) !== '') {
            force_current_language($lang);
        }
        $api::run(static function (string $function) use ($api) {
            $userid = access::userid();
            if ($function === 'get_config') {
                return self::get_config($userid);
            }
            if (!in_array($function, ['send_message', 'get_messages', 'new_conversation'], true)) {
                $api::unknown();
            }
            if ($userid === null) {
                throw new \moodle_exception('err_notavailable', 'local_nit_assistant');
            }
            $key = optional_param('key', '', PARAM_ALPHANUM) ?: null;
            if ($userid === 0 && !conversation::valid_key($key)) {
                throw new \moodle_exception('err_invalidkey', 'local_nit_assistant');
            }
            switch ($function) {
                case 'send_message':
                    $api::require_post();
                    return self::send_message(
                        $userid,
                        $key,
                        required_param('message', PARAM_RAW),
                        optional_param('stream', 0, PARAM_BOOL)
                    );
                case 'get_messages':
                    return self::get_messages($userid, $key, optional_param('after', 0, PARAM_INT));
                case 'new_conversation':
                    $api::require_post();
                    return ['closed' => conversation::close($userid, $key, self::CHANNEL)];
                default:
                    $api::unknown();
            }
            return null;
        });
    }

    /**
     * Whether the chat is open to this caller, and what to show first.
     *
     * @param int|null $userid null = may not chat
     * @return array
     */
    public static function get_config(?int $userid): array {
        $lang = conversation::lang(current_language());
        return [
            'available' => $userid !== null && config::usable(),
            'signed_in' => (bool) $userid,
            'mode' => $userid ? access::mode($userid) : 'sales',
            'greeting' => config::greeting($lang),
            'max_length' => service::MAXLEN,
            'needs_key' => $userid === 0,
        ];
    }

    /**
     * Send one message: the whole reply as JSON, or — with $stream — the same server-sent
     * events as the website (the call then never returns).
     *
     * @param int $userid 0 = visitor
     * @param string|null $key the device key (visitors)
     * @param string $message
     * @param bool $stream
     * @return array
     */
    public static function send_message(int $userid, ?string $key, string $message, bool $stream = false): array {
        $out = ['outcome' => '', 'reply' => '', 'actions' => [], 'status' => '', 'error' => '', 'message_id' => 0,
            'user_message_id' => 0];
        $sender = $userid > 0 ? 'u' . $userid : 'ip' . conversation::hash((string) getremoteaddr());
        if (!service::allow($sender)) {
            $out['outcome'] = 'error';
            $out['error'] = 'ratelimited';
            return $out;
        }
        $mode = access::mode($userid);
        $conv = access::sync_mode(conversation::find_or_create($userid, $key, [
            'channel' => self::CHANNEL, 'lang' => current_language(), 'mode' => $mode, 'ip' => getremoteaddr(), 'page' => 'app',
        ]), $mode);

        if ($stream) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache, no-store');
            header('X-Accel-Buffering: no');
            \core\session\manager::write_close();
            ignore_user_abort(true);
            \core_php_time_limit::raise(300);
            (new service())->send($conv, $message, static function (string $event, array $data): void {
                echo 'event: ' . $event . "\n" . 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
            });
            exit;
        }

        \core_php_time_limit::raise(300);
        $handoff = '';
        $emit = static function (string $event, array $data) use (&$out, &$handoff): void {
            match ($event) {
                'stored' => $out['user_message_id'] = (int) $data['id'],
                'delta' => $out['reply'] .= $data['text'],
                'action' => $out['actions'][] = ['label' => (string) $data['label'], 'url' => (string) $data['url']],
                'handoff' => [$out['status'], $handoff] = [(string) $data['status'], (string) ($data['message'] ?? '')],
                'queued' => $out['status'] = (string) $data['status'],
                'done' => $out['message_id'] = (int) ($data['messageid'] ?? 0),
                'error' => $out['error'] = (string) $data['code'],
                default => null,
            };
        };
        $out['outcome'] = (new service())->send($conv, $message, $emit);
        // A handoff without text from the model still tells the user the team will answer.
        $out['reply'] = trim($out['reply']) !== '' ? trim($out['reply']) : $handoff;
        $out['actions'] = array_values(array_unique($out['actions'], SORT_REGULAR));
        if ($out['status'] === '') {
            $out['status'] = (string) (conversation::find($userid, $key, self::CHANNEL)?->status ?? 'open');
        }
        return $out;
    }

    /**
     * The caller's conversation: status and messages after an id (team replies arrive this way).
     *
     * @param int $userid
     * @param string|null $key
     * @param int $after
     * @return array
     */
    public static function get_messages(int $userid, ?string $key, int $after): array {
        $conv = conversation::find($userid, $key, self::CHANNEL);
        return [
            'status' => $conv ? (string) $conv->status : '',
            'messages' => $conv ? conversation::messages((int) $conv->id, $after) : [],
        ];
    }
}

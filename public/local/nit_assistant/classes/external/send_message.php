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

namespace local_nit_assistant\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_nit_assistant\chat\access;
use local_nit_assistant\chat\conversation;
use local_nit_assistant\chat\service;

/**
 * The mobile app's chat: send a message and get the whole reply at once (no
 * streaming). Same checks, limits and conversation as the web chat, channel "app".
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_message extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'message' => new external_value(PARAM_RAW, 'What the user wrote'),
        ]);
    }

    /**
     * Send one message.
     *
     * @param string $message
     * @return array
     */
    public static function execute(string $message): array {
        ['message' => $message] = self::validate_parameters(self::execute_parameters(), ['message' => $message]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/nit_assistant:use', $context);
        $userid = access::userid();
        if (!$userid) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('pluginname', 'local_nit_assistant'));
        }

        $out = ['outcome' => '', 'reply' => '', 'actions' => [], 'status' => '', 'error' => '', 'messageid' => 0];
        if (!service::allow('u' . $userid)) {
            $out['outcome'] = 'error';
            $out['error'] = 'ratelimited';
            return $out;
        }
        $conv = conversation::find_or_create($userid, null, [
            'channel' => 'app', 'lang' => current_language(), 'mode' => access::mode($userid), 'ip' => getremoteaddr(),
        ]);
        $out['outcome'] = (new service())->send($conv, $message, static function (string $event, array $data) use (&$out): void {
            match ($event) {
                'delta' => $out['reply'] .= $data['text'],
                'action' => $out['actions'][] = ['label' => $data['label'], 'url' => $data['url']],
                'handoff', 'queued' => $out['status'] = (string) $data['status'],
                'done' => $out['messageid'] = (int) ($data['messageid'] ?? 0),
                'error' => $out['error'] = (string) $data['code'],
                default => null,
            };
        });
        if ($out['status'] === '') {
            $out['status'] = (string) (conversation::find($userid, null, 'app')?->status ?? '');
        }
        return $out;
    }

    /**
     * Result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'outcome' => new external_value(PARAM_ALPHA, 'replied, handoff, queued, discarded, invalid, full, busy, '
                . 'unavailable or error'),
            'reply' => new external_value(PARAM_RAW, 'The assistant\'s reply (plain text with light markdown)'),
            'actions' => new external_multiple_structure(new external_single_structure([
                'label' => new external_value(PARAM_TEXT, 'Button text'),
                'url' => new external_value(PARAM_URL, 'Link'),
            ])),
            'status' => new external_value(PARAM_ALPHAEXT, 'Conversation status: open, waiting_human, human or closed'),
            'error' => new external_value(PARAM_ALPHAEXT, 'Error code when the message was refused'),
            'messageid' => new external_value(PARAM_INT, 'Id of the stored reply, 0 if none'),
        ]);
    }
}

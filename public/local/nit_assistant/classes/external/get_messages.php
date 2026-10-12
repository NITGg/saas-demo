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
use local_nit_assistant\config;

/**
 * The mobile app's chat history (and team replies, by polling with "after"),
 * plus the greeting to show first.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_messages extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'after' => new external_value(PARAM_INT, 'Only messages after this id', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * The caller's messages.
     *
     * @param int $after
     * @return array
     */
    public static function execute(int $after = 0): array {
        ['after' => $after] = self::validate_parameters(self::execute_parameters(), ['after' => $after]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/nit_assistant:use', $context);
        $userid = access::userid();
        if (!$userid) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('pluginname', 'local_nit_assistant'));
        }
        $conv = conversation::find($userid, null, 'app');
        return [
            'available' => config::usable(),
            'greeting' => config::greeting(conversation::lang(current_language())),
            'status' => $conv ? $conv->status : '',
            'messages' => $conv ? conversation::messages((int) $conv->id, $after) : [],
        ];
    }

    /**
     * Result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'available' => new external_value(PARAM_BOOL, 'Is the assistant on'),
            'greeting' => new external_value(PARAM_RAW, 'First message to show'),
            'status' => new external_value(PARAM_ALPHAEXT, 'open, waiting_human, human, or empty when no chat yet'),
            'messages' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Message id'),
                'role' => new external_value(PARAM_ALPHA, 'visitor (the user), assistant or staff'),
                'text' => new external_value(PARAM_RAW, 'Text'),
                'time' => new external_value(PARAM_INT, 'Unix time'),
            ])),
        ]);
    }
}

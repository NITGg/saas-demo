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

/**
 * Web service functions (the mobile app's chat).
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_nit_assistant_send_message' => [
        'classname'    => 'local_nit_assistant\external\send_message',
        'description'  => 'Send a message to the AI assistant and get its reply.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/nit_assistant:use',
        'services'     => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_nit_assistant_get_messages' => [
        'classname'    => 'local_nit_assistant\external\get_messages',
        'description'  => 'The user\'s AI assistant chat (and team replies after a message id).',
        'type'         => 'read',
        'ajax'         => false,
        'capabilities' => 'local/nit_assistant:use',
        'services'     => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];

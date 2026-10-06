<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Message providers for local_nit_notifications.
 *
 * - announcement: the notification itself — the bell and the app push. Moodle never
 *   emails it on its own (email is disallowed here), so a user who turned email on
 *   does not get an email for every notification.
 * - announcement_email: only a preference row ("notifications from the administration
 *   by email"). The user turns email on here; they are emailed when BOTH this is on
 *   AND the sender ticked "also send by email" (\local_nit_notifications\sender). It is
 *   never passed to message_send() itself.
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'announcement' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_DISALLOWED,
            'airnotifier' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
    'announcement_email' => [
        'defaults' => [
            'popup' => MESSAGE_DISALLOWED,
            'email' => MESSAGE_PERMITTED,
            'airnotifier' => MESSAGE_DISALLOWED,
        ],
    ],
];

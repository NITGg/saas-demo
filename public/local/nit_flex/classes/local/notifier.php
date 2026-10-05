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

namespace local_nit_flex\local;

/**
 * Messages sent by local_nit_flex.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notifier {

    /**
     * Tell a student their package ends soon and still holds Flex. Never throws: a failed
     * message must not stop the expiry task.
     *
     * @param \stdClass $purchase nit_package_purchase row
     * @return void
     */
    public static function expiry_reminder(\stdClass $purchase): void {
        global $DB;
        try {
            $user = \core_user::get_user((int) $purchase->userid);
            if (!$user || $user->deleted || $user->suspended) {
                return;
            }
            $name = (string) $DB->get_field('nit_package', 'name', ['id' => $purchase->packageid]);
            $a = (object) [
                'package' => format_string($name),
                'flex' => (int) $purchase->remaining_flex,
                'date' => userdate((int) $purchase->expires_at, get_string('strftimedatefullshort', 'langconfig')),
            ];
            $url = new \moodle_url('/local/nit_lessons/student.php', ['tab' => 'book']);

            $message = new \core\message\message();
            $message->component = 'local_nit_flex';
            $message->name = 'expiry';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $user;
            $message->subject = get_string('msg_expiry_subject', 'local_nit_flex', $a);
            $message->fullmessage = get_string('msg_expiry_body', 'local_nit_flex', $a);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->smallmessage = $message->subject;
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = get_string('booklesson', 'local_nit_flex');
            message_send($message);
        } catch (\Throwable $e) {
            debugging('local_nit_flex: expiry reminder failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

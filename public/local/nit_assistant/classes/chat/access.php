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

/**
 * Who may chat, and as what. Decided by the server on every request — never
 * by the browser or the model.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /**
     * The chatting user's id: 0 for a visitor (or the guest account), null when they may not chat.
     *
     * @return int|null
     */
    public static function userid(): ?int {
        global $USER;
        if (!get_config('local_nit_assistant', 'enabled')) {
            return null;
        }
        if (!isloggedin() || isguestuser()) {
            return \local_nit_assistant\config::visitors() ? 0 : null;
        }
        return has_capability('local/nit_assistant:use', \context_system::instance()) ? (int) $USER->id : null;
    }

    /**
     * The assistant's mode for a user, decided on every message: "student" for a
     * signed-in learner (a student role in any course, an active subscription or
     * Flex package), otherwise "sales". The teacher mode arrives in phase 3.
     *
     * @param int $userid
     * @return string sales | student | teacher
     */
    public static function mode(int $userid): string {
        global $DB;
        if ($userid <= 0 || isguestuser($userid)) {
            return 'sales';
        }
        $student = $DB->record_exists_sql(
            "SELECT 1
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE ra.userid = :userid AND r.archetype = 'student'",
            ['userid' => $userid]
        );
        if (!$student && class_exists('\local_nit_subscriptions\subscription_purchase_manager')) {
            $student = \local_nit_subscriptions\subscription_purchase_manager::get_active_subscription($userid) !== null;
        }
        if (!$student && class_exists('\local_nit_flex\api\purchase')) {
            try {
                $student = \local_nit_flex\api\purchase::active($userid) !== null;
            } catch (\Throwable $e) {
                $student = false;
            }
        }
        return $student ? 'student' : 'sales';
    }

    /**
     * Keep a conversation's mode in step with the user (e.g. a visitor who just
     * bought a plan is helped as a student from the next message on).
     *
     * @param \stdClass $conv
     * @param string $mode
     * @return \stdClass the conversation
     */
    public static function sync_mode(\stdClass $conv, string $mode): \stdClass {
        global $DB;
        if ($conv->mode !== $mode) {
            $DB->set_field('local_nit_asst_conv', 'mode', $mode, ['id' => $conv->id]);
            $conv->mode = $mode;
        }
        return $conv;
    }
}

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
     * The assistant's mode for a user. Phase 1: everyone is helped as a prospective
     * student; student and teacher modes arrive in phases 2 and 3.
     *
     * @param int $userid
     * @return string sales | student | teacher
     */
    public static function mode(int $userid): string {
        return 'sales';
    }
}

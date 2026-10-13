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

use local_nit_assistant\runtime\state;

/**
 * Small pieces shared by the staff pages.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Tabs across the staff pages.
     *
     * @param string $current inbox | leads | usage
     * @return string
     */
    public static function tabs(string $current): string {
        $ctx = \context_system::instance();
        $tabs = [new \tabobject(
            'inbox',
            new \moodle_url('/local/nit_assistant/index.php'),
            get_string('inbox', 'local_nit_assistant')
        )];
        $tabs[] = new \tabobject(
            'leads',
            new \moodle_url('/local/nit_assistant/leads.php'),
            get_string('leads', 'local_nit_assistant')
        );
        $tabs[] = new \tabobject(
            'tickets',
            new \moodle_url('/local/nit_assistant/tickets.php'),
            get_string('tickets', 'local_nit_assistant')
        );
        $tabs[] = new \tabobject(
            'followups',
            new \moodle_url('/local/nit_assistant/followups.php'),
            get_string('followups', 'local_nit_assistant')
        );
        if (has_capability('local/nit_assistant:manage', $ctx)) {
            $tabs[] = new \tabobject(
                'usage',
                new \moodle_url('/local/nit_assistant/usage.php'),
                get_string('usage', 'local_nit_assistant')
            );
            $tabs[] = new \tabobject(
                'settings',
                new \moodle_url('/admin/settings.php', ['section' => 'local_nit_assistant']),
                get_string('settings')
            );
        }
        return print_tabs([$tabs], $current, null, null, true);
    }

    /**
     * Who the conversation is with (escaped HTML).
     *
     * @param \stdClass $conv
     * @return string
     */
    public static function who(\stdClass $conv): string {
        if ((int) $conv->userid > 0) {
            $user = \core_user::get_user((int) $conv->userid);
            if ($user && !$user->deleted) {
                return s(fullname($user));
            }
        }
        $name = !empty($conv->leadname) ? $conv->leadname
            : (!empty($conv->phone) ? 'WhatsApp ' . $conv->phone : get_string('visitor', 'local_nit_assistant', (int) $conv->id));
        return s($name);
    }

    /**
     * Status badge (with the owner's name when a person has it).
     *
     * @param string $status
     * @param int $assignedto
     * @return string
     */
    public static function status_badge(string $status, int $assignedto = 0): string {
        $class = [
            state::WAITING => 'bg-warning text-dark',
            state::HUMAN => 'bg-success',
            state::OPEN => 'bg-secondary',
            state::CLOSED => 'bg-light text-dark',
        ][$status] ?? 'bg-light text-dark';
        $label = get_string('status_' . $status, 'local_nit_assistant');
        if ($status === state::HUMAN && $assignedto) {
            $user = \core_user::get_user($assignedto);
            if ($user) {
                $label .= ' · ' . fullname($user);
            }
        }
        return \html_writer::span(s($label), 'badge ' . $class);
    }
}

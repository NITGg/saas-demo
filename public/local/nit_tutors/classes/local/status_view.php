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

namespace local_nit_tutors\local;

/**
 * The status banner above the teacher's application form (template local_nit_tutors/application_status).
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class status_view {

    /** Banner tone per status. */
    const TONE = [
        '' => 'info', application::DRAFT => 'info', application::PENDING => 'warning', application::CHANGES => 'warning',
        application::APPROVED => 'success', application::REJECTED => 'danger', application::SUSPENDED => 'danger',
    ];

    /**
     * Template context.
     *
     * @param int $userid
     * @return array
     */
    public static function context(int $userid): array {
        $row = application::get($userid);
        $status = $row ? (string) $row->status : '';
        $missing = in_array($status, ['', application::DRAFT, application::CHANGES, application::REJECTED], true)
            ? array_values(application::missing($userid)) : [];
        return [
            'status' => $status === '' ? 'draft' : $status,
            'tone' => self::TONE[$status] ?? 'info',
            'title' => get_string('status_' . ($status === '' ? 'draft' : $status), 'local_nit_tutors'),
            'help' => get_string('statushelp_' . ($status === '' ? 'draft' : $status), 'local_nit_tutors'),
            'note' => $row && trim((string) $row->reviewnote) !== '' && $status !== application::APPROVED
                ? s($row->reviewnote) : '',
            'hasmissing' => !empty($missing),
            'missing' => array_map(fn($m) => ['label' => $m], $missing),
            'pageurl' => $status === application::APPROVED
                ? (new \moodle_url('/local/academy/teacher.php', ['id' => $userid]))->out(false) : '',
            'badge' => $status === application::APPROVED && badge::has($userid),
        ];
    }
}

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

namespace local_nit_assistant\tools\student;

use local_nit_assistant\tools\context;
use local_nit_assistant\tools\sales\helper;
use local_nit_assistant\tools\tool;

/**
 * The signed-in student's upcoming live group classes (local_academysessions):
 * title, course, time, and the room link once it opens. Read-only — it never
 * joins (joining records attendance).
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class my_live_sessions extends tool {
    /** Sessions returned. */
    private const MAX = 8;

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'my_live_sessions';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'The user\'s upcoming live group classes (not one-to-one lessons): title, course, start time, length, '
            . 'whether it is live now, and the room link (it opens 30 minutes before the start; in the room, students '
            . 'wait until the teacher arrives).';
    }

    /**
     * {@inheritDoc}
     */
    public function modes(): array {
        return ['student'];
    }

    /**
     * {@inheritDoc}
     */
    public function schema(): array {
        return ['type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        global $DB;
        if (
            !class_exists('\local_academysessions\mobile_service')
                || (class_exists('\local_license\license') && !\local_license\license::has_feature('jitsi'))
        ) {
            return self::ok(['sessions' => [], 'note' => 'This academy has no live group classes.']);
        }
        $date = get_string('strftimedatetime', 'langconfig');
        $out = [];
        foreach (array_slice(\local_academysessions\mobile_service::student_sessions($ctx->userid), 0, self::MAX) as $s) {
            $course = $DB->get_field('course', 'fullname', ['id' => $s->courseid]);
            $item = [
                'title' => (string) ($s->title_formatted ?? $s->title),
                'course' => $course !== false ? format_string($course) : '',
                'starts' => userdate((int) $s->start_time, $date),
                'minutes' => (int) $s->duration,
                'live_now' => $s->status === 'live',
                'room_open' => (bool) $s->link_visible,
            ];
            if ($s->link_visible && !empty($s->cmid)) {
                $item['join_url'] = helper::url('/mod/jitsi/view.php', ['id' => $s->cmid]);
                helper::button($ctx, 'action_joinsession', $item['join_url'], $item['title']);
            }
            $out[] = $item;
        }
        return self::ok(['sessions' => $out]);
    }
}

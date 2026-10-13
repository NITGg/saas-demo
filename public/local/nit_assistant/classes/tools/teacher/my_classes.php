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

namespace local_nit_assistant\tools\teacher;

use local_nit_assistant\tools\context;
use local_nit_assistant\tools\sales\helper;
use local_nit_assistant\tools\tool;

/**
 * The signed-in teacher's own upcoming live group classes
 * (local_academysessions): title, course, time, invited students (a count), and
 * the room link once it opens. Read-only.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class my_classes extends tool {
    /** Classes returned. */
    private const MAX = 8;

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'my_classes';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'The teacher\'s own upcoming live group classes (not one-to-one lessons): title, course, start time, '
            . 'length, how many students are invited, whether it is live, and the room link (it opens 30 minutes '
            . 'before the start).';
    }

    /**
     * {@inheritDoc}
     */
    public function modes(): array {
        return ['teacher'];
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
            return self::ok(['classes' => [], 'note' => 'This academy has no live group classes.']);
        }
        $date = get_string('strftimedatetime', 'langconfig');
        $out = [];
        foreach (\local_academysessions\mobile_service::teacher_sessions($ctx->userid) as $s) {
            $ends = (int) $s->start_time + (int) $s->duration * MINSECS;
            if (!in_array($s->status, ['scheduled', 'live'], true) || $ends < $ctx->now) {
                continue;
            }
            $course = $DB->get_field('course', 'fullname', ['id' => $s->courseid]);
            $open = \local_academysessions\session_manager::is_link_visible($s);
            $item = [
                'title' => (string) ($s->title_formatted ?? $s->title),
                'course' => $course !== false ? format_string($course) : '',
                'starts' => userdate((int) $s->start_time, $date),
                'minutes' => (int) $s->duration,
                'students_invited' => count($s->students ?? []),
                'live_now' => $s->status === 'live',
                'room_open' => $open,
            ];
            if ($open && !empty($s->cmid)) {
                $item['room_url'] = helper::url('/mod/jitsi/view.php', ['id' => $s->cmid]);
            }
            $out[] = $item;
            if (count($out) >= self::MAX) {
                break;
            }
        }
        return self::ok(['classes' => $out]);
    }
}

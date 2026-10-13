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
 * The signed-in teacher's own booking profile (local_nit_lessons): whether
 * students can book them, subjects, weekly hours and the credits one lesson
 * costs. Changed on their profile page, not in the chat.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class my_availability extends tool {
    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'my_availability';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'The teacher\'s own booking profile: whether students can book them now, their title line, subjects, '
            . 'weekly hours (day and time), and how many Flex credits one lesson with them costs. Use it for "why '
            . 'don\'t students see me", "what hours did I set". They change it on their profile page (link given).';
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
        if (!class_exists('\local_nit_lessons\service\teacher_service')) {
            return self::ok(['note' => 'This academy has no live lessons.']);
        }
        $p = (new \local_nit_lessons\service\teacher_service())->profile($ctx->userid);
        $days = [];
        foreach ([0, 1, 2, 3, 4, 5, 6] as $d) {
            $days[$d] = get_string_manager()->get_string(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday',
                'saturday'][$d], 'calendar', null, $ctx->lang);
        }
        $hours = array_map(static fn($h): array => [
            'day' => $days[(int) $h['dayofweek'] % 7] ?? (string) $h['dayofweek'],
            'from' => (string) $h['starttime'],
            'to' => (string) $h['endtime'],
        ], $p['hours']);
        $problems = [];
        if (!$p['available']) {
            $problems[] = 'booking is switched off on the profile';
        }
        if (!$p['subjects']) {
            $problems[] = 'no subjects set';
        }
        if (!$hours) {
            $problems[] = 'no weekly hours set';
        }
        helper::button($ctx, 'action_profile', helper::url('/local/academy/profile.php'));
        return self::ok([
            'bookable' => $p['available'] && $p['subjects'] && $hours,
            'why_not_bookable' => $problems,
            'title' => helper::ml($p['headline'], $ctx->lang),
            'subjects' => array_map(static fn($s) => helper::ml((string) $s, $ctx->lang), $p['subjects']),
            'weekly_hours' => $hours,
            'credits_per_lesson' => (int) $p['lesson_flex'],
        ]);
    }
}

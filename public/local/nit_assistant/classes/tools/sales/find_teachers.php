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

namespace local_nit_assistant\tools\sales;

use local_nit_assistant\tools\context;
use local_nit_assistant\tools\tool;

/**
 * Teachers who give live lessons (local_nit_lessons), by subject, with their
 * rating and lesson cost in Flex credits. Only public profile fields — never a
 * teacher's phone or email.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class find_teachers extends tool {
    /** Most teachers returned. */
    private const MAX = 8;

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'find_teachers';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'Teachers available for live one-to-one lessons, optionally only those teaching a subject (part of '
            . 'the subject name, Arabic or English). Returns name, title line, subjects, rating, how many Flex '
            . 'credits one lesson costs, and the profile link. Lessons are paid with credits from a Flex package '
            . '(see list_packages).';
    }

    /**
     * {@inheritDoc}
     */
    public function modes(): array {
        return ['sales', 'student'];
    }

    /**
     * {@inheritDoc}
     */
    public function schema(): array {
        return [
            'type' => 'object',
            'properties' => ['subject' => ['type' => 'string', 'maxLength' => 60, 'description' => 'May be empty']],
            'required' => ['subject'],
            'additionalProperties' => false,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        if (
            !class_exists('\local_nit_lessons\service\teacher_service')
                || (helper::lib('nit_flex') && function_exists('local_nit_flex_enabled') && !local_nit_flex_enabled())
        ) {
            return self::ok(['teachers' => [], 'note' => 'This academy does not offer live lessons.']);
        }
        $rows = (new \local_nit_lessons\service\teacher_service())->browse(trim($input['subject']));
        $total = count($rows);
        $rows = array_slice($rows, 0, self::MAX);
        $ids = array_map(static fn($t) => (int) $t['id'], $rows);
        $ratings = class_exists('\local_nit_reviews\api') ? \local_nit_reviews\api::get_teacher_aggregates($ids) : [];
        $teachers = array_map(static function (array $t) use ($ratings, $ctx): array {
            $r = $ratings[(int) $t['id']] ?? null;
            return [
                'id' => (int) $t['id'],
                'name' => (string) $t['fullname'],
                'title' => helper::ml($t['headline'], $ctx->lang),
                'subjects' => array_map(static fn($s) => helper::ml((string) $s, $ctx->lang), $t['subjects']),
                'rating' => $r && $r->count ? ['avg' => round((float) $r->avg, 1), 'count' => (int) $r->count] : null,
                'credits_per_lesson' => (int) $t['lesson_flex'],
                'url' => helper::url('/local/academy/teacher.php', ['id' => $t['id']]),
            ];
        }, $rows);
        if (count($teachers) === 1) {
            helper::button($ctx, 'action_teacher', $teachers[0]['url'], $teachers[0]['name']);
        }
        return self::ok(['total' => $total, 'teachers' => $teachers]);
    }
}

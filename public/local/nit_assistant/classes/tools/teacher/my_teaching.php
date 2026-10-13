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
 * The signed-in teacher's own one-to-one lessons (local_nit_lessons): requests
 * waiting for their answer, today's and upcoming lessons, recent past lessons,
 * and the rules for starting the room and reporting an absent student.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class my_teaching extends tool {
    /** Lessons per group. */
    private const MAX = 8;

    /** What a lesson status means for the teacher. */
    private const MEANING = [
        'pending' => 'a student asked for this lesson; accept, decline or suggest another time',
        'waiting_teacher' => 'the student suggested another time; accept or refuse it',
        'waiting_student' => 'you suggested another time; waiting for the student',
        'confirmed' => 'booked; start the room at the time',
        'in_progress' => 'the room is open now',
        'completed' => 'done; it counts in your earnings',
        'student_absent' => 'the student did not come; the credit was used',
        'teacher_absent' => 'reported that you did not come; the credit was returned to the student',
        'cancelled' => 'the student cancelled',
        'cancelled_teacher' => 'you cancelled; the credit was returned',
        'rejected' => 'you declined the request',
    ];

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'my_teaching';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'The teacher\'s own one-to-one lessons: requests waiting for their answer (with the student\'s '
            . 'suggested time), today\'s and upcoming lessons (student, time, status), recent past lessons, and the '
            . 'rules (how early they can start the room, when they can mark a student absent). Use it for "what do I '
            . 'have today", "do I have new requests", "how do I start the lesson".';
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
        if (!class_exists('\local_nit_lessons\local\mobile_api')) {
            return self::ok(['note' => 'This academy has no live lessons.']);
        }
        $lessons = \local_nit_lessons\local\mobile_api::get_my_lessons($ctx->userid, 'teacher', '')['lessons'];
        $date = get_string('strftimedatetime', 'langconfig');
        $today = usergetmidnight($ctx->now);
        $groups = ['requests' => [], 'today' => [], 'upcoming' => [], 'past' => []];
        foreach ($lessons as $l) {
            $status = (string) $l['status'];
            $when = (int) $l['effective_time'];
            if (in_array($status, ['pending', 'waiting_teacher', 'waiting_student'], true)) {
                $group = 'requests';
            } else if (in_array($status, ['confirmed', 'in_progress'], true)) {
                $group = $when < $today + DAYSECS ? 'today' : 'upcoming';
            } else {
                $group = 'past';
            }
            if (count($groups[$group]) >= self::MAX) {
                continue;
            }
            $item = [
                'id' => (int) $l['id'],
                'student' => (string) $l['student_name'],
                'subject' => helper::ml((string) $l['subject'], $ctx->lang),
                'when' => userdate($when, $date),
                'minutes' => (int) $l['duration'],
                'status' => $status,
                'status_label' => (string) $l['status_label'],
                'meaning' => self::MEANING[$status] ?? '',
            ];
            if (!empty($l['note'])) {
                $item['student_note'] = helper::plain((string) $l['note'], 200);
            }
            if (!empty($l['can_join'])) {
                $item['room_url'] = helper::url('/local/nit_lessons/join.php', ['id' => $l['id']]);
            }
            $groups[$group][] = $item;
        }
        $rules = new \local_nit_lessons\service\settings_service();
        helper::button($ctx, 'action_teaching', helper::url('/local/nit_lessons/student.php', ['tab' => 'teaching']));
        return self::ok($groups + ['rules' => [
            'start_room' => 'from ' . $rules->get('start_allowed_minutes') . ' minutes before the lesson time, on the '
                . 'lesson page',
            'mark_student_absent' => 'after ' . $rules->get('absence_report_minutes') . ' minutes from the start',
            'answer_requests' => 'on the teaching page: accept, decline or suggest another time',
        ]]);
    }
}

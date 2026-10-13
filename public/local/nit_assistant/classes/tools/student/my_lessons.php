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
 * The signed-in student's own live one-to-one lessons (local_nit_lessons):
 * requests waiting for an answer, upcoming lessons with the join link once the
 * teacher has started, recent past lessons, and the academy's lesson rules.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class my_lessons extends tool {
    /** Lessons per group. */
    private const MAX = 6;

    /** What a lesson status means, for the model. */
    private const MEANING = [
        'pending' => 'requested; waiting for the teacher to accept',
        'waiting_student' => 'the teacher suggested another time; the student must accept or refuse it',
        'waiting_teacher' => 'the student suggested another time; waiting for the teacher',
        'confirmed' => 'booked; 1 credit is held',
        'in_progress' => 'the teacher started the room; the student can join now',
        'completed' => 'done; the credit was used',
        'student_absent' => 'the student did not attend; the credit was used',
        'teacher_absent' => 'the teacher did not attend; the credit was returned',
        'cancelled' => 'cancelled by the student',
        'cancelled_teacher' => 'cancelled by the teacher; the credit was returned',
        'rejected' => 'the teacher declined the request',
    ];

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'my_lessons';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'The user\'s own live one-to-one lessons: requests waiting for an answer, upcoming lessons (teacher, '
            . 'time, status and what it means, join link when the room is open), recent past lessons, and the rules '
            . '(when the room opens, the free-cancellation deadline, when a missing teacher can be reported). Use it '
            . 'for "when is my lesson", "how do I join", "why was my credit taken", cancellation questions.';
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
        if (
            !class_exists('\local_nit_lessons\local\mobile_api')
                || (helper::lib('nit_flex') && function_exists('local_nit_flex_enabled') && !local_nit_flex_enabled())
        ) {
            return self::ok(['note' => 'This academy has no live lessons.']);
        }
        $lessons = \local_nit_lessons\local\mobile_api::get_my_lessons($ctx->userid, 'student', '')['lessons'];
        $date = get_string('strftimedatetime', 'langconfig');
        $groups = ['waiting' => [], 'upcoming' => [], 'past' => []];
        foreach ($lessons as $l) {
            $status = (string) $l['status'];
            $group = in_array($status, ['pending', 'waiting_student', 'waiting_teacher'], true) ? 'waiting'
                : (in_array($status, ['confirmed', 'in_progress'], true) ? 'upcoming' : 'past');
            if (count($groups[$group]) >= self::MAX) {
                continue;
            }
            $item = [
                'id' => (int) $l['id'],
                'teacher' => (string) $l['teacher_name'],
                'subject' => helper::ml((string) $l['subject'], $ctx->lang),
                'when' => userdate((int) $l['effective_time'], $date),
                'minutes' => (int) $l['duration'],
                'status' => $status,
                'status_label' => (string) $l['status_label'],
                'meaning' => self::MEANING[$status] ?? '',
            ];
            if (!empty($l['flex_state_label'])) {
                $item['credit'] = (string) $l['flex_state_label'];
            }
            if (!empty($l['can_join'])) {
                $item['join_url'] = helper::url('/local/nit_lessons/join.php', ['id' => $l['id']]);
                helper::button($ctx, 'action_joinlesson', $item['join_url'], $item['teacher']);
            }
            foreach (['reject_reason', 'cancel_reason'] as $why) {
                if (!empty($l[$why])) {
                    $item['reason'] = helper::plain((string) $l[$why], 150);
                }
            }
            $groups[$group][] = $item;
        }
        $rules = new \local_nit_lessons\service\settings_service();
        helper::button($ctx, 'action_mylessons', helper::url('/local/nit_lessons/student.php', ['tab' => 'lessons']));
        return self::ok($groups + ['rules' => [
            'room_opens' => 'when the teacher starts it (up to ' . $rules->get('start_allowed_minutes')
                . ' minutes before the time)',
            'free_cancellation' => 'cancel at least ' . $rules->get('cancel_deadline_minutes')
                . ' minutes before the start to get the credit back; later, the credit is used',
            'report_missing_teacher' => 'after ' . $rules->get('absence_report_minutes') . ' minutes from the start',
        ]]);
    }
}

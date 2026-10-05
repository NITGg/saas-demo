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

namespace local_nit_lessons\local;

use local_nit_lessons\service\teacher_service;

/**
 * Turns formatted lessons into template context: lesson cards with their action buttons,
 * and the slot data of the time pickers.
 *
 * Each button says what it needs before it can be sent: nothing, a reason (optional or
 * required), a note, or a new time. The page script opens the shared dialog accordingly
 * and posts to action.php.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lesson_view {

    /** All statuses, in filter order. */
    const STATUSES = ['pending', 'waiting_student', 'waiting_teacher', 'confirmed', 'in_progress', 'completed',
        'student_absent', 'teacher_absent', 'cancelled', 'cancelled_teacher', 'rejected'];

    /**
     * Card context for one lesson, seen by $role.
     *
     * @param array $lesson from lessons::my_lessons()
     * @param string $role student | teacher
     * @return array
     */
    public static function card(array $lesson, string $role): array {
        $fmt = get_string('strftimedaydatetime', 'langconfig');
        $other = $role === 'student' ? $lesson['teacher_name'] : $lesson['student_name'];
        $resched = self::pending_reschedule($lesson);
        $card = [
            'id' => $lesson['id'],
            'subject' => $lesson['subject'],
            'with' => get_string($role === 'student' ? 'withteacher' : 'withstudent', 'local_nit_lessons', $other),
            'when' => userdate($lesson['effective_time'], $fmt),
            'confirmed' => $lesson['confirmed_time'] > 0,
            'status' => $lesson['status'],
            'statuslabel' => get_string('lstat_' . $lesson['status'], 'local_nit_lessons'),
            'note' => (string) $lesson['note'],
            'rejectreason' => (string) $lesson['reject_reason'],
            'cancelreason' => (string) $lesson['cancel_reason'],
            'flex' => $lesson['flex_state'] !== 'none'
                ? get_string('flexstate_' . $lesson['flex_state'], 'local_nit_lessons') : '',
            'suggested' => '',
            'reschedule' => '',
            'actions' => [],
        ];

        // A time the other side suggested (waiting for this user's answer).
        $suggest = self::latest_proposal($lesson, 'suggest');
        if ($suggest && in_array($lesson['status'], ['waiting_student', 'waiting_teacher'], true)) {
            $card['suggested'] = get_string('suggestedtime', 'local_nit_lessons', userdate($suggest['proposed_time'], $fmt));
        }
        if ($resched) {
            $card['reschedule'] = get_string('reschedulepending', 'local_nit_lessons', (object) [
                'who' => get_string($resched['role'] === $role ? 'you' : ($role === 'student' ? 'theteacher' : 'thestudent'),
                    'local_nit_lessons'),
                'time' => userdate($resched['proposed_time'], $fmt),
            ]);
        }

        $needsslots = false;
        foreach ($lesson['actions'] as $action) {
            if ($action === 'view') {
                continue;
            }
            if ($action === 'respond_time_update') {
                // Only the side that did not propose answers.
                if ($resched && $resched['role'] !== $role) {
                    $card['actions'][] = self::button('respond_time_update', 'accept', 'acceptnewtime', 'none', 'primary');
                    $card['actions'][] = self::button('respond_time_update', 'reject', 'rejectnewtime', 'none', 'danger');
                }
                continue;
            }
            $button = self::action_button($action, $role, $lesson);
            if ($button) {
                $needsslots = $needsslots || $button['need'] === 'time';
                $card['actions'][] = $button;
            }
        }
        if ($lesson['status'] === 'in_progress' && $lesson['cmid'] > 0) {
            $card['joinurl'] = (new \moodle_url('/local/nit_lessons/join.php', ['id' => $lesson['id']]))->out(false);
        }
        if ($needsslots) {
            $card['slots'] = json_encode(self::slots_data((int) $lesson['teacherid'], (int) $lesson['id']));
        }
        $card['hasactions'] = !empty($card['actions']) || !empty($card['joinurl']);
        return $card;
    }

    /**
     * Status filter options.
     *
     * @param string $current
     * @return array
     */
    public static function filter(string $current): array {
        $out = [['value' => '', 'label' => get_string('allstatuses', 'local_nit_lessons'), 'selected' => $current === '']];
        foreach (self::STATUSES as $status) {
            $out[] = ['value' => $status, 'label' => get_string('lstat_' . $status, 'local_nit_lessons'),
                'selected' => $current === $status];
        }
        return $out;
    }

    /**
     * Free slots of a teacher for the picker: [{label, date, slots:[{time, label, free}]}].
     *
     * @param int $teacherid
     * @param int $exceptlessonid
     * @return array
     */
    public static function slots_data(int $teacherid, int $exceptlessonid = 0): array {
        $out = [];
        foreach ((new teacher_service())->slots($teacherid, $exceptlessonid) as $day) {
            $slots = [];
            foreach ($day['slots'] as $slot) {
                $slots[] = ['t' => $slot['time'], 'l' => userdate($slot['time'], get_string('strftimetime', 'langconfig')),
                    'f' => $slot['free'] ? 1 : 0];
            }
            $out[] = [
                'dow' => userdate($day['date'], '%a'),
                'day' => userdate($day['date'], '%d %b'),
                'slots' => $slots,
            ];
        }
        return $out;
    }

    /**
     * One action button, or null when the role cannot do it here.
     *
     * @param string $action
     * @param string $role
     * @param array $lesson
     * @return array|null
     */
    private static function action_button(string $action, string $role, array $lesson): ?array {
        $teacher = $role === 'teacher';
        switch ($action) {
            case 'accept':
                return self::button($teacher ? 'teacher_respond' : 'student_respond', 'accept', 'act_accept', 'none', 'primary');
            case 'reject':
                return self::button($teacher ? 'teacher_respond' : 'student_respond', 'reject', 'act_reject', 'reason',
                    'danger');
            case 'suggest':
                return self::button($teacher ? 'teacher_respond' : 'student_respond', 'suggest', 'act_suggest', 'time',
                    'outline');
            case 'cancel_request':
                return self::button('cancel_request', '', 'act_cancel_request', 'reason', 'danger');
            case 'cancel':
                return $teacher
                    ? self::button('cancel_as_teacher', '', 'act_cancel', 'reasonrequired', 'danger')
                    : self::button('cancel_as_student', '', 'act_cancel', 'reason', 'danger', self::cancel_warning($lesson));
            case 'report_teacher_absent':
                return self::button('report_teacher_absent', '', 'act_teacher_absent', 'none', 'danger',
                    get_string('confirm_teacher_absent', 'local_nit_lessons'));
            case 'report_student_absent':
                return self::button('report_student_absent', '', 'act_student_absent', 'none', 'danger',
                    get_string('confirm_student_absent', 'local_nit_lessons'));
            case 'request_time_update':
                return self::button('request_time_update', '', 'act_reschedule', 'time', 'outline');
            case 'start':
                return self::button('start', '', 'act_start', 'none', 'primary');
            case 'complete':
                return self::button('complete', '', 'act_complete', 'note', 'primary');
        }
        return null;
    }

    /**
     * A button descriptor.
     *
     * @param string $do action.php action
     * @param string $value sub-action (accept/reject/suggest)
     * @param string $label string key
     * @param string $need none | reason | reasonrequired | note | time
     * @param string $style primary | outline | danger
     * @param string $confirm text shown in the dialog
     * @return array
     */
    private static function button(string $do, string $value, string $label, string $need, string $style,
            string $confirm = ''): array {
        return [
            'do' => $do,
            'value' => $value,
            'label' => get_string($label, 'local_nit_lessons'),
            'need' => $need,
            'style' => $style,
            'confirm' => $confirm,
        ];
    }

    /**
     * What happens to the Flex if the student cancels now.
     *
     * @param array $lesson
     * @return string
     */
    private static function cancel_warning(array $lesson): string {
        $deadline = (int) $lesson['confirmed_time']
            - (new \local_nit_lessons\service\settings_service())->get('cancel_deadline_minutes') * MINSECS;
        return get_string(time() <= $deadline ? 'cancel_early' : 'cancel_late', 'local_nit_lessons');
    }

    /**
     * The pending reschedule request, if any.
     *
     * @param array $lesson
     * @return array|null
     */
    private static function pending_reschedule(array $lesson): ?array {
        foreach ($lesson['proposals'] ?? [] as $p) {
            if ($p['type'] === 'reschedule' && $p['status'] === 'pending') {
                return $p;
            }
        }
        return null;
    }

    /**
     * The most recent pending proposal of a type.
     *
     * @param array $lesson
     * @param string $type
     * @return array|null
     */
    private static function latest_proposal(array $lesson, string $type): ?array {
        $found = null;
        foreach ($lesson['proposals'] ?? [] as $p) {
            if ($p['type'] === $type && $p['status'] === 'pending') {
                $found = $p;
            }
        }
        return $found;
    }
}

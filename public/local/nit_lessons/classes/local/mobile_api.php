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

use local_nit_lessons\api\lessons;
use local_nit_lessons\entity\lesson;
use local_nit_lessons\exception\lesson_exception;
use local_nit_lessons\service\settings_service;
use local_nit_lessons\service\teacher_service;

/**
 * Live 1:1 lessons for the mobile app (`/local/nit_lessons/api.php`): teachers and free slots,
 * booking, every lesson action for student and teacher, joining the room, the teacher profile,
 * and the admin list / reversal / settings.
 *
 * Same rules as the web pages (it calls the same lesson engine; notifications are sent the
 * same way). Each lesson carries `actions`: what the caller can do now, and what to ask first.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mobile_api {

    /** Lesson action → API function, sub-action, what the app must ask first. */
    const ACTIONS = [
        'teacher' => [
            'accept' => ['teacher_respond_lesson', 'accept', 'none'],
            'reject' => ['teacher_respond_lesson', 'reject', 'reason'],
            'suggest' => ['teacher_respond_lesson', 'suggest', 'time'],
            'start' => ['start_lesson', '', 'none'],
            'complete' => ['complete_lesson', '', 'note'],
            'cancel' => ['cancel_lesson_teacher', '', 'reason_required'],
            'report_student_absent' => ['report_student_absent', '', 'none'],
            'request_time_update' => ['request_time_update', '', 'time'],
        ],
        'student' => [
            'accept' => ['student_respond_lesson', 'accept', 'none'],
            'reject' => ['student_respond_lesson', 'reject', 'reason'],
            'suggest' => ['student_respond_lesson', 'suggest', 'time'],
            'cancel_request' => ['cancel_lesson_request', '', 'reason'],
            'cancel' => ['cancel_lesson_student', '', 'reason'],
            'report_teacher_absent' => ['report_teacher_absent', '', 'none'],
            'request_time_update' => ['request_time_update', '', 'time'],
        ],
    ];

    /**
     * A string of this plugin.
     *
     * @param string $key
     * @param mixed $a
     * @return string
     */
    private static function str(string $key, $a = null): string {
        return get_string_manager()->string_exists($key, 'local_nit_lessons') ? get_string($key, 'local_nit_lessons', $a) : $key;
    }

    /**
     * A lesson as the app sees it: the engine's fields + labels, the actions available now and
     * the room to join.
     *
     * @param array $l from the lesson engine (with proposals)
     * @return array
     */
    public static function lesson(array $l): array {
        $role = $l['my_role'] ?? '';
        $resched = null;
        $suggested = null;
        foreach ($l['proposals'] ?? [] as $p) {
            if ($p['status'] !== 'pending') {
                continue;
            }
            if ($p['type'] === 'reschedule') {
                $resched = $p;
            } else {
                $suggested = $p;
            }
        }
        $actions = [];
        foreach ($l['actions'] ?? [] as $a) {
            if ($a === 'respond_time_update') {
                if ($resched && $resched['role'] !== $role) {
                    foreach (['accept' => 'acceptnewtime', 'reject' => 'rejectnewtime'] as $value => $label) {
                        $actions[] = ['action' => 'respond_time_update', 'function' => 'respond_time_update',
                            'value' => $value, 'needs' => 'none', 'label' => self::str($label)];
                    }
                }
                continue;
            }
            $map = self::ACTIONS[$role][$a] ?? null;
            if (!$map) {
                continue;
            }
            $label = ['report_student_absent' => 'act_student_absent', 'report_teacher_absent' => 'act_teacher_absent',
                'request_time_update' => 'act_reschedule'][$a] ?? 'act_' . $a;
            $actions[] = ['action' => $a, 'function' => $map[0], 'value' => $map[1], 'needs' => $map[2],
                'label' => self::str($label)];
        }
        $canjoin = $l['status'] === lesson::STATUS_IN_PROGRESS && (int) $l['cmid'] > 0;
        unset($l['actions'], $l['my_role']);
        return $l + [
            'my_role' => $role,
            'status_label' => self::str('lstat_' . $l['status']),
            'flex_state_label' => $l['flex_state'] !== 'none' ? self::str('flexstate_' . $l['flex_state']) : '',
            'pending_suggestion' => $suggested,
            'pending_reschedule' => $resched,
            'actions' => $actions,
            'can_join' => $canjoin,
            'join_cmid' => $canjoin ? (int) $l['cmid'] : 0,
        ];
    }

    // ── Booking ──────────────────────────────────────────────────────────────

    /**
     * Teachers who take bookings, optionally filtered by subject.
     *
     * @param int $userid
     * @param string $subject
     * @return array
     */
    public static function get_teachers(int $userid, string $subject): array {
        global $PAGE;
        $PAGE->set_context(\context_system::instance());
        $out = [];
        foreach ((new teacher_service())->browse($subject) as $t) {
            if ($t['id'] === $userid) {
                continue;
            }
            $picture = new \user_picture($t['user']);
            $picture->size = 100;
            $out[] = [
                'id' => $t['id'],
                'fullname' => $t['fullname'],
                'headline' => format_string($t['headline']),
                'subjects' => array_map(fn($s) => ['value' => $s, 'name' => format_string($s)], $t['subjects']),
                'picture_url' => \local_academy\api\endpoint::file_url($picture->get_url($PAGE)),
            ];
        }
        return ['teachers' => $out];
    }

    /**
     * Free one-hour slots of a teacher for the next two weeks.
     *
     * @param int $teacherid
     * @param int $lessonid when moving a lesson, its own time counts as free
     * @return array
     */
    public static function get_teacher_slots(int $teacherid, int $lessonid = 0): array {
        $service = new teacher_service();
        if (!$service->bookable($teacherid)) {
            throw new lesson_exception('err_teachernotbookable');
        }
        $tz = teacher_service::timezone($teacherid);
        $days = [];
        foreach ($service->slots($teacherid, $lessonid) as $day) {
            $days[] = [
                'date' => $day['date'],
                'date_label' => userdate($day['date'], get_string('strftimedaydate', 'langconfig')),
                'slots' => array_map(fn($s) => [
                    'time' => $s['time'],
                    'label' => userdate($s['time'], get_string('strftimetime', 'langconfig')),
                    'free' => $s['free'],
                ], $day['slots']),
            ];
        }
        return [
            'teacherid' => $teacherid,
            'duration' => lesson::DEFAULT_DURATION,
            'teacher_timezone' => $tz->getName(),
            'subjects' => $service->profile($teacherid)['subjects'],
            'days' => $days,
        ];
    }

    // ── Lessons ──────────────────────────────────────────────────────────────

    /**
     * The caller's lessons: as student, as teacher, or both ('').
     *
     * @param int $userid
     * @param string $role student | teacher | ''
     * @param string $status
     * @return array
     */
    public static function get_my_lessons(int $userid, string $role, string $status): array {
        if (!in_array($role, ['student', 'teacher', ''], true)) {
            $role = '';
        }
        return ['lessons' => array_map([self::class, 'lesson'], lessons::my_lessons($userid, $role, $status))];
    }

    /**
     * One lesson the caller takes part in.
     *
     * @param int $userid
     * @param int $lessonid
     * @return array
     */
    public static function get_lesson(int $userid, int $lessonid): array {
        return self::lesson(lessons::get($userid, $lessonid));
    }

    /**
     * Run one lesson action and return the lesson as the caller now sees it.
     *
     * @param int $userid
     * @param string $function API function name
     * @param int $lessonid
     * @param array $p value, time, reason
     * @return array
     */
    public static function act(int $userid, string $function, int $lessonid, array $p): array {
        $time = (int) ($p['time'] ?? 0);
        $reason = trim((string) ($p['reason'] ?? ''));
        $value = (string) ($p['value'] ?? '');
        switch ($function) {
            case 'teacher_respond_lesson':
                lessons::teacher_respond($userid, $lessonid, $value, ['suggested_time' => $time, 'reject_reason' => $reason]);
                break;
            case 'student_respond_lesson':
                lessons::student_respond($userid, $lessonid, $value, ['suggested_time' => $time, 'reject_reason' => $reason]);
                break;
            case 'start_lesson':
                lessons::start($userid, $lessonid);
                break;
            case 'complete_lesson':
                lessons::complete($userid, $lessonid, $reason !== '' ? $reason : null);
                break;
            case 'report_student_absent':
                lessons::report_student_absent($userid, $lessonid);
                break;
            case 'report_teacher_absent':
                lessons::report_teacher_absent($userid, $lessonid);
                break;
            case 'cancel_lesson_request':
                lessons::cancel_request($userid, $lessonid, $reason);
                break;
            case 'cancel_lesson_student':
                lessons::cancel_as_student($userid, $lessonid, $reason);
                break;
            case 'cancel_lesson_teacher':
                lessons::cancel_as_teacher($userid, $lessonid, $reason);
                break;
            case 'request_time_update':
                lessons::request_time_update($userid, $lessonid, $time);
                break;
            case 'respond_time_update':
                lessons::respond_time_update($userid, $lessonid, $value);
                break;
            default:
                throw new lesson_exception('err_badaction');
        }
        return self::get_lesson($userid, $lessonid);
    }

    /**
     * Book a lesson (needs an active package with Flex; nothing is reserved until accepted).
     *
     * @param int $userid
     * @param int $teacherid
     * @param string $subject
     * @param int $time unix start, one of get_teacher_slots' free slots
     * @param string $note
     * @return array
     */
    public static function request_lesson(int $userid, int $teacherid, string $subject, int $time, string $note): array {
        $l = lessons::request($userid, $teacherid, $subject, $time, $note);
        return self::get_lesson($userid, (int) $l['id']);
    }

    /**
     * The room of a started lesson: open it like any Jitsi room (mod_jitsi_get_session_info(cmid)).
     *
     * @param int $userid
     * @param int $lessonid
     * @return array
     */
    public static function get_lesson_join(int $userid, int $lessonid): array {
        $l = lessons::get($userid, $lessonid);
        if ($l['status'] !== lesson::STATUS_IN_PROGRESS || (int) $l['cmid'] <= 0) {
            throw new lesson_exception('err_roomnotready');
        }
        $record = lesson::get_record(['id' => $lessonid]);
        return [
            'lessonid' => $lessonid,
            'cmid' => (int) $l['cmid'],
            'sessionid' => (int) $record->get('sessionid'),
            'is_teacher' => $l['my_role'] === 'teacher',
            'join_url' => (new \moodle_url('/mod/jitsi/view.php', ['id' => $l['cmid']]))->out(false),
        ];
    }

    // ── Teacher profile ──────────────────────────────────────────────────────

    /**
     * The teacher's booking profile.
     *
     * @param int $userid
     * @return array
     */
    public static function get_teacher_profile(int $userid): array {
        $service = new teacher_service();
        if (!$service->is_teacher($userid)) {
            throw new lesson_exception('err_notateacher');
        }
        $profile = $service->profile($userid);
        $days = [];
        foreach (range(0, 6) as $d) {
            $days[] = ['value' => $d, 'label' => self::str('day' . $d)];
        }
        return $profile + [
            // The subjects the teacher picks from (send the value back in update_teacher_profile).
            'subjectoptions' => array_map(fn($s) => ['value' => $s, 'label' => format_string($s)],
                teacher_service::subject_options($userid)),
            'bookable' => $service->bookable($userid),
            'timezone' => teacher_service::timezone($userid)->getName(),
            'days' => $days,
        ];
    }

    /**
     * Save the teacher's booking profile.
     *
     * @param int $userid
     * @param bool $available
     * @param string $headline
     * @param array $subjects strings
     * @param array $hours [{dayofweek, starttime "HH:MM", endtime "HH:MM"}]
     * @return array
     */
    public static function update_teacher_profile(int $userid, bool $available, string $headline, array $subjects,
            array $hours): array {
        $service = new teacher_service();
        if (!$service->is_teacher($userid)) {
            throw new lesson_exception('err_notateacher');
        }
        $service->save($userid, $available, $headline, array_map('strval', $subjects), $hours);
        return self::get_teacher_profile($userid);
    }

    // ── Admin (local/nit_lessons:managesettings) ─────────────────────────────

    /**
     * Every live lesson with its earning, newest first.
     *
     * @param string $status
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function admin_list_lessons(string $status, int $page, int $perpage): array {
        global $DB;
        $where = $status !== '' ? 'WHERE l.status = :status' : '';
        $params = ['status' => $status];
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) FROM {nit_lesson} l $where", $params);
        $rows = $DB->get_records_sql("SELECT l.*, e.teacher_amount_minor, e.platform_amount_minor, e.status AS earningstatus
                                        FROM {nit_lesson} l
                                   LEFT JOIN {nit_earning} e ON e.source = 'lesson' AND e.lessonid = l.id
                                                            AND e.status = 'active'
                                       $where
                                    ORDER BY l.timecreated DESC, l.id DESC", $params, $page * $perpage, $perpage);
        $name = function(int $id): string {
            $u = \core_user::get_user($id);
            return $u ? fullname($u) : '';
        };
        $out = [];
        foreach ($rows as $r) {
            $earned = $r->earningstatus === 'active';
            $out[] = [
                'id' => (int) $r->id,
                'studentid' => (int) $r->studentid,
                'student_name' => $name((int) $r->studentid),
                'teacherid' => (int) $r->teacherid,
                'teacher_name' => $name((int) $r->teacherid),
                'subject' => format_string($r->subject),
                'status' => $r->status,
                'status_label' => self::str('lstat_' . $r->status),
                'flex_state' => $r->flex_state,
                'time' => (int) $r->confirmed_time ?: (int) $r->requested_time,
                'teacher_amount_minor' => $earned ? (int) $r->teacher_amount_minor : 0,
                'platform_amount_minor' => $earned ? (int) $r->platform_amount_minor : 0,
                'can_reverse' => $earned,
                'timecreated' => (int) $r->timecreated,
            ];
        }
        return ['total' => $total, 'page' => $page, 'perpage' => $perpage, 'lessons' => $out];
    }

    /**
     * Return a used Flex to the student and take the shares back.
     *
     * @param int $adminid
     * @param int $lessonid
     * @param string $reason
     * @return array
     */
    public static function admin_reverse_flex(int $adminid, int $lessonid, string $reason): array {
        lessons::reverse_flex($lessonid, $adminid, $reason);
        return ['lessonid' => $lessonid, 'reversed' => true];
    }

    /**
     * The live-lesson settings.
     *
     * @return array
     */
    public static function admin_get_settings(): array {
        return (new settings_service())->get_all() + ['rooms_ready' => \local_nit_lessons\room\jitsi_room::configured()];
    }

    /**
     * Update some of the live-lesson settings (unknown keys are ignored).
     *
     * @param array $data key => int
     * @return array
     */
    public static function admin_update_settings(array $data): array {
        (new settings_service())->update(array_map('intval', $data));
        return self::admin_get_settings();
    }
}

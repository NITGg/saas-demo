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

namespace local_nit_lessons\service;

use local_nit_core\base\service;
use local_nit_finance\api\wallet;
use local_nit_flex\api\flex;
use local_nit_lessons\entity\lesson;
use local_nit_lessons\entity\lesson_proposal;
use local_nit_lessons\exception\lesson_exception;
use local_nit_lessons\room\room_factory;

/**
 * The lesson lifecycle: request, time negotiation, start/complete/absence, cancel, reschedule,
 * and the admin Flex reversal. Ported from the reference lesson_manager onto the SDK.
 *
 * Flex reserve/consume/return go through the Flex facade; revenue distribution through the Finance
 * facade; the meeting room through the room seam. Every money- or Flex-moving transition runs inside
 * a single DB transaction (the money path is synchronous, never event-driven).
 *
 * Status machine:
 *   pending → (waiting_student ⇄ waiting_teacher) → confirmed → in_progress →
 *   completed | student_absent | teacher_absent | cancelled | cancelled_teacher | rejected
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lesson_service extends service {

    // ── US-LS-1-1: request ──────────────────────────────────────────────────

    /**
     * A student requests a lesson (US-LS-1-1).
     *
     * @param int $studentid
     * @param int $teacherid
     * @param string $subject
     * @param int $requestedtime unix start time
     * @param string $note required note
     * @return array lesson detail
     */
    public function request_lesson(int $studentid, int $teacherid, string $subject, int $requestedtime,
            string $note = ''): array {
        $subject = trim($subject);
        if ($subject === '') {
            throw new lesson_exception('err_subjectrequired');
        }
        $note = trim($note);
        if ($note === '') {
            throw new lesson_exception('err_noterequired');
        }
        if ($teacherid === $studentid) {
            throw new lesson_exception('err_selfbooking');
        }
        if (!\core_user::get_user($teacherid, 'id', IGNORE_MISSING)) {
            throw new lesson_exception('err_teachernotfound');
        }
        $teachers = new teacher_service();
        if (!$teachers->bookable($teacherid)) {
            throw new lesson_exception('err_teachernotbookable');
        }
        if (!$teachers->teaches($teacherid, $subject)) {
            throw new lesson_exception('err_subjectnotoffered');
        }
        if (!$teachers->within_hours($teacherid, $requestedtime)) {
            throw new lesson_exception('err_outsidehours');
        }
        // Student must have an active package with available Flex (nothing is reserved yet).
        $active = \local_nit_flex\api\purchase::active($studentid);
        if (!$active || (int) $active['remaining_flex'] < 1) {
            throw new lesson_exception('err_noflex');
        }
        $this->require_min_booking($requestedtime);
        $this->check_time_conflict($teacherid, $requestedtime, lesson::DEFAULT_DURATION);

        $lesson = new lesson(0, (object) [
            'studentid'      => $studentid,
            'teacherid'      => $teacherid,
            'subject'        => $subject,
            'status'         => lesson::STATUS_PENDING,
            'requested_time' => $requestedtime,
            'duration'       => lesson::DEFAULT_DURATION,
            'note'           => $note,
        ]);
        $lesson->create();
        return $this->format_lesson($lesson, $studentid, true);
    }

    // ── US-LS-2-1 / 2-3: teacher responds ───────────────────────────────────

    /**
     * Teacher accepts / rejects / suggests (US-LS-2-1, US-LS-2-3).
     *
     * @param int $teacherid
     * @param int $lessonid
     * @param string $action accept | reject | suggest
     * @param array $opts suggested_time, reject_reason
     * @return array
     */
    public function teacher_respond(int $teacherid, int $lessonid, string $action, array $opts = []): array {
        $lesson = $this->owned_lesson($lessonid);
        if ((int) $lesson->get('teacherid') !== $teacherid) {
            throw new lesson_exception('err_forbidden');
        }
        if (!in_array($lesson->get('status'), [lesson::STATUS_PENDING, lesson::STATUS_WAITING_TEACHER], true)) {
            throw new lesson_exception('err_badstate');
        }

        switch ($action) {
            case 'accept':
                $time = ($lesson->get('status') === lesson::STATUS_PENDING)
                    ? (int) $lesson->get('requested_time')
                    : $this->latest_pending_proposal_time($lessonid);
                $this->check_time_conflict($teacherid, $time, (int) $lesson->get('duration'), $lessonid);
                return $this->confirm_lesson($lesson, $time, $teacherid);

            case 'reject':
                $this->supersede_pending_proposals($lessonid);
                return $this->transition($lesson, lesson::STATUS_REJECTED, $teacherid,
                    ['reject_reason' => trim($opts['reject_reason'] ?? '') ?: null]);

            case 'suggest':
                if ($lesson->get('status') !== lesson::STATUS_PENDING) {
                    throw new lesson_exception('err_badstate');
                }
                $time = $this->required_future_time((int) ($opts['suggested_time'] ?? 0));
                $this->check_time_conflict($teacherid, $time, (int) $lesson->get('duration'), $lessonid);
                $this->add_proposal($lessonid, $teacherid, 'teacher', $time, lesson_proposal::TYPE_SUGGEST);
                return $this->transition($lesson, lesson::STATUS_WAITING_STUDENT, $teacherid);

            default:
                throw new lesson_exception('err_badaction');
        }
    }

    // ── US-LS-2-2: student responds ─────────────────────────────────────────

    /**
     * Student accepts / rejects / suggests a time (US-LS-2-2).
     *
     * @param int $studentid
     * @param int $lessonid
     * @param string $action accept | reject | suggest
     * @param array $opts suggested_time, reject_reason
     * @return array
     */
    public function student_respond(int $studentid, int $lessonid, string $action, array $opts = []): array {
        $lesson = $this->owned_lesson($lessonid);
        if ((int) $lesson->get('studentid') !== $studentid) {
            throw new lesson_exception('err_forbidden');
        }
        if ($lesson->get('status') !== lesson::STATUS_WAITING_STUDENT) {
            throw new lesson_exception('err_badstate');
        }

        switch ($action) {
            case 'accept':
                $time = $this->latest_pending_proposal_time($lessonid);
                $this->check_time_conflict((int) $lesson->get('teacherid'), $time,
                    (int) $lesson->get('duration'), $lessonid);
                return $this->confirm_lesson($lesson, $time, $studentid);

            case 'reject':
                $this->supersede_pending_proposals($lessonid);
                return $this->transition($lesson, lesson::STATUS_CANCELLED, $studentid,
                    ['cancel_reason' => trim($opts['reject_reason'] ?? 'Student rejected the suggested time')]);

            case 'suggest':
                $time = $this->required_future_time((int) ($opts['suggested_time'] ?? 0));
                $this->check_time_conflict((int) $lesson->get('teacherid'), $time,
                    (int) $lesson->get('duration'), $lessonid);
                $this->add_proposal($lessonid, $studentid, 'student', $time, lesson_proposal::TYPE_SUGGEST);
                return $this->transition($lesson, lesson::STATUS_WAITING_TEACHER, $studentid);

            default:
                throw new lesson_exception('err_badaction');
        }
    }

    // ── US-LS-3-1: start ────────────────────────────────────────────────────

    /**
     * Teacher starts a confirmed lesson (US-LS-3-1).
     *
     * @param int $teacherid
     * @param int $lessonid
     * @return array
     */
    public function start_lesson(int $teacherid, int $lessonid): array {
        $lesson = $this->owned_lesson($lessonid);
        if ((int) $lesson->get('teacherid') !== $teacherid) {
            throw new lesson_exception('err_forbidden');
        }
        if ($lesson->get('status') !== lesson::STATUS_CONFIRMED) {
            throw new lesson_exception('err_badstate');
        }
        $allowed = (int) $lesson->get('confirmed_time') - $this->setting('start_allowed_minutes') * MINSECS;
        if (time() < $allowed) {
            throw new lesson_exception('err_tooearlytostart');
        }
        $room = room_factory::instance()->create_for_lesson($lesson);
        return $this->transition($lesson, lesson::STATUS_IN_PROGRESS, $teacherid, [
            'actual_start' => time(),
            'sessionid'    => (int) $room->sessionid,
            'cmid'         => (int) $room->cmid,
        ]);
    }

    // ── US-LS-3-2: complete → consume + distribute ──────────────────────────

    /**
     * Teacher completes a lesson (US-LS-3-2): consume the Flex and distribute revenue.
     *
     * @param int $teacherid
     * @param int $lessonid
     * @param string|null $note
     * @return array
     */
    public function complete_lesson(int $teacherid, int $lessonid, ?string $note = null): array {
        global $DB;
        $lesson = $this->owned_lesson($lessonid);
        if ((int) $lesson->get('teacherid') !== $teacherid) {
            throw new lesson_exception('err_forbidden');
        }
        if (!in_array($lesson->get('status'), [lesson::STATUS_CONFIRMED, lesson::STATUS_IN_PROGRESS], true)) {
            throw new lesson_exception('err_badstate');
        }
        $this->require_complete_window($lesson);

        $studentid  = (int) $lesson->get('studentid');
        $purchaseid = (int) $lesson->get('purchaseid');

        $transaction = $DB->start_delegated_transaction();
        room_factory::instance()->end_for_lesson($lesson);
        flex::consume($studentid, $purchaseid, $lessonid, $teacherid, 'Lesson completed');
        $extra = ['actual_end' => time(), 'flex_state' => 'consumed'];
        if ($note !== null) {
            $extra['note'] = $note;
        }
        $result = $this->transition($lesson, lesson::STATUS_COMPLETED, $teacherid, $extra);
        $value = flex::value_for_purchase($purchaseid);
        wallet::distribute($lessonid, $teacherid, $studentid, $purchaseid, $value);
        $transaction->allow_commit();
        return $result;
    }

    // ── US-LS-3-3 / 3-4: absence ────────────────────────────────────────────

    /**
     * Teacher reports the student absent (US-LS-3-3): the Flex is consumed and, as the lesson
     * did not happen, its whole value goes to the platform (the teacher is not paid).
     *
     * @param int $teacherid
     * @param int $lessonid
     * @return array
     */
    public function report_student_absent(int $teacherid, int $lessonid): array {
        global $DB;
        $lesson = $this->owned_lesson($lessonid);
        if ((int) $lesson->get('teacherid') !== $teacherid) {
            throw new lesson_exception('err_forbidden');
        }
        $this->require_active_lesson($lesson);
        $this->require_absence_window($lesson);

        $transaction = $DB->start_delegated_transaction();
        room_factory::instance()->end_for_lesson($lesson);
        flex::consume((int) $lesson->get('studentid'), (int) $lesson->get('purchaseid'), $lessonid,
            $teacherid, 'Student absent');
        $result = $this->transition($lesson, lesson::STATUS_STUDENT_ABSENT, $teacherid,
            ['flex_state' => 'consumed']);
        wallet::distribute($lessonid, $teacherid, (int) $lesson->get('studentid'),
            (int) $lesson->get('purchaseid'), flex::value_for_purchase((int) $lesson->get('purchaseid')), false);
        $transaction->allow_commit();
        return $result;
    }

    /**
     * Student reports the teacher absent (US-LS-3-4): the Flex is returned.
     *
     * @param int $studentid
     * @param int $lessonid
     * @return array
     */
    public function report_teacher_absent(int $studentid, int $lessonid): array {
        global $DB;
        $lesson = $this->owned_lesson($lessonid);
        if ((int) $lesson->get('studentid') !== $studentid) {
            throw new lesson_exception('err_forbidden');
        }
        $this->require_active_lesson($lesson);
        $this->require_absence_window($lesson);

        $transaction = $DB->start_delegated_transaction();
        room_factory::instance()->end_for_lesson($lesson);
        flex::return_flex($studentid, (int) $lesson->get('purchaseid'), $lessonid, $studentid, 'Teacher absent');
        $result = $this->transition($lesson, lesson::STATUS_TEACHER_ABSENT, $studentid,
            ['flex_state' => 'returned']);
        $transaction->allow_commit();
        return $result;
    }

    // ── US-ST-2-2 / US-LS-4-1 / 4-2: cancellations ─────────────────────────

    /**
     * Student withdraws an un-confirmed request (no Flex reserved yet).
     *
     * @param int $studentid
     * @param int $lessonid
     * @param string $reason
     * @return array
     */
    public function cancel_request_as_student(int $studentid, int $lessonid, string $reason = ''): array {
        $lesson = $this->owned_lesson($lessonid);
        if ((int) $lesson->get('studentid') !== $studentid) {
            throw new lesson_exception('err_forbidden');
        }
        $pre = [lesson::STATUS_PENDING, lesson::STATUS_WAITING_STUDENT, lesson::STATUS_WAITING_TEACHER];
        if (!in_array($lesson->get('status'), $pre, true)) {
            throw new lesson_exception('err_badstate');
        }
        $this->supersede_pending_proposals($lessonid);
        return $this->transition($lesson, lesson::STATUS_CANCELLED, $studentid,
            ['cancel_reason' => trim($reason) !== '' ? trim($reason) : 'Request withdrawn by student']);
    }

    /**
     * Student cancels a confirmed lesson (US-LS-4-1): early returns the Flex, late consumes it.
     *
     * @param int $studentid
     * @param int $lessonid
     * @param string $reason
     * @return array
     */
    public function cancel_as_student(int $studentid, int $lessonid, string $reason = ''): array {
        global $DB;
        $lesson = $this->owned_lesson($lessonid);
        if ((int) $lesson->get('studentid') !== $studentid) {
            throw new lesson_exception('err_forbidden');
        }
        if ($lesson->get('status') !== lesson::STATUS_CONFIRMED) {
            throw new lesson_exception('err_badstate');
        }
        $deadline = (int) $lesson->get('confirmed_time') - $this->setting('cancel_deadline_minutes') * MINSECS;
        $early = time() <= $deadline;
        $purchaseid = (int) $lesson->get('purchaseid');

        $transaction = $DB->start_delegated_transaction();
        if ($early) {
            flex::return_flex($studentid, $purchaseid, $lessonid, $studentid, 'Student cancelled (early)');
            $flexstate = 'returned';
        } else {
            flex::consume($studentid, $purchaseid, $lessonid, $studentid, 'Student cancelled (late)');
            $flexstate = 'consumed';
        }
        $result = $this->transition($lesson, lesson::STATUS_CANCELLED, $studentid,
            ['cancel_reason' => trim($reason) ?: null, 'flex_state' => $flexstate]);
        if (!$early) {
            // A late cancel uses the Flex, but no lesson happened: the platform keeps its value.
            wallet::distribute($lessonid, (int) $lesson->get('teacherid'), $studentid, $purchaseid,
                flex::value_for_purchase($purchaseid), false);
        }
        $transaction->allow_commit();
        return $result;
    }

    /**
     * Teacher cancels a confirmed lesson (US-LS-4-2): the Flex is returned.
     *
     * @param int $teacherid
     * @param int $lessonid
     * @param string $reason required
     * @return array
     */
    public function cancel_as_teacher(int $teacherid, int $lessonid, string $reason = ''): array {
        global $DB;
        $lesson = $this->owned_lesson($lessonid);
        if ((int) $lesson->get('teacherid') !== $teacherid) {
            throw new lesson_exception('err_forbidden');
        }
        if ($lesson->get('status') !== lesson::STATUS_CONFIRMED) {
            throw new lesson_exception('err_badstate');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new lesson_exception('err_reasonrequired');
        }
        $transaction = $DB->start_delegated_transaction();
        flex::return_flex((int) $lesson->get('studentid'), (int) $lesson->get('purchaseid'),
            $lessonid, $teacherid, 'Teacher cancelled');
        $result = $this->transition($lesson, lesson::STATUS_CANCELLED_TEACHER, $teacherid,
            ['cancel_reason' => $reason, 'flex_state' => 'returned']);
        $transaction->allow_commit();
        return $result;
    }

    // ── US-LS-5-1 / 5-2: time update ────────────────────────────────────────

    /**
     * Request a time update on a confirmed lesson (US-LS-5-1).
     *
     * @param int $userid
     * @param int $lessonid
     * @param int $proposedtime
     * @return array
     */
    public function request_time_update(int $userid, int $lessonid, int $proposedtime): array {
        $lesson = $this->owned_lesson($lessonid);
        $role = $this->participant_role($lesson, $userid);
        if ($lesson->get('status') !== lesson::STATUS_CONFIRMED) {
            throw new lesson_exception('err_badstate');
        }
        $deadline = (int) $lesson->get('confirmed_time') - $this->setting('update_deadline_minutes') * MINSECS;
        if (time() > $deadline) {
            throw new lesson_exception('err_updatedeadline');
        }
        $time = $this->required_future_time($proposedtime);
        $this->check_time_conflict((int) $lesson->get('teacherid'), $time, (int) $lesson->get('duration'), $lessonid);
        if ($this->has_pending_reschedule($lessonid)) {
            throw new lesson_exception('err_updatepending');
        }
        $this->add_proposal($lessonid, $userid, $role, $time, lesson_proposal::TYPE_RESCHEDULE);
        return $this->format_lesson($lesson, $userid, true);
    }

    /**
     * Respond to a pending time-update request (US-LS-5-2).
     *
     * @param int $userid
     * @param int $lessonid
     * @param string $action accept | reject
     * @return array
     */
    public function respond_time_update(int $userid, int $lessonid, string $action): array {
        $lesson = $this->owned_lesson($lessonid);
        $this->participant_role($lesson, $userid);
        if ($lesson->get('status') !== lesson::STATUS_CONFIRMED) {
            throw new lesson_exception('err_badstate');
        }
        $proposal = lesson_proposal::get_record([
            'lessonid' => $lessonid, 'type' => lesson_proposal::TYPE_RESCHEDULE,
            'status' => lesson_proposal::STATUS_PENDING,
        ]);
        if (!$proposal) {
            throw new lesson_exception('err_noupdaterequest');
        }
        if ((int) $proposal->get('proposedby') === $userid) {
            throw new lesson_exception('err_forbidden');
        }

        if ($action === 'accept') {
            $this->check_time_conflict((int) $lesson->get('teacherid'),
                (int) $proposal->get('proposed_time'), (int) $lesson->get('duration'), $lessonid);
            $proposal->set('status', lesson_proposal::STATUS_ACCEPTED);
            $proposal->update();
            return $this->transition($lesson, lesson::STATUS_CONFIRMED, $userid,
                ['confirmed_time' => (int) $proposal->get('proposed_time')]);
        }
        if ($action === 'reject') {
            $proposal->set('status', lesson_proposal::STATUS_REJECTED);
            $proposal->update();
            return $this->format_lesson($lesson, $userid, true);
        }
        throw new lesson_exception('err_badaction');
    }

    // ── US-FN-1-5: admin reverses a distributed Flex ────────────────────────

    /**
     * Admin returns a consumed + distributed Flex to the student and reverses the earning
     * (US-FN-1-5). Orchestrates Finance + Flex + the lesson in one transaction.
     *
     * @param int $lessonid
     * @param int $adminid
     * @param string $reason required
     * @return array
     */
    public function reverse_flex(int $lessonid, int $adminid, string $reason): array {
        global $DB;
        $reason = trim($reason);
        if ($reason === '') {
            throw new lesson_exception('err_reasonrequired');
        }
        $lesson = $this->owned_lesson($lessonid);

        $transaction = $DB->start_delegated_transaction();
        // Finance validates there is an active earning to reverse, then flips it.
        wallet::reverse_earning($lessonid, $adminid, $reason);
        flex::reverse_consumed((int) $lesson->get('studentid'), (int) $lesson->get('purchaseid'),
            $lessonid, $adminid, 'Admin reversal: ' . $reason);
        $result = $this->transition($lesson, $lesson->get('status'), $adminid, ['flex_state' => 'returned']);
        $transaction->allow_commit();
        return $result;
    }

    // ── reads ───────────────────────────────────────────────────────────────

    /**
     * Lessons related to a user, ordered: open/needs-action, then upcoming, then history.
     *
     * @param int $userid
     * @param string $role student | teacher | ''
     * @param string $status optional status filter
     * @return array
     */
    public function get_my_lessons(int $userid, string $role = '', string $status = ''): array {
        global $DB;
        $params = [];
        if ($role === 'student') {
            $where = 'studentid = :uid';
            $params['uid'] = $userid;
        } else if ($role === 'teacher') {
            $where = 'teacherid = :uid';
            $params['uid'] = $userid;
        } else {
            $where = '(studentid = :uid1 OR teacherid = :uid2)';
            $params['uid1'] = $userid;
            $params['uid2'] = $userid;
        }
        if ($status !== '') {
            $where .= ' AND status = :status';
            $params['status'] = $status;
        }
        $records = $DB->get_records_select('nit_lesson', $where, $params, 'timecreated DESC');
        $out = [];
        foreach ($records as $record) {
            $out[] = $this->format_lesson(new lesson(0, $record), $userid, true);
        }
        usort($out, function ($a, $b) {
            $ra = $this->sort_rank($a['status']);
            $rb = $this->sort_rank($b['status']);
            if ($ra !== $rb) {
                return $ra - $rb;
            }
            if ($ra === 1) {
                return $a['effective_time'] - $b['effective_time'];
            }
            return $b['timecreated'] - $a['timecreated'];
        });
        return $out;
    }

    /**
     * A single lesson the user participates in, with proposals + available actions.
     *
     * @param int $userid
     * @param int $lessonid
     * @return array
     */
    public function get_lesson(int $userid, int $lessonid): array {
        $lesson = $this->owned_lesson($lessonid);
        $this->participant_role($lesson, $userid);
        return $this->format_lesson($lesson, $userid, true);
    }

    // ── internal helpers ─────────────────────────────────────────────────────

    /**
     * Confirm a lesson at a time and reserve one Flex (US-FN-1-2). Shared by both accept paths.
     *
     * @param lesson $lesson
     * @param int $time
     * @param int $actorid
     * @return array
     */
    private function confirm_lesson(lesson $lesson, int $time, int $actorid): array {
        global $DB;
        if ($time <= 0) {
            throw new lesson_exception('err_notime');
        }
        $transaction = $DB->start_delegated_transaction();
        $purchaseid = flex::reserve((int) $lesson->get('studentid'), (int) $lesson->get('id'),
            $actorid, 'Lesson confirmed');
        $this->mark_proposal_accepted((int) $lesson->get('id'), $time);
        $result = $this->transition($lesson, lesson::STATUS_CONFIRMED, $actorid, [
            'confirmed_time' => $time,
            'purchaseid'     => $purchaseid,
            'flex_state'     => 'reserved',
        ]);
        $transaction->allow_commit();
        return $result;
    }

    /**
     * Apply a status change + extra fields and return the formatted lesson.
     *
     * @param lesson $lesson
     * @param string $status
     * @param int $actorid
     * @param array $extra
     * @return array
     */
    private function transition(lesson $lesson, string $status, int $actorid, array $extra = []): array {
        $lesson->set('status', $status);
        foreach ($extra as $key => $value) {
            $lesson->set($key, $value);
        }
        $lesson->update();
        return $this->format_lesson($lesson, $actorid, true);
    }

    /**
     * Load a lesson entity or throw.
     *
     * @param int $lessonid
     * @return lesson
     */
    private function owned_lesson(int $lessonid): lesson {
        $lesson = lesson::get_record(['id' => $lessonid]);
        if (!$lesson) {
            throw new lesson_exception('err_lessonnotfound');
        }
        return $lesson;
    }

    /**
     * The lesson must be confirmed or in progress.
     *
     * @param lesson $lesson
     * @return void
     */
    private function require_active_lesson(lesson $lesson): void {
        if (!in_array($lesson->get('status'), [lesson::STATUS_CONFIRMED, lesson::STATUS_IN_PROGRESS], true)) {
            throw new lesson_exception('err_badstate');
        }
    }

    /**
     * 'student' | 'teacher', or throw if the user is not a participant.
     *
     * @param lesson $lesson
     * @param int $userid
     * @return string
     */
    private function participant_role(lesson $lesson, int $userid): string {
        if ((int) $lesson->get('studentid') === $userid) {
            return 'student';
        }
        if ((int) $lesson->get('teacherid') === $userid) {
            return 'teacher';
        }
        throw new lesson_exception('err_forbidden');
    }

    /**
     * A deadline/percentage setting value (minutes).
     *
     * @param string $key
     * @return int
     */
    private function setting(string $key): int {
        return (new settings_service())->get($key);
    }

    /**
     * Enforce the minimum booking lead time.
     *
     * @param int $time
     * @return void
     */
    private function require_min_booking(int $time): void {
        if ($time < time() + $this->setting('min_booking_minutes') * MINSECS) {
            throw new lesson_exception('err_minbooking');
        }
    }

    /**
     * A required, valid future time.
     *
     * @param int $time
     * @return int
     */
    private function required_future_time(int $time): int {
        if ($time <= 0) {
            throw new lesson_exception('err_notime');
        }
        $this->require_min_booking($time);
        return $time;
    }

    /**
     * Reject overlapping lessons for the teacher.
     *
     * @param int $teacherid
     * @param int $time
     * @param int $duration minutes
     * @param int $excludelessonid
     * @return void
     */
    private function check_time_conflict(int $teacherid, int $time, int $duration, int $excludelessonid = 0): void {
        global $DB;
        $start = $time;
        $end = $start + $duration * MINSECS;
        $sql = "SELECT id, requested_time, confirmed_time, duration
                  FROM {nit_lesson}
                 WHERE teacherid = :tid
                   AND status IN ('pending','waiting_student','waiting_teacher','confirmed','in_progress')";
        $params = ['tid' => $teacherid];
        if ($excludelessonid > 0) {
            $sql .= ' AND id <> :exid';
            $params['exid'] = $excludelessonid;
        }
        foreach ($DB->get_records_sql($sql, $params) as $l) {
            $lstart = (int) $l->confirmed_time > 0 ? (int) $l->confirmed_time : (int) $l->requested_time;
            $lend = $lstart + (int) $l->duration * MINSECS;
            if ($start < $lend && $end > $lstart) {
                throw new lesson_exception('err_timeconflict');
            }
        }
    }

    /**
     * A lesson can only be completed a configured time after it starts.
     *
     * @param lesson $lesson
     * @return void
     */
    private function require_complete_window(lesson $lesson): void {
        $wait = $this->setting('complete_allowed_minutes') * MINSECS;
        $start = (int) $lesson->get('actual_start') > 0
            ? (int) $lesson->get('actual_start') : (int) $lesson->get('confirmed_time');
        if (time() < $start + $wait) {
            throw new lesson_exception('err_completetooearly');
        }
    }

    /**
     * Enforce the absence-report wait window.
     *
     * @param lesson $lesson
     * @return void
     */
    private function require_absence_window(lesson $lesson): void {
        $wait = $this->setting('absence_report_minutes') * MINSECS;
        if (time() < (int) $lesson->get('confirmed_time') + $wait) {
            throw new lesson_exception('err_absencetooearly');
        }
    }

    /**
     * Add a proposal, superseding any earlier pending one of the same kind.
     *
     * @param int $lessonid
     * @param int $userid
     * @param string $role
     * @param int $time
     * @param string $type
     * @return void
     */
    private function add_proposal(int $lessonid, int $userid, string $role, int $time, string $type): void {
        $this->supersede_pending_proposals($lessonid, $type);
        (new lesson_proposal(0, (object) [
            'lessonid'      => $lessonid,
            'proposedby'    => $userid,
            'role'          => $role,
            'proposed_time' => $time,
            'type'          => $type,
            'status'        => lesson_proposal::STATUS_PENDING,
        ]))->create();
    }

    /**
     * Mark still-pending proposals of a kind as superseded.
     *
     * @param int $lessonid
     * @param string|null $type
     * @return void
     */
    private function supersede_pending_proposals(int $lessonid, ?string $type = null): void {
        $filters = ['lessonid' => $lessonid, 'status' => lesson_proposal::STATUS_PENDING];
        if ($type !== null) {
            $filters['type'] = $type;
        }
        foreach (lesson_proposal::get_records($filters) as $p) {
            $p->set('status', lesson_proposal::STATUS_SUPERSEDED);
            $p->update();
        }
    }

    /**
     * The most recent pending suggested time, or throw.
     *
     * @param int $lessonid
     * @return int
     */
    private function latest_pending_proposal_time(int $lessonid): int {
        $rows = lesson_proposal::get_records([
            'lessonid' => $lessonid, 'type' => lesson_proposal::TYPE_SUGGEST,
            'status' => lesson_proposal::STATUS_PENDING,
        ], 'timecreated', 'DESC');
        if (!$rows) {
            throw new lesson_exception('err_notime');
        }
        $row = reset($rows);
        return (int) $row->get('proposed_time');
    }

    /**
     * Accept the matching pending suggestion; supersede the rest.
     *
     * @param int $lessonid
     * @param int $time
     * @return void
     */
    private function mark_proposal_accepted(int $lessonid, int $time): void {
        foreach (lesson_proposal::get_records([
            'lessonid' => $lessonid, 'type' => lesson_proposal::TYPE_SUGGEST,
            'status' => lesson_proposal::STATUS_PENDING,
        ]) as $p) {
            $p->set('status', (int) $p->get('proposed_time') === $time
                ? lesson_proposal::STATUS_ACCEPTED : lesson_proposal::STATUS_SUPERSEDED);
            $p->update();
        }
    }

    /**
     * Whether a pending reschedule request exists.
     *
     * @param int $lessonid
     * @return bool
     */
    private function has_pending_reschedule(int $lessonid): bool {
        return lesson_proposal::record_exists_select(
            'lessonid = :lid AND type = :type AND status = :status',
            ['lid' => $lessonid, 'type' => lesson_proposal::TYPE_RESCHEDULE,
             'status' => lesson_proposal::STATUS_PENDING]);
    }

    /**
     * Ordering buckets: 0 = open/needs action, 1 = upcoming confirmed, 2 = history.
     *
     * @param string $status
     * @return int
     */
    private function sort_rank(string $status): int {
        if (in_array($status, [lesson::STATUS_PENDING, lesson::STATUS_WAITING_STUDENT,
                lesson::STATUS_WAITING_TEACHER], true)) {
            return 0;
        }
        if (in_array($status, [lesson::STATUS_CONFIRMED, lesson::STATUS_IN_PROGRESS], true)) {
            return 1;
        }
        return 2;
    }

    /**
     * Shape a lesson entity as an array for the UI/API.
     *
     * @param lesson $lesson
     * @param int $viewerid
     * @param bool $withdetail
     * @return array
     */
    private function format_lesson(lesson $lesson, int $viewerid = 0, bool $withdetail = false): array {
        $confirmed = (int) $lesson->get('confirmed_time');
        $out = [
            'id'             => (int) $lesson->get('id'),
            'studentid'      => (int) $lesson->get('studentid'),
            'teacherid'      => (int) $lesson->get('teacherid'),
            'subject'        => format_string($lesson->get('subject')),
            'status'         => $lesson->get('status'),
            'requested_time' => (int) $lesson->get('requested_time'),
            'confirmed_time' => $confirmed,
            'effective_time' => $confirmed > 0 ? $confirmed : (int) $lesson->get('requested_time'),
            'duration'       => (int) $lesson->get('duration'),
            'note'           => $lesson->get('note'),
            'reject_reason'  => $lesson->get('reject_reason'),
            'cancel_reason'  => $lesson->get('cancel_reason'),
            'flex_state'     => $lesson->get('flex_state'),
            'actual_start'   => (int) $lesson->get('actual_start'),
            'actual_end'     => (int) $lesson->get('actual_end'),
            'cmid'           => (int) $lesson->get('cmid'),
            'teacher_name'   => self::user_name((int) $lesson->get('teacherid')),
            'student_name'   => self::user_name((int) $lesson->get('studentid')),
            'timecreated'    => (int) $lesson->get('timecreated'),
            'timemodified'   => (int) $lesson->get('timemodified'),
        ];
        if ($viewerid) {
            $out['my_role'] = (int) $lesson->get('studentid') === $viewerid ? 'student'
                : ((int) $lesson->get('teacherid') === $viewerid ? 'teacher' : '');
            $out['actions'] = $this->available_actions($lesson, $out['my_role']);
        }
        if ($withdetail) {
            $props = lesson_proposal::get_records(['lessonid' => (int) $lesson->get('id')], 'timecreated', 'ASC');
            $out['proposals'] = array_map(static fn($p) => [
                'id'            => (int) $p->get('id'),
                'proposedby'    => (int) $p->get('proposedby'),
                'role'          => $p->get('role'),
                'proposed_time' => (int) $p->get('proposed_time'),
                'type'          => $p->get('type'),
                'status'        => $p->get('status'),
            ], array_values($props));
        }
        return $out;
    }

    /**
     * A user's full name, or a dash.
     *
     * @param int $userid
     * @return string
     */
    private static function user_name(int $userid): string {
        $user = $userid ? \core_user::get_user($userid) : null;
        return $user && !$user->deleted ? fullname($user) : '—';
    }

    /**
     * Actions available to a role for the lesson's current status.
     *
     * @param lesson $lesson
     * @param string $role
     * @return array
     */
    private function available_actions(lesson $lesson, string $role): array {
        $a = [];
        $status = $lesson->get('status');
        $hasreschedule = $this->has_pending_reschedule((int) $lesson->get('id'));
        if ($role === 'teacher') {
            if ($status === lesson::STATUS_PENDING) {
                $a = ['accept', 'reject', 'suggest'];
            } else if ($status === lesson::STATUS_WAITING_TEACHER) {
                $a = ['accept', 'reject'];
            } else if ($status === lesson::STATUS_CONFIRMED) {
                $a = ['start', 'cancel', 'request_time_update'];
                if ($hasreschedule) {
                    $a[] = 'respond_time_update';
                }
            } else if ($status === lesson::STATUS_IN_PROGRESS) {
                $a = ['report_student_absent'];
                $wait = $this->setting('complete_allowed_minutes') * MINSECS;
                $start = (int) $lesson->get('actual_start') > 0
                    ? (int) $lesson->get('actual_start') : (int) $lesson->get('confirmed_time');
                if (time() >= $start + $wait) {
                    $a[] = 'complete';
                }
            }
        } else if ($role === 'student') {
            if ($status === lesson::STATUS_PENDING) {
                $a = ['cancel_request'];
            } else if ($status === lesson::STATUS_WAITING_STUDENT) {
                $a = ['accept', 'reject', 'suggest'];
            } else if ($status === lesson::STATUS_CONFIRMED) {
                $a = ['cancel', 'report_teacher_absent', 'request_time_update'];
                if ($hasreschedule) {
                    $a[] = 'respond_time_update';
                }
            } else if ($status === lesson::STATUS_IN_PROGRESS) {
                $a = ['report_teacher_absent'];
            }
        }
        $a[] = 'view';
        return $a;
    }
}

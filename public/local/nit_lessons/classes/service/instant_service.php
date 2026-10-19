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

use local_nit_lessons\entity\lesson;
use local_nit_lessons\exception\lesson_exception;

/**
 * Instant lessons ("Get a tutor now"): teachers go online, a student asks for a subject, every
 * free online teacher of that subject is prompted, and the first to accept starts a normal lesson
 * (type instant) at once — Flex, room, attendance, completion and payout as for any lesson.
 *
 * Presence is a heartbeat: a teacher counts as online while their page or app polls (see
 * PRESENCE_TTL). Accept, cancel and expire run under one lock per request, so a request can never
 * become two lessons.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instant_service {
    /** @var int Seconds after the last heartbeat a teacher still counts as online. */
    const PRESENCE_TTL = 60;

    /** @var int Minutes kept free after the lesson before a teacher's next booked lesson. */
    const BUFFER_MINUTES = 10;

    const SEARCHING = 'searching';
    const MATCHED = 'matched';
    const CANCELLED = 'cancelled';
    const EXPIRED = 'expired';

    const RINGING = 'ringing';
    const ACCEPTED = 'accepted';
    const DECLINED = 'declined';
    const MISSED = 'missed';
    const TAKEN = 'taken';

    /** @var teacher_service */
    private $teachers;

    /** @var settings_service */
    private $settings;

    public function __construct() {
        $this->teachers = new teacher_service();
        $this->settings = new settings_service();
    }

    /**
     * Is "Get a tutor now" switched on by the admin?
     *
     * @return bool
     */
    public function enabled(): bool {
        return $this->settings->get('instant_enabled') > 0;
    }

    // ── Teacher presence ─────────────────────────────────────────────────────

    /**
     * Go online / offline for instant lessons. Only a teacher students can book may go online.
     *
     * @param int $teacherid
     * @param bool $online
     * @param int|null $now
     * @return array presence
     */
    public function set_online(int $teacherid, bool $online, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        if ($online && (!$this->enabled() || !$this->teachers->bookable($teacherid))) {
            throw new lesson_exception('err_instantnotbookable');
        }
        $row = $DB->get_record('nit_teacher_presence', ['teacherid' => $teacherid]);
        if ($row) {
            $DB->update_record('nit_teacher_presence', (object) ['id' => $row->id, 'online' => (int) $online,
                'lastseen' => $now]);
        } else {
            $DB->insert_record('nit_teacher_presence', (object) ['teacherid' => $teacherid, 'online' => (int) $online,
                'lastseen' => $now]);
        }
        return $this->presence($teacherid, $now);
    }

    /**
     * Heartbeat of an online teacher: keeps them online and returns the prompts waiting for them.
     *
     * @param int $teacherid
     * @param int|null $now
     * @return array {online, offers}
     */
    public function heartbeat(int $teacherid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $DB->set_field('nit_teacher_presence', 'lastseen', $now, ['teacherid' => $teacherid, 'online' => 1]);
        $this->expire_due($now);
        return $this->presence($teacherid, $now) + ['offers' => $this->offers_for($teacherid, $now)];
    }

    /**
     * A teacher's presence.
     *
     * @param int $teacherid
     * @param int|null $now
     * @return array {online, busy}
     */
    public function presence(int $teacherid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $row = $DB->get_record('nit_teacher_presence', ['teacherid' => $teacherid]);
        return [
            'online' => $row && (int) $row->online === 1 && (int) $row->lastseen >= $now - self::PRESENCE_TTL,
            'busy' => $this->busy($teacherid, $now),
        ];
    }

    /**
     * Is the teacher in a lesson now, or does a booked lesson start before an instant one would end?
     *
     * @param int $teacherid
     * @param int $now
     * @return bool
     */
    private function busy(int $teacherid, int $now): bool {
        global $DB;
        $end = $now + ($this->settings->get('instant_minutes') + self::BUFFER_MINUTES) * MINSECS;
        foreach ($DB->get_records_select('nit_lesson', "teacherid = :t AND status IN ('confirmed','in_progress')",
                ['t' => $teacherid], '', 'id, status, confirmed_time, duration') as $l) {
            if ($l->status === lesson::STATUS_IN_PROGRESS) {
                return true;
            }
            $start = (int) $l->confirmed_time;
            if ($start < $end && $start + (int) $l->duration * MINSECS > $now) {
                return true;
            }
        }
        return false;
    }

    /**
     * Teachers who can take an instant lesson in this subject right now.
     *
     * @param string $subject
     * @param int $exceptuserid the student (never their own teacher)
     * @param int|null $now
     * @return int[] teacher ids
     */
    public function available_teachers(string $subject, int $exceptuserid = 0, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        if (!$this->enabled() || trim($subject) === '') {
            return [];
        }
        $ids = $DB->get_fieldset_select('nit_teacher_presence', 'teacherid', 'online = 1 AND lastseen >= :since',
            ['since' => $now - self::PRESENCE_TTL]);
        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id !== $exceptuserid && $this->teachers->bookable($id) && $this->teachers->teaches($id, $subject)
                    && !$this->busy($id, $now)) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * Subjects with at least one teacher online now, with counts (for the student's picker).
     *
     * @param int $studentid
     * @return array[] each {subject, teachers}
     */
    public function online_subjects(int $studentid): array {
        $out = [];
        foreach (teacher_service::site_subjects() as $subject) {
            $n = count($this->available_teachers($subject, $studentid));
            if ($n > 0) {
                $out[] = ['subject' => $subject, 'label' => format_string($subject), 'teachers' => $n];
            }
        }
        return $out;
    }

    // ── Student side ─────────────────────────────────────────────────────────

    /**
     * Ask for a tutor now: every free online teacher of the subject is prompted.
     *
     * @param int $studentid
     * @param string $subject
     * @param string $note
     * @param int|null $now
     * @return array request status
     */
    public function request(int $studentid, string $subject, string $note = '', ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $subject = trim($subject);
        if (!$this->enabled()) {
            throw new lesson_exception('err_instantoff');
        }
        if ($subject === '') {
            throw new lesson_exception('err_subjectrequired');
        }
        $this->expire_due($now);
        if ($DB->record_exists('nit_instant_request', ['studentid' => $studentid, 'status' => self::SEARCHING])) {
            throw new lesson_exception('err_instantopen');
        }
        $active = \local_nit_flex\api\purchase::active($studentid);
        if (!$active || (int) $active['remaining_flex'] < 1) {
            throw new lesson_exception('err_noflex');
        }
        $teachers = array_values(array_filter($this->available_teachers($subject, $studentid, $now),
            fn($t) => $this->teachers->lesson_flex($t) <= (int) $active['remaining_flex']));
        if (!$teachers) {
            throw new lesson_exception('err_noinstanttutor');
        }
        $requestid = $DB->insert_record('nit_instant_request', (object) [
            'studentid' => $studentid,
            'subject' => $subject,
            'duration' => $this->settings->get('instant_minutes'),
            'note' => trim($note) !== '' ? trim($note) : null,
            'status' => self::SEARCHING,
            'expiresat' => $now + $this->settings->get('instant_wait_seconds'),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        foreach ($teachers as $teacherid) {
            $DB->insert_record('nit_instant_offer', (object) ['requestid' => $requestid, 'teacherid' => $teacherid,
                'status' => self::RINGING, 'timecreated' => $now]);
            $this->notify_offer($teacherid, $subject);
        }
        return $this->status($studentid, $requestid, $now);
    }

    /**
     * Where a student's request stands (also expires it once its time is up).
     *
     * @param int $studentid
     * @param int $requestid
     * @param int|null $now
     * @return array {id, status, subject, secondsleft, teachers, lessonid}
     */
    public function status(int $studentid, int $requestid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $this->expire_due($now);
        $r = $DB->get_record('nit_instant_request', ['id' => $requestid, 'studentid' => $studentid]);
        if (!$r) {
            throw new lesson_exception('err_forbidden');
        }
        return [
            'id' => (int) $r->id,
            'status' => (string) $r->status,
            'subject' => (string) $r->subject,
            'secondsleft' => $r->status === self::SEARCHING ? max(0, (int) $r->expiresat - $now) : 0,
            'teachers' => $DB->count_records('nit_instant_offer', ['requestid' => $r->id, 'status' => self::RINGING]),
            'lessonid' => (int) $r->lessonid,
        ];
    }

    /**
     * The student's open request, if any.
     *
     * @param int $studentid
     * @return array|null
     */
    public function open_request(int $studentid): ?array {
        global $DB;
        $this->expire_due(time());
        $id = $DB->get_field('nit_instant_request', 'id', ['studentid' => $studentid, 'status' => self::SEARCHING]);
        return $id ? $this->status($studentid, (int) $id) : null;
    }

    /**
     * The student withdraws a request that is still searching (free: nothing was charged).
     *
     * @param int $studentid
     * @param int $requestid
     * @return array
     */
    public function cancel(int $studentid, int $requestid): array {
        global $DB;
        $this->locked($requestid, function () use ($DB, $studentid, $requestid) {
            $r = $DB->get_record('nit_instant_request', ['id' => $requestid, 'studentid' => $studentid]);
            if (!$r) {
                throw new lesson_exception('err_forbidden');
            }
            if ($r->status === self::SEARCHING) {
                $this->close($r, self::CANCELLED, self::MISSED, time());
            }
        });
        return $this->status($studentid, $requestid);
    }

    // ── Teacher side ─────────────────────────────────────────────────────────

    /**
     * Prompts waiting for a teacher.
     *
     * @param int $teacherid
     * @param int $now
     * @return array[] each {requestid, subject, note, duration, studentname, secondsleft, flex}
     */
    public function offers_for(int $teacherid, int $now): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT o.id AS offerid, r.id, r.subject, r.note, r.duration, r.expiresat, r.studentid
               FROM {nit_instant_offer} o
               JOIN {nit_instant_request} r ON r.id = o.requestid
              WHERE o.teacherid = :t AND o.status = :ringing AND r.status = :searching AND r.expiresat > :now
           ORDER BY r.timecreated", ['t' => $teacherid, 'ringing' => self::RINGING, 'searching' => self::SEARCHING,
                'now' => $now]);
        $out = [];
        foreach ($rows as $r) {
            $student = \core_user::get_user((int) $r->studentid, 'id, firstname');
            $out[] = [
                'requestid' => (int) $r->id,
                'subject' => format_string($r->subject),
                'note' => (string) $r->note,
                'duration' => (int) $r->duration,
                'studentname' => $student ? format_string($student->firstname) : '',
                'secondsleft' => max(0, (int) $r->expiresat - $now),
                'flex' => $this->teachers->lesson_flex($teacherid),
            ];
        }
        return $out;
    }

    /**
     * A teacher accepts: if the request is still open and they are still free, the lesson is created
     * and started at once, and every other prompt closes. Two teachers accepting together get one
     * lesson — the other is told it was taken.
     *
     * @param int $teacherid
     * @param int $requestid
     * @return array the started lesson
     */
    public function accept(int $teacherid, int $requestid): array {
        global $DB;
        return $this->locked($requestid, function () use ($DB, $teacherid, $requestid) {
            $now = time();
            $r = $DB->get_record('nit_instant_request', ['id' => $requestid]);
            $offer = $DB->get_record('nit_instant_offer', ['requestid' => $requestid, 'teacherid' => $teacherid]);
            if (!$r || !$offer) {
                throw new lesson_exception('err_forbidden');
            }
            if ($r->status !== self::SEARCHING || (int) $r->expiresat <= $now) {
                throw new lesson_exception('err_instanttaken');
            }
            if ($this->busy($teacherid, $now)) {
                throw new lesson_exception('err_timeconflict');
            }
            $transaction = $DB->start_delegated_transaction();
            try {
                $lesson = (new lesson_service())->start_instant((int) $r->studentid, $teacherid, (string) $r->subject,
                    (int) $r->duration, (string) $r->note);
                $this->mark_matched($r, $offer, $teacherid, (int) $lesson['id'], $now);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
            return $lesson;
        });
    }

    /**
     * Record the match: request matched, this prompt accepted, every other prompt taken.
     *
     * @param \stdClass $r
     * @param \stdClass $offer
     * @param int $teacherid
     * @param int $lessonid
     * @param int $now
     * @return void
     */
    private function mark_matched(\stdClass $r, \stdClass $offer, int $teacherid, int $lessonid, int $now): void {
        global $DB;
        $DB->update_record('nit_instant_request', (object) ['id' => $r->id, 'status' => self::MATCHED,
            'lessonid' => $lessonid, 'teacherid' => $teacherid, 'timemodified' => $now]);
        $DB->update_record('nit_instant_offer', (object) ['id' => $offer->id, 'status' => self::ACCEPTED,
            'timeanswered' => $now]);
        $DB->set_field_select('nit_instant_offer', 'status', self::TAKEN, 'requestid = :r AND status = :s',
            ['r' => $r->id, 's' => self::RINGING]);
    }

    /**
     * Prompt a teacher outside the page too: bell + app push (message provider instantoffer).
     *
     * @param int $teacherid
     * @param string $subject
     * @return void
     */
    private function notify_offer(int $teacherid, string $subject): void {
        try {
            $to = \core_user::get_user($teacherid);
            if (!$to || $to->deleted || $to->suspended) {
                return;
            }
            $message = new \core\message\message();
            $message->component = 'local_nit_lessons';
            $message->name = 'instantoffer';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $to;
            $message->subject = get_string('notif_instantoffer', 'local_nit_lessons', format_string($subject));
            $message->fullmessage = $message->subject;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->smallmessage = $message->subject;
            $message->notification = 1;
            $message->contexturl = (new \moodle_url('/local/nit_lessons/student.php', ['tab' => 'teaching']))->out(false);
            $message->contexturlname = get_string('mylessons', 'local_nit_lessons');
            message_send($message);
        } catch (\Throwable $e) {
            debugging('local_nit_lessons: instant prompt not sent: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * A teacher declines a prompt. When nobody is left to ask, the request ends early.
     *
     * @param int $teacherid
     * @param int $requestid
     * @return void
     */
    public function decline(int $teacherid, int $requestid): void {
        global $DB;
        $this->locked($requestid, function () use ($DB, $teacherid, $requestid) {
            $offer = $DB->get_record('nit_instant_offer', ['requestid' => $requestid, 'teacherid' => $teacherid,
                'status' => self::RINGING]);
            if (!$offer) {
                return;
            }
            $DB->update_record('nit_instant_offer', (object) ['id' => $offer->id, 'status' => self::DECLINED,
                'timeanswered' => time()]);
            $r = $DB->get_record('nit_instant_request', ['id' => $requestid]);
            if ($r && $r->status === self::SEARCHING
                    && !$DB->record_exists('nit_instant_offer', ['requestid' => $requestid, 'status' => self::RINGING])) {
                $this->close($r, self::EXPIRED, self::MISSED, time());
            }
        });
    }

    // ── Housekeeping ─────────────────────────────────────────────────────────

    /**
     * End requests whose time is up; their unanswered prompts become missed.
     *
     * @param int $now
     * @return int how many expired
     */
    public function expire_due(int $now): int {
        global $DB;
        $n = 0;
        foreach ($DB->get_records_select('nit_instant_request', 'status = :s AND expiresat <= :now',
                ['s' => self::SEARCHING, 'now' => $now]) as $r) {
            $this->locked((int) $r->id, function () use ($DB, $r, $now, &$n) {
                $fresh = $DB->get_record('nit_instant_request', ['id' => $r->id]);
                if ($fresh && $fresh->status === self::SEARCHING) {
                    $this->close($fresh, self::EXPIRED, self::MISSED, $now);
                    $n++;
                }
            });
        }
        return $n;
    }

    /**
     * Close a request and its ringing prompts.
     *
     * @param \stdClass $r
     * @param string $status
     * @param string $offerstatus
     * @param int $now
     * @return void
     */
    private function close(\stdClass $r, string $status, string $offerstatus, int $now): void {
        global $DB;
        $DB->update_record('nit_instant_request', (object) ['id' => $r->id, 'status' => $status, 'timemodified' => $now]);
        $DB->set_field_select('nit_instant_offer', 'status', $offerstatus, 'requestid = :r AND status = :s',
            ['r' => $r->id, 's' => self::RINGING]);
    }

    /**
     * Run under the request's lock.
     *
     * @param int $requestid
     * @param callable $fn
     * @return mixed
     */
    private function locked(int $requestid, callable $fn) {
        $factory = \core\lock\lock_config::get_lock_factory('local_nit_lessons_instant');
        $lock = $factory->get_lock('request_' . $requestid, 10);
        if (!$lock) {
            throw new lesson_exception('err_instantbusy');
        }
        try {
            return $fn();
        } finally {
            $lock->release();
        }
    }
}

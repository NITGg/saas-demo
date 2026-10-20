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
    const NOSHOW = 'noshow';

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

    /**
     * What an instant lesson with this teacher costs: their price plus instant_extra_flex.
     *
     * @param int $teacherid
     * @return int Flex
     */
    public function price(int $teacherid): int {
        return $this->teachers->lesson_flex($teacherid) + max(0, $this->settings->get('instant_extra_flex'));
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
        if ($online) {
            $this->alert_watchers($teacherid);
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
    public function request(int $studentid, string $subject, string $note = '', ?int $now = null,
            ?array $photo = null): array {
        global $DB;
        $now = $now ?? time();
        $subject = trim($subject);
        if ($photo !== null) {
            self::check_photo($photo);
        }
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
        $request = (object) [
            'studentid' => $studentid,
            'subject' => $subject,
            'duration' => $this->settings->get('instant_minutes'),
            'note' => trim($note) !== '' ? trim($note) : null,
            'status' => self::SEARCHING,
            'expiresat' => $now + $this->settings->get('instant_wait_seconds'),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        if (!$this->candidates($request, $now)) {
            throw new lesson_exception('err_noinstanttutor');
        }
        $request->id = $DB->insert_record('nit_instant_request', $request);
        if ($photo !== null) {
            $this->save_photo((int) $request->id, $studentid, $photo);
        }
        $DB->delete_records('nit_instant_watch', ['studentid' => $studentid]);
        $this->send_wave($request, $now);
        return $this->status($studentid, (int) $request->id, $now);
    }

    /** @var int Largest photo a student may attach, bytes. */
    const PHOTO_MAX_BYTES = 5 * 1024 * 1024;

    /** @var string File area of request photos (system context). */
    const PHOTO_AREA = 'instantphoto';

    /**
     * A photo of the problem must be a real image (jpg / png / webp / gif) of at most 5 MB.
     *
     * @param array $photo {name, tmp_name, size} as in $_FILES
     * @return void
     */
    public static function check_photo(array $photo): void {
        $path = (string) ($photo['tmp_name'] ?? '');
        $info = $path !== '' && is_readable($path) ? @getimagesize($path) : false;
        $types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];
        if (!$info || !in_array($info[2], $types, true) || (int) ($photo['size'] ?? filesize($path)) > self::PHOTO_MAX_BYTES) {
            throw new lesson_exception('err_instantphoto');
        }
    }

    /**
     * Store the request's photo.
     *
     * @param int $requestid
     * @param int $studentid
     * @param array $photo
     * @return void
     */
    private function save_photo(int $requestid, int $studentid, array $photo): void {
        $ext = image_type_to_extension(getimagesize($photo['tmp_name'])[2], false);
        get_file_storage()->create_file_from_pathname([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_nit_lessons',
            'filearea' => self::PHOTO_AREA,
            'itemid' => $requestid,
            'filepath' => '/',
            'filename' => 'problem.' . $ext,
            'userid' => $studentid,
        ], $photo['tmp_name']);
    }

    /**
     * URL of a request's photo ('' when none).
     *
     * @param int $requestid
     * @return string
     */
    public function photo_url(int $requestid): string {
        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'local_nit_lessons',
            self::PHOTO_AREA, $requestid, 'id', false);
        $file = reset($files);
        return $file ? \moodle_url::make_pluginfile_url($file->get_contextid(), 'local_nit_lessons', self::PHOTO_AREA,
            $requestid, '/', $file->get_filename())->out(false) : '';
    }

    /**
     * May this user see the photo of a request? Its student, a teacher it was sent to, or a manager.
     *
     * @param int $userid
     * @param int $requestid
     * @return bool
     */
    public static function can_see_photo(int $userid, int $requestid): bool {
        global $DB;
        return $DB->record_exists('nit_instant_request', ['id' => $requestid, 'studentid' => $userid])
            || $DB->record_exists('nit_instant_offer', ['requestid' => $requestid, 'teacherid' => $userid])
            || has_capability('local/nit_lessons:managesettings', \context_system::instance(), $userid);
    }

    // ── "Notify me when a tutor is online" ───────────────────────────────────

    /**
     * The student wants to hear when a tutor of this subject goes online (one alert, then forgotten;
     * forgotten anyway after a day).
     *
     * @param int $studentid
     * @param string $subject
     * @return array {watching}
     */
    public function watch(int $studentid, string $subject): array {
        global $DB;
        $subject = trim($subject);
        if ($subject === '' || !in_array($subject, teacher_service::site_subjects(), true)) {
            throw new lesson_exception('err_subjectrequired');
        }
        if (!$DB->record_exists('nit_instant_watch', ['studentid' => $studentid, 'subject' => $subject])) {
            $DB->insert_record('nit_instant_watch', (object) ['studentid' => $studentid, 'subject' => $subject,
                'timecreated' => time()]);
        }
        return ['watching' => true];
    }

    /**
     * A teacher came online: tell the students waiting for one of their subjects, once.
     *
     * @param int $teacherid
     * @return int how many students were told
     */
    private function alert_watchers(int $teacherid): int {
        global $DB;
        $n = 0;
        foreach ($DB->get_records('nit_instant_watch') as $w) {
            if ((int) $w->studentid !== $teacherid && $this->teachers->teaches($teacherid, (string) $w->subject)) {
                $DB->delete_records('nit_instant_watch', ['id' => $w->id]);
                $this->notify_student((int) $w->studentid, 'instant_tutoronline', (string) $w->subject);
                $n++;
            }
        }
        return $n;
    }

    /**
     * Daily tidy-up: forget day-old watches and delete photos of requests older than 30 days.
     *
     * @param int $now
     * @return void
     */
    public function cleanup(int $now): void {
        global $DB;
        $DB->delete_records_select('nit_instant_watch', 'timecreated < :cut', ['cut' => $now - DAYSECS]);
        $fs = get_file_storage();
        $old = $DB->get_fieldset_select('nit_instant_request', 'id', 'timecreated < :cut', ['cut' => $now - 30 * DAYSECS]);
        foreach ($old as $id) {
            $fs->delete_area_files(\context_system::instance()->id, 'local_nit_lessons', self::PHOTO_AREA, (int) $id);
        }
    }

    /**
     * Teachers who could take this request now and were not asked yet (a teacher whose prompt was
     * only closed because someone else accepted may be asked again), best first.
     *
     * @param \stdClass $r request
     * @param int $now
     * @return int[]
     */
    private function candidates(\stdClass $r, int $now): array {
        global $DB;
        $active = \local_nit_flex\api\purchase::active((int) $r->studentid);
        $flex = $active ? (int) $active['remaining_flex'] : 0;
        $asked = empty($r->id) ? [] : $DB->get_fieldset_select('nit_instant_offer', 'teacherid',
            'requestid = :r AND status <> :taken', ['r' => $r->id, 'taken' => self::TAKEN]);
        $ids = array_filter($this->available_teachers((string) $r->subject, (int) $r->studentid, $now),
            fn($t) => !in_array($t, array_map('intval', $asked), true) && $this->price($t) <= $flex);
        return $this->rank(array_values($ids), (int) $r->studentid);
    }

    /**
     * Best teachers first: the student's previous tutors, then the best rated, then those who answer
     * most of their prompts, then those who have waited longest since their last lesson.
     *
     * @param int[] $ids
     * @param int $studentid
     * @return int[]
     */
    public function rank(array $ids, int $studentid): array {
        global $DB;
        if (count($ids) < 2) {
            return $ids;
        }
        $ratings = class_exists('\local_nit_reviews\api') ? \local_nit_reviews\api::get_teacher_aggregates($ids) : [];
        $score = [];
        foreach ($ids as $id) {
            $score[$id] = [
                $DB->count_records('nit_lesson', ['studentid' => $studentid, 'teacherid' => $id,
                    'status' => lesson::STATUS_COMPLETED]) > 0 ? 1 : 0,
                round((float) ($ratings[$id]->avg ?? 0), 1),
                $this->acceptance_rate($id),
                -(int) $DB->get_field_sql("SELECT COALESCE(MAX(actual_end), 0) FROM {nit_lesson} WHERE teacherid = :t",
                    ['t' => $id]),
            ];
        }
        usort($ids, fn($a, $b) => $score[$b] <=> $score[$a]);
        return $ids;
    }

    /**
     * Share of a teacher's prompts in the last 30 days that they accepted (1.0 with no history).
     *
     * @param int $teacherid
     * @return float
     */
    public function acceptance_rate(int $teacherid): float {
        global $DB;
        [$in, $params] = $DB->get_in_or_equal([self::ACCEPTED, self::DECLINED, self::MISSED, self::NOSHOW],
            SQL_PARAMS_NAMED);
        $params += ['t' => $teacherid, 'since' => time() - 30 * DAYSECS];
        $total = $DB->count_records_select('nit_instant_offer', "teacherid = :t AND timecreated >= :since AND status $in",
            $params);
        if ($total === 0) {
            return 1.0;
        }
        $accepted = $DB->count_records_select('nit_instant_offer',
            'teacherid = :t AND timecreated >= :since AND status = :a', ['t' => $teacherid, 'since' => $params['since'],
                'a' => self::ACCEPTED]);
        return round($accepted / $total, 2);
    }

    /**
     * Ask the next teachers (up to instant_wave_size).
     *
     * @param \stdClass $r request
     * @param int $now
     * @return int how many were asked
     */
    private function send_wave(\stdClass $r, int $now): int {
        global $DB;
        $wave = array_slice($this->candidates($r, $now), 0, max(1, $this->settings->get('instant_wave_size')));
        foreach ($wave as $teacherid) {
            $DB->insert_record('nit_instant_offer', (object) ['requestid' => $r->id, 'teacherid' => $teacherid,
                'status' => self::RINGING, 'timecreated' => $now]);
            $this->notify_offer($teacherid, (string) $r->subject);
        }
        if ($wave) {
            $r->wave = (int) ($r->wave ?? 0) + 1;
            $r->wavestarted = $now;
            $DB->update_record('nit_instant_request', (object) ['id' => $r->id, 'wave' => $r->wave,
                'wavestarted' => $now, 'timemodified' => $now]);
        }
        return count($wave);
    }

    /**
     * Move a searching request on: prompts that rang their full time become missed; when nobody is
     * ringing, the next teachers are asked; when nobody is left, the request ends.
     *
     * @param \stdClass $r request (searching)
     * @param int $now
     * @return void
     */
    private function advance(\stdClass $r, int $now): void {
        global $DB;
        $DB->set_field_select('nit_instant_offer', 'status', self::MISSED,
            'requestid = :r AND status = :s AND timecreated <= :cut', ['r' => $r->id, 's' => self::RINGING,
                'cut' => $now - $this->settings->get('instant_ring_seconds')]);
        if ($DB->record_exists('nit_instant_offer', ['requestid' => $r->id, 'status' => self::RINGING])) {
            return;
        }
        if ($this->send_wave($r, $now) === 0) {
            $this->close($r, self::EXPIRED, self::MISSED, $now);
        }
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
            'subject' => format_string($r->subject), // {mlang} subjects show in the user's language.
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
            "SELECT o.id AS offerid, o.timecreated AS rangat, r.id, r.subject, r.note, r.duration, r.expiresat,
                    r.studentid
               FROM {nit_instant_offer} o
               JOIN {nit_instant_request} r ON r.id = o.requestid
              WHERE o.teacherid = :t AND o.status = :ringing AND r.status = :searching AND r.expiresat > :now
                AND o.timecreated > :ringcut
           ORDER BY r.timecreated", ['t' => $teacherid, 'ringing' => self::RINGING, 'searching' => self::SEARCHING,
                'now' => $now, 'ringcut' => $now - $this->settings->get('instant_ring_seconds')]);
        $out = [];
        foreach ($rows as $r) {
            $r->expiresat = min((int) $r->expiresat, (int) $r->rangat + $this->settings->get('instant_ring_seconds'));
            $student = \core_user::get_user((int) $r->studentid, 'id, firstname');
            $out[] = [
                'requestid' => (int) $r->id,
                'subject' => format_string($r->subject),
                'note' => (string) $r->note,
                'duration' => (int) $r->duration,
                'studentname' => $student ? format_string($student->firstname) : '',
                'secondsleft' => max(0, (int) $r->expiresat - $now),
                'flex' => $this->price($teacherid),
                'photourl' => $this->photo_url((int) $r->id),
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
            if (!$r || !$DB->record_exists('nit_instant_offer', ['requestid' => $requestid, 'teacherid' => $teacherid])) {
                throw new lesson_exception('err_forbidden');
            }
            $offer = $DB->get_record('nit_instant_offer', ['requestid' => $requestid, 'teacherid' => $teacherid,
                'status' => self::RINGING]);
            if (!$offer || $r->status !== self::SEARCHING || (int) $r->expiresat <= $now) {
                throw new lesson_exception('err_instanttaken');
            }
            if ($this->busy($teacherid, $now)) {
                throw new lesson_exception('err_timeconflict');
            }
            $transaction = $DB->start_delegated_transaction();
            try {
                $lesson = (new lesson_service())->start_instant((int) $r->studentid, $teacherid, (string) $r->subject,
                    (int) $r->duration, (string) $r->note, $this->settings->get('instant_extra_flex'));
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
            'lessonid' => $lessonid, 'teacherid' => $teacherid, 'timematched' => $now, 'timemodified' => $now]);
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
            if ($r && $r->status === self::SEARCHING) {
                $this->advance($r, time()); // Nobody left ringing: ask the next teachers, or end.
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
        $this->noshow_due($now);
        $n = 0;
        foreach ($DB->get_records('nit_instant_request', ['status' => self::SEARCHING]) as $r) {
            $this->locked((int) $r->id, function () use ($DB, $r, $now, &$n) {
                $fresh = $DB->get_record('nit_instant_request', ['id' => $r->id]);
                if (!$fresh || $fresh->status !== self::SEARCHING) {
                    return;
                }
                if ((int) $fresh->expiresat <= $now) {
                    $this->close($fresh, self::EXPIRED, self::MISSED, $now);
                } else {
                    $this->advance($fresh, $now);
                }
                if ($DB->get_field('nit_instant_request', 'status', ['id' => $r->id]) === self::EXPIRED) {
                    $n++;
                }
            });
        }
        return $n;
    }

    /**
     * Instant lessons whose teacher accepted but did not open the room within instant_noshow_minutes:
     * the lesson is cancelled with the Flex returned, the teacher's prompt is marked noshow, and the
     * request is reopened once for other teachers (the student is told either way).
     *
     * @param int $now
     * @return int how many no-shows were handled
     */
    public function noshow_due(int $now): int {
        global $DB;
        $cut = $now - $this->settings->get('instant_noshow_minutes') * MINSECS;
        $lessons = $DB->get_records_select('nit_lesson',
            "type = :type AND status = :status AND teacher_joined = 0 AND actual_start > 0 AND actual_start <= :cut",
            ['type' => lesson::TYPE_INSTANT, 'status' => lesson::STATUS_IN_PROGRESS, 'cut' => $cut]);
        $n = 0;
        foreach ($lessons as $l) {
            $r = $DB->get_record('nit_instant_request', ['lessonid' => $l->id]);
            $run = function () use ($DB, $l, $r, $now, &$n) {
                $fresh = $DB->get_record('nit_lesson', ['id' => $l->id]);
                if (!$fresh || $fresh->status !== lesson::STATUS_IN_PROGRESS || (int) $fresh->teacher_joined > 0) {
                    return;
                }
                (new lesson_service())->cancel_instant_noshow((int) $l->id);
                $n++;
                $reopened = false;
                if ($r) {
                    $DB->set_field('nit_instant_offer', 'status', self::NOSHOW,
                        ['requestid' => $r->id, 'teacherid' => $l->teacherid, 'status' => self::ACCEPTED]);
                    if ((int) $r->redispatched === 0) {
                        $r->status = self::SEARCHING;
                        $r->lessonid = 0;
                        $r->teacherid = 0;
                        $r->redispatched = 1;
                        $r->expiresat = $now + $this->settings->get('instant_wait_seconds');
                        $r->timematched = 0;
                        $r->timemodified = $now;
                        $DB->update_record('nit_instant_request', $r);
                        $reopened = $this->send_wave($r, $now) > 0;
                        if (!$reopened) {
                            $this->close($r, self::EXPIRED, self::MISSED, $now);
                        }
                    }
                }
                $this->notify_student((int) $l->studentid, $reopened ? 'instant_noshow_retry' : 'instant_noshow_refund',
                    (string) $l->subject);
            };
            if ($r) {
                $this->locked((int) $r->id, $run);
            } else {
                $run();
            }
        }
        return $n;
    }

    /**
     * Tell the student what happened to their instant lesson (bell + app push).
     *
     * @param int $studentid
     * @param string $key string key in local_nit_lessons
     * @param string $subject
     * @return void
     */
    private function notify_student(int $studentid, string $key, string $subject): void {
        try {
            $to = \core_user::get_user($studentid);
            if (!$to || $to->deleted || $to->suspended) {
                return;
            }
            $message = new \core\message\message();
            $message->component = 'local_nit_lessons';
            $message->name = 'lessonupdate';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $to;
            $message->subject = get_string($key, 'local_nit_lessons', format_string($subject));
            $message->fullmessage = $message->subject;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->smallmessage = $message->subject;
            $message->notification = 1;
            $message->contexturl = (new \moodle_url('/local/nit_lessons/student.php', ['tab' => 'book']))->out(false);
            $message->contexturlname = get_string('studenthub', 'local_nit_lessons');
            message_send($message);
        } catch (\Throwable $e) {
            debugging('local_nit_lessons: instant notice not sent: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // ── Admin live view ──────────────────────────────────────────────────────

    /**
     * What the admin live view shows: teachers online, open requests, and the last 24 hours.
     *
     * @param int|null $now
     * @return array {teachers, requests, stats, subjects}
     */
    public function live_view(?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $this->expire_due($now);
        $teachers = [];
        foreach ($DB->get_records_select('nit_teacher_presence', 'online = 1 AND lastseen >= :since',
                ['since' => $now - self::PRESENCE_TTL], 'lastseen DESC') as $p) {
            $u = \core_user::get_user((int) $p->teacherid);
            if (!$u) {
                continue;
            }
            $teachers[] = [
                'name' => fullname($u),
                'subjects' => implode(', ', array_map('format_string', $this->teachers->profile((int) $p->teacherid)['subjects'])),
                'busy' => $this->busy((int) $p->teacherid, $now),
                'lastseen' => $now - (int) $p->lastseen,
                'acceptance' => (int) round($this->acceptance_rate((int) $p->teacherid) * 100),
            ];
        }
        $requests = [];
        foreach ($DB->get_records('nit_instant_request', ['status' => self::SEARCHING], 'timecreated') as $r) {
            $u = \core_user::get_user((int) $r->studentid);
            $requests[] = [
                'student' => $u ? fullname($u) : '',
                'subject' => format_string($r->subject),
                'waiting' => $now - (int) $r->timecreated,
                'wave' => (int) $r->wave,
                'ringing' => $DB->count_records('nit_instant_offer', ['requestid' => $r->id, 'status' => self::RINGING]),
                'secondsleft' => max(0, (int) $r->expiresat - $now),
            ];
        }
        $since = $now - DAYSECS;
        $all = $DB->get_records_select('nit_instant_request', 'timecreated >= :since', ['since' => $since]);
        $matched = array_filter($all, fn($r) => (int) $r->timematched > 0);
        $waits = array_map(fn($r) => (int) $r->timematched - (int) $r->timecreated, $matched);
        $bysubject = [];
        foreach ($all as $r) {
            $key = format_string($r->subject);
            $bysubject[$key] = $bysubject[$key] ?? ['subject' => $key, 'requests' => 0, 'matched' => 0, 'unmatched' => 0];
            $bysubject[$key]['requests']++;
            if ((int) $r->timematched > 0) {
                $bysubject[$key]['matched']++;
            } else if (in_array($r->status, [self::EXPIRED], true)) {
                $bysubject[$key]['unmatched']++;
            }
        }
        usort($bysubject, fn($a, $b) => $b['unmatched'] <=> $a['unmatched'] ?: $b['requests'] <=> $a['requests']);
        return [
            'teachers' => $teachers,
            'requests' => $requests,
            'stats' => [
                'requests' => count($all),
                'matched' => count($matched),
                'matchrate' => count($all) ? (int) round(count($matched) * 100 / count($all)) : 0,
                'avgwait' => $waits ? (int) round(array_sum($waits) / count($waits)) : 0,
                'noshows' => $DB->count_records_select('nit_instant_offer', 'status = :s AND timecreated >= :since',
                    ['s' => self::NOSHOW, 'since' => $since]),
            ],
            'subjects' => array_values($bysubject),
        ];
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

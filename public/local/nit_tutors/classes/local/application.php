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

namespace local_nit_tutors\local;

/**
 * A teacher's application to teach on the academy, and its review.
 *
 *   draft ──submit──▶ pending ──approve──▶ approved ──suspend──▶ suspended
 *     ▲                 │  │                  ▲                     │
 *     │                 │  └─reject──▶ rejected ──submit (again)────┘ (approve = reinstate)
 *     └──── changes ◀───┘ request changes
 *
 * Approving gives the site-level Tutor role ({@see tutor_role}); suspending or rejecting takes it
 * away. Only an approved teacher is listed in the teacher search and can be booked (when the
 * admin keeps "Approval required" on). The account itself is an ordinary account the whole time:
 * an applicant learns as a student until approved.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class application {

    /** Table. */
    const TABLE = 'nit_tutor';

    /** Statuses. */
    const DRAFT = 'draft';
    const PENDING = 'pending';
    const CHANGES = 'changes';
    const APPROVED = 'approved';
    const REJECTED = 'rejected';
    const SUSPENDED = 'suspended';

    /** Every status, in the order the admin list shows them. */
    const STATUSES = [self::PENDING, self::CHANGES, self::APPROVED, self::REJECTED, self::SUSPENDED, self::DRAFT];

    /** Statuses in which the teacher can edit and (re)submit. Pending waits for the reviewer. */
    const EDITABLE = [self::DRAFT, self::CHANGES, self::REJECTED, self::APPROVED, self::SUSPENDED];

    /**
     * The teacher's row, or null when they never started an application.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get(int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['userid' => $userid]) ?: null;
    }

    /**
     * The teacher's row, created as a draft when missing.
     *
     * @param int $userid
     * @return \stdClass
     */
    public static function ensure(int $userid): \stdClass {
        global $DB;
        if ($row = self::get($userid)) {
            return $row;
        }
        $now = time();
        $row = (object) ['userid' => $userid, 'status' => self::DRAFT, 'reviewedby' => 0, 'timesubmitted' => 0,
            'timereviewed' => 0, 'videotype' => '', 'videourl' => '', 'videostatus' => '', 'badgemode' => 'auto',
            'badge' => 0, 'badgesince' => 0, 'timecreated' => $now, 'timemodified' => $now];
        $row->id = $DB->insert_record(self::TABLE, $row);
        return $row;
    }

    /**
     * The status, or '' when the user has no application.
     *
     * @param int $userid
     * @return string
     */
    public static function status(int $userid): string {
        $row = self::get($userid);
        return $row ? (string) $row->status : '';
    }

    /**
     * Is the teacher approved (listed and bookable)?
     *
     * @param int $userid
     * @return bool
     */
    public static function is_approved(int $userid): bool {
        return self::status($userid) === self::APPROVED;
    }

    /**
     * Whether only approved teachers are listed and bookable (admin setting, on by default).
     *
     * @return bool
     */
    public static function approval_required(): bool {
        $v = get_config('local_nit_tutors', 'requireapproval');
        return $v === false || $v === null || $v === '' || (bool) $v;
    }

    /**
     * Can the teacher change their application now?
     *
     * @param int $userid
     * @return bool
     */
    public static function editable(int $userid): bool {
        $status = self::status($userid);
        return $status === '' || in_array($status, self::EDITABLE, true);
    }

    /**
     * What still blocks submitting: [key => label]. Empty = ready.
     *
     * @param int $userid
     * @return string[]
     */
    public static function missing(int $userid): array {
        global $DB;
        $out = [];
        $row = self::get($userid);
        $user = \core_user::get_user($userid);
        if ($user && trim(html_to_text((string) $user->description, 0, false)) === '') {
            $out['bio'] = get_string('missing_bio', 'local_nit_tutors');
        }
        if (!$DB->record_exists('nit_teacher_subject', ['teacherid' => $userid])) {
            $out['subjects'] = get_string('missing_subjects', 'local_nit_tutors');
        }
        if (self::setting_int('requirevideo', 1) && !video::has_video($userid, $row)) {
            $out['video'] = get_string('missing_video', 'local_nit_tutors');
        }
        $minquals = self::setting_int('minqualifications', 1);
        if ($minquals > 0 && qualifications::count($userid) < $minquals) {
            $out['qualifications'] = get_string('missing_qualifications', 'local_nit_tutors', $minquals);
        }
        foreach (fields::missing_required($userid) as $fieldid => $label) {
            $out['field' . $fieldid] = $label;
        }
        return $out;
    }

    /**
     * Send the application for review.
     *
     * @param int $userid
     * @throws \moodle_exception when it is not complete or not editable
     */
    public static function submit(int $userid): void {
        global $DB;
        if (!self::editable($userid)) {
            throw new \moodle_exception('err_noteditable', 'local_nit_tutors');
        }
        $row = self::ensure($userid);
        if ($row->status === self::APPROVED) {
            return; // Already approved: edits go live (the video waits for its own review).
        }
        if ($row->status === self::REJECTED && !self::setting_int('allowreapply', 1)) {
            throw new \moodle_exception('err_noreapply', 'local_nit_tutors');
        }
        if ($missing = self::missing($userid)) {
            throw new \moodle_exception('err_incomplete', 'local_nit_tutors', '', implode('، ', $missing));
        }
        $row->status = self::PENDING;
        $row->timesubmitted = time();
        $row->timemodified = time();
        $DB->update_record(self::TABLE, $row);
        notifier::submitted($userid);
    }

    /**
     * Approve (or reinstate) the teacher: Tutor role, listed, bookable.
     *
     * @param int $userid
     * @param int $reviewerid
     * @param string $note
     * @throws \moodle_exception teacherlimit when the academy's plan has no teacher seat left
     */
    public static function approve(int $userid, int $reviewerid, string $note = ''): void {
        global $DB;
        $row = self::ensure($userid);
        if (!tutor_role::holds($userid) && class_exists('\local_license\enforcer')
                && !\local_license\enforcer::is_teacher($userid) && !\local_license\enforcer::can_add_teacher()) {
            throw new \moodle_exception('limit_teacher', 'local_license', '', [
                'tier' => \local_license\license::tiername(), 'max' => \local_license\license::max_teachers()]);
        }
        $transaction = $DB->start_delegated_transaction();
        $row->status = self::APPROVED;
        // Approving the application approves the video sent with it.
        if ($row->videostatus === 'pending') {
            $row->videostatus = 'approved';
            $row->videonote = '';
        }
        self::stamp($row, $reviewerid, $note);
        tutor_role::grant($userid);
        // The teacher takes bookings from now on (they can switch it off on their profile).
        if ($DB->record_exists('nit_teacher_subject', ['teacherid' => $userid])) {
            $DB->set_field('nit_teacher_profile', 'available', 1, ['userid' => $userid]);
        }
        $transaction->allow_commit();
        badge::refresh($userid);
        notifier::reviewed($userid, self::APPROVED, $note);
    }

    /**
     * Reject the application.
     *
     * @param int $userid
     * @param int $reviewerid
     * @param string $note the reason (shown to the teacher)
     */
    public static function reject(int $userid, int $reviewerid, string $note): void {
        self::close($userid, $reviewerid, $note, self::REJECTED);
    }

    /**
     * Send it back for changes.
     *
     * @param int $userid
     * @param int $reviewerid
     * @param string $note what to change (shown to the teacher)
     */
    public static function request_changes(int $userid, int $reviewerid, string $note): void {
        self::close($userid, $reviewerid, $note, self::CHANGES);
    }

    /**
     * Stop an approved teacher: no longer listed or bookable; lessons already booked stay.
     *
     * @param int $userid
     * @param int $reviewerid
     * @param string $note
     */
    public static function suspend(int $userid, int $reviewerid, string $note): void {
        self::close($userid, $reviewerid, $note, self::SUSPENDED);
    }

    /**
     * Move to a non-approved status, taking the Tutor role away.
     *
     * @param int $userid
     * @param int $reviewerid
     * @param string $note
     * @param string $status
     */
    private static function close(int $userid, int $reviewerid, string $note, string $status): void {
        global $DB;
        if (trim($note) === '') {
            throw new \moodle_exception('err_notereq', 'local_nit_tutors');
        }
        $row = self::ensure($userid);
        $transaction = $DB->start_delegated_transaction();
        $row->status = $status;
        self::stamp($row, $reviewerid, $note);
        tutor_role::revoke($userid);
        $transaction->allow_commit();
        notifier::reviewed($userid, $status, $note);
    }

    /**
     * Write the reviewer's decision.
     *
     * @param \stdClass $row
     * @param int $reviewerid
     * @param string $note
     */
    private static function stamp(\stdClass $row, int $reviewerid, string $note): void {
        global $DB;
        $row->reviewnote = trim($note);
        $row->reviewedby = $reviewerid;
        $row->timereviewed = time();
        $row->timemodified = time();
        $DB->update_record(self::TABLE, $row);
    }

    /**
     * How many applications are in each status.
     *
     * @return int[] status => count
     */
    public static function counts(): array {
        global $DB;
        $out = array_fill_keys(self::STATUSES, 0);
        foreach ($DB->get_records_sql("SELECT status, COUNT(1) AS n FROM {" . self::TABLE . "} GROUP BY status") as $r) {
            $out[$r->status] = (int) $r->n;
        }
        return $out;
    }

    /**
     * Number of approved teachers whose new intro video waits for review.
     *
     * @return int
     */
    public static function videos_waiting(): int {
        global $DB;
        return $DB->count_records(self::TABLE, ['status' => self::APPROVED, 'videostatus' => 'pending']);
    }

    /**
     * Applications for the admin list.
     *
     * @param string $status '' = all, 'video' = approved teachers with a video to review
     * @param string $search name or email
     * @param int $page
     * @param int $perpage
     * @return array{total:int, rows:\stdClass[]}
     */
    public static function search(string $status, string $search, int $page = 0, int $perpage = 30): array {
        global $DB;
        $where = ['u.deleted = 0'];
        $params = [];
        if ($status === 'video') {
            $where[] = "t.status = :st AND t.videostatus = 'pending'";
            $params['st'] = self::APPROVED;
        } else if (in_array($status, self::STATUSES, true)) {
            $where[] = 't.status = :st';
            $params['st'] = $status;
        }
        $search = trim($search);
        if ($search !== '') {
            $like = [];
            foreach (['firstname', 'lastname', 'email'] as $i => $col) {
                $like[] = $DB->sql_like('u.' . $col, ':q' . $i, false);
                $params['q' . $i] = '%' . $DB->sql_like_escape($search) . '%';
            }
            $like[] = $DB->sql_like($DB->sql_fullname('u.firstname', 'u.lastname'), ':qf', false);
            $params['qf'] = '%' . $DB->sql_like_escape($search) . '%';
            $where[] = '(' . implode(' OR ', $like) . ')';
        }
        $wsql = implode(' AND ', $where);
        $from = "FROM {" . self::TABLE . "} t JOIN {user} u ON u.id = t.userid WHERE $wsql";
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $fields = \core_user\fields::for_userpic()->get_sql('u', false, '', 'uid', false)->selects;
        $rows = $DB->get_records_sql("SELECT t.*, $fields $from
            ORDER BY CASE WHEN t.status = 'pending' THEN 0 ELSE 1 END, t.timesubmitted DESC, t.timemodified DESC",
            $params, $page * $perpage, $perpage);
        return ['total' => $total, 'rows' => array_values($rows)];
    }

    /**
     * An integer setting with its default.
     *
     * @param string $name
     * @param int $default
     * @return int
     */
    public static function setting_int(string $name, int $default): int {
        $v = get_config('local_nit_tutors', $name);
        return ($v === false || $v === null || $v === '') ? $default : (int) $v;
    }
}

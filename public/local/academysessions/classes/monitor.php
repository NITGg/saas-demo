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

namespace local_academysessions;

defined('MOODLE_INTERNAL') || die();

/**
 * The live monitoring wall: which lessons are on now (or on a given day), their
 * state, who is in, and what needs attention.
 *
 * One card per live session (academy_live_sessions: group sessions and started Flex
 * lessons), plus one per confirmed Flex lesson (nit_lesson) whose teacher has not
 * pressed Start yet. Nothing is stored: every state is worked out from the session
 * rows, the attendance rows and the clock.
 *
 * Who is "in" comes from the attendance rows (a student's row is open while
 * left_at is empty; web and app send a leave), and the teacher's presence from
 * teacher_joined_at. These are reported by the browser / app; task 21 moves them to
 * the Jitsi server.
 *
 * Admins (the capability in the system context) see every lesson; a manager of a
 * category or course sees the courses where they hold the capability.
 *
 * @package    local_academysessions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class monitor {

    /** @var string the capability to open the wall. */
    const CAP = 'local/academysessions:monitorlive';

    /** @var int "now" shows a lesson from this many seconds before it starts... */
    const BEFORE = 1800;
    /** @var int ...until this many seconds after it ends. */
    const AFTER = 900;
    /** @var int at most this many cards. */
    const MAXCARDS = 200;

    /** @var string starts within the next half hour. */
    const UPCOMING = 'upcoming';
    /** @var string started, teacher not in yet, still within the grace period. */
    const WAITING = 'waiting';
    /** @var string started, teacher never came in, grace period over. */
    const LATE = 'late';
    /** @var string a confirmed Flex lesson whose teacher has not pressed Start. */
    const NOTSTARTED = 'notstarted';
    /** @var string the teacher is in the call. */
    const RUNNING = 'running';
    /** @var string the teacher came in and left again. */
    const TEACHERLEFT = 'teacherleft';
    /** @var string over. */
    const ENDED = 'ended';
    /** @var string cancelled. */
    const CANCELLED = 'cancelled';

    /** @var string[] statuses in the order the wall shows them (most urgent first). */
    const ORDER = [self::LATE, self::NOTSTARTED, self::TEACHERLEFT, self::WAITING, self::RUNNING,
        self::UPCOMING, self::ENDED, self::CANCELLED];

    /** @var array<string,string> status => Bootstrap colour. */
    const COLOURS = [self::LATE => 'danger', self::NOTSTARTED => 'danger', self::TEACHERLEFT => 'warning',
        self::WAITING => 'warning', self::RUNNING => 'success', self::UPCOMING => 'info',
        self::ENDED => 'secondary', self::CANCELLED => 'secondary'];

    /**
     * The courses a user may monitor.
     *
     * @param int $userid
     * @return int[]|null null = every course (admin); [] = none
     */
    public static function course_scope(int $userid): ?array {
        if (has_capability(self::CAP, \context_system::instance(), $userid)) {
            return null;
        }
        $courses = get_user_capability_course(self::CAP, $userid, true, '', 'id', 0) ?: [];
        return array_values(array_filter(array_map(fn($c) => (int) $c->id, $courses), fn($id) => $id > SITEID));
    }

    /**
     * Minutes a teacher may be late before the lesson is flagged.
     *
     * @return int
     */
    public static function grace_minutes(): int {
        $grace = get_config('local_academysessions', 'monitor_grace');
        return $grace === false || $grace === '' ? 5 : max(0, (int) $grace);
    }

    /**
     * Seconds between two refreshes of the wall.
     *
     * @return int
     */
    public static function refresh_seconds(): int {
        $refresh = (int) get_config('local_academysessions', 'monitor_refresh');
        return $refresh >= 5 ? $refresh : 20;
    }

    /**
     * The filters of a request (page or poll).
     *
     * @return \stdClass {view: now|day, date: Y-m-d, courseid, teacherid, provider, status, q}
     */
    public static function filters_from_request(): \stdClass {
        $f = new \stdClass();
        $f->view = optional_param('view', 'now', PARAM_ALPHA) === 'day' ? 'day' : 'now';
        $f->date = optional_param('date', '', PARAM_RAW_TRIMMED);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f->date)) {
            $f->date = userdate(time(), '%Y-%m-%d');
        }
        $f->courseid = optional_param('courseid', 0, PARAM_INT);
        $f->teacherid = optional_param('teacherid', 0, PARAM_INT);
        $provider = optional_param('provider', '', PARAM_ALPHA);
        $f->provider = in_array($provider, ['jitsi', 'link'], true) ? $provider : '';
        $status = optional_param('status', '', PARAM_ALPHA);
        $f->status = in_array($status, self::ORDER, true) ? $status : '';
        $f->q = trim(optional_param('q', '', PARAM_TEXT));
        return $f;
    }

    /**
     * The filters as URL parameters (defaults left out).
     *
     * @param \stdClass $f
     * @return array
     */
    public static function filter_params(\stdClass $f): array {
        $p = ['view' => $f->view];
        if ($f->view === 'day') {
            $p['date'] = $f->date;
        }
        foreach (['courseid', 'teacherid', 'provider', 'status', 'q'] as $k) {
            if (!empty($f->$k)) {
                $p[$k] = $f->$k;
            }
        }
        return $p;
    }

    /**
     * The wall: one card per lesson, most urgent first, and the count per status.
     *
     * @param \stdClass $f filters_from_request()
     * @param int[]|null $courseids course_scope()
     * @param int|null $now defaults to time()
     * @return array ['cards' => array[], 'summary' => array[], 'total' => int, 'truncated' => bool]
     */
    public static function wall(\stdClass $f, ?array $courseids, ?int $now = null): array {
        $now = $now ?? time();
        $grace = self::grace_minutes();
        $cards = array_merge(self::session_cards($f, $courseids, $now, $grace),
            self::pending_flex_cards($f, $courseids, $now, $grace));

        $counts = array_fill_keys(self::ORDER, 0);
        foreach ($cards as $c) {
            $counts[$c['status']]++;
        }
        if ($f->status !== '') {
            $cards = array_values(array_filter($cards, fn($c) => $c['status'] === $f->status));
        }
        $rank = array_flip(self::ORDER);
        usort($cards, function($a, $b) use ($rank) {
            return [$b['hasalert'], $rank[$a['status']], $a['start']] <=> [$a['hasalert'], $rank[$b['status']], $b['start']];
        });
        $total = count($cards);

        $summary = [];
        foreach (self::ORDER as $st) {
            if ($counts[$st] || in_array($st, [self::RUNNING, self::LATE], true)) {
                $summary[] = ['status' => $st, 'label' => self::str('status_' . $st), 'count' => $counts[$st],
                    'colour' => self::COLOURS[$st], 'active' => $f->status === $st];
            }
        }
        return ['cards' => array_slice($cards, 0, self::MAXCARDS), 'summary' => $summary,
            'total' => $total, 'truncated' => $total > self::MAXCARDS];
    }

    /**
     * One lesson in detail: its card and everyone expected or seen in it, with each
     * person's stretches in the call (every leave and rejoin).
     *
     * @param int $sessionid
     * @param int[]|null $courseids course_scope()
     * @param int|null $now
     * @return array|null null when not found or outside the viewer's courses
     */
    public static function detail(int $sessionid, ?array $courseids, ?int $now = null): ?array {
        global $DB;
        $now = $now ?? time();
        $session = $DB->get_record('academy_live_sessions', ['id' => $sessionid]);
        if (!$session || ($courseids !== null && !in_array((int) $session->courseid, $courseids, true))) {
            return null;
        }
        $card = self::cards_for([$session], $now, self::grace_minutes())[0];
        $over = in_array($card['status'], [self::ENDED, self::CANCELLED], true);
        $until = self::until($session, $now);

        $invited = array_map('intval', $DB->get_fieldset_select('academy_session_students', 'userid',
            'sessionid = ?', [$sessionid]));
        $stretches = [];
        foreach ($DB->get_records('academy_session_presence', ['sessionid' => $sessionid], 'joined_at, id') as $p) {
            $stretches[(int) $p->userid][] = $p;
        }
        $teacherid = (int) $session->teacherid;
        $ids = array_unique(array_merge([$teacherid], $invited, array_keys($stretches)));
        $names = self::names($ids);

        // The timeline: the planned window, stretched to anything seen outside it (an early
        // join, running over, the time it was ended), and "now" while it is on.
        $from = (int) $session->start_time;
        $to = self::end_time($session);
        foreach ($stretches as $mine) {
            foreach ($mine as $p) {
                $from = min($from, (int) $p->joined_at);
                $to = max($to, (int) ($p->left_at ?: 0));
            }
        }
        if (!$over) {
            $to = max($to, $now);
        }
        $span = max(MINSECS, $to - $from);
        $pos = fn(int $t) => round(100 * ($t - $from) / $span, 2);

        $row = function(int $userid, string $role) use ($stretches, $names, $until, $over, $session, $teacherid, $pos, $span) {
            $mine = $stretches[$userid] ?? [];
            $open = !$over && $mine && empty(end($mine)->left_at);
            if ($role === 'teacher') {
                $open = !$over && !empty($session->teacher_joined_at);
            }
            $seconds = 0;
            $parts = [];
            $segments = [];
            foreach ($mine as $p) {
                $joined = (int) $p->joined_at;
                $left = $p->left_at ? (int) $p->left_at : $until;
                $seconds += max(0, $left - $joined);
                $parts[] = self::time($joined) . ' – ' . ($p->left_at ? self::time((int) $p->left_at) : self::str('stillin'));
                $segments[] = [
                    'start' => $pos($joined),
                    // At least a sliver, so a few seconds in the call still shows.
                    'size' => max(0.8, round(100 * ($left - $joined) / $span, 2)),
                    'open' => !$p->left_at && !$over,
                    'label' => end($parts),
                ];
            }
            // The teacher's first join is the session's teacher_first_join (what lateness uses).
            $first = $userid === $teacherid && !empty($session->teacher_first_join)
                ? (int) $session->teacher_first_join : ($mine ? (int) $mine[0]->joined_at : 0);
            $last = 0;
            foreach ($mine as $p) {
                $last = max($last, (int) $p->left_at);
            }
            $state = $open ? 'in' : ($first ? 'out' : 'never');
            return [
                'name' => $names[$userid] ?? '—',
                'role' => self::str('role_' . $role),
                'isteacher' => $role === 'teacher',
                'isother' => $role === 'other',
                'joined' => (bool) $first,
                'firstjoin' => $first ? self::time($first) : '',
                'lastleave' => !$open && $last ? self::time($last) : '',
                'present' => $open,
                'state' => self::str('person_' . ['in' => 'present', 'out' => 'left', 'never' => 'never'][$state]),
                'stateclass' => $state,
                'statecolour' => ['in' => 'success', 'out' => 'warning', 'never' => 'secondary'][$state],
                'visits' => count($mine),
                'minutes' => $mine ? self::str('minutes', (int) floor($seconds / MINSECS)) : '—',
                'summary' => $mine ? self::str('personsummary', ['visits' => count($mine),
                    'minutes' => (int) floor($seconds / MINSECS)]) : '',
                'stretches' => implode(self::str('listsep'), $parts),
                'segments' => $segments,
            ];
        };
        $teacher = $row($teacherid, 'teacher');
        $students = array_map(fn($uid) => $row($uid, 'student'), $invited);
        $others = [];
        foreach (array_keys($stretches) as $uid) {
            if ($uid !== $teacherid && !in_array($uid, $invited, true)) {
                $others[] = $row($uid, 'other');
            }
        }
        $card['people'] = array_merge([$teacher], $students, $others);
        $card['teacherrow'] = [$teacher];
        $card['students'] = $students;
        $card['studentcount'] = count($students);
        $card['others'] = $others;
        $card['hasothers'] = (bool) $others;
        $card['axis'] = [
            'from' => self::time($from),
            'to' => self::time($to),
            'hasnow' => !$over && $now >= $from && $now <= $to,
            'now' => $pos(min(max($now, $from), $to)),
            // The planned window inside the axis (it can be narrower when people came early or stayed late).
            'planstart' => $pos((int) $session->start_time),
            'plansize' => round(100 * (self::end_time($session) - (int) $session->start_time) / $span, 2),
        ];
        return $card;
    }

    // ── Cards ─────────────────────────────────────────────────────────────────

    /**
     * Cards of the live sessions in the window.
     *
     * @param \stdClass $f
     * @param int[]|null $courseids
     * @param int $now
     * @param int $grace minutes
     * @return array[]
     */
    private static function session_cards(\stdClass $f, ?array $courseids, int $now, int $grace): array {
        global $DB;
        if ($courseids === []) {
            return [];
        }
        $where = [];
        $params = [];
        if ($f->view === 'day') {
            [$from, $to] = self::day_bounds($f->date);
            $where[] = 's.start_time >= :dfrom AND s.start_time < :dto';
            $params += ['dfrom' => $from, 'dto' => $to];
        } else {
            $where[] = "s.start_time <= :wfrom AND s.start_time + s.duration * 60 >= :wto AND s.status <> 'cancelled'";
            $params += ['wfrom' => $now + self::BEFORE, 'wto' => $now - self::AFTER];
        }
        if ($courseids !== null) {
            [$in, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'sc');
            $where[] = "s.courseid $in";
            $params += $inparams;
        }
        if ($f->courseid) {
            $where[] = 's.courseid = :fcourse';
            $params['fcourse'] = $f->courseid;
        }
        if ($f->teacherid) {
            $where[] = 's.teacherid = :fteacher';
            $params['fteacher'] = $f->teacherid;
        }
        if ($f->provider === 'jitsi') {
            $where[] = 's.jitsiid IS NOT NULL';
        } else if ($f->provider === 'link') {
            $where[] = 's.jitsiid IS NULL';
        }
        if ($f->q !== '') {
            $where[] = $DB->sql_like('s.title', ':fq', false);
            $params['fq'] = '%' . $DB->sql_like_escape($f->q) . '%';
        }
        $sessions = $DB->get_records_sql('SELECT s.* FROM {academy_live_sessions} s WHERE ' . implode(' AND ', $where)
            . ' ORDER BY s.start_time', $params, 0, self::MAXCARDS * 2);
        return self::cards_for(array_values($sessions), $now, $grace);
    }

    /**
     * Build the cards of some sessions (with their presence stretches and Flex links).
     *
     * @param \stdClass[] $sessions academy_live_sessions rows
     * @param int $now
     * @param int $grace minutes
     * @return array[]
     */
    private static function cards_for(array $sessions, int $now, int $grace): array {
        global $DB;
        if (!$sessions) {
            return [];
        }
        $ids = array_map(fn($s) => (int) $s->id, $sessions);
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);

        $invited = [];
        foreach ($DB->get_recordset_select('academy_session_students', "sessionid $in", $params, '', 'id, sessionid, userid') as $r) {
            $invited[(int) $r->sessionid][(int) $r->userid] = true;
        }
        // Stretches in the call, per session and user.
        $stretches = [];
        foreach ($DB->get_recordset_select('academy_session_presence', "sessionid $in", $params, 'joined_at, id',
                'id, sessionid, userid, joined_at, left_at') as $r) {
            $stretches[(int) $r->sessionid][(int) $r->userid][] = $r;
        }
        $flex = [];
        if ($DB->get_manager()->table_exists('nit_lesson')) {
            foreach ($DB->get_records_select('nit_lesson', "sessionid $in", $params, '', 'id, sessionid, studentid, subject') as $l) {
                $flex[(int) $l->sessionid] = $l;
            }
        }
        $courses = $DB->get_records_list('course', 'id', array_unique(array_map(fn($s) => (int) $s->courseid, $sessions)),
            '', 'id, fullname');
        $cmids = mobile_service::jitsi_cmids(array_map(fn($s) => (int) ($s->jitsiid ?? 0), $sessions));
        $userids = array_merge(array_map(fn($s) => (int) $s->teacherid, $sessions),
            array_map(fn($s) => (int) ($s->ended_by ?? 0), $sessions),
            array_map(fn($l) => (int) $l->studentid, $flex));
        foreach ($invited as $users) {
            $userids = array_merge($userids, array_keys($users));
        }
        $names = self::names($userids);
        $canjoin = is_siteadmin();

        $cards = [];
        foreach ($sessions as $s) {
            $sid = (int) $s->id;
            $end = self::end_time($s);
            $teacherid = (int) $s->teacherid;
            $teacherin = !empty($s->teacher_joined_at);
            $firstjoin = (int) ($s->teacher_first_join ?? 0);

            if ($s->status === 'cancelled') {
                $status = self::CANCELLED;
            } else if ($s->status === 'ended' || $now > $end) {
                $status = self::ENDED;
            } else if ($teacherin) {
                $status = self::RUNNING;
            } else if ($now < (int) $s->start_time) {
                $status = self::UPCOMING;
            } else if ($firstjoin) {
                $status = self::TEACHERLEFT;
            } else if ($now - (int) $s->start_time <= $grace * MINSECS) {
                $status = self::WAITING;
            } else {
                $status = self::LATE;
            }
            $live = !in_array($status, [self::ENDED, self::CANCELLED], true);
            $until = self::until($s, $now);

            // Students: in now / came at some point / never came.
            $inv = array_keys($invited[$sid] ?? []);
            $innow = $came = [];
            foreach ($inv as $uid) {
                $mine = $stretches[$sid][$uid] ?? [];
                if ($mine) {
                    $came[] = $uid;
                    if ($live && empty(end($mine)->left_at)) {
                        $innow[] = $uid;
                    }
                }
            }
            $innames = array_map(fn($uid) => $names[$uid] ?? '—', $innow);
            $presentnames = implode(self::str('listsep'), array_slice($innames, 0, 3))
                . (count($innames) > 3 ? ' ' . self::str('andmore', count($innames) - 3) : '');

            // The teacher: how many times in, time in the call, last leave.
            $tstretches = $stretches[$sid][$teacherid] ?? [];
            $tseconds = 0;
            foreach ($tstretches as $p) {
                $tseconds += max(0, ($p->left_at ? (int) $p->left_at : $until) - (int) $p->joined_at);
            }
            $lastleave = !$teacherin && !empty($s->teacher_last_leave) ? (int) $s->teacher_last_leave : 0;
            $late = $firstjoin > (int) $s->start_time ? (int) floor(($firstjoin - (int) $s->start_time) / MINSECS) : 0;

            $alerts = [];
            if ($status === self::LATE) {
                $alerts[] = self::str('alert_noteacher', (int) floor(($now - (int) $s->start_time) / MINSECS));
            } else if ($status === self::TEACHERLEFT) {
                $alerts[] = $lastleave ? self::str('alert_teacherleft', self::time($lastleave)) : self::str('alert_teacherout');
            } else if ($status === self::RUNNING && $inv && !$innow
                    && $now - max((int) $s->start_time, $firstjoin) > $grace * MINSECS) {
                $alerts[] = self::str('alert_nostudents');
            }

            // How it ended: when, why, who; the actual length against the planned one.
            $endtext = $durationtext = '';
            if ($status === self::ENDED) {
                // Still "live" past its end: the scheduled task has not closed it yet.
                $endedat = !empty($s->ended_at) ? (int) $s->ended_at : ($s->status === 'ended' ? 0 : $end);
                $reason = $s->end_reason ?? ($s->status === 'ended' ? 'unknown' : 'pending');
                $by = !empty($s->ended_by) ? ($names[(int) $s->ended_by] ?? '—') : '';
                $endtext = self::str('end_' . $reason, $by);
                if ($endedat) {
                    $endtext = self::str('endedat', ['time' => self::time($endedat), 'how' => $endtext]);
                    if ($endedat < $end - MINSECS) {
                        $endtext .= ' ' . self::str('endedearly', (int) floor(($end - $endedat) / MINSECS));
                    }
                }
                if ($firstjoin) {
                    $durationtext = self::str('actualduration', [
                        'actual' => max(0, (int) floor((($endedat ?: $end) - $firstjoin) / MINSECS)),
                        'planned' => (int) $s->duration]);
                }
            }

            $l = $flex[$sid] ?? null;
            $cmid = $cmids[(int) ($s->jitsiid ?? 0)] ?? 0;
            $cards[] = self::card([
                'key' => 's' . $sid,
                'sessionid' => $sid,
                'title' => format_string($s->title),
                'course' => isset($courses[$s->courseid]) ? format_string($courses[$s->courseid]->fullname) : '—',
                'type' => self::str($l ? 'type_flex' : 'type_group'),
                'student' => $l ? ($names[(int) $l->studentid] ?? '') : '',
                'provider' => self::str($s->jitsiid ? 'provider_jitsi' : 'provider_link'),
                'teacher' => $names[$teacherid] ?? '—',
                'teacherin' => $teacherin && $live,
                'teachervisits' => $tstretches ? self::str('teachervisits', ['count' => count($tstretches),
                    'minutes' => (int) floor($tseconds / MINSECS)]) : '',
                'teacherlastleave' => $live && $lastleave ? self::time($lastleave) : '',
                'start' => (int) $s->start_time,
                'end' => $end,
                'actualstart' => $firstjoin,
                'latemin' => $late > $grace ? $late : 0,
                'invited' => count($inv),
                'present' => count($innow),
                'joined' => count($came),
                'neverjoined' => count($inv) - count($came),
                'presentnames' => $presentnames,
                'status' => $status,
                'alerts' => $alerts,
                'endtext' => $endtext,
                'durationtext' => $durationtext,
                'joinurl' => $canjoin && $cmid && $live
                    ? (new \moodle_url('/mod/jitsi/view.php', ['id' => $cmid]))->out(false) : '',
            ], $now);
        }
        return $cards;
    }

    /**
     * Until when an open stretch counts: now, or the end of an ended session.
     *
     * @param \stdClass $s
     * @param int $now
     * @return int
     */
    private static function until(\stdClass $s, int $now): int {
        if (!empty($s->ended_at)) {
            return min($now, (int) $s->ended_at);
        }
        return min($now, self::end_time($s));
    }

    /**
     * Cards of confirmed Flex lessons whose teacher has not pressed Start yet (no
     * live session exists for them).
     *
     * @param \stdClass $f
     * @param int[]|null $courseids
     * @param int $now
     * @param int $grace minutes
     * @return array[]
     */
    private static function pending_flex_cards(\stdClass $f, ?array $courseids, int $now, int $grace): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('nit_lesson') || $f->provider === 'link') {
            return [];
        }
        $lessonscourse = (int) get_config('local_nit_lessons', 'lessons_courseid');
        if ($courseids !== null && !in_array($lessonscourse, $courseids, true)) {
            return [];
        }
        if ($f->courseid && $f->courseid !== $lessonscourse) {
            return [];
        }
        $where = ["l.status = 'confirmed'"];
        $params = [];
        if ($f->view === 'day') {
            [$from, $to] = self::day_bounds($f->date);
            $where[] = 'l.confirmed_time >= :dfrom AND l.confirmed_time < :dto';
            $params += ['dfrom' => $from, 'dto' => $to];
        } else {
            $where[] = 'l.confirmed_time <= :wfrom AND l.confirmed_time + l.duration * 60 >= :wto';
            $params += ['wfrom' => $now + self::BEFORE, 'wto' => $now - self::AFTER];
        }
        if ($f->teacherid) {
            $where[] = 'l.teacherid = :fteacher';
            $params['fteacher'] = $f->teacherid;
        }
        if ($f->q !== '') {
            $where[] = $DB->sql_like('l.subject', ':fq', false);
            $params['fq'] = '%' . $DB->sql_like_escape($f->q) . '%';
        }
        $lessons = $DB->get_records_sql('SELECT l.id, l.teacherid, l.studentid, l.subject, l.confirmed_time, l.duration
                                           FROM {nit_lesson} l WHERE ' . implode(' AND ', $where)
            . ' ORDER BY l.confirmed_time', $params, 0, self::MAXCARDS);
        if (!$lessons) {
            return [];
        }
        $names = self::names(array_merge(array_map(fn($l) => (int) $l->teacherid, $lessons),
            array_map(fn($l) => (int) $l->studentid, $lessons)));
        $course = $lessonscourse ? $DB->get_field('course', 'fullname', ['id' => $lessonscourse]) : '';

        $cards = [];
        foreach ($lessons as $l) {
            $start = (int) $l->confirmed_time;
            $end = $start + (int) $l->duration * MINSECS;
            if ($now > $end) {
                $status = self::NOTSTARTED;
            } else if ($now < $start) {
                $status = self::UPCOMING;
            } else {
                $status = $now - $start <= $grace * MINSECS ? self::WAITING : self::NOTSTARTED;
            }
            $alerts = $status === self::NOTSTARTED
                ? [self::str('alert_notstarted', (int) floor((min($now, $end) - $start) / MINSECS))] : [];
            $cards[] = self::card([
                'key' => 'l' . $l->id,
                'sessionid' => 0,
                'title' => format_string($l->subject ?: self::str('type_flex')),
                'course' => $course ? format_string($course) : '—',
                'type' => self::str('type_flex'),
                'student' => $names[(int) $l->studentid] ?? '',
                'provider' => self::str('provider_jitsi'),
                'teacher' => $names[(int) $l->teacherid] ?? '—',
                'teacherin' => false,
                'start' => $start,
                'end' => $end,
                'actualstart' => 0,
                'latemin' => 0,
                'invited' => 1,
                'present' => 0,
                'joined' => 0,
                'status' => $status,
                'alerts' => $alerts,
            ], $now);
        }
        return $cards;
    }

    /**
     * Finish a card: labels and the times as text.
     *
     * @param array $c
     * @param int $now
     * @return array
     */
    private static function card(array $c, int $now): array {
        $c += ['teachervisits' => '', 'teacherlastleave' => '', 'neverjoined' => 0, 'presentnames' => '',
            'endtext' => '', 'durationtext' => '', 'joinurl' => ''];
        // "Running for" counts from when the teacher came in; before that the alert says how late.
        $live = !in_array($c['status'], [self::ENDED, self::CANCELLED, self::UPCOMING, self::NOTSTARTED], true)
            && $c['actualstart'];
        $from = $c['actualstart'];
        $c['statuslabel'] = self::str('status_' . $c['status']);
        $c['colour'] = self::COLOURS[$c['status']];
        $c['hasalert'] = !empty($c['alerts']);
        $c['alerts'] = array_map(fn($a) => ['text' => $a], $c['alerts']);
        $c['timerange'] = self::time($c['start']) . ' – ' . self::time($c['end']);
        $c['actualstarttext'] = $c['actualstart'] ? self::time($c['actualstart']) : '';
        $c['elapsed'] = $live && $now > $from ? self::str('minutes', (int) floor(($now - $from) / MINSECS)) : '';
        $c['startsin'] = $c['status'] === self::UPCOMING
            ? self::str('startsin', max(1, (int) ceil(($c['start'] - $now) / MINSECS))) : '';
        $c['latetext'] = $c['latemin'] ? self::str('teacherwaslate', $c['latemin']) : '';
        $c['attendance'] = self::str('attendance_count', ['present' => $c['present'], 'invited' => $c['invited']]);
        $c['studentsline'] = $c['invited'] && $c['status'] !== self::UPCOMING
            ? self::str('students_line', ['joined' => $c['joined'], 'never' => $c['neverjoined']]) : '';
        $c['hasdetail'] = $c['sessionid'] > 0;
        $c['isflex'] = $c['student'] !== '';
        return $c;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * When a session ends.
     *
     * @param \stdClass $s
     * @return int
     */
    private static function end_time(\stdClass $s): int {
        return (int) $s->start_time + (int) $s->duration * MINSECS;
    }

    /**
     * [midnight, next midnight) of a Y-m-d day in the viewer's timezone.
     *
     * @param string $date
     * @return int[]
     */
    private static function day_bounds(string $date): array {
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        $from = make_timestamp($y, $m, $d);
        return [$from, make_timestamp($y, $m, $d + 1)];
    }

    /**
     * Full names by user id.
     *
     * @param int[] $ids
     * @return array<int,string>
     */
    private static function names(array $ids): array {
        global $DB;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $fields = \core_user\fields::for_name()->get_sql('', false, '', '', false)->selects;
        $out = [];
        foreach ($DB->get_records_list('user', 'id', $ids, '', 'id, ' . $fields) as $u) {
            $out[(int) $u->id] = fullname($u);
        }
        return $out;
    }

    /**
     * A time of day in the viewer's timezone.
     *
     * @param int $t
     * @return string
     */
    private static function time(int $t): string {
        return userdate($t, get_string('strftimetime', 'langconfig'));
    }

    /**
     * A string of this plugin.
     *
     * @param string $key
     * @param mixed $a
     * @return string
     */
    private static function str(string $key, $a = null): string {
        return get_string('monitor_' . $key, 'local_academysessions', $a);
    }
}

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

use local_nit_core\helper\arabic;
use local_nit_lessons\service\teacher_service;

/**
 * The teacher search (/local/nit_tutors/index.php, the header search's "Teachers" tab and the
 * suggestions): teachers students can book, filtered by text, subject, price, availability,
 * rating, the Super Tutor badge and the admin's filter fields (e.g. language, country).
 *
 * A teacher is listed when they take bookings (profile "available", at least one subject), are a
 * teacher at all, and — while "Approval required" is on — their application is approved.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class search {

    /** Sort orders. */
    const SORTS = ['recommended', 'rating', 'price_asc', 'price_desc', 'lessons'];

    /** Time-of-day buckets, minutes from midnight in the viewer's timezone. */
    const TIMES = ['morning' => [360, 720], 'afternoon' => [720, 1020], 'evening' => [1020, 1440]];

    /**
     * Clean filter values from request-like input.
     *
     * @param array $raw
     * @return array
     */
    public static function normalise(array $raw): array {
        $int = fn($k) => isset($raw[$k]) && $raw[$k] !== '' && is_numeric($raw[$k]) ? max(0, (int) $raw[$k]) : null;
        $day = isset($raw['day']) && $raw['day'] !== '' && is_numeric($raw['day']) ? (int) $raw['day'] : null;
        $fieldvals = [];
        foreach ((array) ($raw['f'] ?? []) as $fid => $v) {
            if (is_numeric($fid) && trim((string) $v) !== '') {
                $fieldvals[(int) $fid] = trim((string) $v);
            }
        }
        return [
            'q' => \core_text::substr(trim((string) ($raw['q'] ?? '')), 0, 100),
            'subject' => trim((string) ($raw['subject'] ?? '')),
            'pricemin' => $int('pricemin'),
            'pricemax' => $int('pricemax'),
            'day' => ($day !== null && $day >= 0 && $day <= 6) ? $day : null,
            'time' => isset(self::TIMES[$raw['time'] ?? '']) ? (string) $raw['time'] : '',
            'rating' => isset($raw['rating']) && in_array((string) $raw['rating'], ['3', '4', '4.5'], true)
                ? (float) $raw['rating'] : 0.0,
            'badge' => $int('badge') ?: 0,
            'f' => $fieldvals,
            'sort' => in_array($raw['sort'] ?? '', self::SORTS, true) ? (string) $raw['sort'] : 'recommended',
        ];
    }

    /**
     * Run the search.
     *
     * @param array $filters from normalise()
     * @param int $viewerid whose timezone the availability filter uses (0 = site timezone)
     * @param int $page
     * @param int $perpage
     * @return array{total:int, teachers:array}
     */
    public static function run(array $filters, int $viewerid = 0, int $page = 0, int $perpage = 24): array {
        global $DB;
        $params = [];
        $where = ['tp.available = 1', 'EXISTS (SELECT 1 FROM {nit_teacher_subject} s0 WHERE s0.teacherid = u.id)'];
        if (application::approval_required()) {
            $where[] = "t.status = 'approved'";
        } else {
            $where[] = "(t.id IS NULL OR t.status NOT IN ('suspended', 'rejected'))";
        }
        if ($filters['q'] !== '') {
            $q = '%' . $DB->sql_like_escape(arabic::fold($filters['q'])) . '%';
            $like = [];
            foreach (['firstname', 'lastname'] as $col) {
                $like[] = $DB->sql_like(arabic::sql_fold('u.' . $col), ':q' . $col, false);
                $params['q' . $col] = $q;
            }
            $like[] = $DB->sql_like(arabic::sql_fold($DB->sql_fullname('u.firstname', 'u.lastname')), ':qfull', false);
            $params['qfull'] = $q;
            $like[] = $DB->sql_like(arabic::sql_fold('tp.headline'), ':qhead', false);
            $params['qhead'] = $q;
            $like[] = 'EXISTS (SELECT 1 FROM {nit_teacher_subject} s1 WHERE s1.teacherid = u.id AND '
                . $DB->sql_like(arabic::sql_fold('s1.subject'), ':qsub', false) . ')';
            $params['qsub'] = $q;
            $where[] = '(' . implode(' OR ', $like) . ')';
        }
        if ($filters['subject'] !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM {nit_teacher_subject} s2 WHERE s2.teacherid = u.id AND s2.subject = :subject)';
            $params['subject'] = $filters['subject'];
        }
        if ($filters['pricemin'] !== null) {
            $where[] = 'tp.lesson_flex >= :pmin';
            $params['pmin'] = $filters['pricemin'];
        }
        if ($filters['pricemax'] !== null) {
            $where[] = 'tp.lesson_flex <= :pmax';
            $params['pmax'] = $filters['pricemax'];
        }
        if ($filters['badge']) {
            $where[] = 'EXISTS (SELECT 1 FROM {' . badge::USERS . '} bf JOIN {' . badge::TABLE . '} bfb
                ON bfb.id = bf.badgeid AND bfb.active = 1 WHERE bf.userid = u.id AND bf.awarded = 1 AND bf.badgeid = :badgeid)';
            $params['badgeid'] = $filters['badge'];
        }
        $filterfields = self::filter_fields();
        foreach ($filters['f'] as $fid => $value) {
            if (!isset($filterfields[$fid])) {
                continue;
            }
            $where[] = "EXISTS (SELECT 1 FROM {nit_tutor_data} d$fid WHERE d$fid.userid = u.id AND d$fid.fieldid = :fid$fid
                AND " . $DB->sql_compare_text("d$fid.value", 255) . " = " . $DB->sql_compare_text(":fv$fid", 255) . ')';
            $params['fid' . $fid] = $fid;
            $params['fv' . $fid] = $value;
        }
        $fields = \core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects;
        $rows = $DB->get_records_sql(
            "SELECT $fields, tp.headline, tp.lesson_flex, t.videostatus, t.timecreated AS tutorsince
               FROM {nit_teacher_profile} tp
               JOIN {user} u ON u.id = tp.userid AND u.deleted = 0 AND u.suspended = 0
          LEFT JOIN {nit_tutor} t ON t.userid = u.id
              WHERE " . implode(' AND ', $where), $params);

        $service = new teacher_service();
        $ratings = class_exists('\local_nit_reviews\api') && $rows
            ? \local_nit_reviews\api::get_teacher_aggregates(array_keys($rows)) : [];
        $lessons = self::lesson_counts(array_keys($rows));
        $badges = self::badges_of(array_keys($rows));
        $viewertz = $viewerid ? teacher_service::timezone($viewerid) : \core_date::get_server_timezone_object();
        $out = [];
        foreach ($rows as $u) {
            $id = (int) $u->id;
            if (!$service->is_teacher($id)) {
                continue;
            }
            $agg = $ratings[$id] ?? null;
            $rating = $agg ? (float) $agg->avg : 0.0;
            if ($filters['rating'] > 0 && $rating < $filters['rating']) {
                continue;
            }
            if (($filters['day'] !== null || $filters['time'] !== '')
                    && !self::available_at($service, $id, $viewertz, $filters['day'], $filters['time'])) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'user' => $u,
                'fullname' => fullname($u),
                'headline' => (string) $u->headline,
                'lesson_flex' => max(1, (int) $u->lesson_flex),
                'subjects' => $service->profile($id)['subjects'],
                'rating' => $rating,
                'reviews' => $agg ? (int) $agg->count : 0,
                'lessons' => $lessons[$id] ?? 0,
                'badges' => $badges[$id] ?? [],
                'hasvideo' => $u->videostatus === 'approved',
            ];
        }
        usort($out, self::comparator($filters['sort']));
        return ['total' => count($out), 'teachers' => array_slice($out, $page * $perpage, $perpage)];
    }

    /**
     * Sort comparator.
     *
     * @param string $sort
     * @return callable
     */
    private static function comparator(string $sort): callable {
        $byname = fn($a, $b) => strcmp(\core_text::strtolower($a['fullname']), \core_text::strtolower($b['fullname']));
        switch ($sort) {
            case 'rating':
                return fn($a, $b) => [$b['rating'], $b['reviews']] <=> [$a['rating'], $a['reviews']] ?: $byname($a, $b);
            case 'price_asc':
                return fn($a, $b) => $a['lesson_flex'] <=> $b['lesson_flex'] ?: $byname($a, $b);
            case 'price_desc':
                return fn($a, $b) => $b['lesson_flex'] <=> $a['lesson_flex'] ?: $byname($a, $b);
            case 'lessons':
                return fn($a, $b) => $b['lessons'] <=> $a['lessons'] ?: $byname($a, $b);
            default:
                // Recommended: most badges, then teachers with a video, then the best rated, then the busiest.
                return fn($a, $b) => [count($b['badges']), (int) $b['hasvideo'], $b['rating'], $b['lessons']]
                    <=> [count($a['badges']), (int) $a['hasvideo'], $a['rating'], $a['lessons']] ?: $byname($a, $b);
        }
    }

    /**
     * The active badges each teacher wears, in the admin's order.
     *
     * @param int[] $ids
     * @return array id => \stdClass[] badges
     */
    public static function badges_of(array $ids): array {
        global $DB;
        if (!$ids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $out = [];
        $rs = $DB->get_recordset_sql(
            "SELECT bu.id AS buid, bu.userid, b.*
               FROM {" . badge::USERS . "} bu
               JOIN {" . badge::TABLE . "} b ON b.id = bu.badgeid AND b.active = 1
              WHERE bu.awarded = 1 AND bu.userid $insql
           ORDER BY b.sortorder, b.id", $params);
        foreach ($rs as $r) {
            $out[(int) $r->userid][] = $r;
        }
        $rs->close();
        return $out;
    }

    /**
     * Completed private lessons per teacher.
     *
     * @param int[] $ids
     * @return int[] id => count
     */
    public static function lesson_counts(array $ids): array {
        global $DB;
        if (!$ids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $params['st'] = 'completed';
        return array_map('intval', $DB->get_records_sql_menu(
            "SELECT teacherid, COUNT(1) FROM {nit_lesson} WHERE status = :st AND teacherid $insql GROUP BY teacherid",
            $params));
    }

    /**
     * Select fields the admin made search filters.
     *
     * @return \stdClass[] id => field
     */
    public static function filter_fields(): array {
        return array_filter(fields::all(), fn($f) => $f->filter && $f->datatype === 'select');
    }

    /**
     * Is the teacher bookable on that weekday / in that part of the day, in the viewer's timezone?
     *
     * @param teacher_service $service
     * @param int $teacherid
     * @param \DateTimeZone $viewertz
     * @param int|null $day 0 = Sunday; null = any day
     * @param string $time '' = any time
     * @return bool
     */
    public static function available_at(teacher_service $service, int $teacherid, \DateTimeZone $viewertz,
            ?int $day, string $time): bool {
        [$from, $to] = $time !== '' ? self::TIMES[$time] : [0, 1440];
        foreach (self::weekly_intervals($service, $teacherid, $viewertz) as [$d, $start, $end]) {
            if (($day === null || $d === $day) && $start < $to && $end > $from) {
                return true;
            }
        }
        return false;
    }

    /**
     * The teacher's weekly hours moved into another timezone: [[day, fromminute, tominute], ...]
     * (an interval that crosses midnight there is split in two).
     *
     * @param teacher_service $service
     * @param int $teacherid
     * @param \DateTimeZone $tz
     * @return array
     */
    public static function weekly_intervals(teacher_service $service, int $teacherid, \DateTimeZone $tz): array {
        $hours = $service->profile($teacherid)['hours'];
        if (!$hours) {
            foreach (range(0, 6) as $d) {
                $hours[] = ['dayofweek' => $d, 'starttime' => teacher_service::DEFAULT_WINDOW[0],
                    'endtime' => teacher_service::DEFAULT_WINDOW[1]];
            }
        }
        $ttz = teacher_service::timezone($teacherid);
        // A fixed reference week (Sunday 2026-01-04) keeps the conversion deterministic.
        $sunday = new \DateTimeImmutable('2026-01-04 00:00', $ttz);
        $out = [];
        foreach ($hours as $h) {
            [$sh, $sm] = array_map('intval', explode(':', $h['starttime']));
            [$eh, $em] = array_map('intval', explode(':', $h['endtime']));
            $start = $sunday->modify('+' . (int) $h['dayofweek'] . ' days')->setTime($sh, $sm)->setTimezone($tz);
            $end = $sunday->modify('+' . (int) $h['dayofweek'] . ' days')->setTime($eh, $em)->setTimezone($tz);
            $sd = (int) $start->format('w');
            $smin = (int) $start->format('G') * 60 + (int) $start->format('i');
            $ed = (int) $end->format('w');
            $emin = (int) $end->format('G') * 60 + (int) $end->format('i');
            if ($sd === $ed) {
                $out[] = [$sd, $smin, $emin];
            } else {
                $out[] = [$sd, $smin, 1440];
                if ($emin > 0) {
                    $out[] = [$ed, 0, $emin];
                }
            }
        }
        return $out;
    }

    /**
     * A teacher as a search card.
     *
     * @param array $t a row of run()
     * @return array
     */
    public static function card(array $t): array {
        global $PAGE;
        $picture = new \user_picture($t['user']);
        $picture->size = 200;
        return [
            'id' => $t['id'],
            'name' => $t['fullname'],
            'photo' => $picture->get_url($PAGE)->out(false),
            'headline' => format_string($t['headline']),
            'subjects' => array_map(fn($s) => ['name' => format_string($s)], array_slice($t['subjects'], 0, 4)),
            'price' => $t['lesson_flex'],
            'hasrating' => $t['reviews'] > 0,
            'rating' => format_float($t['rating'], 1),
            'reviews' => $t['reviews'],
            'lessons' => $t['lessons'],
            'hasbadges' => !empty($t['badges']),
            'badges' => badge::chips($t['badges']),
            'hasvideo' => $t['hasvideo'],
            'url' => (new \moodle_url('/local/academy/teacher.php', ['id' => $t['id']]))->out(false),
            'bookurl' => self::book_url($t['id'])->out(false),
        ];
    }

    /**
     * Where a student books this teacher.
     *
     * @param int $teacherid
     * @return \moodle_url
     */
    public static function book_url(int $teacherid): \moodle_url {
        return new \moodle_url('/local/nit_lessons/student.php', ['tab' => 'book', 'teacher' => $teacherid]);
    }

    /**
     * Up to $limit teachers for the header search suggestions.
     *
     * @param string $q
     * @param int $limit
     * @return array{total:int, teachers:array, moreurl:string}
     */
    public static function suggest(string $q, int $limit = 4): array {
        $q = trim($q);
        if (\core_text::strlen($q) < 2) {
            return ['total' => 0, 'teachers' => [], 'moreurl' => ''];
        }
        global $USER;
        $result = self::run(self::normalise(['q' => $q]), isloggedin() ? (int) $USER->id : 0, 0, $limit);
        $out = [];
        foreach ($result['teachers'] as $t) {
            $card = self::card($t);
            $out[] = ['id' => $card['id'], 'name' => $card['name'], 'image' => $card['photo'], 'url' => $card['url'],
                'headline' => $card['headline'], 'badge' => $card['hasbadges']]; // The navbar shows a ★.
        }
        return ['total' => $result['total'], 'teachers' => $out,
            'moreurl' => (new \moodle_url('/local/nit_tutors/index.php', ['q' => $q]))->out(false)];
    }
}

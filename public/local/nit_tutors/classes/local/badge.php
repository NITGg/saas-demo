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
 * Teacher badges the admin defines (Local plugins → Teacher applications → Badges), e.g.
 * "Super Tutor", "Verified", "Top rated".
 *
 * A badge is either earned automatically (method auto: every rule that is set must hold —
 * minimum average rating, number of ratings, completed private lessons, maximum no-show rate)
 * or given by a reviewer (method manual). For one teacher a reviewer can also pin any badge on
 * or off, which beats the method. Only approved teachers wear badges. A daily task refreshes
 * them, so an automatic badge is also lost when the rules stop holding.
 *
 * Not Moodle's own badges (core_badges): those are permanent awards (issued once, emailed,
 * exported to a backpack) with course/profile criteria; these come and go with live numbers.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class badge {

    /** Tables. */
    const TABLE = 'nit_tutor_badge';
    const USERS = 'nit_tutor_badge_user';

    /** Methods. */
    const METHOD_AUTO = 'auto';
    const METHOD_MANUAL = 'manual';

    /** Per-teacher modes. */
    const AUTO = 'auto';
    const ON = 'on';
    const OFF = 'off';

    /** Brand Colors roles a badge can wear. */
    const COLORS = ['warning', 'primary', 'success', 'info', 'error', 'secondary'];

    /**
     * The badges in order.
     *
     * @param bool $activeonly
     * @return \stdClass[] id => badge
     */
    public static function all(bool $activeonly = true): array {
        global $DB;
        return $DB->get_records(self::TABLE, $activeonly ? ['active' => 1] : [], 'sortorder, id');
    }

    /**
     * One badge.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Create or update a badge, then refresh who wears it.
     *
     * @param \stdClass $data name, description, color, method, minrating, minreviews, minlessons, maxabsence, active
     * @return int id
     */
    public static function save(\stdClass $data): int {
        global $DB;
        $id = (int) ($data->id ?? 0);
        $record = (object) [
            'name' => trim((string) $data->name),
            'description' => trim((string) ($data->description ?? '')),
            'color' => in_array($data->color ?? '', self::COLORS, true) ? $data->color : self::COLORS[0],
            'method' => ($data->method ?? '') === self::METHOD_MANUAL ? self::METHOD_MANUAL : self::METHOD_AUTO,
            'minrating' => max(0, min(5, round((float) ($data->minrating ?? 0), 1))),
            'minreviews' => max(0, (int) ($data->minreviews ?? 0)),
            'minlessons' => max(0, (int) ($data->minlessons ?? 0)),
            'maxabsence' => max(0, min(100, round((float) ($data->maxabsence ?? 100), 1))),
            'active' => !isset($data->active) || !empty($data->active) ? 1 : 0,
            'timemodified' => time(),
        ];
        if ($record->name === '') {
            throw new \moodle_exception('err_badgename', 'local_nit_tutors');
        }
        if ($id) {
            $record->id = $id;
            $DB->update_record(self::TABLE, $record);
        } else {
            $record->sortorder = (int) $DB->get_field_sql("SELECT COALESCE(MAX(sortorder), 0) + 1 FROM {" . self::TABLE . "}");
            $record->timecreated = time();
            $id = (int) $DB->insert_record(self::TABLE, $record);
        }
        self::refresh_badge($id);
        return $id;
    }

    /**
     * Delete a badge (every teacher loses it).
     *
     * @param int $id
     */
    public static function delete(int $id): void {
        global $DB;
        $DB->delete_records(self::USERS, ['badgeid' => $id]);
        $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * Move a badge one place up or down.
     *
     * @param int $id
     * @param int $direction -1 up, 1 down
     */
    public static function move(int $id, int $direction): void {
        global $DB;
        $list = array_values(self::all(false));
        foreach ($list as $i => $b) {
            if ((int) $b->id === $id && isset($list[$i + $direction])) {
                [$list[$i], $list[$i + $direction]] = [$list[$i + $direction], $list[$i]];
                break;
            }
        }
        foreach ($list as $i => $b) {
            $DB->set_field(self::TABLE, 'sortorder', $i + 1, ['id' => $b->id]);
        }
    }

    /**
     * The "Super Tutor" badge a new site starts with.
     *
     * @return int id
     */
    public static function ensure_default(): int {
        global $DB;
        if ($id = (int) $DB->get_field_sql("SELECT MIN(id) FROM {" . self::TABLE . "}")) {
            return $id;
        }
        return self::save((object) [
            'name' => '{mlang ar}مدرس متميز{mlang}{mlang en}Super Tutor{mlang}',
            'description' => '{mlang ar}تقييم عالٍ من الطلاب، وحصص كثيرة، والتزام بالمواعيد.{mlang}'
                . '{mlang en}Highly rated by students, many lessons, always on time.{mlang}',
            'color' => 'warning', 'method' => self::METHOD_AUTO,
            'minrating' => 4.8, 'minreviews' => 10, 'minlessons' => 30, 'maxabsence' => 2,
        ]);
    }

    /**
     * The teacher's numbers the rules are checked against.
     *
     * @param int $userid
     * @return array{rating: float, reviews: int, lessons: int, absences: int, absencepct: float}
     */
    public static function stats(int $userid): array {
        global $DB;
        $rating = 0.0;
        $reviews = 0;
        if (class_exists('\local_nit_reviews\api')) {
            $agg = \local_nit_reviews\api::get_teacher_aggregate($userid);
            $rating = (float) $agg->avg;
            $reviews = (int) $agg->count;
        }
        $lessons = $DB->count_records('nit_lesson', ['teacherid' => $userid, 'status' => 'completed']);
        $absences = $DB->count_records('nit_lesson', ['teacherid' => $userid, 'status' => 'teacher_absent']);
        $held = $lessons + $absences;
        return ['rating' => $rating, 'reviews' => $reviews, 'lessons' => $lessons, 'absences' => $absences,
            'absencepct' => $held ? round($absences * 100 / $held, 1) : 0.0];
    }

    /**
     * Do these numbers meet the badge's rules? A manual badge is never earned by rules.
     *
     * @param \stdClass $badge
     * @param array $stats from stats()
     * @return bool
     */
    public static function meets(\stdClass $badge, array $stats): bool {
        if ($badge->method !== self::METHOD_AUTO) {
            return false;
        }
        $minrating = (float) $badge->minrating;
        return ($minrating <= 0 || ($stats['reviews'] > 0 && $stats['rating'] >= $minrating))
            && $stats['reviews'] >= (int) $badge->minreviews
            && $stats['lessons'] >= (int) $badge->minlessons
            && $stats['absencepct'] <= (float) $badge->maxabsence;
    }

    /**
     * The reviewer's choice for one teacher and badge (auto when never set).
     *
     * @param int $badgeid
     * @param int $userid
     * @return string
     */
    public static function mode(int $badgeid, int $userid): string {
        global $DB;
        return (string) ($DB->get_field(self::USERS, 'mode', ['badgeid' => $badgeid, 'userid' => $userid]) ?: self::AUTO);
    }

    /**
     * Pin a badge on or off for one teacher, or back to its method.
     *
     * @param int $userid
     * @param int $badgeid
     * @param string $mode auto|on|off
     */
    public static function set_mode(int $userid, int $badgeid, string $mode): void {
        global $DB;
        if (!in_array($mode, [self::AUTO, self::ON, self::OFF], true)) {
            throw new \coding_exception('Bad badge mode ' . $mode);
        }
        if (!self::get($badgeid)) {
            throw new \moodle_exception('invalidrecord', 'error');
        }
        $row = $DB->get_record(self::USERS, ['badgeid' => $badgeid, 'userid' => $userid]);
        if ($row) {
            $DB->set_field(self::USERS, 'mode', $mode, ['id' => $row->id]);
        } else {
            $DB->insert_record(self::USERS, (object) ['badgeid' => $badgeid, 'userid' => $userid, 'mode' => $mode,
                'awarded' => 0, 'timeawarded' => 0]);
        }
        self::refresh($userid);
    }

    /**
     * Recompute every badge of one teacher.
     *
     * @param int $userid
     * @return int[] ids of the badges they wear now
     */
    public static function refresh(int $userid): array {
        global $DB;
        $approved = application::is_approved($userid);
        $stats = null;
        $rows = $DB->get_records(self::USERS, ['userid' => $userid], '', 'badgeid, id, mode, awarded');
        $wear = [];
        foreach (self::all() as $b) {
            $row = $rows[$b->id] ?? null;
            $mode = $row ? $row->mode : self::AUTO;
            if ($approved && $mode === self::AUTO && $b->method === self::METHOD_AUTO && $stats === null) {
                $stats = self::stats($userid);
            }
            $on = $approved && ($mode === self::ON || ($mode === self::AUTO && $stats !== null && self::meets($b, $stats)));
            if ($on) {
                $wear[] = (int) $b->id;
            }
            if ($row && (bool) $row->awarded !== $on) {
                $DB->update_record(self::USERS, (object) ['id' => $row->id, 'awarded' => $on ? 1 : 0,
                    'timeawarded' => $on ? time() : 0]);
            } else if (!$row && $on) {
                $DB->insert_record(self::USERS, (object) ['badgeid' => $b->id, 'userid' => $userid, 'mode' => self::AUTO,
                    'awarded' => 1, 'timeawarded' => time()]);
            }
        }
        return $wear;
    }

    /**
     * Recompute one badge for every teacher who has an application.
     *
     * @param int $badgeid
     */
    private static function refresh_badge(int $badgeid): void {
        global $DB;
        foreach ($DB->get_fieldset_select(application::TABLE, 'userid', 'status = ?', [application::APPROVED]) as $userid) {
            self::refresh((int) $userid);
        }
        // Teachers no longer approved keep no badge either.
        $DB->set_field_select(self::USERS, 'awarded', 0, 'badgeid = ? AND userid NOT IN (SELECT userid FROM {'
            . application::TABLE . '} WHERE status = ?)', [$badgeid, application::APPROVED]);
    }

    /**
     * The active badges a teacher wears now, in order.
     *
     * @param int $userid
     * @return \stdClass[]
     */
    public static function held(int $userid): array {
        global $DB;
        return array_values($DB->get_records_sql(
            "SELECT b.*
               FROM {" . self::TABLE . "} b
               JOIN {" . self::USERS . "} bu ON bu.badgeid = b.id AND bu.userid = :uid AND bu.awarded = 1
              WHERE b.active = 1
           ORDER BY b.sortorder, b.id", ['uid' => $userid]));
    }

    /**
     * Badges as template chips: [{name, color, description}].
     *
     * @param \stdClass[] $badges
     * @return array
     */
    public static function chips(array $badges): array {
        return array_values(array_map(fn($b) => ['name' => format_string($b->name),
            'color' => $b->color, 'description' => format_string((string) $b->description)], $badges));
    }

    /**
     * How many teachers wear each badge.
     *
     * @return int[] badgeid => count
     */
    public static function holder_counts(): array {
        global $DB;
        return array_map('intval', $DB->get_records_sql_menu(
            "SELECT badgeid, COUNT(1) FROM {" . self::USERS . "} WHERE awarded = 1 GROUP BY badgeid"));
    }

    /**
     * Refresh every approved teacher (the daily task); a teacher no longer approved loses every badge.
     *
     * @return int how many badges are worn
     */
    public static function refresh_all(): int {
        global $DB;
        $n = 0;
        foreach ($DB->get_fieldset_select(application::TABLE, 'userid', 'status = ?', [application::APPROVED]) as $userid) {
            $n += count(self::refresh((int) $userid));
        }
        $DB->set_field_select(self::USERS, 'awarded', 0, 'userid NOT IN (SELECT userid FROM {' . application::TABLE
            . '} WHERE status = ?)', [application::APPROVED]);
        return $n;
    }

    /**
     * A badge's rules as one readable line.
     *
     * @param \stdClass $badge
     * @return string
     */
    public static function rules_text(\stdClass $badge): string {
        if ($badge->method === self::METHOD_MANUAL) {
            return get_string('badgemethod_manual_desc', 'local_nit_tutors');
        }
        $parts = [];
        if ((float) $badge->minrating > 0) {
            $parts[] = get_string('rule_rating', 'local_nit_tutors', format_float($badge->minrating, 1));
        }
        if ((int) $badge->minreviews > 0) {
            $parts[] = get_string('rule_reviews', 'local_nit_tutors', (int) $badge->minreviews);
        }
        if ((int) $badge->minlessons > 0) {
            $parts[] = get_string('rule_lessons', 'local_nit_tutors', (int) $badge->minlessons);
        }
        if ((float) $badge->maxabsence < 100) {
            $parts[] = get_string('rule_absence', 'local_nit_tutors', format_float($badge->maxabsence, 1));
        }
        return $parts ? implode('، ', $parts) : get_string('rule_none', 'local_nit_tutors');
    }
}

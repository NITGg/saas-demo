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

namespace local_nit_reports\report;

use local_nit_reports\data;
use local_nit_reports\scope;

/**
 * Video watching: one row per video lesson (Vimeo / VdoCipher) — how many of the
 * course's current students started it, finished it (90% or more played), and how
 * much of it they watched on average (local_nit_videoprogress). Students who left
 * the course are not counted. The period filters by the last time it was watched.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class videos extends base {

    public static function key(): string {
        return 'videos';
    }

    public static function level(): string {
        return scope::TEACHING;
    }

    public static function available(): bool {
        return data::has_videos();
    }

    public function columns(): array {
        return [
            'video' => self::str('col_video'),
            'course' => get_string('course'),
            'provider' => self::str('col_provider'),
            'students' => self::str('col_students'),
            'started' => self::str('col_started'),
            'finished' => self::str('col_finished'),
            'avg' => self::str('col_avgwatched'),
            'last' => self::str('col_lastwatched'),
        ];
    }

    public function sortable(): array {
        return array_fill_keys(array_keys($this->columns()), true);
    }

    /**
     * The video lessons in scope: "FROM … WHERE …" over cm, m, c, and the SQL of the video name.
     *
     * @return array{0:string, 1:array, 2:string}
     */
    private function from(): array {
        global $DB;
        $mods = data::video_modules();
        if (!$mods) {
            return ['FROM {course_modules} cm WHERE 1 = 0', [], "''"];
        }
        [$msql, $params] = $DB->get_in_or_equal($mods, SQL_PARAMS_NAMED, 'vm');
        [$cwhere, $cparams] = $this->course_where('cm.course');
        $joins = '';
        $names = [];
        foreach ($mods as $i => $mod) {
            $joins .= " LEFT JOIN {{$mod}} i$i ON m.name = '$mod' AND i$i.id = cm.instance";
            $names[] = "i$i.name";
        }
        $name = 'COALESCE(' . implode(', ', $names) . ')';
        $search = '1 = 1';
        if ($this->f->q !== '') {
            $search = $DB->sql_like($name, ':vq', false);
            $params['vq'] = '%' . $DB->sql_like_escape($this->f->q) . '%';
        }
        return ["FROM {course_modules} cm
                 JOIN {modules} m ON m.id = cm.module AND m.name $msql
                 JOIN {course} c ON c.id = cm.course
                 $joins
                WHERE cm.deletioninprogress = 0 AND $cwhere AND $search", $params + $cparams, $name];
    }

    /**
     * The current students of the courses in scope (userid, courseid).
     *
     * @return array{0:string, 1:array}
     */
    private function members(): array {
        [$cwhere, $cparams] = $this->course_where('c.id');
        return data::members_sql(data::student_roles(), $cwhere, $cparams);
    }

    public function summary(): array {
        global $DB;
        [$from, $params] = $this->from();
        $videos = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        [$period, $pparams] = $this->f->period_sql('p.timemodified');
        [$members, $mparams] = $this->members();
        $stats = $DB->get_record_sql("SELECT COUNT(1) AS views, COUNT(DISTINCT p.userid) AS viewers, AVG(p.percent) AS avgpct
                                        FROM {nit_video_progress} p
                                        JOIN ($members) sm ON sm.userid = p.userid AND sm.courseid = p.courseid
                                       WHERE p.percent > 0 AND $period AND p.cmid IN (SELECT cm.id $from)",
            $mparams + $pparams + $params);
        return [
            ['label' => self::str('card_videos'), 'value' => self::num($videos)],
            ['label' => self::str('card_viewers'), 'value' => self::num($stats->viewers ?? 0)],
            ['label' => self::str('card_avgwatched'), 'value' => $stats && $stats->views ? self::pct((float) $stats->avgpct) : '—'],
        ];
    }

    public function rows(int $page, int $perpage): array {
        global $DB;
        // Every video in scope is built, then sorted and cut to the page.
        [$from, $params, $name] = $this->from();
        $cms = $DB->get_records_sql("SELECT cm.id, cm.course, m.name AS modname, $name AS videoname, c.fullname AS coursename
                                     $from ORDER BY c.fullname, cm.section, cm.id", $params);
        if (!$cms) {
            return ['total' => 0, 'rows' => []];
        }
        [$cmsql, $cmparams] = $DB->get_in_or_equal(array_keys($cms), SQL_PARAMS_NAMED, 'vc');
        [$period, $pparams] = $this->f->period_sql('p.timemodified');
        [$members, $mparams] = $this->members();
        $finished = data::FINISHED;
        $stats = $DB->get_records_sql("SELECT p.cmid, SUM(CASE WHEN p.percent > 0 THEN 1 ELSE 0 END) AS started,
                    SUM(CASE WHEN p.percent >= $finished THEN 1 ELSE 0 END) AS finished,
                    AVG(CASE WHEN p.percent > 0 THEN p.percent END) AS avgpct, MAX(p.timemodified) AS lastat
               FROM {nit_video_progress} p
               JOIN ($members) sm ON sm.userid = p.userid AND sm.courseid = p.courseid
              WHERE p.cmid $cmsql AND $period GROUP BY p.cmid", $mparams + $cmparams + $pparams);
        $students = $DB->get_records_sql_menu("SELECT m.courseid, COUNT(1) FROM ($members) m GROUP BY m.courseid", $mparams);

        $rows = [];
        foreach ($cms as $cm) {
            $st = $stats[$cm->id] ?? null;
            $n = (int) ($students[$cm->course] ?? 0);
            $started = (int) ($st->started ?? 0);
            $avg = $st && $st->avgpct !== null ? (float) $st->avgpct : null;
            $rows[] = [
                'video' => self::cname($cm->videoname),
                'course' => self::cname($cm->coursename),
                'provider' => $cm->modname === 'vdocipher' ? 'VdoCipher' : 'Vimeo',
                'students' => self::num($n),
                'started' => self::num($started) . ($n ? ' (' . self::pct(100 * $started / $n) . ')' : ''),
                'finished' => self::num($st->finished ?? 0),
                'avg' => self::pct($avg),
                'last' => self::date((int) ($st->lastat ?? 0), true),
                '_sort' => ['students' => $n, 'started' => $started, 'finished' => (int) ($st->finished ?? 0),
                    'avg' => $avg ?? -1.0, 'last' => (int) ($st->lastat ?? 0)],
            ];
        }
        return $this->finish($rows, $page, $perpage);
    }
}

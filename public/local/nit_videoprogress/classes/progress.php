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

namespace local_nit_videoprogress;

/**
 * How much of a video lesson a student has watched, and where to resume.
 *
 * The video is cut into 100 equal slices (1% each). The player marks a slice
 * as watched only while it actually plays through it — jumping ahead does not
 * count — so "percent" is the share of the video really seen, never just the
 * furthest point reached. "position" is simply the last place the student was,
 * for resuming.
 *
 * @package    local_nit_videoprogress
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress {

    /** Number of slices the video is cut into (one per percent). */
    public const SLICES = 100;

    /** Video lesson module types this plugin tracks. */
    public const PROVIDERS = ['vimeo', 'vdocipher'];

    /** Closer than this to the end (seconds) counts as finished: resume from the start. */
    public const END_MARGIN = 10;

    /** Earlier than this (seconds) is not worth resuming. */
    public const MIN_RESUME = 5;

    /**
     * Save a progress report from the player.
     *
     * @param int $userid
     * @param \cm_info|\stdClass $cm the video lesson (needs id, course, modname)
     * @param int $position current playback position, seconds
     * @param int $duration video length in seconds (0 when the player does not know yet)
     * @param int[] $slices slice numbers (0-99) played through since the last report
     * @return \stdClass the saved row
     */
    public static function record(int $userid, $cm, int $position, int $duration, array $slices): \stdClass {
        global $DB;
        $now = time();
        $row = $DB->get_record('nit_video_progress', ['userid' => $userid, 'cmid' => $cm->id]);
        if (!$row) {
            $row = (object) [
                'userid' => $userid,
                'cmid' => (int) $cm->id,
                'courseid' => (int) $cm->course,
                'provider' => (string) $cm->modname,
                'position' => 0,
                'duration' => 0,
                'watched' => str_repeat('0', self::SLICES),
                'percent' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
        }

        // A longer duration wins: some players report 0 or a rounded-down value first.
        $duration = max(0, $duration);
        if ($duration > (int) $row->duration) {
            $row->duration = $duration;
        }
        $row->position = (int) $row->duration > 0
            ? min(max(0, $position), (int) $row->duration)
            : max(0, $position);

        $mask = str_pad(substr((string) $row->watched, 0, self::SLICES), self::SLICES, '0');
        foreach ($slices as $slice) {
            $slice = (int) $slice;
            if ($slice >= 0 && $slice < self::SLICES) {
                $mask[$slice] = '1';
            }
        }
        $row->watched = $mask;
        $row->percent = substr_count($mask, '1');
        $row->timemodified = $now;

        if (empty($row->id)) {
            try {
                $row->id = $DB->insert_record('nit_video_progress', $row);
            } catch (\dml_write_exception $e) {
                // Two reports raced (two tabs): the other insert won; merge into it.
                if (!$DB->record_exists('nit_video_progress', ['userid' => $userid, 'cmid' => $cm->id])) {
                    throw $e;
                }
                return self::record($userid, $cm, $position, $duration, $slices);
            }
        } else {
            $DB->update_record('nit_video_progress', $row);
        }
        return $row;
    }

    /**
     * A student's progress on one video lesson.
     *
     * @param int $userid
     * @param int $cmid
     * @return \stdClass|null
     */
    public static function get(int $userid, int $cmid): ?\stdClass {
        global $DB;
        return $DB->get_record('nit_video_progress', ['userid' => $userid, 'cmid' => $cmid]) ?: null;
    }

    /**
     * Where the player should start: the last position, unless it is too close
     * to the start (nothing to resume) or to the end (finished — watch again).
     *
     * @param \stdClass|null $row
     * @return int seconds
     */
    public static function resume_position(?\stdClass $row): int {
        if (!$row) {
            return 0;
        }
        $position = (int) $row->position;
        $duration = (int) $row->duration;
        if ($position < self::margin($duration, self::MIN_RESUME)) {
            return 0;
        }
        if ($duration > 0 && $position >= $duration - self::margin($duration, self::END_MARGIN)) {
            return 0;
        }
        return $position;
    }

    /**
     * A start / end margin scaled to the video: the full margin for a long video,
     * a tenth of the video (at least 1 s) for a short one — with fixed 5 s / 10 s
     * margins a 16-second video could only resume between second 5 and 6.
     * tracker.js uses the same rule.
     *
     * @param int $duration seconds (0 = unknown: the full margin)
     * @param int $max MIN_RESUME or END_MARGIN
     * @return int seconds
     */
    public static function margin(int $duration, int $max): int {
        return $duration > 0 ? min($max, max(1, intdiv($duration, 10))) : $max;
    }

    /**
     * Watched percent per video lesson of one student in one course.
     *
     * @param int $userid
     * @param int $courseid
     * @return array<int, int> cmid => percent (only lessons the student has started)
     */
    public static function course_percents(int $userid, int $courseid): array {
        global $DB;
        return array_map('intval', $DB->get_records_menu('nit_video_progress',
            ['userid' => $userid, 'courseid' => $courseid], '', 'cmid, percent'));
    }

    /**
     * Every video lesson of a student's courses with how much was watched —
     * for the student's own pages and the guardian page.
     *
     * Lists all visible video lessons of the given courses, including ones not
     * started yet (0%), in course order.
     *
     * @param int $userid
     * @param int[] $courseids
     * @return array list of [courseid, coursename, lessons => [cmid, name, percent, position, duration, url]]
     */
    public static function overview(int $userid, array $courseids): array {
        global $DB;
        $out = [];
        foreach ($courseids as $courseid) {
            $course = $DB->get_record('course', ['id' => $courseid]);
            if (!$course) {
                continue;
            }
            $percents = $DB->get_records('nit_video_progress', ['userid' => $userid, 'courseid' => $courseid],
                '', 'cmid, percent, position, duration, timemodified');
            $modinfo = get_fast_modinfo($course, $userid);
            $lessons = [];
            foreach ($modinfo->get_cms() as $cm) {
                if (!in_array($cm->modname, self::PROVIDERS, true) || !$cm->visible || $cm->deletioninprogress) {
                    continue;
                }
                $p = $percents[$cm->id] ?? null;
                $lessons[] = [
                    'cmid' => (int) $cm->id,
                    'name' => format_string($cm->name, true, ['context' => $cm->context]),
                    'percent' => $p ? (int) $p->percent : 0,
                    'position' => $p ? (int) $p->position : 0,
                    'duration' => $p ? (int) $p->duration : 0,
                    'lastwatched' => $p ? (int) $p->timemodified : 0,
                    'url' => $cm->url ? $cm->url->out(false) : '',
                ];
            }
            if ($lessons) {
                $total = array_sum(array_column($lessons, 'percent'));
                $out[] = [
                    'courseid' => (int) $course->id,
                    'coursename' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
                    'percent' => (int) round($total / count($lessons)),
                    'lessons' => $lessons,
                ];
            }
        }
        return $out;
    }

    /**
     * The video lesson behind a cmid, after the same access checks as the
     * local_nit_videoprogress_save web service: a vimeo/vdocipher activity,
     * the user is logged in to its course (enrolled), the lesson is available
     * to them, and they hold mod/<provider>:view.
     *
     * @param int $cmid
     * @return array [course, cm_info]
     * @throws \moodle_exception err_notvideo, require_login_exception, required_capability_exception
     */
    public static function require_lesson(int $cmid): array {
        try {
            [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        } catch (\dml_missing_record_exception $e) {
            throw new \moodle_exception('err_notvideo', 'local_nit_videoprogress');
        }
        if (!in_array($cm->modname, self::PROVIDERS, true)) {
            throw new \moodle_exception('err_notvideo', 'local_nit_videoprogress');
        }
        require_login($course, false, $cm, false, true);
        require_capability('mod/' . $cm->modname . ':view', \context_module::instance($cm->id));
        return [$course, $cm];
    }

    /**
     * Parse the slices a player reports: comma-separated numbers 0-99
     * (e.g. "12,13,14"). Anything else is rejected so a broken client is noticed.
     *
     * @param string $raw
     * @return int[]
     * @throws \moodle_exception err_invalidslices
     */
    public static function parse_slices(string $raw): array {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        if (!preg_match('/^\d{1,3}(,\d{1,3})*$/', $raw)) {
            throw new \moodle_exception('err_invalidslices', 'local_nit_videoprogress');
        }
        $out = [];
        foreach (explode(',', $raw) as $slice) {
            $slice = (int) $slice;
            if ($slice >= self::SLICES) {
                throw new \moodle_exception('err_invalidslices', 'local_nit_videoprogress');
            }
            $out[$slice] = $slice;
        }
        return array_values($out);
    }

    /**
     * One lesson's progress as the app sees it (a lesson not started yet is all zeros).
     *
     * @param \cm_info $cm
     * @param \stdClass|null $row the nit_video_progress row, or null
     * @return array
     */
    public static function export(\cm_info $cm, ?\stdClass $row): array {
        $mask = $row ? str_pad(substr((string) $row->watched, 0, self::SLICES), self::SLICES, '0')
            : str_repeat('0', self::SLICES);
        $slices = [];
        for ($i = 0; $i < self::SLICES; $i++) {
            if ($mask[$i] === '1') {
                $slices[] = $i;
            }
        }
        return [
            'cmid' => (int) $cm->id,
            'courseid' => (int) $cm->course,
            'provider' => (string) $cm->modname,
            'name' => format_string($cm->name, true, ['context' => $cm->context]),
            'started' => (bool) $row,
            'percent' => $row ? (int) $row->percent : 0,
            'position' => $row ? (int) $row->position : 0,
            'duration' => $row ? (int) $row->duration : 0,
            'resume_position' => self::resume_position($row),
            'watched' => $mask,
            'slices' => $slices,
            'lastwatched' => $row ? (int) $row->timemodified : 0,
        ];
    }

    /**
     * Seconds as m:ss or h:mm:ss.
     *
     * @param int $seconds
     * @return string
     */
    public static function format_time(int $seconds): string {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }
}

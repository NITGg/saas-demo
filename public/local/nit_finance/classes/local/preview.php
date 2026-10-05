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

namespace local_nit_finance\local;

use local_nit_finance\exception\finance_exception;
use moodle_url;

/**
 * Free preview of a paid video lesson: the teacher sets "free minutes" on the
 * activity, and anyone logged in who may not open the lesson yet (not bought,
 * or not enrolled in the paid course) can watch that many minutes from the start.
 *
 * The cut is made by the player (web: js/preview.js, app: the client), so it is
 * a sales teaser, not DRM: the full stream is still the provider's own embed.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class preview {

    /** Activity types that can have a free preview. */
    public const VIDEO_MODS = ['vimeo', 'vdocipher'];

    /** Longest preview a teacher may set, in minutes. */
    public const MAX_MINUTES = 600;

    /** @var string */
    private const TABLE = 'nit_cm_preview';

    /**
     * Can this kind of activity have a free preview?
     *
     * @param string $modname
     * @return bool
     */
    public static function is_video(string $modname): bool {
        return in_array($modname, self::VIDEO_MODS, true);
    }

    /**
     * Free seconds set on an activity.
     *
     * @param int $cmid
     * @return int 0 = no preview
     */
    public static function seconds(int $cmid): int {
        global $DB;
        return (int) $DB->get_field(self::TABLE, 'seconds', ['cmid' => $cmid]);
    }

    /**
     * Free seconds of every activity of a course that has a preview.
     *
     * @param int $courseid
     * @return array<int, int> cmid => seconds
     */
    public static function course_seconds(int $courseid): array {
        global $DB;
        return array_map('intval', $DB->get_records_menu(self::TABLE, ['courseid' => $courseid], '', 'cmid, seconds'));
    }

    /**
     * Set or clear (0) an activity's free preview.
     *
     * @param int $cmid
     * @param int $courseid
     * @param int $seconds
     * @return void
     */
    public static function set(int $cmid, int $courseid, int $seconds): void {
        global $DB, $USER;
        $existing = $DB->get_record(self::TABLE, ['cmid' => $cmid]);
        if ($seconds <= 0) {
            if ($existing) {
                $DB->delete_records(self::TABLE, ['id' => $existing->id]);
            }
            return;
        }
        $now = time();
        if ($existing) {
            $existing->seconds = $seconds;
            $existing->courseid = $courseid;
            $existing->usermodified = (int) $USER->id;
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
            return;
        }
        $DB->insert_record(self::TABLE, (object) [
            'cmid' => $cmid, 'courseid' => $courseid, 'seconds' => $seconds,
            'usermodified' => (int) $USER->id, 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * Remove the preview of a deleted activity.
     *
     * @param int $cmid
     * @return void
     */
    public static function delete(int $cmid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
    }

    /**
     * Read "free minutes" typed on the settings form.
     *
     * @param string $raw e.g. "", "3", "2.5"
     * @return int|null seconds (0 = no preview), null when not a valid number of minutes
     */
    public static function parse_minutes(string $raw): ?int {
        $raw = str_replace(',', '.', trim($raw));
        if ($raw === '') {
            return 0;
        }
        if (!preg_match('/^\d+(\.\d+)?$/', $raw)) {
            return null;
        }
        $minutes = (float) $raw;
        if ($minutes > self::MAX_MINUTES) {
            return null;
        }
        return (int) round($minutes * 60);
    }

    /**
     * Minutes to show back on the settings form ("" when there is no preview).
     *
     * @param int $seconds
     * @return string
     */
    public static function format_minutes(int $seconds): string {
        if ($seconds <= 0) {
            return '';
        }
        return rtrim(rtrim(number_format($seconds / 60, 2, '.', ''), '0'), '.');
    }

    /**
     * The free seconds this user may watch of an activity — 0 when the activity
     * has no preview, is not a video, or the user can already open all of it.
     *
     * @param int $userid
     * @param \cm_info|\stdClass $cm needs id, course and modname
     * @param array|null $state from {@see access::course_state()}, to reuse it across a course
     * @param int|null $seconds the activity's free seconds, when already known
     * @return int
     */
    public static function for_user(int $userid, $cm, ?array $state = null, ?int $seconds = null): int {
        if ($userid <= 0 || !self::is_video((string) $cm->modname)) {
            return 0;
        }
        $seconds = $seconds ?? self::seconds((int) $cm->id);
        if ($seconds <= 0) {
            return 0;
        }
        $state = $state ?? access::course_state($userid, (int) $cm->course);
        if ($state['staff']) {
            return 0;
        }
        if (access::cm_open($state, (int) $cm->id)
                && is_enrolled(\context_course::instance((int) $cm->course), $userid, '', true)) {
            return 0;
        }
        return $seconds;
    }

    /**
     * The preview page of an activity.
     *
     * @param int $cmid
     * @return moodle_url
     */
    public static function url(int $cmid): moodle_url {
        return new moodle_url('/local/nit_finance/preview.php', ['cmid' => $cmid]);
    }

    /**
     * Send a logged-in user who may only watch the preview to the preview page.
     * Video lesson pages call this before require_login(), which would otherwise
     * send a student who is not enrolled to the course checkout.
     *
     * @param \cm_info|\stdClass $cm
     * @return void
     */
    public static function redirect_if_preview($cm): void {
        global $USER;
        if (!isloggedin() || isguestuser()) {
            return;
        }
        try {
            $seconds = self::for_user((int) $USER->id, $cm);
        } catch (\dml_exception $e) {
            return; // Table not installed yet (mid-upgrade).
        }
        if ($seconds > 0) {
            redirect(self::url((int) $cm->id));
        }
    }

    /**
     * What the player needs to play the preview: the provider's embed (Vimeo) or
     * a fresh watermarked OTP (VdoCipher), plus the free seconds. No access check:
     * the caller has checked {@see self::for_user()}.
     *
     * @param \cm_info|\stdClass $cm
     * @param \stdClass $user the viewer
     * @param int $seconds
     * @return array provider, preview_seconds, videoid, then embedurl | otp, playbackInfo, watermark, ttl
     */
    public static function playback($cm, \stdClass $user, int $seconds): array {
        $out = ['provider' => (string) $cm->modname, 'preview_seconds' => $seconds];
        if ($cm->modname === 'vimeo' && class_exists('\local_vimeo\playback_service')) {
            $row = \local_vimeo\playback_service::video_row((int) $cm->id);
            if ($row) {
                return $out + \local_vimeo\playback_service::embed($row);
            }
        }
        if ($cm->modname === 'vdocipher' && class_exists('\local_vdocipher\playback_service')) {
            $row = \local_vdocipher\playback_service::video_row((int) $cm->id);
            if ($row) {
                return $out + \local_vdocipher\playback_service::mint((string) $row->videoid, $user);
            }
        }
        throw new finance_exception('err_nopreviewvideo');
    }
}

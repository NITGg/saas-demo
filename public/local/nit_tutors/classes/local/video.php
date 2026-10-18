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
 * The teacher's intro video: an uploaded file (file area 'video' of the teacher's user context)
 * or a YouTube / Vimeo link. A new or changed video waits for an admin's approval; only an
 * approved video is shown to visitors.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class video {

    /** File area. */
    const AREA = 'video';

    /** Video types. */
    const UPLOAD = 'upload';
    const LINK = 'link';

    /** Accepted upload types. */
    const ACCEPTED = ['.mp4', '.webm', '.mov', '.m4v'];

    /**
     * Max upload size in bytes (admin setting, MB; default 200).
     *
     * @return int
     */
    public static function max_bytes(): int {
        return max(1, application::setting_int('videomaxmb', 200)) * 1024 * 1024;
    }

    /**
     * The embeddable player URL for a YouTube / Vimeo link, or null when the link is neither.
     *
     * @param string $url
     * @return string|null
     */
    public static function embed_url(string $url): ?string {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        $patterns = [
            '~^https?://(?:www\.|m\.)?youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{11})~i',
            '~^https?://(?:www\.)?youtube(?:-nocookie)?\.com/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})~i',
            '~^https?://youtu\.be/([A-Za-z0-9_-]{11})~i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $url, $m)) {
                return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0';
            }
        }
        if (preg_match('~^https?://(?:www\.)?vimeo\.com/(\d+)(?:/([A-Za-z0-9]+))?~i', $url, $m)
                || preg_match('~^https?://player\.vimeo\.com/video/(\d+)(?:\?(?:.*&)?h=([A-Za-z0-9]+))?~i', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1] . (!empty($m[2]) ? '?h=' . $m[2] : '');
        }
        return null;
    }

    /**
     * The uploaded video file, if any.
     *
     * @param int $userid
     * @return \stored_file|null
     */
    public static function file(int $userid): ?\stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files(\context_user::instance($userid)->id, 'local_nit_tutors', self::AREA, 0,
            'filename', false);
        return $files ? reset($files) : null;
    }

    /**
     * Does the teacher have a video (any review state)?
     *
     * @param int $userid
     * @param \stdClass|null $row the nit_tutor row
     * @return bool
     */
    public static function has_video(int $userid, ?\stdClass $row): bool {
        if (!$row) {
            return false;
        }
        if ($row->videotype === self::LINK) {
            return self::embed_url((string) $row->videourl) !== null;
        }
        return $row->videotype === self::UPLOAD && self::file($userid) !== null;
    }

    /**
     * Record a new video choice. A change sends the video for review (approved teachers keep being
     * listed meanwhile, without a video).
     *
     * @param int $userid
     * @param string $type '' | upload | link
     * @param string $url the link (type link)
     * @param bool $filechanged the upload was replaced in this save
     * @throws \moodle_exception badvideourl
     */
    public static function save(int $userid, string $type, string $url, bool $filechanged): void {
        global $DB;
        $row = application::ensure($userid);
        $type = in_array($type, [self::UPLOAD, self::LINK], true) ? $type : '';
        $url = $type === self::LINK ? trim($url) : '';
        if ($type === self::LINK && self::embed_url($url) === null) {
            throw new \moodle_exception('err_badvideourl', 'local_nit_tutors');
        }
        $changed = $type !== (string) $row->videotype
            || ($type === self::LINK && $url !== (string) $row->videourl)
            || ($type === self::UPLOAD && $filechanged);
        if (!$changed) {
            return;
        }
        if ($type !== self::UPLOAD) {
            get_file_storage()->delete_area_files(\context_user::instance($userid)->id, 'local_nit_tutors', self::AREA);
        }
        $row->videotype = $type;
        $row->videourl = $url;
        $row->videostatus = self::has_video($userid, $row) ? 'pending' : '';
        $row->videonote = '';
        $row->timemodified = time();
        // The reviewers hear about an approved teacher's changes from teacher_input::save() (one message).
        $DB->update_record(application::TABLE, $row);
    }

    /**
     * The reviewer's decision on the video.
     *
     * @param int $userid
     * @param bool $approve
     * @param string $note reason, shown to the teacher when refused
     */
    public static function review(int $userid, bool $approve, string $note = ''): void {
        global $DB;
        $row = application::ensure($userid);
        if (!self::has_video($userid, $row)) {
            return;
        }
        $row->videostatus = $approve ? 'approved' : 'rejected';
        $row->videonote = \core_text::substr(trim($note), 0, 255);
        $row->timemodified = time();
        $DB->update_record(application::TABLE, $row);
        notifier::video_reviewed($userid, $approve, $note);
    }

    /**
     * What a page needs to play the video, or null when there is nothing to play.
     *
     * @param int $userid
     * @param bool $approvedonly true for visitors: only an approved video
     * @return array|null {embed: string|null, src: string|null, mimetype: string}
     */
    public static function player(int $userid, bool $approvedonly = true): ?array {
        $row = application::get($userid);
        if (!$row || !self::has_video($userid, $row) || ($approvedonly && $row->videostatus !== 'approved')) {
            return null;
        }
        if ($row->videotype === self::LINK) {
            return ['embed' => self::embed_url((string) $row->videourl), 'src' => null, 'mimetype' => ''];
        }
        $file = self::file($userid);
        $src = \moodle_url::make_pluginfile_url($file->get_contextid(), 'local_nit_tutors', self::AREA, 0,
            $file->get_filepath(), $file->get_filename())->out(false);
        return ['embed' => null, 'src' => $src, 'mimetype' => $file->get_mimetype()];
    }
}

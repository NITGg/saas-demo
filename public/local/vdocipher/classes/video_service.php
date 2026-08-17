<?php
namespace local_vdocipher;

defined('MOODLE_INTERNAL') || die();

/**
 * Business logic for VdoCipher videos: create (upload credentials), refresh
 * status, list, attach to an activity, and delete — each guarded by the
 * local/vdocipher:manage capability in the relevant context.
 *
 * Playback (OTP + watermark) lives in {@see playback_service}; this class is the
 * teacher/manager CRUD side.
 */
class video_service {

    /** @var string DB table. */
    const TABLE = 'local_vdocipher_videos';

    /**
     * Obtain S3 upload credentials for a new video and record a pending row.
     *
     * The caller uploads the file bytes straight to VdoCipher's S3 link using the
     * returned payload — the bytes never pass through Moodle.
     *
     * @param string $title  human title shown in the dashboard
     * @param int $courseid  course the video belongs to (0 = site-level, admins only)
     * @param int $cmid      course module to attach to now (0 = attach later)
     * @return array ['videoid'=>…, 'upload'=>[…S3 payload…], 'rowid'=>…]
     */
    public static function create_upload(string $title, int $courseid = 0, int $cmid = 0): array {
        global $DB, $USER;

        self::require_manage(self::context_for($courseid));

        $title = trim($title) !== '' ? trim($title) : 'Untitled video';

        $client = new api_client();
        $result = $client->get_upload_credentials($title);

        $videoid = (string) ($result['videoId'] ?? '');
        if ($videoid === '') {
            throw new api_exception('VdoCipher did not return a videoId', 0, json_encode($result));
        }

        $now = time();
        $record = (object) [
            'videoid'      => $videoid,
            'cmid'         => $cmid,
            'courseid'     => $courseid,
            'title'        => \core_text::substr($title, 0, 255),
            'status'       => 'PRE-Upload',
            'length'       => 0,
            'usermodified' => (int) $USER->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record(self::TABLE, $record);

        return [
            'videoid' => $videoid,
            'rowid'   => (int) $record->id,
            'upload'  => $result['clientPayload'] ?? $result,
        ];
    }

    /**
     * Re-fetch a video's status/length/title from VdoCipher and update our row.
     *
     * @param string $videoid
     * @return array normalized ['videoid','status','length','title']
     */
    public static function refresh_status(string $videoid): array {
        global $DB;

        $row = self::get_row($videoid);
        self::require_manage(self::context_for((int) $row->courseid));

        $client = new api_client();
        $video  = $client->get_video($videoid);

        $row->status       = (string) ($video['status'] ?? $row->status);
        $row->length       = (int) ($video['length'] ?? $row->length);
        $row->title        = \core_text::substr((string) ($video['title'] ?? $row->title), 0, 255);
        $row->timemodified = time();
        $DB->update_record(self::TABLE, $row);

        return [
            'videoid' => $videoid,
            'status'  => $row->status,
            'length'  => (int) $row->length,
            'title'   => $row->title,
        ];
    }

    /**
     * List videos, optionally scoped to a course.
     *
     * @param int $courseid 0 = all (site-level manage required)
     * @return array list of row dicts
     */
    public static function list_videos(int $courseid = 0): array {
        global $DB;

        self::require_manage(self::context_for($courseid));

        $conditions = $courseid ? ['courseid' => $courseid] : [];
        $rows = $DB->get_records(self::TABLE, $conditions, 'timecreated DESC');

        return array_values(array_map(static function ($r) {
            return [
                'id'       => (int) $r->id,
                'videoid'  => $r->videoid,
                'cmid'     => (int) $r->cmid,
                'courseid' => (int) $r->courseid,
                'title'    => $r->title,
                'status'   => $r->status,
                'length'   => (int) $r->length,
            ];
        }, $rows));
    }

    /**
     * Attach an existing video to a course module (used by the resource2 form).
     *
     * @param string $videoid
     * @param int $cmid
     * @return array the updated row dict
     */
    public static function attach(string $videoid, int $cmid): array {
        global $DB;

        $row = self::get_row($videoid);
        $cm  = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);

        self::require_manage(\context_course::instance($cm->course));

        $row->cmid         = $cmid;
        $row->courseid     = (int) $cm->course;
        $row->timemodified = time();
        $DB->update_record(self::TABLE, $row);

        return [
            'videoid'  => $videoid,
            'cmid'     => (int) $row->cmid,
            'courseid' => (int) $row->courseid,
        ];
    }

    /**
     * Delete a video from VdoCipher and remove our row.
     *
     * @param string $videoid
     * @return bool
     */
    public static function delete_video(string $videoid): bool {
        global $DB;

        $row = self::get_row($videoid);
        self::require_manage(self::context_for((int) $row->courseid));

        $client = new api_client();
        $client->delete_videos([$videoid]);

        $DB->delete_records(self::TABLE, ['id' => $row->id]);
        return true;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Fetch our mapping row or fail.
     *
     * @param string $videoid
     * @return \stdClass
     */
    public static function get_row(string $videoid): \stdClass {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['videoid' => $videoid]);
        if (!$row) {
            throw new api_exception(get_string('err_novideo', 'local_vdocipher'));
        }
        return $row;
    }

    /**
     * Context for a course id (course context, or system when 0).
     *
     * @param int $courseid
     * @return \context
     */
    protected static function context_for(int $courseid): \context {
        if ($courseid && $courseid != SITEID) {
            return \context_course::instance($courseid);
        }
        return \context_system::instance();
    }

    /**
     * Require the manage capability in the given context.
     *
     * @param \context $context
     */
    protected static function require_manage(\context $context): void {
        require_capability('local/vdocipher:manage', $context);
    }
}

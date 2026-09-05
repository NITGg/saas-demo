<?php
namespace local_vimeo;

defined('MOODLE_INTERNAL') || die();

/**
 * Playback side of Vimeo: resolves the embed URL for a given activity after
 * verifying the user may view it.
 *
 * Vimeo has no per-view token or OTP (unlike VdoCipher). Access is enforced two
 * ways: Moodle checks enrolment + capability here, and the video itself is set
 * to embed-whitelist privacy so it only plays when embedded on the academy's own
 * whitelisted domain. There is no watermark.
 */
class playback_service {

    /** Vimeo player base URL. */
    const PLAYER_BASE = 'https://player.vimeo.com/video/';

    /**
     * Build the embed URL for the video attached to a course module, after
     * verifying the user may view it.
     *
     * @param int $cmid course module id (a resource2 with a Vimeo video)
     * @param \stdClass $user the viewer (token owner)
     * @return array ['videoid','embedurl']
     */
    public static function get_playback(int $cmid, \stdClass $user): array {
        global $DB;

        $row = $DB->get_record(video_service::TABLE, ['cmid' => $cmid]);
        if (!$row) {
            throw new api_exception(get_string('err_novideo', 'local_vimeo'));
        }

        self::require_view($cmid, $user);

        return self::embed($row);
    }

    /**
     * Build the embed payload for a mapping row WITHOUT an access check.
     *
     * The caller MUST have already verified the user may view it (e.g. the token
     * API via {@see get_playback}, or the web player via require_login +
     * require_capability).
     *
     * @param \stdClass $row a local_vimeo_videos row
     * @return array ['videoid','embedurl']
     */
    public static function embed(\stdClass $row): array {
        $url = self::PLAYER_BASE . rawurlencode($row->videoid);
        if (!empty($row->videohash)) {
            $url .= '?h=' . rawurlencode($row->videohash);
        }
        return [
            'videoid'  => $row->videoid,
            'embedurl' => $url,
        ];
    }

    /**
     * Verify the user may view this activity: it must exist, be visible/available
     * to them, and they must be enrolled (or hold a teaching/manage capability).
     *
     * @param int $cmid
     * @param \stdClass $user
     */
    protected static function require_view(int $cmid, \stdClass $user): void {
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $coursecontext = \context_course::instance($cm->course);
        $modcontext    = \context_module::instance($cm->id);

        // Teachers / managers who can manage videos always pass.
        if (has_capability('local/vimeo:manage', $modcontext, $user)) {
            return;
        }

        // Otherwise: must be enrolled (active) and hold the view capability, and
        // the activity must actually be visible/available to this user.
        $enrolled = is_enrolled($coursecontext, $user, '', true);
        $canview  = has_capability('local/vimeo:view', $modcontext, $user);
        if (!$enrolled || !$canview) {
            throw new api_exception(get_string('err_noaccess', 'local_vimeo'));
        }

        $modinfo = get_fast_modinfo($cm->course, $user->id);
        $cminfo  = $modinfo->get_cm($cm->id);
        if (!$cminfo->uservisible) {
            throw new api_exception(get_string('err_noaccess', 'local_vimeo'));
        }
    }
}

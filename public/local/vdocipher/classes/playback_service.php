<?php
namespace local_vdocipher;

defined('MOODLE_INTERNAL') || die();

/**
 * Playback side of VdoCipher: mints a short-lived OTP for a given activity, with
 * the *viewer's* identity baked into a dynamic watermark.
 *
 * The watermark text is built here, server-side, from the requesting user — so a
 * client can never forge or strip it, and every stream carries the identity of
 * whoever requested it.
 */
class playback_service {

    /**
     * Build an OTP + playbackInfo for the video attached to a course module,
     * after verifying the user may view it.
     *
     * @param int $cmid course module id (a resource2 with a VdoCipher video)
     * @param \stdClass $user the viewer (token owner)
     * @return array ['videoid','otp','playbackInfo','watermark','ttl']
     */
    public static function get_playback(int $cmid, \stdClass $user): array {
        global $DB;

        $row = $DB->get_record(video_service::TABLE, ['cmid' => $cmid]);
        if (!$row) {
            throw new api_exception(get_string('err_novideo', 'local_vdocipher'));
        }

        self::require_view($cmid, $user);

        return self::mint($row->videoid, $user);
    }

    /**
     * Mint a watermarked OTP for a video WITHOUT an access check.
     *
     * The caller MUST have already verified the user may view it (e.g. the token
     * API via {@see get_playback}, or the web player via require_login +
     * require_capability). This is the shared OTP-building step.
     *
     * @param string $videoid
     * @param \stdClass $user the viewer whose identity is watermarked
     * @return array ['videoid','otp','playbackInfo','watermark','ttl']
     */
    public static function mint(string $videoid, \stdClass $user): array {
        $ttl = (int) get_config('local_vdocipher', 'otpttl');
        if ($ttl <= 0) {
            $ttl = 300;
        }

        $watermark = self::watermark_text($user);
        $annotate  = self::build_annotate($watermark);

        $client = new api_client();
        $result = $client->create_otp($videoid, $ttl, $annotate);

        return [
            'videoid'      => $videoid,
            'otp'          => $result['otp'] ?? '',
            'playbackInfo' => $result['playbackInfo'] ?? '',
            'watermark'    => $watermark,
            'ttl'          => $ttl,
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
        if (has_capability('local/vdocipher:manage', $modcontext, $user)) {
            return;
        }

        // Otherwise: must be enrolled (active) and hold the view capability, and
        // the activity must actually be visible/available to this user.
        $enrolled = is_enrolled($coursecontext, $user, '', true);
        $canview  = has_capability('local/vdocipher:view', $modcontext, $user);
        if (!$enrolled || !$canview) {
            throw new api_exception(get_string('err_noaccess', 'local_vdocipher'));
        }

        $modinfo = get_fast_modinfo($cm->course, $user->id);
        $cminfo  = $modinfo->get_cm($cm->id);
        if (!$cminfo->uservisible) {
            throw new api_exception(get_string('err_noaccess', 'local_vdocipher'));
        }
    }

    /**
     * Resolve the watermark template against the viewer.
     *
     * @param \stdClass $user
     * @return string
     */
    protected static function watermark_text(\stdClass $user): string {
        $template = (string) get_config('local_vdocipher', 'watermarktext');
        if (trim($template) === '') {
            $template = '{fullname} · {email}';
        }
        return strtr($template, [
            '{fullname}' => fullname($user),
            '{email}'    => $user->email ?? '',
            '{userid}'   => (string) $user->id,
        ]);
    }

    /**
     * Build the VdoCipher "annotate" payload (a stringified JSON array) for a
     * moving rtext watermark. Returns '' when watermarking is disabled.
     *
     * @param string $text
     * @return string
     */
    protected static function build_annotate(string $text): string {
        if (!get_config('local_vdocipher', 'watermarkenabled')) {
            return '';
        }
        $alpha = (string) get_config('local_vdocipher', 'watermarkalpha');
        if ($alpha === '') {
            $alpha = '0.60';
        }
        $size = (int) get_config('local_vdocipher', 'watermarksize');
        if ($size <= 0) {
            $size = 15;
        }

        // rtext = roaming text that moves around the frame, hardest to crop out.
        $annotation = [[
            'type'     => 'rtext',
            'text'     => $text,
            'alpha'    => $alpha,
            'color'    => '0xFFFFFF',
            'size'     => $size,
            'interval' => 5000,
        ]];

        return json_encode($annotation);
    }
}

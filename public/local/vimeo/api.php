<?php
/**
 * Vimeo token-authenticated JSON API (teacher CRUD + playback).
 *
 *   GET|POST /local/vimeo/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<translated>","errorcode":"<stable code>"}
 *
 * Envelope + auth: \local_academy\api\endpoint (token validated by
 * \local_academy\token_auth; HTTP 401 for a missing/dead token so the app can end
 * the session, 403 for a suspended academy). All CRUD functions additionally
 * require local/vimeo:manage in the relevant course context. Vimeo-side and
 * mapping errors keep their errorcode "vimeoapi".
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

use local_academy\api\endpoint as api;

api::boot();
$USER = api::authenticate();
if (($lang = optional_param('lang', '', PARAM_LANG)) !== '') {
    force_current_language($lang);
}

api::run(function (string $function) use ($USER) {
    try {
        switch ($function) {
            // ── Playback ──────────────────────────────────────────────────────────
            // The Vimeo embed URL for the video on this activity (resource2 mapping
            // or a mod_vimeo activity). No OTP: the video is embed-whitelisted to the
            // academy domain; access (enrolment, capability, lesson lock, lesson sale)
            // is checked here. Call right before playback, then log_lesson_view.
            case 'get_playback':
                return \local_vimeo\playback_service::get_playback(required_param('cmid', PARAM_INT), $USER);

            // ── Teacher CRUD ──────────────────────────────────────────────────────

            // Create a Vimeo video and get a resumable (tus) upload link + video id.
            // The client then PATCHes the file bytes straight to the upload link.
            case 'create_upload':
                api::require_post();
                return \local_vimeo\video_service::create_upload(
                    required_param('title', PARAM_TEXT),
                    required_param('size', PARAM_INT),
                    optional_param('courseid', 0, PARAM_INT),
                    optional_param('cmid', 0, PARAM_INT));

            // Refresh + return a video's processing status (PRE-Upload…complete).
            case 'video_status':
                return \local_vimeo\video_service::refresh_status(required_param('videoid', PARAM_ALPHANUMEXT));

            // List videos, optionally scoped to a course.
            case 'list_videos':
                return \local_vimeo\video_service::list_videos(optional_param('courseid', 0, PARAM_INT));

            // Attach an existing video to a course module (resource2).
            case 'attach_video':
                api::require_post();
                return \local_vimeo\video_service::attach(
                    required_param('videoid', PARAM_ALPHANUMEXT), required_param('cmid', PARAM_INT));

            // Delete a video from Vimeo and remove our mapping row.
            case 'delete_video':
                api::require_post();
                return ['deleted' => \local_vimeo\video_service::delete_video(
                    required_param('videoid', PARAM_ALPHANUMEXT))];
        }
    } catch (\local_vimeo\api_exception $e) {
        // Vimeo-side or mapping errors are safe and useful to surface to teachers.
        api::fail('vimeoapi', $e->getMessage());
    }
    return api::unknown();
});

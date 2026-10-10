<?php
/**
 * External function: mod_jitsi_get_session_info
 *
 * Returns everything a native mobile app needs to render a Jitsi session:
 *   - Jitsi server URL, room name, JWT token  (for native Jitsi SDK)
 *   - Whiteboard URL                          (for in-app WebView)
 *   - Recordings with HLS playback URLs       (for native video player)
 *
 * @package   mod_jitsi
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jitsi\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_module;

class get_session_info extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    public static function execute(int $cmid): array {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/mod/jitsi/lib.php'); // jitsi_room_name()

        // Validate and get context.
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $cmid   = $params['cmid'];

        $modinfo = get_fast_modinfo(
            $DB->get_field('course_modules', 'course', ['id' => $cmid], MUST_EXIST)
        );
        $cm     = $modinfo->get_cm($cmid);
        $course = get_course($cm->course);
        $jitsi  = $DB->get_record('jitsi', ['id' => $cm->instance], '*', MUST_EXIST);

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/jitsi:view', $context);

        // Same rule as view.php (\mod_jitsi\local\access): only the session's teacher
        // moderates a linked room, and a student is held out (no JWT) until the
        // teacher is in the call.
        $decision   = \mod_jitsi\local\access::check($cm, (int) $USER->id);
        $is_teacher = $decision->moderator;
        $available  = $decision->allowed;
        $available_info = $decision->code === \mod_jitsi\local\access::UNAVAILABLE
            ? strip_tags($cm->availableinfo ?? '') : $decision->message;
        \mod_jitsi\local\access::record_entry($decision, (int) $USER->id);

        // ── Jitsi config ─────────────────────────────────────────────────────
        $jitsi_host = get_config('local_academysessions', 'jitsi_host') ?: 'localhost:8443';
        $server_url = (strpos($jitsi_host, 'http') === 0)
            ? rtrim($jitsi_host, '/')
            : 'https://' . $jitsi_host;

        // Site-salted room name — matches view.php/api_token so web + mobile join
        // the SAME room, and tenants sharing one Jitsi server never collide.
        // The JWT is the key to the room: only handed out when the user may enter now.
        $room = jitsi_room_name($jitsi, $cm);
        $jwt  = $available ? \local_academysessions\jitsi_jwt::generate(
            $room, fullname($USER), $USER->email, $is_teacher
        ) : '';

        // ── Whiteboard URL ────────────────────────────────────────────────────
        $excalidraw_app = get_config('local_academysessions', 'excalidraw_app')
            ?: 'https://academy2026.nitg-eg.com/whiteboard';
        $wb_room        = 'academy_wb_jitsi_' . $cm->id;
        $whiteboard_url = rtrim($excalidraw_app, '/') . '/#room=' . rawurlencode($wb_room);

        // ── Recordings ────────────────────────────────────────────────────────
        // Vimeo recordings stored by record_notify.php (academy_session_recordings),
        // with the web page's rules: staff always; students of a linked session once
        // it is over; standalone rooms always.
        $recordings = [];
        if (class_exists('\local_academysessions\recordings')) {
            $linked = $DB->get_record('academy_live_sessions', ['jitsiid' => $jitsi->id]) ?: null;
            if ($is_teacher || \local_academysessions\recordings::visible_to_students($linked)) {
                foreach (\local_academysessions\recordings::list_for((int) $cm->id,
                        $linked ? (int) $linked->id : 0) as $rec) {
                    $recordings[] = [
                        'id'           => $rec['id'],
                        'title'        => $rec['title'],
                        'status'       => $rec['status'],
                        'playback_url' => $rec['playback_url'],
                        'embed_url'    => $rec['embed_url'],
                        'timecreated'  => $rec['timecreated'],
                    ];
                }
            }
        }

        return [
            'cmid'          => $cm->id,
            'name'          => format_string($jitsi->name),
            'available'     => $available,
            'available_info'=> $available_info,
            'is_teacher'    => $is_teacher,
            'server_url'    => $server_url,
            'room'          => $room,
            'jwt'           => $jwt,
            'subject'       => format_string($jitsi->name),
            'whiteboard_url'=> $whiteboard_url,
            'recordings'    => $recordings,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'           => new external_value(PARAM_INT,  'Course module ID'),
            'name'           => new external_value(PARAM_TEXT, 'Activity name'),
            'available'      => new external_value(PARAM_BOOL, 'Whether session is accessible'),
            'available_info' => new external_value(PARAM_TEXT, 'Restriction message if not available'),
            'is_teacher'     => new external_value(PARAM_BOOL, 'Whether current user is moderator'),
            'server_url'     => new external_value(PARAM_URL,  'Jitsi server URL for native SDK'),
            'room'           => new external_value(PARAM_TEXT, 'Jitsi room name'),
            'jwt'            => new external_value(PARAM_RAW,  'JWT token for native Jitsi SDK; empty while available is false'),
            'subject'        => new external_value(PARAM_TEXT, 'Conference subject/title'),
            'whiteboard_url' => new external_value(PARAM_URL,  'Excalidraw whiteboard URL (open in WebView)'),
            'recordings'     => new external_multiple_structure(
                new external_single_structure([
                    'id'            => new external_value(PARAM_INT,  'Recording DB id'),
                    'title'         => new external_value(PARAM_TEXT, 'Recording title'),
                    'status'        => new external_value(PARAM_ALPHA,'Status: syncing|ready'),
                    'playback_url'  => new external_value(PARAM_URL,  'Vimeo player URL (https://player.vimeo.com/video/<id>)', VALUE_OPTIONAL),
                    'thumbnail_url' => new external_value(PARAM_URL,  'Thumbnail image URL', VALUE_OPTIONAL),
                    'embed_url'     => new external_value(PARAM_URL,  'Vimeo iframe embed URL (same as playback_url)', VALUE_OPTIONAL),
                    'timecreated'   => new external_value(PARAM_INT,  'Unix timestamp'),
                ])
            ),
        ]);
    }
}

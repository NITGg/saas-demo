<?php
namespace local_license\local;

use local_license\license;
use local_license\enforcer;

defined('MOODLE_INTERNAL') || die();

/**
 * Enforcement callbacks: lock an expired academy, and block creating more than
 * the tier allows — all from a single early hook, so no Moodle core is touched.
 */
class hook_callbacks {

    /**
     * @param \core\hook\output\before_http_headers $hook
     */
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $SCRIPT;

        // Never interfere with CLI, AJAX or web-service requests.
        if (CLI_SCRIPT
                || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)
                || (defined('WS_SERVER') && WS_SERVER)) {
            return;
        }

        $script = (string) ($SCRIPT ?? '');

        // Always let these through so an admin can still recover / upgrade / log in / see the notice.
        foreach (['/login/', '/admin/', '/local/license/'] as $allow) {
            if (strpos($script, $allow) === 0) {
                return;
            }
        }

        // 0) Admin suspend — locks the whole academy regardless of tier enforcement.
        if (license::is_suspended()) {
            redirect(new \moodle_url('/local/license/expired.php', ['reason' => 'suspended']));
        }

        // Everything below is tier enforcement — only when the master switch is on.
        if (!license::is_enforced()) {
            return;
        }

        // 1) Expiry lock — send everyone to the upgrade page.
        if (license::is_expired()) {
            redirect(new \moodle_url('/local/license/expired.php'));
        }

        // 2) Quantity guards at the creation entry points.
        if ($script === '/course/modedit.php') {
            $add = optional_param('add', '', PARAM_ALPHANUMEXT);
            if ($add !== '') {
                $courseid = optional_param('course', SITEID, PARAM_INT);
                $courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);

                // Feature-gated module type (e.g. DRM video is Professional-only).
                $reqfeature = license::module_requires_feature($add);
                if ($reqfeature && !license::has_feature($reqfeature)) {
                    self::block($courseurl, get_string('feature_locked', 'local_license', [
                        'type' => $add,
                        'tier' => license::tiername(),
                    ]));
                }

                // Video source — the DRM/VdoCipher activity needs a tier whose
                // video source allows it (external YouTube/Vimeo links are gated
                // in the app, which Moodle can't distinguish at add-time).
                if (!license::video_allowed($add)) {
                    self::block($courseurl, get_string('video_source_locked', 'local_license', [
                        'type'   => $add,
                        'source' => license::video_source(),
                        'tier'   => license::tiername(),
                    ]));
                }

                // Quantity limit for this activity type.
                if (!enforcer::can_add_activity($add)) {
                    self::block($courseurl, get_string('limit_activity', 'local_license', [
                        'type' => $add,
                        'tier' => license::tiername(),
                    ]));
                }
            }
        } else if ($script === '/course/edit.php') {
            // A new course has a category but no course id.
            $id       = optional_param('id', 0, PARAM_INT);
            $category = optional_param('category', 0, PARAM_INT);
            if (!$id && $category && !enforcer::can_add_course()) {
                self::block(
                    new \moodle_url('/course/management.php'),
                    get_string('limit_course', 'local_license', license::tiername())
                );
            }
        }
    }

    /**
     * Redirect back with an "upgrade to add more" message.
     *
     * @param \moodle_url $to
     * @param string $message
     */
    protected static function block(\moodle_url $to, string $message): void {
        redirect($to, $message, null, \core\output\notification::NOTIFY_ERROR);
    }
}

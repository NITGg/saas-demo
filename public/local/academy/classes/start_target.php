<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Where the home page's "ابدأ رحلتك" (start your journey) button leads.
 *
 * - a visitor (not logged in, or the guest user) → the registration page;
 * - a student who has opened an activity before → the most recent one they can
 *   still open;
 * - a student enrolled in courses but with no activity opened yet → My courses;
 * - anyone else → the course catalogue.
 *
 * "Last opened activity" comes from the standard Recently accessed items block's
 * table (block_recentlyaccesseditems), which core fills on every activity view
 * whether or not the block is on a page.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_target {

    /** How many recent items to try before giving up (hidden/deleted ones are skipped). */
    private const RECENT_LIMIT = 20;

    /**
     * The destination for a user.
     *
     * @param int $userid the user clicking the button; 0 for a visitor
     * @return \moodle_url
     */
    public static function url_for_user(int $userid): \moodle_url {
        if ($userid <= 0 || isguestuser($userid)) {
            return new \moodle_url('/local/academy/register.php');
        }
        if ($cmurl = self::last_activity_url($userid)) {
            return $cmurl;
        }
        if (enrol_get_all_users_courses($userid, true, 'id')) {
            return new \moodle_url('/my/courses.php');
        }
        return new \moodle_url('/course/index.php');
    }

    /**
     * The URL of the most recent activity the user opened and can still open.
     *
     * @param int $userid
     * @return \moodle_url|null null when there is none
     */
    private static function last_activity_url(int $userid): ?\moodle_url {
        global $DB;
        if (!$DB->get_manager()->table_exists('block_recentlyaccesseditems')) {
            return null;
        }
        $recent = $DB->get_records('block_recentlyaccesseditems', ['userid' => $userid],
            'timeaccess DESC, id DESC', 'id, courseid, cmid', 0, self::RECENT_LIMIT);
        foreach ($recent as $item) {
            try {
                $modinfo = get_fast_modinfo($item->courseid, $userid);
                $cm = $modinfo->get_cm($item->cmid);
            } catch (\moodle_exception $e) {
                continue; // Course or activity deleted since.
            }
            if ($cm->uservisible && $cm->url) {
                return $cm->url;
            }
        }
        return null;
    }
}

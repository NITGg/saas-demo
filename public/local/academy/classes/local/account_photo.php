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

namespace local_academy\local;

/**
 * "Change photo" on the account page (/local/academy/profile.php): the user uploads their own
 * picture there. The button used to open /user/editadvanced.php, the admin's page
 * (moodle/user:update), so every student and teacher got "you do not have the permission".
 *
 * Same storage as Moodle's own profile form: process_new_icon() into the user context, the
 * picture id in user.picture, a user_updated event.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class account_photo {

    /** Largest photo accepted (bytes). */
    const MAXBYTES = 5 * 1024 * 1024;

    /** Accepted image types (getimagesize types). */
    const TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

    /**
     * Can this user change their own photo here?
     *
     * @param int $userid
     * @return bool
     */
    public static function can_change(int $userid): bool {
        global $CFG;
        return empty($CFG->disableuserimages)
            && has_capability('moodle/user:editownprofile', \context_user::instance($userid), $userid);
    }

    /**
     * Save a photo posted as $_FILES[...] entry.
     *
     * @param int $userid
     * @param array $upload one $_FILES entry
     * @return string|null an error message, or null when saved
     */
    public static function save_upload(int $userid, array $upload): ?string {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($upload['tmp_name'])
                || !is_uploaded_file($upload['tmp_name'])) {
            return get_string('photo_failed', 'local_academy');
        }
        return self::save_file($userid, $upload['tmp_name'], (int) ($upload['size'] ?? 0));
    }

    /**
     * Save an image file as the user's picture.
     *
     * @param int $userid
     * @param string $path
     * @param int $size bytes
     * @return string|null an error message, or null when saved
     */
    public static function save_file(int $userid, string $path, int $size): ?string {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/gdlib.php');
        if (!self::can_change($userid)) {
            return get_string('photo_notallowed', 'local_academy');
        }
        if ($size <= 0 || $size > self::MAXBYTES) {
            return get_string('photo_toobig', 'local_academy', display_size(self::MAXBYTES));
        }
        $info = @getimagesize($path);
        if (!$info || !in_array($info[2], self::TYPES, true)) {
            return get_string('photo_notimage', 'local_academy');
        }
        $picture = (int) process_new_icon(\context_user::instance($userid), 'user', 'icon', 0, $path);
        if (!$picture) {
            return get_string('photo_failed', 'local_academy');
        }
        $DB->set_field('user', 'picture', $picture, ['id' => $userid]);
        if ((int) $USER->id === $userid) {
            $USER->picture = $picture;
        }
        \core\event\user_updated::create_from_userid($userid)->trigger();
        return null;
    }
}

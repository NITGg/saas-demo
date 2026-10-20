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

/**
 * Library callbacks of local_nit_lessons.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve the photo a student attached to an instant-lesson request: to that student, the teachers it
 * was sent to, and managers only.
 *
 * @param stdClass $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false when not found
 */
function local_nit_lessons_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $USER;
    if ($context->contextlevel !== CONTEXT_SYSTEM
            || $filearea !== \local_nit_lessons\service\instant_service::PHOTO_AREA) {
        return false;
    }
    require_login(null, false);
    $requestid = (int) array_shift($args);
    if (!\local_nit_lessons\service\instant_service::can_see_photo((int) $USER->id, $requestid)) {
        return false;
    }
    $file = get_file_storage()->get_file($context->id, 'local_nit_lessons', $filearea, $requestid, '/',
        (string) array_pop($args));
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
}

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
 * Callbacks of local_nit_tutors.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_nit_tutors\local\application;
use local_nit_tutors\local\qualifications;
use local_nit_tutors\local\video;

/**
 * Serve the teacher's files.
 *
 * - video: anyone (the public teacher page) once the video is approved and the teacher is
 *   approved; the teacher and the reviewers always.
 * - qualification, field: the teacher and the reviewers only.
 *
 * @param stdClass $course
 * @param stdClass|null $cm
 * @param context $context the teacher's user context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false when not found
 */
function local_nit_tutors_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $USER, $CFG;
    if ($context->contextlevel != CONTEXT_USER
            || !in_array($filearea, [video::AREA, qualifications::AREA, \local_nit_tutors\local\fields::AREA], true)) {
        return false;
    }
    $ownerid = (int) $context->instanceid;
    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $isowner = isloggedin() && !isguestuser() && (int) $USER->id === $ownerid;
    $isreviewer = isloggedin() && has_capability('local/nit_tutors:review', context_system::instance());
    if ($filearea === video::AREA) {
        if (!$isowner && !$isreviewer) {
            if (!empty($CFG->forcelogin)) {
                require_login();
            }
            $row = application::get($ownerid);
            if (!$row || $row->videostatus !== 'approved' || $row->videotype !== video::UPLOAD
                    || ($row->status !== application::APPROVED && application::approval_required())) {
                return false;
            }
        }
    } else {
        require_login();
        if (!$isowner && !$isreviewer) {
            return false;
        }
    }
    $file = get_file_storage()->get_file($context->id, 'local_nit_tutors', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    // Video: streamed with range support (send_stored_file handles byte ranges), cached briefly.
    send_stored_file($file, $filearea === video::AREA ? HOURSECS : 0, 0, $forcedownload, $options);
}

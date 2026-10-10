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
 * Runs one live-lesson action posted from "My lessons & Flex" or "My live lessons", then goes back.
 *
 * Who may do what is checked by the lesson engine (the user must be the lesson's student or
 * teacher, and the lesson must be in the right state).
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_lessons\api\lessons;

require_login(null, false);
require_sesskey();
if (isguestuser()) {
    throw new require_login_exception('Guests cannot book lessons');
}
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/nit_lessons/action.php'));

$do = required_param('do', PARAM_ALPHAEXT);
$back = optional_param('back', 'hub', PARAM_ALPHA);
$return = $back === 'teacher'
    ? new moodle_url('/local/nit_lessons/student.php', ['tab' => 'teaching',
        'status' => optional_param('status', '', PARAM_ALPHAEXT)])
    : new moodle_url('/local/nit_lessons/student.php', ['tab' => 'lessons']);

$userid = (int) $USER->id;
$lessonid = optional_param('lessonid', 0, PARAM_INT);
$value = optional_param('value', '', PARAM_ALPHA);
$reason = trim(optional_param('reason', '', PARAM_TEXT));
$time = optional_param('time', 0, PARAM_INT);

try {
    switch ($do) {
        case 'request':
            require_capability('local/nit_lessons:request', context_system::instance());
            if (!$time) {
                throw new \local_nit_lessons\exception\lesson_exception('err_notime');
            }
            lessons::request($userid, required_param('teacherid', PARAM_INT), required_param('subject', PARAM_TEXT),
                $time, $reason);
            $done = 'done_requested';
            break;
        case 'teacher_respond':
            lessons::teacher_respond($userid, $lessonid, $value, ['suggested_time' => $time, 'reject_reason' => $reason]);
            $done = 'done_saved';
            break;
        case 'student_respond':
            lessons::student_respond($userid, $lessonid, $value, ['suggested_time' => $time, 'reject_reason' => $reason]);
            $done = 'done_saved';
            break;
        case 'start':
            lessons::start($userid, $lessonid);
            $done = 'done_started';
            break;
        case 'complete':
            lessons::complete($userid, $lessonid, $reason !== '' ? $reason : null);
            $done = 'done_completed';
            break;
        case 'report_student_absent':
            lessons::report_student_absent($userid, $lessonid);
            $done = 'done_saved';
            break;
        case 'report_teacher_absent':
            lessons::report_teacher_absent($userid, $lessonid);
            $done = 'done_saved';
            break;
        case 'cancel_request':
            lessons::cancel_request($userid, $lessonid, $reason);
            $done = 'done_cancelled';
            break;
        case 'cancel_as_student':
            lessons::cancel_as_student($userid, $lessonid, $reason);
            $done = 'done_cancelled';
            break;
        case 'cancel_as_teacher':
            lessons::cancel_as_teacher($userid, $lessonid, $reason);
            $done = 'done_cancelled';
            break;
        case 'request_time_update':
            lessons::request_time_update($userid, $lessonid, $time);
            $done = 'done_reschedule';
            break;
        case 'respond_time_update':
            lessons::respond_time_update($userid, $lessonid, $value);
            $done = 'done_saved';
            break;
        default:
            throw new \local_nit_lessons\exception\lesson_exception('err_badaction');
    }
} catch (moodle_exception $e) {
    redirect($return, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
}

redirect($return, get_string($done, 'local_nit_lessons'), null, \core\output\notification::NOTIFY_SUCCESS);

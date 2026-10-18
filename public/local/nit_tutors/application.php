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
 * "Teacher profile": the teacher's (or applicant's) data — about, subjects, intro video,
 * qualifications, the admin's fields — inside the account frame of /local/academy/profile.php,
 * with "Save" and "Save and send for review". New teachers fill all of it in the sign-up already
 * (/local/academy/register.php?as=teacher); this page is where they see the status and change it.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_tutors\local\application;
use local_nit_tutors\local\profile_view;
use local_nit_tutors\local\teacher_input;

require_login();
if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}

$userid = (int) $USER->id;
$url = new moodle_url('/local/nit_tutors/application.php');
$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($userid));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('teacherprofile', 'local_nit_tutors'));
$PAGE->set_heading('');
$PAGE->add_body_class('nit-profile-page nit-tutor-application');

$editable = application::editable($userid);
$status = application::status($userid);
$cansubmit = in_array($status, ['', application::DRAFT, application::CHANGES, application::REJECTED], true)
    && ($status !== application::REJECTED || application::setting_int('allowreapply', 1));

$posted = null;
$errors = [];
if ($editable && data_submitted() && confirm_sesskey()) {
    $in = teacher_input::from_request();
    $files = teacher_input::files_from_request();
    $send = $cansubmit && optional_param('send', 0, PARAM_BOOL);
    $errors = teacher_input::validate($in, $files, $userid, $send);
    if (!$errors) {
        teacher_input::save($userid, $in, $files);
        if ($send) {
            try {
                application::submit($userid);
                redirect($url, get_string('submitted', 'local_nit_tutors'), null, \core\output\notification::NOTIFY_SUCCESS);
            } catch (moodle_exception $e) {
                \core\notification::error($e->getMessage());
            }
        } else {
            $approved = $status === application::APPROVED;
            redirect($url, get_string($approved && application::has_updates($userid) ? 'savedforreview' : 'saved',
                'local_nit_tutors'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } else {
        // Show what they typed again; files must be chosen again (a browser cannot refill a file input).
        $posted = $in;
        \core\notification::error(get_string('err_form', 'local_nit_tutors'));
    }
}

$shell = '\local_academy\local\account_shell';
$context = profile_view::inputs($userid, $posted, $errors) + [
    'title' => get_string('teacherprofile', 'local_nit_tutors'),
    'navhtml' => class_exists($shell) ? $shell::nav_html('tutor') : '',
    'statushtml' => $OUTPUT->render_from_template('local_nit_tutors/application_status',
        \local_nit_tutors\local\status_view::context($userid)),
    'actionurl' => $url->out(false),
    'sesskey' => sesskey(),
    'editable' => $editable,
    'cansubmit' => $cansubmit,
];

echo $OUTPUT->header();
if (class_exists($shell)) {
    echo html_writer::tag('style', $shell::css());
}
echo $OUTPUT->render_from_template('local_nit_tutors/profile_page', $context);
echo $OUTPUT->footer();

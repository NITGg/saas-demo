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
 * "Teacher profile": the teacher's (or applicant's) public-profile data and their application —
 * about, subjects, intro video, qualifications, the admin's fields — with "Send for review".
 * Linked from the account page's left nav (/local/academy/profile.php).
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_tutors\local\application;
use local_nit_tutors\local\profile_editor;
use local_nit_lessons\service\teacher_service;

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

$row = application::get($userid);
$editable = application::editable($userid);
$status = $row ? (string) $row->status : '';
$cansubmit = in_array($status, ['', application::DRAFT, application::CHANGES, application::REJECTED], true)
    && ($status !== application::REJECTED || application::setting_int('allowreapply', 1));

$videostatus = '';
if ($row && $row->videostatus !== '' && $row->videostatus !== null) {
    $videostatus = get_string('videostatus_' . $row->videostatus, 'local_nit_tutors')
        . ($row->videostatus === 'rejected' && $row->videonote ? ' — ' . s($row->videonote) : '');
}
$subjectoptions = [];
foreach (teacher_service::subject_options($userid) as $o) {
    $subjectoptions[$o] = format_string($o);
}
$data = profile_editor::load($userid);
$form = new \local_nit_tutors\form\application_form($url, [
    'userid' => $userid,
    'editable' => $editable,
    'cansubmit' => $cansubmit,
    'qualcount' => max(1, count((array) ($data->qualid ?? []))),
    'subjectoptions' => $subjectoptions,
    'videostatus' => $videostatus,
]);
$form->set_data($data);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/academy/profile.php'));
}
if ($editable && ($submitted = $form->get_data())) {
    try {
        profile_editor::save($userid, $submitted);
        if (!empty($submitted->submitbutton)) {
            application::submit($userid);
            redirect($url, get_string('submitted', 'local_nit_tutors'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
        redirect($url, get_string('saved', 'local_nit_tutors'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
}

// The account page's frame (left nav, cards) of /local/academy/profile.php; the form's sections
// are drawn as its cards by .nittu-app in styles.css.
$shell = '\local_academy\local\account_shell';
echo $OUTPUT->header();
if (class_exists($shell)) {
    echo html_writer::tag('style', $shell::css());
}
echo html_writer::start_div('nit-prof nittu-app');
echo html_writer::tag('h1', s(get_string('teacherprofile', 'local_nit_tutors')));
echo html_writer::start_div('nit-prof-grid');
echo class_exists($shell) ? $shell::nav_html('tutor') : html_writer::div('');
echo html_writer::start_div('nit-prof-main');
echo $OUTPUT->render_from_template('local_nit_tutors/application_status', \local_nit_tutors\local\status_view::context($userid));
$form->display();
echo html_writer::end_div() . html_writer::end_div() . html_writer::end_div();
echo $OUTPUT->footer();

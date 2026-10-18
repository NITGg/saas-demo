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
 * "Teacher profile": the teacher's (or applicant's) data — about, the lessons card of
 * local_nit_lessons (taking bookings, headline, price, subjects, weekly hours), intro video,
 * qualifications, the admin's fields — inside the account frame of /local/academy/profile.php,
 * with "Save" and "Save and send for review". New teachers fill most of it in the sign-up already
 * (/local/academy/register.php?as=teacher); this page is where they see the status and change it.
 *
 * An approved teacher's changes wait for the academy's review ({@see \local_nit_tutors\local\pending}):
 * students keep seeing the approved version until then. Bookings, price and hours save at once.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_lessons\local\profile_section;
use local_nit_tutors\local\application;
use local_nit_tutors\local\pending;
use local_nit_tutors\local\profile_save;
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
$cansubmit = profile_save::can_submit($userid);

$posted = null;
$postedcard = null;
$errors = [];
if ($editable && data_submitted() && confirm_sesskey()) {
    // The lessons card (local_nit_lessons) holds the headline and the subjects; the rest of the card —
    // taking bookings, the price, the weekly hours — is the teacher's day-to-day settings.
    $card = profile_section::posted();
    $in = teacher_input::from_request();
    $result = profile_save::save($userid, $in, teacher_input::files_from_request(), $card,
        (bool) optional_param('send', 0, PARAM_BOOL));
    $errors = $result['errors'];
    if (!$errors) {
        redirect($url, get_string($result['submitted'] ? 'submitted' : ($result['waits'] ? 'savedforreview' : 'saved'),
            'local_nit_tutors'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        if ($card !== null) {
            $in['headline'] = $card['headline'];
            $in['subjects'] = $card['subjects'];
        }
        // Show what they typed again; files must be chosen again (a browser cannot refill a file input).
        $posted = $in;
        $postedcard = $card;
        \core\notification::error(get_string('err_form', 'local_nit_tutors'));
    }
}

// What the form shows: what was just refused, else a change waiting for review, else the live data.
$waiting = pending::get($userid);
$shown = $posted ?? $waiting;
$cardvalues = [
    'subjectsrequired' => true,
    'note' => $status === application::APPROVED && application::approval_required()
        ? get_string('lessonsfirst', 'local_nit_tutors') : '',
    'err' => array_intersect_key($errors, array_flip(['general', 'headline', 'subjects', 'lessonflex', 'hours'])),
];
if ($shown !== null) {
    $cardvalues['headline'] = (string) $shown['headline'];
    $cardvalues['subjects'] = array_values((array) $shown['subjects']);
}
if ($postedcard !== null) {
    $cardvalues['available'] = $postedcard['available'];
    $cardvalues['hours'] = $postedcard['hours'];
    if ($postedcard['lessonflex'] !== null) {
        $cardvalues['lessonflex'] = $postedcard['lessonflex'];
    }
}

$shell = '\local_academy\local\account_shell';
$context = profile_view::inputs($userid, $shown, $errors, $posted === null && $waiting !== null) + [
    'lessonshtml' => profile_section::render($userid, true, $cardvalues),
    'pendingnotice' => $waiting !== null ? get_string('pendingnotice', 'local_nit_tutors',
        userdate((int) application::get($userid)->timepending, get_string('strftimedatetimeshort', 'langconfig'))) : '',
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

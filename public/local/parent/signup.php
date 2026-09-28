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
 * Parent sign-up — the phone gate.
 *
 * A parent enters their phone; we continue ONLY if a student has already listed
 * it (local_parent_link). On success the number is stashed in the session and
 * the parent is handed to Moodle's standard sign-up, so no account-creation code
 * lives here. After the account is created, the observer links it to the
 * child(ren) that named the number.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_parent\link_manager;

$PAGE->set_url(new moodle_url('/local/parent/signup.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('parentsignup', 'local_parent'));
$PAGE->set_heading(get_string('parentsignup', 'local_parent'));

// Signing up cannot happen while logged in.
if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/'));
}

$error = '';
if (data_submitted() && confirm_sesskey()) {
    $rawphone = trim((string) optional_param('parentphone', '', PARAM_TEXT));
    $digits = preg_replace('/\D+/', '', $rawphone);
    if (!preg_match('/^[+]?[0-9\s\-()]{7,25}$/', $rawphone) || strlen($digits) < 7) {
        $error = get_string('err_invalidphone', 'local_parent');
    } else if (link_manager::phone_exists($rawphone)) {
        // Verified the number belongs to a student. Remember it for the observer,
        // then hand off to the standard sign-up form.
        $SESSION->local_parent_signup_phone = link_manager::normalize_phone($rawphone);
        redirect(new moodle_url('/login/signup.php', ['parent' => '1']));
    } else {
        $error = get_string('err_noparentnumber', 'local_parent');
    }
}

echo $OUTPUT->header();

echo html_writer::start_div('nit-parent-signup', ['style' => 'max-width:420px;margin:2rem auto;']);
echo html_writer::tag('h2', get_string('parentsignup', 'local_parent'));
echo html_writer::tag('p', get_string('parentsignupintro', 'local_parent'), ['class' => 'text-muted']);

if ($error !== '') {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('label', get_string('yourphone', 'local_parent'),
    ['for' => 'parentphone', 'class' => 'd-block font-weight-bold mb-1']);
echo html_writer::empty_tag('input', [
    'type' => 'tel', 'id' => 'parentphone', 'name' => 'parentphone', 'inputmode' => 'tel',
    'class' => 'form-control mb-3', 'required' => 'required', 'autocomplete' => 'tel',
    'pattern' => '^[+]?[0-9\s\-()]{7,25}$',
    'oninput' => "this.value = this.value.replace(/[^0-9+\s\-()]/g, '');",
    'value' => optional_param('parentphone', '', PARAM_TEXT),
]);
echo html_writer::tag('button', get_string('continuetosignup', 'local_parent'),
    ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

echo $OUTPUT->footer();

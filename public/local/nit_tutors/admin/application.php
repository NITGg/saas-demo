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
 * Review one teacher: what they filled in (about, subjects, video, qualifications with the
 * certificates opened in the browser, the extra fields) and two decisions — Approve, or Reject
 * with the reason the teacher reads.
 *
 *   new application          Approve = approve the teacher (qualifications verified) | Reject = reject
 *   approved, with changes   Approve = approve the new video / qualifications        | Reject = refuse them
 *   approved, no changes     Reject = stop the teacher (suspend)
 *   suspended                Approve = reinstate
 *
 * Badges are given on the Badges tab, not here.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use local_nit_tutors\local\admin_tabs;
use local_nit_tutors\local\review_view;

$userid = required_param('userid', PARAM_INT);
admin_tabs::setup('applications', ['userid' => $userid]);
$url = new moodle_url('/local/nit_tutors/admin/application.php', ['userid' => $userid]);
$PAGE->set_url($url);
core_user::get_user($userid, 'id', MUST_EXIST);

// ── Decision ─────────────────────────────────────────────────────────────────
$action = optional_param('action', '', PARAM_ALPHA);
if (in_array($action, ['approve', 'reject'], true) && confirm_sesskey()) {
    try {
        review_view::decide($userid, $action, optional_param('note', '', PARAM_TEXT), (int) $USER->id);
        redirect($url, get_string('done', 'local_nit_tutors'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
}

// ── Page ─────────────────────────────────────────────────────────────────────
$context = review_view::context($userid);
$context += [
    'sesskey' => sesskey(),
    'actionurl' => $url->out(false),
    'backurl' => (new moodle_url('/local/nit_tutors/admin/applications.php', ['status' => $context['liststatus']]))->out(false),
];

echo $OUTPUT->header();
echo admin_tabs::render('applications');
echo $OUTPUT->render_from_template('local_nit_tutors/admin_review', $context);
echo $OUTPUT->footer();

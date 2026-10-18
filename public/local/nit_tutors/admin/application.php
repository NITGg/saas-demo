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
use local_nit_tutors\local\application;
use local_nit_tutors\local\fields;
use local_nit_tutors\local\pending;
use local_nit_tutors\local\qualifications;
use local_nit_tutors\local\teacher_input;
use local_nit_tutors\local\video;
use local_nit_lessons\service\teacher_service;

$userid = required_param('userid', PARAM_INT);
admin_tabs::setup('applications', ['userid' => $userid]);
$PAGE->set_url(new moodle_url('/local/nit_tutors/admin/application.php', ['userid' => $userid]));

$user = core_user::get_user($userid, '*', MUST_EXIST);
$url = new moodle_url('/local/nit_tutors/admin/application.php', ['userid' => $userid]);
$str = fn(string $k, $a = null) => get_string($k, 'local_nit_tutors', $a);

// Opening the page must not create an application for any user id typed in the URL.
$row = application::get($userid) ?? (object) ['status' => application::DRAFT, 'timesubmitted' => 0, 'timereviewed' => 0,
    'reviewnote' => '', 'videostatus' => '', 'videonote' => ''];
$status = (string) $row->status;
$updates = application::has_updates($userid);
$mode = $status === application::APPROVED ? ($updates ? 'updates' : 'approved')
    : ($status === application::SUSPENDED ? 'suspended' : 'application');

// ── Decision ─────────────────────────────────────────────────────────────────
$action = optional_param('action', '', PARAM_ALPHA);
if (in_array($action, ['approve', 'reject'], true) && confirm_sesskey()) {
    $note = trim(optional_param('note', '', PARAM_TEXT));
    $reviewer = (int) $USER->id;
    try {
        if ($action === 'reject' && $note === '') {
            throw new moodle_exception('err_notereq', 'local_nit_tutors');
        }
        if ($mode === 'updates') {
            application::review_updates($userid, $action === 'approve', $note);
        } else if ($action === 'approve') {
            application::approve($userid, $reviewer, $note);
        } else if ($mode === 'approved') {
            application::suspend($userid, $reviewer, $note);
        } else {
            application::reject($userid, $reviewer, $note);
        }
        redirect($url, $str('done'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
}

// ── Page data ────────────────────────────────────────────────────────────────
$profile = (new teacher_service())->profile($userid);
$player = video::player($userid, false);
$usercontext = context_user::instance($userid);
$date = get_string('strftimedatetimeshort', 'langconfig');

// A stored file as a link that opens in the browser (not a download), with a preview for an image.
$fileview = function(?stored_file $file, string $area, int $itemid) use ($usercontext): array {
    if (!$file) {
        return ['fileurl' => '', 'filename' => '', 'isimage' => false];
    }
    return [
        'fileurl' => moodle_url::make_pluginfile_url($usercontext->id, 'local_nit_tutors', $area, $itemid,
            $file->get_filepath(), $file->get_filename(), false)->out(false),
        'filename' => $file->get_filename(),
        'isimage' => $file->is_valid_image(),
    ];
};

// An approved teacher's change waiting for review is shown as it will look once approved.
$waiting = $mode === 'updates' ? pending::get($userid) : null;
$headline = $profile['headline'];
$subjects = $profile['subjects'];
$bio = format_text((string) $user->description, (int) $user->descriptionformat, ['context' => $usercontext]);
$headlinenew = $subjectsnew = $bionew = $videonew = false;
if ($waiting !== null) {
    $flat = fn($s) => trim(preg_replace('/\s+/u', ' ', (string) $s));
    $headlinenew = $flat($waiting['headline']) !== $flat($headline);
    $headline = (string) $waiting['headline'];
    $proposed = array_values(array_filter(array_map('strval', (array) $waiting['subjects'])));
    $subjectsnew = array_diff($proposed, $subjects) || array_diff($subjects, $proposed);
    $subjects = $proposed;
    $bionew = $flat($waiting['bio']) !== $flat(html_to_text((string) $user->description, 0, false));
    $bio = nl2br(s(trim((string) $waiting['bio'])));
    $waitingvideo = pending::file($userid, pending::AREA_VIDEO, 0);
    if ($waiting['videotype'] === video::LINK && ($embed = video::embed_url((string) $waiting['videourl']))) {
        $videonew = $waiting['videourl'] !== (string) $row->videourl || $row->videotype !== video::LINK;
        $player = $videonew ? ['embed' => $embed, 'src' => null, 'mimetype' => ''] : $player;
    } else if ($waiting['videotype'] === video::UPLOAD && $waitingvideo) {
        $videonew = true;
        $player = ['embed' => null, 'src' => $fileview($waitingvideo, pending::AREA_VIDEO, 0)['fileurl'],
            'mimetype' => $waitingvideo->get_mimetype()];
    }
}

$quals = [];
if ($waiting !== null) {
    $live = [];
    foreach (qualifications::list($userid) as $q) {
        $live[(int) $q->id] = $q;
    }
    foreach ((array) $waiting['qualtitle'] as $i => $title) {
        $id = (int) ($waiting['qualid'][$i] ?? 0);
        if (trim((string) $title) === '' || !empty($waiting['qualdelete'][$i]) || ($id && !isset($live[$id]))) {
            continue;
        }
        $old = $id ? $live[$id] : null;
        $year = (int) ($waiting['qualyear'][$i] ?? 0);
        $institution = (string) ($waiting['qualinstitution'][$i] ?? '');
        $file = pending::file($userid, pending::AREA_QUAL, (int) $i);
        $quals[] = [
            'title' => format_string((string) $title),
            'institution' => format_string($institution),
            'year' => $year ?: '',
            'isnew' => !$old || $file || trim($old->title) !== trim((string) $title)
                || trim((string) $old->institution) !== trim($institution) || (int) $old->year !== $year,
        ] + ($file ? $fileview($file, pending::AREA_QUAL, (int) $i)
            : $fileview($id ? qualifications::file($userid, $id) : null, qualifications::AREA, $id));
    }
} else {
    foreach (qualifications::list($userid) as $q) {
        $quals[] = [
            'title' => format_string($q->title),
            'institution' => format_string((string) $q->institution),
            'year' => (int) $q->year ?: '',
            'isnew' => $status === application::APPROVED && !$q->verified,
        ] + $fileview(qualifications::file($userid, (int) $q->id), qualifications::AREA, (int) $q->id);
    }
}
$answers = [];
$values = fields::answers($userid);
foreach (fields::all(false) as $f) {
    $file = null;
    $area = fields::AREA;
    $value = (string) ($values[$f->id] ?? '');
    if ($f->datatype === 'file') {
        if ($waiting !== null && ($file = pending::file($userid, pending::AREA_FIELD, (int) $f->id))) {
            $area = pending::AREA_FIELD;
        } else {
            $files = get_file_storage()->get_area_files($usercontext->id, 'local_nit_tutors', fields::AREA, $f->id, 'filename', false);
            $file = reset($files) ?: null;
        }
    } else if ($waiting !== null && array_key_exists((int) $f->id, (array) $waiting['field'])) {
        try {
            $value = (string) fields::clean($f, teacher_input::field_value($f, $waiting['field'][(int) $f->id]));
        } catch (moodle_exception $e) {
            $value = (string) $waiting['field'][(int) $f->id];
        }
    }
    $answers[] = ['label' => format_string($f->name), 'value' => fields::display($f, $value),
        'isnew' => $value !== (string) ($values[$f->id] ?? '') || $area === pending::AREA_FIELD]
        + $fileview($file, $area, (int) $f->id);
}

$picture = new user_picture($user);
$picture->size = 120;
$labels = [
    'application' => ['act_approve', 'act_reject'],
    'updates' => ['act_approveupdates', 'act_rejectupdates'],
    'approved' => ['', 'act_suspend'],
    'suspended' => ['act_reinstate', ''],
];
[$approvelabel, $rejectlabel] = $labels[$mode];
$canact = $mode !== 'application' || in_array($status, [application::PENDING, application::CHANGES, application::REJECTED,
    application::DRAFT], true);

$context = [
    'sesskey' => sesskey(),
    'actionurl' => $url->out(false),
    'name' => fullname($user),
    'email' => $user->email,
    'phone' => $user->phone1,
    'photo' => $picture->get_url($PAGE)->out(false),
    'profileurl' => (new moodle_url('/user/profile.php', ['id' => $userid]))->out(false),
    'publicurl' => (new moodle_url('/local/academy/teacher.php', ['id' => $userid]))->out(false),
    'status' => $status,
    'statuslabel' => $str('status_' . $status),
    'submitted' => $row->timesubmitted ? userdate($row->timesubmitted, $date) : '',
    'reviewed' => $row->timereviewed ? userdate($row->timereviewed, $date) : '',
    'reviewnote' => (string) $row->reviewnote,
    'headline' => format_string($headline),
    'headlinenew' => $headlinenew,
    'bio' => $bio,
    'bionew' => $bionew,
    'subjects' => array_map(fn($s) => ['name' => format_string($s)], $subjects),
    'subjectsnew' => (bool) $subjectsnew,
    'price' => $profile['lesson_flex'],
    'hasvideo' => $player !== null,
    'video' => $player,
    'videonew' => $videonew || ($updates && $row->videostatus === 'pending'),
    'videostatus' => $row->videostatus ? $str('videostatus_' . $row->videostatus) : '',
    'videonote' => (string) $row->videonote,
    'quals' => $quals,
    'hasquals' => !empty($quals),
    'answers' => $answers,
    'hasanswers' => !empty($answers),
    'missing' => array_map(fn($m) => ['label' => $m], array_values(application::missing($userid))),
    'decisionhelp' => $str('decision_' . $mode),
    'canact' => $canact,
    'approvelabel' => $approvelabel ? $str($approvelabel) : '',
    'rejectlabel' => $rejectlabel ? $str($rejectlabel) : '',
    'backurl' => (new moodle_url('/local/nit_tutors/admin/applications.php', ['status' => $updates ? 'updates' : $status]))->out(false),
];

echo $OUTPUT->header();
echo admin_tabs::render('applications');
echo $OUTPUT->render_from_template('local_nit_tutors/admin_review', $context);
echo $OUTPUT->footer();

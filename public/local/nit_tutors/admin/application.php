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
 * Review one teacher: everything they sent, the intro video, the certificates (opened in the
 * browser); approve, ask for changes, reject, suspend; verify qualifications; their badges.
 *
 * One Approve and one Reject button: while an approved teacher's new intro video waits, they decide
 * the video (Reject needs the reason, which the teacher sees); otherwise the application. A video
 * sent with a pending application is approved together with it.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use local_nit_tutors\local\admin_tabs;
use local_nit_tutors\local\application;
use local_nit_tutors\local\badge;
use local_nit_tutors\local\fields;
use local_nit_tutors\local\qualifications;
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
// An approved teacher's new intro video: Approve / Reject decide the video, not the application.
$videoonly = $status === application::APPROVED && $row->videostatus === 'pending';

// ── Actions ──────────────────────────────────────────────────────────────────
$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '' && confirm_sesskey()) {
    $note = trim(optional_param('note', '', PARAM_TEXT));
    $reviewer = (int) $USER->id;
    try {
        switch ($action) {
            case 'approve':
                $videoonly ? video::review($userid, true, $note) : application::approve($userid, $reviewer, $note);
                break;
            case 'reject':
                if ($note === '') {
                    throw new moodle_exception('err_notereq', 'local_nit_tutors');
                }
                $videoonly ? video::review($userid, false, $note) : application::reject($userid, $reviewer, $note);
                break;
            case 'changes':
                application::request_changes($userid, $reviewer, $note);
                break;
            case 'suspend':
                application::suspend($userid, $reviewer, $note);
                break;
            case 'verify':
            case 'unverify':
                $qualid = required_param('qualid', PARAM_INT);
                if ($DB->record_exists(qualifications::TABLE, ['id' => $qualid, 'userid' => $userid])) {
                    qualifications::verify($qualid, $action === 'verify');
                }
                break;
            case 'badges':
                foreach (optional_param_array('mode', [], PARAM_ALPHA) as $badgeid => $mode) {
                    if (badge::get((int) $badgeid) && badge::mode((int) $badgeid, $userid) !== $mode) {
                        badge::set_mode($userid, (int) $badgeid, $mode);
                    }
                }
                break;
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

/**
 * A stored file as a link that opens in the browser (not a download), with a preview for an image.
 *
 * @param stored_file|null $file
 * @param string $area
 * @param int $itemid
 * @return array {fileurl, filename, isimage}
 */
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

$quals = [];
foreach (qualifications::list($userid) as $q) {
    $quals[] = [
        'id' => (int) $q->id,
        'title' => format_string($q->title),
        'institution' => format_string((string) $q->institution),
        'year' => (int) $q->year ?: '',
        'verified' => (bool) $q->verified,
    ] + $fileview(qualifications::file($userid, (int) $q->id), qualifications::AREA, (int) $q->id);
}
$answers = [];
$values = fields::answers($userid);
foreach (fields::all(false) as $f) {
    $value = (string) ($values[$f->id] ?? '');
    $file = null;
    if ($f->datatype === 'file') {
        $files = get_file_storage()->get_area_files($usercontext->id, 'local_nit_tutors', fields::AREA, $f->id, 'filename', false);
        $file = reset($files) ?: null;
    }
    $answers[] = ['label' => format_string($f->name), 'value' => fields::display($f, $value)]
        + $fileview($file, fields::AREA, (int) $f->id);
}

// Badges: each one with the reviewer's choice, the rules and whether this teacher wears it.
$stats = badge::stats($userid);
$wearing = array_map(fn($b) => (int) $b->id, badge::held($userid));
$badges = [];
foreach (badge::all() as $b) {
    $mode = badge::mode((int) $b->id, $userid);
    $badges[] = [
        'id' => (int) $b->id,
        'name' => format_string($b->name),
        'color' => $b->color,
        'rules' => badge::rules_text($b),
        'isauto' => $b->method === badge::METHOD_AUTO,
        'meets' => badge::meets($b, $stats),
        'wears' => in_array((int) $b->id, $wearing, true),
        'modes' => array_map(fn($m) => ['value' => $m, 'selected' => $m === $mode,
            'label' => $str('badgemode_' . $m . ($b->method === badge::METHOD_MANUAL && $m === badge::AUTO ? '_manual' : ''))],
            [badge::AUTO, badge::ON, badge::OFF]),
    ];
}

$picture = new user_picture($user);
$picture->size = 120;

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
    'headline' => format_string($profile['headline']),
    'bio' => format_text((string) $user->description, (int) $user->descriptionformat, ['context' => $usercontext]),
    'subjects' => array_map(fn($s) => ['name' => format_string($s)], $profile['subjects']),
    'price' => $profile['lesson_flex'],
    'hasvideo' => $player !== null,
    'video' => $player,
    'videostatus' => $row->videostatus ? $str('videostatus_' . $row->videostatus) : '',
    'videonote' => (string) $row->videonote,
    'quals' => $quals,
    'hasquals' => !empty($quals),
    'answers' => $answers,
    'hasanswers' => !empty($answers),
    'missing' => array_map(fn($m) => ['label' => $m], array_values(application::missing($userid))),
    'videoonly' => $videoonly,
    'canapprove' => $status !== application::APPROVED || $videoonly,
    'canchanges' => in_array($status, [application::PENDING, application::REJECTED], true),
    'canreject' => in_array($status, [application::PENDING, application::CHANGES, application::DRAFT], true) || $videoonly,
    'cansuspend' => $status === application::APPROVED,
    'approvelabel' => $str($videoonly ? 'act_approvevideo' : ($status === application::SUSPENDED ? 'act_reinstate' : 'act_approve')),
    'rejectlabel' => $str($videoonly ? 'act_rejectvideo' : 'act_reject'),
    'hasbadges' => !empty($badges),
    'badges' => $badges,
    'isapproved' => $status === application::APPROVED,
    'stats' => $str('badge_stats', (object) [
        'rating' => format_float($stats['rating'], 1), 'reviews' => $stats['reviews'], 'lessons' => $stats['lessons'],
        'absence' => format_float($stats['absencepct'], 1)]),
    'badgesurl' => (new moodle_url('/local/nit_tutors/admin/badges.php'))->out(false),
    'backurl' => (new moodle_url('/local/nit_tutors/admin/applications.php', ['status' => $status]))->out(false),
];

echo $OUTPUT->header();
echo admin_tabs::render('applications');
echo $OUTPUT->render_from_template('local_nit_tutors/admin_review', $context);
echo $OUTPUT->footer();

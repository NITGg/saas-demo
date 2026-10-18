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
 * Review one teacher: everything they sent, the intro video, the certificates; approve, ask for
 * changes, reject, suspend; approve/refuse a new video; verify qualifications; pin the badge.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_tutors\local\application;
use local_nit_tutors\local\badge;
use local_nit_tutors\local\fields;
use local_nit_tutors\local\qualifications;
use local_nit_tutors\local\video;
use local_nit_lessons\service\teacher_service;

$userid = required_param('userid', PARAM_INT);
admin_externalpage_setup('local_nit_tutors_applications', '', ['userid' => $userid],
    new moodle_url('/local/nit_tutors/admin/application.php'));
$syscontext = context_system::instance();
require_capability('local/nit_tutors:review', $syscontext);

$user = core_user::get_user($userid, '*', MUST_EXIST);
$url = new moodle_url('/local/nit_tutors/admin/application.php', ['userid' => $userid]);
$PAGE->set_url($url);
$str = fn(string $k, $a = null) => get_string($k, 'local_nit_tutors', $a);

// ── Actions ──────────────────────────────────────────────────────────────────
$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '' && confirm_sesskey()) {
    $note = trim(optional_param('note', '', PARAM_TEXT));
    $reviewer = (int) $USER->id;
    try {
        switch ($action) {
            case 'approve':
                application::approve($userid, $reviewer, $note);
                break;
            case 'changes':
                application::request_changes($userid, $reviewer, $note);
                break;
            case 'reject':
                application::reject($userid, $reviewer, $note);
                break;
            case 'suspend':
                application::suspend($userid, $reviewer, $note);
                break;
            case 'videook':
                video::review($userid, true, $note);
                break;
            case 'videono':
                if ($note === '') {
                    throw new moodle_exception('err_notereq', 'local_nit_tutors');
                }
                video::review($userid, false, $note);
                break;
            case 'verify':
            case 'unverify':
                $qualid = required_param('qualid', PARAM_INT);
                if ($DB->record_exists(qualifications::TABLE, ['id' => $qualid, 'userid' => $userid])) {
                    qualifications::verify($qualid, $action === 'verify');
                }
                break;
            case 'badge':
                require_capability('local/nit_tutors:review', $syscontext);
                badge::set_mode($userid, required_param('mode', PARAM_ALPHA));
                break;
        }
        redirect($url, $str('done'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
}

// ── Page data ────────────────────────────────────────────────────────────────
// Opening the page must not create an application for any user id typed in the URL.
$row = application::get($userid) ?? (object) ['status' => application::DRAFT, 'timesubmitted' => 0, 'timereviewed' => 0,
    'reviewnote' => '', 'videostatus' => '', 'videonote' => '', 'badge' => 0, 'badgemode' => badge::AUTO];
$profile = (new teacher_service())->profile($userid);
$player = video::player($userid, false);
$usercontext = context_user::instance($userid);
$date = get_string('strftimedatetimeshort', 'langconfig');

$quals = [];
foreach (qualifications::list($userid) as $q) {
    $file = qualifications::file($userid, (int) $q->id);
    $quals[] = [
        'id' => (int) $q->id,
        'title' => format_string($q->title),
        'institution' => format_string((string) $q->institution),
        'year' => (int) $q->year ?: '',
        'verified' => (bool) $q->verified,
        'fileurl' => $file ? moodle_url::make_pluginfile_url($usercontext->id, 'local_nit_tutors', qualifications::AREA,
            $q->id, $file->get_filepath(), $file->get_filename(), true)->out(false) : '',
        'filename' => $file ? $file->get_filename() : '',
    ];
}
$answers = [];
$values = fields::answers($userid);
foreach (fields::all(false) as $f) {
    $value = (string) ($values[$f->id] ?? '');
    $item = ['label' => format_string($f->name), 'value' => fields::display($f, $value), 'fileurl' => '', 'filename' => ''];
    if ($f->datatype === 'file') {
        $files = get_file_storage()->get_area_files($usercontext->id, 'local_nit_tutors', fields::AREA, $f->id, 'filename', false);
        if ($file = reset($files)) {
            $item['fileurl'] = moodle_url::make_pluginfile_url($usercontext->id, 'local_nit_tutors', fields::AREA, $f->id,
                $file->get_filepath(), $file->get_filename(), true)->out(false);
            $item['filename'] = $file->get_filename();
        }
    }
    $answers[] = $item;
}
$stats = badge::stats($userid);
$rules = badge::rules();
$status = (string) $row->status;
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
    'videopending' => $row->videostatus === 'pending',
    'videonote' => (string) $row->videonote,
    'quals' => $quals,
    'hasquals' => !empty($quals),
    'answers' => $answers,
    'hasanswers' => !empty($answers),
    'missing' => array_map(fn($m) => ['label' => $m], array_values(application::missing($userid))),
    'canapprove' => $status !== application::APPROVED,
    'canchanges' => in_array($status, [application::PENDING, application::REJECTED], true),
    'canreject' => in_array($status, [application::PENDING, application::CHANGES, application::DRAFT], true),
    'cansuspend' => $status === application::APPROVED,
    'approvelabel' => $str($status === application::SUSPENDED ? 'act_reinstate' : 'act_approve'),
    'badge' => (bool) $row->badge,
    'badgemodes' => array_map(fn($m) => ['value' => $m, 'label' => $str('badgemode_' . $m), 'selected' => $row->badgemode === $m],
        [badge::AUTO, badge::ON, badge::OFF]),
    'stats' => $str('badge_stats', (object) [
        'rating' => format_float($stats['rating'], 1), 'reviews' => $stats['reviews'], 'lessons' => $stats['lessons'],
        'absence' => format_float($stats['absencepct'], 1)]),
    'rules' => $str('badge_rules', (object) [
        'rating' => format_float($rules['minrating'], 1), 'reviews' => $rules['minreviews'], 'lessons' => $rules['minlessons'],
        'absence' => format_float($rules['maxabsence'], 1)]),
    'earned' => badge::earned($userid),
    'backurl' => (new moodle_url('/local/nit_tutors/admin/applications.php', ['status' => $status]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_nit_tutors/admin_review', $context);
echo $OUTPUT->footer();

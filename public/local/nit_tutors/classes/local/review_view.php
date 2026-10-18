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

namespace local_nit_tutors\local;

use local_nit_lessons\service\teacher_service;

/**
 * Reviewing one teacher: what they filled in and the two decisions. Shared by the reviewer's page
 * (admin/application.php, template admin_review) and the app (api.php → admin_get_application,
 * admin_decide), so both see and do exactly the same.
 *
 *   mode application  a new application          approve = approve the teacher | reject = reject
 *   mode updates      approved, with changes     approve = put them live       | reject = throw them away
 *   mode approved     approved, no changes       reject = stop the teacher (suspend)
 *   mode suspended                               approve = reinstate
 *
 * For mode updates the data is the new version (a change waiting for review, {@see pending}),
 * with *new flags on what changed.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class review_view {

    /** Mode => [approve label, reject label] ('' = not offered). */
    const LABELS = [
        'application' => ['act_approve', 'act_reject'],
        'updates' => ['act_approveupdates', 'act_rejectupdates'],
        'approved' => ['', 'act_suspend'],
        'suspended' => ['act_reinstate', ''],
    ];

    /**
     * The teacher's application row (a blank draft when they never started one: opening the
     * review must not create an application for any user id).
     *
     * @param int $userid
     * @return \stdClass
     */
    private static function row(int $userid): \stdClass {
        return application::get($userid) ?? (object) ['status' => application::DRAFT, 'timesubmitted' => 0,
            'timereviewed' => 0, 'reviewnote' => '', 'videostatus' => '', 'videonote' => '', 'videotype' => '',
            'videourl' => ''];
    }

    /**
     * The review mode.
     *
     * @param int $userid
     * @return string application|updates|approved|suspended
     */
    public static function mode(int $userid): string {
        $status = (string) self::row($userid)->status;
        if ($status === application::APPROVED) {
            return application::has_updates($userid) ? 'updates' : 'approved';
        }
        return $status === application::SUSPENDED ? 'suspended' : 'application';
    }

    /**
     * Take a decision.
     *
     * @param int $userid
     * @param string $action approve|reject
     * @param string $note the reason (required to reject; the teacher reads it)
     * @param int $reviewerid
     * @throws \moodle_exception notereq, decisionnotoffered, teacherlimit, ...
     */
    public static function decide(int $userid, string $action, string $note, int $reviewerid): void {
        $note = trim($note);
        $mode = self::mode($userid);
        [$approve, $reject] = self::LABELS[$mode];
        if (!in_array($action, ['approve', 'reject'], true) || ($action === 'approve' ? $approve : $reject) === ''
                || !self::can_act($userid, $mode)) {
            throw new \moodle_exception('err_decisionnotoffered', 'local_nit_tutors');
        }
        if ($action === 'reject' && $note === '') {
            throw new \moodle_exception('err_notereq', 'local_nit_tutors');
        }
        if ($mode === 'updates') {
            application::review_updates($userid, $action === 'approve', $note);
        } else if ($action === 'approve') {
            application::approve($userid, $reviewerid, $note);
        } else if ($mode === 'approved') {
            application::suspend($userid, $reviewerid, $note);
        } else {
            application::reject($userid, $reviewerid, $note);
        }
    }

    /**
     * Can the reviewer decide now? A new application only once there is one to decide.
     *
     * @param int $userid
     * @param string $mode
     * @return bool
     */
    private static function can_act(int $userid, string $mode): bool {
        return $mode !== 'application' || (application::get($userid) !== null && in_array(self::row($userid)->status,
            [application::PENDING, application::CHANGES, application::REJECTED, application::DRAFT], true));
    }

    /**
     * Everything the reviewer sees (template admin_review, without the page's own URLs).
     *
     * @param int $userid
     * @param callable|null $fileurl maps a file URL for the caller (the app adds its token); null = as is
     * @return array
     */
    public static function context(int $userid, ?callable $fileurl = null): array {
        global $PAGE;
        $fileurl = $fileurl ?? fn(string $u) => $u;
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $str = fn(string $k, $a = null) => get_string($k, 'local_nit_tutors', $a);
        $row = self::row($userid);
        $status = (string) $row->status;
        $updates = application::has_updates($userid);
        $mode = self::mode($userid);
        $profile = (new teacher_service())->profile($userid);
        $player = video::player($userid, false);
        $usercontext = \context_user::instance($userid);
        $date = get_string('strftimedatetimeshort', 'langconfig');

        // A stored file as a link that opens in the browser (not a download), with a preview for an image.
        $fileview = function(?\stored_file $file, string $area, int $itemid) use ($usercontext, $fileurl): array {
            if (!$file) {
                return ['fileurl' => '', 'filename' => '', 'isimage' => false];
            }
            return [
                'fileurl' => $fileurl(\moodle_url::make_pluginfile_url($usercontext->id, 'local_nit_tutors', $area, $itemid,
                    $file->get_filepath(), $file->get_filename(), false)->out(false)),
                'filename' => $file->get_filename(),
                'isimage' => $file->is_valid_image(),
            ];
        };
        if ($player && !empty($player['src'])) {
            $player['src'] = $fileurl($player['src']);
        }

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
                    $files = get_file_storage()->get_area_files($usercontext->id, 'local_nit_tutors', fields::AREA, $f->id,
                        'filename', false);
                    $file = reset($files) ?: null;
                }
            } else if ($waiting !== null && array_key_exists((int) $f->id, (array) $waiting['field'])) {
                try {
                    $value = (string) fields::clean($f, teacher_input::field_value($f, $waiting['field'][(int) $f->id]));
                } catch (\moodle_exception $e) {
                    $value = (string) $waiting['field'][(int) $f->id];
                }
            }
            $answers[] = ['label' => format_string($f->name), 'value' => fields::display($f, $value),
                'isnew' => $value !== (string) ($values[$f->id] ?? '') || $area === pending::AREA_FIELD]
                + $fileview($file, $area, (int) $f->id);
        }

        $picture = new \user_picture($user);
        $picture->size = 120;
        [$approvelabel, $rejectlabel] = self::LABELS[$mode];

        return [
            'userid' => $userid,
            'mode' => $mode,
            'name' => fullname($user),
            'email' => $user->email,
            'phone' => $user->phone1,
            'photo' => $fileurl($picture->get_url($PAGE)->out(false)),
            'profileurl' => (new \moodle_url('/user/profile.php', ['id' => $userid]))->out(false),
            'publicurl' => (new \moodle_url('/local/academy/teacher.php', ['id' => $userid]))->out(false),
            'status' => $status,
            'statuslabel' => $str('status_' . $status),
            'timesubmitted' => (int) $row->timesubmitted,
            'timereviewed' => (int) $row->timereviewed,
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
            'canact' => self::can_act($userid, $mode),
            'canapprove' => $approvelabel !== '' && self::can_act($userid, $mode),
            'canreject' => $rejectlabel !== '' && self::can_act($userid, $mode),
            'approvelabel' => $approvelabel ? $str($approvelabel) : '',
            'rejectlabel' => $rejectlabel ? $str($rejectlabel) : '',
            'liststatus' => $updates ? 'updates' : $status,
        ];
    }
}

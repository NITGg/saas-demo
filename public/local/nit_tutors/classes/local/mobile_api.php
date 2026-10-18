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

use local_nit_lessons\local\profile_section;
use local_nit_lessons\service\teacher_service;

/**
 * Teachers for the mobile app (`/local/nit_tutors/api.php`, docs/apis/teachers-mobile-api.md):
 * joining as a teacher, the teacher profile and its review status, finding a teacher, and the
 * reviewer's list and decisions.
 *
 * Same rules as the web pages — it calls the same classes (teacher_input, profile_save, pending,
 * search_view, review_view) — so a teacher, a student and a reviewer get the same answers in
 * the app as on the site.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mobile_api {

    /** @var callable|null makes a pluginfile URL loadable by the app (endpoint::file_url) */
    private static $fileurl = null;

    /**
     * Set how file URLs are given to the app.
     *
     * @param callable $fileurl function(string $url): string
     */
    public static function set_file_url(callable $fileurl): void {
        self::$fileurl = $fileurl;
    }

    /**
     * A file URL for the app.
     *
     * @param string $url
     * @return string
     */
    private static function url(string $url): string {
        return $url === '' ? '' : (self::$fileurl ? (self::$fileurl)($url) : $url);
    }

    /**
     * A stored file for the app: {filename, url, mimetype, isimage}, or null.
     *
     * @param \stored_file|null $file
     * @return array|null
     */
    private static function file(?\stored_file $file): ?array {
        if (!$file) {
            return null;
        }
        return [
            'filename' => $file->get_filename(),
            'url' => self::url(\moodle_url::make_pluginfile_url($file->get_contextid(), 'local_nit_tutors',
                $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename(), false)->out(false)),
            'mimetype' => (string) $file->get_mimetype(),
            'isimage' => $file->is_valid_image(),
        ];
    }

    // ── The teacher's data form ─────────────────────────────────────────────────

    /**
     * What the teacher's data form asks and its rules: subjects to pick from, the video, the
     * qualifications, the admin's extra fields, and the weekly hours rule.
     *
     * @param int $userid 0 = a new teacher (sign-up)
     * @return array
     */
    public static function form(int $userid): array {
        $fields = [];
        foreach (fields::all() as $f) {
            $fields[] = [
                'id' => (int) $f->id,
                'shortname' => (string) $f->shortname,
                'label' => format_string($f->name),
                'description' => format_string((string) $f->description),
                'datatype' => (string) $f->datatype,
                'required' => (bool) $f->required,
                'options' => $f->datatype === 'select'
                    ? array_map(fn($o) => ['value' => $o, 'label' => format_string($o)], fields::options($f)) : [],
            ];
        }
        $days = [];
        foreach ([6, 0, 1, 2, 3, 4, 5] as $d) {
            $days[] = ['value' => $d, 'label' => get_string('day' . $d, 'local_nit_lessons')];
        }
        return [
            'subjects' => array_map(fn($s) => ['value' => $s, 'label' => format_string($s)],
                teacher_service::subject_options($userid)),
            'headlinemaxlength' => 255,
            'video' => [
                'required' => (bool) application::setting_int('requirevideo', 1),
                'types' => [video::LINK, video::UPLOAD],
                'linkhosts' => ['youtube', 'vimeo'],
                'accepted' => video::ACCEPTED,
                'maxbytes' => video::max_bytes(),
                'maxlabel' => str_replace('&nbsp;', ' ', display_size(video::max_bytes())),
            ],
            'qualifications' => [
                'min' => application::setting_int('minqualifications', 1),
                'max' => teacher_input::MAXQUALS,
                'accepted' => qualifications::ACCEPTED,
                'maxbytes' => teacher_input::MAXFILEBYTES,
                'maxlabel' => str_replace('&nbsp;', ' ', display_size(teacher_input::MAXFILEBYTES)),
                'minyear' => 1950,
                'maxyear' => (int) date('Y') + 1,
            ],
            'fields' => $fields,
            'hours' => [
                'days' => $days,
                'minminutes' => (int) \local_nit_lessons\entity\lesson::DEFAULT_DURATION,
            ],
        ];
    }

    /**
     * The teacher sign-up form: the account fields and the teacher's data form.
     *
     * @return array
     */
    public static function signup_form(): array {
        $registration = '\local_academy\local\registration';
        return [
            'enabled' => signup::enabled(),
            'account' => [
                'fields' => [
                    ['name' => 'firstname', 'type' => 'text', 'required' => true],
                    ['name' => 'lastname', 'type' => 'text', 'required' => true],
                    ['name' => 'email', 'type' => 'email', 'required' => true],
                    ['name' => 'phone', 'type' => 'tel', 'required' => true],
                    ['name' => 'password', 'type' => 'password', 'required' => true],
                    ['name' => 'password2', 'type' => 'password', 'required' => true],
                ],
                'passwordpolicy' => class_exists($registration) ? $registration::password_policy_text() : '',
                'termsurl' => (new \moodle_url('/local/nit_pages/page.php',
                    ['p' => 'terms', 'embedded' => 1, 'lang' => current_language()]))->out(false),
            ],
            'teacher' => self::form(0),
        ];
    }

    // ── Reading the app's request ──────────────────────────────────────────────

    /**
     * The teacher's data as the app sends it, in teacher_input's shape, plus the files and the
     * lessons card (when the request carries one).
     *
     *   headline, bio, videotype (link|upload), videourl, subjects (JSON ["English", …]),
     *   quals (JSON [{row?, id, title, institution, year, delete}]), fields (JSON {"<fieldid>": value}),
     *   files: videofile, qualfile[<position in quals>], fieldfile[<fieldid>];
     *   card (any of): available (0|1), lesson_flex, hours (JSON [{dayofweek, starttime, endtime}]).
     *
     * @param callable $json function(string $name): array — reads a JSON parameter (endpoint::json_param)
     * @return array [in, files, card|null]
     */
    public static function read_request(callable $json): array {
        $in = [
            'headline' => optional_param('headline', '', PARAM_TEXT),
            'bio' => optional_param('bio', '', PARAM_TEXT),
            'subjects' => array_values(array_map(fn($s) => clean_param((string) $s, PARAM_TEXT), $json('subjects'))),
            'videotype' => optional_param('videotype', '', PARAM_ALPHA),
            'videourl' => optional_param('videourl', '', PARAM_RAW_TRIMMED),
            'qualid' => [], 'qualtitle' => [], 'qualinstitution' => [], 'qualyear' => [], 'qualdelete' => [],
            'field' => [],
        ];
        // A qualification row keeps its `row` (given by get_application) so a file waiting for review
        // stays with it; a new row takes the next free number.
        $quals = array_values(array_filter($json('quals'), 'is_array'));
        $next = 0;
        foreach ($quals as $q) {
            if (isset($q['row'])) {
                $next = max($next, (int) $q['row'] + 1);
            }
        }
        $rowof = [];
        foreach ($quals as $pos => $q) {
            $row = isset($q['row']) && !isset($in['qualtitle'][(int) $q['row']]) ? (int) $q['row'] : $next++;
            $rowof[$pos] = $row;
            $in['qualid'][$row] = (int) ($q['id'] ?? 0);
            $in['qualtitle'][$row] = clean_param((string) ($q['title'] ?? ''), PARAM_TEXT);
            $in['qualinstitution'][$row] = clean_param((string) ($q['institution'] ?? ''), PARAM_TEXT);
            $in['qualyear'][$row] = (int) ($q['year'] ?? 0);
            if (!empty($q['delete'])) {
                $in['qualdelete'][$row] = 1;
            }
        }
        foreach ($json('fields') as $fid => $value) {
            $in['field'][(int) $fid] = is_scalar($value) ? clean_param((string) $value, PARAM_RAW_TRIMMED) : '';
        }

        $files = teacher_input::files_from_request();
        $qualfiles = [];
        foreach ($files['qualfile'] as $pos => $file) {
            if (isset($rowof[(int) $pos])) {
                $qualfiles[$rowof[(int) $pos]] = $file;
            }
        }
        $files['qualfile'] = $qualfiles;

        $card = null;
        if (self::sent('available') || self::sent('lesson_flex') || self::sent('hours')) {
            $profile = (new teacher_service())->profile((int) $GLOBALS['USER']->id);
            $card = profile_section::card(
                self::sent('available') ? (bool) optional_param('available', 0, PARAM_BOOL) : (bool) $profile['available'],
                $in['headline'], $in['subjects'],
                self::sent('hours') ? $json('hours') : $profile['hours'],
                (string) optional_param('lesson_flex', '', PARAM_RAW_TRIMMED));
        }
        return [$in, $files, $card];
    }

    /**
     * Was a parameter sent?
     *
     * @param string $name
     * @return bool
     */
    private static function sent(string $name): bool {
        return isset($_POST[$name]) || isset($_GET[$name]);
    }

    // ── The teacher's own profile ───────────────────────────────────────────────

    /**
     * The teacher profile: status, what still blocks sending it, a change waiting for review, the
     * values to show in the form (the waiting change when there is one), the lessons card and the
     * form's rules.
     *
     * @param int $userid
     * @return array
     */
    public static function application(int $userid): array {
        $row = application::get($userid);
        $status = $row ? (string) $row->status : '';
        $key = $status === '' ? 'draft' : $status;
        $service = new teacher_service();
        $profile = $service->profile($userid);
        $waiting = pending::get($userid);
        $user = \core_user::get_user($userid);
        $date = get_string('strftimedatetimeshort', 'langconfig');
        $missing = in_array($status, ['', application::DRAFT, application::CHANGES, application::REJECTED], true)
            ? application::missing($userid) : [];

        // Values: the waiting change, else the live data.
        if ($waiting !== null) {
            $in = $waiting;
            $quals = [];
            foreach ((array) $in['qualtitle'] as $i => $title) {
                $id = (int) ($in['qualid'][$i] ?? 0);
                if (!empty($in['qualdelete'][$i]) || trim((string) $title) === '') {
                    continue;
                }
                $file = pending::file($userid, pending::AREA_QUAL, (int) $i) ?? ($id ? qualifications::file($userid, $id) : null);
                $quals[] = ['row' => (int) $i, 'id' => $id, 'title' => (string) $title,
                    'institution' => (string) ($in['qualinstitution'][$i] ?? ''), 'year' => (int) ($in['qualyear'][$i] ?? 0),
                    'verified' => false, 'file' => self::file($file)];
            }
            $videofile = pending::file($userid, pending::AREA_VIDEO, 0) ?? video::file($userid);
        } else {
            $in = [
                'headline' => $profile['headline'],
                'bio' => $user ? trim(html_to_text((string) $user->description, 0, false)) : '',
                'subjects' => $profile['subjects'],
                'videotype' => $row ? (string) $row->videotype : '',
                'videourl' => $row ? (string) $row->videourl : '',
                'field' => fields::answers($userid),
            ];
            $quals = [];
            foreach (qualifications::list($userid) as $i => $q) {
                $quals[] = ['row' => (int) $i, 'id' => (int) $q->id, 'title' => (string) $q->title,
                    'institution' => (string) $q->institution, 'year' => (int) $q->year, 'verified' => (bool) $q->verified,
                    'file' => self::file(qualifications::file($userid, (int) $q->id))];
            }
            $videofile = video::file($userid);
        }
        $fieldvalues = [];
        $fieldfiles = [];
        $answers = fields::answers($userid);
        foreach (fields::all() as $f) {
            if ($f->datatype === 'file') {
                $files = get_file_storage()->get_area_files(\context_user::instance($userid)->id, 'local_nit_tutors',
                    fields::AREA, $f->id, 'filename', false);
                $file = ($waiting !== null ? pending::file($userid, pending::AREA_FIELD, (int) $f->id) : null)
                    ?? (reset($files) ?: null);
                $fieldfiles[(string) $f->id] = self::file($file);
                continue;
            }
            $value = array_key_exists((int) $f->id, (array) $in['field']) ? $in['field'][(int) $f->id]
                : ($answers[$f->id] ?? '');
            $fieldvalues[(string) $f->id] = (string) $value;
        }
        $player = video::player($userid, false);
        if ($player && !empty($player['src'])) {
            $player['src'] = self::url($player['src']);
        }
        $publicpage = application::is_approved($userid);

        return [
            'userid' => $userid,
            'status' => $key,
            'statuslabel' => get_string('status_' . $key, 'local_nit_tutors'),
            'statushelp' => get_string('statushelp_' . $key, 'local_nit_tutors'),
            'reviewnote' => $row && $status !== application::APPROVED ? (string) $row->reviewnote : '',
            'timesubmitted' => $row ? (int) $row->timesubmitted : 0,
            'timereviewed' => $row ? (int) $row->timereviewed : 0,
            'editable' => application::editable($userid),
            'cansubmit' => profile_save::can_submit($userid),
            'missing' => array_map(fn($k, $label) => ['key' => (string) $k, 'label' => $label], array_keys($missing),
                array_values($missing)),
            'haspending' => $waiting !== null,
            'timepending' => $waiting !== null ? (int) $row->timepending : 0,
            'pendingnotice' => $waiting !== null ? get_string('pendingnotice', 'local_nit_tutors',
                userdate((int) $row->timepending, $date)) : '',
            'reviewrequired' => pending::applies($userid),
            'badges' => $status === application::APPROVED ? badge::chips(badge::held($userid)) : [],
            'publicpage' => $publicpage,
            'publicurl' => $publicpage ? (new \moodle_url('/local/academy/teacher.php', ['id' => $userid]))->out(false) : '',
            'values' => [
                'headline' => (string) $in['headline'],
                'bio' => (string) $in['bio'],
                'subjects' => array_values(array_map('strval', (array) $in['subjects'])),
                'videotype' => (string) $in['videotype'] ?: video::LINK,
                'videourl' => (string) $in['videourl'],
                'videofile' => self::file($videofile),
                'quals' => $quals,
                'fields' => (object) $fieldvalues,
                'fieldfiles' => (object) $fieldfiles,
            ],
            'video' => [
                'status' => $row ? (string) $row->videostatus : '',
                'statuslabel' => $row && $row->videostatus ? get_string('videostatus_' . $row->videostatus,
                    'local_nit_tutors') : '',
                'note' => $row ? (string) $row->videonote : '',
                'player' => $player,
            ],
            'lessons' => [
                'available' => (bool) $profile['available'],
                'lesson_flex' => (int) $profile['lesson_flex'],
                'hours' => array_map(fn($h) => ['dayofweek' => (int) $h['dayofweek'], 'starttime' => $h['starttime'],
                    'endtime' => $h['endtime']], $profile['hours']),
                'timezone' => \core_date::get_user_timezone($user),
            ],
            'form' => self::form($userid),
        ];
    }

    /**
     * Save the teacher profile (and send it for review with $send).
     *
     * @param int $userid
     * @param array $in
     * @param array $files
     * @param array|null $card
     * @param bool $send
     * @return array {saved, waitsforreview, submitted, message, application} — or throws invalid_teacher_data
     */
    public static function save(int $userid, array $in, array $files, ?array $card, bool $send): array {
        $result = profile_save::save($userid, $in, $files, $card, $send);
        if ($result['errors']) {
            throw new invalid_signup($result['errors']);
        }
        return [
            'saved' => true,
            'waitsforreview' => $result['waits'],
            'submitted' => $result['submitted'],
            'message' => get_string($result['submitted'] ? 'submitted' : ($result['waits'] ? 'savedforreview' : 'saved'),
                'local_nit_tutors'),
            'application' => self::application($userid),
        ];
    }

    /**
     * Send the application for review as it is saved.
     *
     * @param int $userid
     * @return array {submitted, message, application}
     */
    public static function submit(int $userid): array {
        if (!profile_save::can_submit($userid)) {
            throw new \moodle_exception('err_noteditable', 'local_nit_tutors');
        }
        application::submit($userid);
        return ['submitted' => true, 'message' => get_string('submitted', 'local_nit_tutors'),
            'application' => self::application($userid)];
    }

    // ── Finding a teacher ──────────────────────────────────────────────────────

    /**
     * The search filters and sort options (each [{value, label}]) and the field filters.
     *
     * @return array
     */
    public static function filters(): array {
        $view = search_view::context(search::normalise([]), 0, new \moodle_url('/local/nit_category/index.php'));
        $plain = fn(array $opts) => array_map(fn($o) => ['value' => $o['value'], 'label' => $o['label']], $opts);
        $f = $view['filters'];
        return [
            'subjects' => $plain($f['subjects']),
            'days' => $plain($f['days']),
            'times' => $plain($f['times']),
            'ratings' => $plain($f['ratings']),
            'badges' => $plain($f['badges']),
            'fields' => array_map(fn($ff) => ['id' => (int) $ff['id'], 'label' => $ff['label'],
                'options' => $plain($ff['options'])], $f['fieldfilters']),
            'sorts' => $plain($view['sorts']),
            'perpage' => search_view::PERPAGE,
        ];
    }

    /**
     * Teachers for these filters, one page.
     *
     * @param array $raw filters (search::normalise() keys)
     * @param int $viewerid 0 = visitor
     * @param int $page
     * @param int $perpage
     * @return array {total, page, perpage, teachers: [card]}
     */
    public static function search(array $raw, int $viewerid, int $page, int $perpage): array {
        $result = search::run(search::normalise($raw), $viewerid, $page, $perpage);
        $teachers = [];
        foreach ($result['teachers'] as $t) {
            $card = search::card($t);
            $teachers[] = [
                'id' => (int) $card['id'],
                'name' => $card['name'],
                'photo' => self::url($card['photo']),
                'headline' => $card['headline'],
                'subjects' => array_column($card['subjects'], 'name'),
                'lesson_flex' => (int) $card['price'],
                'rating' => $card['hasrating'] ? round((float) $t['rating'], 1) : null,
                'reviews' => (int) $card['reviews'],
                'lessons' => (int) $card['lessons'],
                'badges' => $card['badges'],
                'hasvideo' => (bool) $card['hasvideo'],
                'url' => $card['url'],
            ];
        }
        return ['total' => (int) $result['total'], 'page' => $page, 'perpage' => $perpage, 'teachers' => $teachers];
    }

    // ── Reviewer (local/nit_tutors:review) ─────────────────────────────────────

    /**
     * Applications for the reviewer, with the count of every status tab.
     *
     * @param string $status '' / 'all', a status, or 'updates'
     * @param string $q name or email
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function admin_list(string $status, string $q, int $page, int $perpage): array {
        global $PAGE;
        $counts = application::counts();
        $tabs = [];
        foreach (application::STATUSES as $st) {
            $tabs[] = ['status' => $st, 'label' => get_string('status_' . $st, 'local_nit_tutors'), 'count' => $counts[$st]];
        }
        $tabs[] = ['status' => 'updates', 'label' => get_string('tab_updates', 'local_nit_tutors'),
            'count' => application::updates_waiting()];
        $tabs[] = ['status' => 'all', 'label' => get_string('all'), 'count' => array_sum($counts)];

        $result = application::search($status === 'all' ? '' : $status, $q, $page, $perpage);
        $ids = array_map(fn($r) => (int) $r->userid, $result['rows']);
        $badges = search::badges_of($ids);
        $rows = [];
        foreach ($result['rows'] as $r) {
            $user = \user_picture::unalias($r, null, 'uid');
            $picture = new \user_picture($user);
            $picture->size = 100;
            $quals = qualifications::list((int) $r->userid);
            $rows[] = [
                'userid' => (int) $r->userid,
                'name' => fullname($user),
                'email' => (string) $user->email,
                'photo' => self::url($picture->get_url($PAGE)->out(false)),
                'status' => (string) $r->status,
                'statuslabel' => get_string('status_' . $r->status, 'local_nit_tutors'),
                'haspending' => application::has_updates((int) $r->userid),
                'timesubmitted' => (int) $r->timesubmitted,
                'videostatus' => (string) $r->videostatus,
                'qualifications' => count($quals),
                'verifiedqualifications' => count(array_filter($quals, fn($q) => $q->verified)),
                'badges' => badge::chips($badges[(int) $r->userid] ?? []),
            ];
        }
        return ['tabs' => $tabs, 'total' => (int) $result['total'], 'page' => $page, 'perpage' => $perpage, 'rows' => $rows];
    }

    /**
     * One teacher for the reviewer (review_view): the data — for an approved teacher's waiting
     * change, the new version with *new flags — and which decisions are offered.
     *
     * @param int $userid
     * @return array
     */
    public static function admin_get(int $userid): array {
        $data = review_view::context($userid, fn(string $u) => self::url($u));
        unset($data['profileurl'], $data['liststatus'], $data['canact']);
        return $data;
    }

    /**
     * Decide (review_view::decide) and answer the teacher as it is now.
     *
     * @param int $userid
     * @param string $action approve|reject
     * @param string $note
     * @param int $reviewerid
     * @return array
     */
    public static function admin_decide(int $userid, string $action, string $note, int $reviewerid): array {
        review_view::decide($userid, $action, $note, $reviewerid);
        return ['done' => true, 'message' => get_string('done', 'local_nit_tutors'), 'application' => self::admin_get($userid)];
    }
}

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
 * The teacher's own data — headline, bio, subjects, intro video, qualifications with their
 * certificates, the admin's extra fields — as posted by the two pages that collect it: the
 * step-by-step teacher sign-up (/local/academy/register.php?as=teacher) and the teacher profile
 * (/local/nit_tutors/application.php). One reading, one validation, one save for both.
 *
 * Posted names: headline, bio, subjects[], videotype (link|upload), videourl, videofile (file),
 * qualid[], qualtitle[], qualinstitution[], qualyear[], qualdelete[], qualfile[] (files),
 * field[<fieldid>], fieldfile[<fieldid>] (files).
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class teacher_input {

    /** Most qualification rows. */
    const MAXQUALS = 10;

    /** Largest certificate / file answer (bytes). */
    const MAXFILEBYTES = 10 * 1024 * 1024;

    /**
     * The posted values.
     *
     * @return array
     */
    public static function from_request(): array {
        return [
            'headline' => optional_param('headline', '', PARAM_TEXT),
            'bio' => optional_param('bio', '', PARAM_TEXT),
            'subjects' => optional_param_array('subjects', [], PARAM_TEXT),
            'videotype' => optional_param('videotype', '', PARAM_ALPHA),
            'videourl' => optional_param('videourl', '', PARAM_RAW_TRIMMED),
            'qualid' => optional_param_array('qualid', [], PARAM_INT),
            'qualtitle' => optional_param_array('qualtitle', [], PARAM_TEXT),
            'qualinstitution' => optional_param_array('qualinstitution', [], PARAM_TEXT),
            'qualyear' => optional_param_array('qualyear', [], PARAM_INT),
            'qualdelete' => optional_param_array('qualdelete', [], PARAM_BOOL),
            'field' => optional_param_array('field', [], PARAM_RAW_TRIMMED),
        ];
    }

    /**
     * The posted files: [videofile => entry|null, qualfile => [row => entry], fieldfile => [fieldid => entry]].
     * Only entries that carry a file.
     *
     * @return array
     */
    public static function files_from_request(): array {
        $one = function(array $f, $key = null): ?array {
            $get = fn($k) => $key === null ? ($f[$k] ?? null) : ($f[$k][$key] ?? null);
            if ((int) $get('error') !== UPLOAD_ERR_OK || empty($get('tmp_name'))) {
                return null;
            }
            return ['name' => (string) $get('name'), 'tmp_name' => (string) $get('tmp_name'), 'size' => (int) $get('size')];
        };
        $out = ['videofile' => isset($_FILES['videofile']) ? $one($_FILES['videofile']) : null, 'qualfile' => [],
            'fieldfile' => []];
        foreach (['qualfile', 'fieldfile'] as $name) {
            foreach (array_keys((array) ($_FILES[$name]['name'] ?? [])) as $key) {
                if ($entry = $one($_FILES[$name], $key)) {
                    $out[$name][(int) $key] = $entry;
                }
            }
        }
        return $out;
    }

    /**
     * Field errors: [name => message]. Names: headline, bio, subjects, video, qual<row>, field<id>.
     *
     * @param array $in from from_request()
     * @param array $files from files_from_request()
     * @param int $userid 0 = a new teacher (the sign-up)
     * @param bool $complete also require everything an application needs (sending it for review)
     * @return string[]
     */
    public static function validate(array $in, array $files, int $userid, bool $complete): array {
        $errors = [];
        $str = fn(string $k, $a = null) => get_string($k, 'local_nit_tutors', $a);
        if (\core_text::strlen((string) $in['headline']) > 255) {
            $errors['headline'] = $str('err_toolong', 255);
        }
        $options = teacher_service::subject_options($userid);
        foreach ((array) $in['subjects'] as $s) {
            if (!in_array((string) $s, $options, true)) {
                $errors['subjects'] = get_string('err_badsubject', 'local_nit_lessons');
            }
        }

        // Video.
        $hasvideo = false;
        if ($in['videotype'] === video::LINK) {
            if (video::embed_url((string) $in['videourl']) === null) {
                $errors['video'] = $str('err_badvideourl');
            }
            $hasvideo = true;
        } else if ($in['videotype'] === video::UPLOAD) {
            if (!empty($files['videofile'])) {
                $err = self::file_error($files['videofile'], video::ACCEPTED, video::max_bytes());
                if ($err) {
                    $errors['video'] = $err;
                }
                $hasvideo = true;
            } else {
                $hasvideo = $userid && (video::file($userid) !== null || pending::file($userid, pending::AREA_VIDEO, 0));
                if (!$hasvideo) {
                    $errors['video'] = $str('err_choosevideo');
                }
            }
        }

        // Qualifications.
        $mine = $userid ? array_map(fn($q) => (int) $q->id, qualifications::list($userid)) : [];
        $quals = 0;
        $thisyear = (int) date('Y');
        foreach (self::qual_rows($in) as $i => $q) {
            if ($q['id'] && !in_array($q['id'], $mine, true)) {
                continue; // Not theirs: ignored on save too.
            }
            if ($q['delete']) {
                continue;
            }
            $hasfile = !empty($files['qualfile'][$i]);
            if ($q['title'] === '' && $q['institution'] === '' && !$q['year'] && !$hasfile) {
                continue; // An empty row.
            }
            if ($q['title'] === '') {
                $errors['qual' . $i] = $str('err_qualtitle');
            } else if ($q['year'] && ($q['year'] < 1950 || $q['year'] > $thisyear + 1)) {
                $errors['qual' . $i] = $str('err_qualyear');
            } else if ($hasfile && ($err = self::file_error($files['qualfile'][$i], qualifications::ACCEPTED, self::MAXFILEBYTES))) {
                $errors['qual' . $i] = $err;
            }
            $quals++;
        }

        // The admin's fields.
        $answers = $userid ? fields::answers($userid) : [];
        foreach (fields::all() as $f) {
            $name = 'field' . $f->id;
            if ($f->datatype === 'file') {
                $new = $files['fieldfile'][(int) $f->id] ?? null;
                if ($new && ($err = self::file_error($new, qualifications::ACCEPTED, self::MAXFILEBYTES))) {
                    $errors[$name] = $err;
                } else if ($complete && $f->required && !$new && !($userid && (fields::has_file($userid, (int) $f->id)
                        || pending::file($userid, pending::AREA_FIELD, (int) $f->id)))) {
                    $errors[$name] = get_string('required');
                }
                continue;
            }
            $value = array_key_exists((int) $f->id, (array) $in['field']) ? $in['field'][(int) $f->id] : ($answers[$f->id] ?? '');
            try {
                $clean = fields::clean($f, self::field_value($f, $value));
                if ($complete && $f->required && $clean === '') {
                    $errors[$name] = get_string('required');
                }
            } catch (\moodle_exception $e) {
                $errors[$name] = $e->getMessage();
            }
        }

        if ($complete) {
            if (trim((string) $in['bio']) === '') {
                $errors['bio'] = $errors['bio'] ?? $str('missing_bio');
            }
            if (!array_filter((array) $in['subjects'])) {
                $errors['subjects'] = $errors['subjects'] ?? $str('missing_subjects');
            }
            if (application::setting_int('requirevideo', 1) && !$hasvideo) {
                $errors['video'] = $errors['video'] ?? $str('missing_video');
            }
            $min = application::setting_int('minqualifications', 1);
            if ($min > 0 && $quals < $min) {
                $errors['quals'] = $str('missing_qualifications', $min);
            }
        }
        return $errors;
    }

    /**
     * Save everything posted (call validate() first). An approved teacher's change waits for review
     * ({@see pending}); everyone else's goes live.
     *
     * @param int $userid
     * @param array $in
     * @param array $files
     * @return bool whether a change now waits for the academy's review
     */
    public static function save(int $userid, array $in, array $files): bool {
        if (pending::applies($userid)) {
            return pending::save($userid, $in, $files);
        }
        return self::save_live($userid, $in, $files);
    }

    /**
     * Write the values over the live data.
     *
     * @param int $userid
     * @param array $in
     * @param array $files uploads, or entries carrying a stored file (pending::files())
     * @param bool $notify tell the reviewers when an approved teacher changed the video or a qualification
     * @return bool whether an approved teacher changed something the academy reviews (video, qualifications)
     */
    public static function save_live(int $userid, array $in, array $files, bool $notify = true): bool {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        $context = \context_user::instance($userid);
        $row = application::ensure($userid);
        $wasapproved = $row->status === application::APPROVED;
        $reviewable = false;

        // About + subjects: the booking profile keeps its "available" flag and weekly hours.
        $service = new teacher_service();
        $profile = $service->profile($userid);
        $subjects = array_values(array_filter(array_map('strval', (array) $in['subjects'])));
        $service->save($userid, $profile['available'] && $subjects, (string) $in['headline'], $subjects, $profile['hours']);
        $bio = trim((string) $in['bio']);
        user_update_user((object) ['id' => $userid, 'description' => $bio === '' ? '' : nl2br(s($bio)),
            'descriptionformat' => FORMAT_HTML], false, false);

        // Video.
        $filechanged = false;
        if ($in['videotype'] === video::UPLOAD && !empty($files['videofile'])) {
            $filechanged = self::store($context->id, video::AREA, 0, $files['videofile']);
        }
        $before = (string) application::get($userid)->videostatus;
        video::save($userid, (string) $in['videotype'], (string) $in['videourl'], $filechanged);
        $reviewable = $reviewable || ($wasapproved && application::get($userid)->videostatus === 'pending'
            && $before !== 'pending');

        // Qualifications.
        $mine = array_map(fn($q) => (int) $q->id, qualifications::list($userid));
        $order = 0;
        foreach (self::qual_rows($in) as $i => $q) {
            $id = $q['id'];
            if ($id && !in_array($id, $mine, true)) {
                continue; // Not theirs: ignore a forged id.
            }
            $file = $files['qualfile'][$i] ?? null;
            if ($q['delete'] || ($q['title'] === '' && !$id)) {
                if ($id) {
                    qualifications::delete($userid, $id);
                }
                continue;
            }
            if ($q['title'] === '') {
                continue;
            }
            $old = $id ? $DB->get_record(qualifications::TABLE, ['id' => $id]) : null;
            $id = qualifications::save($userid, $id, $q['title'], $q['institution'], $q['year'], ++$order);
            if ($file && self::store($context->id, qualifications::AREA, $id, $file)) {
                qualifications::save($userid, $id, $q['title'], $q['institution'], $q['year'], $order, true);
            }
            $now = $DB->get_record(qualifications::TABLE, ['id' => $id]);
            $reviewable = $reviewable || ($wasapproved && !$now->verified && (!$old || $old->verified));
        }

        // The admin's fields.
        foreach (fields::all() as $f) {
            if ($f->datatype === 'file') {
                if (!empty($files['fieldfile'][(int) $f->id])) {
                    self::store($context->id, fields::AREA, (int) $f->id, $files['fieldfile'][(int) $f->id]);
                }
                fields::save_answer($userid, $f, fields::has_file($userid, (int) $f->id));
            } else if (array_key_exists((int) $f->id, (array) $in['field'])) {
                fields::save_answer($userid, $f, self::field_value($f, $in['field'][(int) $f->id]));
            }
        }
        $DB->set_field(application::TABLE, 'timemodified', time(), ['userid' => $userid]);
        if ($reviewable && $notify) {
            notifier::updates_waiting($userid);
        }
        return $reviewable;
    }

    /**
     * The qualification rows as posted.
     *
     * @param array $in
     * @return array row => {id, title, institution, year, delete}
     */
    private static function qual_rows(array $in): array {
        $out = [];
        foreach (array_slice((array) $in['qualtitle'], 0, self::MAXQUALS, true) as $i => $title) {
            $out[(int) $i] = [
                'id' => (int) ($in['qualid'][$i] ?? 0),
                'title' => \core_text::substr(trim((string) $title), 0, 255),
                'institution' => \core_text::substr(trim((string) ($in['qualinstitution'][$i] ?? '')), 0, 255),
                'year' => (int) ($in['qualyear'][$i] ?? 0),
                'delete' => !empty($in['qualdelete'][$i]),
            ];
        }
        return $out;
    }

    /**
     * A posted field value as fields::clean() expects it (a date arrives as YYYY-MM-DD).
     *
     * @param \stdClass $field
     * @param mixed $value
     * @return mixed
     */
    public static function field_value(\stdClass $field, $value) {
        if ($field->datatype === 'date' && is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return (new \DateTimeImmutable($value . ' 12:00', \core_date::get_server_timezone_object()))->getTimestamp();
        }
        return $value;
    }

    /**
     * Why an uploaded file is not accepted, or null.
     *
     * @param array $file {name, tmp_name, size}
     * @param string[] $extensions accepted, with the dot
     * @param int $maxbytes
     * @return string|null
     */
    public static function file_error(array $file, array $extensions, int $maxbytes): ?string {
        $ext = '.' . \core_text::strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $extensions, true)) {
            return get_string('err_filetype', 'local_nit_tutors', implode(' ', $extensions));
        }
        if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxbytes) {
            return get_string('err_filesize', 'local_nit_tutors', display_size($maxbytes));
        }
        return null;
    }

    /**
     * Put an uploaded file in a file area, replacing what was there.
     *
     * @param int $contextid
     * @param string $area
     * @param int $itemid
     * @param array $file {name, tmp_name, size}
     * @return bool whether it was stored
     */
    public static function store(int $contextid, string $area, int $itemid, array $file): bool {
        $fs = get_file_storage();
        if (isset($file['stored'])) {
            // A waiting file (pending::files()) copied into place.
            $fs->delete_area_files($contextid, 'local_nit_tutors', $area, $itemid);
            $fs->create_file_from_storedfile(['contextid' => $contextid, 'component' => 'local_nit_tutors',
                'filearea' => $area, 'itemid' => $itemid, 'filepath' => '/'], $file['stored']);
            return true;
        }
        $path = (string) $file['tmp_name'];
        // A real request only stores PHP's own uploads; tests pass a local file.
        if (!is_uploaded_file($path) && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST && is_readable($path))) {
            return false;
        }
        $fs->delete_area_files($contextid, 'local_nit_tutors', $area, $itemid);
        $fs->create_file_from_pathname(['contextid' => $contextid, 'component' => 'local_nit_tutors', 'filearea' => $area,
            'itemid' => $itemid, 'filepath' => '/', 'filename' => clean_param((string) $file['name'], PARAM_FILE) ?: 'file'], $path);
        return true;
    }
}

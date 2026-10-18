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
 * Template context of the teacher's data inputs (teacher_input's names): the teacher profile page
 * (template profile_page) and the step-by-step teacher sign-up (template signup_steps).
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profile_view {

    /**
     * The inputs' values and errors.
     *
     * @param int $userid 0 = a new teacher (the sign-up)
     * @param array|null $in posted values to show again (after a refused save), else the saved ones
     * @param string[] $errors from teacher_input::validate()
     * @return array
     */
    public static function inputs(int $userid, ?array $in = null, array $errors = []): array {
        $service = new teacher_service();
        $profile = $userid ? $service->profile($userid) : ['headline' => '', 'subjects' => []];
        $user = $userid ? \core_user::get_user($userid) : null;
        $row = $userid ? application::get($userid) : null;
        $str = fn(string $k, $a = null) => get_string($k, 'local_nit_tutors', $a);

        $headline = $in['headline'] ?? $profile['headline'];
        $bio = $in['bio'] ?? ($user ? trim(html_to_text((string) $user->description, 0, false)) : '');
        $chosen = $in['subjects'] ?? $profile['subjects'];
        $subjects = [];
        foreach (teacher_service::subject_options($userid) as $i => $s) {
            $subjects[] = ['value' => $s, 'label' => format_string($s), 'checked' => in_array($s, (array) $chosen, true),
                'idx' => $i];
        }

        // Video.
        $videotype = $in['videotype'] ?? ($row ? (string) $row->videotype : '');
        $videotype = $videotype ?: video::LINK;
        $player = $userid ? video::player($userid, false) : null;
        $videofile = $userid ? video::file($userid) : null;

        // Qualifications: the saved ones (or the posted rows), at least one row.
        $quals = [];
        if ($in !== null) {
            foreach ((array) $in['qualtitle'] as $i => $title) {
                $quals[] = ['row' => (int) $i, 'id' => (int) ($in['qualid'][$i] ?? 0), 'title' => (string) $title,
                    'institution' => (string) ($in['qualinstitution'][$i] ?? ''), 'year' => (int) ($in['qualyear'][$i] ?? 0) ?: '',
                    'error' => $errors['qual' . $i] ?? ''];
            }
        } else if ($userid) {
            foreach (qualifications::list($userid) as $i => $q) {
                $quals[] = ['row' => $i, 'id' => (int) $q->id, 'title' => $q->title, 'institution' => (string) $q->institution,
                    'year' => (int) $q->year ?: '', 'verified' => (bool) $q->verified, 'error' => ''];
            }
        }
        foreach ($quals as &$q) {
            $file = ($userid && $q['id']) ? qualifications::file($userid, $q['id']) : null;
            $q['filename'] = $file ? $file->get_filename() : '';
            $q['fileurl'] = $file ? \moodle_url::make_pluginfile_url($file->get_contextid(), 'local_nit_tutors',
                qualifications::AREA, $q['id'], '/', $file->get_filename(), false)->out(false) : '';
        }
        unset($q);
        if (!$quals) {
            $quals[] = ['row' => 0, 'id' => 0, 'title' => '', 'institution' => '', 'year' => '', 'error' => '',
                'filename' => '', 'fileurl' => ''];
        }

        // The admin's fields.
        $answers = $userid ? fields::answers($userid) : [];
        $extra = [];
        foreach (fields::all() as $f) {
            $value = array_key_exists((int) $f->id, (array) ($in['field'] ?? [])) ? (string) $in['field'][(int) $f->id]
                : (string) ($answers[$f->id] ?? '');
            $item = ['id' => (int) $f->id, 'label' => format_string($f->name), 'required' => (bool) $f->required,
                'description' => format_string((string) $f->description), 'value' => $value,
                'error' => $errors['field' . $f->id] ?? '', 'is' . $f->datatype => true];
            if ($f->datatype === 'select') {
                $item['options'] = array_map(fn($o) => ['value' => $o, 'label' => format_string($o), 'selected' => $o === $value],
                    fields::options($f));
            } else if ($f->datatype === 'date') {
                $item['value'] = ctype_digit($value) && (int) $value ? date('Y-m-d', (int) $value) : $value;
            } else if ($f->datatype === 'checkbox') {
                $item['checked'] = !empty($value);
            } else if ($f->datatype === 'file' && $userid) {
                $files = get_file_storage()->get_area_files(\context_user::instance($userid)->id, 'local_nit_tutors',
                    fields::AREA, $f->id, 'filename', false);
                if ($file = reset($files)) {
                    $item['filename'] = $file->get_filename();
                    $item['fileurl'] = \moodle_url::make_pluginfile_url($file->get_contextid(), 'local_nit_tutors', fields::AREA,
                        $f->id, '/', $file->get_filename(), false)->out(false);
                }
            }
            $extra[] = $item;
        }

        return [
            'headline' => $headline,
            'bio' => $bio,
            'subjects' => $subjects,
            'hassubjects' => !empty($subjects),
            'islink' => $videotype === video::LINK,
            'isupload' => $videotype === video::UPLOAD,
            'videourl' => $in['videourl'] ?? ($row ? (string) $row->videourl : ''),
            'videorequired' => (bool) application::setting_int('requirevideo', 1),
            'hasplayer' => $player !== null,
            'player' => $player,
            'videofilename' => $videofile ? $videofile->get_filename() : '',
            'videostatus' => $row && $row->videostatus ? $str('videostatus_' . $row->videostatus)
                . ($row->videostatus === 'rejected' && $row->videonote ? ' — ' . $row->videonote : '') : '',
            'videoaccept' => implode(',', video::ACCEPTED),
            'videomax' => display_size(video::max_bytes()),
            'quals' => $quals,
            'nextqual' => max(array_column($quals, 'row')) + 1,
            'maxquals' => teacher_input::MAXQUALS,
            'minquals' => application::setting_int('minqualifications', 1),
            'fileaccept' => implode(',', qualifications::ACCEPTED),
            'filemax' => display_size(teacher_input::MAXFILEBYTES),
            'hasextra' => !empty($extra),
            'extra' => $extra,
            'err' => array_map('strval', $errors),
        ];
    }
}

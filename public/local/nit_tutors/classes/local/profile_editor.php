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
use local_nit_tutors\form\application_form;

/**
 * Loads the application form from the teacher's data and saves it back: headline, bio and
 * subjects (shared with the live-lesson card of the profile page), intro video, qualifications
 * and the admin's fields.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profile_editor {

    /**
     * The form's starting values (file areas copied to drafts).
     *
     * @param int $userid
     * @return \stdClass
     */
    public static function load(int $userid): \stdClass {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $context = \context_user::instance($userid);
        $profile = (new teacher_service())->profile($userid);
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $row = application::get($userid);

        $data = (object) [
            'headline' => $profile['headline'],
            'bio' => trim(html_to_text((string) $user->description, 0, false)),
            'subjects' => $profile['subjects'],
            'videotype' => $row ? (string) $row->videotype : '',
            'videourl' => $row ? (string) $row->videourl : '',
        ];
        $draft = 0;
        file_prepare_draft_area($draft, $context->id, 'local_nit_tutors', video::AREA, 0, application_form::video_options());
        $data->videofile = $draft;

        foreach (qualifications::list($userid) as $i => $q) {
            $data->qualid[$i] = (int) $q->id;
            $data->qualtitle[$i] = $q->title;
            $data->qualinstitution[$i] = (string) $q->institution;
            $data->qualyear[$i] = (int) $q->year ?: '';
            $data->qualstate[$i] = get_string($q->verified ? 'qual_verified' : 'qual_unverified', 'local_nit_tutors');
            $draft = 0;
            file_prepare_draft_area($draft, $context->id, 'local_nit_tutors', qualifications::AREA, (int) $q->id,
                application_form::file_options());
            $data->qualfile[$i] = $draft;
        }

        $answers = fields::answers($userid);
        foreach (fields::all() as $f) {
            $name = 'field_' . $f->id;
            if ($f->datatype === 'file') {
                $draft = 0;
                file_prepare_draft_area($draft, $context->id, 'local_nit_tutors', fields::AREA, (int) $f->id,
                    application_form::file_options());
                $data->$name = $draft;
            } else {
                $data->$name = (string) ($answers[$f->id] ?? '');
            }
        }
        return $data;
    }

    /**
     * Save the submitted form.
     *
     * @param int $userid
     * @param \stdClass $data from get_data()
     * @throws \moodle_exception from the services (bad subject, bad link…)
     */
    public static function save(int $userid, \stdClass $data): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->libdir . '/filelib.php');
        $context = \context_user::instance($userid);
        application::ensure($userid);

        // About + subjects: the booking profile keeps its "available" flag and weekly hours.
        $service = new teacher_service();
        $profile = $service->profile($userid);
        $subjects = array_values(array_filter(array_map('strval', (array) ($data->subjects ?? []))));
        $service->save($userid, $profile['available'] && $subjects, (string) ($data->headline ?? ''), $subjects,
            $profile['hours']);

        $bio = trim((string) ($data->bio ?? ''));
        user_update_user((object) ['id' => $userid, 'description' => $bio === '' ? '' : nl2br(s($bio)),
            'descriptionformat' => FORMAT_HTML], false, false);

        // Video.
        $before = self::hashes($context->id, video::AREA, 0);
        if (($data->videotype ?? '') === video::UPLOAD && isset($data->videofile)) {
            file_save_draft_area_files($data->videofile, $context->id, 'local_nit_tutors', video::AREA, 0,
                application_form::video_options());
        }
        video::save($userid, (string) ($data->videotype ?? ''), (string) ($data->videourl ?? ''),
            $before !== self::hashes($context->id, video::AREA, 0));

        // Qualifications.
        $mine = array_map('intval', $DB->get_fieldset_select(qualifications::TABLE, 'id', 'userid = ?', [$userid]));
        $order = 0;
        foreach ((array) ($data->qualtitle ?? []) as $i => $title) {
            $id = (int) ($data->qualid[$i] ?? 0);
            if ($id && !in_array($id, $mine, true)) {
                continue; // Not theirs: ignore a forged id.
            }
            if (!empty($data->qualdelete[$i]) || (trim((string) $title) === '' && !$id)) {
                if ($id) {
                    qualifications::delete($userid, $id);
                }
                continue;
            }
            if (trim((string) $title) === '') {
                continue;
            }
            $before = $id ? self::hashes($context->id, qualifications::AREA, $id) : [];
            $id = qualifications::save($userid, $id, (string) $title, (string) ($data->qualinstitution[$i] ?? ''),
                (int) ($data->qualyear[$i] ?? 0), ++$order);
            if (!empty($data->qualfile[$i])) {
                file_save_draft_area_files($data->qualfile[$i], $context->id, 'local_nit_tutors', qualifications::AREA, $id,
                    application_form::file_options());
                if ($before !== self::hashes($context->id, qualifications::AREA, $id)) {
                    qualifications::save($userid, $id, (string) $title, (string) ($data->qualinstitution[$i] ?? ''),
                        (int) ($data->qualyear[$i] ?? 0), $order, true);
                }
            }
        }

        // The admin's fields.
        foreach (fields::all() as $f) {
            $name = 'field_' . $f->id;
            if (!property_exists($data, $name)) {
                continue;
            }
            if ($f->datatype === 'file') {
                file_save_draft_area_files($data->$name, $context->id, 'local_nit_tutors', fields::AREA, (int) $f->id,
                    application_form::file_options());
                fields::save_answer($userid, $f, fields::has_file($userid, (int) $f->id));
            } else {
                fields::save_answer($userid, $f, $data->$name);
            }
        }
        $DB->set_field(application::TABLE, 'timemodified', time(), ['userid' => $userid]);
    }

    /**
     * Content hashes of a file area (to tell whether a save replaced the file).
     *
     * @param int $contextid
     * @param string $area
     * @param int $itemid
     * @return string[]
     */
    private static function hashes(int $contextid, string $area, int $itemid): array {
        $out = [];
        foreach (get_file_storage()->get_area_files($contextid, 'local_nit_tutors', $area, $itemid, 'filename', false) as $f) {
            $out[] = $f->get_contenthash() . ':' . $f->get_filename();
        }
        sort($out);
        return $out;
    }
}

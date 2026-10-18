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
 * An approved teacher's changes waiting for review.
 *
 * While a teacher is approved (and the academy keeps "Approval required" on), what they change on
 * their teacher profile — headline, subjects, about, intro video, qualifications, the admin's
 * fields — does not go live. It is kept here: the posted values (teacher_input names) as JSON in
 * nit_tutor.pending, the uploaded files in the pending* file areas. The public page, the teacher
 * search and the booking pages keep showing the approved version. Approving copies the change
 * over the live data through teacher_input (the one save path); rejecting throws it away.
 *
 * Only what the public sees waits. Taking bookings, the price and the weekly hours are the
 * teacher's day-to-day settings and save at once.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pending {

    /** The new intro video (itemid 0). */
    const AREA_VIDEO = 'pendingvideo';

    /** A qualification's new certificate (itemid = the row of the posted form). */
    const AREA_QUAL = 'pendingqual';

    /** A file answer to one of the admin's fields (itemid = field id). */
    const AREA_FIELD = 'pendingfield';

    /** The pending file areas. */
    const AREAS = [self::AREA_VIDEO, self::AREA_QUAL, self::AREA_FIELD];

    /**
     * Do this teacher's changes wait for review?
     *
     * @param int $userid
     * @return bool
     */
    public static function applies(int $userid): bool {
        return application::is_approved($userid) && application::approval_required();
    }

    /**
     * The waiting change (teacher_input values), or null.
     *
     * @param int $userid
     * @return array|null
     */
    public static function get(int $userid): ?array {
        $row = application::get($userid);
        if (!$row || $row->pending === null || $row->pending === '') {
            return null;
        }
        $in = json_decode((string) $row->pending, true);
        return is_array($in) ? $in + self::blank() : null;
    }

    /**
     * Keep a change for review (call teacher_input::validate() first). Saving the approved
     * version again (no change) withdraws a waiting change instead.
     *
     * @param int $userid
     * @param array $in teacher_input values
     * @param array $files teacher_input files
     * @return bool whether a change now waits
     */
    public static function save(int $userid, array $in, array $files): bool {
        global $DB;
        $in = array_intersect_key($in, self::blank()) + self::blank();
        $context = \context_user::instance($userid);
        $fs = get_file_storage();
        $before = self::get($userid);

        // Files: a new upload replaces the waiting one; a waiting file stays while its row does.
        if ($in['videotype'] !== video::UPLOAD) {
            $fs->delete_area_files($context->id, 'local_nit_tutors', self::AREA_VIDEO);
        } else if (!empty($files['videofile'])) {
            teacher_input::store($context->id, self::AREA_VIDEO, 0, $files['videofile']);
        }
        $rows = [];
        foreach ((array) $in['qualtitle'] as $i => $title) {
            if (empty($in['qualdelete'][$i]) && trim((string) $title) !== '') {
                $rows[(int) $i] = true;
            }
        }
        foreach ($fs->get_area_files($context->id, 'local_nit_tutors', self::AREA_QUAL, false, 'itemid', false) as $f) {
            if (!isset($rows[(int) $f->get_itemid()])) {
                $f->delete();
            }
        }
        foreach ((array) ($files['qualfile'] ?? []) as $i => $file) {
            if (isset($rows[(int) $i])) {
                teacher_input::store($context->id, self::AREA_QUAL, (int) $i, $file);
            }
        }
        foreach ((array) ($files['fieldfile'] ?? []) as $fieldid => $file) {
            teacher_input::store($context->id, self::AREA_FIELD, (int) $fieldid, $file);
        }

        if (!self::differs($userid, $in)) {
            self::discard($userid);
            return false;
        }
        $DB->set_field(application::TABLE, 'pending', json_encode($in, JSON_UNESCAPED_UNICODE), ['userid' => $userid]);
        $DB->set_field(application::TABLE, 'timepending', time(), ['userid' => $userid]);
        $DB->set_field(application::TABLE, 'timemodified', time(), ['userid' => $userid]);
        if ($before === null) {
            notifier::updates_waiting($userid);
        }
        return true;
    }

    /**
     * Put the waiting change live (the reviewer approved it): the video and every qualification
     * count as reviewed.
     *
     * @param int $userid
     */
    public static function apply(int $userid): void {
        global $DB;
        $in = self::get($userid);
        if ($in === null) {
            return;
        }
        teacher_input::save_live($userid, $in, self::files($userid), false);
        $row = application::get($userid);
        if ($row->videostatus === 'pending') {
            $DB->update_record(application::TABLE, (object) ['id' => $row->id, 'videostatus' => 'approved', 'videonote' => '']);
        }
        $DB->set_field(qualifications::TABLE, 'verified', 1, ['userid' => $userid]);
        self::discard($userid);
    }

    /**
     * Throw the waiting change away.
     *
     * @param int $userid
     */
    public static function discard(int $userid): void {
        global $DB;
        $fs = get_file_storage();
        $contextid = \context_user::instance($userid)->id;
        foreach (self::AREAS as $area) {
            $fs->delete_area_files($contextid, 'local_nit_tutors', $area);
        }
        if ($row = application::get($userid)) {
            $DB->update_record(application::TABLE, (object) ['id' => $row->id, 'pending' => null, 'timepending' => 0]);
        }
    }

    /**
     * A waiting file.
     *
     * @param int $userid
     * @param string $area one of AREAS
     * @param int $itemid
     * @return \stored_file|null
     */
    public static function file(int $userid, string $area, int $itemid): ?\stored_file {
        $files = get_file_storage()->get_area_files(\context_user::instance($userid)->id, 'local_nit_tutors', $area,
            $itemid, 'filename', false);
        return reset($files) ?: null;
    }

    /**
     * The waiting files in teacher_input's files shape (entries carry the stored file).
     *
     * @param int $userid
     * @return array
     */
    public static function files(int $userid): array {
        $out = ['videofile' => null, 'qualfile' => [], 'fieldfile' => []];
        $contextid = \context_user::instance($userid)->id;
        $fs = get_file_storage();
        $entry = fn(\stored_file $f) => ['name' => $f->get_filename(), 'size' => (int) $f->get_filesize(), 'stored' => $f];
        if ($f = self::file($userid, self::AREA_VIDEO, 0)) {
            $out['videofile'] = $entry($f);
        }
        foreach ([self::AREA_QUAL => 'qualfile', self::AREA_FIELD => 'fieldfile'] as $area => $key) {
            foreach ($fs->get_area_files($contextid, 'local_nit_tutors', $area, false, 'itemid', false) as $f) {
                $out[$key][(int) $f->get_itemid()] = $entry($f);
            }
        }
        return $out;
    }

    /**
     * Does this change differ from what is live (including a waiting file)?
     *
     * @param int $userid
     * @param array $in
     * @return bool
     */
    public static function differs(int $userid, array $in): bool {
        $contextid = \context_user::instance($userid)->id;
        $fs = get_file_storage();
        foreach (self::AREAS as $area) {
            if (!$fs->is_area_empty($contextid, 'local_nit_tutors', $area)) {
                return true;
            }
        }
        $flat = fn($s) => trim(preg_replace('/\s+/u', ' ', (string) $s));
        $profile = (new teacher_service())->profile($userid);
        if ($flat($in['headline']) !== $flat($profile['headline'])) {
            return true;
        }
        $subjects = array_values(array_unique(array_filter(array_map('strval', (array) $in['subjects']))));
        $live = array_values(array_map('strval', $profile['subjects']));
        sort($subjects);
        sort($live);
        if ($subjects !== $live) {
            return true;
        }
        $user = \core_user::get_user($userid);
        if ($flat($in['bio']) !== $flat(html_to_text((string) $user->description, 0, false))) {
            return true;
        }
        $row = application::get($userid);
        if ((string) $in['videotype'] !== (string) $row->videotype
                || ($in['videotype'] === video::LINK && trim((string) $in['videourl']) !== (string) $row->videourl)) {
            return true;
        }
        $quals = [];
        foreach (qualifications::list($userid) as $q) {
            $quals[(int) $q->id] = $q;
        }
        $seen = [];
        foreach ((array) $in['qualtitle'] as $i => $title) {
            $id = (int) ($in['qualid'][$i] ?? 0);
            $title = $flat($title);
            if (!$id) {
                if ($title !== '') {
                    return true; // A new qualification.
                }
                continue;
            }
            if (!isset($quals[$id])) {
                continue; // Not theirs: ignored on save.
            }
            $seen[$id] = true;
            $q = $quals[$id];
            if (!empty($in['qualdelete'][$i]) || $title === '' || $title !== $flat($q->title)
                    || $flat($in['qualinstitution'][$i] ?? '') !== $flat($q->institution)
                    || (int) ($in['qualyear'][$i] ?? 0) !== (int) $q->year) {
                return true;
            }
        }
        $answers = fields::answers($userid);
        foreach (fields::all() as $f) {
            if ($f->datatype === 'file' || !array_key_exists((int) $f->id, (array) $in['field'])) {
                continue;
            }
            try {
                $value = (string) fields::clean($f, teacher_input::field_value($f, $in['field'][(int) $f->id]));
            } catch (\moodle_exception $e) {
                return true;
            }
            if ($value !== (string) ($answers[$f->id] ?? '')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Every teacher_input value, empty.
     *
     * @return array
     */
    private static function blank(): array {
        return ['headline' => '', 'bio' => '', 'subjects' => [], 'videotype' => '', 'videourl' => '', 'qualid' => [],
            'qualtitle' => [], 'qualinstitution' => [], 'qualyear' => [], 'qualdelete' => [], 'field' => []];
    }
}

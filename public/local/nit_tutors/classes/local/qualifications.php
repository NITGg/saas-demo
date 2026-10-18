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

/**
 * A teacher's qualifications (degree, institution, year) with a certificate file each. The
 * certificate is private (the teacher and the reviewers); visitors see the title, institution,
 * year and whether an admin verified it.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class qualifications {

    /** Table. */
    const TABLE = 'nit_tutor_qual';

    /** File area (itemid = qualification id). */
    const AREA = 'qualification';

    /** Accepted certificate types. */
    const ACCEPTED = ['.pdf', '.jpg', '.jpeg', '.png', '.webp'];

    /**
     * The teacher's qualifications in order.
     *
     * @param int $userid
     * @return \stdClass[]
     */
    public static function list(int $userid): array {
        global $DB;
        return array_values($DB->get_records(self::TABLE, ['userid' => $userid], 'sortorder, id'));
    }

    /**
     * How many qualifications the teacher entered.
     *
     * @param int $userid
     * @return int
     */
    public static function count(int $userid): int {
        global $DB;
        return $DB->count_records(self::TABLE, ['userid' => $userid]);
    }

    /**
     * Save one qualification. Changing the text of a verified one clears "verified".
     *
     * @param int $userid
     * @param int $id 0 = new
     * @param string $title
     * @param string $institution
     * @param int $year 0 = not given
     * @param int $sortorder
     * @param bool $filechanged the certificate was replaced
     * @return int the id
     * @throws \moodle_exception when $id belongs to someone else or the title is empty
     */
    public static function save(int $userid, int $id, string $title, string $institution, int $year, int $sortorder,
            bool $filechanged = false): int {
        global $DB;
        $title = \core_text::substr(trim($title), 0, 255);
        if ($title === '') {
            throw new \moodle_exception('err_qualtitle', 'local_nit_tutors');
        }
        $thisyear = (int) date('Y');
        $year = ($year >= 1950 && $year <= $thisyear + 1) ? $year : 0;
        $record = (object) ['userid' => $userid, 'title' => $title,
            'institution' => \core_text::substr(trim($institution), 0, 255), 'year' => $year,
            'sortorder' => $sortorder, 'timemodified' => time()];
        if ($id) {
            $old = $DB->get_record(self::TABLE, ['id' => $id, 'userid' => $userid], '*', MUST_EXIST);
            $record->id = $id;
            $same = $old->title === $record->title && (string) $old->institution === $record->institution
                && (int) $old->year === $year && !$filechanged;
            $record->verified = $same ? (int) $old->verified : 0;
            $DB->update_record(self::TABLE, $record);
            return $id;
        }
        $record->verified = 0;
        $record->timecreated = time();
        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Delete one qualification and its certificate.
     *
     * @param int $userid
     * @param int $id
     */
    public static function delete(int $userid, int $id): void {
        global $DB;
        if ($DB->record_exists(self::TABLE, ['id' => $id, 'userid' => $userid])) {
            get_file_storage()->delete_area_files(\context_user::instance($userid)->id, 'local_nit_tutors', self::AREA, $id);
            $DB->delete_records(self::TABLE, ['id' => $id]);
        }
    }

    /**
     * Mark a qualification verified (or not) — reviewers only (checked by the caller).
     *
     * @param int $id
     * @param bool $verified
     */
    public static function verify(int $id, bool $verified): void {
        global $DB;
        $DB->set_field(self::TABLE, 'verified', $verified ? 1 : 0, ['id' => $id]);
        $DB->set_field(self::TABLE, 'timemodified', time(), ['id' => $id]);
    }

    /**
     * The certificate file of a qualification, if any.
     *
     * @param int $userid
     * @param int $id
     * @return \stored_file|null
     */
    public static function file(int $userid, int $id): ?\stored_file {
        $files = get_file_storage()->get_area_files(\context_user::instance($userid)->id, 'local_nit_tutors',
            self::AREA, $id, 'filename', false);
        return $files ? reset($files) : null;
    }

    /**
     * The qualifications as the public teacher page shows them.
     *
     * @param int $userid
     * @return array [{title, institution, year, verified}]
     */
    public static function public_list(int $userid): array {
        $out = [];
        foreach (self::list($userid) as $q) {
            $out[] = ['title' => format_string($q->title), 'institution' => format_string((string) $q->institution),
                'year' => (int) $q->year ?: '', 'verified' => (bool) $q->verified];
        }
        return $out;
    }
}

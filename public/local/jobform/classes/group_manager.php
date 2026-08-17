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

namespace local_jobform;

/**
 * CRUD for the global default groups (table local_jobform_group).
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_manager {

    /** @var string The template groups table. */
    const TABLE = 'local_jobform_group';

    /**
     * All groups in display order, keyed by id.
     *
     * @return array
     */
    public static function get_groups(): array {
        global $DB;
        return $DB->get_records(self::TABLE, null, 'sortorder ASC, id ASC');
    }

    /**
     * A single group or false.
     *
     * @param int $id
     * @return object|false
     */
    public static function get_group(int $id) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]);
    }

    /**
     * A select menu of groups: 0 => "no group", then id => resolved name.
     *
     * @return array
     */
    public static function menu(): array {
        $menu = [0 => get_string('nogroup', 'local_jobform')];
        foreach (self::get_groups() as $group) {
            $menu[$group->id] = mlang::resolve($group->name);
        }
        return $menu;
    }

    /**
     * Create or update a group from validated form data (expects ->groupid, ->name).
     *
     * @param object $data
     * @return int the group id
     */
    public static function save_group(object $data): int {
        global $DB, $USER;

        $now = time();
        $record = new \stdClass();
        $record->name = trim($data->name);
        $record->usermodified = $USER->id;
        $record->timemodified = $now;

        if (!empty($data->groupid)) {
            $record->id = $data->groupid;
            $DB->update_record(self::TABLE, $record);
            return (int) $record->id;
        }

        $record->sortorder = self::next_sortorder();
        $record->timecreated = $now;
        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Delete a group and un-assign any template fields that referenced it.
     *
     * @param int $id
     * @return void
     */
    public static function delete_group(int $id): void {
        global $DB;
        $DB->set_field('local_jobform_field', 'groupid', 0, ['groupid' => $id]);
        $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * The next sortorder value to append a group at the end.
     *
     * @return int
     */
    protected static function next_sortorder(): int {
        global $DB;
        $max = $DB->get_field_sql('SELECT MAX(sortorder) FROM {' . self::TABLE . '}');
        return (int) $max + 1;
    }
}

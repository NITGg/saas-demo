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

namespace mod_jobform;

use local_jobform\mlang;

/**
 * Manages one Job Form activity's own groups (table jobform_group).
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_manager {

    /** @var string This activity's groups table. */
    const TABLE = 'jobform_group';

    /**
     * All groups for an activity, in display order, keyed by id.
     *
     * @param int $jobformid
     * @return array
     */
    public static function get_groups(int $jobformid): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['jobformid' => $jobformid], 'sortorder ASC, id ASC');
    }

    /**
     * A single group scoped to its activity, or false.
     *
     * @param int $id
     * @param int $jobformid
     * @return object|false
     */
    public static function get_group(int $id, int $jobformid) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id, 'jobformid' => $jobformid]);
    }

    /**
     * A select menu for the field form: 0 => "no group", then id => resolved name.
     *
     * @param int $jobformid
     * @return array
     */
    public static function menu(int $jobformid): array {
        $menu = [0 => get_string('nogroup', 'local_jobform')];
        foreach (self::get_groups($jobformid) as $group) {
            $menu[$group->id] = mlang::resolve($group->name);
        }
        return $menu;
    }

    /**
     * Create or update a group for this activity (expects ->groupid, ->name).
     *
     * @param int $jobformid
     * @param object $data
     * @return int the group id
     */
    public static function save_group(int $jobformid, object $data): int {
        global $DB, $USER;

        $now = time();
        $record = new \stdClass();
        $record->jobformid = $jobformid;
        $record->name = trim($data->name);
        $record->usermodified = $USER->id;
        $record->timemodified = $now;

        if (!empty($data->groupid)) {
            $existing = self::get_group((int) $data->groupid, $jobformid);
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record(self::TABLE, $record);
                return (int) $record->id;
            }
        }

        $record->sortorder = self::next_sortorder($jobformid);
        $record->timecreated = $now;
        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Delete a group and un-assign this activity's fields that referenced it.
     *
     * @param int $id
     * @param int $jobformid
     * @return void
     */
    public static function delete_group(int $id, int $jobformid): void {
        global $DB;
        if (!self::get_group($id, $jobformid)) {
            return;
        }
        $DB->set_field('jobform_field', 'groupid', 0, ['groupid' => $id, 'jobformid' => $jobformid]);
        $DB->delete_records(self::TABLE, ['id' => $id, 'jobformid' => $jobformid]);
    }

    /**
     * The next sortorder value for appending a group.
     *
     * @param int $jobformid
     * @return int
     */
    protected static function next_sortorder(int $jobformid): int {
        global $DB;
        $max = $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {' . self::TABLE . '} WHERE jobformid = ?', [$jobformid]);
        return (int) $max + 1;
    }
}

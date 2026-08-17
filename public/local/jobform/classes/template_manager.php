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
 * CRUD for the global default field template (table local_jobform_field).
 *
 * A new Job Form activity copies this set on creation; editing an activity's
 * fields never writes back here.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_manager {

    /** @var string The template table. */
    const TABLE = 'local_jobform_field';

    /**
     * All template fields in display order.
     *
     * @return array records keyed by id
     */
    public static function get_fields(): array {
        global $DB;
        return $DB->get_records(self::TABLE, null, 'sortorder ASC, id ASC');
    }

    /**
     * A single field or false.
     *
     * @param int $id
     * @return object|false
     */
    public static function get_field(int $id) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]);
    }

    /**
     * Create or update a template field from validated form data.
     *
     * @param object $data expects ->id, ->name, ->type, ->required and the
     *                      type-specific ->options / ->multiple / ->fixedvalue
     * @return int the field id
     */
    public static function save_field(object $data): int {
        global $DB, $USER;

        $now = time();
        $record = new \stdClass();
        $record->name = trim($data->name);
        $record->groupid = (int) ($data->groupid ?? 0);
        $record->type = field_types::is_valid($data->type) ? $data->type : field_types::TYPE_TEXT;
        $record->configdata = field_types::encode_config(
            $record->type,
            $data->options ?? '',
            !empty($data->multiple),
            $data->fixedvalue ?? ''
        );
        $record->required = !empty($data->required) ? 1 : 0;
        $record->usermodified = $USER->id;
        $record->timemodified = $now;

        if (!empty($data->fieldid)) {
            $record->id = $data->fieldid;
            $DB->update_record(self::TABLE, $record);
            return (int) $record->id;
        }

        $record->sortorder = self::next_sortorder();
        $record->timecreated = $now;
        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Delete a template field.
     *
     * @param int $id
     * @return void
     */
    public static function delete_field(int $id): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * Move a field one step up or down and swap sort order with its neighbour.
     *
     * @param int $id
     * @param int $direction -1 to move up, +1 to move down
     * @return void
     */
    public static function reorder(int $id, int $direction): void {
        global $DB;
        $fields = array_values(self::get_fields());
        $index = null;
        foreach ($fields as $i => $f) {
            if ((int) $f->id === $id) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return;
        }
        $swap = $index + ($direction < 0 ? -1 : 1);
        if ($swap < 0 || $swap >= count($fields)) {
            return;
        }
        $a = $fields[$index];
        $b = $fields[$swap];
        // Swap their sortorder values (normalise first in case of ties).
        $tmp = (int) $a->sortorder;
        $DB->set_field(self::TABLE, 'sortorder', (int) $b->sortorder, ['id' => $a->id]);
        $DB->set_field(self::TABLE, 'sortorder', $tmp, ['id' => $b->id]);
    }

    /**
     * The next sortorder value to append a field at the end.
     *
     * @return int
     */
    protected static function next_sortorder(): int {
        global $DB;
        $max = $DB->get_field_sql('SELECT MAX(sortorder) FROM {' . self::TABLE . '}');
        return (int) $max + 1;
    }
}

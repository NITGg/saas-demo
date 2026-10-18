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
 * The application fields the admin defines (Site administration → Users → Teacher applications →
 * Application fields) and the teachers' answers to them.
 *
 * Fixed parts of every application are not fields here: bio, subjects, intro video and
 * qualifications. A field can be shown on the public teacher page ("public") and, for a select
 * field, be a filter on the teacher search page ("filter") — e.g. "Languages spoken", "Country".
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fields {

    /** Tables. */
    const TABLE = 'nit_tutor_field';
    const DATA = 'nit_tutor_data';

    /** File area for file answers (itemid = field id). */
    const AREA = 'field';

    /** Field types. */
    const TYPES = ['text', 'textarea', 'select', 'number', 'url', 'date', 'checkbox', 'file'];

    /**
     * Field definitions in order.
     *
     * @param bool $activeonly
     * @return \stdClass[] id => field
     */
    public static function all(bool $activeonly = true): array {
        global $DB;
        return $DB->get_records(self::TABLE, $activeonly ? ['active' => 1] : [], 'sortorder, id');
    }

    /**
     * One definition.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * The options of a select field.
     *
     * @param \stdClass $field
     * @return string[] stored values
     */
    public static function options(\stdClass $field): array {
        $out = [];
        foreach (preg_split('/\R/u', (string) $field->options) as $line) {
            $line = trim($line);
            if ($line !== '' && !in_array($line, $out, true)) {
                $out[] = $line;
            }
        }
        return $out;
    }

    /**
     * Create or update a definition.
     *
     * @param \stdClass $data shortname, name, description, datatype, options, required, public, filter, active
     * @return int id
     * @throws \moodle_exception for a bad type or a duplicate shortname
     */
    public static function save(\stdClass $data): int {
        global $DB;
        $id = (int) ($data->id ?? 0);
        $shortname = clean_param(trim((string) $data->shortname), PARAM_ALPHANUMEXT);
        if ($shortname === '') {
            throw new \moodle_exception('err_shortname', 'local_nit_tutors');
        }
        if ($DB->record_exists_select(self::TABLE, 'shortname = ? AND id <> ?', [$shortname, $id])) {
            throw new \moodle_exception('err_shortnametaken', 'local_nit_tutors');
        }
        $type = (string) $data->datatype;
        if (!in_array($type, self::TYPES, true)) {
            throw new \moodle_exception('err_badtype', 'local_nit_tutors');
        }
        $record = (object) [
            'shortname' => $shortname,
            'name' => trim((string) $data->name),
            'description' => trim((string) ($data->description ?? '')),
            'datatype' => $type,
            'options' => $type === 'select' ? trim((string) ($data->options ?? '')) : '',
            'required' => empty($data->required) ? 0 : 1,
            'public' => empty($data->public) || $type === 'file' ? 0 : 1,
            'filter' => !empty($data->filter) && $type === 'select' ? 1 : 0,
            'active' => !isset($data->active) || !empty($data->active) ? 1 : 0,
            'timemodified' => time(),
        ];
        if ($record->name === '') {
            throw new \moodle_exception('err_fieldname', 'local_nit_tutors');
        }
        if ($id) {
            $record->id = $id;
            $DB->update_record(self::TABLE, $record);
            return $id;
        }
        $record->sortorder = (int) $DB->get_field_sql("SELECT COALESCE(MAX(sortorder), 0) + 1 FROM {" . self::TABLE . "}");
        $record->timecreated = time();
        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Delete a definition with every answer and file.
     *
     * @param int $id
     */
    public static function delete(int $id): void {
        global $DB;
        $fs = get_file_storage();
        foreach ($DB->get_fieldset_select(self::DATA, 'userid', 'fieldid = ?', [$id]) as $userid) {
            $fs->delete_area_files(\context_user::instance((int) $userid)->id, 'local_nit_tutors', self::AREA, $id);
        }
        $DB->delete_records(self::DATA, ['fieldid' => $id]);
        $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * Move a field one place up or down.
     *
     * @param int $id
     * @param int $direction -1 up, 1 down
     */
    public static function move(int $id, int $direction): void {
        global $DB;
        $list = array_values(self::all(false));
        foreach ($list as $i => $f) {
            if ((int) $f->id === $id && isset($list[$i + $direction])) {
                [$list[$i], $list[$i + $direction]] = [$list[$i + $direction], $list[$i]];
                break;
            }
        }
        foreach ($list as $i => $f) {
            $DB->set_field(self::TABLE, 'sortorder', $i + 1, ['id' => $f->id]);
        }
    }

    /**
     * The teacher's answers.
     *
     * @param int $userid
     * @return string[] fieldid => value
     */
    public static function answers(int $userid): array {
        global $DB;
        return $DB->get_records_menu(self::DATA, ['userid' => $userid], '', 'fieldid, value');
    }

    /**
     * Store one answer (validated against the field).
     *
     * @param int $userid
     * @param \stdClass $field
     * @param mixed $value
     * @throws \moodle_exception badanswer for a value the field does not accept
     */
    public static function save_answer(int $userid, \stdClass $field, $value): void {
        global $DB;
        $value = self::clean($field, $value);
        $existing = $DB->get_record(self::DATA, ['userid' => $userid, 'fieldid' => $field->id]);
        if ($existing) {
            $existing->value = $value;
            $existing->timemodified = time();
            $DB->update_record(self::DATA, $existing);
        } else {
            $DB->insert_record(self::DATA, (object) ['userid' => $userid, 'fieldid' => $field->id, 'value' => $value,
                'timemodified' => time()]);
        }
    }

    /**
     * A value as stored for this field type.
     *
     * @param \stdClass $field
     * @param mixed $value
     * @return string
     * @throws \moodle_exception badanswer
     */
    public static function clean(\stdClass $field, $value): string {
        $bad = new \moodle_exception('err_badanswer', 'local_nit_tutors', '', format_string($field->name));
        switch ($field->datatype) {
            case 'select':
                $value = trim((string) $value);
                if ($value !== '' && !in_array($value, self::options($field), true)) {
                    throw $bad;
                }
                return $value;
            case 'number':
                $value = trim((string) $value);
                if ($value !== '' && !is_numeric($value)) {
                    throw $bad;
                }
                return $value;
            case 'url':
                $value = trim((string) $value);
                if ($value !== '' && clean_param($value, PARAM_URL) === '') {
                    throw $bad;
                }
                return $value;
            case 'date':
            case 'checkbox':
                return (string) (int) $value ?: '';
            case 'file':
                return $value ? '1' : '';
            case 'textarea':
                return trim(clean_param((string) $value, PARAM_TEXT));
            default:
                return \core_text::substr(trim(clean_param((string) $value, PARAM_TEXT)), 0, 1333);
        }
    }

    /**
     * Does the field have a file answer?
     *
     * @param int $userid
     * @param int $fieldid
     * @return bool
     */
    public static function has_file(int $userid, int $fieldid): bool {
        return !get_file_storage()->is_area_empty(\context_user::instance($userid)->id, 'local_nit_tutors', self::AREA,
            $fieldid, false);
    }

    /**
     * Required fields the teacher has not answered: [fieldid => label].
     *
     * @param int $userid
     * @return string[]
     */
    public static function missing_required(int $userid): array {
        $answers = self::answers($userid);
        $out = [];
        foreach (self::all() as $f) {
            if (!$f->required) {
                continue;
            }
            $done = $f->datatype === 'file' ? self::has_file($userid, (int) $f->id)
                : trim((string) ($answers[$f->id] ?? '')) !== '';
            if (!$done) {
                $out[(int) $f->id] = format_string($f->name);
            }
        }
        return $out;
    }

    /**
     * An answer as text for display.
     *
     * @param \stdClass $field
     * @param string $value
     * @return string
     */
    public static function display(\stdClass $field, string $value): string {
        if ($value === '') {
            return '';
        }
        switch ($field->datatype) {
            case 'checkbox':
                return get_string('yes');
            case 'date':
                return userdate((int) $value, get_string('strftimedate', 'langconfig'));
            case 'select':
            case 'text':
                return format_string($value);
            default:
                return s($value);
        }
    }

    /**
     * Public answers for the teacher page: [{label, value}].
     *
     * @param int $userid
     * @return array
     */
    public static function public_answers(int $userid): array {
        $answers = self::answers($userid);
        $out = [];
        foreach (self::all() as $f) {
            if ($f->public && ($shown = self::display($f, (string) ($answers[$f->id] ?? ''))) !== '') {
                $out[] = ['label' => format_string($f->name), 'value' => $shown];
            }
        }
        return $out;
    }
}

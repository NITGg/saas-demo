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
 * Central definition of the Job Form field types.
 *
 * This is the single source of truth shared by the admin template manager
 * (local_jobform) and the activity module (mod_jobform): the list of types,
 * how each one is rendered on the student form, and how a submitted value is
 * formatted for display.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_types {

    /** @var string Free single-line text. */
    const TYPE_TEXT = 'text';
    /** @var string Numeric input. */
    const TYPE_NUMBER = 'number';
    /** @var string Email address. */
    const TYPE_EMAIL = 'email';
    /** @var string Phone number. */
    const TYPE_PHONE = 'phone';
    /** @var string Date. */
    const TYPE_DATE = 'date';
    /** @var string Single checkbox (yes/no). */
    const TYPE_CHECKBOX = 'checkbox';
    /** @var string URL / link. */
    const TYPE_URL = 'url';
    /** @var string Dropdown with admin-defined options (single or multiple select). */
    const TYPE_SELECT = 'select';
    /** @var string Fixed value set by the admin; read-only for the student. */
    const TYPE_FIXED = 'fixed';

    /**
     * All supported types mapped to their language string key.
     *
     * @return string[] type => lang string identifier (in local_jobform)
     */
    public static function all(): array {
        return [
            self::TYPE_TEXT     => 'fieldtype_text',
            self::TYPE_NUMBER   => 'fieldtype_number',
            self::TYPE_EMAIL    => 'fieldtype_email',
            self::TYPE_PHONE    => 'fieldtype_phone',
            self::TYPE_DATE     => 'fieldtype_date',
            self::TYPE_CHECKBOX => 'fieldtype_checkbox',
            self::TYPE_URL      => 'fieldtype_url',
            self::TYPE_SELECT   => 'fieldtype_select',
            self::TYPE_FIXED    => 'fieldtype_fixed',
        ];
    }

    /**
     * A menu of type => localised label, for select boxes.
     *
     * @return string[]
     */
    public static function menu(): array {
        $menu = [];
        foreach (self::all() as $type => $stringkey) {
            $menu[$type] = get_string($stringkey, 'local_jobform');
        }
        return $menu;
    }

    /**
     * Whether the given type is one we know about.
     *
     * @param string $type
     * @return bool
     */
    public static function is_valid(string $type): bool {
        return array_key_exists($type, self::all());
    }

    /**
     * Whether the type carries a list of options (dropdown).
     *
     * @param string $type
     * @return bool
     */
    public static function has_options(string $type): bool {
        return $type === self::TYPE_SELECT;
    }

    /**
     * Whether the type stores an admin-defined fixed value the student cannot change.
     *
     * @param string $type
     * @return bool
     */
    public static function is_fixed(string $type): bool {
        return $type === self::TYPE_FIXED;
    }

    /**
     * Decode a field's configdata JSON into a predictable array.
     *
     * @param string|null $configdata raw JSON from the DB
     * @return array{options: string[], multiple: bool, fixedvalue: string}
     */
    public static function decode_config(?string $configdata): array {
        $decoded = json_decode((string) $configdata, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $options = [];
        if (!empty($decoded['options']) && is_array($decoded['options'])) {
            foreach ($decoded['options'] as $opt) {
                $opt = trim((string) $opt);
                if ($opt !== '') {
                    $options[] = $opt;
                }
            }
        }
        return [
            'options'    => $options,
            'multiple'   => !empty($decoded['multiple']),
            'fixedvalue' => isset($decoded['fixedvalue']) ? (string) $decoded['fixedvalue'] : '',
        ];
    }

    /**
     * Build the configdata JSON string from raw inputs, keeping only what the type needs.
     *
     * @param string $type
     * @param string $optionstext newline-separated option list (dropdown only)
     * @param bool $multiple allow multi-select (dropdown only)
     * @param string $fixedvalue the fixed value (fixed type only)
     * @return string JSON
     */
    public static function encode_config(string $type, string $optionstext, bool $multiple, string $fixedvalue): string {
        $config = [];
        if (self::has_options($type)) {
            $options = [];
            foreach (preg_split('/\r\n|\r|\n/', $optionstext) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $options[] = $line;
                }
            }
            $config['options'] = array_values(array_unique($options));
            $config['multiple'] = $multiple ? 1 : 0;
        } else if (self::is_fixed($type)) {
            $config['fixedvalue'] = $fixedvalue;
        }
        return json_encode($config);
    }

    /**
     * Format a stored submission value for read-only display (admin view / confirmation).
     *
     * @param object $field a field record (needs ->type, ->configdata)
     * @param string|null $value the stored value
     * @return string plain text, safe to pass through format_string() by the caller
     */
    public static function format_value(object $field, ?string $value): string {
        $value = (string) $value;
        switch ($field->type) {
            case self::TYPE_CHECKBOX:
                return $value ? get_string('yes') : get_string('no');
            case self::TYPE_DATE:
                return $value !== '' ? userdate((int) $value, get_string('strftimedate', 'langconfig')) : '';
            case self::TYPE_SELECT:
                // Stored as a JSON array for multi-select, plain string otherwise.
                // Each stored value may be a {mlang} option, so resolve for display.
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    return implode(', ', array_map([mlang::class, 'resolve'], $decoded));
                }
                return mlang::resolve($value);
            case self::TYPE_FIXED:
                $config = self::decode_config($field->configdata);
                return mlang::resolve($config['fixedvalue']);
            default:
                return $value;
        }
    }
}

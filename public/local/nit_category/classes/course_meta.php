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

namespace local_nit_category;

/**
 * Course catalogue metadata — the "Level" course custom field.
 *
 * Level is a REAL Moodle course custom field with shortname "level" (a select of
 * Beginner / Intermediate / Advanced). Created turnkey by
 * cli/setup_level_field.php (never at install time, so it can't break an upgrade).
 * Everything here READS that field and degrades gracefully to nothing when the
 * field or a value is absent — so the catalogue never shows a Level control it
 * can't back with real data.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_meta {

    /** @var string The course custom-field shortname that stores the level. */
    const LEVEL_SHORTNAME = 'level';

    /** @var string[]|null Cached level options. */
    private static $leveloptions = null;

    /** @var array<int,?string> Per-request cache of course id => level label. */
    private static $levelcache = [];

    /**
     * The course-level select options (the field's own options, in order), or []
     * when the field does not exist.
     *
     * @return string[]
     */
    public static function level_options(): array {
        if (self::$leveloptions !== null) {
            return self::$leveloptions;
        }
        self::$leveloptions = [];
        try {
            $handler = \core_course\customfield\course_handler::create();
            foreach ($handler->get_fields() as $field) {
                if ($field->get('shortname') === self::LEVEL_SHORTNAME) {
                    $config = $field->get('configdata');
                    $raw = is_array($config) ? ($config['options'] ?? '') : '';
                    foreach (preg_split('/[\r\n]+/', (string) $raw) as $opt) {
                        $opt = trim($opt);
                        if ($opt !== '') {
                            self::$leveloptions[] = $opt;
                        }
                    }
                    break;
                }
            }
        } catch (\Throwable $e) {
            self::$leveloptions = [];
        }
        return self::$leveloptions;
    }

    /**
     * Whether the Level field exists (i.e. the catalogue may show the control).
     *
     * @return bool
     */
    public static function level_enabled(): bool {
        return !empty(self::level_options());
    }

    /**
     * The level label for a course, or null when unset / field missing.
     *
     * @param int $courseid
     * @return string|null
     */
    public static function get_level(int $courseid): ?string {
        if (array_key_exists($courseid, self::$levelcache)) {
            return self::$levelcache[$courseid];
        }
        $value = null;
        try {
            $handler = \core_course\customfield\course_handler::create();
            foreach ($handler->get_instance_data($courseid, true) as $data) {
                if ($data->get_field()->get('shortname') === self::LEVEL_SHORTNAME) {
                    if ($data->get_value() !== null && $data->get_value() !== '' && $data->get_value() !== 0) {
                        $export = $data->export_value();
                        $value = ($export !== null && $export !== '') ? (string) $export : null;
                    }
                    break;
                }
            }
        } catch (\Throwable $e) {
            $value = null;
        }
        self::$levelcache[$courseid] = $value;
        return $value;
    }
}

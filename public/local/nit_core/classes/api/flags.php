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

namespace local_nit_core\api;

use local_nit_core\flag\flag;
use local_nit_core\flag\registry;

/**
 * Public feature-flag read facade.
 *
 * A thin, typed seam over Moodle config: a flag's value is its config setting,
 * or the declared default when unset.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
final class flags {
    /**
     * Whether a feature flag is enabled.
     *
     * @param string $key flag key
     * @return bool config value, or the declared default; false for unknown keys
     */
    public static function enabled(string $key): bool {
        $flag = registry::get($key);
        if ($flag === null) {
            debugging("Unknown NIT feature flag: {$key}", DEBUG_DEVELOPER);
            return false;
        }
        return config::get_bool('flag_' . $key, $flag->default());
    }

    /**
     * All declared flags, keyed by key.
     *
     * @return array<string,flag>
     */
    public static function all(): array {
        return registry::all();
    }

    /**
     * A single flag definition, or null if undeclared.
     *
     * @param string $key flag key
     * @return flag|null
     */
    public static function get(string $key): ?flag {
        return registry::get($key);
    }

    /**
     * Why a flag is in its current state, for clean UX messaging.
     *
     * @param string $key flag key
     * @return string one of: default, enabled, disabled, unknown
     */
    public static function reason(string $key): string {
        if (registry::get($key) === null) {
            return 'unknown';
        }
        $raw = get_config('local_nit_core', 'flag_' . $key);
        if ($raw === false) {
            return 'default';
        }
        return ((bool) (int) $raw) ? 'enabled' : 'disabled';
    }
}

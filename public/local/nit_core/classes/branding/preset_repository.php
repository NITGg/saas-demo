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

namespace local_nit_core\branding;

/**
 * The six built-in branding presets (data).
 *
 * Adding a preset is a data change here — never a code change elsewhere.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class preset_repository {
    /** @var string The default preset key. */
    public const DEFAULT_PRESET = 'education';

    /**
     * All presets keyed by identifier.
     *
     * @return array<string,preset>
     */
    public static function all(): array {
        return [
            'corporate'  => new preset('corporate',  '#1f3a93', 'sans',    'tight'),
            'education'  => new preset('education',  '#2a50c8', 'sans',    'comfortable'),
            'medical'    => new preset('medical',    '#0e7c86', 'sans',    'spacious'),
            'government' => new preset('government', '#0f3d5c', 'sans',    'comfortable'),
            'kids'       => new preset('kids',       '#6d28d9', 'rounded', 'large'),
            'university' => new preset('university', '#7a1f3d', 'serif',   'comfortable'),
        ];
    }

    /**
     * Fetch a preset by key, falling back to the default.
     *
     * @param string $key preset key
     * @return preset
     */
    public static function get(string $key): preset {
        $all = self::all();
        return $all[$key] ?? $all[self::DEFAULT_PRESET];
    }

    /**
     * All preset keys.
     *
     * @return string[]
     */
    public static function keys(): array {
        return array_keys(self::all());
    }
}

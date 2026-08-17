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

namespace local_nit_core\flag;

/**
 * Feature-flag registry: discovers all providers and aggregates their flags.
 *
 * Discovery is per-request (cheap: an in-memory classmap scan plus a handful of
 * provider instantiations), memoised in a static cache. Values are NOT stored
 * here — they live in Moodle config; this only holds the definitions.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @internal
 */
final class registry {
    /** @var array<string,flag>|null Memoised flag definitions. */
    private static ?array $flags = null;

    /**
     * All declared flags, keyed by flag key.
     *
     * @return array<string,flag>
     */
    public static function all(): array {
        if (self::$flags === null) {
            self::$flags = self::discover();
        }
        return self::$flags;
    }

    /**
     * A single flag by key, or null if undeclared.
     *
     * @param string $key flag key
     * @return flag|null
     */
    public static function get(string $key): ?flag {
        return self::all()[$key] ?? null;
    }

    /**
     * Flags grouped by domain.
     *
     * @return array<string,flag[]>
     */
    public static function by_domain(): array {
        $grouped = [];
        foreach (self::all() as $flag) {
            $grouped[$flag->domain()][] = $flag;
        }
        return $grouped;
    }

    /**
     * Clear the memoised definitions (test seam).
     *
     * @return void
     */
    public static function reset(): void {
        self::$flags = null;
    }

    /**
     * Discover every provider across NIT plugins and aggregate their flags.
     *
     * @return array<string,flag>
     */
    private static function discover(): array {
        $flags = [];
        $classes = \core_component::get_component_classes_in_namespace(null, 'flag');
        foreach (array_keys($classes) as $classname) {
            if (!in_array(provider::class, class_implements($classname), true)) {
                continue;
            }
            /** @var provider $instance */
            $instance = new $classname();
            foreach ($instance->get_flags() as $flag) {
                if (isset($flags[$flag->key()])) {
                    debugging("Duplicate NIT feature flag key: {$flag->key()}", DEBUG_DEVELOPER);
                    continue;
                }
                $flags[$flag->key()] = $flag;
            }
        }
        return $flags;
    }
}

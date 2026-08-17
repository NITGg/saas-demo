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

use local_nit_core\contract\config_manager;

/**
 * Ergonomic public facade for typed configuration access.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
final class config {
    /**
     * Resolve the config manager service.
     *
     * @return config_manager
     */
    public static function manager(): config_manager {
        /** @var config_manager $service */
        $service = services::get(config_manager::class);
        return $service;
    }

    /**
     * Read a string setting for local_nit_core.
     *
     * @param string $key setting name
     * @param string|null $default value returned when unset
     * @return string|null
     */
    public static function get_string(string $key, ?string $default = null): ?string {
        return self::manager()->get_string($key, $default);
    }

    /**
     * Read a boolean setting for local_nit_core.
     *
     * @param string $key setting name
     * @param bool $default value returned when unset
     * @return bool
     */
    public static function get_bool(string $key, bool $default = false): bool {
        return self::manager()->get_bool($key, $default);
    }

    /**
     * Read an integer setting for local_nit_core.
     *
     * @param string $key setting name
     * @param int|null $default value returned when unset
     * @return int|null
     */
    public static function get_int(string $key, ?int $default = null): ?int {
        return self::manager()->get_int($key, $default);
    }

    /**
     * Return a config reader scoped to another component.
     *
     * @param string $component frankenstyle component name
     * @return config_manager
     */
    public static function for_plugin(string $component): config_manager {
        return self::manager()->for_plugin($component);
    }
}

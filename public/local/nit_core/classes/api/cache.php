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

use local_nit_core\contract\cache_manager;

/**
 * Ergonomic public facade for the NIT cache manager.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
final class cache {
    /**
     * Resolve the cache manager service.
     *
     * @return cache_manager
     */
    public static function manager(): cache_manager {
        /** @var cache_manager $service */
        $service = services::get(cache_manager::class);
        return $service;
    }

    /**
     * Fetch a cached value (null on miss).
     *
     * @param string $area logical area
     * @param string $key item key
     * @return mixed
     */
    public static function get(string $area, string $key): mixed {
        return self::manager()->get($area, $key);
    }

    /**
     * Store a value.
     *
     * @param string $area logical area
     * @param string $key item key
     * @param mixed $value value to store
     * @return bool
     */
    public static function set(string $area, string $key, mixed $value): bool {
        return self::manager()->set($area, $key, $value);
    }

    /**
     * Delete a value.
     *
     * @param string $area logical area
     * @param string $key item key
     * @return bool
     */
    public static function delete(string $area, string $key): bool {
        return self::manager()->delete($area, $key);
    }

    /**
     * Purge a whole area.
     *
     * @param string $area logical area
     * @return bool
     */
    public static function purge_area(string $area): bool {
        return self::manager()->purge_area($area);
    }
}

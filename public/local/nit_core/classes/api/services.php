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

use local_nit_core\service_manager;

/**
 * Public entry point to the NIT service container.
 *
 * Stable @api facade over the internal service_manager. Plugins resolve SDK
 * services by their interface name, e.g.
 *   services::get(\local_nit_core\contract\config_manager::class).
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
final class services {
    /**
     * Resolve a service by its interface name.
     *
     * @param string $id service interface name
     * @return object
     */
    public static function get(string $id): object {
        return service_manager::get($id);
    }

    /**
     * Whether a service is resolvable.
     *
     * @param string $id service interface name
     * @return bool
     */
    public static function has(string $id): bool {
        return service_manager::has($id);
    }

    /**
     * Override a service with a test double (unit-test seam).
     *
     * @param string $id service interface name
     * @param object $service replacement instance
     * @return void
     */
    public static function override(string $id, object $service): void {
        service_manager::override($id, $service);
    }
}

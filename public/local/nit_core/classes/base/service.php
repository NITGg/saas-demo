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

namespace local_nit_core\base;

use local_nit_core\api\services;

/**
 * Base class for NIT domain services.
 *
 * Gives services a single convenience accessor for resolving their
 * dependencies through the NIT container, keeping construction uniform.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
abstract class service {
    /**
     * Resolve a collaborating SDK service by its interface name.
     *
     * @param string $id service interface name
     * @return object
     */
    protected function dependency(string $id): object {
        return services::get($id);
    }
}

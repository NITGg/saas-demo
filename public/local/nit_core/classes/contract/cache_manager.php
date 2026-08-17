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

namespace local_nit_core\contract;

/**
 * Named cache facade over Moodle MUC.
 *
 * Provides namespaced get/set/delete against a single application cache
 * definition, with a consistent key convention across NIT plugins.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface cache_manager {
    /**
     * Fetch a cached value.
     *
     * @param string $area logical area used to namespace the key
     * @param string $key item key
     * @return mixed the value, or null on a miss
     */
    public function get(string $area, string $key): mixed;

    /**
     * Store a value.
     *
     * @param string $area logical area used to namespace the key
     * @param string $key item key
     * @param mixed $value value to store
     * @return bool true on success
     */
    public function set(string $area, string $key, mixed $value): bool;

    /**
     * Delete a value.
     *
     * @param string $area logical area used to namespace the key
     * @param string $key item key
     * @return bool true on success
     */
    public function delete(string $area, string $key): bool;

    /**
     * Purge every key belonging to an area.
     *
     * @param string $area logical area
     * @return bool true on success
     */
    public function purge_area(string $area): bool;
}

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

namespace local_nit_flex\api;

use local_nit_flex\service\package_service;

/**
 * Public facade for the Flex package catalog.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
final class packages {
    /**
     * Active packages a student can buy (US-PK-1-1).
     *
     * @return array
     */
    public static function available(): array {
        return (new package_service())->available();
    }

    /**
     * List packages for admin, optionally filtered by status.
     *
     * @param string $status
     * @return array
     */
    public static function all(string $status = ''): array {
        return (new package_service())->all($status);
    }

    /**
     * Create a package (US-AD-1-1).
     *
     * @param object $data
     * @return int
     */
    public static function create(object $data): int {
        return (new package_service())->create($data);
    }

    /**
     * Update a package (US-AD-1-2).
     *
     * @param int $id
     * @param object $data
     * @return void
     */
    public static function update(int $id, object $data): void {
        (new package_service())->update($id, $data);
    }

    /**
     * Activate/deactivate a package (US-AD-1-3).
     *
     * @param int $id
     * @param string $status
     * @return void
     */
    public static function set_status(int $id, string $status): void {
        (new package_service())->set_status($id, $status);
    }

    /**
     * Delete an unused package (US-AD-1-4).
     *
     * @param int $id
     * @return void
     */
    public static function delete_unused(int $id): void {
        (new package_service())->delete_unused($id);
    }
}

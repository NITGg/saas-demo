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

namespace local_nit_flex\service;

use local_nit_core\base\service;
use local_nit_flex\entity\package;
use local_nit_flex\entity\package_purchase;
use local_nit_flex\exception\flex_exception;

/**
 * Admin catalog CRUD for Flex packages (US-AD-1-1..1-4). Prices are integer minor units.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_service extends service {
    /**
     * List packages, optionally filtered by status.
     *
     * @param string $status '' for all, otherwise a package status
     * @return array
     */
    public function all(string $status = ''): array {
        $filters = $status !== '' ? ['status' => $status] : [];
        $rows = package::get_records($filters, 'timecreated', 'DESC');
        return array_map([self::class, 'format'], array_values($rows));
    }

    /**
     * Active packages a student can buy (US-PK-1-1).
     *
     * @return array
     */
    public function available(): array {
        return $this->all(package::STATUS_ACTIVE);
    }

    /**
     * Create a package (US-AD-1-1).
     *
     * @param object $data name, description, flex_count, price_minor, expiration_days, status
     * @return int new package id
     */
    public function create(object $data): int {
        $this->validate($data);
        $package = new package(0, (object) [
            'name'            => trim((string) $data->name),
            'description'     => $data->description ?? null,
            'flex_count'      => (int) $data->flex_count,
            'price_minor'     => (int) $data->price_minor,
            'expiration_days' => (int) ($data->expiration_days ?? 0),
            'status'          => $data->status ?? package::STATUS_ACTIVE,
        ]);
        $package->create();
        return (int) $package->get('id');
    }

    /**
     * Update a package (US-AD-1-2).
     *
     * @param int $id
     * @param object $data
     * @return void
     */
    public function update(int $id, object $data): void {
        $this->validate($data);
        $package = package::get_record(['id' => $id]);
        if (!$package) {
            throw new flex_exception('err_notfound');
        }
        $package->set('name', trim((string) $data->name));
        $package->set('description', $data->description ?? null);
        $package->set('flex_count', (int) $data->flex_count);
        $package->set('price_minor', (int) $data->price_minor);
        $package->set('expiration_days', (int) ($data->expiration_days ?? 0));
        if (isset($data->status)) {
            $package->set('status', $data->status);
        }
        $package->update();
    }

    /**
     * Activate or deactivate a package (US-AD-1-3).
     *
     * @param int $id
     * @param string $status
     * @return void
     */
    public function set_status(int $id, string $status): void {
        $package = package::get_record(['id' => $id]);
        if (!$package) {
            throw new flex_exception('err_notfound');
        }
        $package->set('status', $status === package::STATUS_ACTIVE
            ? package::STATUS_ACTIVE : package::STATUS_INACTIVE);
        $package->update();
    }

    /**
     * Delete a package that has never been purchased (US-AD-1-4).
     *
     * @param int $id
     * @return void
     */
    public function delete_unused(int $id): void {
        $package = package::get_record(['id' => $id]);
        if (!$package) {
            throw new flex_exception('err_notfound');
        }
        if (package_purchase::count_records(['packageid' => $id]) > 0) {
            throw new flex_exception('err_packageinuse');
        }
        $package->delete();
    }

    /**
     * Validate submitted package data.
     *
     * @param object $data
     * @return void
     */
    private function validate(object $data): void {
        if (trim((string) ($data->name ?? '')) === '' || (int) ($data->flex_count ?? 0) <= 0) {
            throw new flex_exception('err_nameflexrequired');
        }
    }

    /**
     * Shape a package entity as an array.
     *
     * @param package $p
     * @return array
     */
    private static function format(package $p): array {
        return [
            'id'              => (int) $p->get('id'),
            'name'            => $p->get('name'),
            'description'     => $p->get('description'),
            'flex_count'      => (int) $p->get('flex_count'),
            'price_minor'     => (int) $p->get('price_minor'),
            'expiration_days' => (int) $p->get('expiration_days'),
            'status'          => $p->get('status'),
            'timecreated'     => (int) $p->get('timecreated'),
        ];
    }
}

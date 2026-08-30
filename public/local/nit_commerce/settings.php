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

/**
 * Admin navigation for local_nit_commerce (coupons + offers).
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Only surface a menu item when the current licence includes the feature. When
// local_license is absent or enforcement is off, has_feature() returns true.
$nitcommercefeat = static function (string $f): bool {
    return !class_exists('\\local_license\\license') || \local_license\license::has_feature($f);
};

if ($hassiteconfig && $nitcommercefeat('coupons')) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_nit_commerce_managecoupons',
        get_string('managecoupons', 'local_nit_commerce'),
        new moodle_url('/local/nit_commerce/manage_coupons.php'),
        'local/nit_commerce:managecoupons'
    ));
}
if ($hassiteconfig && $nitcommercefeat('offers')) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_nit_commerce_manageoffers',
        get_string('manageoffers', 'local_nit_commerce'),
        new moodle_url('/local/nit_commerce/manage_offers.php'),
        'local/nit_commerce:manageoffers'
    ));
}

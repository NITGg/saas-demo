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
 * Web-service (mobile) functions for local_nit_commerce.
 *
 * Token-based twins of the browser ?function= endpoints in api.php, callable from the mobile app via
 * /webservice/rest/server.php. Registered against the official mobile service.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_nit_commerce_get_available_coupons' => [
        'classname'   => 'local_nit_commerce\external\get_available_coupons',
        'methodname'  => 'execute',
        'description' => 'List active, in-window discount coupons a student can browse.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_nit_commerce_preview_discount' => [
        'classname'   => 'local_nit_commerce\external\preview_discount',
        'methodname'  => 'execute',
        'description' => 'Preview the discounted price of an item (best offer + optional coupon), without charging.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];

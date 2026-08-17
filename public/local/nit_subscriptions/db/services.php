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
 * Web-service (mobile) functions for local_nit_subscriptions.
 *
 * Token-based twins of the browser ?function= endpoints in api.php, callable from the mobile app via
 * /webservice/rest/server.php. Registered against the official mobile service.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_nit_subscriptions_get_available_subscriptions' => [
        'classname'   => 'local_nit_subscriptions\external\get_available_subscriptions',
        'methodname'  => 'execute',
        'description' => 'List active subscription plans a student can buy, with the courses each unlocks.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_nit_subscriptions_get_my_subscriptions' => [
        'classname'   => 'local_nit_subscriptions\external\get_my_subscriptions',
        'methodname'  => 'execute',
        'description' => 'The current user\'s subscription purchases, active first.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_nit_subscriptions_get_subscription_payment_history' => [
        'classname'   => 'local_nit_subscriptions\external\get_subscription_payment_history',
        'methodname'  => 'execute',
        'description' => 'The current user\'s subscription payments (gateway transactions), newest first.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_nit_subscriptions_create_subscription_checkout' => [
        'classname'   => 'local_nit_subscriptions\external\create_subscription_checkout',
        'methodname'  => 'execute',
        'description' => 'Start a payment-gateway checkout for a subscription plan; returns the checkout URL.',
        'type'        => 'write',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];

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
 * Admin navigation for local_nit_subscriptions.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Only surface the menu when the current licence includes subscriptions. When
// local_license is absent or enforcement is off, has_feature() returns true.
$nitsubsfeat = !class_exists('\\local_license\\license') || \local_license\license::has_feature('subscriptions');

if ($hassiteconfig && $nitsubsfeat) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_nit_subscriptions_managesubscriptions',
        get_string('managesubscriptions', 'local_nit_subscriptions'),
        new moodle_url('/local/nit_subscriptions/manage_subscriptions.php'),
        'local/nit_subscriptions:managesubscriptions'
    ));

    // Single-course purchases: who bought which course, with "Unbuy" (revoke + unenrol).
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_nit_subscriptions_managecourses',
        get_string('managecourses', 'local_nit_subscriptions'),
        new moodle_url('/local/nit_subscriptions/manage_courses.php'),
        'local/nit_subscriptions:managesubscriptions'
    ));
}

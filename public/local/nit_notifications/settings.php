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
 * Admin tree: Site administration → Plugins → Local plugins → Notifications, with
 * "Send a notification" and "Notification log" — beside the other academy pages,
 * apart from Moodle's own messaging settings. A manager scoped to a category or a
 * course opens the same pages from the course's menu (lib.php).
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig || has_capability('local/nit_notifications:send', context_system::instance())) {
    $ADMIN->add('localplugins', new admin_category('local_nit_notifications_cat',
        get_string('notifications', 'local_nit_notifications')));
    $ADMIN->add('local_nit_notifications_cat', new admin_externalpage(
        'local_nit_notifications_send',
        get_string('sendnotification', 'local_nit_notifications'),
        new moodle_url('/local/nit_notifications/send.php'),
        'local/nit_notifications:send'
    ));
    $ADMIN->add('local_nit_notifications_cat', new admin_externalpage(
        'local_nit_notifications_log',
        get_string('notificationlog', 'local_nit_notifications'),
        new moodle_url('/local/nit_notifications/log.php'),
        'local/nit_notifications:send'
    ));
}

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
 * Scheduled task: warn subscribers whose plan is about to run out.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_subscriptions\task;

defined('MOODLE_INTERNAL') || die();

use local_nit_subscriptions\reminder_manager;

/**
 * Send the expiry reminders that have come due.
 *
 * The lead times are set on the "Renewal reminders" tab of the subscriptions admin page, and
 * saving them there runs the same calculation immediately — this task is what covers the time
 * in between, as each subscriber's deadline crosses into the window on its own.
 *
 * Hourly, and cheap: it only looks at active purchases whose deadline is already inside the
 * widest configured lead time, and each one is only ever notified once per lead time.
 */
class send_subscription_reminders extends \local_nit_core\base\scheduled_task {

    /**
     * Name shown on Site administration › Server › Scheduled tasks.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_send_subscription_reminders', 'local_nit_subscriptions');
    }

    /**
     * Send what is due and report what went out.
     *
     * @return void
     */
    protected function run(): void {
        // An academy whose licence dropped subscriptions still has the plugin installed and
        // the cron entry scheduled; it should not keep mailing people about a feature its
        // admins can no longer see or switch off.
        require_once(__DIR__ . '/../../lib.php');
        if (!local_nit_subscriptions_feature()) {
            return;
        }

        $result = reminder_manager::run();

        if ($result['sent'] > 0 || $result['failed'] > 0) {
            mtrace("local_nit_subscriptions: sent {$result['sent']} expiry reminder(s)"
                . ($result['failed'] > 0 ? ", {$result['failed']} failed" : '')
                . " from {$result['considered']} subscription(s) in the window.");
        }
    }
}

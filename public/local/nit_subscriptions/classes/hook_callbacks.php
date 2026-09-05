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
 * Hook callbacks for local_nit_subscriptions.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_subscriptions;

defined('MOODLE_INTERNAL') || die();

/**
 * Navigation hook callbacks.
 */
class hook_callbacks {

    /**
     * Add "Manage subscriptions" to the site primary navigation for users who
     * hold the manage capability and whose licence includes subscriptions.
     *
     * @param \core\hook\navigation\primary_extend $hook
     * @return void
     */
    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        $primary = $hook->get_primaryview();
        $context = \context_system::instance();

        $hasfeature = !class_exists('\\local_license\\license')
            || \local_license\license::has_feature('subscriptions');

        if ($hasfeature && has_capability('local/nit_subscriptions:managesubscriptions', $context)) {
            $primary->add(
                get_string('managesubscriptions', 'local_nit_subscriptions'),
                new \moodle_url('/local/nit_subscriptions/manage_subscriptions.php'),
                \navigation_node::TYPE_CUSTOM,
                null,
                'local_nit_subscriptions_manage'
            );
        }
    }
}

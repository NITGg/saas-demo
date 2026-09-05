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
 * Hook callbacks for local_nit_commerce.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_commerce;

defined('MOODLE_INTERNAL') || die();

/**
 * Navigation hook callbacks.
 */
class hook_callbacks {

    /**
     * Add "Manage coupons/offers" to the site primary navigation for users who
     * hold the manage capability and whose licence includes the feature.
     *
     * @param \core\hook\navigation\primary_extend $hook
     * @return void
     */
    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        $primary = $hook->get_primaryview();
        $context = \context_system::instance();

        // Licence-gated (true when local_license is absent or enforcement is off).
        $has = static function (string $feature): bool {
            return !class_exists('\\local_license\\license')
                || \local_license\license::has_feature($feature);
        };

        if ($has('coupons') && has_capability('local/nit_commerce:managecoupons', $context)) {
            $primary->add(
                get_string('managecoupons', 'local_nit_commerce'),
                new \moodle_url('/local/nit_commerce/manage_coupons.php'),
                \navigation_node::TYPE_CUSTOM,
                null,
                'local_nit_commerce_coupons'
            );
        }
        if ($has('offers') && has_capability('local/nit_commerce:manageoffers', $context)) {
            $primary->add(
                get_string('manageoffers', 'local_nit_commerce'),
                new \moodle_url('/local/nit_commerce/manage_offers.php'),
                \navigation_node::TYPE_CUSTOM,
                null,
                'local_nit_commerce_offers'
            );
        }
    }
}

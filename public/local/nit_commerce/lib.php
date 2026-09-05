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
 * Library functions for local_nit_commerce.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Build an associative map of localized strings for the given keys, for shipping to JS.
 *
 * @param array $keys string keys in local_nit_commerce
 * @return array key => localized string
 */
function local_nit_commerce_string_map(array $keys): array {
    $out = [];
    foreach ($keys as $key) {
        $out[$key] = get_string($key, 'local_nit_commerce');
    }
    return $out;
}

/**
 * Whether the academy's licence includes a nit_commerce feature (coupons|offers).
 *
 * Returns true when local_license is absent or enforcement is off — license::has_feature()
 * already yields true in those cases — so unmanaged academies keep everything.
 *
 * @param string $feature 'coupons'|'offers'
 * @return bool
 */
function local_nit_commerce_feature(string $feature): bool {
    if (!class_exists('\\local_license\\license')) {
        return true;
    }
    return \local_license\license::has_feature($feature);
}

/**
 * Block a management page when the feature is not on the current licence: render a
 * short notice and stop. Call after admin_externalpage_setup(), before header().
 *
 * @param string $feature 'coupons'|'offers'
 * @return void
 */
function local_nit_commerce_require_feature(string $feature): void {
    global $OUTPUT, $PAGE;
    if (local_nit_commerce_feature($feature)) {
        return;
    }
    $PAGE->set_title(get_string('feature_unavailable', 'local_nit_commerce'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('feature_unavailable_desc', 'local_nit_commerce'),
        \core\output\notification::NOTIFY_INFO
    );
    echo $OUTPUT->footer();
    exit;
}

/**
 * Add "Manage coupons/offers" links to the main navigation so the academy owner
 * (a restricted manager, not a site admin) can find them without digging into
 * Site administration or knowing the URL. Shown only to users who hold the
 * manage capability and when the licence includes the feature.
 *
 * @param global_navigation $navigation
 * @return void
 */
function local_nit_commerce_extend_navigation(global_navigation $navigation): void {
    $context = context_system::instance();

    if (local_nit_commerce_feature('coupons')
            && has_capability('local/nit_commerce:managecoupons', $context)) {
        $node = $navigation->add(
            get_string('managecoupons', 'local_nit_commerce'),
            new moodle_url('/local/nit_commerce/manage_coupons.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_nit_commerce_coupons',
            new pix_icon('i/settings', '')
        );
        // Boost's nav drawer / flat navigation only renders nodes flagged for it.
        $node->showinflatnavigation = true;
    }
    if (local_nit_commerce_feature('offers')
            && has_capability('local/nit_commerce:manageoffers', $context)) {
        $node = $navigation->add(
            get_string('manageoffers', 'local_nit_commerce'),
            new moodle_url('/local/nit_commerce/manage_offers.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_nit_commerce_offers',
            new pix_icon('i/settings', '')
        );
        $node->showinflatnavigation = true;
    }
}

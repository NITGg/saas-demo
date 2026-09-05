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
 * Library functions for local_nit_subscriptions.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Build an associative map of localized strings for the given keys, for shipping to JS.
 *
 * @param array $keys string keys in local_nit_subscriptions
 * @return array key => localized string
 */
function local_nit_subscriptions_string_map(array $keys): array {
    $out = [];
    foreach ($keys as $key) {
        $out[$key] = get_string($key, 'local_nit_subscriptions');
    }
    return $out;
}

/**
 * Whether the academy's licence includes the subscriptions feature.
 *
 * Returns true when local_license is absent or enforcement is off (license::has_feature()
 * already yields true there), so unmanaged academies keep subscriptions.
 *
 * @return bool
 */
function local_nit_subscriptions_feature(): bool {
    if (!class_exists('\\local_license\\license')) {
        return true;
    }
    return \local_license\license::has_feature('subscriptions');
}

/**
 * Block a management page when subscriptions are not on the current licence: render a
 * short notice and stop. Call after admin_externalpage_setup(), before header().
 *
 * @return void
 */
function local_nit_subscriptions_require_feature(): void {
    global $OUTPUT, $PAGE;
    if (local_nit_subscriptions_feature()) {
        return;
    }
    $PAGE->set_title(get_string('feature_unavailable', 'local_nit_subscriptions'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('feature_unavailable_desc', 'local_nit_subscriptions'),
        \core\output\notification::NOTIFY_INFO
    );
    echo $OUTPUT->footer();
    exit;
}

/**
 * Add a "Manage subscriptions" link to the main navigation so the academy owner
 * (a restricted manager, not a site admin) can find it without digging into Site
 * administration or knowing the URL. Shown only to users who hold the manage
 * capability and when the licence includes subscriptions.
 *
 * @param global_navigation $navigation
 * @return void
 */
function local_nit_subscriptions_extend_navigation(global_navigation $navigation): void {
    $context = context_system::instance();

    if (local_nit_subscriptions_feature()
            && has_capability('local/nit_subscriptions:managesubscriptions', $context)) {
        $node = $navigation->add(
            get_string('managesubscriptions', 'local_nit_subscriptions'),
            new moodle_url('/local/nit_subscriptions/manage_subscriptions.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_nit_subscriptions_manage',
            new pix_icon('i/settings', '')
        );
        // Boost's nav drawer / flat navigation only renders nodes flagged for it.
        $node->showinflatnavigation = true;
    }
}

/**
 * Active subscription plans shaped for public listing (front-page block + mobile web service).
 *
 * Each plan carries its price, unlocked courses, B2B seat tiers and the best current auto-offer.
 * Shared by api.php (?function=get_available_subscriptions) and the get_available_subscriptions
 * external function so both return the identical shape.
 *
 * @return array
 */
function nit_subscriptions_available(): array {
    $subs = \local_nit_subscriptions\subscription_manager::get_subscriptions(
        \local_nit_subscriptions\subscription_manager::STATUS_ACTIVE);
    $hasoffers = class_exists('\local_nit_commerce\discount_manager');
    $out = [];
    foreach ($subs as $s) {
        // The best auto-offer on this plan. Exposed two ways: flat offer_label/offer_final (the web
        // block reads these) and a nested `offer` object (the mobile app reads this) — present only
        // when there IS an active offer.
        $offerlabel = '';
        $offerfinal = 0.0;
        $offer = null;
        if ($hasoffers) {
            $summary = \local_nit_commerce\discount_manager::offer_summary('subscription', (int) $s->id, (float) $s->price);
            if ($summary) {
                $offerlabel = $summary['label'];      // e.g. "-10%"
                $offerfinal = (float) $summary['final'];
                $offer = [
                    'original' => (float) $summary['original'],
                    'final'    => (float) $summary['final'],
                    'label'    => (string) $summary['label'],
                    'name'     => (string) $summary['name'],
                ];
            }
        }
        $item = [
            'id'            => (int) $s->id,
            'name'          => format_string(\local_nit_subscriptions\subscription_manager::resolve_mlang($s->name)),
            'description'   => $s->description !== null
                ? format_text(\local_nit_subscriptions\subscription_manager::resolve_mlang($s->description), FORMAT_HTML) : '',
            'price'         => (float) $s->price,
            'duration_days' => (int) $s->duration_days,
            'status'        => (string) $s->status,
            'b2b_enabled'   => (int) $s->b2b_enabled,
            'courses_count' => count($s->courses),
            // Full {id, fullname} objects — the mobile app needs the course id to map coverage.
            'courses'       => array_values($s->courses),
            'seat_options'  => $s->seat_options,
            'offer_label'   => $offerlabel,
            'offer_final'   => $offerfinal,
        ];
        if ($offer !== null) {
            $item['offer'] = $offer;
        }
        $out[] = $item;
    }
    return $out;
}

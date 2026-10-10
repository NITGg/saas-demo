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

namespace local_academy\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * The order of Site administration → Plugins → Local plugins.
 *
 * Core loads the local plugins' settings.php sorted by their display name in the
 * CURRENT language, so the list is ordered differently in Arabic and English and
 * no plugin can rely on being loaded last. Instead every plugin owning a page of
 * FIRST calls apply() at the end of its settings.php: each call moves the pages
 * of FIRST that exist so far to the top, in this order, and keeps everything else
 * in its current order below them. After the last of those plugins has loaded the
 * list is final; a plugin loaded later is appended below, as it would be anyway.
 *
 * Extends admin_category only to reach its protected children of the existing
 * 'localplugins' node.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_order extends \admin_category {

    /** @var string[] Admin node names that lead the list, in this order. */
    const FIRST = [
        'local_nit_commerce_managecoupons',
        'local_nit_commerce_manageoffers',
        'local_nit_subscriptions_managesubscriptions',
        'local_nit_subscriptions_managecourses',
        'theme_nit_sitepages',
        'local_nit_ai',
        'local_nit_devices',
        'local_jobform_manage',
        'local_nit_flex_cat',
        'local_nit_mlang_settings',
        'local_nit_finance_cat',
        'local_nit_notifications_cat',
        'local_nit_reports',
        'local_academysessions_monitor',
    ];

    /**
     * Put the FIRST pages present under $parent at its top (stable for the rest).
     *
     * @param \part_of_admin_tree $admin the admin root ($ADMIN in settings.php)
     * @param string $parent the category to order
     * @param string[]|null $first node names to lead with (default FIRST)
     */
    public static function apply(\part_of_admin_tree $admin, string $parent = 'localplugins', ?array $first = null): void {
        $category = $admin->locate($parent);
        if (!$category instanceof \admin_category) {
            return;
        }
        $rank = array_flip($first ?? self::FIRST);
        $lead = [];
        $rest = [];
        foreach ($category->children as $child) {
            if (isset($rank[$child->name])) {
                $lead[$rank[$child->name]] = $child;
            } else {
                $rest[] = $child;
            }
        }
        ksort($lead);
        $category->children = array_merge(array_values($lead), $rest);
    }
}

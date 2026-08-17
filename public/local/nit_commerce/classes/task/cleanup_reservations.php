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

namespace local_nit_commerce\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Release abandoned coupon/offer reservations.
 *
 * A reservation is a coupon/offer usage row tied to a still-pending checkout
 * (see discount_manager::reserve_usage). If that checkout's payment failed, was
 * cancelled, or was never completed before it expired, the reservation must be
 * released so the coupon is available to others. This is the safety net that
 * catches every failure path (webhook-failed, browser-closed, timed-out).
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_reservations extends \core\task\scheduled_task {

    /**
     * @return string
     */
    public function get_name() {
        return get_string('cleanupreservations', 'local_nit_commerce');
    }

    /**
     * Delete usage rows whose transaction failed / was cancelled / expired unpaid.
     */
    public function execute() {
        global $DB;

        // We need the payments transactions table to know which reservations are
        // stale; if the payments plugin is absent, there is nothing to do.
        if (!$DB->get_manager()->table_exists('local_payments_transactions')) {
            return;
        }

        $now = time();
        foreach (['nit_coupon_usage', 'nit_offer_usage'] as $table) {
            if (!$DB->get_manager()->table_exists($table)) {
                continue;
            }
            // Reservations whose transaction is a dead end.
            $sql = "SELECT DISTINCT u.transactionid
                      FROM {" . $table . "} u
                      JOIN {local_payments_transactions} t ON t.id = u.transactionid
                     WHERE t.status IN ('failed', 'cancelled')
                        OR (t.status = 'pending' AND t.expires_at > 0 AND t.expires_at < :now)";
            $ids = $DB->get_fieldset_sql($sql, ['now' => $now]);
            if ($ids) {
                [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
                $DB->delete_records_select($table, "transactionid $insql", $params);
            }
        }
    }
}

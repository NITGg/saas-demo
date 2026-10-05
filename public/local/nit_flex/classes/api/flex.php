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

namespace local_nit_flex\api;

use local_nit_flex\service\flex_service;
use local_nit_flex\service\purchase_service;

/**
 * Public facade for the Flex balance engine. Called by nit_lessons inside its transactions.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
final class flex {
    /**
     * Reserve one Flex from the student's active package (US-FN-1-2).
     *
     * @param int $studentid
     * @param int $lessonid
     * @param int $performedby
     * @param string $reason
     * @return int purchase id the Flex was reserved from
     */
    public static function reserve(int $studentid, int $lessonid, int $performedby, string $reason = ''): int {
        return (new flex_service())->reserve($studentid, $lessonid, $performedby, $reason);
    }

    /**
     * Permanently consume a reserved Flex.
     *
     * @param int $studentid
     * @param int $purchaseid
     * @param int $lessonid
     * @param int $performedby
     * @param string $reason
     * @return void
     */
    public static function consume(int $studentid, int $purchaseid, int $lessonid, int $performedby,
            string $reason = ''): void {
        (new flex_service())->consume($studentid, $purchaseid, $lessonid, $performedby, $reason);
    }

    /**
     * Return a reserved Flex to the student's balance (US-FN-1-3).
     *
     * @param int $studentid
     * @param int $purchaseid
     * @param int $lessonid
     * @param int $performedby
     * @param string $reason
     * @return void
     */
    public static function return_flex(int $studentid, int $purchaseid, int $lessonid, int $performedby,
            string $reason = ''): void {
        (new flex_service())->return_flex($studentid, $purchaseid, $lessonid, $performedby, $reason);
    }

    /**
     * Reverse a consumed Flex (admin reversal, US-FN-1-5).
     *
     * @param int $studentid
     * @param int $purchaseid
     * @param int $lessonid
     * @param int $performedby
     * @param string $reason
     * @return void
     */
    public static function reverse_consumed(int $studentid, int $purchaseid, int $lessonid, int $performedby,
            string $reason = ''): void {
        (new flex_service())->reverse_consumed($studentid, $purchaseid, $lessonid, $performedby, $reason);
    }

    /**
     * Value of one Flex from a purchase, in minor units.
     *
     * @param int $purchaseid
     * @return int
     */
    public static function value_for_purchase(int $purchaseid): int {
        return (new flex_service())->value_for_purchase($purchaseid);
    }

    /**
     * A student's Flex ledger (US-AD-3-4).
     *
     * @param int $userid
     * @return array
     */
    public static function history(int $userid): array {
        return (new flex_service())->history($userid);
    }

    /**
     * Money totals for the platform wallet: total payments in + unconsumed Flex value (minor units).
     *
     * @return array{payments_minor:int, undistributed_minor:int}
     */
    public static function money_totals(): array {
        return (new purchase_service())->money_totals();
    }
}

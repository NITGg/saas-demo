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

namespace local_nit_analytics;

/**
 * Purchase conversions: one row per paid purchase (course, Flex package, subscription), kept for the
 * funnel report, plus the browser "purchase" event fired on the buyer's next page.
 *
 * Payment plugins call record_purchase() (guarded by class_exists, so they work without this
 * plugin); course payments arrive through the local_payments payment_completed observer.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class conversions {
    /** @var string Table of recorded purchases. */
    const TABLE = 'local_nit_analytics_conv';

    /** @var string User preference holding purchases whose browser event is still to be sent (JSON). */
    const PREF_PENDING = 'local_nit_analytics_purchase_pending';

    /** @var string[] Known purchase kinds. */
    const KINDS = ['course', 'flex', 'subscription'];

    /**
     * Record a paid purchase once (kind + ref is unique). The browser event is queued only when the
     * buyer paid themselves — not for a package an admin assigned to them.
     *
     * @param int $userid buyer
     * @param string $kind one of KINDS
     * @param int $amountminor amount paid, minor units (piastres / halalas / cents)
     * @param string $currency ISO code, e.g. EGP
     * @param string $ref unique reference within the kind (transaction / purchase id)
     * @param bool $byuser the buyer did it (queues the browser purchase event)
     * @return bool false when already recorded, free, or invalid
     */
    public static function record_purchase(int $userid, string $kind, int $amountminor, string $currency,
            string $ref, bool $byuser): bool {
        global $DB;
        $currency = strtoupper(trim($currency));
        if ($userid <= 0 || $amountminor <= 0 || $ref === '' || !in_array($kind, self::KINDS, true)
                || !preg_match('/^[A-Z]{3}$/', $currency)) {
            return false;
        }
        $ref = \core_text::substr($ref, 0, 100);
        if ($DB->record_exists(self::TABLE, ['kind' => $kind, 'ref' => $ref])) {
            return false;
        }
        $DB->insert_record(self::TABLE, (object) [
            'userid' => $userid,
            'kind' => $kind,
            'amount_minor' => $amountminor,
            'currency' => $currency,
            'ref' => $ref,
            'timecreated' => time(),
        ]);
        if ($byuser) {
            $pending = self::pending($userid);
            $pending[] = ['ref' => $kind . '-' . $ref, 'amount_minor' => $amountminor, 'currency' => $currency];
            set_user_preference(self::PREF_PENDING, json_encode(array_slice($pending, -10)), $userid);
        }
        return true;
    }

    /**
     * Record a purchase made by the current user if they are the buyer (helper for payment plugins).
     *
     * @param int $userid buyer
     * @param string $kind
     * @param float $amountmajor amount in major units (e.g. 150.50 EGP)
     * @param string $currency
     * @param string $ref
     * @return bool
     */
    public static function record_purchase_major(int $userid, string $kind, float $amountmajor, string $currency,
            string $ref): bool {
        global $USER;
        return self::record_purchase($userid, $kind, (int) round($amountmajor * 100), $currency, $ref,
            isset($USER->id) && (int) $USER->id === $userid);
    }

    /**
     * Purchases still waiting for their browser event.
     *
     * @param int $userid
     * @return array[]
     */
    private static function pending(int $userid): array {
        $list = json_decode((string) get_user_preferences(self::PREF_PENDING, '', $userid), true);
        return is_array($list) ? $list : [];
    }

    /**
     * Read and clear the purchases whose browser event is to be sent now.
     *
     * @param int $userid
     * @return array[] each {ref, amount_minor, currency}
     */
    public static function take_pending(int $userid): array {
        if (!$userid || isguestuser($userid)) {
            return [];
        }
        $list = self::pending($userid);
        if ($list) {
            unset_user_preference(self::PREF_PENDING, $userid);
        }
        return $list;
    }

    /**
     * A course payment completed (local_payments).
     *
     * @param \core\event\base $event \local_payments\event\payment_completed
     * @return void
     */
    public static function payment_completed(\core\event\base $event): void {
        try {
            $other = $event->other ?? [];
            self::record_purchase((int) $event->userid, 'course', (int) round(((float) ($other['amount'] ?? 0)) * 100),
                (string) ($other['currency'] ?? ''), (string) $event->objectid, true);
        } catch (\Throwable $e) {
            debugging('local_nit_analytics: could not record course purchase: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

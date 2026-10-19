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

namespace local_payments\local;

/**
 * Where callback.php sends the buyer back to, per item type: a course has its
 * buy page, the other items (wallet top-up, lesson package, subscription) have
 * no course, so "Try again" goes to the page the checkout started from.
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class return_pages {

    /**
     * The page the checkout was started from (metadata return_url), when it is a page of this site.
     *
     * @param \stdClass|null $transaction local_payments_transactions row
     * @return \moodle_url|null
     */
    public static function started_from(?\stdClass $transaction): ?\moodle_url {
        $back = (string) (json_decode($transaction->metadata ?? '{}')->return_url ?? '');
        return ($back !== '' && strpos($back, '/') === 0 && strpos($back, '//') !== 0) ? new \moodle_url($back) : null;
    }

    /**
     * The clean address of the return page when the gateway added more than we use. Kashier appends
     * card details (cardDataToken, maskedCard, …) to the redirect; left in the address bar they end
     * up in browser history and in every analytics hit, and Meta's pixel suppresses the Purchase on
     * a page whose URL carries card data. Only order_id and paymentStatus are kept.
     *
     * @param array $params the request's query parameters ($_GET)
     * @return \moodle_url|null the URL to redirect to, or null when it is already clean
     */
    public static function clean_callback(array $params): ?\moodle_url {
        $keep = array_intersect_key($params, ['order_id' => 1, 'paymentStatus' => 1]);
        if (count($keep) === count($params) || !isset($keep['order_id']) || !is_string($keep['order_id'])) {
            return null;
        }
        $clean = ['order_id' => clean_param($keep['order_id'], PARAM_TEXT)];
        if (isset($keep['paymentStatus']) && is_string($keep['paymentStatus'])) {
            $clean['paymentStatus'] = clean_param($keep['paymentStatus'], PARAM_ALPHANUMEXT);
        }
        return new \moodle_url('/local/payments/callback.php', $clean);
    }

    /**
     * "Try again" after a failed or cancelled payment.
     *
     * @param \stdClass|null $transaction
     * @return \moodle_url
     */
    public static function retry(?\stdClass $transaction): \moodle_url {
        if (!$transaction) {
            return new \moodle_url('/');
        }
        switch (json_decode($transaction->metadata ?? '{}')->item_type ?? 'course') {
            case 'wallet_topup':
                return self::started_from($transaction) ?? new \moodle_url('/local/nit_finance/wallet.php');
            case 'package':
                return self::started_from($transaction) ?? new \moodle_url('/local/nit_flex/packages.php');
            case 'subscription':
                return self::started_from($transaction) ?? new \moodle_url('/');
            default:
                return (int) $transaction->courseid > 0
                    ? new \moodle_url('/local/payments/buy.php', ['courseid' => $transaction->courseid])
                    : new \moodle_url('/');
        }
    }
}

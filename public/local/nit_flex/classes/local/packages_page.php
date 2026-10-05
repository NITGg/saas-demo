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

namespace local_nit_flex\local;

use local_nit_finance\local\money;
use local_nit_finance\local\output as finance_output;
use local_nit_finance\local\wallets;
use local_nit_flex\api\packages;
use local_nit_flex\api\purchase;
use local_nit_flex\exception\flex_exception;

/**
 * Builds the "Available packages" page and starts online checkouts.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class packages_page {

    /**
     * Template context for local_nit_flex/packages_page.
     *
     * @param int $userid
     * @param \moodle_url $url
     * @return array
     */
    public static function context(int $userid, \moodle_url $url): array {
        global $DB;
        $active = purchase::active($userid);
        $balance = wallets::balance(wallets::STUDENT, $userid);

        $cards = [];
        foreach (packages::available() as $p) {
            $quote = purchase::quote($userid, (int) $p['id']);
            $cards[] = [
                'id' => (int) $p['id'],
                'name' => format_string($p['name']),
                'description' => $p['description'] !== null && $p['description'] !== ''
                    ? format_text($p['description'], FORMAT_PLAIN) : '',
                'flex' => (int) $p['flex_count'],
                'validity' => (int) $p['expiration_days'] > 0
                    ? get_string('validdays', 'local_nit_flex', (int) $p['expiration_days'])
                    : get_string('neverexpires', 'local_nit_flex'),
                'price' => money::format($quote['final_minor']),
                'priceminor' => $quote['final_minor'],
                'original' => $quote['discount_minor'] > 0 ? money::format($quote['original_minor']) : '',
                'perflex' => get_string('perflex', 'local_nit_flex',
                    money::format((int) round($quote['final_minor'] / max(1, (int) $p['flex_count'])))),
                'disabled' => $active !== null,
            ];
        }

        $activectx = null;
        if ($active) {
            $name = (string) $DB->get_field('nit_package', 'name', ['id' => $active['packageid']]);
            $activectx = [
                'name' => format_string($name),
                'remaining' => $active['remaining_flex'],
                'total' => $active['total_flex'],
                'expires' => $active['expires_at'] > 0
                    ? userdate($active['expires_at'], get_string('strftimedatefullshort', 'langconfig'))
                    : get_string('neverexpires', 'local_nit_flex'),
                'bookurl' => (new \moodle_url('/local/nit_lessons/student.php', ['tab' => 'book']))->out(false),
            ];
        }

        return [
            'packages' => $cards,
            'haspackages' => !empty($cards),
            'active' => $activectx,
            'balance' => money::format($balance),
            'balanceminor' => $balance,
            'action' => $url->out(false),
            'sesskey' => sesskey(),
            'canonline' => self::online_available(),
            'cancoupon' => class_exists('\local_nit_commerce\discount_manager'),
            'previewurl' => (new \moodle_url('/local/nit_commerce/api.php'))->out(false),
            'topupurl' => (new \moodle_url('/local/nit_finance/wallet.php', [], 'topup'))->out(false),
            'hubpackagesurl' => (new \moodle_url('/local/nit_lessons/student.php', ['tab' => 'packages']))->out(false),
        ];
    }

    /**
     * Whether packages can be paid online right now.
     *
     * @return bool
     */
    public static function online_available(): bool {
        return method_exists('\local_payments\manager', 'create_package_checkout')
            && finance_output::online_payment_available();
    }

    /**
     * Start an online (gateway) checkout for a package and return where to send the student.
     *
     * @param int $userid
     * @param int $packageid
     * @param string $coupon
     * @return \moodle_url
     */
    public static function start_online_checkout(int $userid, int $packageid, string $coupon): \moodle_url {
        if (!self::online_available()) {
            throw new flex_exception('err_noonline');
        }
        if (purchase::active($userid)) {
            throw new flex_exception('err_alreadyhaspackage');
        }
        $checkout = \local_payments\manager::create_package_checkout($packageid, $userid, current_language(),
            $coupon, '/local/nit_lessons/student.php?tab=packages');
        return new \moodle_url($checkout->checkout_url);
    }
}

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
use local_nit_finance\local\wallets;
use local_nit_flex\api\flex;
use local_nit_flex\api\packages;
use local_nit_flex\api\purchase;
use local_nit_flex\entity\package;
use local_nit_flex\exception\flex_exception;

/**
 * Lesson packages for the mobile app (`/local/nit_flex/api.php`): browse, quote, buy with the
 * wallet or online, my packages / payments / Flex history, and the admin catalogue.
 *
 * Same rules as the web pages (it calls the same services). Money goes out twice:
 * `<name>_minor` (int piastres) and `<name>` (float pounds), with `currency`; amounts come
 * in as pounds typed by a person ("150", "99.5").
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mobile_api {

    /** Currency of every amount. */
    public const CURRENCY = 'EGP';

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * `<key>_minor` + `<key>` for one amount.
     *
     * @param string $key
     * @param int $minor
     * @return array
     */
    private static function money(string $key, int $minor): array {
        return [$key . '_minor' => $minor, $key => round($minor / 100, 2)];
    }

    /**
     * A string of this plugin.
     *
     * @param string $key
     * @param mixed $a
     * @return string
     */
    private static function str(string $key, $a = null): string {
        return get_string_manager()->string_exists($key, 'local_nit_flex') ? get_string($key, 'local_nit_flex', $a) : $key;
    }

    /**
     * Package names by id (raw, multilang kept).
     *
     * @return array
     */
    private static function names(): array {
        global $DB;
        return $DB->get_records_menu('nit_package', null, '', 'id, name');
    }

    /**
     * A purchase summary as the app sees it.
     *
     * @param array $p from purchase_service::summarise()
     * @param array $names
     * @return array
     */
    private static function purchase(array $p, array $names): array {
        return [
            'id' => $p['id'],
            'packageid' => $p['packageid'],
            'name' => format_string($names[$p['packageid']] ?? ''),
            'total_flex' => $p['total_flex'],
            'remaining_flex' => $p['remaining_flex'],
            'reserved_flex' => $p['reserved_flex'],
            'consumed_flex' => $p['consumed_flex'],
            'status' => $p['status'],
            'status_label' => self::str('pstat_' . $p['status']),
            'source' => $p['source'],
            'timeactivated' => $p['timeactivated'],
            'expires_at' => $p['expires_at'],
        ] + self::money('price_paid', $p['price_paid_minor']) + ['currency' => self::CURRENCY];
    }

    /**
     * A catalogue package (prices after automatic offers when $userid is given).
     *
     * @param array $p from package_service
     * @param int $userid 0 = admin view (no offers applied)
     * @return array
     */
    private static function package(array $p, int $userid = 0): array {
        $final = (int) $p['price_minor'];
        if ($userid && $p['status'] === package::STATUS_ACTIVE) {
            $final = purchase::quote($userid, (int) $p['id'])['final_minor'];
        }
        [$namear, $nameen] = mlang::split($p['name']);
        [$descar, $descen] = mlang::split($p['description']);
        $out = [
            'id' => (int) $p['id'],
            'name' => format_string($p['name']),
            'description' => $p['description'] !== null ? format_string($p['description']) : '',
            'flex_count' => (int) $p['flex_count'],
            'expiration_days' => (int) $p['expiration_days'],
            'validity_label' => (int) $p['expiration_days'] > 0
                ? self::str('validdays', (int) $p['expiration_days']) : self::str('neverexpires'),
            'status' => $p['status'],
        ] + self::money('price', (int) $p['price_minor'])
          + self::money('final_price', $final)
          + self::money('price_per_flex', (int) round($final / max(1, (int) $p['flex_count'])))
          + ['currency' => self::CURRENCY];
        if (!$userid) {
            $out += ['name_ar' => $namear, 'name_en' => $nameen, 'description_ar' => $descar, 'description_en' => $descen];
        }
        return $out;
    }

    /**
     * Whether online (gateway) payment of packages works right now.
     *
     * @return bool
     */
    private static function online(): bool {
        return packages_page::online_available();
    }

    // ── Student ──────────────────────────────────────────────────────────────

    /**
     * Packages for sale, the student's active package and wallet balance.
     *
     * @param int $userid
     * @return array
     */
    public static function get_packages(int $userid): array {
        $active = purchase::active($userid);
        return [
            'packages' => array_map(fn($p) => self::package($p, $userid), packages::available()),
            'can_buy' => $active === null,
            'active' => $active ? self::purchase($active, self::names()) : null,
            'online_payment' => self::online(),
        ] + self::money('wallet_balance', wallets::balance(wallets::STUDENT, $userid)) + ['currency' => self::CURRENCY];
    }

    /**
     * The price to pay for a package with an optional coupon. An invalid coupon does not fail
     * the call: the price without it comes back with `coupon_error`.
     *
     * @param int $userid
     * @param int $packageid
     * @param string $coupon
     * @return array
     */
    public static function get_package_quote(int $userid, int $packageid, string $coupon = ''): array {
        $couponerror = '';
        try {
            $q = purchase::quote($userid, $packageid, $coupon);
        } catch (flex_exception $e) {
            throw $e;
        } catch (\moodle_exception $e) {
            $couponerror = $e->getMessage();
            $q = purchase::quote($userid, $packageid, '');
        }
        $balance = wallets::balance(wallets::STUDENT, $userid);
        return [
            'packageid' => $packageid,
            'coupon_code' => trim($coupon),
            'coupon_applied' => $coupon !== '' && $couponerror === '' && !empty($q['discount']['coupon_discount']),
            'coupon_error' => $couponerror,
        ] + self::money('original_price', $q['original_minor'])
          + self::money('discount', $q['discount_minor'])
          + self::money('final_price', $q['final_minor'])
          + self::money('wallet_balance', $balance) + [
            'can_pay_wallet' => $balance >= $q['final_minor'] && purchase::active($userid) === null,
            'can_pay_online' => self::online() && $q['final_minor'] > 0,
            'currency' => self::CURRENCY,
        ];
    }

    /**
     * Buy a package with the wallet.
     *
     * @param int $userid
     * @param int $packageid
     * @param string $coupon
     * @return array the purchase + new wallet balance
     */
    public static function buy_package_wallet(int $userid, int $packageid, string $coupon = ''): array {
        $p = purchase::buy_with_wallet($userid, $packageid, $coupon);
        return ['purchase' => self::purchase($p, self::names())]
            + self::money('wallet_balance', wallets::balance(wallets::STUDENT, $userid)) + ['currency' => self::CURRENCY];
    }

    /**
     * Start an online payment for a package: open `checkout_url`, then poll
     * get_package_checkout_status with `order_id`.
     *
     * @param int $userid
     * @param int $packageid
     * @param string $coupon
     * @param string $lang
     * @return array
     */
    public static function create_package_checkout(int $userid, int $packageid, string $coupon, string $lang): array {
        global $CFG;
        if (!self::online()) {
            throw new flex_exception('err_noonline');
        }
        $checkout = \local_payments\manager::create_package_checkout($packageid, $userid, $lang === 'en' ? 'en' : 'ar',
            $coupon, '/local/nit_lessons/student.php?tab=packages');
        return [
            'order_id' => (string) $checkout->order_id,
            'checkout_url' => (string) $checkout->checkout_url,
            'expires_at' => (int) $checkout->expires_at,
            'transaction_id' => (int) $checkout->transaction_id,
        ] + self::money('amount', (int) round($checkout->amount * 100))
          + self::money('original_amount', (int) round($checkout->original_amount * 100)) + [
            'currency' => self::CURRENCY,
            'callback_url' => $CFG->wwwroot . '/local/payments/callback.php',
        ];
    }

    /**
     * Where an online package payment stands. Asks the gateway when the webhook has not come yet
     * (which grants the package if it was paid).
     *
     * @param int $userid
     * @param string $orderid
     * @return array
     */
    public static function get_package_checkout_status(int $userid, string $orderid): array {
        global $DB;
        $tx = $orderid === '' ? null : $DB->get_record('local_payments_transactions', ['order_id' => $orderid]);
        $meta = $tx ? json_decode($tx->metadata ?? '{}') : null;
        if (!$tx || (int) $tx->userid !== $userid || ($meta->item_type ?? '') !== 'package') {
            throw new flex_exception('err_ordernotfound');
        }
        if ($tx->status !== 'completed') {
            try {
                \local_payments\manager::verify_callback($orderid);
            } catch (\Throwable $e) {
                debugging('local_nit_flex: package verify failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
            $tx = $DB->get_record('local_payments_transactions', ['id' => $tx->id], '*', MUST_EXIST);
        }
        $paymentid = (int) $DB->get_field('nit_payment', 'purchaseid', ['userid' => $userid, 'reference' => $orderid]);
        $towallet = !$paymentid && $DB->record_exists_sql(
            "SELECT 1 FROM {nit_wallet_txn} t JOIN {nit_wallet} w ON w.id = t.walletid
              WHERE w.ownertype = 'student' AND w.userid = :uid AND t.itemtype = 'payment' AND t.itemid = :txid",
            ['uid' => $userid, 'txid' => $tx->id]);
        $active = purchase::active($userid);
        return [
            'order_id' => (string) $tx->order_id,
            'status' => (string) $tx->status,
            'paid' => $tx->status === 'completed',
            'granted' => $paymentid > 0,
            'purchaseid' => $paymentid,
            'credited_to_wallet' => $towallet,
            'active' => $active ? self::purchase($active, self::names()) : null,
        ] + self::money('amount', (int) round((float) $tx->amount * 100)) + ['currency' => self::CURRENCY];
    }

    /**
     * The student's Flex balance (active package) in one call.
     *
     * @param int $userid
     * @return array
     */
    public static function get_my_flex(int $userid): array {
        $active = purchase::active($userid);
        return [
            'available_flex' => $active ? $active['remaining_flex'] : 0,
            'reserved_flex' => $active ? $active['reserved_flex'] : 0,
            'has_package' => $active !== null,
            'active' => $active ? self::purchase($active, self::names()) : null,
        ];
    }

    /**
     * All the student's packages, active first.
     *
     * @param int $userid
     * @return array
     */
    public static function get_my_packages(int $userid): array {
        $names = self::names();
        return ['packages' => array_map(fn($p) => self::purchase($p, $names), purchase::my_packages($userid))];
    }

    /**
     * Package payments, newest first.
     *
     * @param int $userid
     * @return array
     */
    public static function get_package_payments(int $userid): array {
        $names = self::names();
        $out = [];
        foreach (purchase::payment_history($userid) as $p) {
            $out[] = [
                'id' => $p['id'],
                'packageid' => $p['packageid'],
                'name' => format_string($names[$p['packageid']] ?? ''),
                'method' => $p['method'],
                'method_label' => self::str('method_' . $p['method']),
                'transaction_no' => (string) $p['transaction_no'],
                'reference' => (string) $p['reference'],
                'status' => $p['status'],
                'timecreated' => $p['timecreated'],
            ] + self::money('amount', $p['amount_minor']) + ['currency' => self::CURRENCY];
        }
        return ['payments' => $out];
    }

    /**
     * The Flex ledger, newest first.
     *
     * @param int $userid
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function get_flex_history(int $userid, int $page, int $perpage): array {
        $all = flex::history($userid);
        $out = [];
        foreach (array_slice($all, $page * $perpage, $perpage) as $tx) {
            $out[] = $tx + ['type_label' => self::str('flx_' . $tx['type'])];
        }
        return ['total' => count($all), 'page' => $page, 'perpage' => $perpage, 'history' => $out];
    }

    // ── Admin (local/nit_flex:managepackages) ────────────────────────────────

    /**
     * Every package (any status) with how many times it was bought.
     *
     * @return array
     */
    public static function admin_list_packages(): array {
        global $DB;
        $out = [];
        foreach (packages::all() as $p) {
            $out[] = self::package($p) + ['purchases' => $DB->count_records('nit_package_purchase', ['packageid' => $p['id']])];
        }
        return ['packages' => $out];
    }

    /**
     * Create (no id) or update a package.
     *
     * @param int $id 0 = new
     * @param array $data name_ar, name_en, description_ar, description_en, flex_count, price, expiration_days, active
     * @return array the package
     */
    public static function admin_save_package(int $id, array $data): array {
        $name = mlang::join((string) $data['name_ar'], (string) $data['name_en']);
        $price = money::to_minor((string) $data['price']);
        if ($price === null) {
            throw new \moodle_exception('err_badprice', 'local_nit_finance');
        }
        if ((int) $data['flex_count'] <= 0) {
            throw new flex_exception('err_flexpositive');
        }
        if ((int) $data['expiration_days'] < 0) {
            throw new flex_exception('err_daysnegative');
        }
        $record = (object) [
            'name' => $name,
            'description' => mlang::join((string) $data['description_ar'], (string) $data['description_en']) ?: null,
            'flex_count' => (int) $data['flex_count'],
            'price_minor' => $price,
            'expiration_days' => (int) $data['expiration_days'],
            'status' => $data['active'] ? package::STATUS_ACTIVE : package::STATUS_INACTIVE,
        ];
        if ($id) {
            packages::update($id, $record);
        } else {
            $id = packages::create($record);
        }
        foreach (packages::all() as $p) {
            if ((int) $p['id'] === $id) {
                return self::package($p);
            }
        }
        throw new flex_exception('err_notfound');
    }

    /**
     * Put a package on sale or take it off.
     *
     * @param int $id
     * @param bool $active
     * @return array
     */
    public static function admin_set_package_status(int $id, bool $active): array {
        packages::set_status($id, $active ? package::STATUS_ACTIVE : package::STATUS_INACTIVE);
        return ['id' => $id, 'status' => $active ? package::STATUS_ACTIVE : package::STATUS_INACTIVE];
    }

    /**
     * Delete a package nobody bought.
     *
     * @param int $id
     * @return array
     */
    public static function admin_delete_package(int $id): array {
        packages::delete_unused($id);
        return ['id' => $id, 'deleted' => true];
    }

    /**
     * Give a package to a student (paid outside the site, or charged to the student wallet).
     *
     * @param int $adminid
     * @param int $studentid
     * @param int $packageid
     * @param string $amount pounds, '' = package price
     * @param string $method offline | bank | cash | wallet
     * @param string $reference
     * @return array the purchase
     */
    public static function admin_assign_package(int $adminid, int $studentid, int $packageid, string $amount,
            string $method, string $reference): array {
        $minor = 0;
        if (trim($amount) !== '') {
            $minor = money::to_minor($amount);
            if ($minor === null) {
                throw new \moodle_exception('err_badprice', 'local_nit_finance');
            }
        }
        if (!in_array($method, ['offline', 'bank', 'cash', 'wallet'], true)) {
            $method = 'offline';
        }
        $p = purchase::assign($adminid, $studentid, $packageid, $minor, $method, $reference);
        return self::purchase($p, self::names());
    }

    /**
     * Every student's packages, newest first.
     *
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function admin_list_purchases(int $page, int $perpage): array {
        global $DB;
        $names = self::names();
        $service = new \local_nit_flex\service\purchase_service();
        $total = $DB->count_records('nit_package_purchase');
        $out = [];
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        foreach ($DB->get_records_sql("SELECT pu.*, $fields, u.email
                                         FROM {nit_package_purchase} pu
                                         JOIN {user} u ON u.id = pu.userid
                                     ORDER BY pu.timecreated DESC, pu.id DESC", [], $page * $perpage, $perpage) as $r) {
            $entity = new \local_nit_flex\entity\package_purchase(0, (object) array_intersect_key((array) $r,
                array_flip(['id', 'packageid', 'userid', 'price_paid_minor', 'flex_count', 'expiration_days', 'status',
                    'source', 'remaining_flex', 'reserved_flex', 'consumed_flex', 'expires_at', 'timeactivated',
                    'expiry_notified', 'timecreated', 'timemodified', 'usermodified'])));
            $status = $service->effective_status($entity);
            $out[] = [
                'id' => (int) $r->id,
                'userid' => (int) $r->userid,
                'student_name' => fullname($r),
                'student_email' => (string) $r->email,
                'packageid' => (int) $r->packageid,
                'name' => format_string($names[$r->packageid] ?? ''),
                'total_flex' => (int) $r->flex_count,
                'remaining_flex' => (int) $r->remaining_flex,
                'reserved_flex' => (int) $r->reserved_flex,
                'consumed_flex' => (int) $r->consumed_flex,
                'status' => $status,
                'status_label' => self::str('pstat_' . $status),
                'can_unassign' => $r->status === 'active',
                'source' => (string) $r->source,
                'expires_at' => (int) $r->expires_at,
                'timecreated' => (int) $r->timecreated,
            ] + self::money('price_paid', (int) $r->price_paid_minor) + ['currency' => self::CURRENCY];
        }
        return ['total' => $total, 'page' => $page, 'perpage' => $perpage, 'purchases' => $out];
    }

    /**
     * Cancel a student's package, optionally refunding the unused Flex to their wallet.
     *
     * @param int $adminid
     * @param int $purchaseid
     * @param bool $refund
     * @return array
     */
    public static function admin_unassign_package(int $adminid, int $purchaseid, bool $refund): array {
        $amount = purchase::unassign($purchaseid, $refund, $adminid);
        return ['purchaseid' => $purchaseid, 'cancelled' => true]
            + self::money('refunded', $amount) + ['currency' => self::CURRENCY];
    }
}

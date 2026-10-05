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

namespace local_nit_flex\service;

use local_nit_core\base\service;
use local_nit_finance\local\wallets;
use local_nit_flex\entity\package;
use local_nit_flex\entity\package_purchase;
use local_nit_flex\entity\payment;
use local_nit_flex\entity\flex_tx;
use local_nit_flex\exception\flex_exception;

/**
 * Buying and holding Flex packages. All money is integer minor units.
 *
 * Ways a student gets a package:
 *  - from the student wallet ({@see self::buy_with_wallet()}), coupons/offers applied;
 *  - online through the payment gateway ({@see self::fulfil_from_gateway()}, called by
 *    local_payments once the payment succeeded);
 *  - assigned by an admin after an offline payment, or charged to the student wallet
 *    ({@see self::assign()}).
 *
 * The amount actually paid is stored on the purchase, so one Flex is worth
 * price_paid / flex_count when a lesson uses it (see local_nit_finance earnings).
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purchase_service extends service {

    /** Wallet ledger item type for package purchases. */
    const ITEMTYPE = 'package';

    /**
     * The student's current active package entity, or null. Active = status active, has Flex,
     * and not expired.
     *
     * @param int $userid
     * @return package_purchase|null
     */
    public function active_entity(int $userid): ?package_purchase {
        $rows = package_purchase::get_records(
            ['userid' => $userid, 'status' => package_purchase::STATUS_ACTIVE], 'timecreated', 'DESC');
        foreach ($rows as $p) {
            if ($this->effective_status($p) === package_purchase::STATUS_ACTIVE) {
                return $p;
            }
        }
        return null;
    }

    /**
     * The student's active package as a summary array, or null.
     *
     * @param int $userid
     * @return array|null
     */
    public function active(int $userid): ?array {
        $p = $this->active_entity($userid);
        return $p ? $this->summarise($p) : null;
    }

    /**
     * The price a student pays for a package, after automatic offers and an optional coupon.
     *
     * @param int $userid
     * @param int $packageid
     * @param string $couponcode
     * @return array{original_minor:int, final_minor:int, discount_minor:int, discount:?array}
     */
    public function quote(int $userid, int $packageid, string $couponcode = ''): array {
        $package = $this->available_package_or_fail($packageid);
        $price = (int) $package->get('price_minor');
        $out = ['original_minor' => $price, 'final_minor' => $price, 'discount_minor' => 0, 'discount' => null];
        if (!class_exists('\local_nit_commerce\discount_manager')) {
            return $out;
        }
        $resolved = \local_nit_commerce\discount_manager::resolve(self::ITEMTYPE, $packageid, $userid,
            trim($couponcode), $price / 100);
        $final = (int) round(((float) $resolved['final']) * 100);
        $out['final_minor'] = max(0, min($price, $final));
        $out['discount_minor'] = $price - $out['final_minor'];
        $out['discount'] = $out['discount_minor'] > 0 ? $resolved : null;
        return $out;
    }

    /**
     * Buy a package with the student wallet. Coupons and offers are applied; the coupon is
     * reserved in the same transaction as the money, so a used-up coupon cancels the purchase.
     *
     * @param int $userid
     * @param int $packageid
     * @param string $couponcode
     * @return array purchase summary
     */
    public function buy_with_wallet(int $userid, int $packageid, string $couponcode = ''): array {
        return wallets::locked('student_' . $userid, function () use ($userid, $packageid, $couponcode) {
            global $DB;
            $package = $this->available_package_or_fail($packageid);
            if ($this->active_entity($userid)) {
                throw new flex_exception('err_alreadyhaspackage');
            }
            $quote = $this->quote($userid, $packageid, $couponcode);
            $transaction = $DB->start_delegated_transaction();
            try {
                $purchase = $this->create_purchase($userid, $package, $quote['final_minor'], 'online', time());
                $this->record_payment($userid, $purchase, $package, $quote['final_minor'], 'wallet', '');
                (new flex_service())->log_grant($userid, (int) $purchase->get('id'),
                    (int) $purchase->get('remaining_flex'), $userid, flex_tx::TYPE_PURCHASE,
                    'Package purchased: ' . $package->get('name'));
                if ($quote['final_minor'] > 0) {
                    wallets::move(wallets::STUDENT, $userid, -$quote['final_minor'], wallets::KIND_PURCHASE, [
                        'itemtype' => self::ITEMTYPE, 'itemid' => $packageid,
                        'purchaseid' => (int) $purchase->get('id'), 'note' => $package->get('name'),
                    ]);
                }
                if ($quote['discount']) {
                    \local_nit_commerce\discount_manager::reserve_usage($quote['discount'], $userid, 0,
                        self::ITEMTYPE, $packageid);
                }
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
            return $this->summarise($purchase);
        });
    }

    /**
     * Fulfil a package paid online. Safe to call from both the gateway webhook and the
     * browser redirect: one order gives one purchase. If the student got another package
     * meanwhile, the money goes to their wallet instead, so a paid order is never lost.
     *
     * @param int $userid
     * @param int $packageid
     * @param int $amountminor what the gateway charged
     * @param string $orderid gateway order id
     * @param int $transactionid local_payments transaction id
     * @return array|null the purchase summary, or null when nothing new was granted
     */
    public function fulfil_from_gateway(int $userid, int $packageid, int $amountminor, string $orderid,
            int $transactionid): ?array {
        return wallets::locked('student_' . $userid, function () use ($userid, $packageid, $amountminor,
                $orderid, $transactionid) {
            global $DB;
            if ($DB->record_exists('nit_payment', ['userid' => $userid, 'reference' => $orderid])) {
                return null;
            }
            $package = package::get_record(['id' => $packageid]);
            if (!$package || $this->active_entity($userid)) {
                // Paid, but the package cannot be granted now: keep the money for the student.
                wallets::topup_from_payment($userid, $amountminor, $transactionid, $orderid);
                return null;
            }
            $transaction = $DB->start_delegated_transaction();
            $purchase = $this->create_purchase($userid, $package, max(0, $amountminor), 'online', time());
            $this->record_payment($userid, $purchase, $package, max(0, $amountminor), 'online', $orderid);
            (new flex_service())->log_grant($userid, (int) $purchase->get('id'),
                (int) $purchase->get('remaining_flex'), $userid, flex_tx::TYPE_PURCHASE,
                'Package purchased online: ' . $package->get('name'));
            $transaction->allow_commit();
            return $this->summarise($purchase);
        });
    }

    /**
     * Grant a package as paid, with no wallet or gateway involved (tests, CLI).
     *
     * @param int $userid
     * @param int $packageid
     * @param string $method payment method label
     * @param string $reference external payment reference
     * @param int|null $priceminor amount paid (null = package price)
     * @return array purchase summary
     */
    public function fulfil(int $userid, int $packageid, string $method = 'online', string $reference = '',
            ?int $priceminor = null): array {
        global $DB;
        $package = $this->available_package_or_fail($packageid);
        if ($this->active_entity($userid)) {
            throw new flex_exception('err_alreadyhaspackage');
        }
        $price = $priceminor ?? (int) $package->get('price_minor');
        $transaction = $DB->start_delegated_transaction();
        $purchase = $this->create_purchase($userid, $package, $price,
            $method === 'admin_assigned' ? 'admin_assigned' : 'online', time());
        $this->record_payment($userid, $purchase, $package, $price, $method, $reference);
        (new flex_service())->log_grant($userid, (int) $purchase->get('id'),
            (int) $purchase->get('remaining_flex'), $userid, flex_tx::TYPE_PURCHASE,
            'Package purchased: ' . $package->get('name'));
        $transaction->allow_commit();
        return $this->summarise($purchase);
    }

    /**
     * Admin assigns a package to a student. With method "wallet" the amount is taken from
     * the student wallet; any other method records a payment made outside the site.
     *
     * @param int $adminid
     * @param int $studentid
     * @param int $packageid
     * @param int $amountminor amount paid (defaults to the package price when <= 0)
     * @param string $method offline | bank | cash | wallet
     * @param string $reference
     * @return array
     */
    public function assign(int $adminid, int $studentid, int $packageid, int $amountminor,
            string $method = 'offline', string $reference = ''): array {
        if (!\core_user::get_user($studentid, '*', IGNORE_MISSING)) {
            throw new flex_exception('err_studentnotfound');
        }
        return wallets::locked('student_' . $studentid, function () use ($adminid, $studentid, $packageid,
                $amountminor, $method, $reference) {
            global $DB;
            $package = $this->available_package_or_fail($packageid);
            if ($this->active_entity($studentid)) {
                throw new flex_exception('err_studenthaspackage');
            }
            $price = $amountminor > 0 ? $amountminor : (int) $package->get('price_minor');
            $method = $method !== '' ? $method : 'offline';
            $transaction = $DB->start_delegated_transaction();
            try {
                $purchase = $this->create_purchase($studentid, $package, $price, 'admin_assigned', time());
                $this->record_payment($studentid, $purchase, $package, $price, $method, $reference);
                (new flex_service())->log_grant($studentid, (int) $purchase->get('id'),
                    (int) $purchase->get('remaining_flex'), $adminid, flex_tx::TYPE_ASSIGN,
                    'Package assigned by admin: ' . $package->get('name'));
                if ($method === 'wallet' && $price > 0) {
                    wallets::move(wallets::STUDENT, $studentid, -$price, wallets::KIND_PURCHASE, [
                        'itemtype' => self::ITEMTYPE, 'itemid' => $packageid,
                        'purchaseid' => (int) $purchase->get('id'), 'note' => $package->get('name'),
                    ]);
                }
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
            return $this->summarise($purchase);
        });
    }

    /**
     * The student's packages, active first.
     *
     * @param int $userid
     * @return array
     */
    public function my_packages(int $userid): array {
        $rows = package_purchase::get_records(['userid' => $userid], 'timecreated', 'DESC');
        $out = array_map([$this, 'summarise'], array_values($rows));
        usort($out, static function ($a, $b) {
            if (($a['status'] === 'active') !== ($b['status'] === 'active')) {
                return $a['status'] === 'active' ? -1 : 1;
            }
            return $b['timeactivated'] - $a['timeactivated'];
        });
        return $out;
    }

    /**
     * The student's payment history, most recent first.
     *
     * @param int $userid
     * @return array
     */
    public function payment_history(int $userid): array {
        $rows = payment::get_records(['userid' => $userid], 'timecreated', 'DESC');
        $out = [];
        foreach ($rows as $p) {
            $out[] = [
                'id'             => (int) $p->get('id'),
                'packageid'      => (int) $p->get('packageid'),
                'amount_minor'   => (int) $p->get('amount_minor'),
                'method'         => $p->get('method'),
                'reference'      => $p->get('reference'),
                'transaction_no' => $p->get('transaction_no'),
                'status'         => $p->get('status'),
                'timecreated'    => (int) $p->get('timecreated'),
            ];
        }
        return $out;
    }

    /**
     * Money totals the platform wallet needs: total successful payments and the value of all
     * unconsumed (remaining + reserved) Flex of live purchases. Minor units.
     *
     * @return array{payments_minor:int, undistributed_minor:int}
     */
    public function money_totals(): array {
        global $DB;
        $payments = (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(amount_minor),0) FROM {nit_payment} WHERE status = :st",
            ['st' => payment::STATUS_SUCCESS]);

        $undistributed = 0;
        $purchases = $DB->get_records_select('nit_package_purchase', 'flex_count > 0', [],
            '', 'id, price_paid_minor, flex_count, remaining_flex, reserved_flex');
        foreach ($purchases as $p) {
            $value = (int) round((int) $p->price_paid_minor / (int) $p->flex_count);
            $undistributed += ((int) $p->remaining_flex + (int) $p->reserved_flex) * $value;
        }
        return ['payments_minor' => $payments, 'undistributed_minor' => $undistributed];
    }

    /**
     * Admin unassigns (cancels) a purchase. With $refund the value of the unused Flex goes
     * back to the student wallet (used Flex was already paid out to teachers).
     *
     * @param int $purchaseid
     * @param bool $refund
     * @param int $adminid
     * @return int the refunded amount, minor units
     */
    public function unassign(int $purchaseid, bool $refund, int $adminid): int {
        $purchase = package_purchase::get_record(['id' => $purchaseid]);
        if (!$purchase) {
            throw new flex_exception('err_notfound');
        }
        $userid = (int) $purchase->get('userid');
        return wallets::locked('student_' . $userid, function () use ($purchaseid, $refund, $adminid, $userid) {
            global $DB;
            $purchase = package_purchase::get_record(['id' => $purchaseid]);
            $unused = (int) $purchase->get('remaining_flex');
            $amount = $refund ? $unused * (new flex_service())->value_for_purchase($purchaseid) : 0;
            $transaction = $DB->start_delegated_transaction();
            try {
                (new flex_service())->log_revoke($userid, $purchaseid, $adminid, 'Unassigned by admin');
                $purchase = package_purchase::get_record(['id' => $purchaseid]);
                $purchase->set('status', package_purchase::STATUS_CANCELLED);
                $purchase->set('expires_at', time());
                $purchase->update();
                if ($amount > 0) {
                    (new payment(0, (object) [
                        'userid'         => $userid,
                        'purchaseid'     => $purchaseid,
                        'packageid'      => (int) $purchase->get('packageid'),
                        'amount_minor'   => -$amount,
                        'method'         => 'refund',
                        'reference'      => 'Refund to wallet',
                        'transaction_no' => self::generate_txn(),
                        'status'         => payment::STATUS_SUCCESS,
                    ]))->create();
                    wallets::move(wallets::STUDENT, $userid, $amount, wallets::KIND_ADJUSTMENT, [
                        'itemtype' => self::ITEMTYPE, 'itemid' => (int) $purchase->get('packageid'),
                        'purchaseid' => $purchaseid, 'note' => get_string('refundnote', 'local_nit_flex'),
                    ]);
                }
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
            return $amount;
        });
    }

    /**
     * Close purchases whose time ran out: unused Flex expires and its value becomes platform
     * income. Reserved Flex is left alone (those lessons still happen).
     *
     * @param int $now
     * @return int how many purchases were closed
     */
    public function expire_due(int $now): int {
        global $DB;
        $rows = $DB->get_records_select('nit_package_purchase',
            'status = :st AND expires_at > 0 AND expires_at < :now', ['st' => package_purchase::STATUS_ACTIVE, 'now' => $now]);
        $count = 0;
        foreach ($rows as $row) {
            $this->expire_one((int) $row->id);
            $count++;
        }
        return $count;
    }

    /**
     * Zero the remaining Flex of a purchase that ended (expired or cancelled) and give its value
     * to the platform. Used by the expiry task and when a Flex comes back to an ended purchase.
     *
     * @param int $purchaseid
     * @return void
     */
    public function expire_one(int $purchaseid): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $purchase = package_purchase::get_record(['id' => $purchaseid]);
        $remaining = (int) $purchase->get('remaining_flex');
        if ($remaining > 0) {
            (new flex_tx(0, (object) [
                'userid'         => (int) $purchase->get('userid'),
                'purchaseid'     => $purchaseid,
                'lessonid'       => 0,
                'type'           => flex_tx::TYPE_EXPIRE,
                'amount'         => -$remaining,
                'balance_before' => $remaining,
                'balance_after'  => 0,
                'performedby'    => 0,
                'reason'         => 'Package expired',
            ]))->create();
            $purchase->set('remaining_flex', 0);
        }
        if ($purchase->get('status') === package_purchase::STATUS_ACTIVE) {
            $purchase->set('status', package_purchase::STATUS_EXPIRED);
        }
        $purchase->update();
        if ($remaining > 0) {
            \local_nit_finance\api\wallet::expired_flex((int) $purchase->get('userid'), $purchaseid,
                $remaining * (new flex_service())->value_for_purchase($purchaseid));
        }
        $transaction->allow_commit();
    }

    /**
     * Active purchases ending within $days that still hold Flex and were not reminded yet.
     *
     * @param int $now
     * @param int $days
     * @return \stdClass[]
     */
    public function due_for_reminder(int $now, int $days): array {
        global $DB;
        if ($days <= 0) {
            return [];
        }
        return array_values($DB->get_records_select('nit_package_purchase',
            'status = :st AND expires_at > :now AND expires_at <= :until AND remaining_flex > 0 AND expiry_notified = 0',
            ['st' => package_purchase::STATUS_ACTIVE, 'now' => $now, 'until' => $now + $days * DAYSECS]));
    }

    /**
     * Compute the real status of a purchase at read time.
     *
     * @param package_purchase $purchase
     * @return string
     */
    public function effective_status(package_purchase $purchase): string {
        if ($purchase->get('status') === package_purchase::STATUS_ACTIVE) {
            if ((int) $purchase->get('remaining_flex') <= 0) {
                return package_purchase::STATUS_FULLY_USED;
            }
            $expires = (int) $purchase->get('expires_at');
            if ($expires > 0 && time() > $expires) {
                return package_purchase::STATUS_EXPIRED;
            }
        }
        return $purchase->get('status');
    }

    /**
     * Create the purchase record (snapshot + opening balance).
     *
     * @param int $userid
     * @param package $package
     * @param int $priceminor what was paid
     * @param string $source
     * @param int $now
     * @return package_purchase
     */
    private function create_purchase(int $userid, package $package, int $priceminor, string $source,
            int $now): package_purchase {
        $expirationdays = (int) $package->get('expiration_days');
        $purchase = new package_purchase(0, (object) [
            'packageid'        => (int) $package->get('id'),
            'userid'           => $userid,
            'price_paid_minor' => $priceminor,
            'flex_count'       => (int) $package->get('flex_count'),
            'expiration_days'  => $expirationdays,
            'status'           => package_purchase::STATUS_ACTIVE,
            'source'           => $source,
            'remaining_flex'   => (int) $package->get('flex_count'),
            'expires_at'       => $expirationdays > 0 ? $now + ($expirationdays * DAYSECS) : 0,
            'timeactivated'    => $now,
        ]);
        $purchase->create();
        return $purchase;
    }

    /**
     * Record a successful payment for a purchase.
     *
     * @param int $userid
     * @param package_purchase $purchase
     * @param package $package
     * @param int $amountminor
     * @param string $method
     * @param string $reference
     * @return void
     */
    private function record_payment(int $userid, package_purchase $purchase, package $package,
            int $amountminor, string $method, string $reference): void {
        (new payment(0, (object) [
            'userid'         => $userid,
            'purchaseid'     => (int) $purchase->get('id'),
            'packageid'      => (int) $package->get('id'),
            'amount_minor'   => $amountminor,
            'method'         => $method,
            'reference'      => $reference !== '' ? $reference : null,
            'transaction_no' => self::generate_txn(),
            'status'         => payment::STATUS_SUCCESS,
        ]))->create();
    }

    /**
     * Load an active (purchasable) package or throw.
     *
     * @param int $packageid
     * @return package
     */
    private function available_package_or_fail(int $packageid): package {
        $package = package::get_record(['id' => $packageid]);
        if (!$package) {
            throw new flex_exception('err_notfound');
        }
        if ($package->get('status') !== package::STATUS_ACTIVE) {
            throw new flex_exception('err_packagenotavailable');
        }
        return $package;
    }

    /**
     * Shape a purchase entity as a summary array.
     *
     * @param package_purchase $p
     * @return array
     */
    private function summarise(package_purchase $p): array {
        $total = (int) $p->get('flex_count');
        return [
            'id'               => (int) $p->get('id'),
            'packageid'        => (int) $p->get('packageid'),
            'userid'           => (int) $p->get('userid'),
            'total_flex'       => $total,
            'remaining_flex'   => (int) $p->get('remaining_flex'),
            'reserved_flex'    => (int) $p->get('reserved_flex'),
            'consumed_flex'    => (int) $p->get('consumed_flex'),
            'used_flex'        => $total - (int) $p->get('remaining_flex'),
            'price_paid_minor' => (int) $p->get('price_paid_minor'),
            'status'           => $this->effective_status($p),
            'source'           => $p->get('source'),
            'timeactivated'    => (int) $p->get('timeactivated'),
            'expires_at'       => (int) $p->get('expires_at'),
        ];
    }

    /**
     * Generate a unique-ish transaction number.
     *
     * @return string
     */
    private static function generate_txn(): string {
        return 'TXN' . strtoupper(substr(md5(uniqid('', true)), 0, 14));
    }
}

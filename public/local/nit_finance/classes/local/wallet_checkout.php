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

namespace local_nit_finance\local;

use local_nit_finance\exception\finance_exception;

/**
 * Paying from the student wallet for what is otherwise sold online: a whole
 * course (price in local_payments) and a subscription plan (local_nit_subscriptions).
 * Single lessons are in {@see purchases}, lesson packages in local_nit_flex.
 *
 * The same offers and coupons as the online checkout apply (local_nit_commerce).
 * Money, purchase and coupon use are written in one transaction under the
 * student's wallet lock; enrolment and notifications follow the commit.
 *
 *  - course: recorded like a code-unlocked course ({@see purchases::grant()}),
 *    so the teacher gets their share and the student is enrolled;
 *  - subscription: a nit_sub_purchase with source "wallet"; the whole amount
 *    goes to the platform wallet (a plan covers many teachers' courses).
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wallet_checkout {

    /** Wallet amounts are Egyptian pounds. */
    public const CURRENCY = 'EGP';

    /** Item type of a subscription plan, as local_nit_commerce and the wallet ledger name it. */
    public const SUBSCRIPTION = 'subscription';

    /**
     * What the student would pay for a course from the wallet.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $couponcode
     * @return array{original_minor:int, final_minor:int, discount_minor:int, discount:?array}
     */
    public static function course_quote(int $userid, int $courseid, string $couponcode = ''): array {
        if (!class_exists('\local_payments\price_resolver') || !\local_payments\price_resolver::has_pricing($courseid)) {
            throw new finance_exception('err_notforsale_course');
        }
        try {
            $pricing = \local_payments\price_resolver::resolve($courseid, $userid);
        } catch (\moodle_exception $e) {
            throw new finance_exception('err_notforsale_course');
        }
        if (strtoupper((string) $pricing->currency) !== self::CURRENCY) {
            // The student is shown a price in another currency: the wallet cannot pay it.
            throw new finance_exception('err_walletcurrency');
        }
        return self::quote('course', $courseid, $userid, (float) $pricing->price, $couponcode);
    }

    /**
     * Whether the wallet can pay for this course for this user (price in pounds).
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function course_payable(int $userid, int $courseid): bool {
        try {
            self::course_quote($userid, $courseid);
            return true;
        } catch (\moodle_exception $e) {
            return false;
        }
    }

    /**
     * Does the student already have the whole course? A student who got into
     * the course only by buying single lessons does not, and may buy it.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function has_course(int $userid, int $courseid): bool {
        if (access::owns_course($userid, $courseid)) {
            return true;
        }
        if (!is_enrolled(\context_course::instance($courseid), $userid, '', true)) {
            return false;
        }
        return !access::course_state($userid, $courseid)['lessononly'];
    }

    /**
     * Buy a whole course with the student wallet.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $couponcode
     * @return \stdClass the nit_purchase row
     */
    public static function buy_course(int $userid, int $courseid, string $couponcode = ''): \stdClass {
        $purchase = wallets::locked('student_' . $userid, function () use ($userid, $courseid, $couponcode) {
            global $DB;
            $item = catalog::item(catalog::COURSE, $courseid);
            if (self::has_course($userid, $courseid)) {
                throw new finance_exception('err_alreadyowned');
            }
            $quote = self::course_quote($userid, $courseid, $couponcode);
            $transaction = $DB->start_delegated_transaction();
            try {
                $purchase = purchases::grant($userid, $item, purchases::METHOD_WALLET, $quote['final_minor']);
                if ($quote['final_minor'] > 0) {
                    wallets::move(wallets::STUDENT, $userid, -$quote['final_minor'], wallets::KIND_PURCHASE,
                        ['itemtype' => $item->type, 'itemid' => $item->id, 'purchaseid' => $purchase->id,
                            'note' => $item->name]);
                }
                self::use_discount($quote, $userid, 'course', $courseid);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
            return $purchase;
        });
        purchases::enrol_after_payment($userid, $courseid);
        return $purchase;
    }

    /**
     * The plan, when it can be bought now.
     *
     * @param int $subscriptionid
     * @return \stdClass nit_subscription row
     */
    private static function plan(int $subscriptionid): \stdClass {
        global $DB;
        if (!class_exists('\local_nit_subscriptions\subscription_purchase_manager')) {
            throw new finance_exception('err_itemnotfound');
        }
        $plan = $DB->get_record('nit_subscription', ['id' => $subscriptionid]);
        if (!$plan || $plan->status !== 'active') {
            throw new finance_exception('err_itemnotfound');
        }
        return $plan;
    }

    /**
     * What the student would pay for a subscription plan from the wallet.
     *
     * @param int $userid
     * @param int $subscriptionid
     * @param string $couponcode
     * @return array{original_minor:int, final_minor:int, discount_minor:int, discount:?array}
     */
    public static function subscription_quote(int $userid, int $subscriptionid, string $couponcode = ''): array {
        $plan = self::plan($subscriptionid);
        return self::quote(self::SUBSCRIPTION, $subscriptionid, $userid, (float) $plan->price, $couponcode);
    }

    /**
     * Buy a subscription plan (normal, one person) with the student wallet.
     *
     * @param int $userid
     * @param int $subscriptionid
     * @param string $couponcode
     * @return \stdClass the nit_sub_purchase row
     */
    public static function buy_subscription(int $userid, int $subscriptionid, string $couponcode = ''): \stdClass {
        $manager = '\local_nit_subscriptions\subscription_purchase_manager';
        $purchase = wallets::locked('student_' . $userid, function () use ($userid, $subscriptionid, $couponcode, $manager) {
            global $DB;
            $plan = self::plan($subscriptionid);
            if ($manager::has_active_normal($userid)) {
                throw new finance_exception('err_subactive');
            }
            $quote = self::subscription_quote($userid, $subscriptionid, $couponcode);
            $reference = 'wallet-' . $userid . '-' . time() . '-' . random_string(6);
            $transaction = $DB->start_delegated_transaction();
            try {
                $purchase = $manager::create_wallet_purchase($userid, $subscriptionid, $quote['final_minor'] / 100,
                    $reference);
                if ($quote['final_minor'] > 0) {
                    $ref = ['itemtype' => self::SUBSCRIPTION, 'itemid' => $subscriptionid,
                        'purchaseid' => (int) $purchase->id, 'note' => format_string($plan->name)];
                    wallets::move(wallets::STUDENT, $userid, -$quote['final_minor'], wallets::KIND_PURCHASE, $ref);
                    wallets::move(wallets::PLATFORM, 0, $quote['final_minor'], wallets::KIND_EARNING, $ref);
                }
                self::use_discount($quote, $userid, self::SUBSCRIPTION, $subscriptionid);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
            return $purchase;
        });
        try {
            $manager::notify_started($purchase);
        } catch (\Throwable $e) {
            debugging('local_nit_finance: subscription notification failed: ' . $e->getMessage(), DEBUG_NORMAL);
        }
        return $purchase;
    }

    /**
     * Price after the automatic offer and the coupon, in minor units.
     *
     * @param string $itemtype course | subscription
     * @param int $itemid
     * @param int $userid
     * @param float $base price in pounds
     * @param string $couponcode
     * @return array{original_minor:int, final_minor:int, discount_minor:int, discount:?array}
     */
    private static function quote(string $itemtype, int $itemid, int $userid, float $base, string $couponcode): array {
        $price = (int) round($base * 100);
        $out = ['original_minor' => $price, 'final_minor' => $price, 'discount_minor' => 0, 'discount' => null];
        if (!class_exists('\local_nit_commerce\discount_manager')) {
            return $out;
        }
        // A coupon that does not apply throws, so the student is never charged a price they did not expect.
        $resolved = \local_nit_commerce\discount_manager::resolve($itemtype, $itemid, $userid, trim($couponcode), $base);
        $final = (int) round(((float) $resolved['final']) * 100);
        $out['final_minor'] = max(0, min($price, $final));
        $out['discount_minor'] = $price - $out['final_minor'];
        $out['discount'] = $out['discount_minor'] > 0 ? $resolved : null;
        return $out;
    }

    /**
     * Record the offer/coupon use with the payment (a used-up coupon cancels the purchase).
     *
     * @param array $quote
     * @param int $userid
     * @param string $itemtype
     * @param int $itemid
     * @return void
     */
    private static function use_discount(array $quote, int $userid, string $itemtype, int $itemid): void {
        if ($quote['discount'] && class_exists('\local_nit_commerce\discount_manager')) {
            \local_nit_commerce\discount_manager::reserve_usage($quote['discount'], $userid, 0, $itemtype, $itemid);
        }
    }
}

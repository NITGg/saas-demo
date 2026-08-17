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
 * Shared discount engine for coupons and offers. Ported from local_academy\discount_manager.
 *
 * Item labels/prices resolve against the new platform's tables (nit_package, nit_subscription, course)
 * with guards so a missing plugin degrades gracefully rather than erroring. The checkout resolution
 * methods are retained for when the purchase flow is wired; the admin pages only use the scope +
 * label helpers.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_commerce;

defined('MOODLE_INTERNAL') || die();

/**
 * Discount maths, scope matching and checkout resolution shared by coupons + offers.
 */
class discount_manager {

    /** @var string Course item type. */
    const TYPE_COURSE       = 'course';
    /** @var string Package item type. */
    const TYPE_PACKAGE      = 'package';
    /** @var string Subscription item type. */
    const TYPE_SUBSCRIPTION = 'subscription';
    /** @var string Program item type. */
    const TYPE_PROGRAM      = 'program';

    /** @var string Percentage discount kind. */
    const DISCOUNT_PERCENT = 'percent';
    /** @var string Fixed-amount discount kind. */
    const DISCOUNT_FIXED   = 'fixed';

    /**
     * The valid item types.
     *
     * @return string[]
     */
    public static function item_types() {
        return array(self::TYPE_COURSE, self::TYPE_PACKAGE, self::TYPE_SUBSCRIPTION, self::TYPE_PROGRAM);
    }

    /**
     * Validate an item type or throw.
     *
     * @param string $type
     * @return string
     */
    public static function normalize_item_type($type) {
        $type = strtolower(trim((string)$type));
        if (!in_array($type, self::item_types(), true)) {
            throw new \moodle_exception('err_itemtype', 'local_nit_commerce');
        }
        return $type;
    }

    /**
     * Validate a discount type or throw.
     *
     * @param string $type
     * @return string
     */
    public static function normalize_discount_type($type) {
        $type = strtolower(trim((string)$type));
        if (!in_array($type, array(self::DISCOUNT_PERCENT, self::DISCOUNT_FIXED), true)) {
            throw new \moodle_exception('err_discounttype', 'local_nit_commerce');
        }
        return $type;
    }

    // ── Pricing ──

    /**
     * The current base price of a sellable item, before any coupon/offer. Best-effort; 0 if unknown.
     *
     * @param string $itemtype course | package | subscription | program
     * @param int $itemid
     * @return float
     */
    public static function price_of($itemtype, $itemid) {
        global $DB;
        $itemtype = self::normalize_item_type($itemtype);
        $itemid = (int)$itemid;
        if ($itemtype === self::TYPE_PACKAGE && $DB->get_manager()->table_exists('nit_package')) {
            $minor = (int)$DB->get_field('nit_package', 'price_minor', array('id' => $itemid));
            return $minor / 100;
        }
        if ($itemtype === self::TYPE_SUBSCRIPTION && $DB->get_manager()->table_exists('nit_subscription')) {
            return (float)$DB->get_field('nit_subscription', 'price', array('id' => $itemid));
        }
        return 0.0;
    }

    /**
     * The raw discount amount for a (type, value) pair against a base, capped by an optional max and by
     * the base itself.
     *
     * @param string $discounttype percent | fixed
     * @param float $value
     * @param float|null $max cap on the applied discount (null = no cap)
     * @param float $base price the discount is applied to
     * @return float
     */
    public static function discount_amount($discounttype, $value, $max, $base) {
        $base = max(0.0, (float)$base);
        $value = max(0.0, (float)$value);
        if ($discounttype === self::DISCOUNT_PERCENT) {
            $amount = $base * $value / 100.0;
        } else {
            $amount = $value;
        }
        if ($max !== null && $max !== '' && (float)$max > 0) {
            $amount = min($amount, (float)$max);
        }
        $amount = min($amount, $base);
        return round($amount, 2);
    }

    // ── Scope matching ──

    /**
     * Whether a set of scope rows targets a given item. A row with item_id 0 means "all of that type".
     *
     * @param array $items rows each with ->item_type and ->item_id
     * @param string $itemtype
     * @param int $itemid
     * @return bool
     */
    public static function scope_matches(array $items, $itemtype, $itemid) {
        $itemid = (int)$itemid;
        foreach ($items as $row) {
            if ($row->item_type === $itemtype && ((int)$row->item_id === 0 || (int)$row->item_id === $itemid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Resolve a stored {mlang en}…{mlang}{mlang ar}…{mlang} value to the current language.
     *
     * The {mlang} (filter_multilang2) filter is not installed here, so we resolve it ourselves for
     * server-rendered / API output. Values without {mlang} markup are returned unchanged.
     *
     * @param string $text
     * @return string
     */
    public static function resolve_mlang($text) {
        $text = (string)$text;
        if (strpos($text, '{mlang') === false) {
            return $text;
        }
        $blocks = array();
        if (preg_match_all('/\{\s*mlang\s+([a-zA-Z0-9_-]+)\s*\}(.*?)\{\s*mlang\s*\}/s', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $blocks[strtolower($m[1])] = trim($m[2]);
            }
        }
        if (!$blocks) {
            return $text;
        }
        $lang = strtolower(current_language());
        foreach ($blocks as $code => $val) {
            if ($code === $lang || strpos($code, $lang) === 0 || strpos($lang, $code) === 0) {
                return $val;
            }
        }
        if (isset($blocks['other'])) { return $blocks['other']; }
        if (isset($blocks['en'])) { return $blocks['en']; }
        return reset($blocks);
    }

    /**
     * A human-readable label for a scope row, for the admin + student "applicable items" displays.
     *
     * @param string $itemtype
     * @param int $itemid 0 = all of the type
     * @return string
     */
    public static function item_label($itemtype, $itemid) {
        global $DB;
        $itemid = (int)$itemid;
        if ($itemid === 0) {
            return get_string('scope_all_' . $itemtype, 'local_nit_commerce');
        }
        if ($itemtype === self::TYPE_COURSE) {
            $name = $DB->get_field('course', 'fullname', array('id' => $itemid));
            return $name !== false ? format_string(self::resolve_mlang($name)) : ('#' . $itemid);
        }
        if ($itemtype === self::TYPE_PACKAGE) {
            $name = $DB->get_manager()->table_exists('nit_package')
                ? $DB->get_field('nit_package', 'name', array('id' => $itemid)) : false;
        } else if ($itemtype === self::TYPE_SUBSCRIPTION) {
            $name = $DB->get_manager()->table_exists('nit_subscription')
                ? $DB->get_field('nit_subscription', 'name', array('id' => $itemid)) : false;
        } else {
            // Programs are not yet available in the new platform.
            $name = false;
        }
        return $name !== false ? format_string(self::resolve_mlang($name)) : ('#' . $itemid);
    }

    // ── Resolution (used by checkout — retained for when the purchase flow is wired) ──

    /**
     * The single best automatic offer for an item, or null.
     *
     * @param string $itemtype
     * @param int $itemid
     * @param float $base base price
     * @param int|null $now timestamp (defaults to time())
     * @return object|null
     */
    public static function best_offer($itemtype, $itemid, $base, $now = null) {
        global $DB;
        $now = $now ?? time();
        $offers = $DB->get_records('nit_offer', array('status' => 'active'));
        $best = null;
        foreach ($offers as $offer) {
            if ($offer->startdate > 0 && $now < $offer->startdate) { continue; }
            if ($offer->enddate > 0 && $now > $offer->enddate) { continue; }
            $items = $DB->get_records('nit_offer_item', array('offerid' => $offer->id));
            if (!self::scope_matches($items, $itemtype, $itemid)) { continue; }
            $discount = self::discount_amount($offer->discount_type, $offer->discount_value, null, $base);
            if ($best === null || $discount > $best->discount) {
                $best = (object) array(
                    'id'             => (int)$offer->id,
                    // Resolve {mlang} to the current language for display (the {mlang} filter is not
                    // installed here), so the offer name shows translated in the checkout modal.
                    'name'           => format_string(self::resolve_mlang($offer->name)),
                    'discount_type'  => $offer->discount_type,
                    'discount_value' => (float)$offer->discount_value,
                    'discount'       => $discount,
                );
            }
        }
        return $best;
    }

    /**
     * The best automatic-offer discount for an item. Offers do NOT stack.
     *
     * @param string $itemtype
     * @param int $itemid
     * @param float $base
     * @param int|null $now
     * @return array {offers: array of {id, name, discount}, total: float}
     */
    public static function offers_discount($itemtype, $itemid, $base, $now = null) {
        $base = round(max(0.0, (float)$base), 2);
        $best = self::best_offer($itemtype, $itemid, $base, $now);
        if (!$best || $best->discount <= 0) {
            return array('offers' => array(), 'total' => 0.0);
        }
        $total = min(round((float)$best->discount, 2), $base);
        return array(
            'offers' => array(array('id' => (int)$best->id, 'name' => $best->name, 'discount' => $total)),
            'total'  => $total,
        );
    }

    /**
     * A compact, display-ready summary of the best automatic offer on an item, or null.
     *
     * @param string $itemtype course | package | subscription | program
     * @param int $itemid
     * @param float|null $base base price (resolved if null)
     * @param int|null $now
     * @return array|null
     */
    public static function offer_summary($itemtype, $itemid, $base = null, $now = null) {
        $itemtype = self::normalize_item_type($itemtype);
        $base = $base !== null ? (float)$base : self::price_of($itemtype, $itemid);
        $base = round(max(0.0, $base), 2);
        $od = self::offers_discount($itemtype, $itemid, $base, $now);
        if ($od['total'] <= 0) {
            return null;
        }
        $total = $od['total'];
        $pct = $base > 0 ? round($total / $base * 100) : 0;
        return array(
            'name'           => format_string($od['offers'][0]['name']),
            'discount_type'  => self::DISCOUNT_PERCENT,
            'discount_value' => $pct,
            'discount'       => round($total, 2),
            'original'       => $base,
            'final'          => round(max(0.0, $base - $total), 2),
            'label'          => '-' . rtrim(rtrim(number_format($pct, 2), '0'), '.') . '%',
        );
    }

    /**
     * Validate a coupon code for an item + user, or throw a moodle_exception describing why.
     *
     * @param string $code
     * @param string $itemtype
     * @param int $itemid
     * @param int $userid
     * @param int|null $now
     * @return object the coupon record
     */
    public static function validate_coupon($code, $itemtype, $itemid, $userid, $now = null) {
        global $DB;
        $now = $now ?? time();
        $code = trim((string)$code);
        if ($code === '') {
            throw new \moodle_exception('err_couponcoderequired', 'local_nit_commerce');
        }
        $coupon = $DB->get_record_select('nit_coupon', $DB->sql_equal('code', ':code', false),
            array('code' => $code));
        if (!$coupon) {
            throw new \moodle_exception('err_couponnotfound', 'local_nit_commerce');
        }
        if ($coupon->status !== 'active') {
            throw new \moodle_exception('err_couponinactive', 'local_nit_commerce');
        }
        if ($coupon->startdate > 0 && $now < $coupon->startdate) {
            throw new \moodle_exception('err_couponnotstarted', 'local_nit_commerce');
        }
        if ($coupon->enddate > 0 && $now > $coupon->enddate) {
            throw new \moodle_exception('err_couponexpired', 'local_nit_commerce');
        }
        $items = $DB->get_records('nit_coupon_item', array('couponid' => $coupon->id));
        if (!self::scope_matches($items, $itemtype, $itemid)) {
            throw new \moodle_exception('err_couponnotapplicable', 'local_nit_commerce');
        }
        $used = $DB->count_records('nit_coupon_usage', array('couponid' => $coupon->id));
        if ($coupon->usage_type === 'once') {
            if ($used >= 1) {
                throw new \moodle_exception('err_couponusedup', 'local_nit_commerce');
            }
        } else if ((int)$coupon->usage_limit > 0 && $used >= (int)$coupon->usage_limit) {
            throw new \moodle_exception('err_couponusedup', 'local_nit_commerce');
        }
        // One redemption per user, on top of any global cap: a coupon this user
        // has already redeemed cannot be applied by them again.
        if (!empty($userid)
                && $DB->record_exists('nit_coupon_usage', array('couponid' => $coupon->id, 'userid' => (int) $userid))) {
            throw new \moodle_exception('err_couponalreadyusedbyuser', 'local_nit_commerce');
        }
        return $coupon;
    }

    /**
     * Compute the charged price for an item, applying the best offer automatically and an optional
     * coupon code on top.
     *
     * @param string $itemtype course | package | subscription
     * @param int $itemid
     * @param int $userid buyer
     * @param string $couponcode optional code entered at checkout
     * @param float|null $baseprice override base price; else resolved
     * @param int|null $now
     * @return array
     */
    public static function resolve($itemtype, $itemid, $userid, $couponcode = '', $baseprice = null, $now = null) {
        $itemtype = self::normalize_item_type($itemtype);
        $now = $now ?? time();
        $base = $baseprice !== null ? (float)$baseprice : self::price_of($itemtype, $itemid);
        $base = round(max(0.0, $base), 2);

        $result = array(
            'original'        => $base,
            'offers'          => array(),
            'offer_id'        => 0,
            'offer_name'      => '',
            'offer_discount'  => 0.0,
            'coupon_id'       => 0,
            'coupon_code'     => '',
            'coupon_discount' => 0.0,
            'discount'        => 0.0,
            'final'           => $base,
        );

        $od = self::offers_discount($itemtype, $itemid, $base, $now);
        $running = $base;
        if ($od['total'] > 0) {
            $result['offers']         = $od['offers'];
            $result['offer_discount'] = $od['total'];
            $result['offer_id']       = $od['offers'][0]['id'];
            $result['offer_name']     = format_string($od['offers'][0]['name']);
            $running = round($running - $od['total'], 2);
        }

        $couponcode = trim((string)$couponcode);
        if ($couponcode !== '') {
            $coupon = self::validate_coupon($couponcode, $itemtype, $itemid, $userid, $now);
            $cdiscount = self::discount_amount($coupon->discount_type, $coupon->discount_value,
                $coupon->max_discount, $running);
            $result['coupon_id']       = (int)$coupon->id;
            $result['coupon_code']     = $coupon->code;
            $result['coupon_discount'] = $cdiscount;
            $running = round($running - $cdiscount, 2);
        }

        $result['final']    = round(max(0.0, $running), 2);
        $result['discount'] = round($base - $result['final'], 2);
        return $result;
    }

    /**
     * Reserve coupon/offer usage for a still-pending checkout, so concurrent
     * checkouts cannot over-redeem a capped coupon. The reservation IS the usage
     * row tied to the pending transaction: it is confirmed as-is at fulfilment
     * (record_usage is idempotent) and removed by {@see self::release_usage()} or
     * the cleanup task if the payment fails or is abandoned.
     *
     * Under a per-coupon lock it re-checks the coupon's global + per-user limits,
     * so two simultaneous reservations can't both pass.
     *
     * @param array $resolved discount metadata (see resolve() / apply_nit_discount)
     * @param int $userid
     * @param int $transactionid local_payments_transactions.id
     * @param string $itemtype
     * @param int $itemid
     * @return void
     * @throws \moodle_exception if the coupon's limit is already reached
     */
    public static function reserve_usage(array $resolved, $userid, $transactionid, $itemtype, $itemid) {
        global $DB;
        $couponid = (int) ($resolved['coupon_id'] ?? 0);
        $hascoupon = $couponid > 0 && ($resolved['coupon_discount'] ?? 0) > 0;

        // No coupon → only automatic (uncapped) offers to record; no lock needed.
        if (!$hascoupon) {
            self::do_record_usage($DB, $resolved, $userid, (int) $transactionid, $itemtype, $itemid, time());
            return;
        }

        // Serialise reservations of the same coupon so the limit check + insert
        // are atomic across concurrent checkouts.
        $lock = \core\lock\lock_config::get_lock_factory('local_nit_commerce')
            ->get_lock('coupon_reserve_' . $couponid, 10);
        if (!$lock) {
            throw new \moodle_exception('err_couponbusy', 'local_nit_commerce');
        }
        try {
            $coupon = $DB->get_record('nit_coupon', array('id' => $couponid));
            if ($coupon) {
                $used = $DB->count_records('nit_coupon_usage', array('couponid' => $couponid));
                if ($coupon->usage_type === 'once' && $used >= 1) {
                    throw new \moodle_exception('err_couponusedup', 'local_nit_commerce');
                }
                if ((int) $coupon->usage_limit > 0 && $used >= (int) $coupon->usage_limit) {
                    throw new \moodle_exception('err_couponusedup', 'local_nit_commerce');
                }
                if (!empty($userid)
                        && $DB->record_exists('nit_coupon_usage', array('couponid' => $couponid, 'userid' => (int) $userid))) {
                    throw new \moodle_exception('err_couponalreadyusedbyuser', 'local_nit_commerce');
                }
            }
            self::do_record_usage($DB, $resolved, $userid, (int) $transactionid, $itemtype, $itemid, time());
        } finally {
            $lock->release();
        }
    }

    /**
     * Release (delete) any coupon/offer usage rows reserved for a transaction,
     * when its payment failed or was abandoned — freeing the coupon for others.
     * A no-op for a fulfilled (paid) transaction should never be called.
     *
     * @param int $transactionid
     * @return void
     */
    public static function release_usage($transactionid) {
        global $DB;
        $transactionid = (int) $transactionid;
        if ($transactionid <= 0) {
            return;
        }
        $DB->delete_records('nit_coupon_usage', array('transactionid' => $transactionid));
        $DB->delete_records('nit_offer_usage', array('transactionid' => $transactionid));
    }

    /**
     * Record coupon + offer usage after a successful payment. Idempotent per transaction.
     *
     * @param array $resolved output of {@see self::resolve()} (or the stored discount metadata)
     * @param int $userid
     * @param int $transactionid local_payments_transactions.id
     * @param string $itemtype
     * @param int $itemid
     * @return void
     */
    public static function record_usage(array $resolved, $userid, $transactionid, $itemtype, $itemid) {
        global $DB;
        $now = time();
        $transactionid = (int)$transactionid;

        // Idempotency lock: a single transaction can be fulfilled twice when the
        // gateway webhook and the browser-redirect callback race. Serialise on the
        // transaction id so the "does a usage row for this transaction exist?"
        // check-then-insert below cannot double-record offer/coupon usage.
        $lock = null;
        if ($transactionid > 0) {
            $lockfactory = \core\lock\lock_config::get_lock_factory('local_nit_commerce');
            $lock = $lockfactory->get_lock('record_usage_' . $transactionid, 10);
        }

        try {
            self::do_record_usage($DB, $resolved, $userid, $transactionid, $itemtype, $itemid, $now);
        } finally {
            if ($lock) {
                $lock->release();
            }
        }
    }

    /**
     * Insert the offer/coupon usage rows for a transaction (idempotent per
     * transaction). Runs inside the transaction lock held by record_usage().
     *
     * @param \moodle_database $DB
     * @param array $resolved
     * @param int $userid
     * @param int $transactionid
     * @param string $itemtype
     * @param int $itemid
     * @param int $now
     * @return void
     */
    private static function do_record_usage($DB, array $resolved, $userid, $transactionid, $itemtype, $itemid, $now) {
        $offers = (isset($resolved['offers']) && is_array($resolved['offers'])) ? $resolved['offers'] : array();
        if (empty($offers) && !empty($resolved['offer_id']) && ($resolved['offer_discount'] ?? 0) > 0) {
            $offers = array(array('id' => $resolved['offer_id'], 'discount' => $resolved['offer_discount']));
        }
        foreach ($offers as $off) {
            $off = (array)$off;
            $oid = (int)($off['id'] ?? 0);
            $odisc = round((float)($off['discount'] ?? 0), 2);
            if ($oid <= 0 || $odisc <= 0) { continue; }
            $exists = $transactionid > 0 && $DB->record_exists('nit_offer_usage',
                array('offerid' => $oid, 'transactionid' => $transactionid));
            if ($exists) { continue; }
            $DB->insert_record('nit_offer_usage', (object) array(
                'offerid'         => $oid,
                'userid'          => $userid,
                'transactionid'   => $transactionid,
                'item_type'       => $itemtype,
                'item_id'         => (int)$itemid,
                'original_amount' => $resolved['original'] ?? 0,
                'discount_amount' => $odisc,
                'final_amount'    => round((float)($resolved['original'] ?? 0) - $odisc, 2),
                'timecreated'     => $now,
            ));
        }

        if (!empty($resolved['coupon_id']) && ($resolved['coupon_discount'] ?? 0) > 0) {
            $exists = $transactionid > 0 && $DB->record_exists('nit_coupon_usage',
                array('couponid' => $resolved['coupon_id'], 'transactionid' => $transactionid));
            if (!$exists) {
                $DB->insert_record('nit_coupon_usage', (object) array(
                    'couponid'        => $resolved['coupon_id'],
                    'userid'          => $userid,
                    'transactionid'   => $transactionid,
                    'item_type'       => $itemtype,
                    'item_id'         => (int)$itemid,
                    'original_amount' => $resolved['original'] ?? 0,
                    'discount_amount' => $resolved['coupon_discount'],
                    'final_amount'    => $resolved['final'] ?? 0,
                    'timecreated'     => $now,
                ));
            }
        }
    }
}

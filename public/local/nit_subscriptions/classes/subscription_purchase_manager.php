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
 * Subscription purchases: fulfilment after a successful gateway payment, and the active-subscription
 * lookup used for on-demand course access.
 *
 * Course access is resolved LIVE (a purchase record grants coverage; real Moodle enrolment happens
 * on demand when the student opens a covered course — see local_payments price_resolver + buy.php).
 * So fulfilment only needs to create the purchase record.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_subscriptions;

defined('MOODLE_INTERNAL') || die();

/**
 * Subscription purchase + access manager.
 */
class subscription_purchase_manager {

    /** @var string Active purchase status. */
    const STATUS_ACTIVE    = 'active';
    /** @var string Expired purchase status. */
    const STATUS_EXPIRED   = 'expired';
    /** @var string Cancelled purchase status. */
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Create a subscription purchase after a successful payment. Idempotent by gateway reference so a
     * re-delivered webhook does not create a second purchase.
     *
     * @param int $userid
     * @param int $subscriptionid
     * @param float $pricepaid amount actually charged (after coupon/offer)
     * @param string $reference gateway order id (for idempotency)
     * @param string $type normal | b2b
     * @param int $seats B2B seat capacity (0 for normal)
     * @return array purchase summary
     */
    public static function fulfil_from_gateway($userid, $subscriptionid, $pricepaid, $reference = '',
            $type = 'normal', $seats = 0) {
        global $DB;

        $reference = trim((string) $reference);

        // Serialise concurrent fulfilments of the SAME gateway reference. The
        // server-to-server webhook and the browser-redirect callback commonly
        // fire near-simultaneously; without this, both pass the "does a purchase
        // with this reference exist yet?" check and each inserts a row, doubling
        // the paid subscription. The lock is keyed on the reference so different
        // orders never block each other. Empty reference (e.g. non-gateway paths)
        // skips the lock — it also skips the idempotency check below.
        $lock = null;
        if ($reference !== '') {
            $lockfactory = \core\lock\lock_config::get_lock_factory('local_nit_subscriptions');
            $lock = $lockfactory->get_lock('fulfil_' . sha1($reference), 10);
        }

        try {
            if ($reference !== '') {
                $existing = $DB->get_record('nit_sub_purchase', ['reference' => $reference]);
                if ($existing) {
                    return self::summary($existing);
                }
            }

            return self::insert_purchase($DB, $userid, $subscriptionid, $pricepaid, $reference, $type, $seats);
        } finally {
            if ($lock) {
                $lock->release();
            }
        }
    }

    /**
     * Build and insert the purchase row. Split out of fulfil_from_gateway so the
     * insert happens inside the reference lock held by the caller.
     *
     * @param \moodle_database $DB
     * @param int $userid
     * @param int $subscriptionid
     * @param float $pricepaid
     * @param string $reference
     * @param string $type
     * @param int $seats
     * @return array purchase summary
     */
    private static function insert_purchase($DB, $userid, $subscriptionid, $pricepaid, $reference, $type, $seats) {
        $sub = $DB->get_record('nit_subscription', ['id' => $subscriptionid], '*', MUST_EXIST);
        $isb2b = ($type === 'b2b');

        // Business rule: one active NORMAL subscription per user. If a duplicate
        // payment reaches fulfilment (two checkouts paid before either fulfilled),
        // do NOT grant a second — return the existing subscription and log so an
        // admin can refund the duplicate charge. (Catches the sequential case; the
        // rare truly-simultaneous cross-checkout case may still slip through, but
        // no money is lost — the buyer paid — only the one-subscription rule.)
        if (!$isb2b && self::has_active_normal($userid)) {
            debugging("local_nit_subscriptions: duplicate active-normal subscription payment for user {$userid}"
                . " (reference {$reference}) — no second subscription granted; refund required.", DEBUG_NORMAL);
            $active = self::get_active_subscription($userid);
            if ($active) {
                return self::summary($active);
            }
        }

        $basePrice = (float) $sub->price;
        $discountPct = 0;
        if ($isb2b && $seats > 0) {
            $option = $DB->get_record('nit_sub_seat_option', ['subscriptionid' => $sub->id, 'seats' => (int) $seats]);
            if ($option) {
                $discountPct = (float) $option->discount_percent;
            }
        }

        $now = time();
        $expiresat = $now + ((int) $sub->duration_days * DAYSECS);

        $purchase = new \stdClass();
        $purchase->subscriptionid   = $sub->id;
        $purchase->userid           = $userid;
        $purchase->type             = $isb2b ? 'b2b' : 'normal';
        $purchase->seats            = (int) $seats;
        $purchase->base_price       = $basePrice;
        $purchase->discount_percent = $discountPct;
        $purchase->price_paid       = (float) $pricepaid;
        $purchase->duration_days    = (int) $sub->duration_days;
        $purchase->status           = self::STATUS_ACTIVE;
        $purchase->source           = ($reference === 'admin_assigned') ? 'admin_assigned' : 'online';
        $purchase->reference        = $reference;
        $purchase->timeactivated    = $now;
        $purchase->expires_at       = $expiresat;
        $purchase->timecreated      = $now;
        $purchase->id = $DB->insert_record('nit_sub_purchase', $purchase);

        return self::summary($purchase);
    }

    /**
     * The user's current active (non-expired) subscription purchase, or null.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get_active_subscription($userid) {
        global $DB;
        $now = time();
        $rows = $DB->get_records('nit_sub_purchase',
            ['userid' => $userid, 'status' => self::STATUS_ACTIVE], 'timeactivated DESC');
        foreach ($rows as $r) {
            if ((int) $r->expires_at === 0 || $now <= (int) $r->expires_at) {
                return $r;
            }
        }
        return null;
    }

    /**
     * Whether the user already holds an active NORMAL subscription (only one allowed at a time).
     *
     * @param int $userid
     * @return bool
     */
    public static function has_active_normal($userid) {
        $active = self::get_active_subscription($userid);
        return $active && (!isset($active->type) || $active->type === 'normal');
    }

    /**
     * Grant a user access to one course covered by their active subscription, as a
     * real Moodle enrolment that ends when the subscription expires. Idempotent:
     * if already actively enrolled, it does nothing.
     *
     * SECURITY: this grants course access, so the CALLER must first verify the
     * user holds an active subscription that actually covers this course (see
     * local/payments/buy.php, which gates on courses_for_subscription + sesskey).
     *
     * @param int $courseid
     * @param int $userid
     * @param int $until unix time the access should end (subscription expiry); 0 = no end date
     * @return bool true on success
     */
    public static function grant_course_access($courseid, $userid, $until = 0) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');

        $courseid = (int) $courseid;
        $userid = (int) $userid;
        $context = \context_course::instance($courseid);

        // Already actively enrolled — nothing to do.
        if (is_enrolled($context, $userid, '', true)) {
            return true;
        }

        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            return false;
        }

        // The course's enabled manual enrolment instance (create one if absent).
        $instance = $DB->get_record('enrol',
            ['courseid' => $courseid, 'enrol' => 'manual', 'status' => ENROL_INSTANCE_ENABLED],
            '*', IGNORE_MULTIPLE);
        if (!$instance) {
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $instanceid = $plugin->add_default_instance($course);
            if (!$instanceid) {
                return false;
            }
            $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        }

        // Enrol as the instance's default role (student), ending when the
        // subscription expires so access lapses automatically.
        $timeend = ((int) $until > time()) ? (int) $until : 0;
        $plugin->enrol_user($instance, $userid, $instance->roleid, time(), $timeend);

        return true;
    }

    /**
     * The effective status of a purchase record (expired if past its expiry).
     *
     * @param \stdClass $record
     * @return string
     */
    public static function effective_status($record) {
        if ($record->status !== self::STATUS_ACTIVE) {
            return $record->status;
        }
        if ((int) $record->expires_at > 0 && time() > (int) $record->expires_at) {
            return self::STATUS_EXPIRED;
        }
        return self::STATUS_ACTIVE;
    }

    /**
     * The user's subscription purchases, active first, for a "My subscriptions" view.
     *
     * @param int $userid
     * @return array
     */
    public static function get_my_subscriptions($userid) {
        global $DB;
        $sql = "SELECT sp.*, s.name AS subscription_name
                  FROM {nit_sub_purchase} sp
                  JOIN {nit_subscription} s ON s.id = sp.subscriptionid
                 WHERE sp.userid = :uid
              ORDER BY sp.timecreated DESC";
        $rows = $DB->get_records_sql($sql, ['uid' => $userid]);
        $now = time();
        $out = [];
        foreach ($rows as $r) {
            $status = self::effective_status($r);
            $daysleft = ($status === self::STATUS_ACTIVE && (int) $r->expires_at > 0)
                ? max(0, (int) ceil(((int) $r->expires_at - $now) / DAYSECS)) : 0;
            $out[] = [
                'id'             => (int) $r->id,
                'subscriptionid' => (int) $r->subscriptionid,
                'name'           => format_string(subscription_manager::resolve_mlang($r->subscription_name)),
                'type'           => $r->type ?? 'normal',
                'price_paid'     => (float) $r->price_paid,
                'status'         => $status,
                'timeactivated'  => (int) $r->timeactivated,
                'expires_at'     => (int) $r->expires_at,
                'remaining_days' => $daysleft,
                'duration_days'  => (int) $r->duration_days,
                // Courses the plan unlocks, as {id, fullname} objects — the catalog reads course.id
                // to show "included in your subscription" coverage.
                'courses'        => subscription_manager::courses_detail((int) $r->subscriptionid),
            ];
        }
        usort($out, function ($a, $b) {
            if (($a['status'] === self::STATUS_ACTIVE) !== ($b['status'] === self::STATUS_ACTIVE)) {
                return $a['status'] === self::STATUS_ACTIVE ? -1 : 1;
            }
            return $b['timeactivated'] - $a['timeactivated'];
        });
        return $out;
    }

    /**
     * The current user's subscription PAYMENTS, newest first, for a "Payment history" screen.
     *
     * Subscriptions are paid through the gateway (local_payments), so the money records live in
     * local_payments_transactions with metadata item_type=subscription — not in nit_sub_purchase
     * (which records fulfilment/access). This returns every subscription checkout the user started,
     * including failed/abandoned ones, so the history is complete. Degrades to [] if local_payments
     * is not installed.
     *
     * @param int $userid
     * @return array
     */
    public static function get_subscription_payment_history($userid) {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_payments_transactions')) {
            return [];
        }

        $rows = $DB->get_records('local_payments_transactions',
            ['userid' => $userid, 'courseid' => 0], 'timecreated DESC');
        $out = [];
        foreach ($rows as $r) {
            $meta = json_decode($r->metadata ?? '{}');
            // courseid=0 is the subscription sentinel, but guard on item_type too so any
            // future non-course, non-subscription item can't leak into this history.
            if (($meta->item_type ?? '') !== 'subscription') {
                continue;
            }
            $name = $meta->subscription_name ?? '';
            if ($name === '' && !empty($meta->item_id)) {
                $name = (string) $DB->get_field('nit_subscription', 'name', ['id' => (int) $meta->item_id]);
            }
            $out[] = [
                'id'             => (int) $r->id,
                'subscriptionid' => (int) ($meta->item_id ?? 0),
                'name'           => format_string(subscription_manager::resolve_mlang($name)),
                'order_id'       => (string) $r->order_id,
                'amount'         => (float) $r->amount,
                'currency'       => (string) $r->currency,
                'status'         => (string) $r->status,
                'payment_method' => (string) ($r->payment_method_type ?? ''),
                'coupon_code'    => (string) ($meta->coupon_code ?? ''),
                'timecreated'    => (int) $r->timecreated,
            ];
        }
        return $out;
    }

    /**
     * All user subscription purchases for the admin table, newest first.
     *
     * @return array
     */
    public static function get_all_user_subscriptions() {
        global $DB;
        $sql = "SELECT sp.*, s.name AS subscription_name, u.firstname, u.lastname, u.email
                  FROM {nit_sub_purchase} sp
                  JOIN {nit_subscription} s ON s.id = sp.subscriptionid
                  JOIN {user} u ON u.id = sp.userid
              ORDER BY sp.timecreated DESC";
        $out = [];
        foreach ($DB->get_records_sql($sql) as $r) {
            $out[] = [
                'id'            => (int) $r->id,
                'userid'        => (int) $r->userid,
                'user_fullname' => fullname($r),
                'user_email'    => $r->email,
                'name'          => format_string(subscription_manager::resolve_mlang($r->subscription_name)),
                'type'          => $r->type,
                'price_paid'    => (float) $r->price_paid,
                'status'        => self::effective_status($r),
                'expires_at'    => (int) $r->expires_at,
            ];
        }
        return $out;
    }

    /**
     * Cancel a user's subscription purchase (admin unsubscribe).
     * Also unenrols the user from every course that was covered by this subscription plan.
     *
     * @param int $purchaseid
     * @return void
     */
    public static function unsubscribe($purchaseid) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');

        $purchase = $DB->get_record('nit_sub_purchase', ['id' => $purchaseid], '*', MUST_EXIST);

        // Cancel the purchase record.
        $DB->update_record('nit_sub_purchase', (object) [
            'id'     => $purchase->id,
            'status' => self::STATUS_CANCELLED,
        ]);

        // Unenrol the user from courses covered by this subscription plan.
        $courseids = $DB->get_fieldset_select('nit_course_access',
            'courseid', 'subscriptionid = :sid', ['sid' => (int) $purchase->subscriptionid]);
        if (empty($courseids)) {
            return;
        }

        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            return;
        }

        foreach ($courseids as $cid) {
            $instance = $DB->get_record('enrol',
                ['courseid' => (int) $cid, 'enrol' => 'manual', 'status' => ENROL_INSTANCE_ENABLED],
                '*', IGNORE_MULTIPLE);
            if (!$instance) {
                continue;
            }
            $ctx = \context_course::instance((int) $cid, IGNORE_MISSING);
            if (!$ctx) {
                continue;
            }
            if (is_enrolled($ctx, (int) $purchase->userid, '', true)) {
                $plugin->unenrol_user($instance, (int) $purchase->userid);
            }
        }
    }

    /**
     * Compact summary of a purchase record.
     *
     * @param \stdClass $p
     * @return array
     */
    private static function summary($p) {
        return [
            'purchaseid'    => (int) $p->id,
            'subscriptionid' => (int) $p->subscriptionid,
            'type'          => $p->type,
            'price_paid'    => (float) $p->price_paid,
            'status'        => self::effective_status($p),
            'timeactivated' => (int) $p->timeactivated,
            'expires_at'    => (int) $p->expires_at,
        ];
    }
}

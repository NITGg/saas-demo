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

use local_nit_finance\config;
use local_nit_finance\exception\finance_exception;

/**
 * Refund requests (TASKS-ROADMAP task 8).
 *
 * Within the grace period after a purchase (Site administration → Plugins → Local
 * plugins → Finance → Earning settings, default 3 days) a student may ask for a refund
 * of a course, a single lesson or a subscription they paid online or from their wallet.
 * An admin approves or rejects it. On approval the amount paid less the refund fee
 * (a percent the platform keeps) goes to the student's wallet; the teacher's share of
 * the sale is taken back in full; the student loses the access the purchase gave
 * (unless something else still gives it); the online payment is marked refunded.
 *
 * Not refundable here: purchases opened with an access code (paid outside the site),
 * lesson packages (their own refund in local_nit_flex), payments in another currency
 * than the wallet's (EGP), and a purchase that already had a request (one request per
 * purchase, so a rejected one is not asked again).
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class refunds {

    /** A gateway payment. */
    public const SOURCE_ONLINE = 'online';
    /** Paid from the wallet balance. */
    public const SOURCE_WALLET = 'wallet';

    /** Item types. */
    public const COURSE = 'course';
    public const CM = 'cm';
    public const SUBSCRIPTION = 'subscription';

    /** Request statuses. */
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const STATUSES = [self::PENDING, self::APPROVED, self::REJECTED];

    /** The currency of wallet balances. */
    private const WALLET_CURRENCY = 'EGP';

    /**
     * What the student may still ask to refund, newest first.
     *
     * @param int $userid
     * @return array[] key, source, itemtype, itemid, courseid, itemname, transactionid, purchaseid,
     *         paidtime, deadline, amount_minor, fee_minor, refund_minor
     */
    public static function refundable(int $userid): array {
        $days = config::refund_days();
        if ($days <= 0) {
            return [];
        }
        $since = time() - $days * DAYSECS;
        $items = array_merge(self::online_purchases($userid, $since), self::wallet_purchases($userid, $since),
            self::wallet_subscriptions($userid, $since));
        $open = self::open_keys($userid);
        $out = [];
        foreach ($items as $item) {
            if (isset($open[$item['key'] . '@' . $item['paidtime']]) || $item['amount_minor'] <= 0) {
                continue;
            }
            [$fee, $refund] = self::split($item['amount_minor'], config::refund_fee_percent());
            $out[] = $item + ['deadline' => $item['paidtime'] + $days * DAYSECS, 'fee_minor' => $fee, 'refund_minor' => $refund];
        }
        usort($out, fn($a, $b) => $b['paidtime'] <=> $a['paidtime']);
        return $out;
    }

    /**
     * The fee and the refund for an amount.
     *
     * @param int $amountminor
     * @param float $feepercent
     * @return int[] [fee, refund] in minor units
     */
    public static function split(int $amountminor, float $feepercent): array {
        $fee = (int) round($amountminor * $feepercent / 100);
        return [$fee, $amountminor - $fee];
    }

    /**
     * Ask for a refund of one of refundable()'s items.
     *
     * @param int $userid the student
     * @param string $key the item's key from refundable()
     * @param string $reason the student's reason (optional)
     * @return \stdClass the request
     */
    public static function request(int $userid, string $key, string $reason = ''): \stdClass {
        global $DB;
        $request = wallets::locked('student_' . $userid, function() use ($DB, $userid, $key, $reason) {
            $item = null;
            foreach (self::refundable($userid) as $candidate) {
                if ($candidate['key'] === $key) {
                    $item = $candidate;
                }
            }
            if (!$item) {
                throw new finance_exception('err_notrefundable');
            }
            $now = time();
            $record = (object) [
                'userid' => $userid,
                'source' => $item['source'],
                'itemtype' => $item['itemtype'],
                'itemid' => $item['itemid'],
                'courseid' => $item['courseid'],
                'itemname' => \core_text::substr($item['itemname'], 0, 255),
                'transactionid' => $item['transactionid'],
                'purchaseid' => $item['purchaseid'],
                'paidtime' => $item['paidtime'],
                'amount_minor' => $item['amount_minor'],
                'fee_percent' => config::refund_fee_percent(),
                'fee_minor' => $item['fee_minor'],
                'refund_minor' => $item['refund_minor'],
                'reason' => trim($reason),
                'status' => self::PENDING,
                'adminreason' => '',
                'processedby' => 0,
                'timeprocessed' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('nit_refund_request', $record);
            return $record;
        });
        self::notify_admins($request);
        return $request;
    }

    /**
     * Approve or reject a pending request.
     *
     * @param int $adminid
     * @param int $requestid
     * @param string $action approve | reject
     * @param string $reason why it is rejected (required to reject)
     * @return \stdClass the request
     */
    public static function process(int $adminid, int $requestid, string $action, string $reason = ''): \stdClass {
        global $DB;
        if (!in_array($action, ['approve', 'reject'], true)) {
            throw new finance_exception('err_badaction');
        }
        $request = $DB->get_record('nit_refund_request', ['id' => $requestid]);
        if (!$request) {
            throw new finance_exception('err_refundnotfound');
        }
        $reason = trim($reason);
        if ($action === 'reject' && $reason === '') {
            throw new finance_exception('err_reasonrequired');
        }
        $request = wallets::locked('student_' . $request->userid, function() use ($DB, $requestid, $adminid, $action, $reason) {
            $request = $DB->get_record('nit_refund_request', ['id' => $requestid], '*', MUST_EXIST);
            if ($request->status !== self::PENDING) {
                throw new finance_exception('err_refundprocessed');
            }
            $transaction = $DB->start_delegated_transaction();
            try {
                if ($action === 'approve') {
                    self::execute($request, $adminid);
                }
                $request->status = $action === 'approve' ? self::APPROVED : self::REJECTED;
                $request->adminreason = $reason;
                $request->processedby = $adminid;
                $request->timeprocessed = $request->timemodified = time();
                $DB->update_record('nit_refund_request', $request);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
            return $request;
        });
        if ($request->status === self::APPROVED) {
            // After the commit: unenrolling sends notifications, which must not undo the refund.
            self::remove_access($request);
        }
        self::notify_student($request);
        return $request;
    }

    /**
     * Requests, newest first.
     *
     * @param string $status '' = all
     * @param int $userid 0 = every student
     * @return \stdClass[]
     */
    public static function list(string $status = '', int $userid = 0): array {
        global $DB;
        $conditions = [];
        if ($status !== '') {
            if (!in_array($status, self::STATUSES, true)) {
                throw new finance_exception('err_badstatus');
            }
            $conditions['status'] = $status;
        }
        if ($userid) {
            $conditions['userid'] = $userid;
        }
        return array_values($DB->get_records('nit_refund_request', $conditions, 'timecreated DESC, id DESC'));
    }

    // ── What happens on approval ─────────────────────────────────────────────

    /**
     * Give the money back and undo the sale (inside the caller's DB transaction).
     *
     * @param \stdClass $request
     * @param int $adminid
     */
    private static function execute(\stdClass $request, int $adminid): void {
        global $DB;
        $now = time();
        $ref = ['itemtype' => $request->itemtype, 'itemid' => (int) $request->itemid, 'note' => $request->itemname];
        if ($request->source === self::SOURCE_ONLINE) {
            $tx = $DB->get_record('local_payments_transactions', ['id' => $request->transactionid]);
            if (!$tx || $tx->status !== 'completed') {
                throw new finance_exception('err_notrefundable');
            }
            $DB->update_record('local_payments_transactions', (object) ['id' => $tx->id, 'status' => 'refunded',
                'timemodified' => $now]);
            $DB->insert_record('local_payments_refunds', (object) [
                'transaction_id' => $tx->id,
                'operation_type' => 'refund',
                'amount' => $request->refund_minor / 100,
                'currency' => self::WALLET_CURRENCY,
                'reason' => (string) $request->reason,
                'status' => 'completed',
                'gateway_code' => 'wallet',
                'initiated_by' => $adminid,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            if ($request->itemtype === self::SUBSCRIPTION) {
                self::cancel_subscription((int) $request->purchaseid);
            }
            // The gateway money never reached a teacher or the platform wallet: nothing to take back.
        } else if ($request->itemtype === self::SUBSCRIPTION) {
            $sub = $DB->get_record('nit_sub_purchase', ['id' => $request->purchaseid]);
            if (!$sub || $sub->status !== 'active') {
                throw new finance_exception('err_notrefundable');
            }
            self::cancel_subscription((int) $sub->id);
            // The whole price went to the platform; it keeps the fee.
            wallets::move(wallets::PLATFORM, 0, -(int) $request->refund_minor, wallets::KIND_REFUND,
                $ref + ['purchaseid' => (int) $sub->id], true);
            $ref['purchaseid'] = (int) $sub->id;
        } else {
            $purchase = $DB->get_record('nit_purchase', ['id' => $request->purchaseid]);
            if (!$purchase || $purchase->status !== 'active' || $purchase->method !== self::SOURCE_WALLET) {
                throw new finance_exception('err_notrefundable');
            }
            $DB->update_record('nit_purchase', (object) ['id' => $purchase->id, 'status' => 'revoked', 'timemodified' => $now]);
            // The teacher's share is taken back in full; the platform pays the rest and keeps the fee.
            $teachershare = 0;
            foreach ($DB->get_records('nit_earning', ['purchaseid' => $purchase->id, 'source' => 'cm', 'status' => 'active']) as $earning) {
                $DB->update_record('nit_earning', (object) ['id' => $earning->id, 'status' => 'reversed',
                    'reverse_reason' => get_string('refundreversal', 'local_nit_finance', $request->id),
                    'reversedby' => $adminid, 'timereversed' => $now, 'timemodified' => $now]);
                wallets::move(wallets::TEACHER, (int) $earning->teacherid, -(int) $earning->teacher_amount_minor,
                    wallets::KIND_REFUND, $ref + ['purchaseid' => (int) $purchase->id], true);
                $teachershare += (int) $earning->teacher_amount_minor;
            }
            $platform = (int) $request->refund_minor - $teachershare;
            if ($platform !== 0) {
                wallets::move(wallets::PLATFORM, 0, -$platform, wallets::KIND_REFUND,
                    $ref + ['purchaseid' => (int) $purchase->id], true);
            }
            $ref['purchaseid'] = (int) $purchase->id;
        }
        if ((int) $request->refund_minor > 0) {
            wallets::move(wallets::STUDENT, (int) $request->userid, (int) $request->refund_minor, wallets::KIND_REFUND, $ref);
        }
    }

    /**
     * End a subscription now.
     *
     * @param int $subpurchaseid nit_sub_purchase.id
     */
    private static function cancel_subscription(int $subpurchaseid): void {
        global $DB;
        $sub = $DB->get_record('nit_sub_purchase', ['id' => $subpurchaseid]);
        if (!$sub) {
            return;
        }
        // Remember when it would have ended: its course enrolments end then (see remove_access()).
        $DB->update_record('nit_sub_purchase', (object) ['id' => $sub->id, 'status' => 'cancelled']);
    }

    /**
     * Take away the access the refunded purchase gave, unless something else (another
     * purchase, a subscription, an online payment) still gives it.
     *
     * @param \stdClass $request
     */
    private static function remove_access(\stdClass $request): void {
        global $DB;
        try {
            $userid = (int) $request->userid;
            if ($request->itemtype === self::SUBSCRIPTION) {
                $sub = $DB->get_record('nit_sub_purchase', ['id' => $request->purchaseid]);
                if (!$sub || !class_exists('\local_nit_subscriptions\subscription_manager')) {
                    return;
                }
                // The subscription's own enrolments end when it would have ended (grant_course_access).
                foreach (\local_nit_subscriptions\subscription_manager::courses_for_subscription((int) $sub->subscriptionid) as $courseid) {
                    self::unenrol_if_unowned($userid, (int) $courseid, (int) $sub->expires_at);
                }
                return;
            }
            if ($request->itemtype === self::CM) {
                $enrolled = (int) $DB->get_field('nit_purchase', 'enrolled', ['id' => $request->purchaseid]);
                if (!$enrolled && $request->source === self::SOURCE_WALLET) {
                    return; // The student was in the course before buying the lesson.
                }
            }
            self::unenrol_if_unowned($userid, (int) $request->courseid);
        } catch (\Throwable $e) {
            // The money is settled; an admin can still unenrol by hand.
            debugging('local_nit_finance: removing access after a refund failed: ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }

    /**
     * Unenrol (manual enrolment) unless the student still owns the course or a lesson in it.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $timeend only an enrolment ending at this time (a subscription's), 0 = any
     */
    private static function unenrol_if_unowned(int $userid, int $courseid, int $timeend = 0): void {
        global $DB;
        if ($courseid <= 1 || access::owns_course($userid, $courseid)
                || $DB->record_exists('nit_purchase', ['userid' => $userid, 'courseid' => $courseid, 'status' => 'active'])) {
            return;
        }
        $plugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
        if (!$plugin || !$instance) {
            return;
        }
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
        if (!$ue || ($timeend && (int) $ue->timeend !== $timeend)) {
            return;
        }
        $plugin->unenrol_user($instance, $userid);
    }

    // ── What may be refunded ─────────────────────────────────────────────────

    /**
     * Completed online course and subscription payments since $since (in the wallet's currency).
     *
     * @param int $userid
     * @param int $since
     * @return array[]
     */
    private static function online_purchases(int $userid, int $since): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_payments_transactions')) {
            return [];
        }
        $out = [];
        $rows = $DB->get_records_select('local_payments_transactions',
            "userid = :u AND status = 'completed' AND currency = :cur AND timecreated >= :since",
            ['u' => $userid, 'cur' => self::WALLET_CURRENCY, 'since' => $since], 'timecreated DESC');
        foreach ($rows as $tx) {
            $meta = json_decode((string) $tx->metadata, true) ?: [];
            $type = (string) ($meta['item_type'] ?? ((int) $tx->courseid ? self::COURSE : ''));
            $base = ['source' => self::SOURCE_ONLINE, 'transactionid' => (int) $tx->id, 'paidtime' => (int) $tx->timecreated,
                'amount_minor' => (int) round((float) $tx->amount * 100)];
            if ($type === self::COURSE && (int) $tx->courseid > 1) {
                $course = $DB->get_record('course', ['id' => $tx->courseid], 'id, fullname');
                if (!$course || !is_enrolled(\context_course::instance($course->id), $userid, '', true)) {
                    continue; // Deleted, or already taken out of the course.
                }
                $out[] = $base + ['key' => self::SOURCE_ONLINE . ':' . self::COURSE . ':' . $tx->id, 'itemtype' => self::COURSE,
                    'itemid' => (int) $course->id, 'courseid' => (int) $course->id, 'purchaseid' => 0,
                    'itemname' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)])];
            } else if ($type === self::SUBSCRIPTION && $DB->get_manager()->table_exists('nit_sub_purchase')) {
                $sub = $DB->get_record('nit_sub_purchase', ['reference' => $tx->order_id, 'userid' => $userid, 'status' => 'active']);
                if ($sub) {
                    $out[] = $base + ['key' => self::SOURCE_ONLINE . ':' . self::SUBSCRIPTION . ':' . $tx->id,
                        'itemtype' => self::SUBSCRIPTION, 'itemid' => (int) $sub->subscriptionid, 'courseid' => 0,
                        'purchaseid' => (int) $sub->id, 'itemname' => self::plan_name((int) $sub->subscriptionid)];
                }
            }
        }
        return $out;
    }

    /**
     * Courses and lessons paid from the wallet since $since.
     *
     * @param int $userid
     * @param int $since
     * @return array[]
     */
    private static function wallet_purchases(int $userid, int $since): array {
        global $DB;
        $out = [];
        $rows = $DB->get_records_select('nit_purchase', "userid = :u AND status = 'active' AND method = :m AND timecreated >= :since",
            ['u' => $userid, 'm' => self::SOURCE_WALLET, 'since' => $since], 'timecreated DESC');
        foreach ($rows as $p) {
            try {
                $item = catalog::item($p->itemtype, (int) $p->itemid);
            } catch (\moodle_exception $e) {
                continue; // The activity or course was deleted.
            }
            $out[] = ['key' => self::SOURCE_WALLET . ':' . $p->itemtype . ':' . $p->id, 'source' => self::SOURCE_WALLET,
                'itemtype' => $p->itemtype, 'itemid' => (int) $p->itemid, 'courseid' => (int) $p->courseid,
                'itemname' => (string) $item->name, 'transactionid' => 0, 'purchaseid' => (int) $p->id,
                'paidtime' => (int) $p->timecreated, 'amount_minor' => (int) $p->amount_minor];
        }
        return $out;
    }

    /**
     * Subscriptions paid from the wallet since $since.
     *
     * @param int $userid
     * @param int $since
     * @return array[]
     */
    private static function wallet_subscriptions(int $userid, int $since): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('nit_sub_purchase')) {
            return [];
        }
        $out = [];
        $rows = $DB->get_records_select('nit_sub_purchase', "userid = :u AND status = 'active' AND source = :s AND timecreated >= :since",
            ['u' => $userid, 's' => self::SOURCE_WALLET, 'since' => $since], 'timecreated DESC');
        foreach ($rows as $sub) {
            $out[] = ['key' => self::SOURCE_WALLET . ':' . self::SUBSCRIPTION . ':' . $sub->id, 'source' => self::SOURCE_WALLET,
                'itemtype' => self::SUBSCRIPTION, 'itemid' => (int) $sub->subscriptionid, 'courseid' => 0,
                'itemname' => self::plan_name((int) $sub->subscriptionid), 'transactionid' => 0, 'purchaseid' => (int) $sub->id,
                'paidtime' => (int) $sub->timecreated, 'amount_minor' => (int) round((float) $sub->price_paid * 100)];
        }
        return $out;
    }

    /**
     * The student's purchases that already have a request (one request per purchase: a
     * rejected one is not asked again), as "key@paidtime". The paid time tells a purchase
     * apart from the same item bought again after a refund (nit_purchase re-uses its row).
     *
     * @param int $userid
     * @return array<string, true>
     */
    private static function open_keys(int $userid): array {
        global $DB;
        $out = [];
        $rows = $DB->get_records('nit_refund_request', ['userid' => $userid]);
        foreach ($rows as $r) {
            $id = $r->source === self::SOURCE_ONLINE ? $r->transactionid : $r->purchaseid;
            $out[$r->source . ':' . $r->itemtype . ':' . $id . '@' . $r->paidtime] = true;
        }
        return $out;
    }

    /**
     * A subscription plan's name.
     *
     * @param int $planid
     * @return string
     */
    private static function plan_name(int $planid): string {
        global $DB;
        $name = $DB->get_manager()->table_exists('nit_subscription')
            ? (string) $DB->get_field('nit_subscription', 'name', ['id' => $planid]) : '';
        return $name !== '' ? format_string($name) : get_string('itemtype_subscription', 'local_nit_finance');
    }

    // ── Notifications ────────────────────────────────────────────────────────

    /**
     * Tell the finance managers a request is waiting.
     *
     * @param \stdClass $request
     */
    private static function notify_admins(\stdClass $request): void {
        $student = \core_user::get_user($request->userid);
        $url = new \moodle_url('/local/nit_finance/manage_refunds.php');
        $admins = get_users_by_capability(\context_system::instance(), 'local/nit_finance:manage', 'u.*');
        foreach (get_admins() as $admin) {
            $admins[$admin->id] = $admin;
        }
        foreach ($admins as $admin) {
            $a = ['student' => $student ? fullname($student) : '', 'item' => $request->itemname,
                'amount' => money::format((int) $request->refund_minor), 'url' => $url->out(false)];
            self::send('refundrequest', $admin, get_string('refundrequest_subject', 'local_nit_finance', $a),
                get_string('refundrequest_body', 'local_nit_finance', $a), $url);
        }
    }

    /**
     * Tell the student the decision.
     *
     * @param \stdClass $request
     */
    private static function notify_student(\stdClass $request): void {
        $student = \core_user::get_user($request->userid);
        if (!$student) {
            return;
        }
        $key = $request->status === self::APPROVED ? 'refundapproved' : 'refundrejected';
        $a = ['item' => $request->itemname, 'amount' => money::format((int) $request->refund_minor),
            'reason' => (string) $request->adminreason];
        self::send('refundresult', $student, get_string($key . '_subject', 'local_nit_finance', $a),
            get_string($key . '_body', 'local_nit_finance', $a), new \moodle_url('/local/nit_finance/refunds.php'));
    }

    /**
     * Send one notification (a failure never undoes the refund).
     *
     * @param string $provider
     * @param \stdClass $to
     * @param string $subject
     * @param string $body
     * @param \moodle_url $url
     */
    private static function send(string $provider, \stdClass $to, string $subject, string $body, \moodle_url $url): void {
        try {
            $message = new \core\message\message();
            $message->component = 'local_nit_finance';
            $message->name = $provider;
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $to;
            $message->subject = $subject;
            $message->fullmessage = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '<p>' . nl2br(s($body)) . '</p>';
            $message->smallmessage = $subject;
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = $subject;
            message_send($message);
        } catch (\Throwable $e) {
            debugging('local_nit_finance: refund notification failed: ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }
}

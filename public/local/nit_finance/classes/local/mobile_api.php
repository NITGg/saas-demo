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

use local_nit_finance\api\wallet;
use local_nit_finance\entity\earning;
use local_nit_finance\exception\finance_exception;

/**
 * The finance side of the mobile app (`/local/nit_finance/api.php`): wallets,
 * lesson sales, codes, teacher earnings and withdrawals, shaped as plain arrays.
 *
 * Every method takes the acting user explicitly and reuses the same managers as
 * the web pages, so the app follows exactly the web's rules. Who may call what
 * (manage capability for the admin methods) is checked by api.php; teacher-only
 * methods check {@see self::require_teacher()} here, and the lesson-price
 * method checks local/nit_finance:setprice in the course itself.
 *
 * Money: amounts come in as pounds typed by a person ("150", "99.5") and go out
 * twice — `<name>_minor` (int piastres) and `<name>` (float pounds) — with
 * `currency`.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mobile_api {

    /** Currency of every wallet amount. */
    public const CURRENCY = 'EGP';

    /** Withdrawal states, in order. */
    public const WITHDRAWAL_STATUSES = ['pending', 'approved', 'rejected', 'paid'];

    /** Withdrawal actions an admin can take. */
    public const WITHDRAWAL_ACTIONS = ['approve', 'reject', 'pay'];

    /** Preset top-up amounts shown by the web wallet (pounds). */
    public const TOPUP_PRESETS = [50, 100, 200, 500];

    // ── Shared helpers ───────────────────────────────────────────────────────

    /**
     * Minor units as pounds.
     *
     * @param int $minor
     * @return float
     */
    public static function major(int $minor): float {
        return round($minor / 100, 2);
    }

    /**
     * `<key>_minor` + `<key>` for one amount.
     *
     * @param string $key
     * @param int $minor
     * @return array
     */
    private static function money(string $key, int $minor): array {
        return [$key . '_minor' => $minor, $key => self::major($minor)];
    }

    /**
     * A positive amount typed in pounds, as minor units.
     *
     * @param string $raw e.g. "150", "99.5", "١٥٠"
     * @return int
     */
    public static function parse_amount(string $raw): int {
        $minor = money::to_minor($raw);
        if ($minor === null || $minor <= 0) {
            throw new finance_exception('err_amountpositive');
        }
        return $minor;
    }

    /**
     * A language string of this plugin, or the key itself when it does not exist.
     *
     * @param string $key
     * @param mixed $a
     * @return string
     */
    private static function str(string $key, $a = null): string {
        return get_string_manager()->string_exists($key, 'local_nit_finance')
            ? get_string($key, 'local_nit_finance', $a) : $key;
    }

    /**
     * One wallet ledger line.
     *
     * @param \stdClass $l nit_wallet_txn row
     * @return array
     */
    public static function ledger_line(\stdClass $l): array {
        return [
            'id' => (int) $l->id,
            'kind' => (string) $l->kind,
            'kind_label' => self::str('kind_' . $l->kind),
        ] + self::money('amount', (int) $l->amount_minor)
          + self::money('balance_after', (int) $l->balance_after_minor) + [
            'itemtype' => (string) $l->itemtype,
            'itemid' => (int) $l->itemid,
            'purchaseid' => (int) $l->purchaseid,
            'note' => format_string((string) $l->note),
            'timecreated' => (int) $l->timecreated,
        ];
    }

    /**
     * One page of a wallet's ledger, with its balance.
     *
     * @param string $type
     * @param int $userid
     * @param int $page
     * @param int $perpage
     * @return array
     */
    private static function ledger(string $type, int $userid, int $page, int $perpage): array {
        return [
            'type' => $type,
            'userid' => $type === wallets::PLATFORM ? 0 : $userid,
        ] + self::money('balance', wallets::balance($type, $userid)) + [
            'currency' => self::CURRENCY,
            'total' => wallets::history_count($type, $userid),
            'page' => $page,
            'perpage' => $perpage,
            'lines' => array_map([self::class, 'ledger_line'],
                wallets::history($type, $userid, $perpage, $page * $perpage)),
        ];
    }

    /**
     * An item (lesson or course) from catalog::item().
     *
     * @param \stdClass $item
     * @return array
     */
    private static function item(\stdClass $item): array {
        return [
            'type' => $item->type,
            'id' => (int) $item->id,
            'cmid' => $item->type === catalog::CM ? (int) $item->id : 0,
            'courseid' => (int) $item->courseid,
            'name' => $item->name,
            'coursename' => $item->coursename,
            'type_label' => self::str('itemtype_' . $item->type),
        ] + self::money('price', (int) $item->priceminor);
    }

    /**
     * A course + activity the user may see, as buy.php allows them.
     *
     * @param int $cmid
     * @param int $userid
     * @return array [$course, \cm_info $cm]
     */
    private static function visible_cm(int $cmid, int $userid): array {
        global $DB;
        $row = $DB->get_record('course_modules', ['id' => $cmid], 'id, course, deletioninprogress');
        if (!$row || $row->deletioninprogress || !$DB->record_exists('course', ['id' => $row->course])) {
            throw new finance_exception('err_itemnotfound');
        }
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $context = \context_course::instance($course->id);
        if ((!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context, $userid))
                || (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $context, $userid))) {
            throw new finance_exception('err_itemnotfound');
        }
        return [$course, $cm];
    }

    /**
     * Full names of some users, by id.
     *
     * @param int[] $ids
     * @return array<int,string>
     */
    private static function names(array $ids): array {
        global $DB;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $fields = 'id, deleted, ' . implode(', ', \core_user\fields::get_name_fields());
        $out = [];
        foreach ($DB->get_records_list('user', 'id', $ids, '', $fields) as $u) {
            $out[(int) $u->id] = $u->deleted ? '' : fullname($u);
        }
        return $out;
    }

    // ── Student ──────────────────────────────────────────────────────────────

    /**
     * The student wallet: balance and what top-up is possible.
     *
     * @param int $userid
     * @return array
     */
    public static function get_wallet(int $userid): array {
        $haspayments = class_exists('\local_payments\manager')
            && method_exists('\local_payments\manager', 'create_wallet_topup_checkout');
        $min = $haspayments ? (int) \local_payments\manager::WALLET_TOPUP_MIN : 0;
        $max = $haspayments ? (int) \local_payments\manager::WALLET_TOPUP_MAX : 0;
        $balance = wallets::balance(wallets::STUDENT, $userid);
        return self::money('balance', $balance) + [
            'balance_text' => money::format($balance),
            'currency' => self::CURRENCY,
            'topup' => [
                'online_payment_available' => output::online_payment_available(),
                'min_minor' => $min * 100,
                'min' => (float) $min,
                'max_minor' => $max * 100,
                'max' => (float) $max,
                'presets' => array_map('floatval', self::TOPUP_PRESETS),
            ],
            'can_redeem_code' => true,
            'is_teacher' => earnings_page::is_teacher($userid),
        ];
    }

    /**
     * The student wallet ledger, newest first.
     *
     * @param int $userid
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function get_wallet_history(int $userid, int $page, int $perpage): array {
        return self::ledger(wallets::STUDENT, $userid, $page, $perpage);
    }

    /**
     * What the student bought or unlocked (lessons and whole courses), newest first.
     *
     * @param int $userid
     * @return array
     */
    public static function get_my_purchases(int $userid): array {
        $out = [];
        foreach (purchases::for_user($userid) as $p) {
            $out[] = [
                'id' => (int) $p->id,
                'item' => self::item($p->item),
            ] + self::money('amount', (int) $p->amount_minor) + [
                'currency' => self::CURRENCY,
                'method' => (string) $p->method,
                'method_label' => self::str('method_' . $p->method),
                'timecreated' => (int) $p->timecreated,
            ];
        }
        return ['purchases' => $out];
    }

    /**
     * Start an online top-up: the app opens checkout_url; the payment gateway's
     * webhook (or get_topup_status) credits the wallet once it is paid.
     *
     * @param int $userid
     * @param string $amount pounds as typed
     * @param string $returnurl local page (path) to land on after paying, '' = the wallet page
     * @param string $lang ar | en
     * @return array
     */
    public static function create_topup_checkout(int $userid, string $amount, string $returnurl = '',
            string $lang = 'ar'): array {
        global $CFG;
        if (!class_exists('\local_payments\manager')
                || !method_exists('\local_payments\manager', 'create_wallet_topup_checkout')) {
            throw new finance_exception('err_paymentunavailable');
        }
        $minor = self::parse_amount($amount);
        $min = (int) \local_payments\manager::WALLET_TOPUP_MIN * 100;
        $max = (int) \local_payments\manager::WALLET_TOPUP_MAX * 100;
        if ($minor < $min || $minor > $max) {
            throw new finance_exception('err_topuprange', (object) ['min' => money::format($min), 'max' => money::format($max)]);
        }
        if (!output::online_payment_available()) {
            throw new finance_exception('err_paymentunavailable');
        }
        $returnpath = '';
        if ($returnurl !== '') {
            $returnpath = str_replace($CFG->wwwroot, '', (new \moodle_url($returnurl))->out(false));
            if (strpos($returnpath, '/') !== 0 || strpos($returnpath, '//') === 0) {
                $returnpath = '';
            }
        }
        $checkout = \local_payments\manager::create_wallet_topup_checkout($minor / 100, $userid,
            $lang === 'en' ? 'en' : 'ar', $returnpath);
        return [
            'order_id' => (string) $checkout->order_id,
            'checkout_url' => (string) $checkout->checkout_url,
            'expires_at' => (int) $checkout->expires_at,
            'provider' => (string) $checkout->provider,
            'transaction_id' => (int) $checkout->transaction_id,
        ] + self::money('amount', $minor) + [
            'currency' => self::CURRENCY,
            'callback_url' => $CFG->wwwroot . '/local/payments/callback.php',
        ];
    }

    /**
     * Where an online top-up stands. Asks the gateway when the webhook has not
     * arrived yet (which credits the wallet if it was paid).
     *
     * @param int $userid
     * @param string $orderid
     * @return array
     */
    public static function get_topup_status(int $userid, string $orderid): array {
        global $DB;
        $tx = $orderid === '' ? null : $DB->get_record('local_payments_transactions', ['order_id' => $orderid]);
        $meta = $tx ? json_decode($tx->metadata ?? '{}') : null;
        if (!$tx || (int) $tx->userid !== $userid || ($meta->item_type ?? '') !== 'wallet_topup') {
            throw new finance_exception('err_ordernotfound');
        }
        if ($tx->status !== 'completed') {
            try {
                \local_payments\manager::verify_callback($orderid);
            } catch (\Throwable $e) {
                debugging('local_nit_finance: top-up verify failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
            $tx = $DB->get_record('local_payments_transactions', ['id' => $tx->id], '*', MUST_EXIST);
        }
        $credited = $DB->record_exists_sql(
            "SELECT 1
               FROM {nit_wallet_txn} t
               JOIN {nit_wallet} w ON w.id = t.walletid
              WHERE w.ownertype = :type AND w.userid = :userid
                AND t.kind = :kind AND t.itemtype = :itemtype AND t.itemid = :itemid",
            ['type' => wallets::STUDENT, 'userid' => $userid, 'kind' => wallets::KIND_TOPUP,
             'itemtype' => 'payment', 'itemid' => $tx->id]);
        return [
            'order_id' => (string) $tx->order_id,
            'status' => (string) $tx->status,
            'paid' => $tx->status === 'completed',
            'credited' => $credited,
        ] + self::money('amount', (int) round((float) $tx->amount * 100))
          + self::money('balance', wallets::balance(wallets::STUDENT, $userid))
          + ['currency' => self::CURRENCY];
    }

    /**
     * Use a code: unlocks a lesson / course, or adds wallet credit.
     *
     * @param int $userid
     * @param string $code as typed
     * @return array
     */
    public static function redeem_code(int $userid, string $code): array {
        $result = codes::redeem($userid, $code);
        $item = $result['item'];
        return [
            'type' => (string) $result['type'],
            'item' => $item ? self::item($item) : null,
            'purchaseid' => $result['purchase'] ? (int) $result['purchase']->id : 0,
        ] + self::money('amount', (int) $result['amount'])
          + self::money('balance', wallets::balance(wallets::STUDENT, $userid)) + [
            'currency' => self::CURRENCY,
            'message' => $item ? get_string('bought', 'local_nit_finance', $item->name)
                : get_string('toppedup', 'local_nit_finance', money::format((int) $result['amount'])),
        ];
    }

    /**
     * The lessons of a course sold on their own, with what the student can open,
     * plus whether the whole course is sold / owned.
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     */
    public static function get_course_lesson_prices(int $userid, int $courseid): array {
        global $DB;
        $course = $courseid && $courseid != SITEID ? $DB->get_record('course', ['id' => $courseid]) : null;
        if (!$course) {
            throw new finance_exception('err_coursenotfound');
        }
        $context = \context_course::instance($course->id);
        if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context, $userid)) {
            throw new finance_exception('err_coursenotfound');
        }
        $state = access::course_state($userid, (int) $course->id);
        $seehidden = has_capability('moodle/course:viewhiddenactivities', $context, $userid);
        $modinfo = get_fast_modinfo($course, $userid);
        $lessons = [];
        foreach ($modinfo->get_sections() as $sectionnum => $cmids) {
            foreach ($cmids as $cmid) {
                $price = (int) ($state['prices'][$cmid] ?? 0);
                if ($price <= 0) {
                    continue;
                }
                $cm = $modinfo->get_cm($cmid);
                if ($cm->deletioninprogress || (!$cm->visible && !$seehidden)) {
                    continue;
                }
                $open = access::cm_open($state, (int) $cmid);
                $lessons[] = [
                    'cmid' => (int) $cmid,
                    'name' => format_string($cm->name, true, ['context' => $cm->context]),
                    'modname' => $cm->modname,
                    'section' => (int) $sectionnum,
                ] + self::money('price', $price) + [
                    'owned' => !empty($state['owned'][$cmid]),
                    'open' => $open,
                    'can_buy' => !$open,
                    'preview_seconds' => preview::for_user($userid, $cm, $state),
                ];
            }
        }
        $courseprice = null;
        if ($state['coursepriced']) {
            try {
                $p = \local_payments\price_resolver::resolve((int) $course->id, $userid);
                $courseprice = self::money('price', (int) round((float) $p->price * 100)) + ['currency' => (string) $p->currency];
            } catch (\Throwable $e) {
                $courseprice = null;
            }
        }
        return [
            'course' => [
                'id' => (int) $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
                'sold' => (bool) $state['coursepriced'],
                'price' => $courseprice,
                'owned' => (bool) $state['ownscourse'],
                'enrolled' => is_enrolled($context, $userid, '', true),
                'staff' => (bool) $state['staff'],
                'lessons_only' => (bool) $state['lessononly'],
            ],
        ] + self::money('balance', wallets::balance(wallets::STUDENT, $userid)) + [
            'currency' => self::CURRENCY,
            'lessons' => $lessons,
        ];
    }

    /**
     * Can the student open one lesson, and can they pay for it from the wallet?
     *
     * @param int $userid
     * @param int $cmid
     * @return array
     */
    public static function get_lesson_access(int $userid, int $cmid): array {
        [$course, $cm] = self::visible_cm($cmid, $userid);
        $state = access::course_state($userid, (int) $course->id);
        $open = access::cm_open($state, (int) $cm->id);
        $owned = !empty($state['owned'][$cm->id]);
        $context = \context_course::instance($course->id);
        if ($open && ($owned || $state['ownscourse']) && !is_enrolled($context, $userid, '', true)) {
            // Bought, but the enrolment after the payment did not happen: retry it (as buy.php does).
            purchases::enrol_after_payment($userid, (int) $course->id);
        }
        $price = (int) ($state['prices'][$cm->id] ?? 0);
        $balance = wallets::balance(wallets::STUDENT, $userid);
        return [
            'cmid' => (int) $cm->id,
            'name' => format_string($cm->name, true, ['context' => $cm->context]),
            'modname' => $cm->modname,
            'courseid' => (int) $course->id,
            'coursename' => format_string($course->fullname, true, ['context' => $context]),
            'priced' => $price > 0,
        ] + self::money('price', $price) + [
            'currency' => self::CURRENCY,
            'open' => $open,
            'owned' => $owned,
            'owns_course' => (bool) $state['ownscourse'],
            'staff' => (bool) $state['staff'],
            'enrolled' => is_enrolled($context, $userid, '', true),
        ] + self::money('balance', $balance) + [
            'can_afford' => !$open && $price > 0 && $balance >= $price,
        ] + self::money('shortfall', !$open && $price > 0 ? max(0, $price - $balance) : 0) + [
            'course_sold' => (bool) $state['coursepriced'],
            'topup_available' => output::online_payment_available(),
            // Free seconds the app may play via get_lesson_preview (0 = no preview for this user).
            'preview_seconds' => preview::for_user($userid, $cm, $state),
        ];
    }

    /**
     * Play the free preview of a paid video lesson: the player data plus the
     * seconds after which the app must stop the video and offer to buy.
     *
     * @param int $userid
     * @param int $cmid
     * @return array cmid, provider (vimeo|vdocipher), preview_seconds, videoid,
     *               then embedurl (vimeo) | otp, playbackInfo, watermark, ttl (vdocipher)
     */
    public static function get_lesson_preview(int $userid, int $cmid): array {
        global $DB;
        [, $cm] = self::visible_cm($cmid, $userid);
        $seconds = preview::for_user($userid, $cm);
        if ($seconds <= 0) {
            throw new finance_exception('err_nopreview');
        }
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        return ['cmid' => (int) $cm->id] + preview::playback($cm, $user, $seconds);
    }

    /**
     * Buy one lesson with the student wallet.
     *
     * @param int $userid
     * @param int $cmid
     * @return array
     */
    public static function buy_lesson(int $userid, int $cmid): array {
        [$course, $cm] = self::visible_cm($cmid, $userid);
        $state = access::course_state($userid, (int) $course->id);
        if (access::cm_open($state, (int) $cm->id) && ($state['prices'][$cm->id] ?? 0) > 0) {
            throw new finance_exception('err_alreadyowned');
        }
        $purchase = purchases::buy_with_wallet($userid, (int) $cm->id);
        return [
            'purchaseid' => (int) $purchase->id,
            'cmid' => (int) $cm->id,
            'courseid' => (int) $course->id,
            'name' => format_string($cm->name, true, ['context' => $cm->context]),
        ] + self::money('amount', (int) $purchase->amount_minor)
          + self::money('balance', wallets::balance(wallets::STUDENT, $userid)) + [
            'currency' => self::CURRENCY,
            'enrolled' => is_enrolled(\context_course::instance($course->id), $userid, '', true),
            'open' => true,
        ];
    }

    // ── Teacher ──────────────────────────────────────────────────────────────

    /**
     * Fail unless the user is a teacher (or already earned money), as earnings.php.
     *
     * @param int $userid
     * @return void
     */
    public static function require_teacher(int $userid): void {
        if (!earnings_page::is_teacher($userid)) {
            throw new finance_exception('err_notateacher');
        }
    }

    /**
     * Payout methods a teacher can choose.
     *
     * @return array
     */
    private static function methods(): array {
        $out = [];
        foreach (earnings_page::METHODS as $method) {
            $out[] = ['value' => $method, 'label' => self::str('earn_method_' . $method)];
        }
        return $out;
    }

    /**
     * The teacher's money: the withdrawable figures (earnings − withdrawals) and
     * the teacher wallet ledger balance.
     *
     * @param int $userid
     * @return array
     */
    public static function get_teacher_wallet(int $userid): array {
        self::require_teacher($userid);
        $s = wallet::teacher($userid);
        $available = (int) $s['available_balance_minor'];
        return ['teacherid' => $userid]
            + self::money('available_balance', $available)
            + self::money('total_earned', (int) $s['total_earned_minor'])
            + self::money('pending_withdrawals', (int) $s['pending_withdrawals_minor'])
            + self::money('total_withdrawn', (int) $s['total_withdrawn_minor'])
            + self::money('wallet_balance', wallets::balance(wallets::TEACHER, $userid)) + [
                'currency' => self::CURRENCY,
                'teacher_percent' => teacher_share::percent_for($userid),
                'can_withdraw' => $available > 0,
                'methods' => self::methods(),
            ];
    }

    /**
     * The teacher wallet ledger, newest first.
     *
     * @param int $userid
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function get_teacher_wallet_history(int $userid, int $page, int $perpage): array {
        self::require_teacher($userid);
        return self::ledger(wallets::TEACHER, $userid, $page, $perpage);
    }

    /**
     * The teacher's earnings (lessons sold on their own and live lessons), newest first.
     *
     * @param int $userid
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function get_my_earnings(int $userid, int $page, int $perpage): array {
        global $DB;
        self::require_teacher($userid);
        $total = earning::count_records(['teacherid' => $userid]);
        $rows = array_values(earning::get_records(['teacherid' => $userid], 'timecreated', 'DESC',
            $page * $perpage, $perpage));
        $names = self::names(array_map(fn($e) => (int) $e->get('studentid'), $rows));
        $haslessons = $DB->get_manager()->table_exists('nit_lesson');
        $items = [];
        $out = [];
        foreach ($rows as $e) {
            $source = (string) $e->get('source') ?: earning::SOURCE_CM;
            $lessonid = (int) $e->get('lessonid');
            $key = $source . ':' . $lessonid;
            if (!isset($items[$key])) {
                $items[$key] = ['name' => '', 'courseid' => 0, 'coursename' => ''];
                if ($source === earning::SOURCE_LESSON) {
                    $subject = $haslessons ? (string) $DB->get_field('nit_lesson', 'subject', ['id' => $lessonid]) : '';
                    $label = get_string('earn_source_lesson', 'local_nit_finance', $lessonid);
                    $items[$key]['name'] = $subject !== '' ? $label . ' · ' . format_string($subject) : $label;
                } else {
                    try {
                        $item = catalog::item(catalog::CM, $lessonid);
                        $items[$key] = ['name' => $item->name, 'courseid' => $item->courseid, 'coursename' => $item->coursename];
                    } catch (\moodle_exception $ex) {
                        $items[$key]['name'] = get_string('itemtype_cm', 'local_nit_finance');
                    }
                }
            }
            $studentid = (int) $e->get('studentid');
            $out[] = [
                'id' => (int) $e->get('id'),
                'source' => $source,
                'lessonid' => $lessonid,
                'cmid' => $source === earning::SOURCE_CM ? $lessonid : 0,
                'item_name' => $items[$key]['name'],
                'courseid' => (int) $items[$key]['courseid'],
                'coursename' => $items[$key]['coursename'],
                'studentid' => $studentid,
                'student_name' => $names[$studentid] ?? '',
            ] + self::money('value', (int) $e->get('flex_value_minor'))
              + self::money('teacher_amount', (int) $e->get('teacher_amount_minor')) + [
                'teacher_percent' => (int) $e->get('teacher_percent'),
                'currency' => self::CURRENCY,
                'status' => (string) $e->get('status'),
                'status_label' => self::str('earn_status_' . $e->get('status')),
                'timecreated' => (int) $e->get('timecreated'),
            ];
        }
        return ['total' => $total, 'page' => $page, 'perpage' => $perpage, 'earnings' => $out];
    }

    /**
     * A withdrawal as the app sees it.
     *
     * @param array $w from withdrawal_service
     * @param array $names teacher names by id
     * @return array
     */
    private static function withdrawal(array $w, array $names = []): array {
        $teacherid = (int) $w['teacherid'];
        $method = (string) $w['method'];
        return [
            'id' => (int) $w['id'],
            'teacherid' => $teacherid,
            'teacher_name' => $w['teacher_name'] ?? ($names[$teacherid] ?? ''),
        ] + self::money('amount', (int) $w['amount_minor']) + [
            'currency' => self::CURRENCY,
            'method' => $method,
            'method_label' => self::str('earn_method_' . (in_array($method, earnings_page::METHODS, true) ? $method : 'bank')),
            'account' => (string) ($w['account'] ?? ''),
            'reference' => (string) ($w['reference'] ?? ''),
            'status' => (string) $w['status'],
            'status_label' => self::str('status_' . $w['status']),
            'reason' => (string) ($w['reason'] ?? ''),
            'timecreated' => (int) $w['timecreated'],
            'timeprocessed' => (int) ($w['timeprocessed'] ?? 0),
        ];
    }

    /**
     * A teacher asks to be paid out.
     *
     * @param int $userid
     * @param string $amount pounds as typed
     * @param string $method bank | wallet | cash ('' = bank)
     * @param string $account payout details
     * @return array the request + the new available balance
     */
    public static function request_withdrawal(int $userid, string $amount, string $method, string $account): array {
        self::require_teacher($userid);
        $minor = self::parse_amount($amount);
        $method = $method === '' ? 'bank' : $method;
        if (!in_array($method, earnings_page::METHODS, true)) {
            throw new finance_exception('err_badmethod');
        }
        $account = \core_text::substr(trim($account), 0, 255);
        $w = wallet::request_withdrawal($userid, $minor, $method, $account);
        return [
            'withdrawal' => self::withdrawal($w, self::names([$userid])),
        ] + self::money('available_balance', wallet::available_balance($userid)) + ['currency' => self::CURRENCY];
    }

    /**
     * The teacher's own withdrawal requests, newest first.
     *
     * @param int $userid
     * @return array
     */
    public static function get_my_withdrawals(int $userid): array {
        self::require_teacher($userid);
        $names = self::names([$userid]);
        return ['withdrawals' => array_map(fn($w) => self::withdrawal($w, $names), wallet::teacher_withdrawals($userid))];
    }

    // ── Admin (local/nit_finance:manage, checked by api.php) ────────────────

    /**
     * Platform wallet, totals per wallet type, withdrawal queue, codes and sales.
     *
     * @return array
     */
    public static function get_finance_summary(): array {
        global $DB;
        $wallets = [];
        foreach ([wallets::STUDENT, wallets::TEACHER] as $type) {
            $wallets[$type] = ['count' => 0] + self::money('total', 0);
        }
        $rows = $DB->get_records_sql(
            "SELECT ownertype, COUNT(1) AS n, COALESCE(SUM(balance_minor), 0) AS total
               FROM {nit_wallet}
           GROUP BY ownertype");
        foreach ($rows as $r) {
            if ($r->ownertype !== wallets::PLATFORM) {
                $wallets[$r->ownertype] = ['count' => (int) $r->n] + self::money('total', (int) $r->total);
            }
        }

        $withdrawals = [];
        foreach (self::WITHDRAWAL_STATUSES as $st) {
            $withdrawals[$st] = ['count' => 0] + self::money('amount', 0);
        }
        foreach ($DB->get_records_sql("SELECT status, COUNT(1) AS n, COALESCE(SUM(amount_minor), 0) AS total
                                         FROM {nit_withdrawal} GROUP BY status") as $r) {
            $withdrawals[$r->status] = ['count' => (int) $r->n] + self::money('amount', (int) $r->total);
        }

        $codecounts = [codes::STATUS_ACTIVE => 0, codes::STATUS_USED => 0, codes::STATUS_DISABLED => 0];
        foreach ($DB->get_records_sql("SELECT status, COUNT(1) AS n FROM {nit_access_code} GROUP BY status") as $r) {
            $codecounts[$r->status] = (int) $r->n;
        }

        $sales = [];
        foreach ([purchases::METHOD_WALLET, purchases::METHOD_CODE] as $m) {
            $sales[$m] = ['count' => 0] + self::money('amount', 0);
        }
        foreach ($DB->get_records_sql("SELECT method, COUNT(1) AS n, COALESCE(SUM(amount_minor), 0) AS total
                                         FROM {nit_purchase} WHERE status = 'active' GROUP BY method") as $r) {
            $sales[$r->method] = ['count' => (int) $r->n] + self::money('amount', (int) $r->total);
        }

        // The Flex plugin (when installed) supplies money-in and unconsumed Flex value.
        $payments = 0;
        $undistributed = 0;
        try {
            if (class_exists('\local_nit_flex\api\flex') && method_exists('\local_nit_flex\api\flex', 'money_totals')) {
                $totals = \local_nit_flex\api\flex::money_totals();
                $payments = (int) ($totals['payments_minor'] ?? 0);
                $undistributed = (int) ($totals['undistributed_minor'] ?? 0);
            }
        } catch (\Throwable $e) {
            debugging('local_nit_finance: flex totals unavailable: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        $report = [];
        foreach (wallet::platform($payments, $undistributed) as $key => $minor) {
            $report += self::money(preg_replace('/_minor$/', '', $key), (int) $minor);
        }

        return [
            'currency' => self::CURRENCY,
            'platform_wallet' => self::money('balance', wallets::balance(wallets::PLATFORM)),
            'wallets' => $wallets,
            'platform_report' => $report,
            'withdrawals' => $withdrawals,
            'codes' => $codecounts,
            'sales' => $sales,
        ];
    }

    /**
     * The withdrawal queue.
     *
     * @param string $status '' = all
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function list_withdrawals(string $status, int $page, int $perpage): array {
        if ($status !== '' && !in_array($status, self::WITHDRAWAL_STATUSES, true)) {
            throw new finance_exception('err_badstatus');
        }
        $all = wallet::list_withdrawals($status);
        $names = self::names(array_column($all, 'teacherid'));
        return [
            'total' => count($all),
            'page' => $page,
            'perpage' => $perpage,
            'withdrawals' => array_map(fn($w) => self::withdrawal(['teacher_name' => $names[(int) $w['teacherid']] ?? ''] + $w),
                array_slice($all, $page * $perpage, $perpage)),
        ];
    }

    /**
     * Approve, reject (reason required) or pay (reference) a withdrawal.
     *
     * @param int $adminid
     * @param int $id
     * @param string $action
     * @param string $reason
     * @param string $reference
     * @return array
     */
    public static function process_withdrawal(int $adminid, int $id, string $action, string $reason = '',
            string $reference = ''): array {
        if (!in_array($action, self::WITHDRAWAL_ACTIONS, true)) {
            throw new finance_exception('err_badaction');
        }
        $opts = $action === 'reject' ? ['reason' => $reason] : ($action === 'pay' ? ['reference' => $reference] : []);
        $w = wallet::process_withdrawal($adminid, $id, $action, $opts);
        return ['withdrawal' => self::withdrawal($w, self::names([(int) $w['teacherid']]))];
    }

    /**
     * Student or teacher wallets, biggest balance first.
     *
     * @param string $type student | teacher
     * @param string $q part of the name, email, username or phone
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function list_wallets(string $type, string $q, int $page, int $perpage): array {
        global $DB;
        if (!in_array($type, [wallets::STUDENT, wallets::TEACHER], true)) {
            throw new finance_exception('err_badwallettype');
        }
        $where = 'w.ownertype = :type';
        $params = ['type' => $type];
        $q = trim($q);
        if ($q !== '') {
            $like = [];
            foreach ([$DB->sql_fullname('u.firstname', 'u.lastname'), 'u.email', 'u.username', 'u.phone1', 'u.phone2']
                    as $i => $field) {
                $like[] = $DB->sql_like($field, ':q' . $i, false, false);
                $params['q' . $i] = '%' . $DB->sql_like_escape($q) . '%';
            }
            $where .= ' AND (' . implode(' OR ', $like) . ')';
        }
        $from = "FROM {nit_wallet} w JOIN {user} u ON u.id = w.userid AND u.deleted = 0 WHERE $where";
        $total = $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $names = implode(', ', array_map(fn($f) => 'u.' . $f, \core_user\fields::get_name_fields()));
        $rows = $DB->get_records_sql(
            "SELECT w.id AS walletid, w.userid, w.balance_minor, w.timemodified, u.id, $names, u.email
               $from
           ORDER BY w.balance_minor DESC, w.timemodified DESC, w.id DESC", $params, $page * $perpage, $perpage);
        $out = [];
        foreach ($rows as $r) {
            $row = [
                'userid' => (int) $r->userid,
                'fullname' => fullname($r),
                'email' => (string) $r->email,
            ] + self::money('balance', (int) $r->balance_minor) + [
                'currency' => self::CURRENCY,
                'timemodified' => (int) $r->timemodified,
            ];
            if ($type === wallets::TEACHER) {
                $row['teacher_percent'] = teacher_share::percent_for((int) $r->userid);
                $row += self::money('available_balance', wallet::available_balance((int) $r->userid));
            }
            $out[] = $row;
        }
        return ['type' => $type, 'total' => $total, 'page' => $page, 'perpage' => $perpage, 'wallets' => $out];
    }

    /**
     * Any wallet's ledger (platform, a teacher's, a student's).
     *
     * @param string $type
     * @param int $userid ignored for the platform
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function get_wallet_ledger(string $type, int $userid, int $page, int $perpage): array {
        if (!in_array($type, [wallets::PLATFORM, wallets::TEACHER, wallets::STUDENT], true)) {
            throw new finance_exception('err_badwallettype');
        }
        $fullname = '';
        if ($type !== wallets::PLATFORM) {
            $user = $userid > 0 ? \core_user::get_user($userid) : null;
            if (!$user || $user->deleted) {
                throw new finance_exception('err_usernotfound');
            }
            $fullname = fullname($user);
        }
        return ['fullname' => $fullname] + self::ledger($type, $userid, $page, $perpage);
    }

    /**
     * Add credit to (positive) or take credit from (negative) a student wallet, as
     * the admin wallets page does. Taking more than the balance is refused.
     *
     * @param int $userid the student
     * @param string $amount signed pounds, e.g. "50" or "-20.5"
     * @param string $note
     * @return array
     */
    public static function adjust_wallet(int $userid, string $amount, string $note): array {
        $raw = trim($amount);
        $sign = 1;
        if ($raw !== '' && ($raw[0] === '-' || $raw[0] === '+')) {
            $sign = $raw[0] === '-' ? -1 : 1;
            $raw = ltrim(substr($raw, 1));
        }
        $minor = money::to_minor($raw);
        if (!$minor) {
            throw new finance_exception('err_amountnonzero');
        }
        $user = $userid > 0 ? \core_user::get_user($userid) : null;
        if (!$user || $user->deleted || isguestuser($user)) {
            throw new finance_exception('err_usernotfound');
        }
        $signed = $sign * $minor;
        $note = \core_text::substr(trim($note), 0, 255);
        $line = wallets::locked('student_' . $userid, fn() => wallets::move(wallets::STUDENT, $userid, $signed,
            $signed > 0 ? wallets::KIND_TOPUP : wallets::KIND_ADJUSTMENT, ['note' => $note]));
        return [
            'userid' => $userid,
            'fullname' => fullname($user),
            'line' => self::ledger_line($line),
        ] + self::money('balance', wallets::balance(wallets::STUDENT, $userid)) + ['currency' => self::CURRENCY];
    }

    /**
     * One code row for the admin.
     *
     * @param \stdClass $row nit_access_code row (+ used_ name fields)
     * @param array $labels cache of item labels
     * @return array
     */
    private static function code_row(\stdClass $row, array &$labels): array {
        $key = $row->itemtype . ':' . $row->itemid;
        if (!isset($labels[$key])) {
            if ($row->itemtype === catalog::WALLET) {
                $labels[$key] = self::str('itemtype_wallet');
            } else {
                try {
                    $item = catalog::item($row->itemtype, (int) $row->itemid);
                    $labels[$key] = self::str('itemtype_' . $row->itemtype) . ': '
                        . ($row->itemtype === catalog::CM ? $item->coursename . ' › ' : '') . $item->name;
                } catch (\moodle_exception $e) {
                    $labels[$key] = self::str('itemtype_' . $row->itemtype) . ' #' . $row->itemid;
                }
            }
        }
        $usedby = '';
        if ((int) $row->usedby) {
            $u = new \stdClass();
            foreach (\core_user\fields::get_name_fields() as $f) {
                $u->$f = $row->{'used_' . $f} ?? '';
            }
            $usedby = fullname($u);
        }
        return [
            'id' => (int) $row->id,
            'code' => (string) $row->code,
            'itemtype' => (string) $row->itemtype,
            'itemid' => (int) $row->itemid,
            'courseid' => (int) $row->courseid,
            'item_label' => $labels[$key],
        ] + self::money('amount', (int) $row->amount_minor) + [
            'currency' => self::CURRENCY,
            'status' => (string) $row->status,
            'status_label' => self::str('codestatus_' . $row->status),
            'batch' => (string) $row->batch,
            'note' => (string) $row->note,
            'timeexpires' => (int) $row->timeexpires,
            'expired' => (int) $row->timeexpires > 0 && (int) $row->timeexpires < time(),
            'createdby' => (int) $row->createdby,
            'usedby' => (int) $row->usedby,
            'usedby_name' => $usedby,
            'timeused' => (int) $row->timeused,
            'purchaseid' => (int) $row->purchaseid,
            'timecreated' => (int) $row->timecreated,
        ];
    }

    /**
     * Codes, newest first, filtered like the admin codes page.
     *
     * @param array $filters status, batch, q
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function list_codes(array $filters, int $page, int $perpage): array {
        $found = codes::search($filters, $page, $perpage);
        $labels = [];
        $out = [];
        foreach ($found['rows'] as $row) {
            $out[] = self::code_row($row, $labels);
        }
        return ['total' => $found['total'], 'page' => $page, 'perpage' => $perpage, 'codes' => $out];
    }

    /**
     * An expiry the admin sent: 0/'' = never, a Unix time, or a date (end of that day).
     *
     * @param string $raw
     * @return int
     */
    public static function parse_expiry(string $raw): int {
        $raw = trim($raw);
        if ($raw === '' || $raw === '0') {
            return 0;
        }
        if (ctype_digit($raw)) {
            $time = (int) $raw;
        } else if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            // Same as the web form: the chosen day, until its last second.
            $time = make_timestamp((int) $m[1], (int) $m[2], (int) $m[3]) + DAYSECS - 1;
        } else {
            throw new finance_exception('err_badexpiry');
        }
        if ($time <= time()) {
            throw new finance_exception('err_badexpiry');
        }
        return $time;
    }

    /**
     * Make a batch of codes, as the admin codes page does.
     *
     * @param int $adminid
     * @param string $type cm | course | wallet
     * @param int $itemid cmid / courseid (ignored for wallet)
     * @param int $count
     * @param string $amount pounds; '' for a lesson = its price
     * @param string $expires '' / 0 / Unix time / YYYY-MM-DD
     * @param string $note
     * @return array
     */
    public static function generate_codes(int $adminid, string $type, int $itemid, int $count, string $amount,
            string $expires, string $note): array {
        if (!in_array($type, [catalog::CM, catalog::COURSE, catalog::WALLET], true)
                || ($type !== catalog::WALLET && $itemid <= 0)) {
            throw new finance_exception('err_chooseitem');
        }
        if ($type !== catalog::WALLET) {
            catalog::item($type, $itemid); // Validates the item.
        }
        $amount = trim($amount);
        if ($amount === '') {
            if ($type !== catalog::CM) {
                throw new finance_exception('err_badprice');
            }
            $minor = catalog::price($itemid);
        } else {
            $minor = money::to_minor($amount);
            if ($minor === null) {
                throw new finance_exception('err_badprice');
            }
        }
        $timeexpires = self::parse_expiry($expires);
        $made = codes::generate($type, $type === catalog::WALLET ? 0 : $itemid, $count, $minor, $timeexpires, $note, $adminid);
        return [
            'batch' => (string) $made[0]->batch,
            'count' => count($made),
            'itemtype' => $type,
            'itemid' => $type === catalog::WALLET ? 0 : $itemid,
        ] + self::money('amount', $minor) + [
            'currency' => self::CURRENCY,
            'timeexpires' => $timeexpires,
            'codes' => array_map(fn($r) => ['id' => (int) $r->id, 'code' => (string) $r->code], $made),
        ];
    }

    /**
     * Stop an unused code from working.
     *
     * @param int $id
     * @return array the code
     */
    public static function disable_code(int $id): array {
        global $DB;
        $row = $DB->get_record('nit_access_code', ['id' => $id]);
        if (!$row) {
            throw new finance_exception('err_codenotfound');
        }
        if ($row->status !== codes::STATUS_ACTIVE) {
            throw new finance_exception('err_codenotactive');
        }
        codes::disable($id);
        $found = codes::search(['q' => $row->code], 0, 1);
        $labels = [];
        return ['code' => self::code_row($found['rows'][0], $labels)];
    }

    /**
     * Set (or, with 0 / '', remove) a lesson's own price. Needs
     * local/nit_finance:setprice in the lesson's course, like the activity form.
     *
     * @param int $userid
     * @param int $cmid
     * @param string $price pounds
     * @return array
     */
    public static function set_lesson_price(int $userid, int $cmid, string $price): array {
        global $DB;
        $row = $DB->get_record('course_modules', ['id' => $cmid], 'id, course, deletioninprogress');
        if (!$row || $row->deletioninprogress || !$DB->record_exists('course', ['id' => $row->course])) {
            throw new finance_exception('err_itemnotfound');
        }
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        require_capability('local/nit_finance:setprice', \context_course::instance($course->id), $userid);
        $price = trim($price);
        $minor = $price === '' ? 0 : money::to_minor($price);
        if ($minor === null) {
            throw new finance_exception('err_badprice');
        }
        catalog::set_price((int) $cm->id, (int) $course->id, $minor);
        return [
            'cmid' => (int) $cm->id,
            'courseid' => (int) $course->id,
            'name' => format_string($cm->name, true, ['context' => $cm->context]),
            'priced' => $minor > 0,
        ] + self::money('price', catalog::price((int) $cm->id)) + ['currency' => self::CURRENCY];
    }
}

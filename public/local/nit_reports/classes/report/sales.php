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

namespace local_nit_reports\report;

use local_nit_reports\data;
use local_nit_reports\scope;

/**
 * Sales and revenue: online payments (local_payments_transactions) in the period —
 * every transaction, or grouped by item, by payment method or by month — with
 * cards for the revenue, refunds and failures, and for the money that did not go
 * through the gateway (codes sold, offline package payments, subscriptions an
 * admin assigned). Revenue is what was sold: completed (and partially refunded) online payments
 * plus purchases paid from wallet balance, less refunds back to the wallet; a wallet top-up is
 * not revenue (it counts when spent) and is shown apart, with all the money collected online.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sales extends base {

    /** Statuses that count as revenue. */
    private const PAID = ['completed', 'partially_refunded'];

    /** LIKE pattern of a wallet top-up payment (local_payments metadata item_type). */
    private const TOPUP_LIKE = '%"item\_type":"wallet\_topup"%';

    /** The currency of wallet balances (amounts in piastres, see data::minor()). */
    private const WALLET_CURRENCY = 'EGP';

    public static function key(): string {
        return 'sales';
    }

    public static function level(): string {
        return scope::SITE;
    }

    public static function available(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('local_payments_transactions');
    }

    public function filters(): array {
        return ['period', 'course', 'status', 'q'];
    }

    public function view_options(): array {
        return ['list' => self::str('view_transactions'), 'item' => self::str('view_byitem'),
            'provider' => self::str('view_byprovider'), 'month' => self::str('view_bymonth')];
    }

    public function status_options(): array {
        $out = [];
        foreach (['completed', 'pending', 'failed', 'cancelled', 'expired', 'refunded', 'partially_refunded'] as $st) {
            $out[$st] = self::str('pay_' . $st);
        }
        return $out;
    }

    public function columns(): array {
        $money = ['count' => self::str('col_sales'), 'revenue' => self::str('col_revenue')];
        switch ($this->view()) {
            case 'item':
                return ['type' => self::str('col_itemtype'), 'item' => self::str('col_item')] + $money
                    + ['refunded' => self::str('col_refunded')];
            case 'provider':
                return ['provider' => self::str('col_paymethod')] + $money;
            case 'month':
                return ['month' => self::str('col_month')] + $money;
        }
        return [
            'date' => get_string('date'),
            'order' => self::str('col_order'),
            'student' => self::str('col_student'),
            'email' => get_string('email'),
            'phone' => get_string('phone1'),
            'type' => self::str('col_itemtype'),
            'item' => self::str('col_item'),
            'provider' => self::str('col_paymethod'),
            'method' => self::str('col_paytype'),
            'amount' => self::str('col_amount'),
            'original' => self::str('col_originalamount'),
            'discount' => self::str('col_discount'),
            'coupon' => self::str('col_coupon'),
            'offer' => self::str('item_offer'),
            'status' => get_string('status'),
            'reference' => self::str('col_reference'),
            'country' => get_string('country'),
            'reason' => self::str('col_failreason'),
        ];
    }

    public function sortable(): array {
        global $DB;
        if ($this->view() !== 'list') {
            return array_fill_keys(array_keys($this->columns()), true);
        }
        return [
            'date' => 't.timecreated',
            'order' => 't.order_id',
            'student' => $DB->sql_fullname('u.firstname', 'u.lastname'),
            'email' => 'u.email',
            'phone' => 'u.phone1',
            'provider' => 'p.display_name',
            'method' => 't.payment_method_type',
            'amount' => 't.amount',
            'original' => 't.original_amount',
            'discount' => 'COALESCE(t.original_amount, t.amount) - t.amount',
            'status' => 't.status',
            'country' => 't.country',
        ];
    }

    /**
     * Offer names by id (local_nit_commerce), multilang resolved.
     *
     * @param int[] $ids
     * @return array<int,string>
     */
    private static function offer_names(array $ids): array {
        global $DB;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids || !$DB->get_manager()->table_exists('nit_offer')) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'of');
        return array_map(fn($n) => self::cname($n), $DB->get_records_select_menu('nit_offer', "id $in", $params, '', 'id, name'));
    }

    /**
     * How the student paid (card, wallet, …) as a label.
     *
     * @param string|null $type payment_method_type from the gateway
     * @return string
     */
    private static function method(?string $type): string {
        $type = trim((string) $type);
        if ($type === '') {
            return '—';
        }
        $id = 'method_' . strtolower(preg_replace('/[^a-z0-9_]/i', '', $type));
        return get_string_manager()->string_exists($id, 'local_nit_reports') ? self::str($id) : $type;
    }

    /**
     * The transactions matching the filters: "FROM … WHERE …" over t, u, p.
     *
     * @param bool $paidonly ignore the status filter and keep the paid ones
     * @return array{0:string, 1:array}
     */
    private function from(bool $paidonly = false): array {
        global $DB;
        [$period, $params] = $this->f->period_sql('t.timecreated');
        $where = [$period];
        if ($paidonly) {
            [$in, $sp] = $DB->get_in_or_equal(self::PAID, SQL_PARAMS_NAMED, 'ps');
            $where[] = "t.status $in";
            $params += $sp;
            // Revenue: a wallet top-up is the student's own balance, not a sale (BUG-064); the
            // sale is counted when the balance is spent (card_walletsales).
            $where[] = $DB->sql_like('t.metadata', ':notopup', true, true, true);
            $params['notopup'] = self::TOPUP_LIKE;
        } else if ($this->f->status !== '') {
            $where[] = 't.status = :st';
            $params['st'] = $this->f->status;
        }
        if ($this->f->courseid) {
            $where[] = 't.courseid = :fcourse';
            $params['fcourse'] = $this->f->courseid;
        }
        if ($this->f->q !== '') {
            $like = '%' . $DB->sql_like_escape($this->f->q) . '%';
            [$usearch, $up] = data::user_search_sql('u', $this->f->q);
            $where[] = '(' . $DB->sql_like('t.order_id', ':sq1', false) . ' OR ' . $DB->sql_like('t.metadata', ':sq2', false)
                . " OR $usearch)";
            $params += ['sq1' => $like, 'sq2' => $like] + $up;
        }
        return ['FROM {local_payments_transactions} t
                 JOIN {user} u ON u.id = t.userid
            LEFT JOIN {local_payments_providers} p ON p.id = t.provider_id
                WHERE ' . implode(' AND ', $where), $params];
    }

    /**
     * Revenue from wallet balance in the period (BUG-064): what students bought with their
     * wallet (ledger kind "purchase"), less what went back to the wallet as a refund of such a
     * purchase (kind "refund" — an approved refund request, less its fee — or a package refund,
     * kind "adjustment", both with the purchase). The top-up itself is not revenue.
     * Same filters as the payments: period, course, student search; a status filter other
     * than "completed" leaves no wallet line.
     *
     * @return \stdClass[] id, timecreated, amount (revenue, major units, refunds negative), kind, itemtype,
     *         itemid, purchaseid, note
     */
    private function wallet_lines(): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('nit_wallet_txn')
                || !in_array($this->f->status, ['', 'completed'], true)) {
            return [];
        }
        [$period, $params] = $this->f->period_sql('wt.timecreated', 'wp');
        $where = ["(wt.kind = 'purchase'
                    OR (wt.kind IN ('adjustment') AND wt.purchaseid > 0 AND wt.amount_minor > 0)) /* RED-CHECK */", $period];
        if ($this->f->courseid) {
            $where[] = "((wt.itemtype = 'course' AND wt.itemid = :wcourse)
                        OR (wt.itemtype = 'cm' AND wt.itemid IN (SELECT cm.id FROM {course_modules} cm WHERE cm.course = :wcourse2)))";
            $params += ['wcourse' => $this->f->courseid, 'wcourse2' => $this->f->courseid];
        }
        if ($this->f->q !== '') {
            [$usearch, $up] = data::user_search_sql('u', $this->f->q);
            $where[] = $usearch;
            $params += $up;
        }
        $rows = $DB->get_records_sql("SELECT wt.id, wt.timecreated, wt.amount_minor, wt.kind, wt.itemtype, wt.itemid,
                                             wt.purchaseid, wt.note
                                        FROM {nit_wallet_txn} wt
                                        JOIN {nit_wallet} w ON w.id = wt.walletid AND w.ownertype = 'student'
                                        JOIN {user} u ON u.id = w.userid
                                       WHERE " . implode(' AND ', $where) . "
                                    ORDER BY wt.timecreated", $params);
        foreach ($rows as $r) {
            $r->amount = -((int) $r->amount_minor) / 100;
        }
        return $rows;
    }

    /**
     * The refund fee kept on online payments refunded to the wallet in the period (an approved
     * refund request, local_nit_finance). Such a payment is "refunded" and leaves the revenue;
     * the fee the platform kept stays revenue. Same filters as wallet_lines().
     *
     * @return \stdClass[] id, timecreated, amount (major units), kind ("fee"), itemtype, itemid,
     *         purchaseid (0), note (item name), provider
     */
    private function online_refund_fees(): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('nit_refund_request')
                || !in_array($this->f->status, ['', 'completed'], true)) {
            return [];
        }
        [$period, $params] = $this->f->period_sql('r.timeprocessed', 'rf');
        $where = ["r.source = 'online'", "r.status = 'approved'", 'r.fee_minor > 0', $period];
        if ($this->f->courseid) {
            $where[] = 'r.courseid = :rcourse';
            $params['rcourse'] = $this->f->courseid;
        }
        if ($this->f->q !== '') {
            [$usearch, $up] = data::user_search_sql('u', $this->f->q);
            $where[] = $usearch;
            $params += $up;
        }
        $rows = $DB->get_records_sql("SELECT r.id, r.timeprocessed AS timecreated, r.fee_minor, r.itemtype, r.itemid,
                                             r.itemname AS note, p.display_name AS provider
                                        FROM {nit_refund_request} r
                                        JOIN {user} u ON u.id = r.userid
                                   LEFT JOIN {local_payments_transactions} t ON t.id = r.transactionid
                                   LEFT JOIN {local_payments_providers} p ON p.id = t.provider_id
                                       WHERE " . implode(' AND ', $where), $params);
        foreach ($rows as $r) {
            $r->amount = ((int) $r->fee_minor) / 100;
            $r->kind = 'fee';
            $r->purchaseid = 0;
        }
        return $rows;
    }

    /**
     * Approved refund requests in the period: how many, and how much went back to wallets.
     *
     * @return array{0:int, 1:float} count, amount (major units)
     */
    private function refund_requests(): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('nit_refund_request')
                || !in_array($this->f->status, ['', 'refunded'], true)) {
            return [0, 0.0];
        }
        [$period, $params] = $this->f->period_sql('r.timeprocessed', 'rq');
        $where = ["r.status = 'approved'", $period];
        if ($this->f->courseid) {
            $where[] = 'r.courseid = :qcourse';
            $params['qcourse'] = $this->f->courseid;
        }
        if ($this->f->q !== '') {
            [$usearch, $up] = data::user_search_sql('u', $this->f->q);
            $where[] = $usearch;
            $params += $up;
        }
        $row = $DB->get_record_sql("SELECT COUNT(1) AS n, COALESCE(SUM(r.refund_minor), 0) AS total
                                      FROM {nit_refund_request} r
                                      JOIN {user} u ON u.id = r.userid
                                     WHERE " . implode(' AND ', $where), $params);
        return [(int) $row->n, ((int) $row->total) / 100];
    }

    /**
     * What a transaction sold: [type label, item name].
     *
     * @param \stdClass $t transaction (metadata, courseid)
     * @param array $coursenames
     * @return array{0:string, 1:string}
     */
    private static function item(\stdClass $t, array $coursenames): array {
        $meta = json_decode((string) $t->metadata, true) ?: [];
        $type = (string) ($meta['item_type'] ?? ((int) $t->courseid ? 'course' : 'other'));
        switch ($type) {
            case 'course':
                $name = $coursenames[(int) $t->courseid] ?? (string) ($meta['course_name'] ?? '');
                break;
            case 'subscription':
                $name = (string) ($meta['subscription_name'] ?? '');
                break;
            case 'package':
                $name = (string) ($meta['package_name'] ?? '');
                break;
            default:
                $name = '';
        }
        $label = get_string_manager()->string_exists('item_' . $type, 'local_nit_reports')
            ? self::str('item_' . $type) : $type;
        return [$label, $name !== '' ? self::cname($name) : '—'];
    }

    /**
     * Sums per currency, as display text.
     *
     * @param array $bycurrency currency => amount
     * @return string
     */
    private static function moneys(array $bycurrency): string {
        if (!$bycurrency) {
            return '0';
        }
        $out = [];
        foreach ($bycurrency as $cur => $amount) {
            $out[] = self::money((float) $amount, (string) $cur);
        }
        return implode(' + ', $out);
    }

    public function summary(): array {
        global $DB;
        [$from, $params] = $this->from(true);
        $paid = $DB->get_records_sql("SELECT t.currency, COUNT(1) AS n, SUM(t.amount) AS total $from GROUP BY t.currency",
            $params);
        [$all, $aparams] = $this->from();
        $bystatus = $DB->get_records_sql_menu("SELECT t.status, COUNT(1) $all GROUP BY t.status", $aparams);
        // Refunded: by the gateway (the whole payment), and approved refund requests (what went back to the wallet).
        $requests = $DB->get_manager()->table_exists('nit_refund_request');
        $viarequest = $requests ? " AND NOT EXISTS (SELECT 1 FROM {nit_refund_request} rr
                                                     WHERE rr.transactionid = t.id AND rr.source = 'online' AND rr.status = 'approved')" : '';
        $refunded = $DB->get_records_sql("SELECT t.currency, COUNT(1) AS n, SUM(t.amount) AS total
                                            $all AND t.status = 'refunded' $viarequest GROUP BY t.currency", $aparams);
        $refundcount = array_sum(array_map(fn($r) => (int) $r->n, $refunded));
        $refundmoney = array_map(fn($r) => (float) $r->total, $refunded);
        [$requestcount, $requestmoney] = $this->refund_requests();
        if ($requestcount) {
            $refundcount += $requestcount;
            $refundmoney[self::WALLET_CURRENCY] = ($refundmoney[self::WALLET_CURRENCY] ?? 0) + $requestmoney;
        }
        // Revenue = what was sold: online sales (no top-ups) + purchases paid from wallet balance, less refunds
        // back to the wallet; the fee kept on a refunded online payment stays revenue.
        $revenue = array_map(fn($r) => (float) $r->total, $paid);
        $wallet = array_sum(array_map(fn($r) => $r->amount, $this->wallet_lines()));
        $fees = array_sum(array_map(fn($r) => $r->amount, $this->online_refund_fees()));
        if ($wallet + $fees != 0) {
            $revenue[self::WALLET_CURRENCY] = ($revenue[self::WALLET_CURRENCY] ?? 0) + $wallet + $fees;
        }
        $cards = [
            ['id' => 'card_paidcount', 'label' => self::str('card_paidcount'), 'value' => self::num(array_sum(array_map(fn($r) => $r->n, $paid)))],
            ['id' => 'card_revenue', 'label' => self::str('card_revenue'), 'value' => self::moneys($revenue)],
            ['id' => 'card_refunded', 'label' => self::str('card_refunded'), 'value' => self::num($refundcount) . ' — '
                . self::moneys($refundmoney)],
            ['id' => 'card_failed', 'label' => self::str('card_failed'), 'value' => self::num(($bystatus['failed'] ?? 0) + ($bystatus['cancelled'] ?? 0)
                + ($bystatus['expired'] ?? 0))],
        ];

        // Money that did not go through the gateway.
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('nit_wallet_txn')) {
            // The wallet part of the revenue: courses, lessons, subscriptions and packages paid from
            // a student's wallet balance, less refunds back to the wallet.
            $cards[] = ['id' => 'card_walletsales', 'label' => self::str('card_walletsales'),
                'value' => data::minor((int) round($wallet * 100))];
        }
        if ($dbman->table_exists('nit_access_code')) {
            // A code that only adds balance to the wallet is not a sale (the purchase from the wallet is).
            [$period, $pp] = $this->f->period_sql('timeused');
            $codes = (int) $DB->get_field_sql("SELECT COALESCE(SUM(amount_minor), 0) FROM {nit_access_code}
                                                WHERE status = 'used' AND itemtype <> 'wallet' AND $period", $pp);
            $cards[] = ['id' => 'card_codesales', 'label' => self::str('card_codesales'), 'value' => data::minor($codes)];
        }
        // Wallet top-ups paid online: shown apart, not revenue.
        [$all, $aparams] = $this->from();
        $topups = $DB->get_records_sql("SELECT t.currency, COUNT(1) AS n, SUM(t.amount) AS total $all
                                           AND t.status IN ('completed', 'partially_refunded')
                                           AND " . $DB->sql_like('t.metadata', ':istopup') . "
                                      GROUP BY t.currency", $aparams + ['istopup' => self::TOPUP_LIKE]);
        $cards[] = ['id' => 'card_topups', 'label' => self::str('card_topups'),
            'value' => self::num(array_sum(array_map(fn($r) => $r->n, $topups))) . ' — '
                . self::moneys(array_map(fn($r) => $r->total, $topups))];
        // All the money that reached the gateway: online sales + top-ups.
        $collected = array_map(fn($r) => (float) $r->total, $paid);
        foreach ($topups as $cur => $r) {
            $collected[$cur] = ($collected[$cur] ?? 0) + (float) $r->total;
        }
        $cards[] = ['id' => 'card_collected', 'label' => self::str('card_collected'), 'value' => self::moneys($collected)];
        if ($dbman->table_exists('nit_payment')) {
            [$period, $pp] = $this->f->period_sql('timecreated');
            $offline = (int) $DB->get_field_sql("SELECT COALESCE(SUM(amount_minor), 0) FROM {nit_payment}
                WHERE method IN ('offline', 'bank', 'cash') AND status = 'success' AND $period", $pp);
            $cards[] = ['id' => 'card_offlinepackages', 'label' => self::str('card_offlinepackages'), 'value' => data::minor($offline)];
        }
        if ($dbman->table_exists('nit_sub_purchase')) {
            [$period, $pp] = $this->f->period_sql('timecreated');
            $assigned = (float) $DB->get_field_sql("SELECT COALESCE(SUM(price_paid), 0) FROM {nit_sub_purchase}
                WHERE source = 'admin_assigned' AND $period", $pp);
            $cards[] = ['id' => 'card_assignedsubs', 'label' => self::str('card_assignedsubs'), 'value' => self::money($assigned)];
        }
        return $cards;
    }

    public function chart(): ?\core\chart_base {
        global $DB;
        [$from, $params] = $this->from(true);
        $rows = array_values($DB->get_records_sql("SELECT t.id, t.timecreated, t.amount $from ORDER BY t.timecreated",
            $params));
        // Revenue includes what was paid from wallet balance (less refunds) and the fees kept on refunds.
        $rows = array_merge($rows, array_values($this->wallet_lines()), array_values($this->online_refund_fees()));
        if (!$rows) {
            return null;
        }
        usort($rows, fn($a, $b) => (int) $a->timecreated <=> (int) $b->timecreated);
        $first = reset($rows)->timecreated;
        $last = end($rows)->timecreated;
        $daily = ($last - $first) <= 62 * DAYSECS;
        $sums = [];
        foreach ($rows as $r) {
            $label = userdate((int) $r->timecreated, $daily ? '%Y-%m-%d' : '%Y-%m', 99, false);
            $sums[$label] = ($sums[$label] ?? 0) + (float) $r->amount;
        }
        $chart = new \core\chart_bar();
        $chart->set_title(self::str('chart_revenue'));
        $chart->add_series(new \core\chart_series(self::str('card_revenue'), array_values(array_map(fn($v) => round($v, 2), $sums))));
        $chart->set_labels(array_keys($sums));
        return $chart;
    }

    public function rows(int $page, int $perpage): array {
        global $DB;
        $view = $this->view();
        if ($view !== 'list') {
            return $this->grouped($view, $page, $perpage);
        }
        [$from, $params] = $this->from();
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $list = $DB->get_records_sql("SELECT t.id, t.timecreated, t.order_id, t.courseid, t.amount, t.original_amount,
                                             t.currency, t.status, t.metadata, t.payment_method_type, t.provider_txn_id,
                                             t.provider_order_id, t.country, t.reject_reason, t.provider_response_message,
                                             p.display_name AS provider, u.email, u.phone1, $names
                                      $from ORDER BY " . $this->order_sql('t.timecreated DESC, t.id DESC'),
            $params, $perpage ? $page * $perpage : 0, $perpage);
        $coursenames = data::course_names(array_map(fn($t) => $t->courseid, $list));
        $metas = array_map(fn($t) => json_decode((string) $t->metadata, true) ?: [], $list);
        $offerids = [];
        foreach ($metas as $meta) {
            foreach ((array) ($meta['discount']['offers'] ?? []) as $o) {
                $offerids[] = (int) ($o['id'] ?? 0);
            }
        }
        $offers = self::offer_names($offerids);
        $failed = ['failed', 'cancelled', 'expired', 'timed_out', 'voided', 'chargeback'];
        $rows = [];
        foreach ($list as $id => $t) {
            [$type, $item] = self::item($t, $coursenames);
            $meta = $metas[$id];
            $original = $t->original_amount !== null ? (float) $t->original_amount : (float) $t->amount;
            $offer = implode('، ', array_filter(array_map(fn($o) => $offers[(int) ($o['id'] ?? 0)] ?? '',
                (array) ($meta['discount']['offers'] ?? []))));
            $reason = in_array($t->status, $failed, true)
                ? trim((string) ($t->reject_reason ?: $t->provider_response_message)) : '';
            $rows[] = [
                'date' => self::date((int) $t->timecreated, true),
                'order' => $t->order_id,
                'student' => fullname($t),
                'email' => $t->email,
                'phone' => $t->phone1 ?: '—',
                'type' => $type,
                'item' => $item,
                'provider' => format_string((string) $t->provider) ?: '—',
                'method' => self::method($t->payment_method_type),
                'amount' => self::money((float) $t->amount, (string) $t->currency),
                'original' => $original != (float) $t->amount ? self::money($original, (string) $t->currency) : '—',
                'discount' => $original > (float) $t->amount ? self::money($original - (float) $t->amount, (string) $t->currency) : '—',
                'coupon' => (string) ($meta['coupon_code'] ?? '') ?: '—',
                'offer' => $offer ?: '—',
                'status' => self::str('pay_' . $t->status),
                'reference' => (string) ($t->provider_txn_id ?: $t->provider_order_id) ?: '—',
                'country' => $t->country ?: '—',
                'reason' => $reason ?: '—',
            ];
        }
        return ['total' => $total, 'rows' => $rows];
    }

    /**
     * The paid transactions grouped by item, payment method or month.
     *
     * @param string $view item | provider | month
     * @param int $page
     * @param int $perpage
     * @return array{total:int, rows:array}
     */
    private function grouped(string $view, int $page, int $perpage): array {
        global $DB;
        [$from, $params] = $this->from(true);
        $list = $DB->get_records_sql("SELECT t.id, t.timecreated, t.courseid, t.amount, t.currency, t.metadata,
                                             p.display_name AS provider $from", $params);
        $coursenames = data::course_names(array_map(fn($t) => $t->courseid, $list));
        $groups = [];
        foreach ($list as $t) {
            if ($view === 'item') {
                [$type, $item] = self::item($t, $coursenames);
                $key = $type . '|' . $item;
                $groups[$key]['type'] = $type;
                $groups[$key]['item'] = $item;
            } else if ($view === 'provider') {
                $key = format_string((string) $t->provider) ?: '—';
                $groups[$key]['provider'] = $key;
            } else {
                $key = userdate((int) $t->timecreated, '%Y-%m', 99, false);
                $groups[$key]['month'] = $key;
            }
            $groups[$key]['n'] = ($groups[$key]['n'] ?? 0) + 1;
            $groups[$key]['money'][$t->currency] = ($groups[$key]['money'][$t->currency] ?? 0) + (float) $t->amount;
        }
        // What was paid from wallet balance is revenue too; a refund back to the wallet takes it off.
        $wallet = $this->wallet_lines();
        $walletcourses = data::course_names(array_map(fn($w) => $w->itemtype === 'course' ? (int) $w->itemid : 0, $wallet));
        $purchasenotes = [];
        foreach ($wallet as $w) {
            if ($w->kind === 'purchase') {
                $purchasenotes[(int) $w->purchaseid] = (string) $w->note;
            }
        }
        foreach ($wallet as $w) {
            $refund = $w->kind !== 'purchase';
            if ($view === 'item') {
                $type = ['course' => 'item_course', 'cm' => 'item_activity', 'subscription' => 'item_subscription',
                    'package' => 'item_package'][$w->itemtype] ?? 'item_other';
                $type = self::str($type);
                // A package refund's note is "Refund": the item's name is on its purchase line.
                $name = $w->itemtype === 'course' ? ($walletcourses[(int) $w->itemid] ?? '')
                    : ($w->kind === 'adjustment' ? ($purchasenotes[(int) $w->purchaseid] ?? '') : (string) $w->note);
                $item = $name !== '' ? self::cname($name) : '—';
                $key = $type . '|' . $item;
                $groups[$key]['type'] = $type;
                $groups[$key]['item'] = $item;
            } else if ($view === 'provider') {
                $key = self::str('paymethod_wallet');
                $groups[$key]['provider'] = $key;
            } else {
                $key = userdate((int) $w->timecreated, '%Y-%m', 99, false);
                $groups[$key]['month'] = $key;
            }
            $groups[$key]['n'] = ($groups[$key]['n'] ?? 0) + ($refund ? 0 : 1);
            $groups[$key]['money'][self::WALLET_CURRENCY] = ($groups[$key]['money'][self::WALLET_CURRENCY] ?? 0) + $w->amount;
            if ($refund && $view === 'item') {
                $groups[$key]['refunded'] = ($groups[$key]['refunded'] ?? 0) + 1;
            }
        }
        // The fee kept on an online payment refunded to the wallet stays revenue (the payment itself left it).
        $feecourses = [];
        foreach ($this->online_refund_fees() as $f) {
            if ($view === 'item') {
                $type = self::str(['course' => 'item_course', 'subscription' => 'item_subscription'][$f->itemtype] ?? 'item_other');
                if ($f->itemtype === 'course') {
                    $feecourses += data::course_names([(int) $f->itemid]);
                }
                $name = $f->itemtype === 'course' ? ($feecourses[(int) $f->itemid] ?? '') : (string) $f->note;
                $item = $name !== '' ? self::cname($name) : '—';
                $key = $type . '|' . $item;
                $groups[$key]['type'] = $type;
                $groups[$key]['item'] = $item;
            } else if ($view === 'provider') {
                $key = format_string((string) $f->provider) ?: '—';
                $groups[$key]['provider'] = $key;
            } else {
                $key = userdate((int) $f->timecreated, '%Y-%m', 99, false);
                $groups[$key]['month'] = $key;
            }
            $groups[$key]['n'] = $groups[$key]['n'] ?? 0;
            $groups[$key]['money'][self::WALLET_CURRENCY] = ($groups[$key]['money'][self::WALLET_CURRENCY] ?? 0) + $f->amount;
        }
        if ($view === 'item') {
            // Refunded count per item (shown next to the sales).
            [$all, $aparams] = $this->from();
            foreach ($DB->get_records_sql("SELECT t.id, t.courseid, t.metadata $all AND t.status = 'refunded'", $aparams) as $t) {
                [$type, $item] = self::item($t, $coursenames + data::course_names([$t->courseid]));
                $key = $type . '|' . $item;
                $groups[$key] = ($groups[$key] ?? ['type' => $type, 'item' => $item, 'n' => 0, 'money' => []]);
                $groups[$key]['refunded'] = ($groups[$key]['refunded'] ?? 0) + 1;
            }
            uasort($groups, fn($a, $b) => array_sum($b['money']) <=> array_sum($a['money']));
        } else if ($view === 'month') {
            krsort($groups);
        } else {
            uasort($groups, fn($a, $b) => array_sum($b['money']) <=> array_sum($a['money']));
        }
        $rows = [];
        foreach ($groups as $g) {
            // The raw refunded count is dropped too, so the formatted one below is shown.
            $rows[] = array_diff_key($g, ['n' => 1, 'money' => 1, 'refunded' => 1]) + ['count' => self::num($g['n']),
                'revenue' => self::moneys($g['money']), 'refunded' => self::num($g['refunded'] ?? 0),
                '_sort' => ['count' => $g['n'], 'revenue' => array_sum($g['money']), 'refunded' => $g['refunded'] ?? 0]];
        }
        return $this->finish($rows, $page, $perpage);
    }
}

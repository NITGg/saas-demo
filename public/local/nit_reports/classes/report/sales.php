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
 * admin assigned). Revenue counts completed (and partially refunded) payments.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sales extends base {

    /** Statuses that count as revenue. */
    private const PAID = ['completed', 'partially_refunded'];

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
        $refunded = $DB->get_records_sql("SELECT t.currency, SUM(t.amount) AS total $all AND t.status = 'refunded'
                                          GROUP BY t.currency", $aparams);
        $cards = [
            ['label' => self::str('card_paidcount'), 'value' => self::num(array_sum(array_map(fn($r) => $r->n, $paid)))],
            ['label' => self::str('card_revenue'), 'value' => self::moneys(array_map(fn($r) => $r->total, $paid))],
            ['label' => self::str('card_refunded'), 'value' => self::num($bystatus['refunded'] ?? 0) . ' — '
                . self::moneys(array_map(fn($r) => $r->total, $refunded))],
            ['label' => self::str('card_failed'), 'value' => self::num(($bystatus['failed'] ?? 0) + ($bystatus['cancelled'] ?? 0)
                + ($bystatus['expired'] ?? 0))],
        ];

        // Money that did not go through the gateway.
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('nit_access_code')) {
            [$period, $pp] = $this->f->period_sql('timeused');
            $codes = (int) $DB->get_field_sql("SELECT COALESCE(SUM(amount_minor), 0) FROM {nit_access_code}
                                                WHERE status = 'used' AND $period", $pp);
            $cards[] = ['label' => self::str('card_codesales'), 'value' => data::minor($codes)];
        }
        if ($dbman->table_exists('nit_payment')) {
            [$period, $pp] = $this->f->period_sql('timecreated');
            $offline = (int) $DB->get_field_sql("SELECT COALESCE(SUM(amount_minor), 0) FROM {nit_payment}
                WHERE method IN ('offline', 'bank', 'cash') AND status = 'success' AND $period", $pp);
            $cards[] = ['label' => self::str('card_offlinepackages'), 'value' => data::minor($offline)];
        }
        if ($dbman->table_exists('nit_sub_purchase')) {
            [$period, $pp] = $this->f->period_sql('timecreated');
            $assigned = (float) $DB->get_field_sql("SELECT COALESCE(SUM(price_paid), 0) FROM {nit_sub_purchase}
                WHERE source = 'admin_assigned' AND $period", $pp);
            $cards[] = ['label' => self::str('card_assignedsubs'), 'value' => self::money($assigned)];
        }
        return $cards;
    }

    public function chart(): ?\core\chart_base {
        global $DB;
        [$from, $params] = $this->from(true);
        $rows = $DB->get_records_sql("SELECT t.id, t.timecreated, t.amount $from ORDER BY t.timecreated", $params);
        if (!$rows) {
            return null;
        }
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
            $rows[] = array_diff_key($g, ['n' => 1, 'money' => 1]) + ['count' => self::num($g['n']),
                'revenue' => self::moneys($g['money']), 'refunded' => self::num($g['refunded'] ?? 0),
                '_sort' => ['count' => $g['n'], 'revenue' => array_sum($g['money']), 'refunded' => $g['refunded'] ?? 0]];
        }
        return $this->finish($rows, $page, $perpage);
    }
}

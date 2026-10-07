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
 * Coupons and offers (local_nit_commerce): each one with how often it was used in
 * the period, by how many students, the discount it gave and the sales it brought.
 * A use counts when its payment completed (or it was a wallet purchase): a use is
 * recorded while the checkout is still open, and an abandoned checkout is not a use.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discounts extends base {

    public static function key(): string {
        return 'discounts';
    }

    public static function level(): string {
        return scope::SITE;
    }

    public static function available(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('nit_coupon_usage');
    }

    public function filters(): array {
        return ['period', 'status', 'q'];
    }

    public function status_options(): array {
        return ['coupon' => self::str('item_coupon'), 'offer' => self::str('item_offer')];
    }

    public function columns(): array {
        return [
            'type' => self::str('col_type'),
            'name' => self::str('col_codeorname'),
            'discount' => self::str('col_discount'),
            'state' => get_string('status'),
            'dates' => self::str('col_validity'),
            'uses' => self::str('col_uses'),
            'users' => self::str('col_users'),
            'given' => self::str('col_discountgiven'),
            'sales' => self::str('col_sales'),
        ];
    }

    /**
     * Usage totals per coupon or offer.
     *
     * @param string $kind coupon | offer
     * @return array<int,\stdClass> id => {uses, users, given, sales}
     */
    private function usage(string $kind): array {
        global $DB;
        [$period, $params] = $this->f->period_sql('us.timecreated');
        $hastx = $DB->get_manager()->table_exists('local_payments_transactions');
        $paid = $hastx ? "AND (us.transactionid = 0 OR EXISTS (SELECT 1 FROM {local_payments_transactions} t
                         WHERE t.id = us.transactionid AND t.status IN ('completed', 'partially_refunded')))" : '';
        return $DB->get_records_sql("SELECT us.{$kind}id AS id, COUNT(1) AS uses, COUNT(DISTINCT us.userid) AS users,
                    SUM(us.discount_amount) AS given, SUM(us.final_amount) AS sales
               FROM {nit_{$kind}_usage} us WHERE $period $paid GROUP BY us.{$kind}id", $params);
    }

    /**
     * Every coupon and offer with its usage, filtered and sorted by use.
     *
     * @return array
     */
    private function all(): array {
        global $DB;
        $items = [];
        foreach (['coupon' => 'code', 'offer' => 'name'] as $kind => $namefield) {
            if (($this->f->status !== '' && $this->f->status !== $kind) || !$DB->get_manager()->table_exists('nit_' . $kind)) {
                continue;
            }
            $usage = $this->usage($kind);
            foreach ($DB->get_records('nit_' . $kind) as $r) {
                $name = $kind === 'offer' ? self::cname($r->$namefield) : $r->$namefield;
                if ($this->f->q !== '' && \core_text::strpos(\core_text::strtolower($name), \core_text::strtolower($this->f->q)) === false) {
                    continue;
                }
                $u = $usage[$r->id] ?? null;
                $value = rtrim(rtrim(number_format((float) $r->discount_value, 2, '.', ''), '0'), '.');
                $items[] = [
                    'sort' => (float) ($u->sales ?? 0),
                    'type' => self::str('item_' . $kind),
                    'name' => $name,
                    'discount' => $r->discount_type === 'percent' ? $value . '%' : self::money((float) $r->discount_value),
                    'state' => $r->status === 'active' ? self::str('state_active') : self::str('state_inactive'),
                    'dates' => self::date((int) $r->startdate) . ' → ' . self::date((int) $r->enddate),
                    'uses' => self::num($u->uses ?? 0),
                    'users' => self::num($u->users ?? 0),
                    'given' => self::money((float) ($u->given ?? 0)),
                    'sales' => self::money((float) ($u->sales ?? 0)),
                    'raw' => $u,
                ];
            }
        }
        usort($items, fn($a, $b) => $b['sort'] <=> $a['sort']);
        return $items;
    }

    public function summary(): array {
        $items = $this->all();
        $sum = fn($field) => array_sum(array_map(fn($i) => (float) ($i['raw']->$field ?? 0), $items));
        return [
            ['label' => self::str('card_discounts'), 'value' => self::num(count($items))],
            ['label' => self::str('col_uses'), 'value' => self::num($sum('uses'))],
            ['label' => self::str('col_discountgiven'), 'value' => self::money($sum('given'))],
            ['label' => self::str('card_discountsales'), 'value' => self::money($sum('sales'))],
        ];
    }

    public function rows(int $page, int $perpage): array {
        $items = array_map(fn($i) => array_diff_key($i, ['sort' => 1, 'raw' => 1]), $this->all());
        return ['total' => count($items), 'rows' => $perpage ? array_slice($items, $page * $perpage, $perpage) : $items];
    }
}

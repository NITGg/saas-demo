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
 * Lesson packages (Flex, local_nit_flex): every package bought or assigned in the
 * period with its Flex bought, used, held for booked lessons and left; or each
 * student's balance. An active package past its end date counts as expired, and
 * its unused Flex as expired Flex.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class packages extends base {

    public static function key(): string {
        return 'packages';
    }

    public static function level(): string {
        return scope::SITE;
    }

    public static function available(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('nit_package_purchase');
    }

    public function filters(): array {
        return ['period', 'status', 'q'];
    }

    public function view_options(): array {
        return ['list' => self::str('view_packages'), 'student' => self::str('view_bystudent')];
    }

    public function status_options(): array {
        $out = [];
        foreach (['active', 'fully_used', 'expired', 'cancelled', 'pending'] as $st) {
            $out[$st] = self::str('pkg_' . $st);
        }
        return $out;
    }

    public function columns(): array {
        $flex = ['bought' => self::str('col_flexbought'), 'used' => self::str('col_flexused'),
            'reserved' => self::str('col_flexreserved'), 'left' => self::str('col_flexleft')];
        if ($this->view() === 'student') {
            return ['student' => self::str('col_student'), 'packages' => self::str('col_packages')] + $flex
                + ['expired' => self::str('col_flexexpired')];
        }
        return ['student' => self::str('col_student'), 'package' => self::str('col_package')] + $flex + [
            'expired' => self::str('col_flexexpired'),
            'paid' => self::str('col_pricepaid'),
            'source' => self::str('col_source'),
            'activated' => self::str('col_activated'),
            'expires' => self::str('col_expires'),
            'status' => get_string('status'),
        ];
    }

    public function sortable(): array {
        global $DB;
        if ($this->view() === 'student') {
            return array_fill_keys(array_keys($this->columns()), true);
        }
        return [
            'student' => $DB->sql_fullname('u.firstname', 'u.lastname'),
            'package' => 'pk.name',
            'bought' => 'pp.flex_count',
            'used' => 'pp.consumed_flex',
            'reserved' => 'pp.reserved_flex',
            'paid' => 'pp.price_paid_minor',
            'activated' => 'pp.timeactivated',
            'expires' => 'pp.expires_at',
            'status' => 'pp.status',
        ];
    }

    /**
     * The effective status in SQL (own placeholder per use).
     *
     * @param string $param
     * @return string
     */
    private static function status_sql(string $param): string {
        return "CASE WHEN pp.status = 'active' AND pp.expires_at > 0 AND pp.expires_at < :$param THEN 'expired' ELSE pp.status END";
    }

    /**
     * The purchases matching the filters: "FROM … WHERE …" over pp, pk, u.
     *
     * @return array{0:string, 1:array}
     */
    private function from(): array {
        global $DB;
        [$period, $params] = $this->f->period_sql('pp.timecreated');
        $where = [$period];
        if ($this->f->status !== '') {
            $where[] = self::status_sql('nowwhere') . ' = :fst';
            $params += ['fst' => $this->f->status, 'nowwhere' => time()];
        }
        if ($this->f->q !== '') {
            [$usearch, $up] = data::user_search_sql('u', $this->f->q);
            $where[] = "($usearch OR " . $DB->sql_like('pk.name', ':pq', false) . ')';
            $params += $up + ['pq' => '%' . $DB->sql_like_escape($this->f->q) . '%'];
        }
        return ['FROM {nit_package_purchase} pp
                 JOIN {nit_package} pk ON pk.id = pp.packageid
                 JOIN {user} u ON u.id = pp.userid AND u.deleted = 0
                WHERE ' . implode(' AND ', $where), $params];
    }

    public function summary(): array {
        global $DB;
        [$from, $params] = $this->from();
        $st = self::status_sql('nowsum');
        $s = $DB->get_record_sql("SELECT COUNT(1) AS sold, COALESCE(SUM(pp.flex_count), 0) AS flex,
                    COALESCE(SUM(pp.consumed_flex), 0) AS used, COALESCE(SUM(pp.price_paid_minor), 0) AS revenue,
                    COALESCE(SUM(CASE WHEN $st = 'active' THEN pp.remaining_flex ELSE 0 END), 0) AS lefts
               $from AND pp.status <> 'pending'", $params + ['nowsum' => time()]);
        $st2 = self::status_sql('nowexp');
        $expired = (int) $DB->get_field_sql("SELECT COALESCE(SUM(pp.remaining_flex), 0) $from AND $st2 = 'expired'",
            $params + ['nowexp' => time()]);
        [$period, $pp] = $this->f->period_sql('timecreated');
        $refunds = (int) $DB->get_field_sql("SELECT COALESCE(SUM(ABS(amount_minor)), 0) FROM {nit_payment}
                                              WHERE method = 'refund' AND $period", $pp);
        return [
            ['label' => self::str('card_packagessold'), 'value' => self::num($s->sold)],
            ['label' => self::str('col_flexbought'), 'value' => self::num($s->flex)],
            ['label' => self::str('col_flexused'), 'value' => self::num($s->used)],
            ['label' => self::str('col_flexleft'), 'value' => self::num($s->lefts)],
            ['label' => self::str('col_flexexpired'), 'value' => self::num($expired)],
            ['label' => self::str('card_revenue'), 'value' => data::minor((int) $s->revenue)],
            ['label' => self::str('card_refundedvalue'), 'value' => data::minor($refunds)],
        ];
    }

    public function rows(int $page, int $perpage): array {
        global $DB;
        [$from, $params] = $this->from();
        $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        if ($this->view() === 'student') {
            $st = self::status_sql('nowst');
            $st2 = self::status_sql('nowst2');
            $list = $DB->get_records_sql("SELECT u.id, $names, COUNT(1) AS packages, SUM(pp.flex_count) AS bought,
                        SUM(pp.consumed_flex) AS used, SUM(pp.reserved_flex) AS reserved,
                        SUM(CASE WHEN $st = 'active' THEN pp.remaining_flex ELSE 0 END) AS lefts,
                        SUM(CASE WHEN $st2 = 'expired' THEN pp.remaining_flex ELSE 0 END) AS expired
                   $from AND pp.status <> 'pending'
                GROUP BY u.id, " . str_replace(' ', '', $names) . "
                ORDER BY SUM(pp.flex_count) DESC", $params + ['nowst' => time(), 'nowst2' => time()]);
            $rows = [];
            foreach ($list as $r) {
                $rows[] = ['student' => fullname($r), 'packages' => self::num($r->packages), 'bought' => self::num($r->bought),
                    'used' => self::num($r->used), 'reserved' => self::num($r->reserved), 'left' => self::num($r->lefts),
                    'expired' => self::num($r->expired),
                    '_sort' => ['packages' => (int) $r->packages, 'bought' => (int) $r->bought, 'used' => (int) $r->used,
                        'reserved' => (int) $r->reserved, 'left' => (int) $r->lefts, 'expired' => (int) $r->expired]];
            }
            return $this->finish($rows, $page, $perpage);
        }
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $st = self::status_sql('nowst');
        $list = $DB->get_records_sql("SELECT pp.id, pp.flex_count, pp.consumed_flex, pp.reserved_flex, pp.remaining_flex,
                    pp.price_paid_minor, pp.source, pp.timeactivated, pp.expires_at, $st AS effstatus,
                    pk.name AS packagename, $names
               $from ORDER BY " . $this->order_sql('pp.timecreated DESC, pp.id DESC'),
            $params + ['nowst' => time()], $perpage ? $page * $perpage : 0, $perpage);
        $rows = [];
        foreach ($list as $p) {
            // Only an active package's Flex can still be used; an ended one's rest is "expired".
            $active = $p->effstatus === 'active';
            $rows[] = [
                'student' => fullname($p),
                'package' => self::cname($p->packagename),
                'bought' => self::num($p->flex_count),
                'used' => self::num($p->consumed_flex),
                'reserved' => self::num($p->reserved_flex),
                'left' => self::num($active ? $p->remaining_flex : 0),
                'expired' => self::num($p->effstatus === 'expired' ? $p->remaining_flex : 0),
                'paid' => data::minor((int) $p->price_paid_minor),
                'source' => self::str($p->source === 'admin_assigned' ? 'source_admin' : 'source_online'),
                'activated' => self::date((int) $p->timeactivated),
                'expires' => self::date((int) $p->expires_at),
                'status' => self::str('pkg_' . $p->effstatus),
            ];
        }
        return ['total' => $total, 'rows' => $rows];
    }
}

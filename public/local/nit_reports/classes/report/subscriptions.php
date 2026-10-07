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
 * Subscriptions: every subscription bought (or assigned) in the period with its
 * state — active, ending within a week, ended, cancelled — and whether it renewed
 * an earlier one; or the same grouped by plan. An active subscription past its end
 * date counts as ended (as local_nit_subscriptions does).
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscriptions extends base {

    public static function key(): string {
        return 'subscriptions';
    }

    public static function level(): string {
        return scope::SITE;
    }

    public static function available(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('nit_sub_purchase');
    }

    public function filters(): array {
        return ['period', 'status', 'q'];
    }

    public function view_options(): array {
        return ['list' => self::str('view_subscriptions'), 'plan' => self::str('view_byplan')];
    }

    public function status_options(): array {
        return ['active' => self::str('sub_active'), 'ending' => self::str('sub_ending'),
            'expired' => self::str('sub_expired'), 'cancelled' => self::str('sub_cancelled')];
    }

    public function columns(): array {
        if ($this->view() === 'plan') {
            return ['plan' => self::str('col_plan'), 'price' => self::str('col_price'), 'active' => self::str('sub_active'),
                'sold' => self::str('col_sold'), 'renewals' => self::str('col_renewals'), 'revenue' => self::str('col_revenue')];
        }
        return [
            'student' => self::str('col_student'),
            'plan' => self::str('col_plan'),
            'type' => self::str('col_type'),
            'paid' => self::str('col_pricepaid'),
            'source' => self::str('col_source'),
            'activated' => self::str('col_activated'),
            'expires' => self::str('col_expires'),
            'status' => get_string('status'),
            'renewal' => self::str('col_renewal'),
        ];
    }

    /**
     * The effective status of a purchase, in SQL. Each use needs its own parameter
     * name (Moodle refuses a placeholder used twice in one query).
     *
     * @param string $param placeholder name for "now"
     * @return string
     */
    private static function status_sql(string $param): string {
        return "CASE WHEN p.status = 'active' AND p.expires_at > 0 AND p.expires_at < :$param THEN 'expired' ELSE p.status END";
    }

    /**
     * The purchases matching the filters: "FROM … WHERE …" over p, s, u.
     *
     * @return array{0:string, 1:array}
     */
    private function from(): array {
        global $DB;
        $now = time();
        [$period, $params] = $this->f->period_sql('p.timecreated');
        $where = [$period];
        $status = self::status_sql('nowwhere');
        if ($this->f->status === 'ending') {
            $where[] = "p.status = 'active' AND p.expires_at > :now1 AND p.expires_at <= :week";
            $params += ['now1' => $now, 'week' => $now + WEEKSECS];
        } else if ($this->f->status !== '') {
            $where[] = "$status = :fst";
            $params['fst'] = $this->f->status;
            $params['nowwhere'] = $now;
        }
        if ($this->f->q !== '') {
            [$usearch, $up] = data::user_search_sql('u', $this->f->q);
            $where[] = "($usearch OR " . $DB->sql_like('s.name', ':pq', false) . ')';
            $params += $up + ['pq' => '%' . $DB->sql_like_escape($this->f->q) . '%'];
        }
        return ['FROM {nit_sub_purchase} p
                 JOIN {nit_subscription} s ON s.id = p.subscriptionid
                 JOIN {user} u ON u.id = p.userid AND u.deleted = 0
                WHERE ' . implode(' AND ', $where), $params];
    }

    /**
     * SQL: 1 when the purchase renewed an earlier one of the same plan by the same user.
     *
     * @return string
     */
    private static function renewal_sql(): string {
        return 'CASE WHEN EXISTS (SELECT 1 FROM {nit_sub_purchase} e WHERE e.userid = p.userid
                    AND e.subscriptionid = p.subscriptionid AND e.timecreated < p.timecreated) THEN 1 ELSE 0 END';
    }

    public function summary(): array {
        global $DB;
        $now = time();
        // Current state of all subscriptions (not limited to the period).
        $active = (int) $DB->count_records_select('nit_sub_purchase', "status = 'active' AND (expires_at = 0 OR expires_at > ?)",
            [$now]);
        $ending = (int) $DB->count_records_select('nit_sub_purchase', "status = 'active' AND expires_at > ? AND expires_at <= ?",
            [$now, $now + WEEKSECS]);
        [$from, $params] = $this->from();
        $renewal = self::renewal_sql();
        $stats = $DB->get_record_sql("SELECT COUNT(1) AS sold, COALESCE(SUM(p.price_paid), 0) AS revenue,
                                             COALESCE(SUM($renewal), 0) AS renewals $from", $params);
        return [
            ['label' => self::str('card_activesubs'), 'value' => self::num($active)],
            ['label' => self::str('card_endingweek'), 'value' => self::num($ending)],
            ['label' => self::str('card_subssold'), 'value' => self::num($stats->sold)],
            ['label' => self::str('col_renewals'), 'value' => self::num($stats->renewals)],
            ['label' => self::str('card_revenue'), 'value' => self::money((float) $stats->revenue)],
        ];
    }

    public function rows(int $page, int $perpage): array {
        global $DB;
        [$from, $params] = $this->from();
        $status = self::status_sql('nowselect');
        $params['nowselect'] = time();
        $renewal = self::renewal_sql();
        if ($this->view() === 'plan') {
            $params['nowact'] = time();
            $list = $DB->get_records_sql("SELECT s.id, s.name, s.price, COUNT(1) AS sold, SUM(p.price_paid) AS revenue,
                        SUM($renewal) AS renewals,
                        SUM(CASE WHEN p.status = 'active' AND (p.expires_at = 0 OR p.expires_at > :nowact) THEN 1 ELSE 0 END)
                            AS active
                   $from GROUP BY s.id, s.name, s.price ORDER BY SUM(p.price_paid) DESC", $params);
            $rows = [];
            foreach ($list as $r) {
                $rows[] = ['plan' => self::cname($r->name), 'price' => self::money((float) $r->price),
                    'active' => self::num($r->active), 'sold' => self::num($r->sold), 'renewals' => self::num($r->renewals),
                    'revenue' => self::money((float) $r->revenue)];
            }
            return ['total' => count($rows), 'rows' => $perpage ? array_slice($rows, $page * $perpage, $perpage) : $rows];
        }
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $list = $DB->get_records_sql("SELECT p.id, p.type, p.seats, p.price_paid, p.source, p.timeactivated, p.expires_at,
                                             $status AS effstatus, $renewal AS renewal, s.name AS planname, $names
                                      $from ORDER BY p.timecreated DESC, p.id DESC",
            $params, $perpage ? $page * $perpage : 0, $perpage);
        $rows = [];
        foreach ($list as $p) {
            $ending = $p->effstatus === 'active' && $p->expires_at > time() && $p->expires_at <= time() + WEEKSECS;
            $rows[] = [
                'student' => fullname($p),
                'plan' => self::cname($p->planname),
                'type' => $p->type === 'b2b' ? self::str('sub_b2b', (int) $p->seats) : self::str('sub_normal'),
                'paid' => self::money((float) $p->price_paid),
                'source' => self::str($p->source === 'admin_assigned' ? 'source_admin' : 'source_online'),
                'activated' => self::date((int) $p->timeactivated),
                'expires' => self::date((int) $p->expires_at),
                'status' => self::str($ending ? 'sub_ending' : 'sub_' . $p->effstatus),
                'renewal' => $p->renewal ? get_string('yes') : get_string('no'),
            ];
        }
        return ['total' => $total, 'rows' => $rows];
    }
}

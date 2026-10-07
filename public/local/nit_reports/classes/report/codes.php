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
 * Access codes (local_nit_finance): per batch — how many were made, used, still
 * open, disabled or expired, and the value made and redeemed; or every code. The
 * period filters by when a code was made.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class codes extends base {

    public static function key(): string {
        return 'codes';
    }

    public static function level(): string {
        return scope::SITE;
    }

    public static function available(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('nit_access_code');
    }

    public function filters(): array {
        return ['period', 'status', 'q'];
    }

    public function view_options(): array {
        return ['batch' => self::str('view_bybatch'), 'list' => self::str('view_codes')];
    }

    public function status_options(): array {
        return ['active' => self::str('code_active'), 'used' => self::str('code_used'), 'disabled' => self::str('code_disabled'),
            'expired' => self::str('code_expired')];
    }

    public function columns(): array {
        if ($this->view() === 'list') {
            return ['code' => self::str('col_code'), 'batch' => self::str('col_batch'), 'item' => self::str('col_item'),
                'value' => self::str('col_value'), 'status' => get_string('status'), 'usedby' => self::str('col_usedby'),
                'usedat' => self::str('col_usedat'), 'expires' => self::str('col_expires')];
        }
        return ['batch' => self::str('col_batch'), 'item' => self::str('col_itemtype'), 'made' => self::str('col_made'),
            'used' => self::str('code_used'), 'open' => self::str('code_active'), 'disabled' => self::str('code_disabled'),
            'expired' => self::str('code_expired'), 'value' => self::str('col_valuemade'), 'redeemed' => self::str('col_redeemed')];
    }

    public function sortable(): array {
        if ($this->view() !== 'list') {
            return array_fill_keys(array_keys($this->columns()), true);
        }
        return ['code' => 'a.code', 'batch' => 'a.batch', 'value' => 'a.amount_minor', 'status' => 'a.status',
            'usedat' => 'a.timeused', 'expires' => 'a.timeexpires'];
    }

    /**
     * The effective status in SQL (own placeholder per use).
     *
     * @param string $param
     * @return string
     */
    private static function status_sql(string $param): string {
        return "CASE WHEN a.status = 'active' AND a.timeexpires > 0 AND a.timeexpires < :$param THEN 'expired' ELSE a.status END";
    }

    /**
     * The codes matching the filters.
     *
     * @return array{0:string, 1:array}
     */
    private function from(): array {
        global $DB;
        [$period, $params] = $this->f->period_sql('a.timecreated');
        $where = [$period];
        if ($this->f->status !== '') {
            $where[] = self::status_sql('nowwhere') . ' = :fst';
            $params += ['fst' => $this->f->status, 'nowwhere' => time()];
        }
        if ($this->f->q !== '') {
            $like = '%' . $DB->sql_like_escape($this->f->q) . '%';
            $where[] = '(' . $DB->sql_like('a.code', ':cq1', false) . ' OR ' . $DB->sql_like('a.batch', ':cq2', false) . ')';
            $params += ['cq1' => $like, 'cq2' => $like];
        }
        return ['FROM {nit_access_code} a WHERE ' . implode(' AND ', $where), $params];
    }

    /**
     * The item a code opens.
     *
     * @param \stdClass $a
     * @param array $courses
     * @return string
     */
    private static function item(\stdClass $a, array $courses): string {
        if ($a->itemtype === 'wallet') {
            return self::str('item_wallet_topup');
        }
        $label = self::str('item_' . ($a->itemtype === 'cm' ? 'activity' : 'course'));
        return $label . ': ' . ($courses[(int) $a->courseid] ?? '#' . $a->itemid);
    }

    public function summary(): array {
        global $DB;
        [$from, $params] = $this->from();
        $st = self::status_sql('nowsum');
        $s = $DB->get_record_sql("SELECT COUNT(1) AS made,
                    SUM(CASE WHEN a.status = 'used' THEN 1 ELSE 0 END) AS used,
                    SUM(CASE WHEN $st = 'active' THEN 1 ELSE 0 END) AS open,
                    COALESCE(SUM(CASE WHEN a.status = 'used' THEN a.amount_minor ELSE 0 END), 0) AS redeemed
               $from", $params + ['nowsum' => time()]);
        return [
            ['label' => self::str('col_made'), 'value' => self::num($s->made)],
            ['label' => self::str('code_used'), 'value' => self::num($s->used ?? 0)],
            ['label' => self::str('code_active'), 'value' => self::num($s->open ?? 0)],
            ['label' => self::str('col_redeemed'), 'value' => data::minor((int) $s->redeemed)],
        ];
    }

    public function rows(int $page, int $perpage): array {
        global $DB;
        [$from, $params] = $this->from();
        $offset = $perpage ? $page * $perpage : 0;
        if ($this->view() === 'list') {
            $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
            $st = self::status_sql('nowst');
            $list = $DB->get_records_sql("SELECT a.*, $st AS effstatus $from ORDER BY "
                    . $this->order_sql('a.timecreated DESC, a.id DESC'),
                $params + ['nowst' => time()], $offset, $perpage);
            $courses = data::course_names(array_map(fn($a) => $a->courseid, $list));
            $users = data::user_names(array_map(fn($a) => $a->usedby, $list));
            $rows = [];
            foreach ($list as $a) {
                $rows[] = ['code' => $a->code, 'batch' => $a->batch ?: '—', 'item' => self::item($a, $courses),
                    'value' => data::minor((int) $a->amount_minor), 'status' => self::str('code_' . $a->effstatus),
                    'usedby' => $users[(int) $a->usedby] ?? '—', 'usedat' => self::date((int) $a->timeused, true),
                    'expires' => self::date((int) $a->timeexpires)];
            }
            return ['total' => $total, 'rows' => $rows];
        }
        $st = self::status_sql('nowb1');
        $st2 = self::status_sql('nowb2');
        $key = $DB->sql_concat("COALESCE(a.batch, '')", "'|'", 'a.itemtype');
        $list = $DB->get_records_sql("SELECT $key AS k, a.batch, a.itemtype,
                    COUNT(1) AS made,
                    SUM(CASE WHEN a.status = 'used' THEN 1 ELSE 0 END) AS used,
                    SUM(CASE WHEN $st = 'active' THEN 1 ELSE 0 END) AS open,
                    SUM(CASE WHEN a.status = 'disabled' THEN 1 ELSE 0 END) AS disabled,
                    SUM(CASE WHEN $st2 = 'expired' THEN 1 ELSE 0 END) AS expired,
                    SUM(a.amount_minor) AS value,
                    SUM(CASE WHEN a.status = 'used' THEN a.amount_minor ELSE 0 END) AS redeemed
               $from GROUP BY a.batch, a.itemtype ORDER BY MAX(a.timecreated) DESC",
            $params + ['nowb1' => time(), 'nowb2' => time()]);
        $rows = [];
        foreach ($list as $b) {
            $rows[] = ['batch' => $b->batch ?: '—',
                'item' => self::str($b->itemtype === 'wallet' ? 'item_wallet_topup' : ($b->itemtype === 'cm' ? 'item_activity' : 'item_course')),
                'made' => self::num($b->made), 'used' => self::num($b->used), 'open' => self::num($b->open),
                'disabled' => self::num($b->disabled), 'expired' => self::num($b->expired),
                'value' => data::minor((int) $b->value), 'redeemed' => data::minor((int) $b->redeemed),
                '_sort' => ['made' => (int) $b->made, 'used' => (int) $b->used, 'open' => (int) $b->open,
                    'disabled' => (int) $b->disabled, 'expired' => (int) $b->expired, 'value' => (int) $b->value,
                    'redeemed' => (int) $b->redeemed]];
        }
        return $this->finish($rows, $page, $perpage);
    }
}

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

use local_nit_reports\filters;
use local_nit_reports\scope;

/**
 * One report: its filters, number cards, optional chart and table. The page and
 * every export (PDF, Excel, CSV) are built from these same methods, so what is
 * downloaded is exactly what is shown.
 *
 * Rows are plain arrays of display strings keyed like columns(); the table and
 * the exports escape them.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {

    /** @var filters */
    protected $f;

    /** @var scope */
    protected $s;

    /**
     * Constructor.
     *
     * @param filters $f
     * @param scope $s
     */
    public function __construct(filters $f, scope $s) {
        $this->f = $f;
        $this->s = $s;
    }

    /**
     * Short key (URL and string ids: report_<key>).
     *
     * @return string
     */
    abstract public static function key(): string;

    /**
     * Who may open it: scope::SITE | scope::MANAGE | scope::TEACHING.
     *
     * @return string
     */
    abstract public static function level(): string;

    /**
     * Whether the report's data exists on this site (its plugin is installed).
     *
     * @return bool
     */
    public static function available(): bool {
        return true;
    }

    /**
     * The display name.
     *
     * @return string
     */
    public static function name(): string {
        return get_string('report_' . static::key(), 'local_nit_reports');
    }

    /**
     * The filters this report offers: period, course, user, status, q.
     *
     * @return string[]
     */
    public function filters(): array {
        return ['period', 'course', 'q'];
    }

    /**
     * Options of the status filter (when offered).
     *
     * @return array value => label
     */
    public function status_options(): array {
        return [];
    }

    /**
     * What the status filter means when nothing is picked.
     *
     * @return string
     */
    public function status_all_label(): string {
        return self::str('allstatuses');
    }

    /**
     * The ways this report can group its rows: key => label (first = default).
     *
     * @return array
     */
    public function view_options(): array {
        return [];
    }

    /**
     * The current view (a key of view_options(), the first by default).
     *
     * @return string
     */
    protected function view(): string {
        $views = $this->view_options();
        return isset($views[$this->f->view]) ? $this->f->view : (string) array_key_first($views);
    }

    /**
     * Options of the user filter (when offered): value => label.
     *
     * @return array
     */
    public function user_options(): array {
        return [];
    }

    /**
     * The table columns.
     *
     * @return array key => label
     */
    abstract public function columns(): array;

    /**
     * The number cards above the table.
     *
     * @return array [['id' => string id of the label, 'label' => string, 'value' => string], …]
     */
    public function summary(): array {
        return [];
    }

    /**
     * What a number card means: the first string found of
     * help_card_<report>_<view>_<id>, help_card_<report>_<id> and help_card_<id>.
     *
     * @param array $card one of summary()
     * @return string|null
     */
    public function card_help(array $card): ?string {
        if (empty($card['id'])) {
            return null;
        }
        $ids = ['help_card_' . static::key() . '_' . $card['id'], 'help_card_' . $card['id']];
        if ($this->view_options()) {
            array_unshift($ids, 'help_card_' . static::key() . '_' . $this->view() . '_' . $card['id']);
        }
        $sm = get_string_manager();
        foreach ($ids as $id) {
            if ($sm->string_exists($id, 'local_nit_reports')) {
                return self::str($id);
            }
        }
        return null;
    }

    /**
     * One page of rows; $perpage 0 = every row (exports).
     *
     * @param int $page
     * @param int $perpage
     * @return array{total:int, rows:array}
     */
    abstract public function rows(int $page, int $perpage): array;

    /**
     * An optional chart drawn above the table (not in exports).
     *
     * @return \core\chart_base|null
     */
    public function chart(): ?\core\chart_base {
        return null;
    }

    /**
     * What each column means, shown next to its header and at the end of the PDF.
     *
     * The text is the first string found of help_<report>_<view>_<column>,
     * help_<report>_<column> and help_<column>.
     *
     * @return array column key => text
     */
    public function help(): array {
        $sm = get_string_manager();
        $out = [];
        foreach (array_keys($this->columns()) as $col) {
            $ids = ['help_' . static::key() . '_' . $col, 'help_' . $col];
            if ($this->view_options()) {
                array_unshift($ids, 'help_' . static::key() . '_' . $this->view() . '_' . $col);
            }
            foreach ($ids as $id) {
                if ($sm->string_exists($id, 'local_nit_reports')) {
                    $out[$col] = self::str($id);
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * The columns the table can be sorted by.
     *
     * A string is the SQL the report orders by (see order_sql()); true means the
     * report sorts its rows itself with finish(), by the raw value in the row's
     * "_sort" entry (or the shown text when there is none).
     *
     * @return array column key => SQL expression | true
     */
    public function sortable(): array {
        return [];
    }

    /**
     * The requested sort, when the column can be sorted.
     *
     * @return array{0:string, 1:string}|null [column, asc|desc]
     */
    public function sort(): ?array {
        $sortable = $this->sortable();
        if ($this->f->sort === '' || !isset($sortable[$this->f->sort]) || !isset($this->columns()[$this->f->sort])) {
            return null;
        }
        return [$this->f->sort, $this->f->dir];
    }

    /**
     * ORDER BY for a report that sorts in SQL: the requested column first, then the default.
     *
     * @param string $default the report's own order
     * @return string
     */
    protected function order_sql(string $default): string {
        $sort = $this->sort();
        $sortable = $this->sortable();
        if (!$sort || !is_string($sortable[$sort[0]])) {
            return $default;
        }
        return $sortable[$sort[0]] . ' ' . strtoupper($sort[1]) . ', ' . $default;
    }

    /**
     * Sort rows built in PHP (when a "true" column is requested) and cut out one page.
     *
     * @param array $rows every row, in the report's own order
     * @param int $page
     * @param int $perpage 0 = every row
     * @return array{total:int, rows:array}
     */
    protected function finish(array $rows, int $page, int $perpage): array {
        $sort = $this->sort();
        if ($sort && $this->sortable()[$sort[0]] === true) {
            [$col, $dir] = $sort;
            $collator = new \Collator(current_language());
            $value = fn($row) => $row['_sort'][$col] ?? ($row[$col] ?? '');
            uasort($rows, function($a, $b) use ($value, $dir, $collator) {
                $x = $value($a);
                $y = $value($b);
                $cmp = is_string($x) || is_string($y) ? $collator->compare((string) $x, (string) $y) : $x <=> $y;
                return $dir === 'desc' ? -$cmp : $cmp;
            });
            $rows = array_values($rows);
        }
        return ['total' => count($rows), 'rows' => $perpage ? array_slice($rows, $page * $perpage, $perpage) : $rows];
    }

    // =========================================================================
    // Helpers for the reports.
    // =========================================================================

    /**
     * The course condition for this report's level, plus the course filter.
     *
     * @param string $column
     * @return array{0:string, 1:array}
     */
    protected function course_where(string $column): array {
        static $n = 0;
        [$sql, $params] = $this->s->course_sql($column, static::level());
        if ($this->f->courseid) {
            // Own placeholder per call: two of these can sit in one query.
            $p = 'fcourseid' . (++$n);
            $sql .= " AND $column = :$p";
            $params[$p] = $this->f->courseid;
        }
        return [$sql, $params];
    }

    /**
     * A course name for display (multilang resolved).
     *
     * @param string|null $name
     * @return string
     */
    protected static function cname(?string $name): string {
        return $name === null || $name === '' ? '—' : format_string($name, true, ['escape' => false]);
    }

    /**
     * A date for display, '—' when empty.
     *
     * @param int|null $time
     * @param bool $withtime
     * @return string
     */
    protected static function date(?int $time, bool $withtime = false): string {
        if (!$time) {
            return '—';
        }
        return userdate($time, get_string($withtime ? 'strftimedatetimeshort' : 'strftimedatefullshort', 'langconfig'));
    }

    /**
     * A percentage for display.
     *
     * @param float|null $value 0..100
     * @return string
     */
    protected static function pct(?float $value): string {
        return $value === null ? '—' : format_float($value, 1) . '%';
    }

    /**
     * A money amount for display.
     *
     * @param float $amount
     * @param string $currency
     * @return string
     */
    protected static function money(float $amount, string $currency = ''): string {
        return format_float($amount, 2) . ($currency !== '' ? ' ' . $currency : '');
    }

    /**
     * A plain number for display.
     *
     * @param int|float $n
     * @return string
     */
    protected static function num($n): string {
        return number_format((float) $n, 0);
    }

    /**
     * A string of this plugin.
     *
     * @param string $key
     * @param mixed $a
     * @return string
     */
    protected static function str(string $key, $a = null): string {
        return get_string($key, 'local_nit_reports', $a);
    }
}

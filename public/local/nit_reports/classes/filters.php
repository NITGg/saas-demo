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

namespace local_nit_reports;

/**
 * The filters of a report request: period (from / to), course, user, status,
 * and a free-text search. Read from the page parameters; dates are taken in the
 * viewer's timezone, "to" includes the whole day.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filters {

    /** @var int start of the period (0 = none) */
    public $from = 0;
    /** @var int end of the period, inclusive (0 = none) */
    public $to = 0;
    /** @var string the "from" date as typed (Y-m-d) */
    public $fromtext = '';
    /** @var string the "to" date as typed (Y-m-d) */
    public $totext = '';
    /** @var int */
    public $courseid = 0;
    /** @var int a student, or a teacher in the teacher reports */
    public $userid = 0;
    /** @var string */
    public $status = '';
    /** @var string */
    public $q = '';
    /** @var string how the report groups its rows (each report lists its own views) */
    public $view = '';
    /** @var string the column the table is sorted by ('' = the report's own order) */
    public $sort = '';
    /** @var string asc | desc */
    public $dir = 'asc';

    /**
     * Read the filters from the request.
     *
     * @return self
     */
    public static function from_request(): self {
        $f = new self();
        $f->fromtext = optional_param('from', '', PARAM_RAW_TRIMMED);
        $f->totext = optional_param('to', '', PARAM_RAW_TRIMMED);
        $f->from = self::day($f->fromtext);
        $f->to = self::day($f->totext);
        if ($f->to) {
            $f->to += DAYSECS - 1;
        }
        $f->courseid = optional_param('courseid', 0, PARAM_INT);
        $f->userid = optional_param('userid', 0, PARAM_INT);
        $f->status = optional_param('status', '', PARAM_ALPHANUMEXT);
        $f->q = trim(optional_param('q', '', PARAM_TEXT));
        $f->view = optional_param('view', '', PARAM_ALPHA);
        $f->sort = optional_param('sort', '', PARAM_ALPHANUMEXT);
        $f->dir = optional_param('dir', 'asc', PARAM_ALPHA) === 'desc' ? 'desc' : 'asc';
        return $f;
    }

    /**
     * Midnight of a Y-m-d date in the viewer's timezone, 0 when not a date.
     *
     * @param string $text
     * @return int
     */
    private static function day(string $text): int {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return 0;
        }
        return make_timestamp((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    /**
     * The URL parameters that reproduce these filters.
     *
     * @return array
     */
    public function params(): array {
        return array_filter(['from' => $this->fromtext, 'to' => $this->totext, 'courseid' => $this->courseid,
            'userid' => $this->userid, 'status' => $this->status, 'q' => $this->q, 'view' => $this->view,
            'sort' => $this->sort, 'dir' => $this->sort !== '' ? $this->dir : ''],
            fn($v) => $v !== '' && $v !== 0);
    }

    /**
     * A SQL condition for the period on a time column.
     *
     * @param string $column
     * @param string $prefix param prefix
     * @return array{0:string, 1:array}
     */
    public function period_sql(string $column, string $prefix = 'p'): array {
        $where = [];
        $params = [];
        if ($this->from) {
            $where[] = "$column >= :{$prefix}from";
            $params[$prefix . 'from'] = $this->from;
        }
        if ($this->to) {
            $where[] = "$column <= :{$prefix}to";
            $params[$prefix . 'to'] = $this->to;
        }
        return [$where ? implode(' AND ', $where) : '1 = 1', $params];
    }

    /**
     * A one-line description of the active filters (for the PDF header).
     *
     * @return string
     */
    public function describe(): string {
        global $DB;
        $s = fn($k, $a = null) => get_string($k, 'local_nit_reports', $a);
        $parts = [];
        if ($this->from || $this->to) {
            $parts[] = $s('filter_period') . ': ' . ($this->fromtext ?: '…') . ' → ' . ($this->totext ?: '…');
        }
        if ($this->courseid && ($name = $DB->get_field('course', 'fullname', ['id' => $this->courseid]))) {
            $parts[] = get_string('course') . ': ' . format_string($name);
        }
        if ($this->userid && ($user = \core_user::get_user($this->userid))) {
            $parts[] = $s('filter_user') . ': ' . fullname($user);
        }
        if ($this->q !== '') {
            $parts[] = get_string('search') . ': ' . $this->q;
        }
        return $parts ? implode(' — ', $parts) : $s('filter_none');
    }
}

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

defined('MOODLE_INTERNAL') || die();

/**
 * The teacher reports for the mobile app: the same report classes, scope and
 * filters as the web page /local/nit_reports/index.php, returned as data
 * (number cards, columns, rows) instead of HTML.
 *
 * Only the "teaching" reports are offered (students, course performance, student
 * results, video watching, own earnings & payouts); the site-wide admin reports
 * stay on the website.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile_api {

    /** Rows per page: default and ceiling. */
    const PERPAGE = 20;
    const MAXPERPAGE = 100;

    /**
     * The teaching reports this user may open, keyed by report key.
     *
     * @param scope $scope
     * @return array<string, string> key => report class
     */
    public static function reports(scope $scope): array {
        return array_filter(registry::for_scope($scope),
            static fn(string $class): bool => $class::level() === scope::TEACHING);
    }

    /**
     * The list of reports for the reports screen.
     *
     * @param int $userid
     * @return array
     */
    public static function list(int $userid): array {
        $scope = scope::for_user($userid);
        $out = [];
        foreach (self::reports($scope) as $key => $class) {
            $out[] = ['key' => $key, 'name' => $class::name()];
        }
        return ['reports' => $out, 'sitewide' => (bool) $scope->sitewide];
    }

    /**
     * One report as data: its filters (offered + current values + options), the
     * number cards, the columns and one page of rows. Values are display text,
     * already formatted in the caller's language (dates, %, money).
     *
     * @param int $userid
     * @param string $key
     * @param filters $filters
     * @param int $page 0-based
     * @param int $perpage
     * @return array
     * @throws \moodle_exception reportnotfound, nopermissions
     */
    public static function get(int $userid, string $key, filters $filters, int $page, int $perpage): array {
        global $DB;
        $scope = scope::for_user($userid);
        $reports = self::reports($scope);
        if (!$reports) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('reports', 'local_nit_reports'));
        }
        if (!isset($reports[$key])) {
            throw new \moodle_exception('err_reportnotfound', 'local_nit_reports');
        }
        $class = $reports[$key];
        /** @var report\base $report */
        $report = new $class($filters, $scope);
        $page = max(0, $page);
        $perpage = max(1, min(self::MAXPERPAGE, $perpage));

        // Filters the screen should show, with their options.
        $offered = $report->filters();
        $options = [];
        if (in_array('course', $offered, true)) {
            $ids = $scope->course_ids($class::level());
            $courses = $ids === null
                ? $DB->get_records_select('course', 'id <> ?', [SITEID], 'fullname', 'id, fullname')
                : ($ids ? $DB->get_records_list('course', 'id', $ids, 'fullname', 'id, fullname') : []);
            $options['courses'] = [];
            foreach ($courses as $c) {
                $options['courses'][] = ['id' => (int) $c->id,
                    'name' => format_string($c->fullname, true, ['context' => \context_course::instance($c->id)])];
            }
        }
        if (in_array('status', $offered, true) && ($statuses = $report->status_options())) {
            $options['statuses'] = self::pairs($statuses);
            $options['statusall'] = $report->status_all_label();
        }
        if (in_array('user', $offered, true) && ($users = $report->user_options())) {
            $options['users'] = self::pairs($users);
        }
        if ($views = $report->view_options()) {
            $offered[] = 'view';
            $options['views'] = self::pairs($views);
        }

        // Number cards.
        $cards = [];
        foreach ($report->summary() as $card) {
            $cards[] = [
                'id' => (string) ($card['id'] ?? ''),
                'label' => (string) $card['label'],
                'value' => (string) $card['value'],
                'help' => (string) ($report->card_help($card) ?? ''),
            ];
        }

        // Columns and one page of rows (a page past the end falls back to the last one, like the web).
        $result = $report->rows($page, $perpage);
        if (!$result['rows'] && $page > 0 && $result['total'] > 0) {
            $page = max(0, (int) ceil($result['total'] / $perpage) - 1);
            $result = $report->rows($page, $perpage);
        }
        $columns = $report->columns();
        $help = $report->help();
        $sortable = $report->sortable();
        $cols = [];
        foreach ($columns as $col => $label) {
            $cols[] = ['key' => (string) $col, 'label' => (string) $label, 'help' => (string) ($help[$col] ?? ''),
                'sortable' => isset($sortable[$col])];
        }
        $rows = [];
        foreach ($result['rows'] as $row) {
            $clean = [];
            foreach (array_keys($columns) as $col) {
                $clean[$col] = isset($row[$col]) ? (string) $row[$col] : '';
            }
            $rows[] = $clean;
        }
        $sort = $report->sort();

        return [
            'report' => ['key' => $key, 'name' => $class::name()],
            'filters' => [
                'offered' => array_values($offered),
                'values' => [
                    'from' => $filters->fromtext,
                    'to' => $filters->totext,
                    'courseid' => (int) $filters->courseid,
                    'userid' => (int) $filters->userid,
                    'status' => $filters->status,
                    'q' => $filters->q,
                    'view' => $filters->view,
                    'sort' => $sort ? $sort[0] : '',
                    'dir' => $sort ? $sort[1] : '',
                ],
                'options' => (object) $options, // Always a JSON object, also when empty.
                'description' => $filters->describe(),
            ],
            'cards' => $cards,
            'columns' => $cols,
            'rows' => $rows,
            'total' => (int) $result['total'],
            'page' => $page,
            'perpage' => $perpage,
        ];
    }

    /**
     * [value => label] as a list of {value, label}.
     *
     * @param array $map
     * @return array
     */
    protected static function pairs(array $map): array {
        $out = [];
        foreach ($map as $value => $label) {
            $out[] = ['value' => (string) $value, 'label' => (string) $label];
        }
        return $out;
    }
}

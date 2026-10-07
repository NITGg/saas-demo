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

/**
 * Download a report with its current filters: PDF (title, filters, number cards
 * and the table; right-to-left for Arabic), Excel or CSV (the table).
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_reports\filters;
use local_nit_reports\registry;
use local_nit_reports\scope;

require_login();
$scope = scope::for_user();
$reports = registry::for_scope($scope);
$key = required_param('report', PARAM_ALPHANUMEXT);
$format = required_param('format', PARAM_ALPHA);
if (!isset($reports[$key]) || !in_array($format, ['pdf', 'excel', 'csv'], true)) {
    throw new required_capability_exception(context_system::instance(), 'local/nit_reports:view', 'nopermissions', '');
}
$class = $reports[$key];
$filters = filters::from_request();
/** @var \local_nit_reports\report\base $report */
$report = new $class($filters, $scope);
\core_php_time_limit::raise(300);
raise_memory_limit(MEMORY_HUGE);

$filename = clean_filename($key . '_' . userdate(time(), '%Y-%m-%d'));
$columns = $report->columns();
$rows = $report->rows(0, 0)['rows'];

if ($format !== 'pdf') {
    $ordered = function() use ($rows, $columns) {
        foreach ($rows as $row) {
            $out = [];
            foreach (array_keys($columns) as $col) {
                $out[$col] = (string) ($row[$col] ?? '');
            }
            yield $out;
        }
    };
    \core\dataformat::download_data($filename, $format, $columns, $ordered());
    exit;
}

$cards = $report->summary();
$cardhelp = [];
foreach ($cards as $card) {
    if ($text = $report->card_help($card)) {
        $cardhelp[$card['label']] = $text;
    }
}
\local_nit_reports\pdf::download($filename, $class::name(), $filters->describe(), $cards, $columns, $rows,
    $report->help(), $cardhelp);

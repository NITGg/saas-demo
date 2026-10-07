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
 * Reports: one tab per report the viewer may open, with filters, number cards,
 * a chart where it helps, the table, and PDF / Excel / CSV export.
 *
 * Admins and site managers reach it from Site administration → Plugins → Local
 * plugins → Reports and see everything; a manager of a category or a course, and
 * a teacher, reach it from the course's "More" menu and see their courses only.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_reports\filters;
use local_nit_reports\registry;
use local_nit_reports\scope;

require_login();
$scope = scope::for_user();
$reports = registry::for_scope($scope);
if (!$reports) {
    throw new required_capability_exception(context_system::instance(), 'local/nit_reports:view', 'nopermissions', '');
}
$key = optional_param('report', '', PARAM_ALPHANUMEXT);
if (!isset($reports[$key])) {
    $key = array_key_first($reports);
}
$filters = filters::from_request();
$page = optional_param('page', 0, PARAM_INT);
$perpageoptions = [5, 10, 20, 30];
$perpage = optional_param('perpage', $perpageoptions[0], PARAM_INT);
if (!in_array($perpage, $perpageoptions, true)) {
    $perpage = $perpageoptions[0];
}
$class = $reports[$key];
/** @var \local_nit_reports\report\base $report */
$report = new $class($filters, $scope);
$s = fn(string $k, $a = null) => get_string($k, 'local_nit_reports', $a);

$pageurl = new moodle_url('/local/nit_reports/index.php', ['report' => $key] + $filters->params()
    + ($perpage !== $perpageoptions[0] ? ['perpage' => $perpage] : []));
if ($scope->sitewide) {
    admin_externalpage_setup('local_nit_reports', '', null, $pageurl);
} else {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url($pageurl);
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title($s('reports'));
    $PAGE->set_heading($s('reports'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($s('reports'));

// One tab per report.
$tabs = [];
foreach ($reports as $k => $c) {
    $tabs[] = new tabobject($k, new moodle_url('/local/nit_reports/index.php', ['report' => $k]), $c::name());
}
echo $OUTPUT->tabtree($tabs, $key);

// Filters.
$offered = $report->filters();
$field = fn(string $label, string $id, string $html) => html_writer::div(
    html_writer::label($label, $id, true, ['class' => 'form-label small mb-1 d-block']) . $html);
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $pageurl->out_omit_querystring(),
    'class' => 'd-flex flex-wrap align-items-end gap-2 mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'report', 'value' => $key]);
if ($views = $report->view_options()) {
    echo $field($s('filter_view'), 'nit-rp-view', html_writer::select($views, 'view',
        isset($views[$filters->view]) ? $filters->view : array_key_first($views), false,
        ['id' => 'nit-rp-view', 'class' => 'form-select']));
}
if (in_array('period', $offered, true)) {
    echo $field($s('filter_from'), 'nit-rp-from', html_writer::empty_tag('input', ['type' => 'date', 'name' => 'from',
        'id' => 'nit-rp-from', 'value' => $filters->fromtext, 'class' => 'form-control']));
    echo $field($s('filter_to'), 'nit-rp-to', html_writer::empty_tag('input', ['type' => 'date', 'name' => 'to',
        'id' => 'nit-rp-to', 'value' => $filters->totext, 'class' => 'form-control']));
}
if (in_array('course', $offered, true)) {
    $ids = $scope->course_ids($class::level());
    $courses = $ids === null
        ? $DB->get_records_select('course', 'id <> ?', [SITEID], 'fullname', 'id, fullname')
        : ($ids ? $DB->get_records_list('course', 'id', $ids, 'fullname', 'id, fullname') : []);
    $options = [0 => $s('allcourses')];
    foreach ($courses as $c) {
        $options[(int) $c->id] = format_string($c->fullname, true, ['context' => context_course::instance($c->id)]);
    }
    echo $field(get_string('course'), 'nit-rp-course', html_writer::select($options, 'courseid', $filters->courseid, false,
        ['id' => 'nit-rp-course', 'class' => 'form-select']));
}
if (in_array('user', $offered, true) && ($users = $report->user_options())) {
    echo $field($s('filter_user'), 'nit-rp-user', html_writer::select([0 => $s('allusers')] + $users, 'userid',
        $filters->userid, false, ['id' => 'nit-rp-user', 'class' => 'form-select']));
}
if (in_array('status', $offered, true) && ($statuses = $report->status_options())) {
    echo $field(get_string('status'), 'nit-rp-status', html_writer::select(['' => $report->status_all_label()] + $statuses, 'status',
        $filters->status, false, ['id' => 'nit-rp-status', 'class' => 'form-select']));
}
if (in_array('q', $offered, true)) {
    echo $field(get_string('search'), 'nit-rp-q', html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q',
        'id' => 'nit-rp-q', 'value' => $filters->q, 'class' => 'form-control', 'placeholder' => $s('search_' . $key)]));
}
// The sort and rows-per-page stay when filtering again.
foreach (['sort' => $filters->sort, 'dir' => $filters->sort !== '' ? $filters->dir : ''] as $name => $value) {
    if ($value !== '') {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
}
if ($perpage !== $perpageoptions[0]) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'perpage', 'value' => $perpage]);
}
echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::link(new moodle_url('/local/nit_reports/index.php', ['report' => $key]), $s('clearfilters'),
    ['class' => 'btn btn-link']);
echo html_writer::end_tag('form');

// Export.
$exports = '';
foreach (['pdf' => 'PDF', 'excel' => 'Excel', 'csv' => 'CSV'] as $format => $label) {
    $exports .= html_writer::link(new moodle_url('/local/nit_reports/export.php',
        ['report' => $key, 'format' => $format] + $filters->params()), $label, ['class' => 'btn btn-outline-secondary btn-sm']);
}
echo html_writer::div(html_writer::span($s('export') . ':', 'small text-muted') . $exports,
    'd-flex flex-wrap align-items-center gap-2 mb-3');

// Number cards.
if ($cards = $report->summary()) {
    $html = '';
    foreach ($cards as $card) {
        $html .= html_writer::div(
            html_writer::div(s($card['label']), 'small text-muted') .
            html_writer::div(s($card['value']), 'fs-4 fw-bold'),
            'border rounded p-3 bg-white', ['style' => 'min-width:170px']);
    }
    echo html_writer::div($html, 'd-flex flex-wrap gap-3 mb-4');
}

// Chart.
if ($chart = $report->chart()) {
    echo html_writer::div($OUTPUT->render($chart), 'mb-4', ['style' => 'max-width:960px']);
}

// Table.
$result = $report->rows($page, $perpage);
if (!$result['rows'] && $page > 0) {
    // Past the last page (e.g. after a filter): show the last one.
    $page = max(0, (int) ceil($result['total'] / $perpage) - 1);
    $result = $report->rows($page, $perpage);
}
if (!$result['rows']) {
    echo $OUTPUT->notification($s('norows'), \core\output\notification::NOTIFY_INFO, false);
} else {
    $columns = $report->columns();
    $help = $report->help();
    $sortable = $report->sortable();
    $sort = $report->sort();

    // Header: the label (a sort link when the column sorts) and its meaning behind a (?) icon.
    $head = [];
    foreach ($columns as $col => $label) {
        $html = s($label);
        if (isset($sortable[$col])) {
            $dir = $sort && $sort[0] === $col && $sort[1] === 'asc' ? 'desc' : 'asc';
            $html = html_writer::link(new moodle_url($pageurl, ['sort' => $col, 'dir' => $dir, 'page' => 0]), $html,
                ['title' => $s('sortby', $label), 'class' => 'text-reset']);
            if ($sort && $sort[0] === $col) {
                $html .= ' ' . $OUTPUT->pix_icon($sort[1] === 'asc' ? 't/sort_asc' : 't/sort_desc',
                    $s($sort[1] === 'asc' ? 'sortedasc' : 'sorteddesc'));
            }
        }
        if (isset($help[$col])) {
            $html .= ' ' . $OUTPUT->render_from_template('core/help_icon', [
                'text' => $help[$col],
                'alt' => get_string('helpprefix2', '', $label),
                'title' => get_string('helpprefix2', '', $label),
                'ltr' => !right_to_left(),
                'icon' => (new pix_icon('help', get_string('helpprefix2', '', $label)))->export_for_template($OUTPUT),
            ]);
        }
        $head[] = html_writer::span($html, 'text-nowrap');
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable table-sm';
    $table->head = $head;
    foreach ($result['rows'] as $row) {
        $cells = [];
        foreach (array_keys($columns) as $col) {
            $cells[] = s((string) ($row[$col] ?? ''));
        }
        $table->data[] = $cells;
    }

    // Paging: rows per page, "showing a–b of n", page links above and below the table.
    $first = $page * $perpage + 1;
    $last = min($result['total'], ($page + 1) * $perpage);
    $choices = [];
    foreach ($perpageoptions as $n) {
        $choices[(new moodle_url($pageurl, ['perpage' => $n, 'page' => 0]))->out(false)] = $n;
    }
    $selected = (new moodle_url($pageurl, ['perpage' => $perpage, 'page' => 0]))->out(false);
    $perpageselect = new url_select($choices, $selected, null, 'nit-rp-perpage');
    $perpageselect->set_label($s('perpage'));
    $perpageselect->class = 'd-inline-block';
    $pagingbar = $OUTPUT->paging_bar($result['total'], $page, $perpage, $pageurl);
    echo html_writer::div(
        html_writer::div($s('showingrows', (object) ['first' => $first, 'last' => $last, 'total' => $result['total']]),
            'small text-muted') .
        html_writer::div($OUTPUT->render($perpageselect), 'small'),
        'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1');
    echo $pagingbar;
    echo html_writer::div(html_writer::table($table), 'table-responsive');
    echo $pagingbar;
}

echo $OUTPUT->footer();

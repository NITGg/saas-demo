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
 * Site administration → Users → Teacher applications → Applications: the list, by status.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use local_nit_tutors\local\admin_tabs;
use local_nit_tutors\local\application;

$status = optional_param('status', application::PENDING, PARAM_ALPHA);
$search = optional_param('q', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 30;

admin_tabs::setup('applications', ['status' => $status, 'q' => $search]);
$baseurl = new moodle_url('/local/nit_tutors/admin/applications.php', ['status' => $status, 'q' => $search]);
$str = fn(string $k, $a = null) => get_string($k, 'local_nit_tutors', $a);

echo $OUTPUT->header();
echo admin_tabs::render('applications');

// Status filter with counts (a row of pills under the page tabs).
$counts = application::counts();
$filters = [];
foreach (application::STATUSES as $st) {
    $filters[$st] = $str('status_' . $st) . ' (' . $counts[$st] . ')';
}
$filters['updates'] = $str('tab_updates') . ' (' . application::updates_waiting() . ')';
$filters['all'] = get_string('all') . ' (' . array_sum($counts) . ')';
echo html_writer::start_tag('nav', ['class' => 'nittu-tabs mb-3', 'aria-label' => $str('col_status')]);
foreach ($filters as $st => $label) {
    echo $st === $status
        ? html_writer::span(s($label), 'nittu-tab is-active', ['aria-current' => 'page'])
        : html_writer::link(new moodle_url('/local/nit_tutors/admin/applications.php', ['status' => $st]), s($label),
            ['class' => 'nittu-tab']);
}
echo html_writer::end_tag('nav');

// Search.
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'form-inline d-flex gap-2 mb-3', 'action' => $baseurl->out_omit_querystring()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'status', 'value' => $status]);
echo html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $search, 'class' => 'form-control',
    'placeholder' => $str('searchapplicants')]);
echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

$result = application::search($status === 'all' ? '' : $status, $search, $page, $perpage);
if (!$result['rows']) {
    echo $OUTPUT->notification($str('noapplications'), 'info');
} else {
    $table = new html_table();
    $table->head = [get_string('fullname'), get_string('email'), $str('col_status'), $str('col_submitted'),
        $str('col_video'), $str('col_quals'), $str('tab_badges'), ''];
    $date = get_string('strftimedatetimeshort', 'langconfig');
    $badges = \local_nit_tutors\local\search::badges_of(array_map(fn($r) => (int) $r->userid, $result['rows']));
    foreach ($result['rows'] as $r) {
        $user = user_picture::unalias($r, null, 'uid');
        $quals = \local_nit_tutors\local\qualifications::list((int) $r->userid);
        $verified = count(array_filter($quals, fn($q) => $q->verified));
        $reviewurl = new moodle_url('/local/nit_tutors/admin/application.php', ['userid' => $r->userid]);
        $table->data[] = [
            $OUTPUT->user_picture($user, ['size' => 30, 'link' => false]) . ' ' . html_writer::link($reviewurl, fullname($user)),
            s($user->email),
            html_writer::span($str('status_' . $r->status), 'badge bg-secondary nittu-st-' . $r->status),
            $r->timesubmitted ? userdate($r->timesubmitted, $date) : '-',
            $r->videostatus ? $str('videostatus_' . $r->videostatus) : '-',
            $verified . ' / ' . count($quals),
            implode(' ', array_map(fn($b) => html_writer::span(format_string($b->name), 'nittu-badge nittu-badge--' . $b->color),
                $badges[(int) $r->userid] ?? [])),
            html_writer::link($reviewurl, $str('review'), ['class' => 'btn btn-sm btn-outline-primary']),
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($result['total'], $page, $perpage, $baseurl);
}
echo $OUTPUT->footer();

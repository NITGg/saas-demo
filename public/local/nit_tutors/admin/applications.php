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
require_once($CFG->libdir . '/adminlib.php');

use local_nit_tutors\local\application;

$status = optional_param('status', application::PENDING, PARAM_ALPHA);
$search = optional_param('q', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 30;

admin_externalpage_setup('local_nit_tutors_applications', '', ['status' => $status, 'q' => $search]);
$baseurl = new moodle_url('/local/nit_tutors/admin/applications.php', ['status' => $status, 'q' => $search]);
$str = fn(string $k, $a = null) => get_string($k, 'local_nit_tutors', $a);

echo $OUTPUT->header();
echo $OUTPUT->heading($str('applications'));

// Status tabs with counts.
$counts = application::counts();
$tabs = [];
foreach (application::STATUSES as $st) {
    $tabs[] = new tabobject($st, new moodle_url('/local/nit_tutors/admin/applications.php', ['status' => $st]),
        $str('status_' . $st) . ' (' . $counts[$st] . ')');
}
$tabs[] = new tabobject('video', new moodle_url('/local/nit_tutors/admin/applications.php', ['status' => 'video']),
    $str('tab_videos') . ' (' . application::videos_waiting() . ')');
$tabs[] = new tabobject('all', new moodle_url('/local/nit_tutors/admin/applications.php', ['status' => 'all']),
    get_string('all') . ' (' . array_sum($counts) . ')');
echo $OUTPUT->tabtree($tabs, $status);

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
        $str('col_video'), $str('col_quals'), $str('supertutor'), ''];
    $date = get_string('strftimedatetimeshort', 'langconfig');
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
            $r->badge ? '★' : '',
            html_writer::link($reviewurl, $str('review'), ['class' => 'btn btn-sm btn-outline-primary']),
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($result['total'], $page, $perpage, $baseurl);
}
echo $OUTPUT->footer();

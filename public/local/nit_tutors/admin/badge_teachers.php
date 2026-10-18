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
 * Teacher applications → Badges → one badge's teachers: every approved teacher, whether they wear
 * the badge, and the reviewer's choice for each (by the badge's method / always give / never give).
 * The way to give a manual badge.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use local_nit_tutors\local\admin_tabs;
use local_nit_tutors\local\application;
use local_nit_tutors\local\badge;

$id = required_param('id', PARAM_INT);
$search = optional_param('q', '', PARAM_TEXT);
admin_tabs::setup('badges', ['id' => $id]);
$PAGE->set_url(new moodle_url('/local/nit_tutors/admin/badge_teachers.php', ['id' => $id, 'q' => $search]));
$b = badge::get($id) ?? throw new moodle_exception('invalidrecord', 'error');
$url = new moodle_url('/local/nit_tutors/admin/badge_teachers.php', ['id' => $id, 'q' => $search]);
$str = fn(string $k, $a = null) => get_string($k, 'local_nit_tutors', $a);

if (optional_param('save', 0, PARAM_BOOL) && confirm_sesskey()) {
    foreach (optional_param_array('mode', [], PARAM_ALPHA) as $userid => $mode) {
        if (application::is_approved((int) $userid) && badge::mode($id, (int) $userid) !== $mode) {
            badge::set_mode((int) $userid, $id, $mode);
        }
    }
    redirect($url, $str('done'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$result = application::search(application::APPROVED, $search, 0, 500);
$stats = [];
echo $OUTPUT->header();
echo admin_tabs::render('badges');
echo html_writer::tag('p', html_writer::link(new moodle_url('/local/nit_tutors/admin/badges.php'), '← ' . $str('tab_badges')));
echo $OUTPUT->heading(html_writer::span(format_string($b->name), 'nittu-badge nittu-badge--' . $b->color), 3);
echo html_writer::tag('p', s(badge::rules_text($b)), ['class' => 'text-muted']);
echo html_writer::tag('p', $str('badgeteachers_help'), ['class' => 'small text-muted']);

echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'd-flex gap-2 mb-3', 'action' => $url->out_omit_querystring()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
echo html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $search, 'class' => 'form-control',
    'placeholder' => $str('searchapplicants')]);
echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

if (!$result['rows']) {
    echo $OUTPUT->notification($str('noapprovedteachers'), 'info');
} else {
    $wearing = array_map('intval', $DB->get_fieldset_select(badge::USERS, 'userid', 'badgeid = ? AND awarded = 1', [$id]));
    $modes = [badge::AUTO => $str('badgemode_auto' . ($b->method === badge::METHOD_MANUAL ? '_manual' : '')),
        badge::ON => $str('badgemode_on'), badge::OFF => $str('badgemode_off')];
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1]);
    $table = new html_table();
    $table->head = [get_string('fullname'), $str('badge_wears'), $str('badge_method')];
    foreach ($result['rows'] as $r) {
        $user = user_picture::unalias($r, null, 'uid');
        $uid = (int) $r->userid;
        $table->data[] = [
            $OUTPUT->user_picture($user, ['size' => 30, 'link' => false]) . ' ' . s(fullname($user)),
            in_array($uid, $wearing, true) ? html_writer::span($str('badge_wears'), 'badge bg-success') : '',
            html_writer::select($modes, 'mode[' . $uid . ']', badge::mode($id, $uid), false,
                ['class' => 'form-select form-select-sm', 'aria-label' => fullname($user)]),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
}
echo $OUTPUT->footer();

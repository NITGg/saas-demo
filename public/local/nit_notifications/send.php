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
 * Send a notification: write it, see how many users it reaches, confirm.
 *
 * Admins and site managers reach it from Site administration → Notifications and
 * may send to any audience. A manager assigned on a category or a course reaches
 * it from the course's "More" menu and may send only to the students or teachers
 * of the courses they manage.
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_notifications\audience;
use local_nit_notifications\sender;

$courseid = optional_param('courseid', 0, PARAM_INT);
$pageurl = new moodle_url('/local/nit_notifications/send.php', $courseid ? ['courseid' => $courseid] : []);
$s = fn(string $key, $a = null) => get_string($key, 'local_nit_notifications', $a);

if (audience::is_sitewide()) {
    admin_externalpage_setup('local_nit_notifications_send', '', null, $pageurl);
} else {
    require_login();
    if (!audience::can_send()) {
        throw new required_capability_exception(context_system::instance(), audience::CAP, 'nopermissions', '');
    }
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url($pageurl);
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title($s('sendnotification'));
    $PAGE->set_heading($s('sendnotification'));
}

// What this sender may choose.
$audiences = [];
foreach (audience::allowed() as $key) {
    $audiences[$key] = $s('audience_' . $key);
}
$scope = audience::course_ids();
$courses = [];
if ($scope === null) {
    $courses[0] = get_string('choosedots');
    $rows = $DB->get_records_select('course', 'id <> :site', ['site' => SITEID], 'fullname', 'id, fullname');
} else {
    $courses[0] = $s('allmycourses');
    $rows = $scope ? $DB->get_records_list('course', 'id', $scope, 'fullname', 'id, fullname') : [];
}
foreach ($rows as $c) {
    $courses[(int) $c->id] = format_string($c->fullname, true, ['context' => context_course::instance($c->id)]);
}

// Step 2: confirmed → send.
if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
    $data = [
        'audience' => required_param('audience', PARAM_ALPHANUMEXT),
        'courseid' => optional_param('courseid', 0, PARAM_INT),
        'type' => required_param('type', PARAM_ALPHA),
        'title' => required_param('title', PARAM_TEXT),
        'body' => required_param('body', PARAM_TEXT),
        'url' => optional_param('url', '', PARAM_RAW_TRIMMED),
        'email' => optional_param('email', 0, PARAM_BOOL),
    ];
    try {
        $notif = sender::send($data);
    } catch (moodle_exception $e) {
        redirect($pageurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    $message = $notif->status === 'done'
        ? $s('sentdone', (object) ['sent' => $notif->sent, 'failed' => $notif->failed])
        : $s('sentqueued', $notif->total);
    redirect(new moodle_url('/local/nit_notifications/log.php', ['id' => $notif->id]), $message, null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$form = new \local_nit_notifications\form\compose_form($pageurl, ['audiences' => $audiences, 'courses' => $courses]);
$form->set_data(['courseid' => $courseid, 'type' => 'general',
    'audience' => $courseid ? audience::COURSE_STUDENTS : array_key_first($audiences)]);

if ($form->is_cancelled()) {
    redirect($pageurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($s('sendnotification'));

if ($data = $form->get_data()) {
    // Step 1 done: show what will be sent and to how many, then confirm.
    $iscourse = audience::is_course_audience($data->audience);
    $count = audience::count($data->audience, $iscourse ? (int) $data->courseid : 0, (int) $USER->id);
    $target = $audiences[$data->audience] . ($iscourse ? ' — ' . ($courses[(int) $data->courseid] ?? '') : '');

    echo html_writer::start_div('card mb-3', ['style' => 'max-width:720px']);
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h5', s($data->title), ['class' => 'card-title']);
    echo html_writer::div(nl2br(s($data->body)), 'card-text mb-3');
    if ($data->url !== '') {
        echo html_writer::div(html_writer::link($data->url, s($data->url), ['target' => '_blank']), 'mb-3');
    }
    $facts = [
        $s('audience') => $target,
        $s('type') => $s('type_' . $data->type),
        $s('alsoemail') => $data->email ? get_string('yes') : get_string('no'),
    ];
    foreach ($facts as $label => $value) {
        echo html_writer::div(html_writer::tag('strong', s($label) . ': ') . s($value), 'small mb-1');
    }
    echo html_writer::end_div();
    echo html_writer::end_div();

    if ($count === 0) {
        echo $OUTPUT->notification($s('err_norecipients'), \core\output\notification::NOTIFY_WARNING);
        echo $OUTPUT->single_button($pageurl, $s('back'), 'get');
    } else {
        echo $OUTPUT->notification($s('confirmcount', $count) . ($count > sender::SYNC_LIMIT ? ' ' . $s('bigsendnote') : ''),
            \core\output\notification::NOTIFY_INFO, false);
        $hidden = ['sesskey' => sesskey(), 'confirm' => 1, 'audience' => $data->audience,
            'courseid' => $iscourse ? (int) $data->courseid : 0, 'type' => $data->type, 'title' => $data->title,
            'body' => $data->body, 'url' => $data->url, 'email' => (int) $data->email];
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false), 'class' => 'd-flex gap-2']);
        foreach ($hidden as $name => $value) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        echo html_writer::tag('button', $s('sendnow', $count), ['type' => 'submit', 'class' => 'btn btn-primary']);
        echo html_writer::link($pageurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('form');
    }
} else {
    if (!audience::is_sitewide()) {
        echo $OUTPUT->notification($s('scopednote'), \core\output\notification::NOTIFY_INFO, false);
    }
    $form->display();
}

echo html_writer::div(html_writer::link(new moodle_url('/local/nit_notifications/log.php'), $s('notificationlog')), 'mt-4');
echo $OUTPUT->footer();

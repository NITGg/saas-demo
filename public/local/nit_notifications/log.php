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
 * The notification log: every notification sent by hand or by the system, who
 * sent it, to whom, and how many got it, failed or read it. ?id=N opens one with
 * its recipients.
 *
 * Admins and site managers see everything; a scoped manager sees what they sent
 * and what went to the courses they manage.
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_notifications\audience;
use local_nit_notifications\sender;

$id       = optional_param('id', 0, PARAM_INT);
$type     = optional_param('type', '', PARAM_ALPHA);
$source   = optional_param('source', '', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$q        = trim(optional_param('q', '', PARAM_TEXT));
$state    = optional_param('state', '', PARAM_ALPHA);
$page     = optional_param('page', 0, PARAM_INT);

$params = array_filter(['id' => $id, 'type' => $type, 'source' => $source, 'courseid' => $courseid, 'q' => $q,
    'state' => $state]);
$pageurl = new moodle_url('/local/nit_notifications/log.php', $params);
$listurl = new moodle_url('/local/nit_notifications/log.php');
$s = fn(string $key, $a = null) => get_string($key, 'local_nit_notifications', $a);

if (audience::is_sitewide()) {
    admin_externalpage_setup('local_nit_notifications_log', '', null, $pageurl);
} else {
    require_login();
    if (!audience::can_send()) {
        throw new required_capability_exception(context_system::instance(), audience::CAP, 'nopermissions', '');
    }
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url($pageurl);
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title($s('notificationlog'));
    $PAGE->set_heading($s('notificationlog'));
}

$datetime = get_string('strftimedatetimeshort', 'langconfig');

/**
 * "Students — Course X", "All teachers", "System: subscription_expiry".
 *
 * @param stdClass $n
 * @return string
 */
$audiencetext = function(stdClass $n) use ($s): string {
    $text = $n->audience === audience::USERS ? $s('audience_users') : $s('audience_' . $n->audience);
    if ((int) $n->courseid > 1 && !empty($n->coursename)) {
        $text .= ' — ' . format_string($n->coursename);
    } else if (audience::is_course_audience($n->audience)) {
        $text .= ' — ' . $s('allmycourses');
    }
    return $text;
};

// One notification and its recipients.
if ($id) {
    $notif = $DB->get_record('local_nit_notif', ['id' => $id], '*', MUST_EXIST);
    if (!sender::can_view($notif)) {
        throw new required_capability_exception(context_system::instance(), audience::CAP, 'nopermissions', '');
    }
    $notif->coursename = (int) $notif->courseid > 1 ? (string) $DB->get_field('course', 'fullname', ['id' => $notif->courseid]) : '';
    $sender = (int) $notif->senderid ? core_user::get_user((int) $notif->senderid) : null;
    $reads = sender::read_counts([$id])[$id] ?? 0;

    echo $OUTPUT->header();
    echo html_writer::div(html_writer::link($listurl, '« ' . $s('notificationlog')), 'mb-3');
    echo $OUTPUT->heading(s(\local_nit_notifications\mlang::resolve($notif->title)));
    echo html_writer::div(\local_nit_notifications\output::versions($notif->title, $notif->body), 'mb-3',
        ['style' => 'max-width:720px']);
    if (!empty($notif->url)) {
        echo html_writer::div(html_writer::link($notif->url, s($notif->url), ['target' => '_blank']), 'mb-3');
    }
    $facts = [
        $s('sender') => $sender ? fullname($sender) : $s('system') . ' (' . $notif->source . ')',
        $s('audience') => $audiencetext($notif),
        $s('type') => $s('type_' . $notif->type),
        get_string('date') => userdate($notif->timecreated, $datetime),
        $s('alsoemail') => $notif->email ? get_string('yes') : get_string('no'),
        get_string('status') => $s('status_' . $notif->status),
    ];
    foreach ($facts as $label => $value) {
        echo html_writer::div(html_writer::tag('strong', s($label) . ': ') . s($value), 'small mb-1');
    }

    // Totals; each is a filter of the recipient list.
    $counts = [
        '' => [$s('recipients'), (int) $notif->total, 'bg-secondary'],
        'sent' => [$s('state_sent'), (int) $notif->sent, 'bg-success'],
        'failed' => [$s('state_failed'), (int) $notif->failed, 'bg-danger'],
        'read' => [$s('state_read'), $reads, 'bg-info text-dark'],
        'unread' => [$s('state_unread'), max(0, (int) $notif->sent - $reads), 'bg-light text-dark'],
    ];
    if ($notif->email) {
        $emails = sender::email_counts($id);
        $counts['emailsent'] = [$s('email_sent'), $emails['sent'], 'bg-success'];
        $counts['emailfailed'] = [$s('email_failed'), $emails['failed'], 'bg-danger'];
        $counts['emailoff'] = [$s('email_off'), $emails['off'], 'bg-light text-dark'];
    }
    if ($notif->status !== 'done') {
        $counts['queued'] = [$s('state_queued'), max(0, (int) $notif->total - $notif->sent - $notif->failed),
            'bg-warning text-dark'];
    }
    $chips = '';
    foreach ($counts as $key => [$label, $n, $class]) {
        $chips .= html_writer::link(new moodle_url($pageurl, ['state' => $key, 'page' => 0]),
            s($label) . ' ' . html_writer::span($n, 'badge ' . $class),
            ['class' => 'btn btn-sm ' . ($state === $key ? 'btn-primary' : 'btn-outline-secondary')]);
    }
    echo html_writer::div($chips, 'd-flex flex-wrap gap-2 my-3');

    $list = sender::recipients($id, $state, $page, 50);
    $labels = [sender::RCPT_QUEUED => $s('state_queued'), sender::RCPT_SENT => $s('state_sent'),
        sender::RCPT_FAILED => $s('state_failed')];
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table-sm';
    $emaillabels = [sender::EMAIL_NONE => '—', sender::EMAIL_SENT => $s('email_sent'),
        sender::EMAIL_FAILED => $s('email_failed'), sender::EMAIL_BY_MOODLE => $s('email_bymoodle'),
        sender::EMAIL_OFF => $s('email_off')];
    $table->head = [get_string('fullname'), get_string('email'), get_string('status'), $s('timesent'), $s('timeread')];
    if ($notif->email) {
        $table->head[] = $s('emailcopy');
    }
    foreach ($list['items'] as $r) {
        $table->data[] = [
            html_writer::link(new moodle_url('/user/profile.php', ['id' => $r->userid]), s($r->fullname)),
            s($r->email),
            $labels[(int) $r->status] ?? '',
            $r->timesent ? userdate($r->timesent, $datetime) : '—',
            $r->timeread ? userdate($r->timeread, $datetime) : '—',
        ];
        if ($notif->email) {
            $status = (int) $r->emailstatus;
            $table->data[array_key_last($table->data)][] = $status === sender::EMAIL_FAILED
                ? html_writer::span($emaillabels[$status], 'text-danger fw-bold') : ($emaillabels[$status] ?? '');
        }
    }
    echo $list['items'] ? html_writer::table($table)
        : $OUTPUT->notification($s('norecipients'), \core\output\notification::NOTIFY_INFO, false);
    echo $OUTPUT->paging_bar($list['total'], $page, 50, $pageurl);
    echo $OUTPUT->footer();
    exit;
}

// The list.
$scope = audience::course_ids();
$courseoptions = [0 => get_string('allcourses', 'local_nit_notifications')];
$sql = "SELECT DISTINCT c.id, c.fullname FROM {local_nit_notif} n JOIN {course} c ON c.id = n.courseid";
$cparams = [];
if ($scope !== null) {
    [$insql, $cparams] = $scope ? $DB->get_in_or_equal($scope) : ['= 0', []];
    $sql .= " WHERE c.id $insql";
}
foreach ($DB->get_records_sql($sql . ' ORDER BY c.fullname', $cparams) as $c) {
    $courseoptions[(int) $c->id] = format_string($c->fullname, true, ['context' => context_course::instance($c->id)]);
}
$typeoptions = ['' => $s('alltypes')];
foreach (sender::TYPES as $t) {
    $typeoptions[$t] = $s('type_' . $t);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($s('notificationlog'));
echo html_writer::div(html_writer::link(new moodle_url('/local/nit_notifications/send.php'), $s('sendnotification'),
    ['class' => 'btn btn-primary']), 'mb-3');

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $listurl->out_omit_querystring(),
    'class' => 'd-flex flex-wrap align-items-end gap-2 mb-3']);
$field = fn(string $label, string $id, string $html) => html_writer::div(
    html_writer::label($label, $id, true, ['class' => 'form-label small mb-1 d-block']) . $html);
echo $field($s('source'), 'nit-nl-source', html_writer::select(['' => $s('allsources'), 'manual' => $s('source_manual'),
    'auto' => $s('source_auto')], 'source', $source, false, ['id' => 'nit-nl-source', 'class' => 'form-select']));
echo $field($s('type'), 'nit-nl-type', html_writer::select($typeoptions, 'type', $type, false,
    ['id' => 'nit-nl-type', 'class' => 'form-select']));
echo $field(get_string('course'), 'nit-nl-course', html_writer::select($courseoptions, 'courseid', $courseid, false,
    ['id' => 'nit-nl-course', 'class' => 'form-select']));
echo $field(get_string('search'), 'nit-nl-q', html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q',
    'value' => $q, 'id' => 'nit-nl-q', 'class' => 'form-control', 'placeholder' => $s('searchplaceholder')]));
echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

$list = sender::log(['type' => $type, 'source' => $source, 'courseid' => $courseid, 'q' => $q], $page, 30);
if (!$list['items']) {
    echo $OUTPUT->notification($s('nonotifications'), \core\output\notification::NOTIFY_INFO, false);
    echo $OUTPUT->footer();
    exit;
}
$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm align-middle';
$table->head = [get_string('date'), $s('title'), $s('type'), $s('audience'), $s('sender'),
    $s('recipients'), $s('state_sent'), $s('state_failed'), $s('state_read'), get_string('status')];
foreach ($list['items'] as $n) {
    $table->data[] = [
        html_writer::span(userdate($n->timecreated, $datetime), 'text-nowrap'),
        html_writer::link(new moodle_url($listurl, ['id' => $n->id]), s(\local_nit_notifications\mlang::resolve($n->title))),
        $s('type_' . $n->type),
        s($audiencetext($n)),
        $n->sendername !== '' ? s($n->sendername) : html_writer::span($s('system'), 'text-muted'),
        (int) $n->total,
        (int) $n->sent,
        (int) $n->failed ? html_writer::span((int) $n->failed, 'text-danger fw-bold') : 0,
        (int) $n->readcount,
        $s('status_' . $n->status),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->paging_bar($list['total'], $page, 30, $pageurl);
echo $OUTPUT->footer();

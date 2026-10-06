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
 * Review moderation: approve, reject (with a reason) or delete learners' course
 * and teacher reviews. Pending ones are listed first.
 *
 * Site admins and site managers reach it from Site administration and see every
 * review; a manager assigned on a category or a course reaches it from the
 * course's "More" menu and sees only the reviews of the courses they manage.
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_reviews\api;

$status   = optional_param('status', (string) api::STATUS_PENDING, PARAM_ALPHANUM); // A status number, or 'all'.
$rating   = optional_param('rating', 0, PARAM_INT);
$type     = optional_param('type', '', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$q        = trim(optional_param('q', '', PARAM_TEXT));
$page     = optional_param('page', 0, PARAM_INT);
$perpage  = 30;

$filters = ['status' => $status, 'rating' => $rating, 'type' => $type, 'courseid' => $courseid, 'q' => $q];
$pageurl = new moodle_url('/local/nit_reviews/moderate.php', array_filter($filters, fn($v) => $v !== '' && $v !== 0));

$syscontext = context_system::instance();
if (has_capability('local/nit_reviews:moderate', $syscontext)) {
    admin_externalpage_setup('local_nit_reviews_moderate', '', null, $pageurl);
} else {
    require_login();
    if (!api::can_moderate_any()) {
        throw new required_capability_exception($syscontext, 'local/nit_reviews:moderate', 'nopermissions', '');
    }
    $PAGE->set_context($syscontext);
    $PAGE->set_url($pageurl);
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title(get_string('moderatereviews', 'local_nit_reviews'));
    $PAGE->set_heading(get_string('moderatereviews', 'local_nit_reviews'));
}
$s = fn(string $key, $a = null) => get_string($key, 'local_nit_reviews', $a);

// Actions: one review (approve / reject / delete) or the ticked ones (approve / reject).
$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '' && confirm_sesskey()) {
    $ids = optional_param_array('ids', [], PARAM_INT);
    if ($one = optional_param('reviewid', 0, PARAM_INT)) {
        $ids = [$one];
    }
    $reason = trim(optional_param('reason', '', PARAM_TEXT));
    $done = 0;
    $failed = 0;
    foreach (array_unique(array_filter($ids)) as $id) {
        try {
            if ($action === 'approve') {
                api::approve((int) $id);
            } else if ($action === 'reject') {
                api::reject((int) $id, $reason);
            } else if ($action === 'delete') {
                api::delete_review((int) $id);
            } else {
                continue;
            }
            $done++;
        } catch (moodle_exception $e) {
            $failed++;
        }
    }
    $message = $s('moderation_done', $done) . ($failed ? ' ' . $s('moderation_failed', $failed) : '');
    redirect($pageurl, $message, null, $failed ? \core\output\notification::NOTIFY_WARNING
        : \core\output\notification::NOTIFY_SUCCESS);
}

$list = api::moderation_list(['status' => $status === 'all' ? null : (int) $status] + $filters, $page, $perpage);

// The courses to filter by: those with reviews, within what this user moderates.
$scope = api::moderated_course_ids();
$sql = "SELECT DISTINCT c.id, c.fullname FROM {local_nit_reviews} r JOIN {course} c ON c.id = r.courseid";
$params = [];
if ($scope !== null) {
    [$insql, $params] = $scope ? $DB->get_in_or_equal($scope) : ['= 0', []];
    $sql .= " WHERE c.id $insql";
}
$courseoptions = [0 => $s('allcourses')];
foreach ($DB->get_records_sql($sql . ' ORDER BY c.fullname', $params) as $c) {
    $courseoptions[(int) $c->id] = format_string($c->fullname, true, ['context' => context_course::instance($c->id)]);
}
if ($scope === null) {
    $courseoptions[-1] = $s('privatelessons');
}

// Teacher names for the teacher reviews on this page.
$teacherids = array_unique(array_filter(array_column($list['reviews'], 'teacherid')));
$teachers = $teacherids ? $DB->get_records_list('user', 'id', $teacherids, '',
    'id, ' . \core_user\fields::for_name()->get_sql('', false, '', '', false)->selects) : [];

$statuslabels = [
    api::STATUS_PENDING => [$s('status_pending'), 'bg-warning text-dark'],
    api::STATUS_APPROVED => [$s('status_approved'), 'bg-success'],
    api::STATUS_REJECTED => [$s('status_rejected'), 'bg-secondary'],
];

echo $OUTPUT->header();
echo $OUTPUT->heading($s('moderatereviews'));

// Status tabs, with the number waiting.
$pendingcount = api::pending_count();
$tabs = [];
foreach ([(string) api::STATUS_PENDING => $s('status_pending') . " ($pendingcount)",
          (string) api::STATUS_APPROVED => $s('status_approved'),
          (string) api::STATUS_REJECTED => $s('status_rejected'),
          'all' => $s('allstatuses')] as $key => $label) {
    $tabparams = ['status' => $key, 'page' => 0];
    if ($rating) {
        $tabparams['rating'] = $rating;
    }
    if ($type !== '') {
        $tabparams['type'] = $type;
    }
    if ($courseid) {
        $tabparams['courseid'] = $courseid;
    }
    if ($q !== '') {
        $tabparams['q'] = $q;
    }
    $tabs[] = new tabobject($key, new moodle_url('/local/nit_reviews/moderate.php', $tabparams), $label);
}
echo $OUTPUT->tabtree($tabs, $status);

// Filters.
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $pageurl->out_omit_querystring(),
    'class' => 'd-flex flex-wrap align-items-end gap-2 mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'status', 'value' => $status]);

$ratingoptions = [
    0 => $s('allratings'),
    5 => '★★★★★ (5)',
    4 => '★★★★☆ (4)',
    3 => '★★★☆☆ (3)',
    2 => '★★☆☆☆ (2)',
    1 => '★☆☆☆☆ (1)',
];
echo html_writer::div(html_writer::label($s('filterbystars'), 'nit-rv-rating', true, ['class' => 'form-label small mb-1 d-block'])
    . html_writer::select($ratingoptions, 'rating', $rating, false, ['id' => 'nit-rv-rating', 'class' => 'form-select']));

echo html_writer::div(html_writer::label($s('reviewtype'), 'nit-rv-type', true, ['class' => 'form-label small mb-1 d-block'])
    . html_writer::select(['' => $s('alltypes'), 'course' => $s('type_course'), 'teacher' => $s('type_teacher')],
        'type', $type, false, ['id' => 'nit-rv-type', 'class' => 'form-select']));
echo html_writer::div(html_writer::label(get_string('course'), 'nit-rv-course', true, ['class' => 'form-label small mb-1 d-block'])
    . html_writer::select($courseoptions, 'courseid', $courseid, false, ['id' => 'nit-rv-course', 'class' => 'form-select']));
echo html_writer::div(html_writer::label(get_string('search'), 'nit-rv-q', true, ['class' => 'form-label small mb-1 d-block'])
    . html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $q, 'id' => 'nit-rv-q',
        'class' => 'form-control', 'placeholder' => $s('searchplaceholder')]));
echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-primary']);

if ($rating || $type !== '' || $courseid || $q !== '') {
    $reseturl = new moodle_url('/local/nit_reviews/moderate.php', ['status' => $status]);
    echo html_writer::link($reseturl, get_string('reset'), ['class' => 'btn btn-outline-secondary']);
}
echo html_writer::end_tag('form');

if (!$list['reviews']) {
    echo $OUTPUT->notification($s('noreviews'), \core\output\notification::NOTIFY_INFO, false);
    echo $OUTPUT->footer();
    exit;
}

// The list, inside one form for the bulk actions.
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false), 'id' => 'nit-rv-bulk']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm align-middle';
$table->head = [
    html_writer::checkbox('selectall', 1, false, '', ['id' => 'nit-rv-all', 'title' => get_string('selectall')]),
    $s('author'), $s('reviewof'), $s('rating'), $s('comment'), get_string('status'), get_string('date'), get_string('actions'),
];
foreach ($list['reviews'] as $r) {
    if ($r['teacherid']) {
        $teacher = $teachers[$r['teacherid']] ?? null;
        $target = html_writer::tag('strong', $teacher ? s(fullname($teacher)) : '#' . $r['teacherid'])
            . html_writer::div(s($r['courseid'] ? $r['coursename'] : $s('privatelessons')), 'small text-muted');
    } else {
        $target = html_writer::tag('strong', s($r['coursename'])) . html_writer::div($s('type_course'), 'small text-muted');
    }
    [$label, $class] = $statuslabels[$r['status']] ?? [$r['status'], 'bg-light'];
    $badge = html_writer::span($label, 'badge ' . $class);
    if ($r['status'] === api::STATUS_REJECTED && $r['rejectreason'] !== '') {
        $badge .= html_writer::div(s($r['rejectreason']), 'small text-muted mt-1');
    }

    $rowaction = fn(string $act, string $label, string $btn, array $extra = []) => html_writer::tag('button', $label,
        ['type' => 'submit', 'name' => 'action', 'value' => $act, 'class' => "btn btn-sm $btn",
         'formaction' => (new moodle_url($pageurl, ['reviewid' => $r['id']]))->out(false)] + $extra);
    $actions = '';
    if ($r['status'] !== api::STATUS_APPROVED) {
        $actions .= $rowaction('approve', $s('approve'), 'btn-success');
    }
    if ($r['status'] !== api::STATUS_REJECTED) {
        $actions .= $rowaction('reject', $s('reject'), 'btn-outline-secondary', ['data-nit-rv-reject' => 1]);
    }
    $actions .= $rowaction('delete', get_string('delete'), 'btn-outline-danger',
        ['data-nit-rv-confirm' => $s('confirmdelete')]);

    $table->data[] = [
        html_writer::checkbox('ids[]', $r['id'], false, '', ['class' => 'nit-rv-check']),
        html_writer::div(html_writer::img($r['pictureurl'], '', ['class' => 'userpicture rounded-circle', 'width' => 30,
            'height' => 30]) . html_writer::span(s($r['fullname'])), 'd-flex align-items-center gap-2'),
        $target,
        html_writer::span(str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']), 'text-nowrap',
            ['title' => $r['rating'] . '/5']),
        $r['review'] === '' ? html_writer::span($s('nocomment'), 'text-muted') : nl2br(s($r['review'])),
        $badge,
        html_writer::span(userdate($r['timemodified'], get_string('strftimedatetimeshort', 'langconfig')), 'text-nowrap'),
        html_writer::div($actions, 'd-flex flex-wrap gap-1'),
    ];
}
echo html_writer::table($table);

// The rejection reason (optional) used by every "Reject" button, and the bulk buttons.
echo html_writer::div(
    html_writer::label($s('rejectreason'), 'nit-rv-reason', true, ['class' => 'form-label small mb-1 d-block'])
    . html_writer::empty_tag('input', ['type' => 'text', 'name' => 'reason', 'id' => 'nit-rv-reason',
        'maxlength' => api::MAX_REASON_LENGTH, 'class' => 'form-control', 'placeholder' => $s('rejectreason_help')]),
    'mb-2', ['style' => 'max-width:560px']);
echo html_writer::div(
    html_writer::tag('button', $s('approveselected'), ['type' => 'submit', 'name' => 'action', 'value' => 'approve',
        'class' => 'btn btn-success', 'data-nit-rv-bulk' => 1])
    . html_writer::tag('button', $s('rejectselected'), ['type' => 'submit', 'name' => 'action', 'value' => 'reject',
        'class' => 'btn btn-outline-secondary', 'data-nit-rv-bulk' => 1]),
    'd-flex gap-2 mb-3');
echo html_writer::end_tag('form');

echo $OUTPUT->paging_bar($list['total'], $page, $perpage, $pageurl);

// A row button acts on its own review only, so it must not send the ticked boxes;
// a bulk button needs at least one ticked box; delete asks first.
$nothingselected = json_encode($s('nothingselected'));
echo html_writer::script(<<<JS
(function() {
    var form = document.getElementById('nit-rv-bulk');
    var all = document.getElementById('nit-rv-all');
    var checks = function() { return form.querySelectorAll('.nit-rv-check'); };
    all && all.addEventListener('change', function() {
        checks().forEach(function(c) { c.checked = all.checked; });
    });
    form.addEventListener('submit', function(e) {
        var btn = e.submitter;
        if (!btn) { return; }
        if (btn.hasAttribute('data-nit-rv-confirm') && !window.confirm(btn.getAttribute('data-nit-rv-confirm'))) {
            e.preventDefault();
            return;
        }
        if (btn.hasAttribute('data-nit-rv-bulk')) {
            var ticked = Array.prototype.some.call(checks(), function(c) { return c.checked; });
            if (!ticked) { e.preventDefault(); window.alert($nothingselected); }
            return;
        }
        checks().forEach(function(c) { c.disabled = true; });
    });
})();
JS);

echo $OUTPUT->footer();

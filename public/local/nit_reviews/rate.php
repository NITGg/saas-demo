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
 * Rate a course and its teachers, or one teacher — stars and an optional comment
 * per target. The comment is shown once a moderator approves it.
 *
 *   rate.php?courseid=N              the course, then each of its teachers
 *   rate.php?teacherid=T[&courseid=N] that teacher in every course (and private
 *                                     lessons) the learner may rate them in
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_reviews\api;

$courseid  = optional_param('courseid', 0, PARAM_INT);
$teacherid = optional_param('teacherid', 0, PARAM_INT);
if (!$courseid && !$teacherid) {
    throw new moodle_exception('missingparam', 'error', '', 'courseid');
}

$s = fn(string $key, $a = null) => get_string($key, 'local_nit_reviews', $a);
$pageurl = new moodle_url('/local/nit_reviews/rate.php', array_filter(['courseid' => $courseid, 'teacherid' => $teacherid]));

if ($courseid > 1) {
    $course = get_course($courseid);
    require_login($course);
    $PAGE->set_context(context_course::instance($courseid));
    $backurl = new moodle_url('/course/view.php', ['id' => $courseid]);
} else {
    require_login();
    $PAGE->set_context(context_system::instance());
    $backurl = new moodle_url('/local/academy/teacher.php', ['id' => $teacherid]);
}
$teacher = $teacherid ? core_user::get_user($teacherid, '*', MUST_EXIST) : null;

$PAGE->set_url($pageurl);
$PAGE->set_pagelayout($courseid > 1 ? 'incourse' : 'standard');
$PAGE->set_title($teacher ? $s('rateteacher') : $s('ratecourseandteachers'));
$PAGE->set_heading($teacher ? fullname($teacher) : format_string($course->fullname));

// What may be rated here: [courseid, teacherid] pairs, teacherid 0 = the course.
$targets = [];
if ($teacher) {
    foreach (api::rateable_courses_for_teacher($teacherid) as $cid) {
        if (!$courseid || $cid === $courseid) {
            $targets[] = [$cid, $teacherid];
        }
    }
} else {
    if (api::can_rate($courseid)) {
        $targets[] = [$courseid, 0];
    }
    foreach (api::get_course_teachers($courseid) as $tid => $unused) {
        if (api::can_rate_teacher($courseid, $tid)) {
            $targets[] = [$courseid, $tid];
        }
    }
}

if (!$targets) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification($teacher ? $s('cannotrateteacher') : $s('mustenrol'),
        \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->continue_button($backurl);
    echo $OUTPUT->footer();
    exit;
}

// Save one target's form.
if (data_submitted() && confirm_sesskey()) {
    $pcourse = required_param('target_course', PARAM_INT);
    $pteacher = required_param('target_teacher', PARAM_INT);
    $rating = optional_param('rating', 0, PARAM_INT);
    $review = trim(optional_param('review', '', PARAM_TEXT));
    if (in_array([$pcourse, $pteacher], $targets, true) && $rating >= 1 && $rating <= 5) {
        try {
            $saved = api::save_validated($pcourse, $rating, $review, 0, $pteacher);
            $pending = (int) $saved->status === api::STATUS_PENDING;
            redirect($pageurl, $s($pending ? 'reviewsaved_pending' : 'reviewsaved'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        } catch (moodle_exception $e) {
            redirect($pageurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
        }
    }
    redirect($pageurl, $s('err_invalidrating'), null, \core\output\notification::NOTIFY_ERROR);
}

$coursenames = [];
$coursename = function(int $cid) use (&$coursenames, $s): string {
    if (!isset($coursenames[$cid])) {
        $coursenames[$cid] = $cid > 1
            ? format_string(get_course($cid)->fullname, true, ['context' => context_course::instance($cid)])
            : $s('privatelessons');
    }
    return $coursenames[$cid];
};

echo $OUTPUT->header();
echo html_writer::start_div('nit-brand-18 nitrv');
echo html_writer::tag('h2', s($teacher ? $s('rateteacher') : $s('ratecourseandteachers')), ['class' => 'nitrv__title']);

foreach ($targets as $i => [$cid, $tid]) {
    $existing = api::get_user_review($cid, 0, $tid);
    $curr = $existing ? (int) $existing->rating : 0;
    $uid = 'nitrv-' . $i;

    // Who or what this card rates.
    if ($tid) {
        $tuser = $teacher ?: core_user::get_user($tid);
        $pic = $OUTPUT->user_picture($tuser, ['size' => 56, 'link' => false, 'class' => 'nitrv__pic']);
        $head = $pic . html_writer::div(
            html_writer::div(s(fullname($tuser)), 'nitrv__name')
            . html_writer::div(s($s('type_teacher') . ' · ' . $coursename($cid)), 'nitrv__sub'), 'nitrv__who');
    } else {
        $head = html_writer::div(
            html_writer::div(s($coursename($cid)), 'nitrv__name')
            . html_writer::div(s($s('type_course')), 'nitrv__sub'), 'nitrv__who');
    }

    // The learner's current review status.
    $state = '';
    if ($existing) {
        $st = (int) $existing->status;
        if ($st === api::STATUS_PENDING) {
            $state = html_writer::div($s('mine_pending'), 'nitrv__state nitrv__state--pending');
        } else if ($st === api::STATUS_REJECTED) {
            $reason = trim((string) $existing->rejectreason);
            $state = html_writer::div($reason !== '' ? $s('mine_rejected_reason', $reason) : $s('mine_rejected'),
                'nitrv__state nitrv__state--rejected');
        } else {
            $state = html_writer::div($s('mine_approved'), 'nitrv__state nitrv__state--approved');
        }
    }

    $stars = '';
    for ($n = 5; $n >= 1; $n--) {
        $stars .= html_writer::empty_tag('input', ['type' => 'radio', 'id' => "$uid-star-$n", 'name' => 'rating',
            'value' => $n, 'required' => 'required'] + ($curr === $n ? ['checked' => 'checked'] : []));
        $stars .= html_writer::tag('label', '&#9733;', ['for' => "$uid-star-$n", 'title' => $n . '/5']);
    }

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false), 'class' => 'nitrv__card']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'target_course', 'value' => $cid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'target_teacher', 'value' => $tid]);
    echo html_writer::div($head, 'nitrv__head');
    echo $state;
    echo html_writer::div(s($s('yourrating')), 'nitrv__lbl');
    echo html_writer::div($stars, 'nitrv__stars', ['role' => 'radiogroup', 'aria-label' => $s('yourrating')]);
    echo html_writer::label($s('yourreview'), "$uid-text", true, ['class' => 'nitrv__lbl']);
    echo html_writer::tag('textarea', s($existing ? (string) $existing->review : ''), ['name' => 'review',
        'id' => "$uid-text", 'maxlength' => api::MAX_REVIEW_LENGTH, 'class' => 'nitrv__text']);
    echo html_writer::div(s($s('commentneedsapproval')), 'nitrv__hint');
    echo html_writer::tag('button', s($existing ? $s('updatereview') : $s('submitreview')),
        ['type' => 'submit', 'class' => 'nitrv__btn']);
    echo html_writer::end_tag('form');
}

echo html_writer::div(html_writer::link($backurl, s($s('back'))), 'nitrv__back');
echo html_writer::end_div();
echo $OUTPUT->footer();

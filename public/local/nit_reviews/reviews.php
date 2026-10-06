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
 * Course reviews and ratings overview page for teachers and managers.
 *
 * Accessible from the course "More" (المزيد) menu. Displays the course's overall
 * rating aggregate, star distribution, and the list of student reviews for the
 * course and its teachers.
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

use local_nit_reviews\api;

$courseid = optional_param('id', optional_param('courseid', 0, PARAM_INT), PARAM_INT);
if ($courseid <= 1) {
    throw new moodle_exception('invalidcourseid');
}

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);

// Check if user is course staff (editing teacher, teacher, manager, or admin).
if (!has_any_capability(['moodle/course:update', 'moodle/course:viewhiddensections', 'local/nit_reviews:moderate'], $context)) {
    throw new required_capability_exception($context, 'moodle/course:update', 'nopermissions', '');
}

$rating    = optional_param('rating', 0, PARAM_INT);
$type      = optional_param('type', '', PARAM_ALPHA);
$q         = trim(optional_param('q', '', PARAM_TEXT));
$page      = optional_param('page', 0, PARAM_INT);
$perpage   = 20;

$filters = [
    'rating' => $rating,
    'type'   => $type,
    'q'      => $q,
];

$pageparams = ['id' => $courseid] + array_filter($filters, fn($v) => $v !== '' && $v !== 0);
$pageurl = new moodle_url('/local/nit_reviews/reviews.php', $pageparams);

$PAGE->set_course($course);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('coursereviews', 'local_nit_reviews') . ' - ' . format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));

// Ensure navigation highlights the reviews node.
navigation_node::override_active_url(new moodle_url('/local/nit_reviews/reviews.php', ['id' => $courseid]));

$s = fn(string $key, $a = null) => get_string($key, 'local_nit_reviews', $a);

// Fetch stats and data.
$agg = api::get_aggregate($courseid);
$distribution = api::get_distribution($courseid);
$teachers = api::get_course_teachers($courseid);

// Query filtered reviews.
$results = api::get_course_all_reviews($courseid, $filters, $page, $perpage);
$total = $results['total'];
$reviews = $results['reviews'];

$canmoderate = has_capability('local/nit_reviews:moderate', $context);
$moderateurl = new moodle_url('/local/nit_reviews/moderate.php', ['courseid' => $courseid]);

echo $OUTPUT->header();

echo html_writer::start_div('nit-crv-wrap my-3');

// Header with title and quick actions.
echo html_writer::start_div('d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4');
echo html_writer::start_div();
echo html_writer::tag('h2', $s('coursereviews'), ['class' => 'h3 fw-bold mb-1']);
echo html_writer::tag('p', $s('coursereviews_desc'), ['class' => 'text-muted mb-0']);
echo html_writer::end_div();

if ($canmoderate) {
    echo html_writer::start_div('d-flex flex-wrap gap-2');
    echo html_writer::link($moderateurl,
        '<i class="fa fa-sliders me-1 ms-1"></i> ' . $s('gotomoderation'),
        ['class' => 'btn btn-outline-warning btn-sm d-inline-flex align-items-center']);
    echo html_writer::end_div();
}
echo html_writer::end_div(); // End header actions.

// Statistics summary card.
echo html_writer::start_div('card border-0 shadow-sm rounded-4 mb-4 nit-crv-statscard');
echo html_writer::start_div('card-body p-4');
echo html_writer::start_div('row g-4 align-items-center');

// Column 1: Big score & stars.
echo html_writer::start_div('col-12 col-md-4 text-center border-md-end');
$scoreformatted = $agg->count > 0 ? format_float($agg->avg, 1) : '0.0';
echo html_writer::div($scoreformatted, 'display-4 fw-bold text-dark mb-1');

// Stars.
$roundedavg = (int) round($agg->avg);
$starshtml = '';
for ($i = 1; $i <= 5; $i++) {
    $color = $i <= $roundedavg ? 'text-warning' : 'text-muted opacity-25';
    $starshtml .= '<i class="fa fa-star ' . $color . ' fs-4 mx-1"></i>';
}
echo html_writer::div($starshtml, 'mb-2');
echo html_writer::div($s('nreviews', $agg->count), 'badge bg-light text-dark fs-6 px-3 py-2 border rounded-pill');
echo html_writer::end_div();

// Column 2: Distribution bars (5 stars down to 1 star).
echo html_writer::start_div('col-12 col-md-8');
echo html_writer::start_div('d-flex flex-column gap-2');
for ($n = 5; $n >= 1; $n--) {
    $count = $distribution[$n] ?? 0;
    $pct = $agg->count > 0 ? (int) round(100 * $count / $agg->count) : 0;

    echo html_writer::start_div('d-flex align-items-center gap-3');
    echo html_writer::div($n . ' <i class="fa fa-star text-warning"></i>', 'small fw-bold text-nowrap', ['style' => 'width: 45px;']);
    echo html_writer::start_div('progress flex-grow-1', ['style' => 'height: 10px; border-radius: 6px; background-color: #e9ecef;']);
    echo html_writer::div('', 'progress-bar bg-warning', [
        'role' => 'progressbar',
        'style' => 'width: ' . $pct . '%;',
        'aria-valuenow' => $pct,
        'aria-valuemin' => '0',
        'aria-valuemax' => '100',
    ]);
    echo html_writer::end_div();
    echo html_writer::div($count . ' (' . $pct . '%)', 'small text-muted text-nowrap', ['style' => 'min-width: 65px; text-align: end;']);
    echo html_writer::end_div();
}
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // End row.
echo html_writer::end_div(); // End card body.
echo html_writer::end_div(); // End stats card.

// Filters form.
echo html_writer::start_div('card border-0 shadow-sm rounded-4 mb-4 bg-light');
echo html_writer::start_div('card-body p-3');
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/nit_reviews/reviews.php'))->out_omit_querystring(),
    'class'  => 'row g-2 align-items-end',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);

// Rating filter.
$ratingoptions = [
    0 => $s('allratings'),
    5 => '★★★★★ (5)',
    4 => '★★★★☆ (4)',
    3 => '★★★☆☆ (3)',
    2 => '★★☆☆☆ (2)',
    1 => '★☆☆☆☆ (1)',
];
echo html_writer::start_div('col-12 col-sm-6 col-md-3');
echo html_writer::label($s('filterbystars'), 'nit-flt-rating', true, ['class' => 'form-label small fw-semibold text-muted mb-1']);
echo html_writer::select($ratingoptions, 'rating', $rating, false, ['id' => 'nit-flt-rating', 'class' => 'form-select']);
echo html_writer::end_div();

// Type filter.
$typeoptions = [
    ''        => $s('alltypes'),
    'course'  => $s('target_course'),
    'teacher' => $s('target_teacher'),
];
echo html_writer::start_div('col-12 col-sm-6 col-md-3');
echo html_writer::label($s('filterbytarget'), 'nit-flt-type', true, ['class' => 'form-label small fw-semibold text-muted mb-1']);
echo html_writer::select($typeoptions, 'type', $type, false, ['id' => 'nit-flt-type', 'class' => 'form-select']);
echo html_writer::end_div();

// Search input.
echo html_writer::start_div('col-12 col-md-6');
echo html_writer::label(get_string('search'), 'nit-flt-q', true, ['class' => 'form-label small fw-semibold text-muted mb-1']);
echo html_writer::start_div('input-group');
echo html_writer::empty_tag('input', [
    'type'        => 'search',
    'name'        => 'q',
    'id'          => 'nit-flt-q',
    'value'       => $q,
    'class'       => 'form-control',
    'placeholder' => $s('searchplaceholder'),
]);
echo html_writer::tag('button', '<i class="fa fa-search"></i> ' . get_string('search'), ['type' => 'submit', 'class' => 'btn btn-primary']);
if ($rating || $type || $q !== '') {
    $reseturl = new moodle_url('/local/nit_reviews/reviews.php', ['id' => $courseid]);
    echo html_writer::link($reseturl, '<i class="fa fa-times"></i>', ['class' => 'btn btn-outline-secondary', 'title' => get_string('reset')]);
}
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div(); // End filter form.

// Reviews list.
if (empty($reviews)) {
    echo html_writer::start_div('card border-0 shadow-sm rounded-4 text-center py-5 my-4');
    echo html_writer::start_div('card-body');
    echo html_writer::div('<i class="fa fa-star-o text-muted opacity-50 display-3 mb-3"></i>');
    echo html_writer::tag('h5', $s('noreviews'), ['class' => 'text-muted fw-bold']);
    echo html_writer::end_div();
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('d-flex flex-column gap-3 mb-4');

    foreach ($reviews as $rev) {
        echo html_writer::start_div('card border-0 shadow-sm rounded-4 p-3 nit-crv-item');
        echo html_writer::start_div('card-body p-2');

        // Top row: Author pic + name + target badge + date.
        echo html_writer::start_div('d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3');
        echo html_writer::start_div('d-flex align-items-center gap-3');

        $pic = $rev['pictureurl'] ? html_writer::img($rev['pictureurl'], '', [
            'class' => 'rounded-circle object-fit-cover shadow-sm',
            'style' => 'width: 48px; height: 48px;',
        ]) : '<div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 48px; height: 48px;"><i class="fa fa-user"></i></div>';

        echo $pic;

        echo html_writer::start_div();
        echo html_writer::div(s($rev['fullname']), 'fw-bold text-dark fs-6');

        // Review target badge.
        if (!empty($rev['teacherid']) && isset($teachers[$rev['teacherid']])) {
            $tname = fullname($teachers[$rev['teacherid']]);
            $badge = html_writer::span('<i class="fa fa-user-circle me-1 ms-1"></i> ' . $s('target_teacher_name', $tname),
                'badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill small');
        } else if (!empty($rev['teacherid'])) {
            $badge = html_writer::span('<i class="fa fa-user-circle me-1 ms-1"></i> ' . $s('target_teacher'),
                'badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill small');
        } else {
            $badge = html_writer::span('<i class="fa fa-book me-1 ms-1"></i> ' . $s('target_course'),
                'badge bg-primary-subtle text-primary-emphasis border border-primary-subtle rounded-pill small');
        }
        echo html_writer::div($badge, 'mt-1');
        echo html_writer::end_div();

        echo html_writer::end_div(); // End author info.

        // Stars + Date.
        echo html_writer::start_div('text-end');
        $revstars = '';
        for ($snum = 1; $snum <= 5; $snum++) {
            $scolor = $snum <= $rev['rating'] ? 'text-warning' : 'text-muted opacity-25';
            $revstars .= '<i class="fa fa-star ' . $scolor . '"></i> ';
        }
        echo html_writer::div($revstars, 'fs-6 mb-1');
        echo html_writer::div(userdate($rev['timemodified'], get_string('strftimedatetimeshort')), 'text-muted small');
        echo html_writer::end_div();

        echo html_writer::end_div(); // End top row.

        // Comment text.
        if ($rev['review'] !== '') {
            echo html_writer::div(nl2br(s($rev['review'])),
                'p-3 rounded-3 bg-light text-dark fs-6 lh-base border-start border-4 border-warning mt-2');
        } else {
            echo html_writer::div('<span class="text-muted fst-italic small"><i class="fa fa-comment-o me-1"></i> ' . $s('nocomment') . '</span>', 'mt-1');
        }

        echo html_writer::end_div(); // End card body.
        echo html_writer::end_div(); // End review card.
    }

    echo html_writer::end_div(); // End list.

    // Pagination bar.
    if ($total > $perpage) {
        echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);
    }
}

echo html_writer::end_div(); // End wrap.

echo $OUTPUT->footer();

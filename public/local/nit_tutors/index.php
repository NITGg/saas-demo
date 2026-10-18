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
 * Find a teacher: every bookable teacher, filtered by text, subject, price per lesson, day and
 * time of day, rating, Super Tutor, and the admin's filter fields. The courses search
 * (/local/nit_category/index.php) links here with the same ?q= ("Teachers" tab).
 *
 * Plain GET filters, like the course catalogue. Public like the catalogue; behind the log-in when
 * the site forces log-in.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_tutors\local\search;
use local_nit_lessons\service\teacher_service;

$raw = [
    'q' => optional_param('q', '', PARAM_TEXT),
    'subject' => optional_param('subject', '', PARAM_TEXT),
    'pricemin' => optional_param('pricemin', '', PARAM_RAW_TRIMMED),
    'pricemax' => optional_param('pricemax', '', PARAM_RAW_TRIMMED),
    'day' => optional_param('day', '', PARAM_RAW_TRIMMED),
    'time' => optional_param('time', '', PARAM_ALPHA),
    'rating' => optional_param('rating', '', PARAM_RAW_TRIMMED),
    'badge' => optional_param('badge', '', PARAM_RAW_TRIMMED),
    'sort' => optional_param('sort', 'recommended', PARAM_ALPHAEXT),
    'f' => optional_param_array('f', [], PARAM_TEXT),
];
$page = optional_param('page', 0, PARAM_INT);
$perpage = 24;
$filters = search::normalise($raw);

// The URL of the current filters (without the page), for links and the pager.
$params = array_filter([
    'q' => $filters['q'], 'subject' => $filters['subject'], 'pricemin' => $filters['pricemin'],
    'pricemax' => $filters['pricemax'], 'day' => $filters['day'], 'time' => $filters['time'],
    'rating' => $filters['rating'] ?: null, 'badge' => $filters['badge'] ?: null,
    'sort' => $filters['sort'] !== 'recommended' ? $filters['sort'] : null,
], fn($v) => $v !== null && $v !== '');
foreach ($filters['f'] as $fid => $v) {
    $params["f[$fid]"] = $v;
}
$url = new moodle_url('/local/nit_tutors/index.php', $params);

$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
if (!empty($CFG->forcelogin)) {
    require_login();
}
$PAGE->set_pagelayout('nit_fullwidth');
$PAGE->set_title(get_string('findteacher', 'local_nit_tutors'));
$PAGE->set_heading('');
$PAGE->add_body_class('nit-tutor-search');

$viewer = isloggedin() && !isguestuser() ? (int) $USER->id : 0;
$result = search::run($filters, $viewer, $page, $perpage);

// Filter controls.
$subjects = [];
foreach (teacher_service::site_subjects() as $s) {
    $subjects[] = ['value' => $s, 'label' => format_string($s), 'selected' => $s === $filters['subject']];
}
$days = [];
foreach ([6, 0, 1, 2, 3, 4, 5] as $d) {
    $days[] = ['value' => $d, 'label' => get_string('day' . $d, 'local_nit_lessons'), 'selected' => $filters['day'] === $d];
}
$times = [];
foreach (array_keys(search::TIMES) as $t) {
    $times[] = ['value' => $t, 'label' => get_string('time_' . $t, 'local_nit_tutors'), 'selected' => $filters['time'] === $t];
}
$ratings = [];
foreach (['4.5', '4', '3'] as $r) {
    $ratings[] = ['value' => $r, 'label' => get_string('ratingup', 'local_nit_tutors', $r),
        'selected' => (string) $filters['rating'] === $r];
}
$sorts = [];
foreach (search::SORTS as $s) {
    $sorts[] = ['value' => $s, 'label' => get_string('sort_' . $s, 'local_nit_tutors'), 'selected' => $filters['sort'] === $s];
}
$fieldfilters = [];
foreach (search::filter_fields() as $f) {
    $options = [];
    foreach (\local_nit_tutors\local\fields::options($f) as $o) {
        $options[] = ['value' => $o, 'label' => format_string($o), 'selected' => ($filters['f'][$f->id] ?? '') === $o];
    }
    $fieldfilters[] = ['id' => $f->id, 'label' => format_string($f->name), 'options' => $options];
}

$badges = [];
foreach (\local_nit_tutors\local\badge::all() as $b) {
    $badges[] = ['value' => $b->id, 'label' => format_string($b->name), 'selected' => (int) $b->id === $filters['badge']];
}

$cards = array_map([search::class, 'card'], $result['teachers']);
$active = count($params) - (isset($params['q']) ? 1 : 0) - (isset($params['sort']) ? 1 : 0);

$context = [
    'actionurl' => (new moodle_url('/local/nit_tutors/index.php'))->out(false),
    'reseturl' => (new moodle_url('/local/nit_tutors/index.php', $filters['q'] !== '' ? ['q' => $filters['q']] : []))->out(false),
    'q' => $filters['q'],
    'pricemin' => $filters['pricemin'],
    'pricemax' => $filters['pricemax'],
    'hasbadgefilter' => !empty($badges),
    'badges' => $badges,
    'subjects' => $subjects,
    'days' => $days,
    'times' => $times,
    'ratings' => $ratings,
    'sorts' => $sorts,
    'fieldfilters' => $fieldfilters,
    'hasfilters' => $active > 0,
    'total' => $result['total'],
    'teachers' => $cards,
    'hasteachers' => !empty($cards),
    'coursesurl' => $filters['q'] !== ''
        ? (new moodle_url('/local/nit_category/index.php', ['q' => $filters['q']]))->out(false) : '',
    'applyurl' => (new moodle_url('/local/nit_tutors/apply.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_nit_tutors/search_page', $context);
echo $OUTPUT->paging_bar($result['total'], $page, $perpage, $url);
echo $OUTPUT->footer();

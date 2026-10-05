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
 * The parent dashboard — bassthalk.com/parent_dashboard rebuilt over real data.
 *
 * Public, no account: the parent types the student's phone and their own phone
 * (one of the guardian phones the student registered). When both match, the
 * page shows the student's courses; picking one lists its lectures with the
 * student's videos, homework and exams. Look: theme/nit/scss/components/_bthparent.scss
 * (Brand Colors → Bassthalk → "Parent dashboard").
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_parent\dashboard;

$PAGE->set_context(context_system::instance());
// Served as /parent_dashboard (public/.htaccess rewrites it here), like Bassthalk.
$PAGE->set_url(new moodle_url('/parent_dashboard'));
$PAGE->set_pagelayout('nit_fullwidth');
$PAGE->set_title(get_string('dashboard', 'local_parent'));
$PAGE->set_heading('');
$PAGE->add_body_class('nit-bth-parent-page');

$childphone = '';
$parentphone = '';
$error = '';
$courses = null;

if (data_submitted()) {
    require_sesskey();
    $childphone = trim(optional_param('childphone', '', PARAM_TEXT));
    $parentphone = trim(optional_param('parentphone', '', PARAM_TEXT));
    $ip = getremoteaddr();
    if (dashboard::is_blocked($ip)) {
        $error = get_string('err_toomany', 'local_parent');
    } else if (!dashboard::valid_phone($childphone) || !dashboard::valid_phone($parentphone)) {
        $error = get_string('err_invalidphone', 'local_parent');
    } else if ($studentid = dashboard::find_student($childphone, $parentphone)) {
        $courses = dashboard::report($studentid);
    } else {
        dashboard::record_failure($ip);
        $error = get_string('err_nomatch', 'local_parent');
    }
}

/**
 * "الفيديو الأول", "الواجب الثاني", … — Bassthalk names the items by their order.
 *
 * @param string $kind videos | homework | exams
 * @param int $n 1-based position in the lecture
 * @return string
 */
function local_parent_item_label(string $kind, int $n): string {
    $ord = $n <= 10 ? get_string('ord' . $n, 'local_parent') : (string) $n;
    return get_string('label_' . $kind, 'local_parent', $ord);
}

/**
 * A percent the way Bassthalk prints it: whole numbers bare, else one decimal.
 *
 * @param float|null $percent
 * @return string
 */
function local_parent_percent(?float $percent): string {
    if ($percent === null) {
        return '—';
    }
    return floor($percent) == $percent ? (string) (int) $percent : format_float($percent, 1);
}

$context = [
    'action' => $PAGE->url->out(false),
    'sesskey' => sesskey(),
    'childphone' => $childphone,
    'parentphone' => $parentphone,
    'error' => $error,
    'verified' => $courses !== null,
    'heroimg' => $OUTPUT->image_url('bassthalk_parent', 'theme_nit')->out(false),
    'decoimg' => $OUTPUT->image_url('bassthalk_parent_deco', 'theme_nit')->out(false),
    'arrowimg' => $OUTPUT->image_url('bassthalk_down_arrow', 'theme_nit')->out(false),
    'hascourses' => !empty($courses),
    'courses' => [],
];

foreach ($courses ?? [] as $i => $course) {
    $sections = [];
    foreach ($course['sections'] as $section) {
        $columns = [];
        foreach (['videos', 'homework', 'exams'] as $kind) {
            $items = [];
            foreach ($section[$kind] as $n => $item) {
                $items[] = [
                    'label' => local_parent_item_label($kind, $n + 1),
                    'name' => $item['name'],
                    'done' => $item['state'] === 'done',
                    'pending' => $item['state'] === 'pending',
                    'absent' => $item['state'] === 'absent',
                    'percent' => local_parent_percent($item['percent']),
                    'donetext' => get_string('done_' . $kind, 'local_parent'),
                    'doneafter' => $kind === 'videos' ? get_string('done_videos_after', 'local_parent') : '',
                    'pendingtext' => get_string('pending_' . $kind, 'local_parent'),
                    'absenttext' => get_string('absent_' . $kind, 'local_parent'),
                ];
            }
            $columns[] = ['items' => $items, 'hasitems' => !empty($items),
                'none' => get_string('none_' . $kind, 'local_parent')];
        }
        $empty = !$section['videos'] && !$section['homework'] && !$section['exams'];
        $sections[] = [
            'name' => $section['name'],
            'date' => (!$empty && $section['started'])
                ? userdate($section['started'], '%Y-%m-%d %H:%M:%S', 99, false) : '',
            'empty' => $empty,
            'columns' => $columns,
        ];
    }
    $context['courses'][] = [
        'index' => $i,
        'id' => $course['id'],
        'name' => $course['name'],
        'hassections' => !empty($sections),
        'sections' => $sections,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_parent/dashboard', $context);
echo $OUTPUT->footer();

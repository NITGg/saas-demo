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
 * NIT homepage template picker (admin only).
 *
 * Lets NIT choose one of the ten homepage templates (T1..T10) for this academy.
 * Applying rewrites the Site-home section blocks from the chosen template set —
 * the academy owner then applies their images and brand colour on top.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use theme_nit\local\homepage_templates;
use theme_nit\local\template_applier;

admin_externalpage_setup('theme_nit_homepage');

$pageurl = new moodle_url('/theme/nit/homepage.php');

// Apply a chosen template.
if (($data = data_submitted()) && confirm_sesskey() && !empty($data->applytemplate)) {
    $tid = required_param('tid', PARAM_ALPHANUMEXT);
    if (!homepage_templates::exists($tid)) {
        redirect($pageurl, get_string('templateunknown', 'theme_nit'), null,
            \core\output\notification::NOTIFY_ERROR);
    }
    $log = [];
    template_applier::apply($tid, $log);
    redirect($pageurl,
        get_string('templateapplied', 'theme_nit', homepage_templates::name($tid)),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

$current = homepage_templates::current();
$isar = current_language() === 'ar';

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('homepagetemplates', 'theme_nit'));

echo html_writer::div(
    get_string('homepagetemplates_desc', 'theme_nit') . ' ' .
    html_writer::link(new moodle_url('/'), get_string('viewhomepage', 'theme_nit'),
        ['target' => '_blank', 'rel' => 'noopener']),
    'box generalbox');

echo html_writer::start_div('', ['style' =>
    'display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px;margin-top:8px;']);

foreach (homepage_templates::all() as $id => $t) {
    $iscurrent = ($id === $current);
    $name = $isar ? $t['name']['ar'] : $t['name']['en'];
    $blurb = $isar ? $t['blurb']['ar'] : $t['blurb']['en'];

    // Card.
    echo html_writer::start_div('', ['style' =>
        'border:1px solid ' . ($iscurrent ? $t['accent'] : '#dee2e6') . ';border-radius:14px;overflow:hidden;' .
        'box-shadow:0 6px 18px rgba(0,0,0,.06);background:#fff;display:flex;flex-direction:column;']);

    // Swatch band (illustrative signature colour).
    echo html_writer::div(
        strtoupper($id) . ($iscurrent ? ' · ' . get_string('templatecurrent', 'theme_nit') : ''),
        '', ['style' =>
        'height:74px;display:flex;align-items:flex-end;padding:10px 14px;font-weight:700;font-size:12px;' .
        'letter-spacing:.06em;color:#fff;' .
        'background:linear-gradient(135deg,' . $t['accent'] . ',' .
        ($t['dark'] ? '#07090D' : 'color-mix(in srgb,' . $t['accent'] . ' 55%,#000)') . ');']);

    echo html_writer::start_div('', ['style' => 'padding:14px 16px 16px;display:flex;flex-direction:column;flex:1;']);
    echo html_writer::tag('div', s($name), ['style' => 'font-size:16px;font-weight:700;']);
    echo html_writer::tag('div', s($t['font']),
        ['style' => 'font-size:12px;color:#6c757d;margin-top:2px;']);
    echo html_writer::tag('p', s($blurb),
        ['style' => 'font-size:13px;color:#495057;line-height:1.6;margin:10px 0 14px;flex:1;']);

    // Apply form.
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false),
        'style' => 'margin:0;']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tid', 'value' => $id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'applytemplate', 'value' => 1]);
    $btnlabel = $iscurrent ? get_string('templatereapply', 'theme_nit') : get_string('applytemplate', 'theme_nit');
    $confirm = get_string('applyconfirm', 'theme_nit', $name);
    echo html_writer::tag('button', $btnlabel, [
        'type' => 'submit',
        'class' => 'btn ' . ($iscurrent ? 'btn-outline-secondary' : 'btn-primary'),
        'style' => 'width:100%;',
        'onclick' => 'return confirm(' . json_encode($confirm) . ');',
    ]);
    echo html_writer::end_tag('form');

    echo html_writer::end_div(); // padding.
    echo html_writer::end_div(); // card.
}

echo html_writer::end_div(); // grid.

echo html_writer::div(get_string('applywarning', 'theme_nit'),
    'alert alert-info', ['style' => 'margin-top:20px;']);

echo $OUTPUT->footer();

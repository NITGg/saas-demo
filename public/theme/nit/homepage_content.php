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
 * NIT homepage CONTENT editor (admin).
 *
 * Edits the editable-content hooks of the applied homepage template — the same
 * fields NIT/the owner fill on the build page. Text is bilingual (EN/AR); images
 * are uploaded and inlined. Moodle-dynamic content (courses/subscriptions/…) is
 * not touched.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use theme_nit\local\homepage_content;

admin_externalpage_setup('theme_nit_homepage_content');

$pageurl = new moodle_url('/theme/nit/homepage_content.php');
$fields = homepage_content::fields();

// ── Save ──
if (($data = data_submitted()) && confirm_sesskey()) {
    $manifest = ['text' => [], 'href' => [], 'images' => []];
    foreach ($fields as $f) {
        $key = $f['key'];
        if ($f['type'] === 'text') {
            $en = trim(optional_param('t_' . $key . '_en', '', PARAM_TEXT));
            $ar = trim(optional_param('t_' . $key . '_ar', '', PARAM_TEXT));
            if ($en !== '' || $ar !== '') {
                $manifest['text'][$key] = ['en' => $en, 'ar' => $ar];
            }
        } else if ($f['type'] === 'link') {
            $v = trim(optional_param('h_' . $key, '', PARAM_URL));
            if ($v !== '') {
                $manifest['href'][$key] = $v;
            }
        } else if ($f['type'] === 'image') {
            $field = 'i_' . $key;
            if (!empty($_FILES[$field]['name']) && ($_FILES[$field]['error'] ?? 4) === UPLOAD_ERR_OK
                    && is_uploaded_file($_FILES[$field]['tmp_name'])) {
                $mime = mime_content_type($_FILES[$field]['tmp_name']) ?: 'image/png';
                if (strpos($mime, 'image/') === 0) {
                    $manifest['images'][$key] = 'data:' . $mime . ';base64,'
                        . base64_encode((string) file_get_contents($_FILES[$field]['tmp_name']));
                }
            }
        }
    }
    homepage_content::apply($manifest);
    redirect($pageurl, get_string('contentsaved', 'theme_nit'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$current = homepage_content::read();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('homepagecontent', 'theme_nit'));
echo html_writer::div(
    get_string('homepagecontent_desc', 'theme_nit') . ' ' .
    html_writer::link(new moodle_url('/'), get_string('viewhomepage', 'theme_nit'),
        ['target' => '_blank', 'rel' => 'noopener']),
    'box generalbox');

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false),
    'enctype' => 'multipart/form-data']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

$lastgroup = null;
foreach ($fields as $f) {
    if ($f['group'] !== $lastgroup) {
        if ($lastgroup !== null) {
            echo html_writer::end_div();
        }
        echo html_writer::tag('h4', ucfirst($f['group']), ['style' => 'margin:22px 0 8px;']);
        echo html_writer::start_div('', ['style' => 'display:grid;gap:14px;']);
        $lastgroup = $f['group'];
    }
    $key = $f['key'];
    echo html_writer::start_div('', ['style' => 'border:1px solid #dee2e6;border-radius:8px;padding:12px 14px;']);
    echo html_writer::tag('div', s($f['label']), ['style' => 'font-weight:600;margin-bottom:8px;']);

    if ($f['type'] === 'text') {
        $en = is_array($current[$key] ?? null) ? ($current[$key]['en'] ?? '') : '';
        $ar = is_array($current[$key] ?? null) ? ($current[$key]['ar'] ?? '') : '';
        $tag = !empty($f['multiline']) ? 'textarea' : 'input';
        foreach (['en' => 'English', 'ar' => 'العربية'] as $l => $lbl) {
            echo html_writer::tag('label', $lbl, ['style' => 'display:block;font-size:12px;color:#6c757d;margin:6px 0 2px;']);
            $val = $l === 'en' ? $en : $ar;
            if ($tag === 'textarea') {
                echo html_writer::tag('textarea', s($val), ['name' => 't_' . $key . '_' . $l,
                    'rows' => 2, 'style' => 'width:100%;', 'dir' => $l === 'ar' ? 'rtl' : 'ltr']);
            } else {
                echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 't_' . $key . '_' . $l,
                    'value' => $val, 'style' => 'width:100%;', 'dir' => $l === 'ar' ? 'rtl' : 'ltr']);
            }
        }
    } else if ($f['type'] === 'link') {
        echo html_writer::empty_tag('input', ['type' => 'url', 'name' => 'h_' . $key,
            'value' => $current[$key . '__href'] ?? '', 'style' => 'width:100%;', 'placeholder' => 'https://…']);
    } else { // image.
        echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'i_' . $key, 'accept' => 'image/*']);
        echo html_writer::tag('div', get_string('contentimghint', 'theme_nit'),
            ['style' => 'font-size:12px;color:#8a8a82;margin-top:4px;']);
    }
    echo html_writer::end_div();
}
if ($lastgroup !== null) {
    echo html_writer::end_div();
}

echo html_writer::div(
    html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']),
    '', ['style' => 'margin:22px 0;']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();

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
 * Campaign link builder: a tracked (UTM) link to any page, for ads and posts.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_analytics\tracker;

admin_externalpage_setup('local_nit_analytics_links');

$s = fn(string $key, $a = null) => get_string($key, 'local_nit_analytics', $a);
$path = optional_param('path', '/', PARAM_RAW_TRIMMED);
$source = optional_param('source', 'google', PARAM_TEXT);
$medium = optional_param('medium', 'cpc', PARAM_TEXT);
$campaign = optional_param('campaign', '', PARAM_TEXT);
$content = optional_param('content', '', PARAM_TEXT);
$url = $campaign !== '' ? tracker::campaign_url($path, $source, $medium, $campaign, $content) : '';

echo $OUTPUT->header();
echo $OUTPUT->heading($s('linksheading'));
echo html_writer::tag('p', $s('linksintro'), ['class' => 'text-muted']);

$field = function (string $name, string $value, string $help, array $suggest = []) use ($s): string {
    $id = 'nit-utm-' . $name;
    $list = $suggest ? html_writer::tag('datalist', implode('', array_map(
        fn($v) => html_writer::tag('option', '', ['value' => $v]), $suggest)), ['id' => $id . '-list']) : '';
    return html_writer::div(
        html_writer::label($s('links_' . $name), $id, true, ['class' => 'form-label fw-bold']) .
        html_writer::empty_tag('input', ['type' => 'text', 'name' => $name, 'id' => $id, 'value' => $value,
            'class' => 'form-control'] + ($suggest ? ['list' => $id . '-list'] : [])) .
        $list . html_writer::tag('small', $help, ['class' => 'form-text text-muted']), 'mb-3');
};

echo html_writer::start_tag('form', ['method' => 'get', 'action' => new moodle_url('/local/nit_analytics/links.php'),
    'style' => 'max-width:640px']);
echo $field('path', $path, $s('links_path_help'));
echo $field('source', $source, $s('links_source_help'), ['google', 'facebook', 'instagram', 'tiktok', 'youtube',
    'whatsapp', 'email', 'sms']);
echo $field('medium', $medium, $s('links_medium_help'), ['cpc', 'paid_social', 'social', 'email', 'sms', 'referral']);
echo $field('campaign', $campaign, $s('links_campaign_help'));
echo $field('content', $content, $s('links_content_help'));
echo html_writer::tag('button', $s('links_build'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

if ($campaign !== '' && $url === '') {
    echo $OUTPUT->notification($s('links_missing'), 'warning');
} else if ($url !== '') {
    echo html_writer::start_div('mt-4', ['style' => 'max-width:640px']);
    echo html_writer::label($s('links_result'), 'nit-utm-result', true, ['class' => 'form-label fw-bold']);
    echo html_writer::div(
        html_writer::empty_tag('input', ['type' => 'text', 'readonly' => 'readonly', 'id' => 'nit-utm-result',
            'value' => $url, 'class' => 'form-control', 'dir' => 'ltr', 'onclick' => 'this.select()']) .
        html_writer::tag('button', $s('links_copy'), ['type' => 'button', 'class' => 'btn btn-outline-secondary',
            'onclick' => "navigator.clipboard.writeText(document.getElementById('nit-utm-result').value);"
                . "this.textContent=" . json_encode($s('links_copied')) . ";"]),
        'input-group');
    echo html_writer::tag('p', $s('links_googleads'), ['class' => 'form-text text-muted mt-2']);
    echo html_writer::end_div();
}

echo html_writer::tag('p', html_writer::link(new moodle_url('/local/nit_analytics/report.php'), $s('funnelreport')),
    ['class' => 'mt-4']);
echo $OUTPUT->footer();

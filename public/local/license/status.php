<?php
/**
 * Licence status: current tier, expiry, and live usage vs. limits.
 * Site administration → Plugins → Local plugins → Academy licence — status.
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_license\license;
use local_license\enforcer;

admin_externalpage_setup('local_license_status');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('status_heading', 'local_license'));

// ── Summary ────────────────────────────────────────────────────────────────
$enforced = license::is_enforced();
$expiry   = license::expiry();
$daysleft = license::days_left();

$summary = new html_table();
$summary->attributes['class'] = 'generaltable';
$summary->data = [
    [get_string('status_enforced', 'local_license'),
        $enforced
            ? html_writer::tag('b', get_string('status_on', 'local_license'), ['style' => 'color:#1E7A55'])
            : html_writer::tag('b', get_string('status_off', 'local_license'), ['style' => 'color:#B23A34'])],
    [get_string('status_tier', 'local_license'), s(license::tiername())],
    [get_string('status_videosrc', 'local_license'), s(license::video_source())],
    [get_string('status_expiry', 'local_license'),
        $expiry ? userdate($expiry) : get_string('status_never', 'local_license')],
    [get_string('status_daysleft', 'local_license'),
        ($expiry && $enforced) ? max(0, $daysleft) : '—'],
    [get_string('status_features', 'local_license'),
        (license::tierdef()['features']
            ? s(implode(', ', license::tierdef()['features']))
            : get_string('status_none', 'local_license'))],
];
echo html_writer::table($summary);

// ── Usage vs limits ──────────────────────────────────────────────────────────
echo $OUTPUT->heading(get_string('usage_heading', 'local_license'), 4);

$fmt = function (int $count, int $limit): string {
    return $count . ' / ' . ($limit < 0 ? '∞' : $limit);
};

$usage = new html_table();
$usage->attributes['class'] = 'generaltable';
$usage->head = [get_string('usage_item', 'local_license'), get_string('usage_used', 'local_license')];
$usage->data = [
    [get_string('usage_courses', 'local_license'),  $fmt(enforcer::count_courses(),  license::max_courses())],
    [get_string('usage_teachers', 'local_license'), $fmt(enforcer::count_teachers(), license::max_teachers())],
    [get_string('usage_quiz', 'local_license'),     $fmt(enforcer::count_bucket('quiz'),  license::bucket_limit('quiz'))],
    [get_string('usage_video', 'local_license'),    $fmt(enforcer::count_bucket('video'), license::bucket_limit('video'))],
    [get_string('usage_pdf', 'local_license'),      $fmt(enforcer::count_bucket('pdf'),   license::bucket_limit('pdf'))],
];
echo html_writer::table($usage);

if (!$enforced) {
    echo $OUTPUT->notification(get_string('enabled_desc', 'local_license'),
        \core\output\notification::NOTIFY_INFO);
}

echo $OUTPUT->footer();

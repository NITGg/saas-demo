<?php
/**
 * My plan & subscription — owner/admin-facing view of the current licence,
 * subscription term, and live usage, with a link out to the NIT account for
 * price/invoices/upgrade/renew.
 *
 * Unlike status.php (Site administration → siteadmin only), this page is gated
 * on local/academy:manageplatform so the academy OWNER (academymanager) can see
 * it too. It never exposes price or billing history — those live in nit2.
 */

require(__DIR__ . '/../../config.php');

use local_license\license;
use local_license\enforcer;

require_login();
$context = context_system::instance();
require_capability('local/academy:manageplatform', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/license/my.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('myplan_heading', 'local_license'));
$PAGE->set_heading(get_string('myplan_heading', 'local_license'));

$enforced   = license::is_enforced();
$expiry     = license::expiry();
$subat      = license::subscribed_at();
$daysleft   = license::days_left();
$suspended  = license::is_suspended();
$expired    = license::is_expired();
$def        = license::tierdef();
$storagegb  = (int) ($def['storagegb'] ?? -1);
$autorenew  = get_config('local_license', 'autorenew'); // '1' | '0' | false
$renewurl   = trim((string) get_config('local_license', 'renewurl'));

$statuskey = $suspended ? 'sub_status_suspended' : ($expired ? 'sub_status_expired' : 'sub_status_active');
$statuscolour = $suspended ? '#B23A34' : ($expired ? '#B23A34' : '#1E7A55');

echo $OUTPUT->header();

// ── Your plan ────────────────────────────────────────────────────────────────
echo $OUTPUT->heading(get_string('pkg_heading', 'local_license'), 4);
$pkg = new html_table();
$pkg->attributes['class'] = 'generaltable';
$pkg->data = [
    [get_string('status_tier', 'local_license'), s(license::tiername())],
    [get_string('status_videosrc', 'local_license'), s(license::video_source())],
    [get_string('pkg_storage', 'local_license'),
        $storagegb > 0
            ? get_string('pkg_gb', 'local_license', $storagegb)
            : get_string('pkg_unlimited', 'local_license')],
    [get_string('status_features', 'local_license'),
        (!empty($def['features'])
            ? s(implode(', ', (array) $def['features']))
            : get_string('status_none', 'local_license'))],
];
echo html_writer::table($pkg);

// ── Subscription ───────────────────────────────────────────────────────────────
echo $OUTPUT->heading(get_string('sub_heading', 'local_license'), 4);
$sub = new html_table();
$sub->attributes['class'] = 'generaltable';
$sub->data = [
    [get_string('sub_subscribed', 'local_license'),
        $subat ? userdate($subat, get_string('strftimedate', 'langconfig')) : get_string('sub_unknown', 'local_license')],
    [get_string('sub_expires', 'local_license'),
        $expiry ? userdate($expiry, get_string('strftimedate', 'langconfig')) : get_string('status_never', 'local_license')],
    [get_string('sub_daysleft', 'local_license'),
        ($expiry && $enforced) ? max(0, $daysleft) : '—'],
    [get_string('sub_status', 'local_license'),
        html_writer::tag('b', get_string($statuskey, 'local_license'), ['style' => "color:{$statuscolour}"])],
];
// Only show the auto-renew row when the control plane has told us its state.
if ($autorenew === '1' || $autorenew === '0') {
    $sub->data[] = [get_string('sub_autorenew', 'local_license'),
        get_string($autorenew === '1' ? 'yes' : 'no', 'local_license')];
}
echo html_writer::table($sub);

// ── Usage vs limits ────────────────────────────────────────────────────────────
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

// ── Billing & upgrades (handled in the NIT account, not here) ──────────────────
echo $OUTPUT->heading(get_string('billing_heading', 'local_license'), 4);
echo html_writer::tag('p', get_string('billing_note', 'local_license'));
if ($renewurl !== '') {
    echo html_writer::link(
        $renewurl,
        get_string('billing_manage', 'local_license'),
        ['class' => 'btn btn-primary', 'target' => '_blank', 'rel' => 'noopener']
    );
}

echo $OUTPUT->footer();

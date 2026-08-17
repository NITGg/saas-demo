<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_payments_reports');
require_capability('local/payments:viewreports', context_system::instance());

$PAGE->set_url(new moodle_url('/local/payments/report.php'));
$PAGE->set_title(get_string('reports', 'local_payments'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reports', 'local_payments'));

// Revenue summary.
$total_revenue = $DB->get_record_sql(
    "SELECT COUNT(*) as total_transactions, SUM(amount) as total_revenue
     FROM {local_payments_transactions} WHERE status = :status",
    ['status' => 'completed']
);

// Revenue by country.
$by_country = $DB->get_records_sql(
    "SELECT country, currency, COUNT(*) as count, SUM(amount) as revenue
     FROM {local_payments_transactions} WHERE status = :status
     GROUP BY country, currency ORDER BY revenue DESC",
    ['status' => 'completed']
);

// Revenue by provider.
$by_provider = $DB->get_records_sql(
    "SELECT p.display_name, t.currency, COUNT(*) as count, SUM(t.amount) as revenue
     FROM {local_payments_transactions} t
     JOIN {local_payments_providers} p ON p.id = t.provider_id
     WHERE t.status = :status
     GROUP BY p.display_name, t.currency ORDER BY revenue DESC",
    ['status' => 'completed']
);

// Top courses.
$top_courses = $DB->get_records_sql(
    "SELECT c.fullname, t.currency, COUNT(*) as purchases, SUM(t.amount) as revenue
     FROM {local_payments_transactions} t
     JOIN {course} c ON c.id = t.courseid
     WHERE t.status = :status
     GROUP BY c.fullname, t.currency ORDER BY revenue DESC
     LIMIT 20",
    ['status' => 'completed']
);

// Failed payments count.
$failed_count = $DB->count_records('local_payments_transactions', ['status' => 'failed']);
$refund_count = $DB->count_records('local_payments_transactions', ['status' => 'refunded']);
$pending_count = $DB->count_records_select('local_payments_transactions',
    "status = :status AND expires_at > :now",
    ['status' => 'pending', 'now' => time()]);

$templatedata = [
    'total_transactions' => (int) ($total_revenue->total_transactions ?? 0),
    'total_revenue' => number_format((float) ($total_revenue->total_revenue ?? 0), 2),
    'failed_count' => $failed_count,
    'refund_count' => $refund_count,
    'pending_count' => $pending_count,
    'by_country' => array_values(array_map(function($r) {
        return ['country' => $r->country, 'currency' => $r->currency,
                'count' => $r->count, 'revenue' => number_format((float) $r->revenue, 2)];
    }, $by_country)),
    'has_country_data' => !empty($by_country),
    'by_provider' => array_values(array_map(function($r) {
        return ['provider' => $r->display_name, 'currency' => $r->currency,
                'count' => $r->count, 'revenue' => number_format((float) $r->revenue, 2)];
    }, $by_provider)),
    'has_provider_data' => !empty($by_provider),
    'top_courses' => array_values(array_map(function($r) {
        return ['course' => $r->fullname, 'currency' => $r->currency,
                'purchases' => $r->purchases, 'revenue' => number_format((float) $r->revenue, 2)];
    }, $top_courses)),
    'has_course_data' => !empty($top_courses),
];

echo $OUTPUT->render_from_template('local_payments/admin_report', $templatedata);
echo $OUTPUT->footer();

<?php
require_once(__DIR__ . '/../../config.php');

require_login();

$PAGE->set_url(new moodle_url('/local/payments/history.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('paymenthistory', 'local_payments'));
$PAGE->set_heading(get_string('paymenthistory', 'local_payments'));
$PAGE->set_pagelayout('standard');

$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;

$transactions = $DB->get_records_select(
    'local_payments_transactions',
    'userid = :userid',
    ['userid' => $USER->id],
    'timecreated DESC',
    '*',
    $page * $perpage,
    $perpage
);

$total = $DB->count_records('local_payments_transactions', ['userid' => $USER->id]);

echo $OUTPUT->header();
if (empty($transactions)) {
    echo $OUTPUT->notification(get_string('nopayments', 'local_payments'), 'info');
} else {
    // Batch-load course names + invoices once, instead of a query per row (N+1).
    $courseids = [];
    $txnids = [];
    foreach ($transactions as $txn) {
        $courseids[(int) $txn->courseid] = true;
        $txnids[] = (int) $txn->id;
    }
    $coursenames = $courseids
        ? $DB->get_records_list('course', 'id', array_keys($courseids), '', 'id, fullname')
        : [];
    $invoices = $txnids
        ? $DB->get_records_list('local_payments_invoices', 'transaction_id', $txnids, '', 'transaction_id, invoice_number')
        : [];

    $rows = [];
    foreach ($transactions as $txn) {
        $coursename = isset($coursenames[$txn->courseid]) ? $coursenames[$txn->courseid]->fullname : '';
        $invoice = $invoices[$txn->id] ?? null;

        $status_class = '';
        switch ($txn->status) {
            case 'completed': $status_class = 'text-bg-success'; break;
            case 'pending': $status_class = 'text-bg-warning'; break;
            case 'failed': case 'cancelled': $status_class = 'text-bg-danger'; break;
            case 'refunded': case 'voided': $status_class = 'text-bg-info'; break;
            default: $status_class = 'text-bg-secondary';
        }

        $rows[] = [
            'order_id' => $txn->order_id,
            'course_name' => $coursename ?: '-',
            'amount' => number_format((float) $txn->amount, 2) . ' ' . $txn->currency,
            'status' => $txn->status,
            'status_class' => $status_class,
            'payment_method' => $txn->payment_method_type ?? '-',
            'invoice_number' => $invoice->invoice_number ?? '-',
            'date' => userdate($txn->timecreated),
        ];
    }

    $templatedata = [
        'transactions' => $rows,
        'has_transactions' => true,
    ];
    echo $OUTPUT->render_from_template('local_payments/payment_history', $templatedata);
    echo $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url('/local/payments/history.php'));
}

echo $OUTPUT->footer();

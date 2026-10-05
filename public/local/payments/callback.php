<?php
require_once(__DIR__ . '/../../config.php');

$order_id      = required_param('order_id', PARAM_TEXT);
// Kashier appends paymentStatus=SUCCESS|FAILED to the redirect URL.
$kashier_status = strtoupper(optional_param('paymentStatus', '', PARAM_ALPHANUMEXT));

require_login();

// Ownership gate: only the buyer (or a staff member with viewalltransactions)
// may act on / read an order. Without this, any logged-in user could flip
// another user's PENDING order to FAILED, or read its course, by guessing the
// semi-sequential order id.
$owntx = $DB->get_record('local_payments_transactions', ['order_id' => $order_id]);
if ($owntx && (int) $owntx->userid !== (int) $USER->id
        && !has_capability('local/payments:viewalltransactions', context_system::instance())) {
    throw new moodle_exception('invalidaccess', 'error');
}

$PAGE->set_url(new moodle_url('/local/payments/callback.php', ['order_id' => $order_id]));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');

// ── FAILED redirect from Kashier ────────────────────────────────────────────
// Kashier told us right now that the payment failed. Update the DB and show
// the failure page immediately — no need to call Kashier's API again.
if ($kashier_status === 'FAILED') {
    $transaction = $DB->get_record('local_payments_transactions', ['order_id' => $order_id]);

    if ($transaction && $transaction->status === \local_payments\status_machine::PENDING) {
        $DB->update_record('local_payments_transactions', (object) [
            'id'            => $transaction->id,
            'status'        => \local_payments\status_machine::FAILED,
            'reject_reason' => 'Payment failed at provider (redirect)',
            'timemodified'  => time(),
        ]);
        // Release any coupon/offer reservation this failed checkout was holding.
        if (class_exists('\local_nit_commerce\discount_manager')) {
            \local_nit_commerce\discount_manager::release_usage((int) $transaction->id);
        }
    }

    $PAGE->set_title(get_string('payment_failure', 'local_payments'));
    echo $OUTPUT->header();
    $templatedata = [
        'success'      => false,
        'status'       => 'FAILED',
        'order_id'     => $order_id,
        'retry_url'    => \local_payments\local\return_pages::retry($transaction ?: null)->out(false),
        'history_url'  => (new moodle_url('/local/payments/history.php'))->out(false),
    ];
    echo $OUTPUT->render_from_template('local_payments/payment_failure', $templatedata);
    echo $OUTPUT->footer();
    exit;
}

// ── SUCCESS / unknown redirect — read DB state set by the webhook ────────────
// The webhook (server-to-server) is the authoritative path for enrollment.
// We just read what the webhook already wrote. The Kashier API fallback inside
// verify_callback() is only reached when the webhook hasn't fired yet.
try {
    $result = \local_payments\manager::verify_callback($order_id);

    if ($result->success) {
        // Packages and subscriptions are not tied to a single course, so there
        // is no course page to land on. Send the student straight to the home
        // dashboard with a success notice instead of the generic success page.
        $item_type = $result->item_type ?? 'course';
        if ($item_type === 'wallet_topup') {
            // Back to where the student started the top-up (e.g. a lesson's buy page), else the wallet.
            $paidtx = $DB->get_record('local_payments_transactions', ['order_id' => $order_id], 'amount, metadata');
            $back = (string) (json_decode($paidtx->metadata ?? '{}')->return_url ?? '');
            $backurl = ($back !== '' && strpos($back, '/') === 0 && strpos($back, '//') !== 0)
                ? new moodle_url($back) : new moodle_url('/local/nit_finance/wallet.php');
            redirect($backurl,
                get_string('toppedup', 'local_nit_finance', \local_nit_finance\local\money::format((int) round($paidtx->amount * 100))),
                null, \core\output\notification::NOTIFY_SUCCESS);
        }
        if ($item_type === 'package') {
            // Lesson package: back to "My lessons & Flex", where the new Flex balance shows.
            redirect(new moodle_url('/local/nit_lessons/student.php', ['tab' => 'packages']),
                get_string('payment_success', 'local_payments'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
        if ($item_type === 'subscription') {
            // Back to the page the subscription was bought from (e.g. the student hub), else home.
            $paidtx = $DB->get_record('local_payments_transactions', ['order_id' => $order_id], 'metadata');
            redirect(
                \local_payments\local\return_pages::started_from($paidtx ?: null) ?? new moodle_url('/'),
                get_string('payment_success', 'local_payments'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        $PAGE->set_title(get_string('payment_success', 'local_payments'));
        echo $OUTPUT->header();

        $course = $DB->get_record('course', ['id' => $result->courseid], 'id, fullname');
        $templatedata = [
            'success'      => true,
            'course_name'  => $course->fullname ?? '',
            'course_url'   => (new moodle_url('/course/view.php', ['id' => $result->courseid]))->out(false),
            'order_id'     => $order_id,
            'history_url'  => (new moodle_url('/local/payments/history.php'))->out(false),
        ];
        echo $OUTPUT->render_from_template('local_payments/payment_success', $templatedata);
    } else {
        $PAGE->set_title(get_string('payment_failure', 'local_payments'));
        echo $OUTPUT->header();

        $transaction = $DB->get_record('local_payments_transactions', ['order_id' => $order_id]);
        $templatedata = [
            'success'     => false,
            'status'      => $result->status,
            'order_id'    => $order_id,
            'retry_url'   => \local_payments\local\return_pages::retry($transaction ?: null)->out(false),
            'history_url' => (new moodle_url('/local/payments/history.php'))->out(false),
        ];
        echo $OUTPUT->render_from_template('local_payments/payment_failure', $templatedata);
    }
} catch (\Exception $e) {
    $PAGE->set_title(get_string('payment_failure', 'local_payments'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification($e->getMessage(), 'error');
    echo $OUTPUT->continue_button(new moodle_url('/'));
}

echo $OUTPUT->footer();

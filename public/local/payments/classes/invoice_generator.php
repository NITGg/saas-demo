<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

class invoice_generator {

    /**
     * Generate an invoice for a completed transaction.
     *
     * @param int $transaction_id
     * @return int Invoice ID.
     */
    public static function create(int $transaction_id): int {
        global $DB;

        $txn = $DB->get_record('local_payments_transactions', ['id' => $transaction_id], '*', MUST_EXIST);

        // Check for existing invoice.
        $existing = $DB->get_record('local_payments_invoices', ['transaction_id' => $transaction_id]);
        if ($existing) {
            return (int) $existing->id;
        }

        // Allocate a sequential number and insert, retrying on collision: two
        // completions racing can compute the same number and hit the unique
        // index. On failure, re-check for a now-existing invoice (same-transaction
        // race) or recompute the next number and try again — so a losing insert
        // no longer leaves the transaction with no invoice.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $invoice = (object) [
                'transaction_id' => $transaction_id,
                'userid' => $txn->userid,
                'invoice_number' => self::generate_number(),
                'amount' => $txn->amount,
                'currency' => $txn->currency,
                'status' => 'issued',
                'pdf_path' => null,
                'timecreated' => time(),
            ];
            try {
                return (int) $DB->insert_record('local_payments_invoices', $invoice);
            } catch (\dml_write_exception $e) {
                $existing = $DB->get_record('local_payments_invoices', ['transaction_id' => $transaction_id]);
                if ($existing) {
                    return (int) $existing->id;
                }
                // Otherwise the invoice_number collided — loop to recompute it.
            }
        }
        throw new \moodle_exception('error', 'moodle', '', null, 'Could not allocate an invoice number');
    }

    /**
     * Generate a sequential invoice number: INV-YYYY-NNNNNNN
     */
    private static function generate_number(): string {
        global $DB;

        $year = date('Y');
        $prefix = "INV-{$year}-";

        $last = $DB->get_record_sql(
            "SELECT invoice_number FROM {local_payments_invoices}
             WHERE invoice_number LIKE :prefix
             ORDER BY id DESC LIMIT 1",
            ['prefix' => $prefix . '%']
        );

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->invoice_number);
            $seq = ((int) end($parts)) + 1;
        }

        return $prefix . str_pad($seq, 7, '0', STR_PAD_LEFT);
    }
}

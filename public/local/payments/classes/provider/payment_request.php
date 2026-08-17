<?php
namespace local_payments\provider;

defined('MOODLE_INTERNAL') || die();

class payment_request {
    public string $order_id;
    public float $amount;
    public string $currency;
    public string $description;
    public int $userid;
    public int $courseid;
    public string $customer_email;
    public string $customer_reference;
    public string $display_lang;
    public string $webhook_url;
    public string $success_url;
    public string $failure_url;
    public array $metadata;
    public int $transaction_id;

    public function __construct(array $data) {
        $this->order_id = $data['order_id'];
        $this->amount = (float) $data['amount'];
        $this->currency = $data['currency'];
        $this->description = $data['description'] ?? '';
        $this->userid = (int) $data['userid'];
        $this->courseid = (int) $data['courseid'];
        $this->customer_email = $data['customer_email'] ?? '';
        $this->customer_reference = $data['customer_reference'] ?? '';
        $this->display_lang = $data['display_lang'] ?? 'en';
        $this->webhook_url = $data['webhook_url'] ?? '';
        $this->success_url = $data['success_url'] ?? '';
        $this->failure_url = $data['failure_url'] ?? '';
        $this->metadata = $data['metadata'] ?? [];
        $this->transaction_id = (int) ($data['transaction_id'] ?? 0);
    }
}

<?php
namespace local_payments\provider;

defined('MOODLE_INTERNAL') || die();

class transaction_info {
    public bool $found;
    public string $status;
    public float $amount;
    public string $currency;
    public string $provider_txn_id;
    public string $provider_order_id;
    public string $payment_method_type;
    public array $raw_response;

    public function __construct(array $data) {
        $this->found = $data['found'] ?? false;
        $this->status = $data['status'] ?? '';
        $this->amount = (float) ($data['amount'] ?? 0);
        $this->currency = $data['currency'] ?? '';
        $this->provider_txn_id = $data['provider_txn_id'] ?? '';
        $this->provider_order_id = $data['provider_order_id'] ?? '';
        $this->payment_method_type = $data['payment_method_type'] ?? '';
        $this->raw_response = $data['raw_response'] ?? [];
    }
}

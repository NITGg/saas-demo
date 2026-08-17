<?php
namespace local_payments\provider;

defined('MOODLE_INTERNAL') || die();

class verification_result {
    public bool $verified;
    public string $status;
    public float $amount;
    public string $currency;
    public string $provider_txn_id;
    public string $provider_order_id;
    public string $payment_method_type;
    public string $error_message;
    public array $raw_response;

    public function __construct(array $data) {
        $this->verified = $data['verified'] ?? false;
        $this->status = $data['status'] ?? '';
        $this->amount = (float) ($data['amount'] ?? 0);
        $this->currency = $data['currency'] ?? '';
        $this->provider_txn_id = $data['provider_txn_id'] ?? '';
        $this->provider_order_id = $data['provider_order_id'] ?? '';
        $this->payment_method_type = $data['payment_method_type'] ?? '';
        $this->error_message = $data['error_message'] ?? '';
        $this->raw_response = $data['raw_response'] ?? [];
    }
}
